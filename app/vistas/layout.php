<?php
/** @var string $contenido  @var array $meta */
$u = usuario();
$titulo_pag = $meta['titulo'] ? $meta['titulo'] . ' · ' . APP_NOMBRE : APP_NOMBRE . ' · Entradas para eventos privados';
$desc = $meta['descripcion'] ?? 'Entradas para eventos privados.';
$noindex = MODO_DEMO || !empty($meta['noindex']);
$es_admin_pag = str_starts_with(ruta_actual(), '/admin');
?><!doctype html>
<html lang="es-AR" data-app="<?= e(APP_NOMBRE) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titulo_pag) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (!empty($meta['palabras'])): ?><meta name="keywords" content="<?= e($meta['palabras']) ?>">
<?php endif; ?>
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if (!empty($meta['canonical'])): ?><link rel="canonical" href="<?= e($meta['canonical']) ?>">
<?php endif; ?>
<?php $og_desc = $meta['og_descripcion'] ?? $desc; $og_img = $meta['imagen'] ?? URL_PUBLICA . '/estaticos/compartir.jpg'; ?>
<meta property="og:site_name" content="<?= e(APP_NOMBRE) ?>">
<meta property="og:locale" content="es_AR">
<meta property="og:type" content="<?= e($meta['og_tipo'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($meta['titulo'] ?? APP_NOMBRE) ?>">
<meta property="og:description" content="<?= e($og_desc) ?>">
<meta property="og:url" content="<?= e($meta['canonical'] ?? URL_PUBLICA . ruta_actual()) ?>">
<meta property="og:image" content="<?= e($og_img) ?>">
<meta property="og:image:width" content="<?= (int)($meta['imagen_ancho'] ?? 1200) ?>">
<meta property="og:image:height" content="<?= (int)($meta['imagen_alto'] ?? 630) ?>">
<meta property="og:image:alt" content="<?= e($meta['titulo'] ?? APP_NOMBRE) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($meta['titulo'] ?? APP_NOMBRE) ?>">
<meta name="twitter:description" content="<?= e($og_desc) ?>">
<meta name="twitter:image" content="<?= e($og_img) ?>">
<meta name="theme-color" content="#0b0a09">
<script>try{var t=localStorage.getItem('tqr-tema');if(t==='claro'||t==='auto')document.documentElement.dataset.tema=t}catch(e){}</script>
<link rel="icon" href="/estaticos/app/icono-64.png" type="image/png">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/estaticos/app/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Lo quiero YA">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="preload" href="/estaticos/fuentes/anton.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/estaticos/fuentes/montserrat.woff2" as="font" type="font/woff2" crossorigin>
<?php if (!empty($meta['leaflet'])): ?><link rel="stylesheet" href="/estaticos/vendor/leaflet/leaflet.css">
<script src="/estaticos/vendor/leaflet/leaflet.js" defer></script>
<?php endif; ?>
<link rel="stylesheet" href="<?= estatico('app.css') ?>">
<?php if (!empty($meta['jsonld'])): ?><script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="<?= e($meta['cuerpo_clase'] ?? '') ?><?= $es_admin_pag ? ' es-admin' : '' ?>">
<?php if (MODO_DEMO): ?>
<div class="franja-demo">Versión de prueba · todavía no vendemos entradas: no transfieras dinero</div>
<?php endif; ?>
<header class="cabecera">
  <div class="cabecera-in">
    <a class="marca" href="/" aria-label="<?= e(APP_NOMBRE) ?> — inicio">
      <?= marca_html() ?>
    </a>
    <button class="menu-btn" type="button" aria-expanded="false" aria-controls="nav" data-menu>
      <span></span><span></span><span></span><b class="sr">Menú</b>
    </button>
    <nav class="nav" id="nav">
      <a href="/"<?= activo('/') ?>>Eventos</a>
      <a href="/mapa"<?= activo('/mapa') ?>>Mapa</a>
      <?php if ($u): ?>
        <a href="/mis-entradas"<?= activo('/mis-entradas') ?>>Mis entradas</a>
        <?php if (in_array($u['rol'], ['puerta', 'admin'], true)): ?><a href="/puerta"<?= activo('/puerta') ?>>Puerta</a><?php endif; ?>
        <?php if ($u['rol'] === 'admin'): ?><a href="/admin"<?= activo('/admin') ?>>Administración</a><?php endif; ?>
        <span class="nav-sep"></span>
        <a href="/cuenta" class="nav-cuenta"<?= activo('/cuenta') ?>><span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span><span class="nav-nombre"><?= e(explode(' ', $u['nombre'])[0]) ?></span></a>
        <form method="post" action="/salir" class="nav-salir" data-salir><?= campo_csrf() ?><button type="submit" class="enlace">Salir</button></form>
      <?php else: ?>
        <span class="nav-sep"></span>
        <a href="/ingresar"<?= activo('/ingresar') ?>>Ingresar</a>
        <a href="/registro" class="btn btn-chico">Crear cuenta</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if ($es_admin_pag): ?>
