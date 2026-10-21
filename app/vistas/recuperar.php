<section class="contenedor acceso">
  <div class="acceso-caja">
    <h1 class="titulo-pagina">Recuperar contraseña</h1>
    <?php if ($enviado): ?>
      <div class="aviso aviso-ok">Si hay una cuenta con ese correo, te llega un enlace para elegir una contraseña nueva. Vence en una hora. Revisá también la carpeta de spam.</div>
      <p class="acceso-pie"><a href="/ingresar">Volver a ingresar</a></p>
    <?php else: ?>
      <p class="tenue">Te mandamos un enlace para elegir una contraseña nueva.</p>
      <form method="post" action="/recuperar" class="form">
        <?= campo_csrf() ?>
        <label class="campo"><span>Correo electrónico</span><input type="email" name="email" required autocomplete="email" autofocus></label>
        <?= campo_captcha() ?>
        <button class="btn btn-bloque btn-grande" type="submit">Enviar enlace</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<script src="<?= estatico('captcha.js') ?>" defer></script>
