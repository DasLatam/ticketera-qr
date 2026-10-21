<?php
// Lista y mapa comparten todo lo de arriba; cada enlace de filtro conserva el
// modo en el que se está ($base).
$mapa = $modo === 'mapa';
$base = $mapa ? '/mapa' : '/';
$con = function (array $cambios, ?string $destino = null) use ($f, $base) {
    $p = ['q' => $f['q'], 'cat' => $f['cat'], 'cuando' => $f['cuando'], 'etiqueta' => $f['etiqueta'], 'disp' => $f['disp'] ? '1' : '0', 'orden' => $f['orden']];
    $p = array_merge($p, $cambios);
    $p = array_filter($p, fn($v, $k) => $v !== '' && !($k === 'disp' && $v === '1') && !($k === 'orden' && $v === 'fecha'), ARRAY_FILTER_USE_BOTH);
    return ($destino ?? $base) . ($p ? '?' . http_build_query($p) : '');
};
$hay_filtros = $f['q'] !== '' || $f['cat'] !== '' || $f['cuando'] !== '' || $f['etiqueta'] !== '' || !$f['disp'];
// El destacado ya está arriba: no se repite en la grilla.
$grilla = $destacado ? array_values(array_filter($eventos, fn($ev) => $ev['evento_id'] !== $destacado['evento_id'])) : $eventos;
?>
<section class="portada">
  <div class="contenedor">
    <p class="sobretitulo"><?= ORGANIZADOR !== '' ? 'La ticketera de ' . (ORGANIZADOR_URL !== '' ? '<a href="' . e(ORGANIZADOR_URL) . '" target="_blank" rel="noopener">' . e(ORGANIZADOR) . '</a>' : e(ORGANIZADOR)) : 'Entradas para eventos privados' ?></p>
    <h1 class="titulo-portada">El evento, en vivo.<br><em>La entrada, en tu celular.</em></h1>
  </div>
</section>

<?php if ($destacado): ?>
<section class="contenedor"><?php $ev = $destacado; require __DIR__ . '/_destacado.php'; ?></section>
<?php endif; ?>

