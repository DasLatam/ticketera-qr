<section class="contenedor angosto">
  <h1 class="titulo-pagina">Mi cuenta</h1>
  <form method="post" action="/cuenta" class="form tarjeta-form">
    <?= campo_csrf() ?>
    <label class="campo"><span>Correo electrónico</span>
      <input type="email" value="<?= e($u['email']) ?>" disabled></label>
    <label class="campo"><span>Nombre y apellido</span>
      <input type="text" name="nombre" value="<?= e($u['nombre']) ?>" required maxlength="80"></label>
    <div class="campos-2">
      <label class="campo"><span>Teléfono</span><input type="tel" name="telefono" value="<?= e($u['telefono']) ?>"></label>
      <label class="campo"><span>DNI</span><input type="text" name="dni" value="<?= e($u['dni']) ?>" inputmode="numeric"></label>
    </div>
    <fieldset>
      <legend>Cambiar contraseña</legend>
      <div class="campos-2">
        <label class="campo"><span>Actual</span><input type="password" name="clave_actual" autocomplete="current-password"></label>
        <label class="campo"><span>Nueva</span><input type="password" name="clave_nueva" minlength="8" autocomplete="new-password"></label>
      </div>
    </fieldset>
    <button class="btn" type="submit">Guardar</button>
  </form>
  <p class="tenue chico">Cuenta creada el <?= e(fecha_larga($u['creado'])) ?><?= $u['rol'] !== 'cliente' ? ' · rol: ' . e($u['rol']) : '' ?>.</p>
</section>
