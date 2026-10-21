<?php
$transf = $o['medio_pago'] === 'transferencia';
$pendiente = $o['estado'] === 'pendiente';
$cancelada = $o['estado'] === 'cancelada';
// Para transferir alcanza con alias o CBU; lo de depósito va aparte.
$datos = array_filter([
    'Alias' => ajuste('transf_alias'), 'CBU / CVU' => ajuste('transf_cbu'),
    'Titular' => ajuste('transf_titular'), 'CUIT' => ajuste('transf_cuit'),
], fn($v) => $v !== '');
$deposito = array_filter([
    'Banco' => ajuste('transf_banco'), 'Tipo de cuenta' => ajuste('transf_tipo_cuenta'), 'Sucursal' => ajuste('transf_sucursal'),
    'Número de cuenta' => ajuste('transf_cuenta'), 'Titular' => ajuste('transf_titular'), 'CUIT' => ajuste('transf_cuit'),
], fn($v) => $v !== '');
$subir = function (string $codigo, bool $reemplazo = false) { ?>
  <form method="post" action="/orden/<?= e($codigo) ?>/comprobante" enctype="multipart/form-data" class="subir-comprobante" data-subir>
    <?= campo_csrf() ?>
    <div class="subir-botones">
      <label class="btn<?= $reemplazo ? ' btn-secundario' : '' ?> subir-boton">
        <input type="file" name="comprobante" accept="image/*" capture="environment" data-archivo>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13.5" r="3.5"/></svg>
        Sacar foto</label>
      <label class="btn btn-secundario subir-boton">
        <input type="file" name="comprobante" accept="image/*,application/pdf" data-archivo>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8l-5-5Z"/><path d="M14 3v5h5M12 18v-6M9 15l3-3 3 3"/></svg>
        Elegir archivo</label>
    </div>
    <div class="previa-comprobante" data-previa hidden>
      <img alt="" data-previa-img hidden>
      <span data-elegido></span>
      <button class="btn btn-bloque btn-grande" type="submit"><?= $reemplazo ? 'Reemplazar comprobante' : 'Enviar comprobante' ?></button>
    </div>
    <p class="tenue chico">Una foto o captura de la pantalla de «transferencia enviada», o el PDF del banco. Hasta 10 MB.</p>
  </form>
<?php };
?>
<section class="contenedor angosto">
  <?php if ($pendiente && $transf): ?>
    <div class="confirmacion">
      <span class="confirmacion-icono confirmacion-espera" aria-hidden="true">⏳</span>
      <h1 class="titulo-pagina">Falta la transferencia</h1>
      <p class="tenue">Orden <strong class="mono"><?= e($o['codigo']) ?></strong> · tus entradas están reservadas</p>
    </div>
  <?php elseif ($cancelada): ?>
    <div class="confirmacion">
      <span class="confirmacion-icono confirmacion-cancelada" aria-hidden="true">×</span>
      <h1 class="titulo-pagina">Reserva cancelada</h1>
      <p class="tenue">Orden <strong class="mono"><?= e($o['codigo']) ?></strong><?= $o['nota'] ? ' · ' . e($o['nota']) : '' ?></p>
    </div>
  <?php else: ?>
    <div class="confirmacion">
      <span class="confirmacion-icono" aria-hidden="true">✓</span>
      <h1 class="titulo-pagina">Compra confirmada</h1>
      <p class="tenue">Orden <strong class="mono"><?= e($o['codigo']) ?></strong> · <?= e(fecha_corta($o['creado'])) ?> h<?= $transf ? ' · transferencia verificada' : '' ?></p>
    </div>
  <?php endif; ?>

  <div class="resumen-evento resumen-evento-sin-img">
    <div>
      <strong><?= e($o['titulo']) ?></strong>
      <span class="tenue"><?= e(ucfirst(fecha_larga($o['inicio']))) ?> · <?= e(hora($o['inicio'])) ?> h<?= $o['lugar'] ? ' · ' . e($o['lugar']) : '' ?></span>
    </div>
  </div>

  <?php if ($pendiente && $transf): ?>
    <?php if (MODO_DEMO): ?><div class="aviso aviso-error"><strong>Versión de prueba: no transfieras dinero.</strong> Los datos de la cuenta son de ejemplo. Para probar el circuito, subí cualquier imagen como comprobante.</div><?php endif; ?>
    <ol class="pasos-transferencia">
      <li class="<?= $o['comprobante'] ? 'hecho' : 'actual' ?>">
        <?php $imp = importe_orden($o); ?>
        <h2><b>1</b> Transferí <?= e(pesos_cent($imp)) ?></h2>
        <dl class="datos-cuenta">
          <div><dt>Monto exacto</dt><dd><span class="mono" data-copiable><?= e(intdiv($imp, 100) . ($imp % 100 ? ',' . str_pad((string)($imp % 100), 2, '0', STR_PAD_LEFT) : '')) ?></span><button type="button" class="copiar" data-copiar>Copiar</button></dd></div>
          <?php foreach ($datos as $k => $v): ?>
            <div><dt><?= e($k) ?></dt><dd><span class="mono" data-copiable><?= e($v) ?></span><?php if (in_array($k, ['Alias', 'CBU / CVU', 'CUIT'], true)): ?><button type="button" class="copiar" data-copiar>Copiar</button><?php endif; ?></dd></div>
          <?php endforeach; ?>
          <div><dt>Concepto</dt><dd><span class="mono" data-copiable><?= e($o['codigo']) ?></span><button type="button" class="copiar" data-copiar>Copiar</button></dd></div>
        </dl>
        <?php if (array_diff_key($deposito, ['Titular' => 1, 'CUIT' => 1])): ?>
        <details class="deposito">
          <summary>¿Vas a depositar en un cajero o por ventanilla?</summary>
          <dl class="datos-cuenta">
            <?php foreach ($deposito as $k => $v): ?><div><dt><?= e($k) ?></dt><dd><span class="mono" data-copiable><?= e($v) ?></span><?php if ($k === 'Número de cuenta'): ?><button type="button" class="copiar" data-copiar>Copiar</button><?php endif; ?></dd></div><?php endforeach; ?>
          </dl>
        </details>
        <?php endif; ?>
        <p class="tenue chico">Poné el código <strong><?= e($o['codigo']) ?></strong> en el concepto o la referencia: es lo que nos permite encontrar tu pago.</p>
      </li>
      <li class="<?= $o['comprobante'] ? 'hecho' : '' ?>" id="comprobante">
        <h2><b>2</b> Mandanos el comprobante</h2>
        <?php if ($o['comprobante']): ?>
          <p class="comprobante-ok">Recibimos tu comprobante el <?= e(fecha_corta($o['comprobante_en'])) ?> h. <a href="/comprobante/<?= e($o['codigo']) ?>" target="_blank">Verlo</a></p>
          <details class="reemplazar"><summary>¿Te equivocaste de archivo? Reemplazarlo</summary>
            <?php $subir($o['codigo'], true); ?>
          </details>
        <?php else: ?>
          <p class="tenue chico">Una captura de pantalla o el PDF que te da el banco. Tenés tiempo hasta el <strong><?= e(fecha_corta($o['vence'])) ?> h</strong>; si no llega, la reserva se cancela y los lugares vuelven a la venta.</p>
          <?php $subir($o['codigo']); ?>
        <?php endif; ?>
      </li>
      <li class="<?= $o['comprobante'] ? 'actual' : '' ?>">
        <h2><b>3</b> Lo verificamos y te llegan las entradas</h2>
        <?php if ($o['comprobante'] && $o['ocr'] === null): ?><p class="leyendo-comprobante" data-ocr-comprador data-url="/comprobante/<?= e($o['codigo']) ?>" data-codigo="<?= e($o['codigo']) ?>" data-csrf="<?= e(csrf()) ?>">Revisando tu comprobante…</p><?php endif; ?>
        <p class="tenue chico">Cruzamos tu comprobante con lo que entra a la cuenta: si el pago ya llegó, se confirma solo en unos minutos; si hay que revisarlo a mano, puede demorar unas horas. Cuando esté, te mandamos las entradas con su QR por correo y aparecen en «Mis entradas». Hasta entonces la entrada no sirve en la puerta.</p>
      </li>
    </ol>
  <?php endif; ?>

  <?php if (!$cancelada): ?>
  <h2 class="subtitulo"><?= $pendiente ? 'Entradas reservadas' : 'Tus entradas' ?></h2>
  <ul class="lista-entradas">
    <?php foreach ($entradas as $x): ?>
    <li>
      <a href="/entrada/<?= e($x['codigo']) ?>" class="fila-entrada<?= $pendiente ? ' fila-pendiente' : '' ?>">
        <?php if ($pendiente): ?><span class="qr-mini qr-bloqueado" aria-hidden="true">⏳</span>
        <?php else: ?><span class="qr-mini" data-qr="TQR:<?= e($x['codigo']) ?>" aria-hidden="true"></span><?php endif; ?>
        <span class="fila-entrada-texto">
          <strong><?= e($x['titular']) ?></strong>
          <span class="tenue"><?= e($x['tipo']) ?> · <span class="mono"><?= e($x['codigo']) ?></span></span>
        </span>
        <span class="fila-entrada-ir"><?= $pendiente ? 'Pago en verificación' : 'Ver QR →' ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <div class="tabla-resumen-pie">
    <span><?= $pendiente ? 'A transferir' : ($cancelada ? 'Total' : 'Total pagado') ?><?= (int)$o['cargo'] ? ' <span class="tenue chico">(incluye ' . e(pesos((int)$o['cargo'])) . ' de cargo por servicio)</span>' : '' ?><?= (int)($o['descuento'] ?? 0) ? ' <span class="tenue chico">(con ' . e(pesos((int)$o['descuento'])) . ' de descuento por cupón)</span>' : '' ?></span>
    <strong><?= e($transf && !$cancelada ? pesos_cent(importe_orden($o)) : pesos((int)$o['total'])) ?></strong>
  </div>
  <p class="acciones">
    <a class="btn" href="/mis-entradas">Ir a mis entradas</a>
    <a class="btn btn-secundario" href="/">Seguir viendo eventos</a>
  </p>
  <?php if (!$cancelada && strtotime($o['inicio']) > time()): ?>
    <p class="tenue chico">¿Te arrepentiste? Podés anular la compra con el <a href="/arrepentimiento?orden=<?= e($o['codigo']) ?>">botón de arrepentimiento</a> dentro de los <?= ARREPENTIMIENTO_DIAS ?> días y hasta <?= ARREPENTIMIENTO_HORAS_ANTES ?> h antes de la función.</p>
  <?php endif; ?>
