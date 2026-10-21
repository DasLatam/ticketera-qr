<?php $max = max(1, max(array_column($serie, 'monto'))); ?>
<section class="contenedor">
  <div class="titulo-con-accion">
    <h1 class="titulo-pagina">Resumen</h1>
    <a class="btn" href="/admin/eventos/nuevo">+ Nuevo evento</a>
  </div>

  <?php if ((int)$transf['verificar']): ?>
  <a class="panel alerta-transf" href="/admin/transferencias">
    <strong><?= (int)$transf['verificar'] ?> <?= (int)$transf['verificar'] === 1 ? 'transferencia para verificar' : 'transferencias para verificar' ?></strong>
    <span><?= e(pesos((int)$transf['monto'])) ?> con comprobante<?= (int)$transf['esperando'] ? ' · ' . (int)$transf['esperando'] . ' esperando comprobante' : '' ?> · Verificar →</span>
  </a>
  <?php endif; ?>
  <div class="kpis">
    <div class="kpi"><span>Recaudado · 30 días</span><strong>$ <?= number_format($k['ventas30'], 0, ',', '.') ?></strong></div>
    <div class="kpi"><span>Entradas · 30 días</span><strong><?= number_format($k['entradas30'], 0, ',', '.') ?></strong></div>
    <div class="kpi"><span>Funciones a la venta</span><strong><?= $k['activas'] ?></strong></div>
    <div class="kpi"><span>Clientes registrados</span><strong><?= number_format($k['clientes'], 0, ',', '.') ?></strong></div>
  </div>

  <div class="panel">
    <h2 class="panel-titulo">Ventas por día <span class="tenue">· últimos 30 días</span></h2>
    <div class="barras" role="img" aria-label="Recaudación diaria de los últimos 30 días">
      <?php foreach ($serie as $d): ?>
        <div class="barra" style="--h:<?= round($d['monto'] / $max * 100, 1) ?>%" title="<?= e(date('j/n', strtotime($d['dia']))) ?> · <?= $d['n'] ?> entradas · <?= e(pesos($d['monto'])) ?>"><i></i></div>
      <?php endforeach; ?>
    </div>
    <div class="barras-eje"><span><?= e(date('j/n', strtotime($serie[0]['dia']))) ?></span><span>hoy</span></div>
  </div>

  <?php $hay_tope = $correo['libre'] <= 0; ?>
  <a class="panel panel-correo<?= $hay_tope || $correo['pendientes'] > 20 ? ' con-alerta' : '' ?>" href="/admin/correos">
    <span class="panel-titulo">Correo</span>
    <span class="tenue chico"><?= $correo['detalle']['hora']['usados'] ?>/<?= $correo['detalle']['hora']['tope'] ?> esta hora · <?= $correo['detalle']['dia']['usados'] ?>/<?= $correo['detalle']['dia']['tope'] ?> hoy · <?= $correo['detalle']['mes']['usados'] ?>/<?= $correo['detalle']['mes']['tope'] ?> en 30 días<?= $correo['pendientes'] ? ' · ' . $correo['pendientes'] . ' en cola' : '' ?><?= $hay_tope ? ' · frenado por tope' : '' ?></span>
  </a>

  <div class="dos-columnas">
    <div class="panel">
      <h2 class="panel-titulo">Próximas funciones</h2>
      <?php if (!$proximas): ?><p class="tenue">No hay funciones próximas.</p><?php endif; ?>
      <ul class="lista-ocupacion">
        <?php foreach ($proximas as $ev): $pct = $ev['capacidad'] ? min(100, round($ev['vendidas'] / $ev['capacidad'] * 100)) : 0; ?>
        <li>
          <a href="/admin/funciones/<?= (int)$ev['id'] ?>/asistentes">
            <span class="lo-titulo"><strong><?= e($ev['titulo']) ?></strong><span class="tenue"><?= e(fecha_corta($ev['inicio'])) ?> h<?= $ev['lugar'] ? ' · ' . e($ev['lugar']) : '' ?><?= $ev['evento_estado'] === 'borrador' ? ' · borrador' : '' ?></span></span>
            <span class="lo-num mono"><?= (int)$ev['vendidas'] ?>/<?= (int)$ev['capacidad'] ?></span>
            <span class="ocupacion"><i style="width:<?= $pct ?>%"></i></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="panel">
      <h2 class="panel-titulo">Últimas compras</h2>
      <?php if (!$ultimas): ?><p class="tenue">Todavía no hay compras.</p><?php endif; ?>
      <ul class="lista-simple">
        <?php foreach ($ultimas as $o): ?>
        <li>
          <span><strong><?= e($o['nombre']) ?></strong><span class="tenue"><?= (int)$o['n'] ?> × <?= e($o['titulo']) ?></span></span>
          <span class="derecha"><span class="mono"><?= e(pesos((int)$o['total'])) ?></span><span class="tenue chico"><?= e(fecha_corta($o['creado'])) ?></span></span>
        </li>
        <?php endforeach; ?>
      </ul>
      <p><a class="enlace-tenue" href="/admin/ventas">Ver todas las ventas →</a></p>
    </div>
  </div>
</section>
