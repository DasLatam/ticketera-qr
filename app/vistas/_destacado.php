<?php
/** El próximo evento armado como un flyer: quién presenta, título
 *  enorme, el día espaciado y la fecha en naranja.  @var array $ev */
$t = strtotime($ev['inicio']);
$url = '/evento/' . $ev['slug'] . '?f=' . (int)$ev['funcion_id'];
?>
<article class="destacado">
  <a class="destacado-img" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
    <?php if ($ev['portada']): ?><img src="<?= e(imagen_url($ev['portada'])) ?>" alt="" width="1080" height="1350" fetchpriority="high"><?php endif; ?>
  </a>
  <div class="destacado-texto">
    <p class="destacado-presenta">
      <?php if (($ev['organizador'] ?? '') !== ''): ?><span><?= e($ev['organizador']) ?></span> presenta<?php else: ?>Próxima fecha<?php endif; ?>
    </p>
    <h2 class="destacado-titulo"><a href="<?= e($url) ?>"><?= e($ev['titulo']) ?></a></h2>
    <?php if ($ev['bajada']): ?><p class="destacado-bajada"><?= e($ev['bajada']) ?></p><?php endif; ?>
    <p class="destacado-dia"><?= DIAS_LARGOS[date('w', $t)] ?></p>
    <p class="destacado-fecha"><?= date('d.m', $t) ?></p>
    <p class="destacado-hora"><b><?= date('H.i', $t) ?> h</b><?= $ev['lugar'] ? ' · ' . e($ev['lugar']) : '' ?><?= $ev['ciudad'] ? '<br>' . e($ev['ciudad']) : '' ?></p>
    <div class="destacado-compra">
      <a class="btn btn-acento btn-grande" href="<?= e($url) ?>#entradas">Comprar entradas</a>
      <span><?= $ev['desde_min'] === null ? '' : ($ev['desde_min'] === 0 ? 'Sin cargo' : 'Desde <b>' . e(pesos($ev['desde_min'])) . '</b>') ?></span>
    </div>
  </div>
</article>
