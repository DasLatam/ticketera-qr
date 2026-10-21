<section class="contenedor acceso">
  <div class="acceso-caja">
    <h1 class="titulo-pagina">Nueva contraseña</h1>
    <?php if (!$r): ?>
      <div class="aviso aviso-error">El enlace venció o ya se usó.</div>
      <p class="acceso-pie"><a href="/recuperar">Pedir uno nuevo</a></p>
    <?php else: ?>
      <p class="tenue">Para <?= e($r['email']) ?></p>
      <form method="post" action="/recuperar/<?= e($token) ?>" class="form">
        <?= campo_csrf() ?>
        <label class="campo"><span>Contraseña nueva</span><input type="password" name="clave" required minlength="8" autocomplete="new-password" autofocus><small>Al menos 8 caracteres.</small></label>
        <button class="btn btn-bloque btn-grande" type="submit">Guardar y entrar</button>
      </form>
    <?php endif; ?>
  </div>
</section>
