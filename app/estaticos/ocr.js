// Lectura de comprobantes: en Administración › Transferencias (con el
// análisis a la vista) y en la página de la orden, apenas el comprador sube
// el comprobante (sólo lee y guarda el texto; el servidor lo cruza con los
// pagos de Mercado Pago y, si coincide, confirma la compra en el momento).
//
// El texto se saca en el navegador de quien verifica (gratis, sin mandar el
// comprobante a ningún servicio y sin cargar el servidor): de un PDF se toma
// el texto que trae; de una foto, con Tesseract.js (OCR en español). El texto
// se guarda en la orden para no volver a leerlo.
//
// Después se buscan los datos que importan para verificar —monto, fecha,
// número de operación, CUIT, CBU— y se marca si el monto coincide, si aparece
// el código de la orden y si el destino es nuestra cuenta. Es una AYUDA: la
// lectura puede fallar, y la confirmación se hace mirando la cuenta.
(() => {
  const cajas = [...document.querySelectorAll('[data-ocr]')];
  const comprador = document.querySelector('[data-ocr-comprador]');
  if (!cajas.length && !comprador) return;
  const csrf = document.querySelector('[data-csrf-admin]')?.dataset.csrfAdmin || '';
  const TESSERACT = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
  const PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.min.mjs';
  const PDFJS_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.4.168/pdf.worker.min.mjs';

  const cargarScript = (src) => new Promise((ok, mal) => {
    if (document.querySelector(`script[src="${src}"]`)) return ok();
    const s = Object.assign(document.createElement('script'), { src, onload: ok, onerror: mal });
    document.head.appendChild(s);
  });
  let trabajador = null;
  const ocr = async (blob) => {
    await cargarScript(TESSERACT);
    trabajador ??= await window.Tesseract.createWorker('spa');
    return (await trabajador.recognize(blob)).data.text;
  };
  const textoDePdf = async (blob) => {
    const pdfjs = await import(PDFJS);
    pdfjs.GlobalWorkerOptions.workerSrc = PDFJS_WORKER;
    const doc = await pdfjs.getDocument({ data: await blob.arrayBuffer() }).promise;
    let texto = '';
    for (let i = 1; i <= Math.min(doc.numPages, 2); i++) {
      const pag = await doc.getPage(i);
      texto += (await pag.getTextContent()).items.map((t) => t.str).join(' ') + '\n';
    }
    if (texto.replace(/\s/g, '').length > 30) return texto;
    // PDF escaneado, sin texto: se dibuja la primera página y se lee como foto.
    const pag = await doc.getPage(1);
    const vp = pag.getViewport({ scale: 2 });
    const lienzo = Object.assign(document.createElement('canvas'), { width: vp.width, height: vp.height });
    await pag.render({ canvasContext: lienzo.getContext('2d'), viewport: vp }).promise;
    return ocr(await new Promise((ok) => lienzo.toBlob(ok, 'image/png')));
  };

  // ── Qué se busca en el texto ──
  const soloDigitos = (s) => s.replace(/\D/g, '');
  // Importes en centavos.
  // «24.000,37» → 2400037 · «24.000» → 2400000 · «24000.37» → 2400037
  const aCentavos = (s) => {
    let t = s.replace(/[^\d.,]/g, '');
    let cent = 0;
    const m = t.match(/[.,](\d{1,2})$/);
    // Una coma final siempre es decimal; un punto final sólo si no parece de miles.
    if (m && (t.endsWith(',' + m[1]) || (t.match(/\./g) || []).length === 1 && !t.includes(','))) {
      cent = parseInt(m[1].padEnd(2, '0'), 10);
      t = t.slice(0, -m[0].length);
    }
    return parseInt(t.replace(/[.,]/g, '') || '0', 10) * 100 + cent;
  };
  const analizar = (texto, c) => {
    const plano = texto.replace(/\s+/g, ' ');
    const total = +c.dataset.total;
    const montos = [...new Set([...plano.matchAll(/\$\s?([\d][\d.\s]*(?:[.,]\d{1,2})?)/g)].map((m) => aCentavos(m[1])).filter((n) => n > 0))];
    // Algunos comprobantes ponen el monto sin el signo: se acepta el importe
    // si aparece escrito tal cual (con sus centavos).
    const entero = Math.floor(total / 100), cs = String(total % 100).padStart(2, '0');
    const totalEscrito = new RegExp('(^|[^\\d])' + entero.toLocaleString('es-AR').replace(/\./g, '[.\\s]?') + (total % 100 ? '[,.]' + cs : '([,.]00)?') + '([^\\d]|$)').test(plano);
    const codigo = c.dataset.codigo;
    const alias = (c.dataset.alias || '').toLowerCase();
    const cbu = c.dataset.cbu || '';
    const fecha = plano.match(/\b(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}|\d{1,2} de [a-záéíóú]+( de \d{4})?)\b/i)?.[0];
    const hora = plano.match(/\b([01]?\d|2[0-3])[:.]([0-5]\d)( ?h| ?hs)?\b/)?.[0];
    const operacion = plano.match(/(?:operaci[oó]n|transacci[oó]n|comprobante|referencia|c[oó]digo|id)\s*(?:n[º°o.]*|nro\.?|número)?\s*[:#]?\s*([A-Z0-9][A-Z0-9-]{5,})/i)?.[1];
    const cuits = [...new Set([...plano.matchAll(/\b(20|23|24|27|30|33|34)-?\s?(\d{8})-?\s?(\d)\b/g)].map((m) => `${m[1]}-${m[2]}-${m[3]}`))];
    const cbus = [...new Set([...plano.matchAll(/\b\d[\d\s]{20,30}\d\b/g)].map((m) => soloDigitos(m[0])).filter((d) => d.length === 22))];
    return {
      montos, fecha: fecha && (fecha + (hora ? ' · ' + hora : '')), operacion, cuits, cbus,
      montoOk: montos.includes(total) || totalEscrito,
      codigoOk: plano.toUpperCase().replace(/\s/g, '').includes(codigo),
      destinoOk: (alias && plano.toLowerCase().includes(alias)) || (cbu && soloDigitos(plano).includes(cbu)),
      tieneDestino: !!(alias || cbu),
    };
  };

  const pesos = (cent) => '$ ' + Math.floor(cent / 100).toLocaleString('es-AR') + (cent % 100 ? ',' + String(cent % 100).padStart(2, '0') : '');
  const marca = (ok, si, no) => `<li class="${ok ? 'ocr-si' : 'ocr-no'}">${ok ? '✓' : '✗'} ${ok ? si : no}</li>`;
  const mostrar = (c, texto) => {
    const a = analizar(texto, c);
    const datos = [
      ['Montos', a.montos.length ? a.montos.map(pesos).join(', ') : '—'],
      ['Fecha', a.fecha || '—'],
      ['Operación', a.operacion || '—'],
      ['CUIT', a.cuits.join(', ') || '—'],
      ['CBU / CVU', a.cbus.join(', ') || '—'],
    ];
    const esc = (s) => String(s).replace(/[&<>"]/g, (x) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[x]);
    c.innerHTML = `
      <ul class="ocr-marcas">
        ${marca(a.montoOk, 'El monto coincide (' + pesos(+c.dataset.total) + ')', 'No encontré ' + pesos(+c.dataset.total) + ' en el comprobante')}
        ${marca(a.codigoOk, 'Aparece el código ' + esc(c.dataset.codigo), 'No aparece el código ' + esc(c.dataset.codigo) + ' (puede no haberlo puesto)')}
        ${a.tieneDestino ? marca(a.destinoOk, 'El destino es nuestra cuenta', 'No encontré nuestro alias o CBU') : ''}
      </ul>
      <dl class="ocr-datos">${datos.map(([k, v]) => `<div><dt>${k}</dt><dd>${esc(v)}</dd></div>`).join('')}</dl>
      <details class="ocr-texto"><summary>Texto leído</summary><pre>${esc(texto.trim() || '(no se pudo leer texto)')}</pre></details>
      <p class="ocr-aviso">Lectura automática: puede equivocarse. Confirmá mirando la cuenta.</p>`;
    // Si el monto no coincide, la tarjeta se marca y el botón lo advierte
    // antes de aprobar: un error ahí regala entradas.
    const tarjeta = c.closest('.transf');
    const boton = tarjeta?.querySelector('.btn-confirmar');
    if (tarjeta && !a.montoOk) {
      tarjeta.classList.add('transf-dudosa');
      if (boton) boton.dataset.confirmar = 'ATENCIÓN: el comprobante no muestra ' + pesos(+c.dataset.total) + (a.montos.length ? ' (dice ' + a.montos.map(pesos).join(', ') + ')' : '') + '. ¿Viste en la cuenta que entró el monto completo?';
    }
  };

  const leer = async (url) => {
    const blob = await (await fetch(url, { credentials: 'same-origin' })).blob();
    if (/heic|heif/i.test(blob.type)) return null;
    return blob.type === 'application/pdf' ? textoDePdf(blob) : ocr(blob);
  };

  if (comprador) {
    (async () => {
      let r = {};
      try {
        const texto = await leer(comprador.dataset.url);
        r = await (await fetch('/api/orden/' + comprador.dataset.codigo + '/ocr', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': comprador.dataset.csrf },
          body: new URLSearchParams({ texto: texto ?? '', _csrf: comprador.dataset.csrf }),
        })).json();
      } catch (_) {}
      trabajador?.terminate();
      if (r.confirmada) { comprador.classList.add('ok'); comprador.textContent = '¡Encontramos tu pago! Confirmando…'; setTimeout(() => location.reload(), 1200); }
      else comprador.textContent = 'Comprobante revisado. Apenas veamos el pago en la cuenta, te llegan las entradas.';
    })();
    return;
  }

  (async () => {
    // De a uno: el OCR es pesado y la primera vez descarga el idioma (~2 MB).
    for (const c of cajas) {
      if (c.dataset.texto) { mostrar(c, c.dataset.texto); continue; }
      c.innerHTML = '<p class="ocr-leyendo">Leyendo el comprobante…</p>';
      try {
        const blob = await (await fetch(c.dataset.url, { credentials: 'same-origin' })).blob();
        if (/heic|heif/i.test(blob.type)) { c.innerHTML = '<p class="ocr-aviso">Foto en formato HEIC: no se puede leer automáticamente. Abrila y verificá a ojo.</p>'; continue; }
        const texto = blob.type === 'application/pdf' ? await textoDePdf(blob) : await ocr(blob);
        mostrar(c, texto);
        fetch('/api/admin/ordenes/' + c.dataset.orden + '/ocr', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': csrf },
          body: new URLSearchParams({ texto, _csrf: csrf }),
        }).catch(() => {});
      } catch (err) {
        c.innerHTML = '<p class="ocr-aviso">No se pudo leer el comprobante automáticamente. Abrilo y verificá a ojo.</p>';
      }
    }
    trabajador?.terminate();
  })();
})();
