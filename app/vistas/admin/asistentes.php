<?php
$etq = ['ok' => 'Ingresó', 'ya_usada' => 'Repetida', 'otro_evento' => 'Otra función', 'anulada' => 'Anulada', 'inexistente' => 'No existe'];
$pct = $resumen['vigentes'] ? round($resumen['ingresados'] / $resumen['vigentes'] * 100) : 0;
?>
<section class="contenedor">
  <a class="volver" href="/admin/eventos/<?= (int)$fn['evento_id'] ?>#funciones">← <?= e($fn['titulo']) ?></a>
  <div class="titulo-con-accion">
    <h1 class="titulo-pagina">Asistentes</h1>
    <div class="acciones">
      <a class="btn btn-secundario" href="?formato=csv">Descargar CSV</a>
      <a class="btn" href="/puerta/<?= (int)$fn['id'] ?>">Abrir escáner</a>
    </div>
  </div>
  <p class="tenue"><?= e(ucfirst(fecha_larga($fn['inicio']))) ?> · <?= e(hora($fn['inicio'])) ?> h<?= $fn['lugar'] ? ' · ' . e($fn['lugar']) : '' ?></p>

  <div class="kpis">
    <div class="kpi"><span>Vendidas</span><strong><?= (int)$resumen['vigentes'] ?></strong></div>
    <div class="kpi"><span>Ingresaron</span><strong><?= (int)$resumen['ingresados'] ?> <small><?= $pct ?>%</small></strong></div>
    <div class="kpi"><span>Anuladas</span><strong><?= (int)$resumen['anuladas'] ?></strong></div>
    <div class="kpi"><span>Recaudado</span><strong><?= e(pesos((int)$resumen['recaudado'])) ?></strong></div>
  </div>

  <form class="barra-filtros" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, correo o código" aria-label="Buscar">
    <button class="btn btn-secundario btn-chico">Buscar</button>
  </form>

  <?php if (!$entradas): ?>
    <div class="vacio"><p><?= $q !== '' ? 'Nadie coincide con la búsqueda.' : 'Todavía no se vendieron entradas.' ?></p></div>
  <?php else: ?>
  <div class="tabla-envoltura">
  <table class="tabla">
    <thead><tr><th>Titular</th><th>Tipo</th><th>Código</th><th>Comprador</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($entradas as $x): ?>
      <tr class="fila-<?= e($x['estado']) ?>">
        <td><strong><?= e($x['titular']) ?></strong></td>
        <td data-l="Tipo"><?= e($x['tipo']) ?></td>
        <td data-l="Código" class="mono"><?= e($x['codigo']) ?></td>
        <td data-l="Comprador"><?= e($x['comprador']) ?><br><span class="tenue chico"><?= e($x['email']) ?></span></td>
        <td data-l="Estado">
          <span class="estado estado-<?= e($x['estado']) ?>"><?= e(['valida' => 'válida', 'usada' => 'ingresó', 'anulada' => 'anulada'][$x['estado']]) ?></span>
          <?php if ($x['usada_en']): ?><br><span class="tenue chico"><?= e(hora($x['usada_en'])) ?> h<?= $x['portero'] ? ' · ' . e($x['portero']) : '' ?></span><?php endif; ?>
        </td>
        <td class="acciones-fila">
          <?php if ($x['estado'] === 'valida'): ?>
            <form method="post" action="/admin/entradas/<?= (int)$x['id'] ?>/anular"><?= campo_csrf() ?><button class="enlace peligro" data-confirmar="¿Anular la entrada de <?= e($x['titular']) ?>? Deja de servir para entrar.">Anular</button></form>
          <?php else: ?>
            <form method="post" action="/admin/entradas/<?= (int)$x['id'] ?>/reactivar"><?= campo_csrf() ?><button class="enlace" data-confirmar="¿Volver a dejarla válida?">Reactivar</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <?php if ($escaneos): ?>
  <div class="panel">
    <h2 class="panel-titulo">Últimas lecturas en la puerta</h2>
    <ul class="lista-simple">
      <?php foreach ($escaneos as $s): ?>
      <li>
        <span><span class="estado estado-<?= $s['resultado'] === 'ok' ? 'usada' : 'anulada' ?>"><?= e($etq[$s['resultado']] ?? $s['resultado']) ?></span>
          <?= e($s['titular'] ?: $s['codigo_leido']) ?></span>
        <span class="derecha tenue chico"><?= e(hora($s['creado'])) ?> h · <?= e($s['portero'] ?? '') ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</section>
