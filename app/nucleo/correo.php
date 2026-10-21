<?php
// Correo: todo pasa por una cola (tabla `correos`) y se despacha respetando los
// topes del proveedor por casilla. Ninguna página espera al SMTP: la compra se
// confirma al instante y el correo sale al final del pedido, o en el próximo.
//
// Topes (CORREO_TOPES en config.php): 100/hora, 800/día, 4000/semana,
// 15000/mes, medidos en ventanas móviles sobre los enviados. Si alguno está al
// 95 %, el correo espera: superar un tope puede dejar la casilla marcada como
// spam y entonces no sale ni la confirmación de compra.

use PHPMailer\PHPMailer\PHPMailer;

/**
 * $adjuntos: imágenes incrustadas que el HTML referencia como cid:<cid>.
 *   ['cid' => 'qr-ABC', 'qr' => 'TQR:ABC']             QR generado al enviar
 *   ['cid' => 'portada', 'archivo' => 'eventos/3/x-og.jpg']  archivo de subidas/
 * Van incrustadas y no como enlace porque muchos programas de correo bloquean
 * las imágenes remotas, y el QR tiene que verse aunque no haya señal.
 */
function encolar_correo(string $para, string $asunto, string $html, string $texto, string $motivo = '', array $adjuntos = []): void
{
    q('INSERT INTO correos (para, asunto, html, texto, motivo, adjuntos, creado) VALUES (?,?,?,?,?,?,?)',
        [$para, $asunto, $html, $texto, $motivo, $adjuntos ? json_encode($adjuntos) : null, ahora()]);
    $GLOBALS['hay_correo_nuevo'] = true;
}

/** Cuántos se pueden mandar ya, sin pasar ningún tope. */
function cupo_correo(): array
{
    $libre = PHP_INT_MAX;
    $detalle = [];
    foreach (CORREO_TOPES as $nombre => [$seg, $tope]) {
        $usados = (int)valor("SELECT COUNT(*) FROM correos WHERE estado = 'enviado' AND enviado >= ?", [date('Y-m-d H:i:s', time() - $seg)]);
        $techo = (int)floor($tope * CORREO_MARGEN);
        $detalle[$nombre] = ['usados' => $usados, 'tope' => $tope, 'techo' => $techo];
        $libre = min($libre, max(0, $techo - $usados));
    }
    return ['libre' => $libre, 'detalle' => $detalle];
}

