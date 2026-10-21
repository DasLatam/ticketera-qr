// Comportamiento común. Todo funciona sin JS (formularios comunes); esto
// sólo lo hace más ágil.
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const pesos = (n) => (n === 0 ? '$ 0' : '$ ' + n.toLocaleString('es-AR'));

  // Menú en celular
  const btnMenu = $('[data-menu]');
  if (btnMenu) {
    btnMenu.addEventListener('click', () => {
      const abierto = btnMenu.getAttribute('aria-expanded') === 'true';
      btnMenu.setAttribute('aria-expanded', String(!abierto));
      document.body.classList.toggle('menu-abierto', !abierto);
    });
  }

  // Filtros: se aplican solos al cambiar; el buscador espera a que se deje de tipear.
  $$('[data-filtros]').forEach((form) => {
    let t;
    // La dirección queda limpia (sólo lo que cambia algo): ?cuando=mes y no
    // ?q=&cuando=mes&orden=fecha&disp=0&disp=1&cat=… Se puede compartir.
    const POR_DEFECTO = { orden: 'fecha', disp: '1' };
    const ir = () => {
      const datos = {};
      new FormData(form).forEach((v, k) => { datos[k] = String(v); }); // el último valor gana (disp)
      const q = new URLSearchParams();
      Object.entries(datos).forEach(([k, v]) => { if (v !== '' && POR_DEFECTO[k] !== v) q.set(k, v); });
      const destino = (form.getAttribute('action') || location.pathname) + (q.toString() ? '?' + q : '');
      location.href = destino;
    };
    form.addEventListener('submit', (ev) => { ev.preventDefault(); ir(); });
    form.addEventListener('change', (ev) => { if (ev.target.type !== 'search') ir(); });
    $$('input[type=search]', form).forEach((inp) => {
      inp.addEventListener('input', () => { clearTimeout(t); t = setTimeout(ir, 600); });
      inp.addEventListener('search', ir);
    });
    // El hidden disp=0 va antes del checkbox: si el checkbox está tildado,
    // PHP se queda con el último valor (1).
  });

  // Confirmaciones de acciones destructivas
  document.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-confirmar]');
    if (b && !confirm(b.dataset.confirmar)) ev.preventDefault();
  });

  // Selección de entradas en la ficha del evento
  const compra = $('[data-compra]');
  if (compra) {
    const max = +compra.dataset.max;
    const total = $('[data-total]', compra);
    const btn = $('[data-comprar]', compra);
    const recalcular = () => {
      let suma = 0, n = 0;
      $$('.tipo', compra).forEach((li) => {
        const inp = $('input[type=number]', li);
        if (!inp) return;
        const c = +inp.value || 0;
        n += c;
        suma += c * +li.dataset.precio;
      });
      if (total) total.textContent = pesos(suma);
      if (btn) {
        btn.disabled = n === 0;
        btn.textContent = n === 0 ? 'Comprar' : `Comprar ${n} ${n === 1 ? 'entrada' : 'entradas'}`;
      }
      // Tope por compra: cuando se llega, los «+» se apagan.
      $$('[data-mas]', compra).forEach((b) => {
        const inp = b.parentElement.querySelector('input');
        b.disabled = n >= max || +inp.value >= +inp.max;
      });
      $$('[data-menos]', compra).forEach((b) => (b.disabled = +b.parentElement.querySelector('input').value <= 0));
    };
    $$('[data-contador]', compra).forEach((c) => {
      const inp = $('input', c);
      $('[data-menos]', c).addEventListener('click', () => { inp.value = Math.max(0, (+inp.value || 0) - 1); recalcular(); });
      $('[data-mas]', c).addEventListener('click', () => { inp.value = Math.min(+inp.max, (+inp.value || 0) + 1); recalcular(); });
      inp.addEventListener('input', recalcular);
    });
    recalcular();
  }

  // QR: se dibujan en el navegador a partir del código (data-qr).
  if (window.qrcode) {
    $$('[data-qr]').forEach((el) => {
      const qr = window.qrcode(0, 'M');
      qr.addData(el.dataset.qr);
      qr.make();
      el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: el.hasAttribute('data-qr-grande') ? 2 : 0, scalable: true });
    });
  }

  // Galería deslizable con puntos
  $$('[data-galeria]').forEach((g) => {
    const pista = $('.galeria-pista', g);
    const puntos = $$('.galeria-puntos button', g);
    puntos.forEach((p, i) => p.addEventListener('click', () => pista.scrollTo({ left: i * pista.clientWidth, behavior: 'smooth' })));
    pista.addEventListener('scroll', () => {
      const i = Math.round(pista.scrollLeft / pista.clientWidth);
      puntos.forEach((p, k) => p.toggleAttribute('aria-current', k === i));
    }, { passive: true });
  });

  // Editor de eventos
  const fe = $('[data-form-evento]');
  const cont = fe && $('[data-tipos]', fe);
  if (fe && cont) {
    const aforo = $('[data-aforo]', fe);
    const aviso = $('[data-aviso-cupos]', fe);
    const revisarCupos = () => {
      const suma = $$('[data-tipo-fila]', cont)
        .filter((f) => !($('[data-quitar]', f) || {}).checked)
        .reduce((s, f) => s + (+$('[data-cupo]', f).value || 0), 0);
      const a = +aforo.value || 0;
      aviso.textContent = suma > a
        ? `Los cupos suman ${suma} y el aforo es ${a}: se venderán como máximo ${a}, lo que se agote primero.`
        : suma < a ? `Los cupos suman ${suma}: quedan ${a - suma} lugares del aforo sin asignar a ningún tipo.` : `Los cupos suman exactamente el aforo (${a}).`;
    };
    fe.addEventListener('input', (ev) => { if (ev.target.matches('[data-cupo], [data-aforo]')) revisarCupos(); });
    cont.addEventListener('change', (ev) => {
      if (ev.target.matches('[data-quitar]')) {
        const fila = ev.target.closest('[data-tipo-fila]');
        if (!$('input[name$="[id]"]', fila).value) fila.remove();
        else fila.classList.toggle('se-quita', ev.target.checked);
        revisarCupos();
      }
    });
    $('[data-agregar-tipo]', fe).addEventListener('click', () => {
      const filas = $$('[data-tipo-fila]', cont);
      const i = Date.now();
      const nueva = filas[filas.length - 1].cloneNode(true);
      $$('input', nueva).forEach((inp) => {
        inp.name = inp.name.replace(/tipo\[[^\]]+\]/, `tipo[n${i}]`);
        if (inp.type === 'checkbox') inp.checked = inp.name.endsWith('[activo]');
        else inp.value = inp.name.endsWith('[precio]') || inp.name.endsWith('[cupo]') ? '0' : '';
      });
      $$('small', nueva).forEach((s) => s.remove());
      nueva.classList.remove('se-quita');
      // Una fila nueva siempre se puede quitar, aunque la copiada tuviera ventas.
      const ultimo = nueva.lastElementChild;
      if (!ultimo.matches('.quitar')) {
        ultimo.outerHTML = `<label class="quitar" title="Quitar"><input type="checkbox" name="tipo[n${i}][borrar]" value="1" data-quitar><span aria-hidden="true">×</span><span class="sr">Quitar</span></label>`;
      }
      cont.appendChild(nueva);
      $('input[type=text]', nueva).focus();
    });
    revisarCupos();
  }
  const titulo = fe && $('[data-titulo]', fe);
  if (titulo) {
    // Slug y vista previa de buscadores
    const slug = $('[data-slug]', fe);
    const mt = $('[data-meta-titulo]', fe);
    const md = $('[data-meta-desc]', fe);
    const bajada = $('[name=bajada]', fe);
    const aSlug = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    let slugTocado = !!slug.value;
    slug.addEventListener('input', () => { slugTocado = true; slug.value = aSlug(slug.value).slice(0, 80) || ''; previa(); });
    const previa = () => {
      if (!slugTocado) slug.value = aSlug(titulo.value).slice(0, 80);
      $('[data-pg-slug]', fe).textContent = slug.value || '…';
      $('[data-pg-titulo]', fe).textContent = (mt.value || titulo.value || 'Título del evento') + ' · ' + (document.documentElement.dataset.app || '');
      const d = md.value || bajada.value;
      $('[data-pg-desc]', fe).textContent = d.length > 160 ? d.slice(0, 157) + '…' : d;
    };
    [titulo, mt, md, bajada].forEach((x) => x.addEventListener('input', previa));
    previa();
  }
  const soltar = fe && $('[data-soltar]', fe);
  if (soltar) {
    // Imágenes: vista previa y arrastrar
    const archivo = $('input[type=file]', soltar);
    const previas = $('[data-previas]', fe);
    const mostrarPrevias = () => {
      previas.innerHTML = '';
      Array.from(archivo.files).forEach((f) => {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.alt = f.name;
        previas.appendChild(img);
      });
      if (archivo.files.length) {
        const p = document.createElement('p');
        p.className = 'tenue chico';
        p.textContent = `${archivo.files.length} para subir al guardar.`;
        previas.appendChild(p);
      }
    };
    archivo.addEventListener('change', mostrarPrevias);
    ['dragenter', 'dragover'].forEach((t) => soltar.addEventListener(t, (ev) => { ev.preventDefault(); soltar.classList.add('encima'); }));
    ['dragleave', 'drop'].forEach((t) => soltar.addEventListener(t, () => soltar.classList.remove('encima')));
    soltar.addEventListener('drop', (ev) => {
      ev.preventDefault();
      archivo.files = ev.dataTransfer.files;
      mostrarPrevias();
    });
  }
})();

