<?php
// Configuración. Todo lo propio de cada instalación (nombre, dominio, correo,
// carpeta de datos, modo prueba) va en config.local.php, en la raíz del
// proyecto, que NO se versiona. Copiá config.example.php y editalo.
// Lo que no esté definido ahí toma el valor por defecto de abajo.

$__local = dirname(__DIR__, 2) . '/config.local.php';
if (is_file($__local)) require $__local;
unset($__local);

/** Define la constante sólo si config.local.php no la definió antes. */
function _cfg(string $nombre, $valor): void
{
    if (!defined($nombre)) define($nombre, $valor);
}

_cfg('APP_NOMBRE', 'Ticketera QR');
// Quién produce los eventos (opcional). Si se completa, aparece como
// «<organizador> presenta» por defecto en los eventos nuevos y en el pie.
_cfg('ORGANIZADOR', '');
_cfg('ORGANIZADOR_URL', '');
// Dirección pública del sitio, sin barra final. Se usa en correos, metadatos y
// sitemap. En modo prueba se toma el Host del pedido.
_cfg('URL_PUBLICA', 'http://localhost:8190');
_cfg('ZONA_HORARIA', 'America/Argentina/Buenos_Aires');

// La tarjeta no está integrada: su «pago» es simulado. Mientras sea así,
// fuera del modo prueba la tarjeta no se puede encender (tarjeta_activa()).
const PAGO_SIMULADO = true;

// Modo prueba: franja de aviso arriba, noindex, «no transfieras dinero», no se
// mandan correos y los errores se muestran con detalle. En una instalación
// real, poné MODO_DEMO en false en config.local.php.
_cfg('MODO_DEMO', true);
// Producción = no es modo prueba: cookies «secure», correos de verdad, errores
// sin detalle. Requiere https.
define('ES_PROD', !MODO_DEMO);

// Versión de lo publicado: si existe app/version.txt (por ejemplo, la escribe
// tu script de publicación con fecha y commit), se usa esa. Va en la URL de
// cada CSS y JS y dentro del service worker, así cada publicación obliga al
// navegador a renovar todo. Si no hay archivo, vale la última modificación.
define('VERSION_APP', (function () {
    $v = trim((string)@file_get_contents(dirname(__DIR__) . '/version.txt'));
    if ($v !== '') return $v;
    $m = 0;
    foreach (glob(dirname(__DIR__) . '/estaticos/*.{css,js}', GLOB_BRACE) as $f) $m = max($m, filemtime($f));
    return (string)max($m, filemtime(__DIR__ . '/sw.js'));
})());

define('DIR_APP', dirname(__DIR__));
// La base, las claves y los comprobantes van FUERA de la carpeta pública.
// En un hosting compartido, apuntalo a una carpeta hermana de public_html.
_cfg('DIR_DATOS', dirname(DIR_APP) . '/datos');
define('ARCHIVO_BASE', DIR_DATOS . '/base.sqlite');
define('DIR_SUBIDAS', DIR_APP . '/subidas');
const URL_SUBIDAS = '/subidas';

// Casilla de la plataforma: remitente de todos los correos y contacto de los
// textos legales. La contraseña NO va acá: va en DIR_DATOS/secretos.php.
_cfg('CORREO_CONTACTO', 'contacto@example.com');
_cfg('SMTP_HOST', 'smtp.example.com');
_cfg('SMTP_PUERTO', 465);
// Topes de envío de tu proveedor por casilla [segundos de la ventana, máximo].
// Se frena un poco antes (CORREO_MARGEN) para no rozarlos.
_cfg('CORREO_TOPES', ['hora' => [3600, 100], 'dia' => [86400, 800], 'semana' => [604800, 4000], 'mes' => [2592000, 15000]]);
_cfg('CORREO_MARGEN', 0.95);

date_default_timezone_set(ZONA_HORARIA);

// secretos.php define SMTP_CLAVE y, opcionalmente, TAREA_CLAVE.
if (is_file(DIR_DATOS . '/secretos.php')) require DIR_DATOS . '/secretos.php';
if (!defined('SMTP_CLAVE')) define('SMTP_CLAVE', '');
