<section class="contenedor acceso">
  <div class="acceso-caja">
    <h1 class="titulo-pagina">Ingresar</h1>
    <form method="post" action="/ingresar" class="form">
      <?= campo_csrf() ?>
      <input type="hidden" name="volver" value="<?= e($volver) ?>">
      <label class="campo"><span>Correo electrónico</span>
        <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus></label>
      <label class="campo"><span>Contraseña</span>
        <input type="password" name="clave" required autocomplete="current-password"></label>
      <?= campo_captcha() ?>
      <button class="btn btn-bloque btn-grande" type="submit">Ingresar</button>
    </form>
    <p class="acceso-pie">¿No tenés cuenta? <a href="/registro<?= $volver ? '?volver=' . e(rawurlencode($volver)) : '' ?>">Creala en un minuto</a></p>
    <p class="acceso-pie chico"><a href="/recuperar">¿Olvidaste la contraseña?</a></p>
  </div>
</section>
<script src="<?= estatico('captcha.js') ?>" defer></script>
