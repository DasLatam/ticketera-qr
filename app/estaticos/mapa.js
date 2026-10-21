// Mapas con OpenStreetMap + Leaflet: el mapa grande (/mapa), el micro de la
// ficha del evento y el selector de ubicación del administrador.
(() => {
  const TESELAS = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  const ATRIB = '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
  const BA = [-34.6037, -58.3816];
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  // Marcador propio (un punto con el acento) en vez del pin azul de Leaflet.
  const icono = (clase = '') => L.divIcon({ className: 'pin ' + clase, html: '<span></span>', iconSize: [26, 26], iconAnchor: [13, 13], popupAnchor: [0, -14] });

  function base(el, opciones = {}) {
    const m = L.map(el, { scrollWheelZoom: false, ...opciones });
    L.tileLayer(TESELAS, { maxZoom: 19, attribution: ATRIB }).addTo(m);
    // La rueda del mouse sólo hace zoom después de un clic: si no, el mapa
    // atrapa el scroll de la página.
    m.once('focus click', () => m.scrollWheelZoom.enable());
    return m;
  }

  // Mapa grande
  const grande = document.querySelector('[data-mapa-grande]');
  if (grande && window.L) {
    const puntos = JSON.parse(document.getElementById('puntos-mapa').textContent || '[]');
    const m = base(grande);
    // Varias funciones en el mismo lugar se agrupan en un solo marcador.
    const lugares = {};
    puntos.forEach((p) => { (lugares[p.lat.toFixed(5) + ',' + p.lng.toFixed(5)] ||= []).push(p); });
    const capas = Object.values(lugares).map((grupo) => {
      const p0 = grupo[0];
      const html = grupo.map((p) => `
        <a class="pop-evento" href="${esc(p.url)}">
          ${p.imagen ? `<img src="${esc(p.imagen)}" alt="">` : ''}
          <span><strong>${esc(p.titulo)}</strong><span>${esc(p.fecha)}</span><span>${esc(p.precio)} · <em class="stock-${esc(p.clase)}">${esc(p.stock)}</em></span></span>
        </a>`).join('');
      return L.marker([p0.lat, p0.lng], { icon: icono(grupo.length > 1 ? 'pin-varios' : ''), title: p0.lugar, cantidad: grupo.length })
        .bindPopup(`<div class="pop"><div class="pop-lugar">${esc(p0.lugar)}</div>${html}</div>`, { maxWidth: 300, minWidth: 240 });
    });
    // Lugares cercanos se juntan en un círculo con el número de funciones;
    // al acercar se separan.
    const grupo = L.markerClusterGroup ? L.markerClusterGroup({
      showCoverageOnHover: false, maxClusterRadius: 44,
      iconCreateFunction: (c) => {
        const n = c.getAllChildMarkers().reduce((s, mk) => s + (mk.options.cantidad || 1), 0);
        return L.divIcon({ className: 'pin-grupo', html: '<span>' + n + '</span>', iconSize: [38, 38] });
      },
    }) : L.featureGroup();
    capas.forEach((c) => grupo.addLayer(c));
    grupo.addTo(m);
    if (capas.length) m.fitBounds(grupo.getBounds().pad(0.15), { maxZoom: 15 });
    else m.setView(BA, 12);
  }

  // Micro mapa de la ficha del evento
  document.querySelectorAll('[data-mapa-micro]').forEach((el) => {
    if (!window.L) return;
    const lat = +el.dataset.lat, lng = +el.dataset.lng;
    const m = base(el, { zoomControl: false, dragging: !L.Browser.mobile, tap: false });
    m.setView([lat, lng], 15);
    L.marker([lat, lng], { icon: icono() }).addTo(m);
  });

  // Selector de ubicación (administración): clic o arrastre del marcador, y
  // «Buscar en el mapa» con la dirección vía Nominatim (el geocodificador de
  // OpenStreetMap; se usa a mano y de a una, como pide su política de uso).
  const sel = document.querySelector('[data-mapa-selector]');
  if (sel && window.L) {
    const fLat = document.querySelector('[name=lat]');
    const fLng = document.querySelector('[name=lng]');
    const aviso = document.querySelector('[data-geo-aviso]');
    const tiene = fLat.value !== '' && fLng.value !== '';
    const m = base(sel);
    m.setView(tiene ? [+fLat.value, +fLng.value] : BA, tiene ? 16 : 11);
    let marca = null;
    const poner = (lat, lng, centrar) => {
      fLat.value = lat.toFixed(6);
      fLng.value = lng.toFixed(6);
      if (!marca) {
        marca = L.marker([lat, lng], { icon: icono(), draggable: true }).addTo(m);
        marca.on('dragend', () => { const p = marca.getLatLng(); poner(p.lat, p.lng); });
      } else marca.setLatLng([lat, lng]);
      if (centrar) m.setView([lat, lng], 16);
    };
    if (tiene) poner(+fLat.value, +fLng.value);
    m.on('click', (ev) => poner(ev.latlng.lat, ev.latlng.lng));
    document.querySelector('[data-geo-buscar]').addEventListener('click', async () => {
      const q = ['direccion', 'ciudad'].map((n) => document.querySelector(`[name=${n}]`).value.trim()).filter(Boolean).join(', ');
      if (!q) { aviso.textContent = 'Escribí la dirección y la ciudad primero.'; return; }
      aviso.textContent = 'Buscando…';
      try {
        const r = await fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=ar&accept-language=es&q=' + encodeURIComponent(q));
        const d = await r.json();
        if (!d.length) { aviso.textContent = 'No encontré esa dirección. Marcá el punto con un clic en el mapa.'; return; }
        poner(+d[0].lat, +d[0].lon, true);
        aviso.textContent = 'Ubicado: ' + d[0].display_name + '. Si no es exacto, arrastrá el marcador.';
      } catch (_) {
        aviso.textContent = 'No se pudo consultar el buscador. Marcá el punto con un clic en el mapa.';
      }
    });
    document.querySelector('[data-geo-quitar]').addEventListener('click', () => {
      fLat.value = fLng.value = '';
      if (marca) { m.removeLayer(marca); marca = null; }
      aviso.textContent = 'Sin ubicación: la función no aparece en el mapa.';
    });
  }
})();