// La barra fija de compra se esconde cuando la caja de entradas ya está a la vista.
(() => {
  const barra = document.querySelector('[data-barra-compra]');
  const caja = document.getElementById('entradas');
  if (!barra || !caja || !('IntersectionObserver' in window)) return;
  new IntersectionObserver(([e]) => barra.classList.toggle('oculta', e.isIntersecting), { threshold: 0.15 }).observe(caja);
})();

// Compartir. WhatsApp y X tienen enlace propio. Instagram y TikTok no tienen
// forma de compartir un link desde la web: en el celular se abre el menú
// nativo (donde aparecen las dos apps); en la compu se copia el link y se avisa.
(() => {
  const caja = document.querySelector('[data-compartir]');
  if (!caja) return;
  const url = caja.dataset.url, texto = caja.dataset.texto;
  const aviso = caja.querySelector('[data-compartir-aviso]');
  let t;
  const avisar = (m) => { aviso.textContent = m; aviso.classList.add('visible'); clearTimeout(t); t = setTimeout(() => aviso.classList.remove('visible'), 3500); };
  const copiar = async () => {
    try { await navigator.clipboard.writeText(url); return true; }
    catch (_) {
      const ta = Object.assign(document.createElement('textarea'), { value: url });
      document.body.appendChild(ta); ta.select();
      const ok = document.execCommand('copy'); ta.remove(); return ok;
    }
  };
  caja.addEventListener('click', async (ev) => {
    const b = ev.target.closest('[data-red]');
    if (!b) return;
    const red = b.dataset.red;
    if (red === 'copiar') { avisar((await copiar()) ? 'Link copiado' : url); return; }
    const nombre = red === 'instagram' ? 'Instagram' : 'TikTok';
    if (navigator.share && matchMedia('(pointer: coarse)').matches) {
      try { await navigator.share({ title: texto, text: texto, url }); } catch (_) {}
      return;
    }
    avisar((await copiar()) ? `Link copiado: pegalo en tu historia, bio o mensaje de ${nombre}.` : url);
  });
})();

