<?php
$nuevo = empty($e['id']);
$accion = $nuevo ? '/admin/eventos/nuevo' : '/admin/eventos/' . (int)$e['id'];
?>
<section class="contenedor">
  <a class="volver" href="/admin/eventos">← Eventos</a>
  <div class="titulo-con-accion">
    <h1 class="titulo-pagina"><?= $nuevo ? 'Nuevo evento' : e($e['titulo']) ?></h1>
    <?php if (!$nuevo): ?>
    <div class="acciones">
      <a class="btn btn-secundario" href="/evento/<?= e($e['slug']) ?>" target="_blank">Ver ↗</a>
    </div>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= $accion ?>" enctype="multipart/form-data" class="form-evento" id="form-evento" data-form-evento>
    <?= campo_csrf() ?>
    <div class="form-evento-cuerpo">

      <fieldset class="panel">
        <legend class="panel-titulo">Datos principales</legend>
        <label class="campo"><span>Título</span>
          <input type="text" name="titulo" value="<?= e($e['titulo']) ?>" required maxlength="120" data-titulo></label>
        <label class="campo"><span>Bajada <i>una línea que lo resume; va en la tarjeta y en buscadores si no hay meta descripción</i></span>
          <input type="text" name="bajada" value="<?= e($e['bajada']) ?>" maxlength="200"></label>
        <label class="campo"><span>Descripción <i>separá párrafos con una línea en blanco</i></span>
          <textarea name="descripcion" rows="9"><?= e($e['descripcion']) ?></textarea></label>
        <div class="campos-2">
          <label class="campo"><span>Categoría</span>
            <select name="categoria_id">
              <option value="">Sin categoría</option>
              <?php foreach ($categorias as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $e['categoria_id'] == $c['id'] ? ' selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
            </select></label>
          <label class="campo"><span>Etiquetas <i>separadas por coma</i></span>
            <input type="text" name="etiquetas" value="<?= e($etiquetas_ev) ?>" list="lista-etiquetas" placeholder="techno, terraza, after office" data-etiquetas>
            <datalist id="lista-etiquetas"><?php foreach ($todas_etiquetas as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist></label>
        </div>
      </fieldset>

      <fieldset class="panel">
        <legend class="panel-titulo">Datos para todas las funciones</legend>
        <div class="campos-3">
          <label class="campo"><span>Organiza</span><input type="text" name="organizador" value="<?= e($e['organizador']) ?>"></label>
          <label class="campo"><span>Edad mínima</span><input type="number" name="edad_minima" value="<?= e($e['edad_minima']) ?>" min="0" max="99"></label>
          <label class="campo"><span>Vestimenta</span><input type="text" name="vestimenta" value="<?= e($e['vestimenta']) ?>" placeholder="Elegante sport"></label>
        </div>
        <label class="campo campo-angosto"><span>Máximo de entradas por compra</span><input type="number" name="max_por_compra" value="<?= (int)$e['max_por_compra'] ?>" min="1" max="50"></label>
      </fieldset>

      <?php if ($nuevo): ?>
        <h2 class="seccion-admin">Primera función <span class="tenue">· después podés agregar más fechas</span></h2>
        <?php require __DIR__ . '/_funcion_campos.php'; ?>
      <?php else: ?>
      <section class="panel" id="funciones">
        <div class="panel-cabecera">
          <h2 class="panel-titulo">Funciones <span class="tenue">· <?= count($funciones) ?></span></h2>
          <a class="btn btn-secundario btn-chico" href="/admin/eventos/<?= (int)$e['id'] ?>/funciones/nueva">+ Agregar fecha</a>
        </div>
        <?php if (!$funciones): ?><p class="tenue">Sin funciones: el evento no se puede comprar hasta que tenga al menos una.</p><?php endif; ?>
        <ul class="lista-funciones">
          <?php foreach ($funciones as $fn): $pas = terminada($fn); $pct = $fn['capacidad'] ? min(100, round($fn['vendidas'] / $fn['capacidad'] * 100)) : 0; ?>
          <li class="<?= $pas ? 'es-pasada' : '' ?>">
            <a class="lf-datos" href="/admin/eventos/<?= (int)$e['id'] ?>/funciones/<?= (int)$fn['id'] ?>">
              <strong><?= e(ucfirst(fecha_corta($fn['inicio']))) ?> h</strong>
              <span class="tenue chico"><?= e($fn['lugar'] ?: 'Sin lugar') ?><?= $fn['lat'] === null ? ' · sin ubicación en el mapa' : '' ?></span>
            </a>
            <span class="lf-estado">
              <?php if ($fn['estado'] === 'cancelada'): ?><span class="estado estado-cancelado">cancelada</span>
              <?php elseif ($pas): ?><span class="estado estado-borrador">pasada</span><?php endif; ?>
              <span class="mono chico"><?= (int)$fn['vendidas'] ?>/<?= (int)$fn['capacidad'] ?></span>
            </span>
            <span class="ocupacion"><i style="width:<?= $pct ?>%"></i></span>
            <?php // Los precios viven en cada función: se muestran acá para que se vean y se encuentren. ?>
            <span class="lf-precios">
              <?php foreach (filas('SELECT nombre, precio, activo, precio_oferta, oferta_hasta FROM tipos_entrada WHERE funcion_id = ? ORDER BY orden, id', [$fn['id']]) as $t): $of = oferta_vigente($t); ?>
                <span class="lf-precio<?= $t['activo'] ? '' : ' inactivo' ?><?= $of ? ' en-oferta' : '' ?>"><?= e($t['nombre']) ?>
                  <?php if ($of): ?><s><?= e(pesos((int)$t['precio'])) ?></s> <b><?= e(pesos((int)$t['precio_oferta'])) ?></b> <small>oferta<?= $t['oferta_hasta'] ? ' hasta ' . e(fecha_corta($t['oferta_hasta'])) : '' ?></small>
                  <?php else: ?><b><?= (int)$t['precio'] ? e(pesos((int)$t['precio'])) : 'sin cargo' ?></b><?php endif; ?></span>
              <?php endforeach; ?>
            </span>
            <span class="lf-acciones"><a class="lf-editar" href="/admin/eventos/<?= (int)$e['id'] ?>/funciones/<?= (int)$fn['id'] ?>">✎ Editar fecha y precios</a><a href="/admin/funciones/<?= (int)$fn['id'] ?>/asistentes">Asistentes</a><a href="/puerta/<?= (int)$fn['id'] ?>">Puerta</a></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endif; ?>

      <fieldset class="panel" id="imagenes">
        <legend class="panel-titulo">Imágenes</legend>
        <?php if ($imagenes): ?>
        <div class="imagenes-grilla">
          <?php foreach ($imagenes as $i => $img): ?>
          <figure class="imagen-item<?= $i === 0 ? ' es-portada' : '' ?>">
            <img src="<?= e(imagen_url($img['archivo'], 'm')) ?>" alt="" loading="lazy">
            <figcaption>
              <?php if ($i === 0): ?><span class="estado estado-publicado">Portada</span>
              <?php else: ?><button class="enlace" type="submit" form="img-portada-<?= (int)$img['id'] ?>">Hacer portada</button><?php endif; ?>
              <button class="enlace peligro" type="submit" form="img-borrar-<?= (int)$img['id'] ?>" data-confirmar="¿Borrar esta imagen?">Borrar</button>
            </figcaption>
          </figure>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <label class="soltar" data-soltar>
          <input type="file" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple>
          <span><strong>Elegí o arrastrá imágenes</strong><br><span class="tenue chico">JPG, PNG o WebP. Se ajustan solas a 1800 px; la primera es la portada. Proporción ideal 3:2.</span></span>
        </label>
        <div class="previas" data-previas></div>
      </fieldset>

      <fieldset class="panel">
        <legend class="panel-titulo">Metainformación <span class="tenue">· buscadores y redes</span></legend>
        <label class="campo"><span>Dirección del evento</span>
          <span class="campo-prefijo"><b>/evento/</b><input type="text" name="slug" value="<?= e($e['slug']) ?>" placeholder="se arma sola con el título" pattern="[a-z0-9-]*" data-slug></span></label>
        <label class="campo"><span>Meta título <i>si queda vacío, se usa el título</i></span>
          <input type="text" name="meta_titulo" value="<?= e($e['meta_titulo']) ?>" maxlength="70" data-meta-titulo></label>
        <label class="campo"><span>Meta descripción <i>hasta 160 caracteres</i></span>
          <textarea name="meta_descripcion" rows="2" maxlength="300" data-meta-desc><?= e($e['meta_descripcion']) ?></textarea></label>
        <label class="campo"><span>Palabras clave</span>
          <input type="text" name="meta_palabras" value="<?= e($e['meta_palabras']) ?>"></label>
        <div class="previa-google" aria-label="Así se vería en Google">
          <span class="pg-url"><?= e(preg_replace('#^https?://#', '', URL_PUBLICA)) ?> › evento › <span data-pg-slug><?= e($e['slug'] ?: '…') ?></span></span>
          <span class="pg-titulo" data-pg-titulo><?= e(($e['meta_titulo'] ?: $e['titulo']) ?: 'Título del evento') ?></span>
          <span class="pg-desc" data-pg-desc><?= e($e['meta_descripcion'] ?: $e['bajada']) ?></span>
        </div>
      </fieldset>
    </div>

    <aside class="form-evento-lateral">
      <div class="panel panel-pegajoso">
        <label class="campo"><span>Estado</span>
          <select name="estado">
            <?php foreach (['borrador' => 'Borrador — no se ve', 'publicado' => 'Publicado — a la venta', 'cancelado' => 'Cancelado'] as $k => $t): ?>
              <option value="<?= $k ?>"<?= $e['estado'] === $k ? ' selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select></label>
        <label class="campo"><span>Visibilidad</span>
          <select name="visibilidad">
            <option value="publica"<?= $e['visibilidad'] === 'publica' ? ' selected' : '' ?>>En el catálogo</option>
            <option value="enlace"<?= $e['visibilidad'] === 'enlace' ? ' selected' : '' ?>>Sólo con el enlace</option>
          </select></label>
        <button class="btn btn-bloque" type="submit">Guardar</button>
        <?php if (!$nuevo): ?>
        <div class="lateral-extra">
          <button class="enlace" type="submit" form="f-duplicar">Duplicar evento</button>
          <?php if (!$vendidas): ?><button class="enlace peligro" type="submit" form="f-borrar" data-confirmar="¿Borrar el evento? No se puede deshacer.">Borrar evento</button><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </aside>
  </form>

  <?php // Formularios sueltos: un form no puede ir adentro de otro. ?>
  <?php if (!$nuevo): ?>
    <form id="f-duplicar" method="post" action="/admin/eventos/<?= (int)$e['id'] ?>/duplicar" hidden><?= campo_csrf() ?></form>
    <form id="f-borrar" method="post" action="/admin/eventos/<?= (int)$e['id'] ?>/borrar" hidden><?= campo_csrf() ?></form>
    <?php foreach ($imagenes as $img): ?>
      <form id="img-portada-<?= (int)$img['id'] ?>" method="post" action="/admin/imagenes/<?= (int)$img['id'] ?>/portada" hidden><?= campo_csrf() ?></form>
      <form id="img-borrar-<?= (int)$img['id'] ?>" method="post" action="/admin/imagenes/<?= (int)$img['id'] ?>/borrar" hidden><?= campo_csrf() ?></form>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php if ($nuevo): ?><script src="<?= estatico('mapa.js') ?>" defer></script><?php endif; ?>
