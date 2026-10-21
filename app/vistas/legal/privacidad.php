<?php $c = e(CORREO_CONTACTO); $actualizado = '7 de octubre de 2026'; ?>
<article class="contenedor angosto legal">
  <h1 class="titulo-pagina">Política de Privacidad</h1>
  <?php require __DIR__ . '/_encabezado.php'; ?>

  <h2>1. Introducción</h2>
  <p>En <?= e(APP_NOMBRE) ?>, accesible desde <?= e(preg_replace('#^https?://#', '', URL_PUBLICA)) ?>, respetamos su privacidad y nos comprometemos a proteger los datos personales que nos proporciona. Esta Política describe qué datos recopilamos, para qué los usamos y cómo los protegemos, de acuerdo con la Ley 25.326 de Protección de los Datos Personales.</p>

  <h2>2. Responsable del tratamiento</h2>
  <p>El responsable del tratamiento de sus datos es <?= e(APP_NOMBRE) ?>, con correo de contacto <a href="mailto:<?= $c ?>"><?= $c ?></a>.</p>

  <h2>3. Datos que recopilamos</h2>
  <ul>
    <li><strong>Datos de la cuenta:</strong> nombre y apellido, correo electrónico y contraseña (guardada cifrada, nunca en texto legible). Opcionalmente, teléfono y DNI.</li>
    <li><strong>Datos de las compras:</strong> eventos y funciones, tipo y cantidad de entradas, importe, fecha y el nombre del titular de cada entrada si usted lo indica.</li>
    <li><strong>Datos del ingreso:</strong> fecha y hora en que cada entrada fue leída en la puerta.</li>
    <li><strong>Comprobantes de transferencia</strong> que usted envíe para pagar: se guardan fuera del espacio público del servidor junto con el texto que se lee de ellos para agilizar la verificación (la lectura se hace en el propio dispositivo de quien lo sube o de quien verifica, sin enviarlos a terceros); sólo los ve el equipo que verifica los pagos y se usan únicamente para confirmar la compra.</li>
    <li><strong>Datos del pago recibido:</strong> cuando la cuenta que recibe las transferencias informa un ingreso, guardamos el importe, la fecha, el número de operación y el nombre y documento de quien transfirió, para reconocer a qué compra corresponde.</li>
    <li><strong>Pedidos de arrepentimiento, devoluciones y reintegros:</strong> el código de la orden, el correo, el alias o CBU/CVU y el titular de la cuenta donde pide recibir el dinero, el comentario que deje, la fecha y la dirección IP desde la que se hizo el pedido, y el resultado del trámite.</li>
    <li><strong>Reseñas</strong> que usted decida publicar.</li>
    <li><strong>Datos técnicos:</strong> la dirección IP de los intentos de ingreso a la cuenta, que se conserva quince minutos para frenar intentos de adivinar contraseñas.</li>
  </ul>

  <h2>4. Finalidad del tratamiento</h2>
  <ul>
    <li>Gestionar su cuenta, sus compras y sus entradas.</li>
    <li>Controlar el acceso a cada función y evitar el uso duplicado de entradas.</li>
    <li>Enviarle por correo sus entradas, avisos sobre las funciones que compró (cambios o cancelaciones) y los enlaces para restablecer su contraseña.</li>
    <li>Tramitar arrepentimientos, devoluciones por cancelación o reprogramación, y reintegros.</li>
    <li>Mostrar reseñas verificadas de asistentes.</li>
    <li>Cumplir con obligaciones legales.</li>
  </ul>
  <p>No enviamos publicidad ni boletines sin su consentimiento expreso.</p>

  <h2>5. Base legal</h2>
  <p>Tratamos sus datos para cumplir el contrato de compra que usted celebra al adquirir entradas, con base en su consentimiento al crear la cuenta y para cumplir obligaciones legales.</p>

  <h2>6. Con quién compartimos sus datos</h2>
  <p>No vendemos ni cedemos sus datos. Los compartimos sólo en estos casos:</p>
  <ul>
    <li>Con el <strong>organizador del evento</strong> y su personal de puerta: el nombre del titular y el estado de cada entrada de su función, para controlar el acceso.</li>
    <li>Con el <strong>procesador de pagos</strong>, si en el futuro se habilita el pago con tarjeta, en la medida necesaria para cobrar la compra.</li>
    <li>Cuando lo exija una autoridad competente o una obligación legal.</li>
  </ul>
  <p>Al usar el Sitio, su navegador descarga los mapas desde OpenStreetMap, que recibe su dirección IP como parte de cualquier conexión a internet. Las tipografías se sirven desde este mismo Sitio.</p>

  <h2>7. Seguridad</h2>
  <p>Aplicamos medidas técnicas y organizativas para proteger sus datos contra pérdida, uso indebido o acceso no autorizado: conexión cifrada (HTTPS), contraseñas cifradas, base de datos fuera del espacio público del servidor y acceso restringido por roles.</p>

  <h2>8. Sus derechos</h2>
  <p>Usted puede acceder a sus datos, rectificarlos, actualizarlos y pedir su supresión, en forma gratuita y a intervalos no inferiores a seis meses salvo interés legítimo (art. 14 de la Ley 25.326). También puede retirar en cualquier momento el consentimiento para recibir comunicaciones que no sean de sus compras. Puede corregir nombre, teléfono y DNI desde «Mi cuenta», y para el resto escribirnos a <a href="mailto:<?= $c ?>"><?= $c ?></a>. Responderemos dentro de los plazos de la Ley 25.326 (diez días corridos para el acceso y cinco días hábiles para la rectificación o supresión).</p>
  <p>La Agencia de Acceso a la Información Pública, en su carácter de órgano de control de la Ley 25.326, tiene la atribución de atender las denuncias y reclamos que interpongan quienes resulten afectados en sus derechos por incumplimiento de las normas vigentes en materia de protección de datos personales.</p>

  <h2>9. Conservación</h2>
  <p>Conservamos los datos de la cuenta mientras esté activa. Los registros de compras, pagos, ingresos, arrepentimientos y reintegros se conservan el tiempo que exijan las obligaciones legales y contables, aunque se dé de baja la cuenta. Los comprobantes de transferencia se conservan mientras puedan hacer falta para resolver un reclamo sobre esa compra.</p>

  <h2>10. Cookies</h2>
  <p>Usamos únicamente una cookie técnica para mantener su sesión iniciada. Ver la <a href="/cookies">Política de Cookies</a>.</p>

  <h2>11. Enlaces a sitios de terceros</h2>
  <p>El Sitio contiene enlaces a terceros (mapas, redes sociales). No somos responsables de sus prácticas de privacidad; le recomendamos leer sus políticas.</p>

  <h2>12. Modificaciones</h2>
  <p>Podemos modificar esta Política en cualquier momento. Los cambios se publican en esta página con una nueva fecha de actualización.</p>

  <h2>13. Contacto</h2>
  <p>Por preguntas sobre esta Política de Privacidad, escribanos a <a href="mailto:<?= $c ?>"><?= $c ?></a>.</p>
</article>
