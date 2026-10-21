<?php
$f = $_SESSION['cupon_form'] ?? [];
unset($_SESSION['cupon_form']);
$v = fn($k, $d = '') => e($f[$k] ?? $d);
$estado = function (array $c): array {
    if (!$c['activo']) return ['desactivado', 'borrador'];
    if ($c['vence'] && $c['vence'] <= ahora()) return ['vencido', 'cancelado'];
    if ($c['desde'] && $c['desde'] > ahora()) return ['programado', 'borrador'];
    if ($c['usos_max'] !== null && (int)$c['usos'] >= (int)$c['usos_max']) return ['agotado', 'cancelado'];
    return ['vigente', 'publicado'];
};
?>
<section class="contenedor">
  <div class="titulo-con-accion">
    <h1 class="titulo-pagina">Cupones</h1>
    <a class="btn btn-secundario btn-chico" href="#nuevo">+ Nuevo cupón</a>
  </div>
  <p class="tenue">Un código que el comprador escribe al confirmar la compra («¿Tenés un cupón de descuento?»). Se aplica sobre el precio vigente, incluida la oferta si la hay. Una reserva que se cancela o vence devuelve el uso.</p>

  <?php if (!$cupones): ?>
    <div class="vacio"><p>Todavía no hay cupones.</p></div>
  <?php else: ?>
  <div class="tabla-envoltura">
    <table class="tabla">
      <thead><tr><th>Código</th><th>Descuento</th><th>Vale para</th><th class="num">Usos</th><th>Vigencia</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($cupones as $c): [$txt, $cls] = $estado($c); ?>
        <tr>
          <td><strong class="mono"><?= e($c['codigo']) ?></strong><?= $c['nota'] ? '<br><span class="tenue chico">' . e($c['nota']) . '</span>' : '' ?></td>
          <td data-l="Descuento"><?= e(cupon_texto($c)) ?></td>
          <td data-l="Vale para"><?= $c['evento'] ? e($c['evento']) : 'Todos los eventos' ?><?= $c['por_persona'] ? '<br><span class="tenue chico">una vez por persona</span>' : '' ?></td>
          <td class="num" data-l="Usos"><?= (int)$c['usos'] ?><?= $c['usos_max'] !== null ? ' / ' . (int)$c['usos_max'] : '' ?><?= (int)$c['descontado'] ? '<br><span class="tenue chico">−' . e(pesos((int)$c['descontado'])) . '</span>' : '' ?></td>
          <td class="chico" data-l="Vigencia"><?= $c['desde'] ? 'desde ' . e(fecha_corta($c['desde'])) . ' h<br>' : '' ?><?= $c['vence'] ? 'hasta ' . e(fecha_corta($c['vence'])) . ' h' : 'sin vencimiento' ?></td>
          <td data-l="Estado"><span class="estado estado-<?= $cls ?>"><?= $txt ?></span></td>
          <td class="acciones-fila">
            <form method="post" action="/admin/cupones/<?= (int)$c['id'] ?>/activo"><?= campo_csrf() ?><button class="enlace"><?= $c['activo'] ? 'Desactivar' : 'Activar' ?></button></form>
            <?php if (!(int)$c['usos']): ?><form method="post" action="/admin/cupones/<?= (int)$c['id'] ?>/borrar"><?= campo_csrf() ?><button class="enlace peligro" data-confirmar="¿Borrar el cupón <?= e($c['codigo']) ?>?">Borrar</button></form><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <form method="post" action="/admin/cupones" class="panel angosto-admin" id="nuevo">
    <?= campo_csrf() ?>
    <h2 class="panel-titulo">Nuevo cupón</h2>
    <div class="campos-2">
      <label class="campo"><span>Código <i>letras y números, sin espacios</i></span><input type="text" name="codigo" value="<?= $v('codigo') ?>" placeholder="AMIGOS20" required maxlength="30" autocapitalize="characters" style="text-transform:uppercase"></label>
      <div class="campo"><span>Descuento</span>
        <div class="cupon-valor">
          <input type="text" name="valor" value="<?= $v('valor') ?>" inputmode="numeric" placeholder="20" required aria-label="Valor del descuento">
          <select name="tipo" aria-label="Tipo de descuento">
            <option value="pct"<?= ($f['tipo'] ?? '') !== 'monto' ? ' selected' : '' ?>>% del total</option>
            <option value="monto"<?= ($f['tipo'] ?? '') === 'monto' ? ' selected' : '' ?>>$ fijos</option>
          </select>
        </div></div>
      <label class="campo"><span>Vale para</span>
        <select name="evento_id">
          <option value="">Todos los eventos</option>
          <?php foreach ($eventos as $ev): ?><option value="<?= (int)$ev['id'] ?>"<?= (int)($f['evento_id'] ?? 0) === (int)$ev['id'] ? ' selected' : '' ?>><?= e($ev['titulo']) ?></option><?php endforeach; ?>
        </select></label>
      <label class="campo"><span>Cantidad de usos <i>vacío = sin tope</i></span><input type="number" name="usos_max" value="<?= $v('usos_max') ?>" min="1" placeholder="sin tope"></label>
      <label class="campo"><span>Vale desde <i>opcional</i></span><input type="datetime-local" name="desde" value="<?= $v('desde') ?>"></label>
      <label class="campo"><span>Vence <i>opcional</i></span><input type="datetime-local" name="vence" value="<?= $v('vence') ?>"></label>
    </div>
    <label class="check"><input type="checkbox" name="por_persona" value="1"<?= !$f || !empty($f['por_persona']) ? ' checked' : '' ?>><span>Una sola vez por persona</span></label>
    <label class="campo"><span>Nota interna <i>opcional: para quién es, de qué campaña</i></span><input type="text" name="nota" value="<?= $v('nota') ?>" maxlength="120"></label>
    <button class="btn" type="submit">Crear cupón</button>
  </form>
</section>
