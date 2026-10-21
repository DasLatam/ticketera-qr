<?php
// «No soy un robot» propio, sin servicios de terceros.
//
// Tres barreras, en orden de costo para el que ataca:
//   1. Prueba de trabajo: el servidor elige un número secreto, publica el hash
//      sha256(sal + número) firmado con HMAC, y el navegador tiene que
//      encontrar el número probando (~1 s en un celular). Para una persona es
//      tildar una casilla; para un robot que manda miles de formularios, cada
//      uno cuesta CPU. La firma impide inventar desafíos fáciles y la tabla
//      captcha_usados impide reusar uno resuelto.
//   2. Campo trampa: un input invisible que una persona nunca completa.
//   3. Tiempo mínimo: un formulario enviado en menos de 1 s no lo llenó nadie.
//
// No usa cookies ni manda datos a nadie: por eso no cambia la Política de
// Cookies, como sí la cambiaría reCAPTCHA.

const CAPTCHA_MAX = 60000;      // tope del número secreto: ~0,3–1,5 s de cálculo
const CAPTCHA_VIGENCIA = 1800;  // segundos que vale un desafío
const CAPTCHA_TIEMPO_MIN = 1;   // segundos mínimos entre mostrar y enviar (con autocompletado, una persona tarda más)

function clave_captcha(): string
{
    $f = DIR_DATOS . '/captcha.key';
    if (!is_file($f)) {
        if (!is_dir(DIR_DATOS)) mkdir(DIR_DATOS, 0750, true);
        file_put_contents($f, bin2hex(random_bytes(32)));
        chmod($f, 0600);
    }
    return trim(file_get_contents($f));
}

/** HTML del widget para meter dentro de un <form>. */
function campo_captcha(): string
{
    $sal = bin2hex(random_bytes(12)) . '.' . (time() + CAPTCHA_VIGENCIA) . '.' . time();
    $numero = random_int(1, CAPTCHA_MAX);
    $desafio = hash('sha256', $sal . $numero);
    $firma = hash_hmac('sha256', $desafio, clave_captcha());
    return '<div class="captcha" data-captcha data-sal="' . e($sal) . '" data-desafio="' . $desafio . '" data-firma="' . $firma . '" data-max="' . CAPTCHA_MAX . '">
  <input type="hidden" name="captcha" value="">
  <label class="captcha-caja"><input type="checkbox" data-captcha-check><span class="captcha-marca" aria-hidden="true"></span>
    <span class="captcha-texto" data-captcha-texto>No soy un robot</span></label>
  <span class="captcha-pie">Verificación sin cookies</span>
  <noscript><span class="captcha-pie">Para continuar, activá JavaScript.</span></noscript>
</div>
<div class="campo-trampa" aria-hidden="true"><label>No completar este campo <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>';
}

/** true si el formulario pasó las tres barreras. */
function captcha_valido(): bool
{
    if (trim((string)($_POST['sitio_web'] ?? '')) !== '') return false;
    $datos = json_decode(base64_decode((string)($_POST['captcha'] ?? ''), true) ?: '', true);
    if (!is_array($datos) || !isset($datos['sal'], $datos['numero'], $datos['desafio'], $datos['firma'])) return false;
    [$azar, $vence, $emitido] = array_pad(explode('.', (string)$datos['sal']), 3, 0);
    if ((int)$vence < time() || time() - (int)$emitido < CAPTCHA_TIEMPO_MIN) return false;
    if (!hash_equals(hash_hmac('sha256', (string)$datos['desafio'], clave_captcha()), (string)$datos['firma'])) return false;
    if (!hash_equals((string)$datos['desafio'], hash('sha256', $datos['sal'] . (int)$datos['numero']))) return false;
    // Un desafío resuelto sirve una sola vez.
    q('DELETE FROM captcha_usados WHERE vence < ?', [time()]);
    try {
        q('INSERT INTO captcha_usados (desafio, vence) VALUES (?, ?)', [$datos['desafio'], (int)$vence]);
    } catch (PDOException) {
        return false;
    }
    return true;
}
