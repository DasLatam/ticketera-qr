<section class="contenedor">
  <h1 class="titulo-pagina">Arrepentimientos</h1>
  <p class="tenue">Pedidos del <a href="/arrepentimiento">botón de arrepentimiento</a>. Los que entran en la regla (<?= ARREPENTIMIENTO_DIAS ?> días corridos desde que recibió las entradas y hasta <?= ARREPENTIMIENTO_HORAS_ANTES ?> h antes de la función) anulan la compra solos; acá queda <strong>devolver el dinero</strong> y marcarlo. Los que no entran no se rechazan solos: los decidís vos.</p>

  <?php if (!$lista): ?>
    <div class="vacio"><p>Todavía no hay pedidos.</p></div>
  <?php else: ?>
  <div class="tabla-envoltura">
    <table class="tabla">
      <thead><tr><th>Trámite</th><th>Compra</th><th>Quién</th><th class="num">A devolver</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lista as $a): [$txt, $cls] = ARREPENTIMIENTO_ESTADOS[$a['estado']]; ?>
        <tr>
          <td><strong class="mono"><?= e($a['codigo']) ?></strong><br><span class="tenue chico"><?= e(fecha_corta($a['creado'])) ?> h</span></td>
          <td data-l="Compra"><a href="/orden/<?= e($a['orden']) ?>" class="mono"><?= e($a['orden']) ?></a><br><span class="chico"><?= e($a['titulo']) ?> · <?= e(fecha_corta($a['inicio'])) ?> h</span></td>
          <td data-l="Quién"><?= e($a['nombre']) ?><br><span class="tenue chico"><?= e($a['email']) ?></span>
            <?= $a['comentario'] ? '<br><span class="chico">«' . e($a['comentario']) . '»</span>' : '' ?></td>
          <td class="num" data-l="A devolver">
            <?php if ((int)$a['importe']): ?><?= e(pesos((int)$a['importe'])) ?>
            <?php elseif ($a['comprobante']): ?><span class="chico">lo que haya entrado<br>(había comprobante)</span>
            <?php else: ?><span class="tenue">—</span><?php endif; ?>
            <?= $a['reintegro'] ? '<br><span class="mono chico">' . e($a['reintegro']) . '</span>' : '' ?></td>
          <td data-l="Estado"><span class="estado estado-<?= $cls ?>"><?= $txt ?></span>
            <?= $a['detalle'] && $a['estado'] === 'a_revisar' ? '<br><span class="chico">' . e($a['detalle']) . '</span>' : '' ?>
            <?= $a['nota'] ? '<br><span class="tenue chico">' . e($a['nota']) . '</span>' : '' ?>
            <?= $a['resolvio'] ? '<br><span class="tenue chico">' . e($a['resolvio']) . ', ' . e(fecha_corta($a['resuelto_en'])) . ' h</span>' : '' ?></td>
          <td class="acciones-fila acciones-arrep">
            <?php if ($a['estado'] === 'aceptado'): ?>
              <form method="post" action="/admin/arrepentimientos/<?= (int)$a['id'] ?>/reintegrado"><?= campo_csrf() ?>
                <input type="text" name="nota" placeholder="N.º de operación (opcional)" maxlength="120" aria-label="Número de operación del reintegro">
                <button class="btn btn-chico" data-confirmar="¿Ya hiciste la transferencia de <?= e(pesos((int)$a['importe'])) ?>? Le avisamos por correo.">Ya lo devolví</button></form>
            <?php elseif ($a['estado'] === 'a_revisar'): ?>
              <form method="post" action="/admin/arrepentimientos/<?= (int)$a['id'] ?>/aceptar"><?= campo_csrf() ?>
                <button class="btn btn-chico" data-confirmar="¿Aceptar igual? Se anula la compra y se liberan los lugares.">Aceptar igual</button></form>
              <form method="post" action="/admin/arrepentimientos/<?= (int)$a['id'] ?>/rechazar"><?= campo_csrf() ?>
                <input type="text" name="nota" placeholder="Motivo (le llega por correo)" maxlength="300" required aria-label="Motivo del rechazo">
                <button class="enlace peligro">Rechazar</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
