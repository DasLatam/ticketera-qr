<?php
/** Tarjeta de un evento en la grilla, con formato de flyer (4:5).
 *  @var array $ev  fila de pag_catalogo() o de funciones_pasadas()
 *  @var bool $pasado */
$pasado = $pasado ?? false;
$t = strtotime($ev['inicio']);
if (!$pasado) [$txt, $cls] = $ev['hay_lugar'] ? estado_stock((int)$ev['disponibles'], (int)$ev['capacidad']) : ['Agotado', 'agotado'];
?>
<a class="tarjeta<?= !$pasado && !$ev['hay_lugar'] ? ' es-agotado' : '' ?><?= $pasado ? ' tarjeta-pasada' : '' ?>" href="/evento/<?= e($ev['slug']) ?>?f=<?= (int)$ev['funcion_id'] ?>">
  <div class="tarjeta-img">
    <?php if ($ev['portada']): ?><img src="<?= e(imagen_url($ev['portada'], 'm')) ?>" alt="" loading="lazy" width="720" height="900"><?php endif; ?>
    <span class="fecha-sello"><small><?= DIAS[date('w', $t)] ?></small><b><?= date('d.m', $t) ?></b><?php if ($pasado): ?><small><?= date('Y', $t) ?></small><?php endif; ?></span>
    <?php if (!$pasado): ?><span class="stock stock-<?= $cls ?>"><?= e($txt) ?></span><?php endif; ?>
  </div>
  <div class="tarjeta-cuerpo">
    <span class="tarjeta-sup">
      <?php if ($ev['categoria']): ?><span class="tarjeta-cat"><?= e($ev['categoria']) ?></span><?php endif; ?>
      <?php if ($ev['puntaje']['n']): ?><span class="tarjeta-puntaje"><?= estrellas($ev['puntaje']['prom'], 'chicas') ?> <?= e(str_replace('.', ',', (string)$ev['puntaje']['prom'])) ?> <span class="tenue">(<?= $ev['puntaje']['n'] ?>)</span></span><?php endif; ?>
    </span>
    <h3 class="tarjeta-titulo"><?= e($ev['titulo']) ?></h3>
    <p class="tarjeta-meta"><?= $pasado ? 'Fue el ' . e(fecha_larga($ev['inicio'])) : e(fecha_corta($ev['inicio'])) . ' h' ?><?= $ev['lugar'] ? ' · ' . e($ev['lugar']) : '' ?></p>
    <?php if ($pasado): ?>
      <p class="tarjeta-precio"><?= $ev['puntaje']['n'] ? 'Leé lo que dijeron quienes fueron' : 'Ver cómo fue' ?> →</p>
    <?php else: ?>
    <p class="tarjeta-precio">
      <?= $ev['desde_min'] === null ? '' : ($ev['desde_min'] === 0 ? 'Sin cargo' : 'Desde <b>' . e(pesos($ev['desde_min'])) . '</b>') ?>
      <?php if ($ev['fechas'] > 1): ?><span class="mas-fechas">+<?= $ev['fechas'] - 1 ?> <?= $ev['fechas'] === 2 ? 'fecha' : 'fechas' ?></span><?php endif; ?>
    </p>
    <?php endif; ?>
  </div>
</a>
