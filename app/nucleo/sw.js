// Service worker de la ticketera.
//
// Para qué existe: en la puerta de un salón o un sótano muchas veces no hay
// señal, y la entrada tiene que abrirse igual. Las páginas de «Mis entradas» y
// de cada entrada se guardan cada vez que se abren con conexión, y sin
// conexión se muestran desde el celular. El QR se dibuja en el navegador
// (vendor/qrcode.js, guardado acá también), así que no necesita red.
//
// Nada más se guarda: catálogo, compra, administración y puerta van siempre a
// la red (la puerta necesita el servidor para quemar la entrada).
// Al cerrar sesión la página borra la caché de entradas (app.js).

const VERSION = 'tqr-__VERSION__'; // la pone index.php: cambia con cada deploy
const ESTATICOS = VERSION + '-estaticos';
const ENTRADAS = 'tqr-entradas'; // sin versión: sobrevive a las actualizaciones
const PRECARGA = ['/sin-conexion.html', '/estaticos/vendor/qrcode.js', '/estaticos/app/icono-192.png', '/estaticos/fuentes/anton.woff2'];

self.addEventListener('install', (ev) => {
  ev.waitUntil(caches.open(ESTATICOS).then((c) => c.addAll(PRECARGA)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (ev) => {
  ev.waitUntil(
    caches.keys()
      .then((ks) => Promise.all(ks.filter((k) => k !== ESTATICOS && k !== ENTRADAS).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

const esEntradas = (url) => url.pathname === '/mis-entradas' || /^\/entrada\/[A-Z0-9]+$/.test(url.pathname);

self.addEventListener('fetch', (ev) => {
  const req = ev.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Estáticos: primero la red y la caché sólo sin conexión. Se guarda sólo
  // lo que es de verdad un estático: si durante un deploy el servidor
  // devolvía una página HTML en lugar del CSS, antes quedaba guardada y el
  // sitio se veía sin estilo en ese navegador hasta borrar los datos.
  if (url.pathname.startsWith('/estaticos/') || url.pathname.startsWith('/subidas/')) {
    ev.respondWith(caches.open(ESTATICOS).then((c) =>
      fetch(req).then((r) => {
        const tipo = r.headers.get('content-type') || '';
        if (r.ok && !r.redirected && !tipo.includes('text/html')) c.put(req, r.clone());
        return r;
      }).catch(async () => (await c.match(req)) || Response.error())
    ));
    return;
  }

  if (req.mode !== 'navigate') return;

  // Entradas: red primero; si hay, se guarda la copia; si no hay, la copia.
  if (esEntradas(url)) {
    ev.respondWith(
      fetch(req).then((r) => {
        // Sólo se guarda la página de verdad, no una redirección al login.
        // La copia se saca ANTES de devolver la respuesta: después el
        // navegador ya consumió el cuerpo y clone() falla sin avisar.
        if (r.ok && !r.redirected) {
          const copia = r.clone();
          caches.open(ENTRADAS).then((c) => c.put(url.pathname, copia));
        }
        return r;
      }).catch(async () => (await caches.match(url.pathname, { cacheName: ENTRADAS })) || caches.match('/sin-conexion.html'))
    );
    return;
  }

  // El resto: siempre la red; sin conexión, la página que lo explica.
  ev.respondWith(fetch(req).catch(() => caches.match('/sin-conexion.html')));
});

self.addEventListener('message', (ev) => {
  if (ev.data === 'borrar-entradas') ev.waitUntil(caches.delete(ENTRADAS));
});
