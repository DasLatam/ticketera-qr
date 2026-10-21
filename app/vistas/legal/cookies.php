<?php $c = e(CORREO_CONTACTO); $actualizado = '7 de octubre de 2026'; ?>
<article class="contenedor angosto legal">
  <h1 class="titulo-pagina">Política de Cookies</h1>
  <?php require __DIR__ . '/_encabezado.php'; ?>

  <h2>¿Qué son las cookies?</h2>
  <p>Las cookies son pequeños archivos de texto que los sitios web guardan en su dispositivo mientras navega. Permiten, por ejemplo, recordar que usted ya inició sesión.</p>

  <h2>¿Qué cookies usamos?</h2>
  <p>En <?= e(preg_replace('#^https?://#', '', URL_PUBLICA)) ?> usamos <strong>una sola cookie, estrictamente necesaria</strong>:</p>
  <div class="tabla-envoltura">
    <table class="tabla">
      <thead><tr><th>Nombre</th><th>Para qué</th><th>Duración</th></tr></thead>
      <tbody><tr><td class="mono">tqr</td><td>Mantener su sesión iniciada y proteger los formularios contra envíos falsificados.</td><td>30 días</td></tr></tbody>
    </table>
  </div>
  <p>No usamos cookies de rendimiento, de análisis ni de publicidad, y no hay seguimiento de terceros. Los mapas (OpenStreetMap) se descargan sin instalar cookies, y las tipografías se sirven desde este mismo Sitio.</p>
  <p>Si elige el estilo claro u oscuro con el selector del pie de página, esa preferencia se guarda en el almacenamiento local de su navegador (<span class="mono">tqr-tema</span>). No es una cookie, no se envía a nuestro servidor y se borra al volver a «Automático».</p>
  <p>Como la única cookie es necesaria para el funcionamiento del Sitio, no pedimos un consentimiento aparte. Si en el futuro se incorporan otras, se pedirá su consentimiento antes de instalarlas y se actualizará esta página.</p>

  <h2>¿Cómo puede gestionar las cookies?</h2>
  <p>Puede configurar su navegador para aceptar o rechazar cookies. Si bloquea la cookie <span class="mono">tqr</span>, podrá ver los eventos pero no iniciar sesión ni comprar.</p>
  <ul>
    <li><strong>Google Chrome:</strong> menú de tres puntos › Configuración › Privacidad y seguridad › Cookies de terceros y otros datos de sitios.</li>
    <li><strong>Mozilla Firefox:</strong> menú › Configuración › Privacidad y seguridad › Cookies y datos del sitio.</li>
    <li><strong>Microsoft Edge:</strong> menú de tres puntos › Configuración › Cookies y permisos del sitio.</li>
    <li><strong>Safari:</strong> Ajustes (o Preferencias) › Privacidad.</li>
  </ul>

  <h2>Cambios en esta política</h2>
  <p>Podemos actualizar esta Política de Cookies. La fecha de arriba indica la última versión.</p>

  <h2>Contacto</h2>
  <p>Por preguntas sobre esta Política de Cookies, escribanos a <a href="mailto:<?= $c ?>"><?= $c ?></a>.</p>
</article>