/** Despacha hasta $max correos pendientes. Devuelve cuántos salieron. */
function despachar_correos(int $max = 5): int
{
    // Un solo despachante a la vez: dos pedidos simultáneos no pueden mandar
    // el mismo correo dos veces ni contar mal los topes.
    $candado = fopen(DIR_DATOS . '/correo.lock', 'c');
    if (!$candado || !flock($candado, LOCK_EX | LOCK_NB)) return 0;
    try {
        $libre = cupo_correo()['libre'];
        if ($libre <= 0) return 0;
        // Reintentos con espera creciente: 5 min, 20 min, 1 h… hasta 6 intentos.
        $pend = filas("SELECT * FROM correos WHERE estado = 'pendiente' OR (estado = 'error' AND intentos < 6) ORDER BY id LIMIT ?", [min($max, $libre) * 3]);
        $enviados = 0;
        $mailer = null;
        foreach ($pend as $c) {
            if ($enviados >= min($max, $libre)) break;
            if ($c['estado'] === 'error') {
                $espera = 300 * (4 ** max(0, $c['intentos'] - 1));
                if (strtotime($c['creado']) + $espera > time() && $c['intentos'] > 0) continue;
            }
            // Las cuentas de ejemplo usan dominios .invalid: nunca se intenta.
            if (str_ends_with(strtolower($c['para']), '.invalid') || !filter_var($c['para'], FILTER_VALIDATE_EMAIL)) {
                q("UPDATE correos SET estado = 'omitido', error = 'destinatario de prueba o inválido' WHERE id = ?", [$c['id']]);
                continue;
            }
            // En modo prueba no se manda nada de verdad.
            if (!ES_PROD || SMTP_CLAVE === '') {
                q("UPDATE correos SET estado = 'omitido', error = ? WHERE id = ?", [ES_PROD ? 'sin contraseña SMTP' : 'modo prueba: no se envía', $c['id']]);
                continue;
            }
            try {
                $mailer ??= nuevo_mailer();
                $mailer->clearAddresses();
                $mailer->clearAttachments();
                $mailer->addAddress($c['para']);
                foreach (json_decode((string)$c['adjuntos'], true) ?: [] as $a) {
                    if (isset($a['qr'])) {
                        $mailer->addStringEmbeddedImage(qr_png($a['qr']), $a['cid'], $a['cid'] . '.png', PHPMailer::ENCODING_BASE64, 'image/png');
                    } elseif (isset($a['archivo']) && is_file($f = DIR_SUBIDAS . '/' . $a['archivo'])) {
                        $mailer->addEmbeddedImage($f, $a['cid'], basename($f), PHPMailer::ENCODING_BASE64, 'image/jpeg');
                    }
                }
                $mailer->Subject = $c['asunto'];
                $mailer->Body = $c['html'];
                $mailer->AltBody = $c['texto'];
                $mailer->send();
                q("UPDATE correos SET estado = 'enviado', enviado = ?, intentos = intentos + 1, error = NULL WHERE id = ?", [ahora(), $c['id']]);
                $enviados++;
            } catch (Throwable $ex) {
                q("UPDATE correos SET estado = 'error', intentos = intentos + 1, error = ? WHERE id = ?", [mb_substr($ex->getMessage(), 0, 300), $c['id']]);
                $mailer = null; // conexión rota: la próxima vuelta abre otra
            }
        }
        if ($mailer) $mailer->smtpClose();
        return $enviados;
    } finally {
        flock($candado, LOCK_UN);
        fclose($candado);
    }
}

function nuevo_mailer(): PHPMailer
{
    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host = SMTP_HOST;
    $m->Port = SMTP_PUERTO;
    $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $m->SMTPAuth = true;
    $m->Username = CORREO_CONTACTO;
    $m->Password = SMTP_CLAVE;
    $m->SMTPKeepAlive = true;
    $m->Timeout = 20;
    $m->CharSet = 'UTF-8';
    $m->setFrom(CORREO_CONTACTO, APP_NOMBRE);
    $m->addReplyTo(CORREO_CONTACTO, APP_NOMBRE);
    $m->isHTML(true);
    return $m;
}

/**
 * Despacho al terminar el pedido, después de mandarle la respuesta al
 * navegador: así el que compra no espera al SMTP, y el correo sale en el
 * momento, no cuando pase un cron. Con PHP-FPM existe fastcgi_finish_request;
 * si no, el pedido tarda un poco más y listo.
 *
 * Además, cualquier visita reintenta lo que haya quedado pendiente (un envío
 * que falló o que esperaba un tope). La tarea programada del hosting
 * (cron_correos.php) queda como respaldo opcional para las horas sin visitas.
 */
function despachar_al_final(): void
{
    register_shutdown_function(function () {
        try { vencer_reservas(); } catch (Throwable $ex) { error_log('[app reservas] ' . $ex->getMessage()); }
        if (empty($GLOBALS['hay_correo_nuevo'])) {
            $hay = valor("SELECT 1 FROM correos WHERE estado = 'pendiente' OR (estado = 'error' AND intentos < 6) LIMIT 1");
            if (!$hay) return;
        }
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        try { despachar_correos(5); } catch (Throwable $ex) { error_log('[app correo] ' . $ex->getMessage()); }
    });
}

/** PNG del QR (negro sobre blanco, con margen) para incrustar en el correo. */
function qr_png(string $dato): string
{
    require_once __DIR__ . '/vendor/autoload.php';
    $op = new \chillerlan\QRCode\QROptions([
        'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
        'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
        'scale' => 10, 'quietzoneSize' => 2, 'outputBase64' => false,
    ]);
    return (new \chillerlan\QRCode\QRCode($op))->render($dato);
}

// ── Plantillas ─────────────────────────────────────────────────────────────
// HTML de correo: tablas y estilos en línea, ancho 600, porque Gmail y Outlook
// ignoran casi todo el CSS moderno. Misma paleta que el sitio.

