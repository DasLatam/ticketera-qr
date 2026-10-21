<?php
// Configurar cobro: la cuenta donde transfieren (lo que ve el comprador) y la
// conexión con Mercado Pago, con la guía paso a paso. Aparte de la bandeja de
// Transferencias, que es para trabajar los pagos del día.
$conectada = $mp['access_token'] !== '' && $mp['user_id'] !== '';
$mp['prueba'] = !empty($mp['prueba']);
$token_guardado = $mp['access_token'] !== '';
[, $error_conexion] = array_pad(explode('|', ajuste('mp_error_conexion'), 2), 2, '');
$con_clave = $mp['webhook_secret'] !== '';
[$aviso_en, $aviso] = array_pad(explode('|', ajuste('mp_ultimo_aviso'), 2), 2, '');
$aviso_ok = str_starts_with($aviso, 'ok') || str_starts_with($aviso, 'firma ok');
$url_aviso = URL_PUBLICA . '/api/mp/aviso';
$enmascarar = fn(string $v) => $v !== '' ? '••••••••' . substr($v, -4) : '';
$paso = function (int $n, bool $hecho, string $titulo) { ?>
  <h3 class="guia-titulo<?= $hecho ? ' hecho' : '' ?>"><b><?= $hecho ? '✓' : $n ?></b> <?= $titulo ?></h3>
<?php };
?>
<section class="contenedor angosto-admin">
  <a class="volver" href="/admin/transferencias">← Transferencias</a>
  <h1 class="titulo-pagina">Configurar cobro</h1>
  <nav class="indice-cobro" aria-label="En esta página">
    <a href="#cuenta">Cuenta donde te transfieren</a>
    <a href="#mercadopago">Conectar Mercado Pago <?= $conectada ? '<span class="estado estado-publicado">conectada</span>' : '<span class="estado">sin conectar</span>' ?></a>
  </nav>

  <form method="post" action="/admin/transferencias/ajustes" class="panel" id="cuenta">
    <?= campo_csrf() ?>
    <h2 class="panel-titulo">Cuenta donde te transfieren</h2>
    <p class="tenue chico">Es lo que ve el comprador para pagar. Con el alias o el CBU alcanza.</p>
    <label class="check"><input type="checkbox" name="transf_activa" value="1"<?= ajuste('transf_activa') === '1' ? ' checked' : '' ?>><span>Recibir pagos por transferencia</span></label>
    <div class="campos-2">
      <label class="campo"><span>Alias</span><input type="text" name="transf_alias" value="<?= e(ajuste('transf_alias')) ?>" placeholder="mi.alias.mp" autocapitalize="off"></label>
      <label class="campo"><span>CBU / CVU <i>22 dígitos</i></span><input type="text" name="transf_cbu" value="<?= e(ajuste('transf_cbu')) ?>" inputmode="numeric" maxlength="26"></label>
      <label class="campo"><span>Nombre del titular</span><input type="text" name="transf_titular" value="<?= e(ajuste('transf_titular')) ?>"></label>
      <label class="campo"><span>CUIT / CUIL del titular</span><input type="text" name="transf_cuit" value="<?= e(ajuste('transf_cuit')) ?>" inputmode="numeric"></label>
    </div>
    <details class="deposito-admin">
      <summary>Para depósitos en cajero o ventanilla <span class="tenue">· opcional</span></summary>
      <div class="campos-2">
        <label class="campo"><span>Banco</span><input type="text" name="transf_banco" value="<?= e(ajuste('transf_banco')) ?>" placeholder="Banco Nación, Galicia…"></label>
        <label class="campo"><span>Tipo de cuenta</span>
          <select name="transf_tipo_cuenta">
            <?php foreach (['' => '—', 'Caja de ahorro en pesos' => 'Caja de ahorro en pesos', 'Cuenta corriente en pesos' => 'Cuenta corriente en pesos', 'Cuenta virtual (billetera)' => 'Cuenta virtual (billetera)'] as $v => $t): ?>
              <option value="<?= e($v) ?>"<?= ajuste('transf_tipo_cuenta') === $v ? ' selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select></label>
        <label class="campo"><span>Sucursal</span><input type="text" name="transf_sucursal" value="<?= e(ajuste('transf_sucursal')) ?>" placeholder="Número y nombre"></label>
        <label class="campo"><span>Número de cuenta</span><input type="text" name="transf_cuenta" value="<?= e(ajuste('transf_cuenta')) ?>"></label>
      </div>
    </details>
    <h3 class="sub-panel">Plazos</h3>
    <div class="campos-2">
      <label class="campo"><span>Horas para pagar y mandar el comprobante</span><input type="number" name="transf_horas" value="<?= (int)ajuste('transf_horas') ?>" min="1" max="168"><small>Si no llega el pago ni el comprobante, la reserva se cancela y los lugares vuelven a la venta.</small></label>
      <label class="campo"><span>Cerrar la venta antes de la función (horas)</span><input type="number" name="transf_cierre_horas" value="<?= (int)ajuste('transf_cierre_horas') ?>" min="1" max="168"><small>Tiempo para verificar el pago antes de que empiece.</small></label>
    </div>
    <input type="hidden" name="cargo_pct" value="<?= e(str_replace('.', ',', ajuste('cargo_pct'))) ?>">
    <button class="btn" type="submit">Guardar</button>
  </form>

  <form method="post" action="/admin/mercadopago" class="panel guia" id="mercadopago">
    <?= campo_csrf() ?>
    <h2 class="panel-titulo">Conectar Mercado Pago <span class="tenue">· opcional</span></h2>
    <p class="tenue chico">No es cobrar con Mercado Pago: es dejar que el sitio <strong>lea</strong> los pagos que entran a la cuenta donde te transfieren y los cruce con los comprobantes. Si coinciden, la compra se confirma sola. Lleva unos 10 minutos.</p>

    <div class="estado-mp<?= $conectada ? ' conectada' : '' ?>">
      <div><span>Cuenta</span><strong><?= $conectada ? e($mp['nickname'] ?: 'cuenta ' . $mp['user_id']) : ($token_guardado ? 'el token no funciona' : 'sin conectar') ?></strong><?= $conectada && $mp['prueba'] ? '<small class="mal">Es una cuenta de PRUEBA: no ve tus transferencias reales. Pegá el Access Token de «Credenciales de producción».</small>' : '' ?><?= !$conectada && $error_conexion ? '<small class="mal">' . e($error_conexion) . '</small>' : '' ?></div>
      <div><span>Último aviso de Mercado Pago</span><strong><?= $aviso_en ? e(fecha_corta($aviso_en)) . ' h' : 'ninguno todavía' ?></strong><?= $aviso ? '<small class="' . ($aviso_ok ? 'ok' : 'mal') . '">' . e($aviso) . '</small>' : '' ?></div>
      <div><span>Última consulta de pagos</span><strong><?= ajuste('mp_ultima_sync') ? e(fecha_corta(ajuste('mp_ultima_sync'))) . ' h' : 'ninguna todavía' ?></strong><?= ajuste('mp_error') ? '<small class="mal">' . e(ajuste('mp_error')) . '</small>' : '' ?></div>
    </div>

    <ol class="guia-pasos">
      <li>
        <?php $paso(1, $token_guardado, 'Creá una aplicación en Mercado Pago'); ?>
        <p>Entrá a <a href="https://www.mercadopago.com.ar/developers/panel/app" target="_blank" rel="noopener">Mercado Pago Developers › Tus integraciones</a> con <strong>la misma cuenta donde te transfieren</strong> (la del alias de arriba) y tocá <em>Crear aplicación</em>.</p>
        <ul>
          <li>En «Elegí una solución para integrar», elegí <strong>Checkout Pro</strong>. Para leer pagos sirve cualquiera, pero es la que se usaría si más adelante se cobra con tarjeta, así que queda la misma aplicación.</li>
          <li>Nombre: <span class="mono"><?= e(APP_NOMBRE) ?></span> (o el que quieras).</li>
          <li>Si pregunta por plataforma de e-commerce, contestá <em>No</em>.</li>
        </ul>
        <p class="tenue chico">Los nombres de los botones pueden cambiar un poco: Mercado Pago rediseña ese panel cada tanto.</p>
      </li>
      <li>
        <?php $paso(2, $conectada && !$mp['prueba'], 'Copiá el Access Token de producción'); ?>
        <p>Dentro de la aplicación: <em>Producción › Credenciales de producción</em>. Si te pide activarlas, poné la industria (entretenimiento o eventos) y el sitio <span class="mono"><?= e(URL_PUBLICA) ?></span>. Copiá el <strong>Access Token</strong> con el botón de copiar que tiene al lado: empieza con <span class="mono">APP_USR-</span> y es largo (unos 70 caracteres). No sirven la <em>Public Key</em>, el <em>Client ID</em> ni el nombre de la aplicación. <strong>Ojo:</strong> las <em>credenciales de prueba</em> también empiezan con <span class="mono">APP_USR-</span> y conectan con un usuario <span class="mono">TESTUSER…</span>, que no recibe transferencias reales; si arriba aparece ese nombre, copiaste las de prueba.</p>
        <label class="campo"><span>Access Token</span>
          <input type="password" name="access_token" value="<?= e($enmascarar($mp['access_token'])) ?>" placeholder="APP_USR-…" autocomplete="off" spellcheck="false"></label>
        <p class="aviso-llave">Es una llave de tu cuenta: no la mandes por chat ni por correo. Pegala sólo acá; se guarda fuera de la parte pública del servidor.</p>
      </li>
      <li>
        <?php $paso(3, $con_clave, 'Configurá los avisos (webhooks)'); ?>
        <p>En la aplicación: <em>Webhooks › Configurar notificaciones</em>, pestaña <em>Modo productivo</em>.</p>
        <div class="campo"><span>URL de los avisos</span>
          <span class="copiable"><input type="text" value="<?= e($url_aviso) ?>" readonly data-copiable-input><button type="button" class="copiar" data-copiar-input>Copiar</button></span></div>
        <ul>
          <li>Eventos: marcá sólo <strong>Pagos</strong>. Guardá.</li>
          <li>Abajo aparece la <strong>clave secreta</strong>: tocá el ojito para verla, copiala y pegala acá.</li>
        </ul>
        <label class="campo"><span>Clave secreta de los avisos</span>
          <input type="password" name="webhook_secret" value="<?= e($enmascarar($mp['webhook_secret'])) ?>" autocomplete="off" spellcheck="false"></label>
      </li>
      <li>
        <?php $paso(4, $conectada && !$mp['prueba'], 'Conectá y probá'); ?>
        <label class="check"><input type="checkbox" name="mp_auto_confirmar" value="1"<?= ajuste('mp_auto_confirmar') === '1' ? ' checked' : '' ?>>
          <span>Confirmar solas las compras cuando el pago y el comprobante coinciden. Si lo desmarcás, quedan como sugeridas y las confirmás vos.</span></label>
        <div class="acciones">
          <button class="btn" type="submit"><?= $conectada ? 'Guardar y probar' : 'Conectar y probar' ?></button>
          <?php if ($token_guardado): ?><button class="enlace peligro" type="submit" name="desconectar" value="1" data-confirmar="¿Desconectar la cuenta de Mercado Pago? Las transferencias se van a verificar a mano.">Desconectar</button><?php endif; ?>
        </div>
        <p class="tenue chico">Si sale bien, arriba aparece el nombre de tu cuenta de Mercado Pago.</p>
      </li>
      <li>
        <?php $paso(5, $aviso_ok, 'Probá que lleguen los avisos'); ?>
        <p>Volvé a <em>Webhooks</em> en Mercado Pago y tocá <em>Simular notificación</em> (modo productivo, evento Pagos). Mercado Pago tiene que mostrar la respuesta <strong>200</strong>, y al recargar esta página, en «Último aviso» tiene que decir <em>firma ok</em>.</p>
        <p class="tenue chico">Si dice <strong>401</strong> o «la firma no coincide», la clave secreta no es la de esa aplicación: copiala de nuevo en el paso 3.</p>
      </li>
      <li>
        <?php $paso(6, false, 'Probá con una transferencia de verdad'); ?>
        <p>Con tu alias real cargado arriba, reservá una entrada barata (o armá una función de prueba con precio bajo), transferí <strong>desde una cuenta de banco</strong> y subí la captura en la página de la compra.</p>
        <p>Tiene que pasar una de tres cosas, que se ven en <a href="/admin/transferencias">Transferencias</a>: se confirma sola (pestaña <em>Confirmadas solas</em>), queda <em>Sugerida</em>, o aparece en <em>Plata sin reserva</em>. Cualquiera de las tres quiere decir que Mercado Pago informa las transferencias; las dos últimas, que hay que afinar el cruce. Avisale a Claude qué pasó.</p>
        <?php if (MODO_DEMO): ?><p class="tenue chico">El sitio está en versión de prueba y la página de la compra dice «no transfieras dinero»: para esta prueba, ignoralo.</p><?php endif; ?>
      </li>
    </ol>
  </form>
</section>
<script>
document.querySelectorAll('[data-copiar-input]').forEach((b) => b.addEventListener('click', async () => {
  const i = b.parentElement.querySelector('[data-copiable-input]');
  i.select();
  try { await navigator.clipboard.writeText(i.value); } catch (_) { document.execCommand('copy'); }
  b.textContent = 'Copiado'; setTimeout(() => (b.textContent = 'Copiar'), 1500);
}));
</script>
