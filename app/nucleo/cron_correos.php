<?php
// Despacho de la cola de correos por consola, para la tarea programada (cron)
// del hosting, por ejemplo cada 15 minutos:
//   php /ruta/a/la/app/nucleo/cron_correos.php
// Es un respaldo: los correos salen en el momento en que se generan, y
// cualquier visita reintenta lo pendiente. Esto cubre las horas sin visitas.
//
// Las primeras líneas están escritas a propósito para que las entienda
// cualquier PHP, hasta el 5: en muchos hostings compartidos el `php` de la
// consola es más viejo que el de la web, y si es viejo el resto ni compila.
// Cada corrida deja una línea en datos/cron.log (o en la ruta de la variable
// de entorno TICKETERA_CRON_LOG) con la versión, así se sabe si la tarea corre.
if (PHP_SAPI !== 'cli') exit;
@date_default_timezone_set('America/Argentina/Buenos_Aires');
$registro = getenv('TICKETERA_CRON_LOG') ? getenv('TICKETERA_CRON_LOG') : dirname(dirname(dirname(__FILE__))) . '/datos/cron.log';
@file_put_contents($registro, date('Y-m-d H:i:s') . ' php ' . PHP_VERSION . "\n", FILE_APPEND);
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    @file_put_contents($registro, "  PHP demasiado viejo para esta app: hace falta 8.2 o más. Usar el comando con curl (ver README).\n", FILE_APPEND);
    exit(1);
}
require __DIR__ . '/tarea_correos.php';
