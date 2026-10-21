<?php $total = array_sum(array_column($ordenes, 'total')); ?>
<section class="contenedor">
  <h1 class="titulo-pagina">Ventas</h1>
  <form class="barra-filtros" method="get" data-filtros>
    <select name="evento" aria-label="Evento">
      <option value="">Todos los eventos</option>
      <?php foreach ($eventos as $ev): ?><option value="<?= (int)$ev['id'] ?>"<?= $evento == $ev['id'] ? ' selected' : '' ?>><?= e($ev['titulo']) ?></option><?php endforeach; ?>
    </select>
    <span class="tenue"><?= count($ordenes) ?> compras · <?= e(pesos($total)) ?></span>
  </form>

  <?php if (!$ordenes): ?>
    <div class="vacio"><p>Todavía no hay compras.</p></div>
  <?php else: ?>
  <div class="tabla-envoltura">
  <table class="tabla">
    <thead><tr><th>Orden</th><th>Comprador</th><th>Evento</th><th class="num">Entradas</th><th class="num">Total</th><th>Medio</th><th>Fecha</th></tr></thead>
    <tbody>
    <?php foreach ($ordenes as $o): ?>
      <tr>
        <td class="mono"><a href="/orden/<?= e($o['codigo']) ?>"><?= e($o['codigo']) ?></a></td>
        <td data-l="Comprador"><?= e($o['nombre']) ?><br><span class="tenue chico"><?= e($o['email']) ?></span></td>
        <td data-l="Evento"><?= e($o['titulo']) ?><br><span class="tenue chico"><?= e(fecha_corta($o['inicio'])) ?> h</span></td>
        <td class="num" data-l="Entradas"><?= (int)$o['n'] ?><?= $o['anuladas'] ? ' <span class="tenue chico">(' . (int)$o['anuladas'] . ' anul.)</span>' : '' ?></td>
        <td class="num" data-l="Total"><?= e(pesos((int)$o['total'])) ?></td>
        <td data-l="Medio"><?= e($o['medio_pago']) ?></td>
        <td class="nowrap" data-l="Fecha"><?= e(fecha_corta($o['creado'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
