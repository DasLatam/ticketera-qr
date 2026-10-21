<section class="contenedor angosto">
  <h1 class="titulo-pagina">Puerta</h1>
  <p class="tenue">Elegí la función que estás controlando. El escáner sólo deja pasar entradas de esa fecha y ese lugar.</p>
  <?php if (!$funciones): ?>
    <div class="vacio"><p>No hay eventos próximos ni en curso.</p></div>
  <?php else: ?>
  <ul class="lista-puerta">
    <?php foreach ($funciones as $ev):
      $en_curso = strtotime($ev['inicio']) <= time() + 3 * 3600 && strtotime($ev['fin'] ?: $ev['inicio']) >= time() - 12 * 3600; ?>
    <li>
      <a class="fila-puerta" href="/puerta/<?= (int)$ev['id'] ?>">
        <span class="fila-puerta-fecha"><b><?= date('j', strtotime($ev['inicio'])) ?></b><?= MESES[date('n', strtotime($ev['inicio'])) - 1] ?></span>
        <span class="fila-puerta-texto">
          <strong><?= e($ev['titulo']) ?></strong>
          <span class="tenue"><?= e(hora($ev['inicio'])) ?> h<?= $ev['lugar'] ? ' · ' . e($ev['lugar']) : '' ?></span>
        </span>
        <span class="fila-puerta-cuenta">
          <?php if ($en_curso): ?><span class="punto-vivo">Hoy</span><?php endif; ?>
          <span class="mono"><?= (int)$ev['ingresados'] ?>/<?= (int)$ev['vendidas'] ?></span>
        </span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
