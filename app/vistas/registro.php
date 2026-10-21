<section class="contenedor acceso">
  <div class="acceso-caja">
    <h1 class="titulo-pagina">Crear cuenta</h1>
    <p class="tenue">Con tu cuenta comprás y tenés tus entradas siempre a mano.</p>
    <form method="post" action="/registro" class="form">
      <?= campo_csrf() ?>
      <input type="hidden" name="volver" value="<?= e($volver) ?>">
      <label class="campo"><span>Nombre y apellido</span>
        <input type="text" name="nombre" value="<?= e($d['nombre'] ?? '') ?>" required autocomplete="name" maxlength="80"></label>
      <label class="campo"><span>Correo electrónico</span>
        <input type="email" name="email" value="<?= e($d['email'] ?? '') ?>" required autocomplete="email"></label>
      <div class="campos-2">
        <label class="campo"><span>Teléfono <i>opcional</i></span>
          <input type="tel" name="telefono" value="<?= e($d['telefono'] ?? '') ?>" autocomplete="tel"></label>
        <label class="campo"><span>DNI <i>opcional</i></span>
          <input type="text" name="dni" value="<?= e($d['dni'] ?? '') ?>" inputmode="numeric" maxlength="10"></label>
      </div>
      <label class="campo"><span>Contraseña</span>
        <input type="password" name="clave" required minlength="8" autocomplete="new-password">
        <small>Al menos 8 caracteres.</small></label>
      <label class="check"><input type="checkbox" name="terminos" value="1" required>
        <span>Acepto las <a href="/condiciones" target="_blank">Condiciones de Servicio</a> y la <a href="/privacidad" target="_blank">Política de Privacidad</a>.</span></label>
      <?= campo_captcha() ?>
      <button class="btn btn-bloque btn-grande" type="submit">Crear cuenta</button>
    </form>
    <p class="acceso-pie">¿Ya tenés cuenta? <a href="/ingresar<?= $volver ? '?volver=' . e(rawurlencode($volver)) : '' ?>">Ingresá</a></p>
  </div>
</section>
<script src="<?= estatico('captcha.js') ?>" defer></script>
