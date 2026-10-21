<section class="contenedor">
  <div class="titulo-con-accion">
    <h1 class="titulo-pagina">Eventos</h1>
    <a class="btn" href="/admin/eventos/nuevo">+ Nuevo evento</a>
  </div>

  <form class="barra-filtros" method="get" data-filtros>
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar por título o lugar" aria-label="Buscar">
    <select name="cuando" aria-label="Período">
      <?php foreach (['proximos' => 'Próximos y en curso', 'pasados' => 'Pasados', 'todos' => 'Todos'] as $k => $t): ?>
        <option value="<?= $k ?>"<?= $f['cuando'] === $k ? ' selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    <select name="estado" aria-label="Estado">
      <option value="">Todos los estados</option>
      <?php foreach (['publicado' => 'Publicados', 'borrador' => 'Borradores', 'cancelado' => 'Cancelados'] as $k => $t): ?>
        <option value="<?= $k ?>"<?= $f['estado'] === $k ? ' selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if (!$eventos): ?>
    <div class="vacio"><p>No hay eventos con esos filtros.</p></div>
  <?php else: ?>
  <div class="tabla-envoltura">
  <table class="tabla">
    <thead><tr><th>Evento</th><th>Próxima fecha</th><th>Estado</th><th class="num">Funciones</th><th class="num">Vendidas</th><th>Valoración</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($eventos as $ev): ?>
      <tr>
        <td>
          <a class="celda-evento" href="/admin/eventos/<?= (int)$ev['id'] ?>">
            <?php if ($ev['portada']): ?><img src="<?= e(imagen_url($ev['portada'], 'm')) ?>" alt="" width="60" height="40" loading="lazy"><?php else: ?><span class="img-vacia"></span><?php endif; ?>
            <span><strong><?= e($ev['titulo']) ?></strong><span class="tenue chico"><?= e($ev['categoria'] ?? 'Sin categoría') ?><?= $ev['visibilidad'] === 'enlace' ? ' · sólo con enlace' : '' ?></span></span>
          </a>
        </td>
        <td class="nowrap" data-l="Próxima"><?= $ev['proxima'] ? e(fecha_corta($ev['proxima'])) : '<span class="tenue">' . ($ev['ultima'] ? 'terminó ' . e(date('j/n', strtotime($ev['ultima']))) : 'sin fechas') . '</span>' ?></td>
        <td data-l="Estado"><span class="estado estado-<?= e($ev['estado']) ?>"><?= e($ev['estado']) ?></span></td>
        <td class="num" data-l="Funciones"><?= (int)$ev['n_funciones'] ?></td>
        <td class="num" data-l="Vendidas"><?= (int)$ev['vendidas'] ?></td>
        <td data-l="Valoración"><?= $ev['puntaje']['n'] ? estrellas($ev['puntaje']['prom'], 'chicas') . ' <span class="tenue chico">' . $ev['puntaje']['n'] . '</span>' : '<span class="tenue">—</span>' ?></td>
        <td class="acciones-fila">
          <a href="/admin/eventos/<?= (int)$ev['id'] ?>#funciones">Funciones</a>
          <a href="/evento/<?= e($ev['slug']) ?>" target="_blank">Ver ↗</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
