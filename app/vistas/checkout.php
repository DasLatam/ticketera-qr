<section class="contenedor angosto">
  <a class="volver" href="/evento/<?= e($fn['slug']) ?>">← Volver al evento</a>
  <h1 class="titulo-pagina">Confirmar compra</h1>

  <div class="resumen-evento">
    <?php if ($portada): ?><img src="<?= e(imagen_url($portada, 'm')) ?>" alt="" width="120" height="80"><?php endif; ?>
    <div>
      <strong><?= e($fn['titulo']) ?></strong>
      <span class="tenue"><?= e(ucfirst(fecha_corta($fn['inicio']))) ?> h<?= $fn['lugar'] ? ' · ' . e($fn['lugar']) : '' ?></span>
    </div>
  </div>

  <?php if ($errores): ?>
    <div class="aviso aviso-error">
      <?php foreach ($errores as $x): ?><div><?= e($x) ?></div><?php endforeach; ?>
    </div>
    <p><a class="btn btn-secundario" href="/evento/<?= e($fn['slug']) ?>?f=<?= (int)$fn['id'] ?>#entradas">Cambiar la selección</a></p>
  <?php else: ?>
  <?php $con_cupon = !empty($cup['cupon']); ?>
  <form method="post" action="/checkout/cupon" class="cupon-form<?= $con_cupon ? ' aplicado' : '' ?>" id="cupon">
    <?= campo_csrf() ?>
    <?php if ($con_cupon): ?>
      <span>🏷 Cupón <strong class="mono"><?= e($cup['cupon']['codigo']) ?></strong> · <?= e(cupon_texto($cup['cupon'])) ?></span>
      <button class="enlace" name="quitar" value="1">Quitar</button>
    <?php else: ?>
      <details<?= isset($_GET['cupon']) ? ' open' : '' ?>>
        <summary>¿Tenés un cupón de descuento?</summary>
        <div class="cupon-campo">
          <input type="text" name="cupon" placeholder="CÓDIGO" autocapitalize="characters" autocomplete="off" spellcheck="false" maxlength="40" aria-label="Código del cupón">
          <button class="btn btn-secundario btn-chico">Aplicar</button>
        </div>
      </details>
    <?php endif; ?>
  </form>

  <form method="post" action="/checkout" class="tarjeta-form">
    <?= campo_csrf() ?>
    <table class="tabla-resumen" data-resumen data-cargo="<?= (int)$cargo ?>" data-total="<?= (int)$total ?>">
      <?php foreach ($renglones as $r): ?>
        <tr><td><?= (int)$r['cant'] ?> × <?= e($r['tipo']['nombre']) ?></td><td class="num"><?= e(pesos($r['subtotal'])) ?></td></tr>
      <?php endforeach; ?>
      <?php if ($con_cupon): ?><tr class="fila-descuento"><td>Descuento <span class="tenue chico">(cupón <?= e($cup['cupon']['codigo']) ?>)</span></td><td class="num">− <?= e(pesos((int)$cup['descuento'])) ?></td></tr><?php endif; ?>
      <?php if ($cargo): ?><tr class="fila-cargo" data-fila-cargo><td>Cargo por servicio <span class="tenue chico">(pago con tarjeta)</span></td><td class="num"><?= e(pesos($cargo)) ?></td></tr><?php endif; ?>
      <tr class="total"><td>Total</td><td class="num" data-total-texto><?= e(pesos($total + $cargo)) ?></td></tr>
    </table>

    <fieldset class="titulares">
      <legend>¿A nombre de quién va cada entrada?</legend>
      <p class="tenue chico">Opcional. Si lo dejás vacío, van a tu nombre. Ayuda en la puerta cuando entran por separado.</p>
      <?php $i = 0; foreach ($renglones as $r): for ($k = 0; $k < $r['cant']; $k++, $i++): ?>
        <label class="campo">
          <span>Entrada <?= $i + 1 ?> · <?= e($r['tipo']['nombre']) ?></span>
          <input type="text" name="titular[]" placeholder="<?= e($u['nombre']) ?>" maxlength="80" autocomplete="off">
        </label>
      <?php endfor; endforeach; ?>
    </fieldset>

    <fieldset class="pago">
      <legend>¿Cómo pagás?</legend>
      <?php if ($total === 0): ?>
        <div class="medio-pago"><span class="medio-pago-icono" aria-hidden="true">✓</span>
          <div><strong>Sin cargo</strong><br><span class="tenue chico">No hay nada que pagar: tus entradas llegan al confirmar.</span></div></div>
        <input type="hidden" name="medio" value="gratis">
      <?php else: ?>
        <?php if ($tarjeta): ?>
        <label class="medio-opcion">
          <input type="radio" name="medio" value="tarjeta" checked data-medio>
          <span class="medio-cuerpo">
            <strong>Tarjeta, débito o Mercado Pago</strong>
            <span class="tenue chico">Confirmación inmediata.<?= $cargo ? ' Incluye un cargo por servicio de ' . e(pesos($cargo)) . '.' : '' ?></span>
            <?php if (PAGO_SIMULADO): ?><span class="medio-demo">Demo: se confirma sin cobrar.</span><?php endif; ?>
          </span>
          <span class="medio-precio"><?= e(pesos($total + $cargo)) ?></span>
        </label>
        <?php endif; ?>
        <?php if ($transf): ?>
        <label class="medio-opcion<?= $tarjeta ? '' : ' medio-unico' ?>">
          <input type="radio" name="medio" value="transferencia" data-medio<?= $tarjeta ? '' : ' checked' ?>>
          <span class="medio-cuerpo">
            <strong>Transferencia bancaria <span class="sin-cargo">sin cargo</span></strong>
            <?php if (MODO_DEMO): ?><span class="medio-demo">Demo: no transfieras dinero; probá subiendo cualquier imagen.</span><?php endif; ?>
            <span class="tenue chico">Al confirmar reservamos tus entradas y te mostramos el alias y el CBU. Transferís desde tu banco o billetera y subís el comprobante: si el pago ya entró, se confirma en el momento; si hay que revisarlo a mano, <strong>puede demorar unas horas</strong>. Tenés <?= (int)ajuste('transf_horas') ?> h para mandarlo.</span>
          </span>
          <span class="medio-precio"><?= e(pesos($total)) ?></span>
        </label>
        <?php else: ?>
          <div class="aviso aviso-error">La venta online para esta función ya cerró: empieza en menos de <?= (int)ajuste('transf_cierre_horas') ?> horas y el pago por transferencia necesita tiempo para verificarse.</div>
        <?php endif; ?>
      <?php endif; ?>
    </fieldset>

    <label class="check">
      <input type="checkbox" name="acepto" value="1" required>
      <span>Entiendo que cada entrada tiene un QR de un solo uso, que soy responsable de no compartirlo y que puedo arrepentirme de la compra dentro de los <?= ARREPENTIMIENTO_DIAS ?> días y hasta <?= ARREPENTIMIENTO_HORAS_ANTES ?> h antes de la función. Acepto las <a href="/condiciones" target="_blank">Condiciones de Servicio</a>.</span>
    </label>

    <?php $sin_medio = $total > 0 && !$transf && !$tarjeta; ?>
    <button class="btn btn-bloque btn-grande" type="submit" data-confirmar-texto<?= $sin_medio ? ' disabled' : '' ?>><?= $total === 0 ? 'Confirmar' : 'Confirmar compra · ' . e(pesos($total + $cargo)) ?></button>
    <p class="caja-nota">Comprás como <strong><?= e($u['nombre']) ?></strong> (<?= e($u['email']) ?>).</p>
  </form>
  <?php endif; ?>
</section>
<script>
// El total y el botón cambian según el medio: la transferencia no lleva cargo.
(() => {
  const t = document.querySelector('[data-resumen]');
  if (!t) return;
  const pesos = (n) => '$ ' + n.toLocaleString('es-AR');
  const total = +t.dataset.total, cargo = +t.dataset.cargo;
  const fila = t.querySelector('[data-fila-cargo]');
  const actualizar = () => {
    const transf = document.querySelector('[data-medio]:checked')?.value === 'transferencia';
    if (!document.querySelector('[data-medio]')) return;
    const final = transf ? total : total + cargo;
    if (fila) fila.hidden = transf;
    t.querySelector('[data-total-texto]').textContent = pesos(final);
    document.querySelector('[data-confirmar-texto]').textContent = transf ? 'Reservar entradas · ' + pesos(final) : 'Confirmar compra · ' + pesos(final);
  };
  document.querySelectorAll('[data-medio]').forEach((r) => r.addEventListener('change', actualizar));
  actualizar();
})();
</script>
