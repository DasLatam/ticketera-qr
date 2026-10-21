<section class="contenedor">
  <h1 class="titulo-pagina">Reseñas</h1>
  <p class="tenue">Sólo pueden opinar quienes pasaron por la puerta. Una reseña ofensiva o ajena al evento se oculta; no se borra, para que quede el registro.</p>
  <?php if (!$resenas): ?><div class="vacio"><p>Todavía no hay reseñas.</p></div><?php else: ?>
  <div class="tabla-envoltura">
  <table class="tabla">
    <thead><tr><th>Reseña</th><th>Evento y función</th><th>Autor</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($resenas as $r): ?>
      <tr<?= $r['visible'] ? '' : ' class="fila-anulada"' ?>>
        <td><?= estrellas((float)$r['puntaje'], 'chicas') ?><?php if ($r['texto']): ?><br><span class="chico"><?= e(mb_strimwidth($r['texto'], 0, 180, '…')) ?></span><?php endif; ?></td>
        <td data-l="Evento"><a href="/evento/<?= e($r['slug']) ?>#resenas"><?= e($r['titulo']) ?></a><br><span class="tenue chico"><?= e(fecha_corta($r['inicio'])) ?> h · <?= e($r['lugar']) ?></span></td>
        <td data-l="Autor"><?= e($r['nombre']) ?><br><span class="tenue chico"><?= e($r['email']) ?> · <?= e(date('j/n', strtotime($r['creado']))) ?></span></td>
        <td class="acciones-fila"><form method="post" action="/admin/resenas/<?= (int)$r['id'] ?>/visible"><?= campo_csrf() ?><button class="enlace<?= $r['visible'] ? ' peligro' : '' ?>"><?= $r['visible'] ? 'Ocultar' : 'Mostrar' ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
