<?php
// Configuración de esta instalación. Copiá este archivo como config.local.php
// (en la misma carpeta, al lado de app/) y cambiá lo que necesites.
// config.local.php NO se versiona: no lo subas a ningún repositorio.
// Todo lo que borres o comentes toma el valor por defecto de app/nucleo/config.php.

// Nombre que se ve en la cabecera, los correos y la app instalable.
// La última palabra sale en el color de acento.
const APP_NOMBRE = 'Ticketera QR';

// Quién produce los eventos (opcional). Si lo completás, aparece en la portada
// («La ticketera de …»), en el pie y como organizador por defecto.
const ORGANIZADOR = '';
// Un enlace a su sitio o red social (opcional).
const ORGANIZADOR_URL = '';

// Dirección pública del sitio, sin barra final. Se usa en correos, metadatos
// y sitemap. Para probar en tu computadora dejá la de localhost.
const URL_PUBLICA = 'http://localhost:8190';
const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';

// true  = modo prueba: franja de aviso, no se mandan correos, sitio cerrado a
//         buscadores, «no transfieras dinero» en las compras.
// false = instalación real: requiere https y la casilla de correo configurada.
const MODO_DEMO = true;

// Carpeta de datos (base SQLite, comprobantes, clave del captcha, sesiones,
// secretos.php). Tiene que quedar FUERA de la carpeta que publica el servidor.
// Por defecto es datos/, al lado de app/.
// define('DIR_DATOS', '/home/tu-usuario/datos-ticketera');

// Casilla desde la que salen los correos (también es el contacto de los
// textos legales). La contraseña va en DIR_DATOS/secretos.php, no acá.
const CORREO_CONTACTO = 'contacto@example.com';
const SMTP_HOST = 'smtp.example.com';
const SMTP_PUERTO = 465;   // 465 = SSL implícito
// Topes de tu proveedor por casilla: [ventana en segundos, máximo de correos].
// const CORREO_TOPES = ['hora' => [3600, 100], 'dia' => [86400, 800], 'semana' => [604800, 4000], 'mes' => [2592000, 15000]];
