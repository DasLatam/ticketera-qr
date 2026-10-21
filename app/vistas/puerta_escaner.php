<section class="escaner" data-escaner data-funcion="<?= (int)$fn['id'] ?>" data-csrf="<?= e(csrf()) ?>">
  <header class="escaner-cabecera">
    <a href="/puerta" class="escaner-volver" aria-label="Cambiar de evento">←</a>
    <div class="escaner-evento">
      <strong><?= e($fn['titulo']) ?></strong>
      <span><?= e(fecha_corta($fn['inicio'])) ?> h<?= $fn['lugar'] ? ' · ' . e($fn['lugar']) : '' ?></span>
    </div>
    <div class="escaner-cuenta" aria-live="polite"><b data-ingresados>–</b><span>/<i data-vendidas>–</i></span></div>
  </header>

  <div class="escaner-visor">
    <video playsinline muted data-video></video>
    <div class="escaner-marco" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
    <div class="escaner-estado" data-estado>Iniciando la cámara…</div>
    <button type="button" class="btn escaner-activar" data-activar hidden>Activar cámara</button>
  </div>

  <form class="escaner-manual" data-manual autocomplete="off">
    <input type="text" name="codigo" placeholder="Código a mano" aria-label="Código de la entrada" autocapitalize="characters" spellcheck="false" maxlength="20">
    <button class="btn" type="submit">Validar</button>
  </form>

  <ol class="escaner-historial" data-historial aria-label="Últimas lecturas"></ol>

  <div class="resultado" data-resultado hidden role="alert">
    <div class="resultado-icono" data-r-icono></div>
    <div class="resultado-titulo" data-r-titulo></div>
    <div class="resultado-titular" data-r-titular></div>
    <div class="resultado-detalle" data-r-detalle></div>
    <div class="resultado-toque">Tocá para seguir</div>
  </div>
</section>
<script src="/estaticos/vendor/jsQR.js" defer></script>
<script src="<?= estatico('puerta.js') ?>" defer></script>
