<section class="contenedor">
  <h1 class="titulo-pagina">Usuarios</h1>
  <p class="tenue">Los clientes se registran solos. Para el personal de la puerta, que se cree una cuenta y acá se le da el rol «puerta».</p>

  <form class="barra-filtros" method="get" data-filtros>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, correo o DNI" aria-label="Buscar">
    <select name="rol" aria-label="Rol">
      <option value="">Todos los roles</option>
      <?php foreach (['cliente' => 'Clientes', 'puerta' => 'Puerta', 'admin' => 'Administradores'] as $k => $t): ?>
        <option value="<?= $k ?>"<?= $rol === $k ? ' selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <div class="tabla-envoltura">
  <table class="tabla tabla-usuarios">
    <thead><tr><th>Usuario</th><th class="num">Entradas</th><th>Rol</th><th>Activo</th><th>Nueva contraseña</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($usuarios as $x): ?>
      <tr<?= $x['activo'] ? '' : ' class="fila-anulada"' ?>>
        <td><strong><?= e($x['nombre']) ?></strong><br><span class="tenue chico"><?= e($x['email']) ?><?= $x['dni'] ? ' · DNI ' . e($x['dni']) : '' ?><?= $x['telefono'] ? ' · ' . e($x['telefono']) : '' ?></span></td>
        <td class="num" data-l="Entradas"><?= (int)$x['entradas'] ?></td>
        <td data-l="Rol">
          <select name="rol" form="u-<?= (int)$x['id'] ?>" aria-label="Rol">
            <?php foreach (['cliente', 'puerta', 'admin'] as $r): ?><option<?= $x['rol'] === $r ? ' selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
          </select>
        </td>
        <td data-l="Activo"><label class="check-solo"><input type="checkbox" name="activo" value="1" form="u-<?= (int)$x['id'] ?>"<?= $x['activo'] ? ' checked' : '' ?>><span class="sr">Activo</span></label></td>
        <td data-l="Contraseña"><input type="password" name="clave" form="u-<?= (int)$x['id'] ?>" placeholder="sin cambios" autocomplete="new-password" aria-label="Nueva contraseña"></td>
        <td class="acciones-fila">
          <form id="u-<?= (int)$x['id'] ?>" method="post" action="/admin/usuarios/<?= (int)$x['id'] ?>"><?= campo_csrf() ?><button class="btn btn-secundario btn-chico">Guardar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
