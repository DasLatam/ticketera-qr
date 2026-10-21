<?php $nueva = empty($fn['id']); $accion = '/admin/eventos/' . (int)$e['id'] . '/funciones/' . ($nueva ? 'nueva' : (int)$fn['id']); ?>
<section class="contenedor">
  <a class="volver" href="/admin/eventos/<?= (int)$e['id'] ?>#funciones">← <?= e($e['titulo']) ?></a>
  <div class="titulo-con-accion">
    <h1 class="titulo-pagina"><?= $nueva ? 'Nueva función' : 'Función del ' . e(fecha_corta($fn['inicio'])) . ' h' ?></h1>
    <?php if (!$nueva): ?>
    <div class="acciones">
      <a class="btn btn-secundario" href="/admin/funciones/<?= (int)$fn['id'] ?>/asistentes">Asistentes<?= $vendidas ? " ($vendidas)" : '' ?></a>
      <a class="btn btn-secundario" href="/evento/<?= e($e['slug']) ?>?f=<?= (int)$fn['id'] ?>" target="_blank">Ver ↗</a>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($nueva): ?><p class="tenue">Se copiaron el lugar, el aforo y los tipos de entrada de la última función; cambiá lo que haga falta.</p><?php endif; ?>

  <form method="post" action="<?= $accion ?>" class="form-evento" data-form-evento>
    <?= campo_csrf() ?>
    <div class="form-evento-cuerpo">
      <?php require __DIR__ . '/_funcion_campos.php'; ?>
    </div>
    <aside class="form-evento-lateral">
      <div class="panel panel-pegajoso">
        <label class="campo"><span>Estado de la función</span>
          <select name="funcion_estado">
            <option value="activa"<?= $fn['estado'] === 'activa' ? ' selected' : '' ?>>Activa</option>
            <option value="cancelada"<?= $fn['estado'] === 'cancelada' ? ' selected' : '' ?>>Cancelada</option>
          </select></label>
        <button class="btn btn-bloque" type="submit"><?= $nueva ? 'Agregar función' : 'Guardar' ?></button>
        <?php if (!$nueva && !$vendidas): ?>
        <div class="lateral-extra"><button class="enlace peligro" type="submit" form="f-borrar-funcion" data-confirmar="¿Borrar esta función?">Borrar función</button></div>
        <?php endif; ?>
      </div>
    </aside>
  </form>
  <?php if (!$nueva): ?><form id="f-borrar-funcion" method="post" action="<?= $accion ?>/borrar" hidden><?= campo_csrf() ?></form><?php endif; ?>
</section>
<script src="<?= estatico('mapa.js') ?>" defer></script>