</section>
<?php if ($pendiente && $transf && $o['comprobante'] && $o['ocr'] === null): ?><script src="<?= estatico('ocr.js') ?>" defer></script><?php endif; ?>
<script src="/estaticos/vendor/qrcode.js" defer></script>
<script>
document.querySelectorAll('[data-copiar]').forEach((b) => b.addEventListener('click', async () => {
  const v = b.parentElement.querySelector('[data-copiable]').textContent.trim();
  try { await navigator.clipboard.writeText(v); } catch (_) {}
  const antes = b.textContent; b.textContent = 'Copiado'; b.classList.add('copiado');
  setTimeout(() => { b.textContent = antes; b.classList.remove('copiado'); }, 1500);
}));
document.querySelectorAll('[data-subir]').forEach((f) => {
  // Dos botones (cámara y archivo) para un solo envío: se usa el último que
  // se eligió y el otro se vacía, así no viajan dos archivos.
  const ins = [...f.querySelectorAll('[data-archivo]')];
  const previa = f.querySelector('[data-previa]'), img = f.querySelector('[data-previa-img]'), txt = f.querySelector('[data-elegido]');
  ins.forEach((inp) => inp.addEventListener('change', () => {
    const arch = inp.files[0];
    if (!arch) return;
    // Sólo el elegido lleva el nombre: con dos campos «comprobante», PHP se
    // queda con el último aunque esté vacío.
    ins.forEach((o) => { if (o !== inp) { o.value = ''; o.removeAttribute('name'); } });
    inp.name = 'comprobante';
    previa.hidden = false;
    txt.textContent = arch.name + ' · ' + Math.max(1, Math.round(arch.size / 1024)) + ' KB';
    if (arch.type.startsWith('image/') && !/heic|heif/i.test(arch.type)) { img.src = URL.createObjectURL(arch); img.hidden = false; } else img.hidden = true;
  }));
  f.addEventListener('submit', () => { const bt = f.querySelector('button[type=submit]'); bt.disabled = true; bt.textContent = 'Enviando…'; });
});

</script>
