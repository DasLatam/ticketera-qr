<?php $c = e(CORREO_CONTACTO); $sitio = preg_replace('#^https?://#', '', URL_PUBLICA); $actualizado = '4 de octubre de 2026'; ?>
<article class="contenedor angosto legal">
  <h1 class="titulo-pagina">Condiciones de Servicio</h1>
  <?php require __DIR__ . '/_encabezado.php'; ?>

  <h2>1. Aceptación de las condiciones</h2>
  <p>Al acceder y utilizar el sitio web <?= $sitio ?> (en adelante, «el Sitio») y la plataforma <?= e(APP_NOMBRE) ?> (en adelante, «la Plataforma»), usted acepta cumplir con estas Condiciones de Servicio y con todas las leyes y regulaciones aplicables. Si no está de acuerdo con alguna de estas condiciones, no debe utilizar el Sitio ni comprar entradas a través de él.</p>

  <h2>2. Qué es la Plataforma</h2>
  <p>La Plataforma permite consultar eventos privados, comprar entradas para sus funciones y presentarlas en el ingreso mediante un código QR. Cada evento es organizado por quien figura como organizador en su ficha, que es responsable de su realización, contenido, horarios, condiciones de acceso y seguridad en el lugar.</p>

  <h2>3. Cuenta de usuario</h2>
  <p>Para comprar es necesario crear una cuenta con datos verdaderos. Usted es responsable de mantener la confidencialidad de su contraseña y de toda la actividad realizada con su cuenta. Podemos suspender o dar de baja cuentas usadas para fines ilegales, fraudulentos o contrarios a estas condiciones.</p>

  <h2>4. Compra de entradas y medios de pago</h2>
  <p>El precio de cada entrada es el que figura al momento de confirmar la compra. Las entradas están sujetas a disponibilidad. Cada compra tiene un tope de entradas por persona, informado en la ficha del evento. El total a pagar, con todos sus cargos, se muestra completo antes de confirmar.</p>
  <p>El pago se hace por <strong>transferencia bancaria o depósito, sin cargo</strong>. Al confirmar, las entradas quedan reservadas y se informan los datos de la cuenta y el monto exacto. Quien compra debe transferir y enviar el comprobante (una foto o el archivo del banco) desde la página de la compra dentro del plazo indicado (24 horas, salvo que la función esté más cerca). El comprobante se cruza con los ingresos de la cuenta; cuando no se puede confirmar automáticamente se verifica de forma manual, por lo que <strong>la confirmación puede demorar</strong>. Hasta que se confirme, la entrada no tiene código QR y no habilita el ingreso. Si el comprobante no llega a tiempo, o si la transferencia no se puede verificar, la reserva se cancela y se informa por correo. La venta online de cada función cierra unas horas antes de que empiece, para dar tiempo a verificar los pagos.</p>
  <p>Más adelante podrán habilitarse otros medios de pago; si tuvieran algún cargo, se informará en el detalle antes de pagar.</p>
  <p>Una vez confirmada la compra, las entradas quedan disponibles en la sección «Mis entradas» y se envían por correo electrónico.</p>

  <h2>5. Validez de la entrada y condiciones de acceso</h2>
  <p>Cada entrada vale para <strong>un solo ingreso</strong>, a la función (fecha, horario y lugar) y con el tipo de entrada para los que fue emitida, y sólo una vez confirmado el pago. No vale para otra función del mismo evento. Una entrada anulada, cancelada o cuya reserva venció no habilita el ingreso.</p>
  <p>Para ingresar hay que mostrar el código QR de la entrada, desde «Mis entradas», desde el correo de la compra o impreso. La entrada se puede abrir sin conexión desde la aplicación si se abrió antes con conexión.</p>
  <p>Las condiciones de acceso de cada evento (edad mínima, documento, vestimenta, horario de cierre de puertas, objetos no permitidos u otras) son las informadas en su ficha y las fija el organizador, que puede negar el ingreso a quien no las cumpla. Esas condiciones deben ser objetivas y no discriminatorias. Quien llega después del cierre de puertas informado, o no cumple las condiciones publicadas, no tiene derecho a reintegro, salvo lo que disponga la ley.</p>

  <h2>6. Responsabilidad sobre el código QR y entradas duplicadas</h2>
  <p>El código QR <strong>es la entrada</strong>. Al ser leído en la puerta queda utilizado y no vuelve a habilitar el acceso, aunque se presente otra vez desde otro teléfono, en una captura o impreso. Si existen copias de un mismo código, <strong>ingresa sólo quien lo presente primero</strong>.</p>
  <p>Quien compra es responsable de guardar sus códigos y de no compartirlos ni publicarlos (por ejemplo, en redes sociales). <?= e(APP_NOMBRE) ?> no responde por el uso que terceros hagan de un código que el comprador compartió, perdió o dejó a la vista, ni por entradas obtenidas fuera de este sitio. Si sospecha que un código fue copiado, escríbanos antes de la función para revisarlo.</p>
  <p>Cada entrada tiene un código único; si por un error nuestro se emitiera más de una vez la misma entrada o se cobrara dos veces una compra, se anula la duplicada y se devuelve lo cobrado de más.</p>

  <h2 id="arrepentimiento">7. Derecho de arrepentimiento</h2>
  <p>Quien compra puede <strong>revocar la compra sin dar motivos y sin costo</strong> dentro de los <strong><?= ARREPENTIMIENTO_DIAS ?> días corridos</strong> contados desde que recibe las entradas o el comprobante de compra, lo que ocurra primero, y siempre que lo haga como mínimo <strong><?= ARREPENTIMIENTO_HORAS_ANTES ?> horas antes</strong> del inicio de la función. No se puede ejercer sobre entradas ya utilizadas.</p>
  <p>Se ejerce desde el <a href="/arrepentimiento">Botón de arrepentimiento</a>, que está al pie de todas las páginas y no requiere iniciar sesión (alcanza con el código de la orden y el correo de la compra). Al enviarlo se informa un código de trámite, que también llega por correo. La compra se anula entera, las entradas dejan de servir y <strong>se devuelve la totalidad de lo pagado</strong>, incluidos los cargos, por transferencia a la cuenta que indique quien compró.</p>
  <p>Los pedidos que no cumplan los plazos no se rechazan automáticamente: los revisa una persona y responde por correo.</p>

  <h2>8. Reprogramación</h2>
  <p>Si una función cambia de fecha, horario o lugar, se avisa por correo a cada comprador. <strong>Las entradas siguen valiendo para la nueva fecha</strong> sin trámite alguno. Quien no pueda o no quiera asistir a la función reprogramada puede pedir la <strong>devolución total</strong> de lo pagado escribiendo a <a href="mailto:<?= $c ?>"><?= $c ?></a> o desde el botón de arrepentimiento, dentro de los diez días corridos desde el aviso y antes del inicio de la nueva función.</p>

  <h2>9. Cancelación y devoluciones</h2>
  <p>Si una función se cancela, se avisa por correo y <strong>se devuelve la totalidad de lo pagado</strong>, incluidos los cargos, sin que haga falta pedirlo, por transferencia a la cuenta que indique quien compró. Fuera del arrepentimiento, la reprogramación y la cancelación, las entradas no tienen cambio ni devolución, sin perjuicio de los derechos que la Ley 24.240 de Defensa del Consumidor reconoce.</p>
  <p>Las devoluciones se hacen por transferencia bancaria dentro de los diez días hábiles desde que se acepta el pedido o se anuncia la cancelación. Si la compra estaba pendiente de pago, no hay nada que devolver y los lugares vuelven a la venta.</p>

  <h2>10. Caso fortuito o fuerza mayor</h2>
  <p>Si una función no puede realizarse, o debe interrumpirse, por caso fortuito o fuerza mayor (por ejemplo, fenómenos climáticos graves, disposiciones de la autoridad, emergencias sanitarias, cortes de servicios esenciales o hechos de terceros ajenos al organizador), el organizador la reprogramará o la cancelará, y se aplican los puntos 8 y 9. Si la función ya había comenzado y se interrumpe por esas causas, el organizador informará si corresponde una nueva fecha o un reintegro, total o parcial.</p>

  <h2>11. Reseñas</h2>
  <p>Sólo pueden publicar reseñas quienes asistieron a una función (su entrada fue leída en la puerta). Las reseñas muestran el nombre de pila y la fecha y lugar de la función. Nos reservamos el derecho de ocultar reseñas ofensivas, discriminatorias, ajenas al evento o que contengan datos personales de terceros.</p>

  <h2>12. Uso del Sitio</h2>
  <p>Queda prohibido:</p>
  <ul>
    <li>Utilizar el Sitio para fines ilegales o no autorizados.</li>
    <li>Revender entradas con fines comerciales, o comprarlas de manera automatizada.</li>
    <li>Interferir o interrumpir el funcionamiento del Sitio o de los servidores o redes conectados a él.</li>
    <li>Reproducir, duplicar, copiar, vender o explotar cualquier parte del Sitio sin autorización expresa por escrito.</li>
  </ul>

  <h2>13. Propiedad intelectual</h2>
  <p>Los contenidos del Sitio (textos, gráficos, logotipos, imágenes y software) pertenecen a <?= e(APP_NOMBRE) ?>, a los organizadores de cada evento o a sus licenciantes, y están protegidos por las leyes de propiedad intelectual.</p>

  <h2>14. Enlaces a terceros</h2>
  <p>El Sitio puede contener enlaces a sitios de terceros (por ejemplo, servicios de mapas o redes sociales para compartir un evento). Se ofrecen por conveniencia y no implican respaldo. No somos responsables del contenido ni de las prácticas de privacidad de esos sitios.</p>

  <h2>15. Limitación de responsabilidad</h2>
  <p>La Plataforma actúa como medio para la venta y el control de entradas. La realización, calidad y contenido de los eventos corresponden a cada organizador, sin perjuicio de la responsabilidad que la Ley 24.240 asigna a quienes intervienen en la venta. En la medida en que lo permita la ley, no seremos responsables por daños indirectos, incidentales o consecuentes derivados del uso o de la imposibilidad de uso del Sitio.</p>

  <h2>16. Modificaciones</h2>
  <p>Podemos modificar estas Condiciones de Servicio en cualquier momento. Las modificaciones rigen desde su publicación en el Sitio y no alteran las condiciones de las compras ya confirmadas.</p>

  <h2>17. Legislación aplicable</h2>
  <p>Estas Condiciones se rigen por las leyes de la República Argentina. Cualquier disputa se someterá a los tribunales competentes de la Ciudad Autónoma de Buenos Aires, sin perjuicio de los derechos que la normativa de defensa del consumidor reconoce a los usuarios.</p>

  <h2>18. Contacto</h2>
  <p>Por consultas sobre estas Condiciones de Servicio, escribanos a <a href="mailto:<?= $c ?>"><?= $c ?></a>.</p>
</article>