<div class="subnav"><div class="subnav-in">
  <a href="/admin"<?= ruta_actual() === '/admin' ? ' aria-current="page"' : '' ?>>Resumen</a>
  <a href="/admin/eventos"<?= activo('/admin/eventos') ?>>Eventos</a>
  <?php // Lo que necesita a una persona: comprobantes por revisar + pagos de Mercado Pago sin asignar (sugeridos o sueltos).
  $por_verificar = (int)valor("SELECT COUNT(*) FROM ordenes WHERE estado = 'pendiente' AND medio_pago = 'transferencia' AND comprobante IS NOT NULL")
    + (int)valor("SELECT COUNT(*) FROM pagos_mp WHERE orden_id IS NULL AND descartado = 0 AND estado = 'approved'"); ?>
  <a href="/admin/transferencias"<?= activo('/admin/transferencias') ?>>Transferencias<?= $por_verificar ? ' <span class="insignia">' . $por_verificar . '</span>' : '' ?></a>
  <a href="/admin/ventas"<?= activo('/admin/ventas') ?>>Ventas</a>
  <a href="/admin/pagos"<?= activo('/admin/pagos') ?>>Pagos</a>
  <a href="/admin/cupones"<?= activo('/admin/cupones') ?>>Cupones</a>
  <?php $arrep = arrepentimientos_por_atender(); ?>
  <a href="/admin/arrepentimientos"<?= activo('/admin/arrepentimientos') ?>>Arrepentimientos<?= $arrep ? ' <span class="insignia">' . $arrep . '</span>' : '' ?></a>
  <a href="/admin/resenas"<?= activo('/admin/resenas') ?>>Reseñas</a>
  <a href="/admin/categorias"<?= activo('/admin/categorias') ?>>Categorías</a>
  <a href="/admin/usuarios"<?= activo('/admin/usuarios') ?>>Usuarios</a>
  <a href="/admin/correos"<?= activo('/admin/correos') ?>>Correos</a>
</div></div>
<?php endif; ?>

<?php $fl = flashes(); if ($fl): ?>
<div class="avisos" role="status">
  <?php foreach ($fl as [$tipo, $texto]): ?><div class="aviso aviso-<?= e($tipo) ?>"><?= e($texto) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<main class="principal">
<?= $contenido ?>
</main>