document.querySelector('[data-mas-resenas]')?.addEventListener('click', (ev) => {
  document.querySelectorAll('[data-resenas] [hidden]').forEach((li) => li.removeAttribute('hidden'));
  ev.currentTarget.remove();
});

// ── Aplicación instalable y entradas sin conexión ─────────────────────────
(() => {
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === '127.0.0.1' || location.hostname === 'localhost')) {
    // updateViaCache 'none': el navegador busca sw.js nuevo en cada visita,
    // sin pasar por su caché. Cada deploy cambia sw.js (lleva la versión).
    navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' }).then((r) => r.update()).catch(() => {});
    // Cuando entra el service worker de una versión nueva, la página que se
    // está viendo es de la anterior: se recarga sola, una vez. No si es la
    // primera instalación, ni en la puerta (cámara abierta), ni si la persona
    // ya empezó a escribir en un formulario.
    const habiaUno = !!navigator.serviceWorker.controller;
    let escribio = false;
    document.addEventListener('input', () => { escribio = true; }, { once: true });
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (!habiaUno || escribio || document.body.classList.contains('pantalla-puerta')) return;
      if (sessionStorage.getItem('tqr-recargada') === '1') return;
      try { sessionStorage.setItem('tqr-recargada', '1'); } catch (e) {}
      location.reload();
    });
    addEventListener('load', () => setTimeout(() => { try { sessionStorage.removeItem('tqr-recargada'); } catch (e) {} }, 5000));
  }
  // Al cerrar sesión se borran las entradas guardadas en el celular: si no,
  // quien use después el mismo teléfono podría verlas sin conexión.
  document.querySelector('[data-salir]')?.addEventListener('submit', () => {
    navigator.serviceWorker?.controller?.postMessage('borrar-entradas');
    if (window.caches) caches.delete('tqr-entradas');
  });

  const caja = document.querySelector('[data-instalar]');
  const yaInstalada = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (caja && yaInstalada) caja.hidden = true;
  if (!caja || yaInstalada) return;

  // Pestaña según el teléfono: iPhone/iPad (también el iPad que se presenta
  // como Mac) o Android.
  const ua = navigator.userAgent;
  const esIOS = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
  const elegir = (cual) => {
    caja.querySelectorAll('[data-tab]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.tab === cual)));
    caja.querySelector('#inst-ios').hidden = cual !== 'ios';
    caja.querySelector('#inst-android').hidden = cual !== 'android';
  };
  caja.querySelectorAll('[data-tab]').forEach((b) => b.addEventListener('click', () => elegir(b.dataset.tab)));
  elegir(/Android/.test(ua) ? 'android' : esIOS ? 'ios' : 'ios');

  // Android/Chrome avisan que se puede instalar: botón directo.
  let pedido = null;
  const boton = caja.querySelector('[data-instalar-boton]');
  window.addEventListener('beforeinstallprompt', (ev) => { ev.preventDefault(); pedido = ev; boton.hidden = false; });
  boton.addEventListener('click', async () => {
    if (!pedido) return;
    pedido.prompt();
    await pedido.userChoice.catch(() => {});
    pedido = null; boton.hidden = true;
  });
  window.addEventListener('appinstalled', () => { caja.hidden = true; });
})();