<section class="contenedor catalogo">
  <?php if ($con_filtros): ?>
  <form class="filtros" method="get" action="<?= $base ?>" data-filtros>
    <div class="buscador">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por evento, lugar o etiqueta" aria-label="Buscar" autocomplete="off">
    </div>
    <div class="filtros-fila">
      <label class="campo-mini">
        <span>Cuándo</span>
        <select name="cuando">
          <?php foreach (['' => 'Todas las fechas', 'hoy' => 'Hoy', 'finde' => 'Este fin de semana', 'semana' => 'Próximos 7 días', 'mes' => 'Próximos 30 días'] as $k => $t): ?>
            <option value="<?= $k ?>"<?= $f['cuando'] === $k ? ' selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="campo-mini">
        <span>Ordenar</span>
        <select name="orden">
          <option value="fecha"<?= $f['orden'] === 'fecha' ? ' selected' : '' ?>>Más próximos</option>
          <option value="precio"<?= $f['orden'] === 'precio' ? ' selected' : '' ?>>Menor precio</option>
          <option value="valoracion"<?= $f['orden'] === 'valoracion' ? ' selected' : '' ?>>Mejor valorados</option>
          <option value="nuevos"<?= $f['orden'] === 'nuevos' ? ' selected' : '' ?>>Recién publicados</option>
        </select>
      </label>
      <label class="interruptor">
        <input type="hidden" name="disp" value="0">
        <input type="checkbox" name="disp" value="1"<?= $f['disp'] ? ' checked' : '' ?>>
        <span class="interruptor-pista"></span>
        <span>Sólo con entradas</span>
      </label>
      <input type="hidden" name="cat" value="<?= e($f['cat']) ?>">
      <input type="hidden" name="etiqueta" value="<?= e($f['etiqueta']) ?>">
      <noscript><button class="btn btn-chico">Filtrar</button></noscript>
    </div>
  </form>

  <?php endif; ?>
  <?php // Con un solo evento (el destacado) no hay grilla: tampoco barra; el mapa sigue en el menú.
  if (!$destacado || $grilla): ?>
  <div class="catalogo-barra">
    <h2 class="seccion-titulo"><?= $destacado ? 'Más fechas' : 'Próximas fechas' ?></h2>
    <div class="vista-toggle" aria-label="Vista">
      <a href="<?= e($con([], '/')) ?>"<?= $mapa ? '' : ' aria-current="page"' ?>>Lista</a>
      <a href="<?= e($con([], '/mapa')) ?>"<?= $mapa ? ' aria-current="page"' : '' ?>>Mapa</a>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($con_filtros): ?>
  <nav class="chips" aria-label="Categorías">
    <a class="chip" href="<?= e($con(['cat' => ''])) ?>"<?= $f['cat'] === '' ? ' aria-current="true"' : '' ?>>Todas</a>
    <?php foreach ($categorias as $c): if (!$c['n'] && $f['cat'] !== $c['slug']) continue; ?>
      <a class="chip" href="<?= e($con(['cat' => $c['slug']])) ?>"<?= $f['cat'] === $c['slug'] ? ' aria-current="true"' : '' ?>><?= e($c['nombre']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <?php if ($f['etiqueta'] !== '' || $hay_filtros): ?>
  <div class="resumen-filtros">
    <span><?= count($eventos) ?> <?= count($eventos) === 1 ? 'evento' : 'eventos' ?></span>
    <?php if ($f['etiqueta'] !== ''): ?><a class="etiqueta etiqueta-activa" href="<?= e($con(['etiqueta' => ''])) ?>">#<?= e($f['etiqueta']) ?> <b aria-hidden="true">×</b></a><?php endif; ?>
    <?php if ($hay_filtros): ?><a class="enlace-tenue" href="<?= $base ?>">Limpiar filtros</a><?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$eventos && !$hay_filtros): ?>
    <div class="vacio">
      <p><strong>Pronto anunciamos la próxima fecha.</strong></p>
      <?php if (ORGANIZADOR_URL !== ''): ?><p>Las novedades salen primero en <a href="<?= e(ORGANIZADOR_URL) ?>" target="_blank" rel="noopener"><?= e(ORGANIZADOR ?: 'nuestras redes') ?></a>.</p><?php endif; ?>
    </div>
  <?php elseif (!$eventos): ?>
    <div class="vacio">
      <p><strong>No hay eventos con esos filtros.</strong></p>
      <?php if ($ocultos > 0): ?>
        <p>Hay <?= $ocultos ?> <?= $ocultos === 1 ? 'evento agotado que coincide' : 'eventos agotados que coinciden' ?>. <a href="<?= e($con(['disp' => '0'])) ?>">Mostrarlos</a></p>
      <?php else: ?>
        <p><a href="<?= $base ?>">Ver todos los eventos</a></p>
      <?php endif; ?>
    </div>
  <?php elseif ($mapa): ?>
  <div class="mapa-grande" data-mapa-grande>
    <?php if (!$puntos): ?><div class="mapa-vacio">Estos eventos no tienen ubicación cargada.</div><?php endif; ?>
  </div>
  <p class="mapa-pie tenue chico"><?= count($puntos) ?> <?= count($puntos) === 1 ? 'función' : 'funciones' ?> en el mapa · Mapa © colaboradores de <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a></p>
  <script type="application/json" id="puntos-mapa"><?= json_encode($puntos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <?php elseif ($grilla): ?>
  <div class="grilla">
    <?php foreach ($grilla as $ev): $pasado = false; require __DIR__ . '/_tarjeta.php'; endforeach; ?>
  </div>
  <?php if ($ocultos > 0 && $f['disp']): ?>
    <p class="nota-agotados"><?= $ocultos ?> <?= $ocultos === 1 ? 'evento agotado no se muestra' : 'eventos agotados no se muestran' ?>. <a href="<?= e($con(['disp' => '0'])) ?>">Ver también los agotados</a></p>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($etiquetas && $con_filtros): ?>
  <div class="nube">
    <span class="nube-titulo">Explorar por etiqueta</span>
    <?php foreach ($etiquetas as $g): ?>
      <a class="etiqueta<?= $f['etiqueta'] === $g['slug'] ? ' etiqueta-activa' : '' ?>" href="<?= e($con(['etiqueta' => $f['etiqueta'] === $g['slug'] ? '' : $g['slug']])) ?>">#<?= e($g['nombre']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if ($pasados): ?>
<section class="contenedor pasados" aria-labelledby="pasados-titulo">
  <h2 class="seccion-titulo" id="pasados-titulo">Ya pasaron</h2>
  <div class="grilla">
    <?php foreach ($pasados as $ev): $pasado = true; require __DIR__ . '/_tarjeta.php'; endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php if ($mapa): ?>
<script src="/estaticos/vendor/leaflet/leaflet.markercluster.js" defer></script>
<script src="<?= estatico('mapa.js') ?>" defer></script>
<?php endif; ?>
