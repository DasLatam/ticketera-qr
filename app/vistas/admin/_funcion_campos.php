<?php
/** Campos de una función. Espera $fn, $tipos. */
$dt = fn($s) => $s ? date('Y-m-d\TH:i', strtotime($s)) : '';
?>
<fieldset class="panel">
  <legend class="panel-titulo">Fecha y lugar</legend>
  <div class="campos-2">
    <label class="campo"><span>Empieza</span><input type="datetime-local" name="inicio" value="<?= e($dt($fn['inicio'])) ?>" required></label>
    <label class="campo"><span>Termina <i>opcional</i></span><input type="datetime-local" name="fin" value="<?= e($dt($fn['fin'])) ?>"></label>
  </div>
  <label class="campo"><span>Lugar</span><input type="text" name="lugar" value="<?= e($fn['lugar']) ?>" placeholder="Nombre del salón, casa o espacio"></label>
  <div class="campos-2">
    <label class="campo"><span>Dirección</span><input type="text" name="direccion" value="<?= e($fn['direccion']) ?>"></label>
    <label class="campo"><span>Ciudad</span><input type="text" name="ciudad" value="<?= e($fn['ciudad']) ?>"></label>
  </div>
  <div class="ubicacion">
    <div class="ubicacion-cabecera">
      <span class="campo-mini-titulo">Ubicación en el mapa</span>
      <span class="acciones">
        <button type="button" class="btn btn-secundario btn-chico" data-geo-buscar>Buscar la dirección</button>
        <button type="button" class="enlace" data-geo-quitar>Quitar</button>
      </span>
    </div>
    <div class="mapa-selector" data-mapa-selector></div>
    <p class="tenue chico" data-geo-aviso><?= $fn['lat'] !== null ? 'Arrastrá el marcador o hacé clic para corregir el punto.' : 'Buscá la dirección o hacé clic en el mapa. Sin ubicación, la función no aparece en el mapa de eventos.' ?></p>
    <input type="hidden" name="lat" value="<?= e($fn['lat']) ?>">
    <input type="hidden" name="lng" value="<?= e($fn['lng']) ?>">
  </div>
</fieldset>

<fieldset class="panel">
  <legend class="panel-titulo">Aforo y entradas</legend>
  <label class="campo campo-angosto"><span>Aforo <i>personas que entran en el lugar</i></span><input type="number" name="aforo" value="<?= (int)$fn['aforo'] ?>" min="1" required data-aforo></label>
  <div class="tipos-editor" data-tipos>
    <div class="tipos-cabecera"><span>Tipo de entrada</span><span>Precio ($)</span><span>Cupo</span><span>Activo</span><span></span></div>
    <?php foreach ($tipos as $i => $t): ?>
    <div class="tipo-fila" data-tipo-fila>
      <input type="hidden" name="tipo[<?= $i ?>][id]" value="<?= e($t['id']) ?>">
      <div class="tipo-col-nombre">
        <input type="text" name="tipo[<?= $i ?>][nombre]" value="<?= e($t['nombre']) ?>" placeholder="General" aria-label="Nombre">
        <input type="text" name="tipo[<?= $i ?>][descripcion]" value="<?= e($t['descripcion']) ?>" placeholder="Descripción breve (opcional)" aria-label="Descripción" class="chico">
        <?php $of = ($t['precio_oferta'] ?? null) !== null; $vencida = $of && !empty($t['oferta_hasta']) && $t['oferta_hasta'] <= ahora(); ?>
        <div class="tipo-oferta<?= $of ? ' con-oferta' : '' ?>">
          <label><span>Oferta $</span><input type="text" name="tipo[<?= $i ?>][precio_oferta]" value="<?= $of ? (int)$t['precio_oferta'] : '' ?>" inputmode="numeric" placeholder="—" aria-label="Precio de oferta"></label>
          <label><span>hasta</span><input type="datetime-local" name="tipo[<?= $i ?>][oferta_hasta]" value="<?= !empty($t['oferta_hasta']) ? e($dt($t['oferta_hasta'])) : '' ?>" aria-label="Oferta vigente hasta"></label>
          <?php if ($vencida): ?><small class="tenue">venció: se cobra el precio normal</small><?php endif; ?>
        </div>
      </div>
      <input type="text" name="tipo[<?= $i ?>][precio]" value="<?= (int)$t['precio'] ?>" inputmode="numeric" aria-label="Precio">
      <div><input type="number" name="tipo[<?= $i ?>][cupo]" value="<?= (int)$t['cupo'] ?>" min="0" aria-label="Cupo" data-cupo>
        <?php if (!empty($t['vendidas'])): ?><small class="tenue"><?= (int)$t['vendidas'] ?> vendidas</small><?php endif; ?></div>
      <label class="check-solo"><input type="checkbox" name="tipo[<?= $i ?>][activo]" value="1"<?= $t['activo'] ? ' checked' : '' ?>><span class="sr">Activo</span></label>
      <?php if (empty($t['vendidas'])): ?>
        <label class="quitar" title="Quitar"><input type="checkbox" name="tipo[<?= $i ?>][borrar]" value="1" data-quitar><span aria-hidden="true">×</span><span class="sr">Quitar</span></label>
      <?php else: ?><span></span><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" class="btn btn-secundario btn-chico" data-agregar-tipo>+ Agregar tipo de entrada</button>
  <p class="tenue chico"><strong>Oferta:</strong> un precio rebajado que vale hasta la fecha y hora que pongas (preventa, early bird). En la ficha se ve el precio normal tachado; al vencer vuelve solo al normal. Sin fecha, dura hasta que la borres.</p>
  <p class="tenue chico" data-aviso-cupos></p>
</fieldset>
