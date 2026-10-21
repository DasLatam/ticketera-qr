<?php $n = count($hermanas); $sin_pagar = $x['orden_estado'] !== 'pagada'; ?>
<section class="contenedor angosto entrada-pantalla" data-entrada="<?= e($x['codigo']) ?>" data-estado="<?= e($x['estado']) ?>" data-pago="<?= e($x['orden_estado']) ?>">
  <div class="entrada-barra">
    <a class="volver" href="/mis-entradas">← Mis entradas</a>
    <?php if ($n > 1): ?>
    <nav class="entrada-nav" aria-label="Otras entradas de esta compra">
      <?php if ($pos > 0): ?><a href="/entrada/<?= e($hermanas[$pos - 1]) ?>" aria-label="Entrada anterior">‹</a><?php else: ?><span>‹</span><?php endif; ?>
      <b><?= $pos + 1 ?> de <?= $n ?></b>
      <?php if ($pos < $n - 1): ?><a href="/entrada/<?= e($hermanas[$pos + 1]) ?>" aria-label="Entrada siguiente">›</a><?php else: ?><span>›</span><?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
  <p class="aviso-sin-red">Sin conexión: esta entrada está guardada en tu celular y sirve igual.</p>

  <div class="ticket ticket-<?= e($x['estado']) ?>" data-ticket>
    <div class="ticket-cabecera">
      <span class="marca marca-ticket"><?= marca_html() ?></span>
      <span class="ticket-tipo"><?= e($x['tipo']) ?></span>
    </div>
    <h1 class="ticket-titulo"><?= e($x['titulo']) ?></h1>
    <p class="ticket-fecha"><?= e(ucfirst(fecha_larga($x['inicio']))) ?> · <?= e(hora($x['inicio'])) ?> h</p>
    <?php if ($x['lugar']): ?><p class="ticket-lugar"><?= e($x['lugar']) ?><?= $x['direccion'] ? ' · ' . e($x['direccion']) : '' ?></p><?php endif; ?>

    <div class="ticket-corte" aria-hidden="true"></div>

    <?php if ($sin_pagar): ?>
    <div class="ticket-espera">
      <span class="ticket-espera-icono" aria-hidden="true"><?= $x['orden_estado'] === 'cancelada' ? '×' : '⏳' ?></span>
      <strong><?= $x['orden_estado'] === 'cancelada' ? 'Reserva cancelada' : 'Pago en verificación' ?></strong>
      <p><?= $x['orden_estado'] === 'cancelada' ? 'Esta entrada no sirve para entrar.' : 'El QR aparece acá cuando confirmemos tu transferencia. Te avisamos también por correo.' ?></p>
      <a class="btn btn-secundario btn-chico" href="/orden/<?= e($x['orden']) ?>">Ver la orden<?= $x['orden_estado'] === 'pendiente' ? ' y el comprobante' : '' ?></a>
    </div>
    <?php else: ?>
    <div class="ticket-qr-caja">
      <div class="ticket-qr" data-qr="TQR:<?= e($x['codigo']) ?>" data-qr-grande role="img" aria-label="Código QR de la entrada <?= e($x['codigo']) ?>"></div>
      <div class="ticket-sello" data-sello<?= $x['estado'] === 'valida' ? ' hidden' : '' ?>>
        <span data-sello-texto><?= $x['estado'] === 'usada' ? 'Usada' : ($x['estado'] === 'anulada' ? 'Anulada' : '') ?></span>
        <small data-sello-hora><?= $x['usada_en'] ? e(fecha_corta($x['usada_en'])) . ' h' : '' ?></small>
      </div>
    </div>
    <p class="ticket-codigo mono"><?= e(implode(' ', str_split($x['codigo'], 5))) ?></p>
    <p class="ticket-ayuda" data-ayuda<?= $x['estado'] !== 'valida' ? ' hidden' : '' ?>>Subí el brillo y mostrá el QR en la puerta.</p>
    <?php endif; ?>

    <dl class="ticket-datos">
      <div><dt>Titular</dt><dd><?= e($x['titular']) ?></dd></div>
      <div><dt>Orden</dt><dd class="mono"><?= e($x['orden']) ?></dd></div>
    </dl>
  </div>
  <p class="caja-nota">El QR sirve una sola vez. No lo compartas: quien lo muestre primero, entra.</p>
</section>
<script src="/estaticos/vendor/qrcode.js" defer></script>
