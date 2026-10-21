<section class="contenedor angosto">
  <h1 class="titulo-pagina">Botón de arrepentimiento</h1>

  <?php if ($tramite): ?>
    <div class="aviso <?= $tramite['estado'] === 'a_revisar' ? 'aviso-info' : 'aviso-ok' ?> arrepentimiento-hecho" role="status">
      <p><strong>Recibimos tu pedido.</strong> Código de trámite: <strong class="mono"><?= e($tramite['codigo']) ?></strong> (orden <?= e($tramite['orden']) ?>). Te lo mandamos también por correo.</p>
      <?php if ($tramite['estado'] === 'a_revisar'): ?>
        <p>Lo revisa una persona porque <?= e(lcfirst(rtrim($tramite['detalle'], '.'))) ?>. Te respondemos por correo; mientras tanto tus entradas siguen vigentes.</p>
      <?php elseif ($tramite['estado'] === 'aceptado'): ?>
        <p>La compra quedó anulada y vamos a devolverte <strong><?= (int)$tramite['importe'] ? e(pesos((int)$tramite['importe'])) : 'lo que transferiste' ?></strong> a <strong><?= e($tramite['reintegro']) ?></strong>. Te avisamos por correo cuando esté hecho.</p>
      <?php else: ?>
        <p>La compra quedó anulada. No había nada pagado, así que no hay dinero que devolver.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="legal">
    <p>Si compraste entradas y te arrepentiste, podés anular la compra desde acá, sin dar explicaciones y sin costo, dentro de los <strong><?= ARREPENTIMIENTO_DIAS ?> días corridos</strong> desde que recibiste las entradas y hasta <strong><?= ARREPENTIMIENTO_HORAS_ANTES ?> horas antes</strong> de la función. Te devolvemos todo lo que pagaste, por transferencia, a la cuenta que nos indiques.</p>
    <p>Al enviar el pedido te damos un código de trámite. Si la compra cumple esas condiciones se anula en el momento y los lugares vuelven a la venta; si no, lo revisa una persona y te responde por correo. Más detalle en las <a href="/condiciones#arrepentimiento">Condiciones de Servicio</a>.</p>
  </div>

  <form method="post" action="/arrepentimiento" class="form panel">
    <?= campo_csrf() ?>
    <?php if ($u): ?>
      <?php if (!$mias): ?>
        <p class="tenue">No tenés compras de funciones que todavía no pasaron. Si compraste con otro correo, salí de tu cuenta y usá el código de la orden.</p>
      <?php else: ?>
        <fieldset>
          <legend>¿De qué compra te arrepentiste?</legend>
          <?php foreach ($mias as $i => $m): ?>
          <label class="medio-opcion">
            <input type="radio" name="orden" value="<?= e($m['codigo']) ?>" required<?= $d['orden'] === $m['codigo'] || (!$d['orden'] && count($mias) === 1) ? ' checked' : '' ?>>
            <span class="medio-cuerpo">
              <strong><?= e($m['titulo']) ?></strong>
              <span class="tenue chico"><?= e(ucfirst(fecha_corta($m['inicio']))) ?> h · orden <span class="mono"><?= e($m['codigo']) ?></span><?= $m['estado'] === 'pendiente' ? ' · reserva sin confirmar' : '' ?></span>
            </span>
            <span class="medio-precio"><?= e(pesos((int)$m['total'] + (int)$m['cargo'])) ?></span>
          </label>
          <?php endforeach; ?>
        </fieldset>
      <?php endif; ?>
    <?php else: ?>
      <div class="campos-2">
        <label class="campo"><span>Código de la orden</span>
          <input type="text" name="orden" value="<?= e($d['orden'] ?? '') ?>" required maxlength="20" autocomplete="off" spellcheck="false" class="mono" placeholder="Ej.: K7M2Q9XR">
          <small>Está en el correo de la compra.</small></label>
        <label class="campo"><span>Correo de la compra</span>
          <input type="email" name="email" value="<?= e($d['email'] ?? '') ?>" required autocomplete="email"></label>
      </div>
    <?php endif; ?>

    <?php if (!$u || $mias): ?>
      <label class="campo"><span>Alias o CBU/CVU para el reintegro <i>si ya pagaste</i></span>
        <input type="text" name="reintegro" value="<?= e($d['reintegro'] ?? '') ?>" maxlength="60" autocomplete="off" spellcheck="false">
        <small>Ahí te devolvemos el dinero. Si la reserva no estaba pagada, dejalo vacío.</small></label>
      <label class="campo"><span>Titular de esa cuenta <i>opcional</i></span>
        <input type="text" name="nombre" value="<?= e($d['nombre'] ?? '') ?>" maxlength="80" autocomplete="name"></label>
      <label class="campo"><span>Comentario <i>opcional</i></span>
        <textarea name="comentario" rows="2" maxlength="1000"><?= e($d['comentario'] ?? '') ?></textarea></label>
      <?php if (!$u): ?><?= campo_captcha() ?><?php endif; ?>
      <button class="btn btn-bloque btn-grande" type="submit" data-confirmar="¿Anular la compra? Las entradas dejan de servir.">Me arrepiento: anular la compra</button>
    <?php endif; ?>
  </form>
  <?php if (!$u): ?><p class="tenue chico">¿Tenés cuenta? <a href="/ingresar?volver=/arrepentimiento">Ingresá</a> y elegí la compra de una lista.</p><?php endif; ?>
</section>
<?php if (!$u): ?><script src="<?= estatico('captcha.js') ?>" defer></script><?php endif; ?>