// Sin conexión: aviso arriba de las entradas guardadas.
(() => {
  const marcar = () => document.body.classList.toggle('sin-red', !navigator.onLine);
  addEventListener('online', marcar); addEventListener('offline', marcar); marcar();
})();

// Pantalla de la entrada: no se apaga, y se entera sola cuando la puerta la quema.
(() => {
  const raiz = document.querySelector('[data-entrada]');
  if (!raiz) return;
  let lock = null;
  const despierta = () => { if ('wakeLock' in navigator && document.visibilityState === 'visible') navigator.wakeLock.request('screen').then((l) => (lock = l)).catch(() => {}); };
  despierta();
  document.addEventListener('visibilitychange', despierta);
  // Pago por transferencia sin verificar: se consulta cada 20 s y, cuando se
  // aprueba, se recarga para mostrar el QR.
  if (raiz.dataset.pago === 'pendiente') {
    setInterval(async () => {
      if (document.visibilityState !== 'visible' || !navigator.onLine) return;
      try {
        const d = await (await fetch('/api/entrada/' + raiz.dataset.entrada + '/estado', { credentials: 'same-origin', cache: 'no-store' })).json();
        if (d.pago && d.pago !== 'pendiente') location.reload();
      } catch (_) {}
    }, 20000);
    return;
  }
  if (raiz.dataset.estado !== 'valida' || raiz.dataset.pago !== 'pagada') return;
  const ticket = raiz.querySelector('[data-ticket]');
  const consultar = async () => {
    if (document.visibilityState !== 'visible' || !navigator.onLine) return;
    try {
      const r = await fetch('/api/entrada/' + raiz.dataset.entrada + '/estado', { credentials: 'same-origin', cache: 'no-store' });
      const d = await r.json();
      if (d.estado && d.estado !== 'valida') {
        clearInterval(cada);
        ticket.className = 'ticket ticket-' + d.estado + ' recien-usada';
        raiz.querySelector('[data-sello]').hidden = false;
        raiz.querySelector('[data-sello-texto]').textContent = d.estado === 'usada' ? '¡Adentro!' : 'Anulada';
        raiz.querySelector('[data-sello-hora]').textContent = d.usada_en || '';
        raiz.querySelector('[data-ayuda]').hidden = true;
        if (navigator.vibrate) navigator.vibrate(120);
        lock?.release?.();
        // La copia guardada para sin conexión tiene que dejar de mostrarla válida.
        if (window.caches) caches.open('tqr-entradas').then((c) => c.delete('/entrada/' + raiz.dataset.entrada));
      }
    } catch (_) {}
  };
  const cada = setInterval(consultar, 4000);
})();

