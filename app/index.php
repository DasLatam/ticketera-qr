<?php
// Controlador frontal. En Apache llega por el .htaccess; en desarrollo,
// `php -S` lo usa de router y los archivos que existen se sirven solos.

if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($f !== __DIR__ . '/' && is_file($f) && !preg_match('#/(nucleo|vistas)/#', $f)) return false;
}

// El service worker sale de nucleo/sw.js con la versión publicada adentro:
// si su contenido no cambia, el navegador no lo renueva. Antes de la sesión,
// para no mandar cookies con él.
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/sw.js') {
    require __DIR__ . '/nucleo/config.php';
    header('Content-Type: text/javascript; charset=utf-8'); // no application/javascript: mod_expires le sumaría 30 días
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Service-Worker-Allowed: /');
    echo str_replace('__VERSION__', preg_replace('/[^A-Za-z0-9._-]/', '', VERSION_APP), file_get_contents(__DIR__ . '/nucleo/sw.js'));
    exit;
}

require __DIR__ . '/nucleo/base.php';
require __DIR__ . '/nucleo/publico.php';
require __DIR__ . '/nucleo/cuenta.php';
require __DIR__ . '/nucleo/puerta.php';
require __DIR__ . '/nucleo/admin.php';
require __DIR__ . '/nucleo/correo.php';
require __DIR__ . '/nucleo/captcha.php';
require __DIR__ . '/nucleo/transferencias.php';
require __DIR__ . '/nucleo/mercadopago.php';
require __DIR__ . '/nucleo/cupones.php';
require __DIR__ . '/nucleo/arrepentimiento.php';

header('X-Content-Type-Options: nosniff');
// OpenStreetMap exige Referer en cada tesela y bloquea sin él (403 «Access
// blocked»). strict-origin… manda sólo el dominio a sitios de afuera, nunca la ruta.
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
if (MODO_DEMO) header('X-Robots-Tag: noindex, nofollow');

iniciar_sesion();
despachar_al_final();

