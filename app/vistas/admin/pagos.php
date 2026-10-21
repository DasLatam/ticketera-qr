<?php
// Estrategia de cobro con Mercado Pago. Página de trabajo para decidir con el
// cliente antes de integrar; los números de comisión son los publicados por
// Mercado Pago al 2026-10-03 y hay que confirmarlos en la cuenta real.
$plazos = [
    ['id' => 'inst', 'nombre' => 'Al instante', 'pct' => 6.29],
    ['id' => 'd10', 'nombre' => 'A 10 días', 'pct' => 4.39],
    ['id' => 'd18', 'nombre' => 'A 18 días', 'pct' => 3.39],
    ['id' => 'd35', 'nombre' => 'A 35 días', 'pct' => 1.49],
];
?>
<section class="contenedor estrategia">
  <div class="titulo-con-accion">
    <div>
      <p class="sobretitulo">Propuesta · para aprobar con el cliente</p>
      <h1 class="titulo-pagina">Cobrar con Mercado Pago</h1>
    </div>
    <span class="estado estado-borrador">Estrategia · todavía sin integrar</span>
  </div>
  <div class="aviso aviso-info"><strong>Decisión del 3/10/2026: por ahora el sitio cobra sólo por transferencia, que es gratuita.</strong> El pago con tarjeta está apagado. Esta página queda como plan para cuando convenga sumar Mercado Pago.</div>
  <p class="bajada-estrategia">La transferencia con comprobante funciona en <a href="/admin/transferencias">Transferencias</a>, con lectura automática del comprobante. Abajo, cómo se integraría Mercado Pago, qué costaría y qué faltaría decidir.</p>

  <div class="dos-columnas medios-resumen">
    <div class="panel recomendacion">
      <h2 class="panel-titulo">1 · Tarjeta, débito o Mercado Pago</h2>
      <p><strong>Checkout Pro de Mercado Pago</strong>, con aprobación inmediata y la entrada reservada 15 minutos mientras la persona paga. <strong>El cargo por servicio lo paga el comprador</strong> (decidido el 3/10): se suma al total y el organizador recibe el precio publicado completo.</p>
      <p class="tenue chico">Estado: apagado. Se integraría en las etapas de abajo.</p>
    </div>
    <div class="panel recomendacion recomendacion-ok">
      <h2 class="panel-titulo">2 · Transferencia con comprobante <span class="sin-cargo">el medio actual</span></h2>
      <p><strong>Sin cargo y sin comisión.</strong> Se reservan las entradas, la persona transfiere a la cuenta y sube el comprobante, y alguien lo verifica en la cuenta y aprueba. Se le avisa que <strong>la confirmación demora</strong>. Mientras tanto la entrada no tiene QR y la puerta la rechaza.</p>
      <p class="tenue chico"><a href="/admin/transferencias">Administración › Transferencias</a>: bandeja con la foto del comprobante, lectura automática de monto, fecha y número de operación, y el botón «Confirmar que recibí el dinero».</p>
    </div>
  </div>

  <h2 class="seccion-admin">Cómo lo vive quien compra</h2>
  <ol class="pasos-flujo">
    <li><b>1</b><strong>Elige sus entradas</strong><span>Como hoy: función, tipos y cantidades.</span></li>
    <li><b>2</b><strong>Confirma</strong><span>Las entradas quedan reservadas a su nombre por 15 minutos: nadie más las puede comprar.</span></li>
    <li><b>3</b><strong>Paga en Mercado Pago</strong><span>Se abre la pantalla de pago de Mercado Pago. Tarjeta, débito, saldo o cuotas.</span></li>
    <li><b>4</b><strong>Vuelve al sitio</strong><span>«Pago aprobado» y sus entradas con QR, en pantalla y por correo.</span></li>
    <li><b>5</b><strong>Entra con el QR</strong><span>Igual que ahora: la puerta lo escanea y la entrada se consume.</span></li>
  </ol>

  <h2 class="seccion-admin">Qué pasa por detrás</h2>
  <div class="panel">
    <div class="secuencia" role="img" aria-label="Secuencia: el sitio crea el pago en Mercado Pago, la persona paga, Mercado Pago avisa al sitio, el sitio verifica y emite las entradas">
      <div class="sec-col"><span class="sec-actor">Sitio</span></div>
      <div class="sec-col"><span class="sec-actor">Mercado Pago</span></div>
      <div class="sec-paso sec-der"><i>1</i>Reserva las entradas (15 min) y crea la orden de pago con el detalle y el código de la compra</div>
      <div class="sec-paso sec-izq"><i>2</i>Devuelve el enlace de pago y la persona paga ahí</div>
      <div class="sec-paso sec-izq"><i>3</i>Avisa al sitio que hubo un pago (aviso firmado)</div>
      <div class="sec-paso sec-der"><i>4</i>Comprueba la firma, consulta el pago y verifica monto y compra</div>
      <div class="sec-paso sec-local"><i>5</i>Si está aprobado: emite las entradas, manda el correo. Si no: libera la reserva</div>
    </div>
    <p class="tenue chico">El sitio <strong>nunca</strong> da por pagada una compra por la página de regreso (se puede falsificar): sólo por el aviso de Mercado Pago, y después de consultar el pago directamente.</p>
  </div>

  <h2 class="seccion-admin">Cuánto cuesta</h2>
  <div class="panel">
    <p>Mercado Pago cobra un porcentaje de cada venta aprobada, <strong>más IVA</strong>, y es más barato cuanto más se tarda en tener el dinero disponible. Sin abono mensual ni costo por las ventas rechazadas.</p>
    <div class="tabla-envoltura">
      <table class="tabla">
        <thead><tr><th>Dinero disponible</th><th class="num">Comisión</th><th class="num">Con IVA</th></tr></thead>
        <tbody>
          <?php foreach ($plazos as $p): ?>
            <tr><td><?= e($p['nombre']) ?></td><td class="num" data-l="Comisión"><?= number_format($p['pct'], 2, ',', '.') ?> % + IVA</td><td class="num" data-l="Con IVA"><?= number_format($p['pct'] * 1.21, 2, ',', '.') ?> %</td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="tenue chico">Publicado por Mercado Pago para Checkout en Argentina, consultado el 3/10/2026. Pueden sumarse retenciones de Ingresos Brutos según la provincia y la situación fiscal. <strong>Confirmar en la cuenta que se use</strong> antes de fijar precios.</p>

    <h3 class="sub-panel">Calculadora por entrada</h3>
    <div class="calculadora" data-calculadora>
      <label class="campo"><span>Precio de la entrada ($)</span><input type="number" value="18000" min="0" step="500" data-c-precio inputmode="numeric"></label>
      <label class="campo"><span>Plazo de acreditación</span>
        <select data-c-plazo>
          <?php foreach ($plazos as $i => $p): ?><option value="<?= $p['pct'] ?>"<?= $i === 2 ? ' selected' : '' ?>><?= e($p['nombre']) ?> · <?= number_format($p['pct'], 2, ',', '') ?> % + IVA</option><?php endforeach; ?>
        </select></label>
      <fieldset class="campo">
        <legend class="sr">Quién paga la comisión</legend>
        <span>Quién paga la comisión</span>
        <label class="opcion-radio"><input type="radio" name="quien" value="cli" checked data-c-quien> El comprador (se suma un cargo por servicio) · lo decidido</label>
        <label class="opcion-radio"><input type="radio" name="quien" value="org" data-c-quien> El organizador (precio final igual al publicado)</label>
      </fieldset>
      <div class="calc-resultado" aria-live="polite">
        <div><span>Paga el comprador</span><strong data-c-paga>—</strong></div>
        <div><span>Comisión + IVA</span><strong data-c-comision>—</strong></div>
        <div><span>Recibe el organizador</span><strong data-c-neto>—</strong></div>
      </div>
    </div>
  </div>

  <h2 class="seccion-admin">Decisiones antes de empezar</h2>
  <div class="decisiones">
    <article class="panel decision">
      <h3>1 · ¿De quién es la cuenta de Mercado Pago?</h3>
      <p>El dinero entra a la cuenta cuyas credenciales usemos. Si cada organizador cobra lo suyo, hace falta el modelo «marketplace» (cada uno vincula su cuenta y la plataforma puede cobrar una comisión propia). Si cobra una sola cuenta y después reparte, alcanza la integración simple.</p>
      <p class="sugerencia"><b>Sugerencia:</b> empezar con <strong>una sola cuenta</strong> (la del cliente). El modelo marketplace se suma después sin rehacer la compra.</p>
    </article>
    <article class="panel decision decision-tomada">
      <h3>2 · ¿Quién paga la comisión? <span class="sin-cargo">decidido</span></h3>
      <p><strong>El comprador</strong>, como «cargo por servicio» sólo cuando paga con tarjeta. Quien no quiere pagarlo elige transferencia. El cargo se muestra desde el checkout, antes de confirmar, como pide la defensa del consumidor. El porcentaje se ajusta en Transferencias › Datos de cobro.</p>
    </article>
    <article class="panel decision">
      <h3>3 · ¿En qué plazo se quiere el dinero?</h3>
      <p>Se elige en la cuenta de Mercado Pago, no en el sitio. Para eventos con fecha, cobrar a 10 o 18 días suele cerrar bien: el dinero llega antes o cerca del evento y la comisión baja a la mitad.</p>
    </article>
    <article class="panel decision">
      <h3>4 · ¿Cuotas y medios en efectivo?</h3>
      <p>Las cuotas se ofrecen sin trabajo extra (el costo de financiación lo ve el comprador). <strong>Rapipago y Pago Fácil conviene excluirlos</strong>: el pago queda pendiente hasta días, y una entrada no puede quedar reservada tanto tiempo.</p>
    </article>
    <article class="panel decision">
      <h3>5 · ¿Qué pasa si se cancela una función?</h3>
      <p>Con el pago integrado, el panel puede <strong>reintegrar</strong> la compra desde la misma pantalla de asistentes: devuelve el dinero por Mercado Pago y anula las entradas. Falta definir la política (total, parcial, plazos) para los textos legales.</p>
    </article>
    <article class="panel decision">
      <h3>6 · Facturación</h3>
      <p>Mercado Pago no factura por el vendedor. Si hace falta factura por cada entrada, es otra integración (ARCA). Conviene decidirlo con el contador.</p>
    </article>
  </div>

  <h2 class="seccion-admin">Qué se suma al panel</h2>
  <div class="dos-columnas">
    <div class="panel">
      <h3 class="sub-panel">Pagos › Configuración</h3>
      <ul class="lista-check">
        <li>Modo <strong>prueba / real</strong>, con un interruptor.</li>
        <li>Credenciales de Mercado Pago (se guardan fuera de la parte pública del servidor, nunca en la base).</li>
        <li>Prueba de conexión: «las credenciales funcionan».</li>
        <li>Cuotas máximas y medios excluidos.</li>
        <li>Texto que aparece en el resumen de la tarjeta.</li>
      </ul>
    </div>
    <div class="panel">
      <h3 class="sub-panel">Ventas y asistentes</h3>
      <ul class="lista-check">
        <li>Estado del pago en cada compra: aprobado, rechazado, reintegrado, en disputa.</li>
        <li>Comisión y neto de cada venta, y totales por función.</li>
        <li>Botón <strong>Reintegrar</strong> (total) que también anula las entradas.</li>
        <li>Si Mercado Pago informa un contracargo, la entrada se anula sola si todavía no se usó.</li>
        <li>Exportar a CSV para conciliar con la cuenta.</li>
      </ul>
    </div>
  </div>

  <h2 class="seccion-admin">Cuidados que ya están pensados</h2>
  <div class="panel">
    <ul class="lista-check">
      <li><strong>Nunca se vende de más:</strong> la reserva de 15 minutos usa el mismo candado que hoy impide vender dos veces la última entrada. Si el pago no llega, la reserva se libera sola.</li>
      <li><strong>Un pago, una compra:</strong> si Mercado Pago avisa dos veces del mismo pago (pasa), la compra se confirma una sola vez.</li>
      <li><strong>Avisos falsos:</strong> se valida la firma de cada aviso con la clave secreta de la aplicación y se consulta el pago a Mercado Pago antes de dar nada por pagado. También se controla que el monto coincida.</li>
      <li><strong>Pagos que llegan tarde:</strong> si el pago se aprueba después de que la reserva venció y ya no hay lugar, se reintegra automáticamente y se avisa por correo.</li>
      <li><strong>Datos de tarjeta:</strong> nunca pasan por el servidor del sitio; los maneja Mercado Pago.</li>
    </ul>
  </div>

  <h2 class="seccion-admin">Plan de trabajo</h2>
  <ol class="plan">
    <li><b>Etapa 1 · Cuenta y credenciales</b><span>El cliente crea la aplicación en Mercado Pago Developers y nos pasa credenciales de prueba y de producción, y la clave secreta de los avisos.</span><em>Lo hace el cliente</em></li>
    <li><b>Etapa 2 · Integración en modo prueba</b><span>Reserva de 15 minutos, pago con Checkout Pro, avisos firmados, confirmación y correo. Pruebas con las tarjetas y usuarios de prueba de Mercado Pago, incluidos rechazos y avisos duplicados.</span><em>Desarrollo</em></li>
    <li><b>Etapa 3 · Panel</b><span>Configuración, estado de pago en ventas, reintegros y conciliación.</span><em>Desarrollo</em></li>
    <li><b>Etapa 4 · Salida a producción</b><span>Credenciales reales, una compra real chica y su reintegro para probar el circuito completo, y recién ahí abrir la venta.</span><em>Juntos</em></li>
  </ol>

  <div class="panel lo-que-hace-falta">
    <h2 class="panel-titulo">Lo que hace falta del cliente para arrancar</h2>
    <ol>
      <li>Aprobar esta estrategia (o marcar qué cambiar).</li>
      <li>Decidir los puntos de arriba que siguen abiertos: cuenta, plazo y cuotas.</li>
      <li>Crear la aplicación en <a href="https://www.mercadopago.com.ar/developers/panel/app" target="_blank" rel="noopener">Mercado Pago Developers</a> (producto: Checkout Pro) y pasar las credenciales de prueba.</li>
    </ol>
  </div>

  <p class="tenue chico fuentes">Fuentes: <a href="https://www.mercadopago.com.ar/developers/es/docs/checkout-pro/overview" target="_blank" rel="noopener">Checkout Pro (documentación oficial)</a> ·
    <a href="https://www.mercadopago.com.ar/herramientas-para-vender/check-out" target="_blank" rel="noopener">Comisiones de Checkout publicadas por Mercado Pago</a> ·
    <a href="https://pkg.go.dev/github.com/mercadopago/sdk-go/pkg/webhook" target="_blank" rel="noopener">Validación de la firma de los avisos (SDK oficial)</a>.</p>
</section>
<script>
// Calculadora: la comisión se cobra sobre lo que paga el comprador, más IVA.
// Si la paga el comprador, el cargo se calcula para que el organizador reciba
// exactamente el precio publicado: total = precio / (1 - tasa).
(() => {
  const c = document.querySelector('[data-calculadora]');
  if (!c) return;
  const $ = (s) => c.querySelector(s);
  const pesos = (n) => '$ ' + Math.round(n).toLocaleString('es-AR');
  const calc = () => {
    const precio = Math.max(0, +$('[data-c-precio]').value || 0);
    const tasa = (+$('[data-c-plazo]').value / 100) * 1.21;
    const traslada = c.querySelector('[data-c-quien]:checked').value === 'cli';
    const paga = traslada ? precio / (1 - tasa) : precio;
    const comision = paga * tasa;
    $('[data-c-paga]').textContent = pesos(paga) + (traslada ? ' (incluye ' + pesos(paga - precio) + ' de cargo)' : '');
    $('[data-c-comision]').textContent = pesos(comision);
    $('[data-c-neto]').textContent = pesos(paga - comision);
  };
  c.addEventListener('input', calc);
  c.addEventListener('change', calc);
  calc();
})();
</script>
