<?php
// Copiá este archivo a DIR_DATOS/secretos.php (por defecto, datos/secretos.php),
// fuera de la carpeta pública, y completalo. No lo subas a ningún repositorio.

// Contraseña de la casilla CORREO_CONTACTO.
const SMTP_CLAVE = '';

// Opcional: clave para disparar el despacho de correos por web
// (https://tu-sitio/tarea/correos?clave=...), útil si el cron del hosting no
// puede correr `php` por consola. Sin clave, esa dirección da 404.
// Generala con:  php -r 'echo bin2hex(random_bytes(16)), "\n";'
const TAREA_CLAVE = '';
