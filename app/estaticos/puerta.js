// Escáner de la puerta: cámara trasera → jsQR → /api/puerta/validar.
// El servidor decide; acá sólo se muestra el resultado bien grande, con color
// y sonido, porque quien lo mira tiene una fila de gente adelante.
(() => {
  const raiz = document.querySelector('[data-escaner]');
  if (!raiz) return;
  const funcionId = raiz.dataset.funcion;
  const csrf = raiz.dataset.csrf;
  const video = raiz.querySelector('[data-video]');
  const estado = raiz.querySelector('[data-estado]');
  const btnActivar = raiz.querySelector('[data-activar]');
  const panel = raiz.querySelector('[data-resultado]');
  const historial = raiz.querySelector('[data-historial]');
  const lienzo = document.createElement('canvas');
  const ctx = lienzo.getContext('2d', { willReadFrequently: true });
  const detector = 'BarcodeDetector' in window ? new BarcodeDetector({ formats: ['qr_code'] }) : null;

  let ocupado = false;          // hay una validación en curso o un resultado en pantalla
  let ultimo = { codigo: '', t: 0 };
  let cierre = null;

  const TEXTOS = {
    ok: ['✓', 'Adelante'],
    ya_usada: ['!', 'Ya ingresó'],
    otro_evento: ['!', 'Otra función'],
    anulada: ['×', 'Anulada'],
    pendiente_pago: ['!', 'Pago pendiente'],
    inexistente: ['×', 'No válida'],
    error: ['?', 'Error'],
  };

  function contar(d) {
    if (d.ingresados === undefined) return;
    raiz.querySelector('[data-ingresados]').textContent = d.ingresados;
    raiz.querySelector('[data-vendidas]').textContent = d.vendidas;
  }

  // Un pitido distinto para el sí y para el no: en la puerta no siempre se
  // mira la pantalla.
  let audio;
  function pitar(ok) {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      const o = audio.createOscillator();
      const g = audio.createGain();
      o.type = 'sine';
      o.frequency.value = ok ? 880 : 220;
      g.gain.setValueAtTime(0.25, audio.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + (ok ? 0.18 : 0.5));
      o.connect(g).connect(audio.destination);
      o.start();
      o.stop(audio.currentTime + (ok ? 0.2 : 0.5));
    } catch (_) {}
    if (navigator.vibrate) navigator.vibrate(ok ? 80 : [200, 80, 200]);
  }

  function mostrar(d) {
    const [icono, titulo] = TEXTOS[d.resultado] || TEXTOS.error;
    panel.className = 'resultado resultado-' + d.resultado;
    panel.querySelector('[data-r-icono]').textContent = icono;
    panel.querySelector('[data-r-titulo]').textContent = titulo;
    panel.querySelector('[data-r-titular]').textContent = d.titular || '';
    let det = d.tipo ? d.tipo : '';
    if (d.resultado === 'ya_usada' && d.usada_en) det += (det ? ' · ' : '') + 'entró a las ' + d.usada_en;
    if (d.resultado !== 'ok' && d.mensaje) det += (det ? ' — ' : '') + d.mensaje;
    panel.querySelector('[data-r-detalle]').textContent = det;
    panel.hidden = false;
    pitar(d.resultado === 'ok');

    const li = document.createElement('li');
    li.className = 'h-' + d.resultado;
    const hora = new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
    li.innerHTML = '<span></span><b></b><time></time>';
    li.children[0].textContent = titulo;
    li.children[1].textContent = d.titular || d.codigo || '';
    li.children[2].textContent = hora;
    historial.prepend(li);
    while (historial.children.length > 6) historial.lastChild.remove();

    clearTimeout(cierre);
    cierre = setTimeout(ocultar, d.resultado === 'ok' ? 1600 : 4000);
  }

  function ocultar() {
    clearTimeout(cierre);
    panel.hidden = true;
    ocupado = false;
  }
  panel.addEventListener('click', ocultar);

  async function validar(codigo) {
    if (ocupado) return;
    // La misma entrada frente a la cámara se lee diez veces por segundo: se
    // ignora la repetición durante unos segundos.
    if (codigo === ultimo.codigo && Date.now() - ultimo.t < 4000) return;
    ultimo = { codigo, t: Date.now() };
    ocupado = true;
    try {
      const r = await fetch('/api/puerta/validar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF': csrf },
        body: new URLSearchParams({ codigo, funcion_id: funcionId, _csrf: csrf }),
        credentials: 'same-origin',
      });
      const d = await r.json();
      contar(d);
      mostrar(d);
    } catch (err) {
      mostrar({ resultado: 'error', mensaje: 'Sin conexión. Probá de nuevo.' });
    }
  }

  async function leerCuadro() {
    if (!ocupado && video.readyState >= 2) {
      try {
        let texto = null;
        if (detector) {
          const r = await detector.detect(video);
          if (r.length) texto = r[0].rawValue;
        } else if (window.jsQR) {
          // Se lee el centro, achicado: alcanza para un QR en primer plano y
          // en un celular modesto la diferencia es leer o no leer.
          const lado = Math.min(video.videoWidth, video.videoHeight);
          const escala = Math.min(1, 480 / lado);
          lienzo.width = lienzo.height = Math.round(lado * escala);
          ctx.drawImage(video, (video.videoWidth - lado) / 2, (video.videoHeight - lado) / 2, lado, lado, 0, 0, lienzo.width, lienzo.height);
          const img = ctx.getImageData(0, 0, lienzo.width, lienzo.height);
          const r = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
          if (r) texto = r.data;
        }
        if (texto) validar(texto.trim());
      } catch (_) {}
    }
    setTimeout(() => requestAnimationFrame(leerCuadro), 110);
  }

  async function encender() {
    btnActivar.hidden = true;
    if (!window.isSecureContext || !navigator.mediaDevices) {
      estado.textContent = 'La cámara necesita una conexión segura (https). Usá el código a mano.';
      return;
    }
    try {
      const flujo = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
      video.srcObject = flujo;
      await video.play();
      estado.textContent = 'Apuntá al código QR';
      raiz.classList.add('camara-activa');
      leerCuadro();
    } catch (err) {
      estado.textContent = err && err.name === 'NotAllowedError'
        ? 'Sin permiso para usar la cámara. Habilitalo en el navegador o usá el código a mano.'
        : 'No se pudo abrir la cámara. Usá el código a mano.';
      btnActivar.hidden = false;
    }
  }
  btnActivar.addEventListener('click', encender);

  raiz.querySelector('[data-manual]').addEventListener('submit', (ev) => {
    ev.preventDefault();
    const campo = ev.target.codigo;
    const v = campo.value.trim();
    if (!v) return;
    ultimo = { codigo: '', t: 0 };
    validar(v);
    campo.value = '';
  });

  fetch('/api/puerta/' + funcionId + '/resumen', { credentials: 'same-origin' }).then((r) => r.json()).then(contar).catch(() => {});
  // La pantalla no se apaga mientras se controla la puerta.
  if ('wakeLock' in navigator) {
    const pedir = () => navigator.wakeLock.request('screen').catch(() => {});
    pedir();
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && pedir());
  }
  encender();
})();