<?php if (!str_starts_with(ruta_actual(), '/admin')): ?>
<section class="instalar" id="instalar" data-instalar aria-labelledby="instalar-titulo">
  <div class="instalar-in">
    <div class="instalar-cabecera">
      <img src="/estaticos/app/icono-192.png" alt="" width="52" height="52">
      <div>
        <h2 id="instalar-titulo">Llevalo como app</h2>
        <p>Un ícono en tu pantalla de inicio, a pantalla completa, y tus entradas a mano aunque en la puerta no haya señal.</p>
      </div>
      <button type="button" class="btn" data-instalar-boton hidden>Instalar</button>
    </div>
    <div class="instalar-pestanas" role="tablist">
      <button type="button" role="tab" aria-selected="true" aria-controls="inst-ios" id="tab-ios" data-tab="ios">iPhone / iPad</button>
      <button type="button" role="tab" aria-selected="false" aria-controls="inst-android" id="tab-android" data-tab="android">Android</button>
    </div>
    <ol class="instalar-pasos" id="inst-ios" role="tabpanel" aria-labelledby="tab-ios">
      <li><b>1</b><span>Abrí este sitio en <strong>Safari</strong> y tocá el ícono de <strong>Compartir</strong>
        <svg class="ico-en-linea" viewBox="0 0 24 24" aria-label="(un cuadrado con una flecha hacia arriba)"><path d="M12 3v12M8 7l4-4 4 4M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg>
        en la barra de abajo.</span></li>
      <li><b>2</b><span>Deslizá y elegí <strong>«Agregar a pantalla de inicio»</strong>.</span></li>
      <li><b>3</b><span>Tocá <strong>«Agregar»</strong>: listo, el ícono de <strong><?= e(APP_NOMBRE) ?></strong> queda junto a tus apps.</span></li>
    </ol>
    <ol class="instalar-pasos" id="inst-android" role="tabpanel" aria-labelledby="tab-android" hidden>
      <li><b>1</b><span>Abrí este sitio en <strong>Chrome</strong> y tocá el menú
        <svg class="ico-en-linea" viewBox="0 0 24 24" aria-label="(tres puntos)"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>
        arriba a la derecha.</span></li>
      <li><b>2</b><span>Elegí <strong>«Instalar aplicación»</strong> o <strong>«Agregar a la pantalla principal»</strong>.</span></li>
      <li><b>3</b><span>Confirmá con <strong>«Instalar»</strong>: listo, el ícono de <strong><?= e(APP_NOMBRE) ?></strong> queda junto a tus apps.</span></li>
    </ol>
  </div>
</section>
<?php endif; ?>

<footer class="pie">
  <div class="pie-in">
    <div class="pie-marca">
      <span class="marca marca-pie"><?= marca_html() ?></span>
      <p><?= ORGANIZADOR !== '' ? 'La ticketera de ' . e(ORGANIZADOR) . '. ' : '' ?>Vendemos las entradas, mandamos el QR y cuidamos la puerta.</p>
      <span class="puntos" aria-hidden="true"><i></i><i></i><i></i></span>
    </div>
    <nav class="pie-links" aria-label="Información">
      <a href="/condiciones">Condiciones de Servicio</a>
      <a href="/privacidad">Política de Privacidad</a>
      <a href="/cookies">Política de Cookies</a>
      <a href="/arrepentimiento" class="pie-arrepentimiento">Botón de arrepentimiento</a>
      <a href="mailto:<?= e(CORREO_CONTACTO) ?>"><?= e(CORREO_CONTACTO) ?></a>
      <?php if (ORGANIZADOR_URL !== ''): ?><a href="<?= e(ORGANIZADOR_URL) ?>" target="_blank" rel="noopener"><?= e(ORGANIZADOR ?: 'Redes') ?></a><?php endif; ?>
    </nav>
    <div class="pie-der">
      <div class="tema" role="group" aria-label="Estilo">
        <button type="button" data-tema="claro" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>Claro</button>
        <button type="button" data-tema="oscuro" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>Oscuro</button>
        <button type="button" data-tema="auto" aria-pressed="true"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 3v18" /><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/></svg>Automático</button>
      </div>
      <span>© <?= date('Y') ?></span>
    </div>
  </div>
</footer>
<script src="<?= estatico('app.js') ?>" defer></script>
</body>
</html>
