<section class="contenedor angosto">
  <h1 class="titulo-pagina">Categorías</h1>
  <p class="tenue">Las categorías son los botones de filtro del catálogo. Una categoría sin eventos a la venta no se muestra.</p>

  <div class="panel">
    <ul class="lista-categorias">
      <?php foreach ($categorias as $c): ?>
      <li>
        <form method="post" action="/admin/categorias" class="fila-categoria">
          <?= campo_csrf() ?>
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <input type="text" name="nombre" value="<?= e($c['nombre']) ?>" aria-label="Nombre" required>
          <input type="number" name="orden" value="<?= (int)$c['orden'] ?>" aria-label="Orden" class="orden" title="Orden">
          <span class="tenue chico nowrap"><?= (int)$c['n'] ?> eventos</span>
          <button class="btn btn-secundario btn-chico">Guardar</button>
          <button class="enlace peligro" type="submit" form="cat-borrar-<?= (int)$c['id'] ?>" data-confirmar="¿Borrar «<?= e($c['nombre']) ?>»? Sus eventos quedan sin categoría.">Borrar</button>
        </form>
        <form id="cat-borrar-<?= (int)$c['id'] ?>" method="post" action="/admin/categorias/<?= (int)$c['id'] ?>/borrar" hidden><?= campo_csrf() ?></form>
      </li>
      <?php endforeach; ?>
    </ul>
    <form method="post" action="/admin/categorias" class="fila-categoria fila-nueva">
      <?= campo_csrf() ?>
      <input type="text" name="nombre" placeholder="Nueva categoría" aria-label="Nombre de la nueva categoría" required>
      <input type="number" name="orden" value="<?= count($categorias) + 1 ?>" aria-label="Orden" class="orden">
      <span></span>
      <button class="btn btn-chico">Agregar</button>
    </form>
  </div>
</section>