// «Mostrar contraseña» en todo campo de clave (pedido de los clientes,
// 2026-10-04). El botón va dentro del <label>: al ser contenido interactivo,
// tocarlo no le pasa el foco al campo.
(() => {
  const OJO = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  const OJO_NO = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.6 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-2.5 3.4M6.6 6.6C3.7 8.4 2 12 2 12s3.6 7 10 7a9.6 9.6 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2M3 3l18 18"/></svg>';
  document.querySelectorAll('input[type=password]').forEach((inp) => {
    const caja = document.createElement('span');
    caja.className = 'clave-caja';
    inp.replaceWith(caja);
    caja.append(inp);
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'clave-ver';
    const pintar = (ver) => {
      b.innerHTML = ver ? OJO_NO : OJO;
      b.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
      b.setAttribute('aria-pressed', String(ver));
      b.title = b.getAttribute('aria-label');
    };
    pintar(false);
    b.addEventListener('click', (ev) => {
      ev.preventDefault();
      const ver = inp.type === 'password';
      inp.type = ver ? 'text' : 'password';
      pintar(ver);
      // Mantener el cursor donde estaba, sin abrir el teclado si no estaba abierto.
      if (document.activeElement === inp) inp.focus();
    });
    // Al mandar el formulario vuelve a ocultarse: así el navegador no la
    // guarda en el historial de autocompletar como texto común.
    inp.form?.addEventListener('submit', () => { inp.type = 'password'; });
    caja.append(b);
  });
})();

// Estilo claro / oscuro / automático (pie). Oscuro es el de la marca y el
// que se ve si no se eligió nada; «claro» y «auto» se guardan en este
// navegador y el <head> los aplica antes de pintar para que no parpadee.
(() => {
  const botones = document.querySelectorAll('.tema [data-tema]');
  if (!botones.length) return;
  const raiz = document.documentElement;
  const marcar = () => {
    const actual = raiz.dataset.tema || 'oscuro';
    botones.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.tema === actual)));
  };
  botones.forEach((b) => b.addEventListener('click', () => {
    const t = b.dataset.tema;
    if (t === 'oscuro') delete raiz.dataset.tema; else raiz.dataset.tema = t;
    try { t === 'oscuro' ? localStorage.removeItem('tqr-tema') : localStorage.setItem('tqr-tema', t); } catch (e) {}
    marcar();
  }));
  marcar();
})();
