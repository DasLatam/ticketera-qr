<?php
$es_imagen = fn($o) => $o['comprobante'] && str_starts_with(tipo_comprobante($o['comprobante']), 'image/') && !str_ends_with($o['comprobante'], '.heic');
$falta_cuenta = ajuste('transf_alias') === '' && ajuste('transf_cbu') === '';
$nombres = ['sugeridas' => 'Sugeridas por Mercado Pago', 'revisar' => 'Con comprobante', 'esperando' => 'Esperando pago',
            'sin_reserva' => 'Plata sin reserva', 'auto' => 'Confirmadas solas', 'resueltas' => 'Resueltas'];
$ayuda = [
    'sugeridas' => 'Entró un pago en Mercado Pago que probablemente es de esta reserva, pero sin una prueba fuerte (coincide el nombre, o es la única reserva por ese importe). Revisá y confirmá.',
    'revisar' => 'Mandaron el comprobante pero Mercado Pago todavía no informó el pago (o no está conectado). Verificá en la cuenta.',
    'esperando' => 'Reservas sin comprobante ni pago informado. Si vencen, se cancelan solas.',
    'sin_reserva' => 'Pagos que entraron a la cuenta y no coinciden con ninguna reserva pendiente: alguien que transfirió otro monto, tarde, o algo ajeno a las entradas.',
    'auto' => 'Reservas que se confirmaron solas porque entró el importe exacto en Mercado Pago y el comprobante o los datos de la persona lo prueban (número de operación, DNI, CUIT, código). Últimos 7 días.',
    'resueltas' => 'Las últimas confirmadas o canceladas.',
];
$cuanto_falta = function (string $inicio): string {
    $h = (strtotime($inicio) - time()) / 3600;
    if ($h < 0) return '<span class="urgencia urgencia-ya">empezó</span>';
    if ($h < 24) return '<span class="urgencia urgencia-ya">función en ' . max(1, (int)round($h)) . ' h</span>';
    if ($h < 72) return '<span class="urgencia">función en ' . (int)round($h / 24) . ' días</span>';
    return '';
};
$tarjeta_orden = function (array $o, string $extra = '') use ($es_imagen, $cuanto_falta) { ?>
    <article class="panel transf<?= $o['comprobante'] ? '' : ' transf-sin-comp' ?>">
      <?php if ($o['comprobante']): ?>
      <a class="transf-comprobante" href="/comprobante/<?= e($o['codigo']) ?>" target="_blank" title="Abrir el comprobante en tamaño completo">
        <?php if ($es_imagen($o)): ?><img src="/comprobante/<?= e($o['codigo']) ?>" alt="Comprobante de <?= e($o['nombre']) ?>" loading="lazy">
        <?php else: ?><span class="transf-archivo"><?= e(strtoupper(pathinfo($o['comprobante'], PATHINFO_EXTENSION))) ?><small>Abrir</small></span><?php endif; ?>
        <span class="transf-ampliar">Ampliar ↗</span>
      </a>
      <?php endif; ?>
      <div class="transf-datos">
        <div class="transf-monto"><?= e(pesos_cent(importe_orden($o))) ?> <span class="mono"><?= e($o['codigo']) ?></span> <?= $cuanto_falta($o['inicio']) ?></div>
        <p><strong><?= e($o['nombre']) ?></strong> · <?= e($o['email']) ?><?= $o['telefono'] ? ' · ' . e($o['telefono']) : '' ?><?= $o['dni'] ? ' · DNI ' . e($o['dni']) : '' ?></p>
        <p class="tenue chico"><?= (int)$o['n'] ?> × <?= e($o['titulo']) ?> · <?= e(fecha_corta($o['inicio'])) ?> h<br>
          Reservó <?= e(fecha_corta($o['creado'])) ?> h<?= $o['comprobante_en'] ? ' · comprobante ' . e(fecha_corta($o['comprobante_en'])) . ' h' : ' · <strong>sin comprobante</strong> · vence ' . e(fecha_corta($o['vence'])) . ' h' ?></p>
        <?= $extra ?>
        <?php if ($o['comprobante']): ?>
        <div class="ocr" data-ocr data-orden="<?= (int)$o['id'] ?>" data-url="/comprobante/<?= e($o['codigo']) ?>"
             data-total="<?= importe_orden($o) ?>" data-codigo="<?= e($o['codigo']) ?>" data-alias="<?= e(ajuste('transf_alias')) ?>"
             data-cbu="<?= e(preg_replace('/\D/', '', ajuste('transf_cbu'))) ?>"<?= $o['ocr'] !== null ? ' data-texto="' . e($o['ocr']) . '"' : '' ?>>
          <p class="ocr-leyendo">Preparando la lectura del comprobante…</p>
        </div>
        <?php endif; ?>
        <div class="transf-acciones">
          <form method="post" action="/admin/ordenes/<?= (int)$o['id'] ?>/aprobar"><?= campo_csrf() ?>
            <button class="btn btn-confirmar" data-confirmar="¿Ya viste en la cuenta que entraron <?= e(pesos_cent(importe_orden($o))) ?>? Se le mandan las entradas con su QR.">✓ Confirmar que recibí el dinero</button></form>
          <details class="rechazar">
            <summary class="btn btn-secundario">No llegó / rechazar</summary>
            <form method="post" action="/admin/ordenes/<?= (int)$o['id'] ?>/rechazar"><?= campo_csrf() ?>
              <label class="campo"><span>Motivo (se lo mandamos al comprador)</span>
                <select name="motivo">
                  <option>No encontramos la transferencia en la cuenta.</option>
                  <option>El monto transferido no coincide con el de la compra.</option>
                  <option>El comprobante no se lee o no corresponde a esta compra.</option>
                  <option>La reserva se canceló a pedido.</option>
                </select></label>
              <button class="btn btn-secundario btn-chico peligro-btn" data-confirmar="Se cancela la reserva y se liberan los lugares. ¿Seguro?">Rechazar y liberar lugares</button>
            </form>
          </details>
        </div>
      </div>
    </article>
<?php };
$pago_resumen = fn(array $pg) => '<strong>' . e(pesos_cent((int)$pg['importe_cent'])) . '</strong> · ' . e(fecha_corta($pg['fecha'])) . ' h'
    . ($pg['pagador'] || $pg['pagador_doc'] ? ($pg['pagador'] ? ' · ' . e($pg['pagador']) : '') . ($pg['pagador_doc'] ? ' · doc. ' . e($pg['pagador_doc']) : '') : ' · <span class="tenue">desde otro banco, sin datos de quien transfirió</span>')
    . ($pg['descripcion'] ? ' · «' . e(mb_strimwidth($pg['descripcion'], 0, 60, '…')) . '»' : '') . ' · <span class="mono tenue">MP ' . e($pg['id']) . '</span>';
