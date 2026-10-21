<?php
// Autoloader mínimo para las librerías vendorizadas a mano (sin composer):
// chillerlan/php-qrcode 6.0.1 (MIT) y su dependencia
// chillerlan/php-settings-container 3.x (MIT). Sólo se usan para dibujar el QR
// de las entradas dentro de los correos.
spl_autoload_register(function (string $clase) {
    $mapa = [
        'chillerlan\\QRCode\\' => __DIR__ . '/php-qrcode/src/',
        'chillerlan\\Settings\\' => __DIR__ . '/php-settings-container/src/',
    ];
    foreach ($mapa as $prefijo => $dir) {
        if (str_starts_with($clase, $prefijo)) {
            $f = $dir . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
            if (is_file($f)) require $f;
            return;
        }
    }
});