const MAIL_TINTA = '#12100e';
const MAIL_TENUE = '#6f665b';
const MAIL_BORDE = '#ddd2c0';
const MAIL_FONDO = '#f3ede2';
const MAIL_ACENTO = '#0b7a70';   // naranja para texto sobre blanco
const MAIL_NARANJA = '#2ec4b6';  // color de acento, para fondos
// Las fuentes web no llegan al correo: Montserrat cae en Helvetica/Arial y
// los titulares condensados en Impact / Arial Narrow.
const MAIL_FUENTE = "Montserrat,'Segoe UI',Helvetica,Arial,sans-serif";
const MAIL_TITULOS = "Anton,Impact,'Arial Narrow Bold','Arial Narrow',sans-serif";

function mail_boton(string $texto, string $url, bool $secundario = false): string
{
    $estilo = $secundario
        ? 'background:#ffffff;color:' . MAIL_TINTA . ';border:1px solid #c6b9a4'
        : 'background:' . MAIL_NARANJA . ';color:' . MAIL_TINTA . ';border:1px solid ' . MAIL_NARANJA;
    return '<a href="' . e($url) . '" style="display:inline-block;' . $estilo . ';text-decoration:none;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.12em;padding:13px 22px;border-radius:4px;margin:0 8px 8px 0">' . e($texto) . '</a>';
}

/**
 * Marco común. $o: previa (texto que muestra la bandeja antes de abrir),
 * imagen (cid de la imagen de cabecera), banda (franja oscura con la marca).
 */
function correo_html(string $cuerpo_html, array $o = []): string
{
    $f = MAIL_FUENTE;
    $previa = isset($o['previa']) ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . e($o['previa']) . str_repeat('&#847; ', 40) . '</div>' : '';
    // Siempre la franja negra con la marca, como el sitio. ($o['banda'] quedó
    // de cuando había cabecera clara.)
    $marca = '<span style="font-family:' . MAIL_TITULOS . ';font-size:22px;text-transform:uppercase;letter-spacing:.02em;color:#eef1f4">' . e(APP_NOMBRE) . '</span>';
    $club = '<span style="font-size:10px;text-transform:uppercase;letter-spacing:.25em;color:#9a9184">' . (ORGANIZADOR !== '' ? 'La ticketera de <b style="color:#eef1f4">' . e(ORGANIZADOR) . '</b>' : 'Entradas con QR') . '</span>';
    $cabecera = '<tr><td style="background:#0b0a09;border-radius:10px 10px 0 0;padding:22px 28px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td>' . $marca . '</td><td align="right">' . $club . '</td></tr></table></td></tr>';
    $imagen = !empty($o['imagen'])
        ? '<tr><td style="padding:16px 28px 0"><img src="cid:' . e($o['imagen']) . '" width="544" alt="" style="display:block;width:100%;max-width:544px;height:auto;border-radius:6px;border:0"></td></tr>'
        : '';
    $legales = '<a href="' . URL_PUBLICA . '/condiciones" style="color:' . MAIL_TENUE . '">Condiciones</a> · <a href="' . URL_PUBLICA . '/privacidad" style="color:' . MAIL_TENUE . '">Privacidad</a> · <a href="' . URL_PUBLICA . '/arrepentimiento" style="color:' . MAIL_TENUE . '">Botón de arrepentimiento</a> · <a href="mailto:' . CORREO_CONTACTO . '" style="color:' . MAIL_TENUE . '">' . CORREO_CONTACTO . '</a>';
    return '<!doctype html><html lang="es-AR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . e(APP_NOMBRE) . '</title></head>
<body style="margin:0;padding:0;background:' . MAIL_FONDO . ';font-family:' . $f . ';color:' . MAIL_TINTA . ';-webkit-font-smoothing:antialiased">' . $previa . '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . MAIL_FONDO . '"><tr><td align="center" style="padding:28px 12px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid ' . MAIL_BORDE . ';border-radius:10px">
' . $cabecera . $imagen . '
<tr><td style="padding:22px 28px 30px;font-size:15px;line-height:1.6">' . $cuerpo_html . '</td></tr>
</table>
<p style="font-size:12px;line-height:1.6;color:' . MAIL_TENUE . ';margin:18px 0 0;font-family:' . $f . '">' . e(APP_NOMBRE) . (ORGANIZADOR !== '' ? ' · la ticketera de ' . e(ORGANIZADOR) : '') . '<br>' . $legales . '</p>
</td></tr></table></body></html>';
}