?>
<section class="contenedor">
  <div class="titulo-con-accion">
    <h1 class="titulo-pagina">Transferencias</h1>
    <div class="acciones">
      <?php if ($mp['user_id'] !== ''): ?>
        <form method="post" action="/admin/mercadopago/sincronizar"><?= campo_csrf() ?><button class="btn btn-secundario btn-chico">↻ Buscar pagos</button></form>
      <?php endif; ?>
      <a class="btn btn-secundario btn-chico" href="/admin/cobro">⚙ Configurar cobro</a>
    </div>
  </div>

  <?php if ($falta_cuenta): ?><div class="aviso aviso-error">Falta cargar el alias o el CBU: hasta entonces el sitio no ofrece pagar por transferencia. <a href="/admin/cobro#cuenta">Cargarlos</a></div><?php endif; ?>
  <p class="linea-estado<?= $mp['user_id'] !== '' ? ' conectada' : '' ?>">
    <?php if ($mp['user_id'] !== ''): ?>
      <span class="punto"></span> Mercado Pago conectado (<?= e($mp['nickname'] ?: $mp['user_id']) ?>)<?= !empty($mp['prueba']) ? ' <strong class="peligro">· cuenta de prueba</strong>' : '' ?><?= ajuste('mp_auto_confirmar') === '1' ? ': las que coinciden se confirman solas' : ': los pagos se sugieren y se confirman a mano' ?>.
      <span class="tenue">Última consulta <?= ajuste('mp_ultima_sync') ? e(fecha_corta(ajuste('mp_ultima_sync'))) . ' h' : '—' ?></span>
      <?php if (ajuste('mp_error')): ?> · <a href="/admin/cobro#mercadopago" class="peligro">hubo un error</a><?php endif; ?>
    <?php else: ?>
      <span class="punto"></span> Verificación a mano. <a href="/admin/cobro#mercadopago">Conectá Mercado Pago</a> para que las compras se confirmen solas.
    <?php endif; ?>
  </p>

  <form class="barra-filtros" method="get">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por código, nombre, correo o importe" aria-label="Buscar">
    <button class="btn btn-secundario btn-chico">Buscar</button>
    <?php if ($q !== ''): ?><a class="enlace-tenue" href="?tab=<?= e($tab) ?>">Limpiar</a><?php endif; ?>
  </form>

  <nav class="pestanas-admin" aria-label="Bandejas">
    <?php foreach ($nombres as $k => $t): ?>
      <a href="?tab=<?= $k ?><?= $q !== '' ? '&q=' . e(rawurlencode($q)) : '' ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?> class="pest-<?= $k ?>"><?= $t ?> <span><?= $pestanas[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <p class="tenue chico ayuda-pestana"><?= $ayuda[$tab] ?></p>

  <?php if ($tab === 'sugeridas'): ?>
    <?php if (!$sugeridas): ?><div class="vacio"><p>No hay pagos sugeridos para revisar.</p></div><?php else: ?>
    <form method="post" action="/admin/transferencias/confirmar-sugeridas" id="f-lote"><?= campo_csrf() ?></form>
    <div class="lote">
      <label class="check"><input type="checkbox" data-todas> <span>Seleccionar todas</span></label>
      <button class="btn btn-confirmar btn-chico" form="f-lote" data-confirmar="Se confirman las reservas marcadas con su pago y se mandan las entradas. ¿Seguro?">✓ Confirmar las seleccionadas</button>
    </div>
    <div class="transf-lista" data-csrf-admin="<?= e(csrf()) ?>">
      <?php foreach ($sugeridas as $s):
        $extra = '<div class="sugerencia-pago"><label class="check"><input type="checkbox" name="par[]" value="' . e($s['pago']['id'] . ':' . $s['orden']['id']) . '" form="f-lote" data-par>'
          . '<span>Pago informado: ' . $pago_resumen($s['pago']) . '<br><span class="tenue chico">Por qué: ' . e($s['motivo']) . '</span></span></label>'
          . '<form method="post" action="/admin/pagos-mp/' . e($s['pago']['id']) . '/conciliar">' . campo_csrf() . '<input type="hidden" name="orden_id" value="' . (int)$s['orden']['id'] . '">'
          . '<button class="btn btn-confirmar btn-chico">✓ Es este pago: confirmar</button></form></div>';
        $tarjeta_orden($s['orden'], $extra); endforeach; ?>
    </div>
    <?php endif; ?>

  <?php elseif ($tab === 'revisar'): ?>
    <?php if (!$revisar): ?><div class="vacio"><p>No hay comprobantes esperando verificación.</p></div><?php endif; ?>
    <div class="transf-lista" data-csrf-admin="<?= e(csrf()) ?>">
      <?php foreach ($revisar as $o) $tarjeta_orden($o); ?>
    </div>

  <?php elseif ($tab === 'esperando'): ?>
    <?php if (!$esperando): ?><div class="vacio"><p>No hay reservas esperando pago.</p></div><?php else: ?>
    <div class="tabla-envoltura">
      <table class="tabla">
        <thead><tr><th>Comprador</th><th>Función</th><th class="num">Importe</th><th>Vence</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($esperando as $o): ?>
          <tr>
            <td><strong><?= e($o['nombre']) ?></strong><br><span class="tenue chico"><?= e($o['email']) ?> · <span class="mono"><?= e($o['codigo']) ?></span></span></td>
            <td data-l="Función"><?= (int)$o['n'] ?> × <?= e($o['titulo']) ?><br><span class="tenue chico"><?= e(fecha_corta($o['inicio'])) ?> h</span> <?= $cuanto_falta($o['inicio']) ?></td>
            <td class="num" data-l="Importe"><?= e(pesos_cent(importe_orden($o))) ?></td>
            <td class="nowrap" data-l="Vence"><?= e(fecha_corta($o['vence'])) ?> h</td>
            <td class="acciones-fila">
              <form method="post" action="/admin/ordenes/<?= (int)$o['id'] ?>/aprobar"><?= campo_csrf() ?><button class="enlace" data-confirmar="¿Confirmar sin comprobante? Hacelo sólo si viste el ingreso en la cuenta.">Recibí el dinero</button></form>
              <form method="post" action="/admin/ordenes/<?= (int)$o['id'] ?>/rechazar"><?= campo_csrf() ?><input type="hidden" name="motivo" value="La reserva se canceló a pedido."><button class="enlace peligro" data-confirmar="Se cancela la reserva y se liberan los lugares.">Cancelar</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  <?php elseif ($tab === 'sin_reserva'): ?>
    <?php if (!$sin_reserva): ?><div class="vacio"><p>Toda la plata que entró corresponde a una reserva.</p></div><?php else: ?>
    <div class="tabla-envoltura">
      <table class="tabla">
        <thead><tr><th>Pago informado</th><th>Por qué no se asignó</th><th>Asignar a una reserva</th></tr></thead>
        <tbody>
        <?php foreach ($sin_reserva as $pg): ?>
          <tr>
            <td><?= $pago_resumen($pg) ?></td>
            <td data-l="Motivo" class="chico"><?= e($pg['resultado'] ?? 'sin procesar') ?></td>
            <td class="acciones-fila">
              <form method="post" action="/admin/pagos-mp/<?= e($pg['id']) ?>/conciliar" class="asignar"><?= campo_csrf() ?><input type="hidden" name="volver" value="sin_reserva">
                <input type="text" placeholder="Código de orden" aria-label="Código de la orden" data-codigo-a-id>
                <input type="hidden" name="orden_id" value="">
                <button class="btn btn-secundario btn-chico">Asignar</button></form>
              <form method="post" action="/admin/pagos-mp/<?= e($pg['id']) ?>/descartar"><?= campo_csrf() ?><button class="enlace" data-confirmar="¿Marcar este pago como ajeno a las entradas?">No corresponde</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

  <?php else: $lista = $tab === 'auto' ? $auto : $resueltas; ?>
    <?php if (!$lista): ?><div class="vacio"><p>Nada por acá todavía.</p></div><?php else: ?>
    <div class="tabla-envoltura">
      <table class="tabla">
        <thead><tr><th>Comprador</th><th class="num">Importe</th><th>Resultado</th><th>Cuándo</th></tr></thead>
        <tbody>
        <?php foreach ($lista as $o): ?>
          <tr>
            <td><strong><?= e($o['nombre']) ?></strong><br><span class="tenue chico"><?= e($o['titulo']) ?> · <span class="mono"><?= e($o['codigo']) ?></span><?= $o['comprobante'] ? ' · <a href="/comprobante/' . e($o['codigo']) . '" target="_blank">comprobante</a>' : '' ?></span></td>
            <td class="num" data-l="Importe"><?= e(pesos_cent(importe_orden($o))) ?></td>
            <td data-l="Resultado"><span class="estado estado-<?= $o['estado'] === 'pagada' ? 'publicado' : 'cancelado' ?>"><?= $o['estado'] === 'pagada' ? 'confirmada' : 'cancelada' ?></span>
              <br><span class="tenue chico"><?= e(['mp-automatica' => 'sola, por Mercado Pago', 'mp-sugerida' => 'con el pago de Mercado Pago', 'manual' => 'a mano'][$o['conciliacion'] ?? ''] ?? '') ?><?= $o['mp_pago_id'] ? ' · MP ' . e($o['mp_pago_id']) : '' ?><?= $o['nota'] ? e($o['nota']) : '' ?></span></td>
            <td class="nowrap" data-l="Cuándo"><?= $o['verificada_en'] ? e(fecha_corta($o['verificada_en'])) . ' h' : '' ?><br><span class="tenue chico"><?= e($o['verificador'] ?? 'automático') ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (($tab === 'revisar' && $revisar) || ($tab === 'sugeridas' && $sugeridas)): ?><script src="<?= estatico('ocr.js') ?>" defer></script><?php endif; ?>

</section>
<script>
document.querySelector('[data-todas]')?.addEventListener('change', (ev) => document.querySelectorAll('[data-par]').forEach((c) => (c.checked = ev.target.checked)));
// «Asignar» en plata sin reserva: se escribe el código de la orden y se busca su id.
document.querySelectorAll('form.asignar').forEach((f) => f.addEventListener('submit', async (ev) => {
  const id = f.querySelector('[name=orden_id]');
  if (id.value) return;
  ev.preventDefault();
  const cod = f.querySelector('[data-codigo-a-id]').value.trim().toUpperCase();
  try {
    const d = await (await fetch('/api/admin/orden-por-codigo/' + encodeURIComponent(cod), { credentials: 'same-origin' })).json();
    if (!d.id) { alert('No hay una reserva pendiente con el código ' + cod + '.'); return; }
    if (confirm('¿Asignar este pago a la orden ' + cod + ' (' + d.nombre + ', ' + d.importe + ') y confirmarla?')) { id.value = d.id; f.submit(); }
  } catch (_) { alert('No se pudo buscar la orden.'); }
}));
</script>
