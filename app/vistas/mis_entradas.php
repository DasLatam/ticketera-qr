<?php
$bloque = function (array $grupo, bool $pasado) { $p = $grupo[0]; ?>
  <article class="grupo-entradas<?= $pasado ? ' es-pasado' : '' ?>">
    <a class="grupo-cabecera" href="/evento/<?= e($p['slug']) ?>">
      <?php if ($p['portada']): ?><img src="<?= e(imagen_url($p['portada'], 'm')) ?>" alt="" width="96" height="64" loading="lazy"><?php endif; ?>
      <div>
        <strong><?= e($p['titulo']) ?></strong>
        <span class="tenue"><?= e(ucfirst(fecha_corta($p['inicio']))) ?> h<?= $p['lugar'] ? ' · ' . e($p['lugar']) : '' ?></span>
        <?php if ($p['evento_estado'] === 'cancelado' || $p['funcion_estado'] === 'cancelada'): ?><span class="stock stock-agotado stock-en-linea">Cancelado</span><?php endif; ?>
      </div>
    </a>
    <ul class="lista-entradas">
      <?php foreach ($grupo as $x): ?>
      <li>
        <?php $pend = $x['orden_estado'] === 'pendiente'; ?>
        <a href="<?= $pend ? '/orden/' . e($x['orden_codigo']) : '/entrada/' . e($x['codigo']) ?>" class="fila-entrada fila-<?= e($x['estado']) ?><?= $pend ? ' fila-pendiente' : '' ?>">
          <?php if ($pend): ?><span class="qr-mini qr-bloqueado" aria-hidden="true">⏳</span>
          <?php else: ?><span class="qr-mini" data-qr="TQR:<?= e($x['codigo']) ?>" aria-hidden="true"></span><?php endif; ?>
          <span class="fila-entrada-texto">
            <strong><?= e($x['titular']) ?></strong>
            <span class="tenue"><?= e($x['tipo']) ?> · <span class="mono"><?= e($x['codigo']) ?></span></span>
          </span>
          <span class="fila-entrada-ir"><?= $pend ? 'Pago en verificación' : match ($x['estado']) { 'usada' => 'Usada', 'anulada' => 'Anulada', default => 'Ver QR →' } ?></span>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($pasado && !$p['resenada'] && array_filter($grupo, fn($x) => $x['estado'] === 'usada')): ?>
      <a class="pedir-resena" href="/evento/<?= e($p['slug']) ?>#resenas">★ ¿Cómo estuvo? Dejá tu reseña</a>
    <?php endif; ?>
  </article>
<?php }; ?>
<section class="contenedor angosto">
  <h1 class="titulo-pagina">Mis entradas</h1>
  <?php if (!$proximos && !$pasados): ?>
    <div class="vacio">
      <p><strong>Todavía no compraste entradas.</strong></p>
      <p><a class="btn" href="/">Ver eventos</a></p>
    </div>
  <?php endif; ?>

  <?php if ($proximos): ?>
    <p class="tenue">Mostrá el QR de cada entrada en la puerta. Subí el brillo del celular para que se lea rápido.</p>
    <?php foreach ($proximos as $g) $bloque($g, false); ?>
  <?php endif; ?>

  <?php if ($pasados): ?>
    <h2 class="subtitulo">Eventos pasados</h2>
    <?php foreach ($pasados as $g) $bloque($g, true); ?>
  <?php endif; ?>
</section>
<script src="/estaticos/vendor/qrcode.js" defer></script>