function correo_bienvenida(array $u): void
{
    $nombre = explode(' ', trim($u['nombre']))[0];
    // Los tres próximos eventos con lugar, con su imagen: la bienvenida ya
    // muestra a qué se puede ir.
    $evs = filas("SELECT e.titulo, e.slug, f.id AS fid, f.inicio, f.lugar, " . SQL_DESDE_F . " AS desde,
                    (SELECT archivo FROM evento_imagenes i WHERE i.evento_id = e.id ORDER BY i.orden, i.id LIMIT 1) AS portada
                  FROM funciones f JOIN eventos e ON e.id = f.evento_id
                  WHERE e.estado = 'publicado' AND e.visibilidad = 'publica' AND f.estado = 'activa' AND f.inicio >= ? AND " . SQL_DISPONIBLES_F . " > 0
                  GROUP BY e.id ORDER BY f.inicio LIMIT 3", [ahora()]);
    $adj = [];
    $filas = '';
    foreach ($evs as $i => $ev) {
        $url = URL_PUBLICA . '/evento/' . $ev['slug'] . '?f=' . $ev['fid'];
        $img = '';
        if ($ev['portada']) {
            $adj[] = ['cid' => "ev$i", 'archivo' => 'eventos/' . $ev['portada'] . '-m.jpg'];
            $img = '<a href="' . e($url) . '"><img src="cid:ev' . $i . '" width="120" height="80" alt="" style="display:block;width:120px;height:80px;object-fit:cover;border-radius:8px;border:0"></a>';
        }
        $precio = $ev['desde'] === null ? '' : ((int)$ev['desde'] === 0 ? 'Sin cargo' : 'Desde ' . pesos((int)$ev['desde']));
        $filas .= '<tr><td style="padding:12px 0;border-top:1px solid ' . MAIL_BORDE . ';width:132px;vertical-align:top">' . $img . '</td>
          <td style="padding:12px 0 12px 4px;border-top:1px solid ' . MAIL_BORDE . ';vertical-align:top;font-size:14px;line-height:1.45">
            <a href="' . e($url) . '" style="color:' . MAIL_TINTA . ';font-weight:650;font-size:15px;text-decoration:none">' . e($ev['titulo']) . '</a><br>
            <span style="color:' . MAIL_TENUE . '">' . e(ucfirst(fecha_corta($ev['inicio']))) . ' h' . ($ev['lugar'] ? ' · ' . e($ev['lugar']) : '') . '</span><br>
            <span>' . e($precio) . '</span></td></tr>';
    }
    $proximos = $filas ? '<p style="margin:26px 0 6px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:' . MAIL_ACENTO . ';font-weight:700">Próximos eventos</p>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $filas . '</table>' : '';
    $cuerpo = '<h1 style="font-family:' . MAIL_TITULOS . ';font-weight:400;text-transform:uppercase;font-size:30px;line-height:1.2;letter-spacing:.01em;margin:4px 0 12px">Bienvenido, ' . e($nombre) . '</h1>
      <p style="margin:0 0 18px;color:#3b352f">Tu cuenta ya está lista. Con ella comprás entradas en un par de toques y las tenés siempre en el celular, cada una con su código QR para entrar.</p>'
      . mail_boton('Ver eventos', URL_PUBLICA . '/') . $proximos;
    $texto = "Bienvenido, $nombre.\nTu cuenta en " . APP_NOMBRE . " ya está lista.\nVer eventos: " . URL_PUBLICA . "/\n";
    foreach ($evs as $ev) $texto .= "\n- {$ev['titulo']}: " . fecha_corta($ev['inicio']) . " h · " . URL_PUBLICA . "/evento/{$ev['slug']}";
    encolar_correo($u['email'], 'Bienvenido a ' . APP_NOMBRE, correo_html($cuerpo, ['banda' => true, 'previa' => 'Tu cuenta ya está lista. Mirá los próximos eventos.']), $texto, 'bienvenida', $adj);
}

/** $para: otro destinatario (el correo de prueba del panel se lo manda a la casilla). */
function correo_compra(int $orden_id, ?string $para = null, string $motivo = 'compra'): void
{
    $o = fila('SELECT o.*, u.nombre, u.email, e.titulo, e.slug, f.id AS fid, f.inicio, f.fin, f.lugar, f.direccion, f.ciudad, f.lat, f.lng,
                 (SELECT archivo FROM evento_imagenes i WHERE i.evento_id = e.id ORDER BY i.orden, i.id LIMIT 1) AS portada
               FROM ordenes o JOIN usuarios u ON u.id = o.usuario_id JOIN funciones f ON f.id = o.funcion_id JOIN eventos e ON e.id = f.evento_id WHERE o.id = ?', [$orden_id]);
    $entradas = filas('SELECT x.*, t.nombre AS tipo FROM entradas x JOIN tipos_entrada t ON t.id = x.tipo_id WHERE x.orden_id = ? ORDER BY x.id', [$orden_id]);
    $adj = [];
    if ($o['portada']) $adj[] = ['cid' => 'portada', 'archivo' => 'eventos/' . $o['portada'] . '-og.jpg'];
    $dir = trim(($o['direccion'] ?? '') . ', ' . ($o['ciudad'] ?? ''), ', ');
    $destino = $o['lat'] !== null ? $o['lat'] . ',' . $o['lng'] : $dir;
    $mapa = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destino);
    $n = count($entradas);

    $tickets = '';
    $lista_txt = '';
    foreach ($entradas as $x) {
        $adj[] = ['cid' => 'qr-' . $x['codigo'], 'qr' => 'TQR:' . $x['codigo']];
        $url = URL_PUBLICA . '/entrada/' . $x['codigo'];
        $tickets .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1.5px dashed #c6b9a4;border-radius:8px;margin:0 0 12px">
          <tr><td style="padding:14px;width:132px;vertical-align:middle"><img src="cid:qr-' . e($x['codigo']) . '" width="124" height="124" alt="QR de la entrada ' . e($x['codigo']) . '" style="display:block;width:124px;height:124px;border:0"></td>
          <td style="padding:14px 16px 14px 4px;vertical-align:middle;font-size:14px;line-height:1.5">
            <span style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:' . MAIL_TENUE . ';font-weight:700">' . e($x['tipo']) . '</span><br>
            <span style="font-size:17px;font-weight:650">' . e($x['titular']) . '</span><br>
            <span style="font-family:Menlo,Consolas,monospace;letter-spacing:.12em;font-size:14px">' . e(implode(' ', str_split($x['codigo'], 5))) . '</span><br>
            <a href="' . e($url) . '" style="color:' . MAIL_TINTA . ';font-size:13px">Abrir en el celular</a></td></tr></table>';
        $lista_txt .= "- {$x['titular']} ({$x['tipo']}) {$x['codigo']}: $url\n";
    }
    $cuerpo = '<p style="margin:0 0 4px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:' . MAIL_ACENTO . ';font-weight:700">Compra confirmada</p>
      <h1 style="font-family:' . MAIL_TITULOS . ';font-weight:400;text-transform:uppercase;font-size:30px;line-height:1.2;letter-spacing:.01em;margin:0 0 16px">' . e($o['titulo']) . '</h1>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . MAIL_FONDO . ';border-radius:8px;margin:0 0 20px">
        <tr><td style="padding:14px 18px;font-size:14px;line-height:1.55;vertical-align:top;width:50%">
          <span style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:' . MAIL_TENUE . ';font-weight:700">Cuándo</span><br>
          <strong>' . e(ucfirst(fecha_larga($o['inicio']))) . '</strong><br>' . e(hora($o['inicio'])) . ' h' . ($o['fin'] ? ' a ' . e(hora($o['fin'])) . ' h' : '') . '
        </td><td style="padding:14px 18px;font-size:14px;line-height:1.55;vertical-align:top">
          <span style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:' . MAIL_TENUE . ';font-weight:700">Dónde</span><br>
          <strong>' . e($o['lugar'] ?: $o['ciudad']) . '</strong><br>' . e($dir) . '<br>
          <a href="' . e($mapa) . '" style="color:' . MAIL_TINTA . ';font-size:13px">Cómo llegar ↗</a>
        </td></tr></table>
      <p style="margin:0 0 10px;font-size:14px;color:#3b352f">' . ($n === 1 ? 'Tu entrada. Mostrá el QR en la puerta:' : "Tus $n entradas. Cada persona muestra su QR en la puerta:") . '</p>'
      . $tickets . '
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 18px;font-size:14px"><tr>
        <td style="padding:10px 0;border-top:1px solid ' . MAIL_BORDE . ';color:' . MAIL_TENUE . '">Orden ' . e($o['codigo']) . ' · ' . $n . ($n === 1 ? ' entrada' : ' entradas')
          . ((int)$o['cargo'] ? '<br><span style="font-size:12px">Incluye ' . e(pesos((int)$o['cargo'])) . ' de cargo por servicio</span>' : '')
          . ($o['medio_pago'] === 'transferencia' ? '<br><span style="font-size:12px">Pagado por transferencia</span>' : '') . '</td>
        <td align="right" style="padding:10px 0;border-top:1px solid ' . MAIL_BORDE . ';font-weight:700;font-size:16px">' . e(pesos((int)$o['total'])) . '</td></tr></table>
      <p style="margin:0 0 20px;font-size:13px;color:' . MAIL_TENUE . '">Cada QR sirve una sola vez. No hace falta imprimirlo: subí el brillo del celular y listo. No lo compartas: el primero que lo muestra, entra.</p>'
      . mail_boton('Ver mis entradas', URL_PUBLICA . '/mis-entradas') . mail_boton('Ver el evento', URL_PUBLICA . '/evento/' . $o['slug'] . '?f=' . $o['fid'], true);
    $texto = "Compra confirmada: {$o['titulo']}\n" . ucfirst(fecha_larga($o['inicio'])) . ' ' . hora($o['inicio']) . " h\n" . trim(($o['lugar'] ?? '') . ' · ' . $dir, ' ·') . "\nCómo llegar: $mapa\n\n$lista_txt\nTotal: " . pesos((int)$o['total']) . "\nOrden {$o['codigo']}\nMis entradas: " . URL_PUBLICA . "/mis-entradas";
    encolar_correo($para ?? $o['email'], ($para ? '[Prueba] ' : '') . 'Tus entradas · ' . $o['titulo'],
        correo_html($cuerpo, ['imagen' => $o['portada'] ? 'portada' : null, 'previa' => ucfirst(fecha_corta($o['inicio'])) . ' h · ' . ($o['lugar'] ?: '') . ' · ' . $n . ($n === 1 ? ' entrada con su QR' : ' entradas con su QR')]),
        $texto, $motivo, $adj);
}

function correo_recupero(array $u, string $token): void
{
    $url = URL_PUBLICA . '/recuperar/' . $token;
    $cuerpo = '<h1 style="font-family:' . MAIL_TITULOS . ';font-weight:400;text-transform:uppercase;font-size:30px;line-height:1.2;letter-spacing:.01em;margin:4px 0 12px">Restablecer contraseña</h1>
      <p style="margin:0 0 18px;color:#3b352f">Pediste cambiar la contraseña de tu cuenta. El enlace sirve una sola vez y vence en una hora.</p>'
      . mail_boton('Elegir una contraseña nueva', $url) . '
      <p style="margin:14px 0 0;font-size:13px;color:' . MAIL_TENUE . '">Si no fuiste vos, ignorá este correo: tu contraseña sigue igual.</p>';
    encolar_correo($u['email'], 'Restablecer tu contraseña', correo_html($cuerpo, ['banda' => true, 'previa' => 'El enlace vence en una hora.']),
        "Para elegir una contraseña nueva (vence en una hora):\n$url", 'recupero');
}
