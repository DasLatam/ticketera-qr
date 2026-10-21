<?php $nombres = ['hora' => 'Última hora', 'dia' => 'Últimas 24 h', 'semana' => 'Últimos 7 días', 'mes' => 'Últimos 30 días']; ?>
<section class="contenedor">
  <div class="titulo-con-accion">
    <h1 class="titulo-pagina">Correos</h1>
    <div class="acciones">
      <form method="post" action="/admin/correos/prueba"><?= campo_csrf() ?><button class="btn btn-secundario">Enviar correo de prueba</button></form>
      <form method="post" action="/admin/correos/despachar"><?= campo_csrf() ?><button class="btn btn-secundario">Despachar ahora</button></form>
    </div>
  </div>
  <p class="tenue">Salen desde <strong><?= e(CORREO_CONTACTO) ?></strong>. Los topes por casilla (configurables en <span class="mono">CORREO_TOPES</span>) son <?= CORREO_TOPES['hora'][1] ?> por hora, <?= CORREO_TOPES['dia'][1] ?> por día, <?= CORREO_TOPES['semana'][1] ?> por semana y <?= CORREO_TOPES['mes'][1] ?> por mes; el despacho frena al <?= (int)(CORREO_MARGEN * 100) ?> % de cada tope y lo que no entra espera al próximo período.<?= ES_PROD ? '' : ' <strong>Modo prueba: no se envía nada.</strong>' ?><?= ES_PROD && SMTP_CLAVE === '' ? ' <strong>Falta la contraseña de la casilla (secretos.php).</strong>' : '' ?></p>
  <?php if ($cron): [$cuando] = explode('|', $cron); $hace = (int)round((time() - strtotime($cuando)) / 60); ?>
    <p class="chico">Tarea programada de respaldo: última corrida <?= e(fecha_corta($cuando)) ?> h (hace <?= $hace ?> min)<?= $hace > 40 ? ' — <strong>parece detenida</strong>: revisar la tarea programada del hosting' : '' ?>.</p>
  <?php else: ?>
    <p class="chico tenue">La tarea programada de respaldo todavía no corrió nunca<?= ES_PROD ? '' : ' (en modo prueba no hace falta)' ?>.</p>
  <?php endif; ?>
  <div class="kpis">
    <?php foreach ($cupo['detalle'] as $k => $d): $pct = min(100, round($d['usados'] / $d['tope'] * 100)); ?>
    <div class="kpi"><span><?= $nombres[$k] ?></span><strong><?= number_format($d['usados'], 0, ',', '.') ?> <small>/ <?= number_format($d['tope'], 0, ',', '.') ?></small></strong>
      <span class="ocupacion<?= $d['usados'] >= $d['techo'] ? ' llena' : '' ?>"><i style="width:<?= $pct ?>%"></i></span></div>
    <?php endforeach; ?>
  </div>
  <p class="chico"><?php foreach (['pendiente' => 'en cola', 'enviado' => 'enviados', 'error' => 'con error (se reintentan)', 'omitido' => 'omitidos (cuentas de prueba o copia local)'] as $k => $t): ?><span class="estado estado-<?= $k === 'enviado' ? 'usada' : ($k === 'error' ? 'anulada' : 'borrador') ?>"><?= (int)($estados[$k] ?? 0) ?> <?= $t ?></span> <?php endforeach; ?></p>
  <?php if ($correos): ?>
  <div class="tabla-envoltura">
  <table class="tabla">
    <thead><tr><th>Para</th><th>Asunto</th><th>Estado</th><th>Creado</th></tr></thead>
    <tbody>
    <?php foreach ($correos as $c): ?>
      <tr>
        <td><?= e($c['para']) ?></td>
        <td data-l="Asunto"><?= e($c['asunto']) ?></td>
        <td data-l="Estado"><span class="estado estado-<?= $c['estado'] === 'enviado' ? 'usada' : ($c['estado'] === 'error' ? 'anulada' : 'borrador') ?>"><?= e($c['estado']) ?></span>
          <?php if ($c['error']): ?><br><span class="tenue chico"><?= e(mb_strimwidth($c['error'], 0, 90, '…')) ?></span><?php endif; ?></td>
        <td class="nowrap" data-l="Creado"><?= e(fecha_corta($c['creado'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