$rutas = [
    ['GET',  '#^/$#', 'pag_catalogo'],
    ['GET',  '#^/mapa$#', 'pag_mapa'],
    ['GET',  '#^/evento/([a-z0-9-]+)$#', 'pag_evento'],
    ['POST', '#^/evento/([a-z0-9-]+)/resena$#', 'acc_resena'],
    ['POST', '#^/evento/([a-z0-9-]+)/comprar$#', 'acc_elegir_entradas'],
    ['GET',  '#^/checkout$#', 'pag_checkout'],
    ['POST', '#^/checkout$#', 'acc_confirmar_compra'],
    ['POST', '#^/checkout/cupon$#', 'acc_checkout_cupon'],
    ['GET',  '#^/orden/([A-Z0-9]+)$#', 'pag_orden'],
    ['POST', '#^/orden/([A-Z0-9]+)/comprobante$#', 'acc_comprobante'],
    ['POST', '#^/api/orden/([A-Z0-9]+)/ocr$#', 'api_comprador_ocr'],
    ['GET',  '#^/comprobante/([A-Z0-9]+)$#', 'ver_comprobante'],
    ['GET',  '#^/mis-entradas$#', 'pag_mis_entradas'],
    ['GET',  '#^/entrada/([A-Z0-9]+)$#', 'pag_entrada'],
    ['GET',  '#^/api/entrada/([A-Z0-9]+)/estado$#', 'api_entrada_estado'],
    ['GET',  '#^/ingresar$#', 'pag_ingresar'],
    ['POST', '#^/ingresar$#', 'acc_ingresar'],
    ['GET',  '#^/registro$#', 'pag_registro'],
    ['POST', '#^/registro$#', 'acc_registro'],
    ['POST', '#^/salir$#', 'acc_salir'],
    ['GET',  '#^/recuperar$#', 'pag_recuperar'],
    ['POST', '#^/recuperar$#', 'acc_recuperar'],
    ['GET',  '#^/recuperar/([a-f0-9]{48})$#', 'pag_recuperar_token'],
    ['POST', '#^/recuperar/([a-f0-9]{48})$#', 'acc_recuperar_token'],
    ['GET',  '#^/sitemap\.xml$#', 'pag_sitemap'],
    ['GET',  '#^/(condiciones|privacidad|cookies)$#', 'pag_legal'],
    ['GET',  '#^/arrepentimiento$#', 'pag_arrepentimiento'],
    ['POST', '#^/arrepentimiento$#', 'acc_arrepentimiento'],
    ['GET',  '#^/cuenta$#', 'pag_cuenta'],
    ['POST', '#^/cuenta$#', 'acc_cuenta'],

    ['GET',  '#^/tarea/correos$#', 'tarea_correos_web'],
    ['GET',  '#^/puerta$#', 'pag_puerta'],
    ['GET',  '#^/puerta/(\d+)$#', 'pag_puerta_escaner'],
    ['POST', '#^/api/puerta/validar$#', 'api_puerta_validar'],
    ['GET',  '#^/api/puerta/(\d+)/resumen$#', 'api_puerta_resumen'],

    ['GET',  '#^/admin$#', 'adm_tablero'],
    ['GET',  '#^/admin/eventos$#', 'adm_eventos'],
    ['GET',  '#^/admin/eventos/nuevo$#', 'adm_evento_form'],
    ['POST', '#^/admin/eventos/nuevo$#', 'adm_evento_guardar'],
    ['GET',  '#^/admin/eventos/(\d+)$#', 'adm_evento_form'],
    ['POST', '#^/admin/eventos/(\d+)$#', 'adm_evento_guardar'],
    ['POST', '#^/admin/eventos/(\d+)/borrar$#', 'adm_evento_borrar'],
    ['POST', '#^/admin/eventos/(\d+)/duplicar$#', 'adm_evento_duplicar'],
    ['POST', '#^/admin/eventos/(\d+)/imagenes$#', 'adm_imagenes_subir'],
    ['POST', '#^/admin/imagenes/(\d+)/borrar$#', 'adm_imagen_borrar'],
    ['POST', '#^/admin/imagenes/(\d+)/portada$#', 'adm_imagen_portada'],
    ['GET',  '#^/admin/eventos/(\d+)/funciones/nueva$#', 'adm_funcion_form'],
    ['POST', '#^/admin/eventos/(\d+)/funciones/nueva$#', 'adm_funcion_guardar'],
    ['GET',  '#^/admin/eventos/(\d+)/funciones/(\d+)$#', 'adm_funcion_form'],
    ['POST', '#^/admin/eventos/(\d+)/funciones/(\d+)$#', 'adm_funcion_guardar'],
    ['POST', '#^/admin/eventos/(\d+)/funciones/(\d+)/borrar$#', 'adm_funcion_borrar'],
    ['GET',  '#^/admin/funciones/(\d+)/asistentes$#', 'adm_asistentes'],
    ['GET',  '#^/admin/resenas$#', 'adm_resenas'],
    ['POST', '#^/admin/resenas/(\d+)/visible$#', 'adm_resena_visible'],
    ['GET',  '#^/admin/correos$#', 'adm_correos'],
    ['POST', '#^/admin/correos/despachar$#', 'adm_correos_despachar'],
    ['POST', '#^/admin/correos/prueba$#', 'adm_correos_prueba'],
    ['POST', '#^/admin/entradas/(\d+)/anular$#', 'adm_entrada_anular'],
    ['POST', '#^/admin/entradas/(\d+)/reactivar$#', 'adm_entrada_reactivar'],
    ['GET',  '#^/admin/categorias$#', 'adm_categorias'],
    ['POST', '#^/admin/categorias$#', 'adm_categoria_guardar'],
    ['POST', '#^/admin/categorias/(\d+)/borrar$#', 'adm_categoria_borrar'],
    ['GET',  '#^/admin/usuarios$#', 'adm_usuarios'],
    ['POST', '#^/admin/usuarios/(\d+)$#', 'adm_usuario_guardar'],
    ['GET',  '#^/admin/ventas$#', 'adm_ventas'],
    ['GET',  '#^/admin/pagos$#', 'adm_pagos'],
    ['GET',  '#^/admin/transferencias$#', 'adm_transferencias'],
    ['GET',  '#^/admin/cobro$#', 'adm_cobro'],
    ['GET',  '#^/admin/cupones$#', 'adm_cupones'],
    ['POST', '#^/admin/cupones$#', 'adm_cupon_crear'],
    ['POST', '#^/admin/cupones/(\d+)/activo$#', 'adm_cupon_activo'],
    ['POST', '#^/admin/cupones/(\d+)/borrar$#', 'adm_cupon_borrar'],
    ['GET',  '#^/admin/arrepentimientos$#', 'adm_arrepentimientos'],
    ['POST', '#^/admin/arrepentimientos/(\d+)/(aceptar|rechazar|reintegrado)$#', 'adm_arrepentimiento_resolver'],
    ['POST', '#^/admin/transferencias/ajustes$#', 'adm_transferencias_ajustes'],
    ['POST', '#^/admin/ordenes/(\d+)/aprobar$#', 'adm_orden_aprobar'],
    ['POST', '#^/admin/ordenes/(\d+)/rechazar$#', 'adm_orden_rechazar'],
    ['POST', '#^/api/admin/ordenes/(\d+)/ocr$#', 'api_orden_ocr'],
    ['POST', '#^/api/mp/aviso$#', 'api_mp_aviso'],
    ['GET',  '#^/api/admin/orden-por-codigo/([A-Za-z0-9]+)$#', 'api_orden_por_codigo'],
    ['POST', '#^/admin/mercadopago$#', 'adm_mp_guardar'],
    ['POST', '#^/admin/mercadopago/sincronizar$#', 'adm_mp_sincronizar'],
    ['POST', '#^/admin/pagos-mp/(\w+)/conciliar$#', 'adm_pago_conciliar'],
    ['POST', '#^/admin/pagos-mp/(\w+)/descartar$#', 'adm_pago_descartar'],
    ['POST', '#^/admin/transferencias/confirmar-sugeridas$#', 'adm_confirmar_sugeridas'],
];

$metodo = $_SERVER['REQUEST_METHOD'] === 'HEAD' ? 'GET' : $_SERVER['REQUEST_METHOD'];
$ruta = rtrim(ruta_actual(), '/') ?: '/';

try {
    foreach ($rutas as [$m, $patron, $fn]) {
        if ($m === $metodo && preg_match($patron, $ruta, $coinc)) {
            // El aviso de Mercado Pago viene de afuera, sin sesión: se protege
            // con su propia firma (mp_firma_valida), no con el token de formulario.
            if ($metodo === 'POST' && $fn !== 'api_mp_aviso') verificar_csrf();
            $fn(...array_slice($coinc, 1));
            exit;
        }
    }
    no_encontrado();
} catch (Throwable $ex) {
    error_log('[app] ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    $msg = ES_PROD ? 'Algo salió mal de nuestro lado. Probá de nuevo en un momento.' : $ex->getMessage() . ' @ ' . basename($ex->getFile()) . ':' . $ex->getLine();
    if (str_starts_with($ruta, '/api/')) json_resp(['resultado' => 'error', 'mensaje' => $msg], 500);
    vista('error', ['titulo' => 'Error', 'mensaje' => $msg]);
}
