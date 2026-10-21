<?php
// Pago por transferencia con comprobante: sin
// comisión de Mercado Pago. La persona transfiere, sube la foto o el PDF del
// comprobante, alguien lo verifica en la cuenta y aprueba o rechaza.
//
// Mientras tanto la orden queda `pendiente` con sus entradas creadas: eso
// reserva el lugar (el stock cuenta toda entrada no anulada) pero la entrada
// NO sirve todavía: no muestra el QR y la puerta la rechaza. Si en
// `transf_horas` no aparece el comprobante, la reserva se cae sola.

const COMPROBANTE_MAX = 10 * 1024 * 1024;
const COMPROBANTE_TIPOS = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
    'image/heic' => 'heic', 'image/heif' => 'heic', 'application/pdf' => 'pdf',
];

/** «$ 24.000,37» (o «$ 24.000» si es redondo). */
function pesos_cent(int $cent): string
{
    $p = intdiv($cent, 100);
    $c = $cent % 100;
    return '$ ' . number_format($p, 0, ',', '.') . ($c ? ',' . str_pad((string)$c, 2, '0', STR_PAD_LEFT) : '');
}

/** El importe que se le pide a una orden, en centavos. Unas pocas del 2026-10-03 se crearon con centavos únicos (importe_cent); el resto, el total. */
function importe_orden(array $o): int
{
    return (int)($o['importe_cent'] ?? 0) ?: (int)$o['total'] * 100;
}

function dir_comprobantes(): string
{
    // Fuera de public_html: un comprobante muestra datos bancarios del comprador.
    $d = DIR_DATOS . '/comprobantes';
    if (!is_dir($d)) mkdir($d, 0750, true);
    return $d;
}

/** Hasta cuándo se guarda una reserva por transferencia: lo que pase primero. */
function vencimiento_transferencia(string $inicio_funcion): string
{
    $limite = min(time() + (int)ajuste('transf_horas') * 3600, strtotime($inicio_funcion) - 3 * 3600);
    return date('Y-m-d H:i:s', $limite);
}

/** Guarda el archivo subido. Devuelve el nombre o un mensaje de error. */
function guardar_comprobante(array $orden, array $archivo): array
{
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [null, 'No llegó ningún archivo. Probá de nuevo.'];
    if ($archivo['size'] > COMPROBANTE_MAX) return [null, 'El archivo pesa más de 10 MB.'];
    // El tipo se mira en el contenido, no en el nombre ni en lo que diga el navegador.
    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']) ?: '';
    $ext = COMPROBANTE_TIPOS[$tipo] ?? null;
    if (!$ext) return [null, 'Subí una foto (JPG, PNG, WebP o HEIC) o un PDF.'];
    $nombre = $orden['codigo'] . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($archivo['tmp_name'], dir_comprobantes() . '/' . $nombre)) return [null, 'No se pudo guardar el archivo.'];
    return [$nombre, null];
}

function tipo_comprobante(string $nombre): string
{
    return array_search(pathinfo($nombre, PATHINFO_EXTENSION), COMPROBANTE_TIPOS, true) ?: 'application/octet-stream';
}

/** Cancela una orden pendiente: libera los lugares y deja el motivo. */
function cancelar_orden(int $orden_id, string $motivo, ?int $quien = null): void
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $st = q("UPDATE ordenes SET estado = 'cancelada', nota = ?, verificada_por = ?, verificada_en = ? WHERE id = ? AND estado = 'pendiente'",
            [$motivo, $quien, ahora(), $orden_id]);
        if ($st->rowCount()) q("UPDATE entradas SET estado = 'anulada' WHERE orden_id = ? AND estado = 'valida'", [$orden_id]);
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $ex;
    }
}

/**
 * Reservas por transferencia vencidas SIN comprobante: se cancelan y se avisa.
 * Las que tienen comprobante no vencen solas: las decide una persona.
 * Corre al final de cada pedido y en la tarea programada; es una consulta corta.
 */
function vencer_reservas(): int
{
    // No vence si Mercado Pago ya informó un pago por ese importe que todavía
    // no se concilió: puede ser justo el suyo.
    $vencidas = filas("SELECT id FROM ordenes o WHERE estado = 'pendiente' AND medio_pago = 'transferencia'
                       AND comprobante IS NULL AND vence IS NOT NULL AND vence < ?
                       AND NOT EXISTS (SELECT 1 FROM pagos_mp m WHERE m.orden_id IS NULL AND m.descartado = 0
                                       AND m.importe_cent IN (o.importe_cent, o.total * 100))", [ahora()]);
    foreach ($vencidas as $o) {
        cancelar_orden((int)$o['id'], 'Venció el plazo para enviar el comprobante.');
        correo_transferencia_cancelada((int)$o['id']);
    }
    return count($vencidas);
}

/** $via: manual (alguien miró la cuenta), mp-automatica o mp-sugerida (con el pago de Mercado Pago). */
function aprobar_transferencia(int $orden_id, ?int $quien, string $via = 'manual', ?string $mp_pago_id = null): bool
{
    $st = q("UPDATE ordenes SET estado = 'pagada', verificada_por = ?, verificada_en = ?, nota = NULL, conciliacion = ?, mp_pago_id = ? WHERE id = ? AND estado = 'pendiente'",
        [$quien, ahora(), $via, $mp_pago_id, $orden_id]);
    if (!$st->rowCount()) return false;
    correo_compra($orden_id);
    return true;
}

// ── Correos ────────────────────────────────────────────────────────────────

function bloque_datos_transferencia(array $o): string
{
    $fila = fn($k, $v) => $v === '' ? '' : '<tr><td style="padding:6px 0;color:' . MAIL_TENUE . ';font-size:13px;width:110px">' . $k . '</td><td style="padding:6px 0;font-weight:600;font-family:Menlo,Consolas,monospace">' . e($v) . '</td></tr>';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . MAIL_FONDO . ';border-radius:12px;margin:0 0 18px"><tr><td style="padding:14px 18px">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px">'
      . $fila('Monto', pesos_cent(importe_orden($o))) . $fila('Alias', ajuste('transf_alias')) . $fila('CBU / CVU', ajuste('transf_cbu'))
      . $fila('Titular', ajuste('transf_titular')) . $fila('CUIT', ajuste('transf_cuit')) . $fila('Banco', ajuste('transf_banco'))
      . $fila('Concepto', $o['codigo']) . '</table></td></tr></table>';
}

function datos_orden_correo(int $orden_id): array
{
    return fila('SELECT o.*, u.nombre, u.email, e.titulo, e.slug, f.inicio, f.lugar FROM ordenes o JOIN usuarios u ON u.id = o.usuario_id
                 JOIN funciones f ON f.id = o.funcion_id JOIN eventos e ON e.id = f.evento_id WHERE o.id = ?', [$orden_id]);
}

function correo_transferencia_reserva(int $orden_id): void
{
    $o = datos_orden_correo($orden_id);
    $url = URL_PUBLICA . '/orden/' . $o['codigo'];
    $cuerpo = '<p style="margin:0 0 4px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:' . MAIL_ACENTO . ';font-weight:700">Reserva por transferencia</p>
      <h1 style="font-family:' . MAIL_TITULOS . ';font-weight:400;text-transform:uppercase;font-size:30px;line-height:1.2;letter-spacing:.01em;margin:0 0 12px">' . e($o['titulo']) . '</h1>
      <p style="margin:0 0 16px;color:#3a3c41">Tus entradas quedaron reservadas hasta el <strong>' . e(fecha_corta($o['vence'])) . ' h</strong>. Para confirmarlas, transferí <strong>' . e(pesos_cent(importe_orden($o))) . '</strong> y subí el comprobante: lo cruzamos con lo que entra a la cuenta. Si podés, poné el código <strong>' . e($o['codigo']) . '</strong> en el concepto.</p>'
      . bloque_datos_transferencia($o) . mail_boton('Subir el comprobante', $url) . '
      <p style="margin:14px 0 0;font-size:13px;color:' . MAIL_TENUE . '">Si el pago ya entró, la confirmación suele ser inmediata; si hay que revisarlo a mano, puede demorar unas horas. Cuando esté, te llegan las entradas con su QR. Si el comprobante no llega a tiempo, la reserva se cancela y los lugares vuelven a la venta.</p>';
    $texto = "Reserva por transferencia: {$o['titulo']}\nTransferí " . pesos_cent(importe_orden($o)) . " a: alias " . ajuste('transf_alias') . ' / CBU ' . ajuste('transf_cbu')
        . "\nConcepto: {$o['codigo']}\nReservado hasta el " . fecha_corta($o['vence']) . " h.\nSubí el comprobante: $url";
    encolar_correo($o['email'], 'Reservaste tus entradas · falta la transferencia', correo_html($cuerpo, ['previa' => 'Transferí ' . pesos_cent(importe_orden($o)) . ' y mandanos el comprobante.']), $texto, 'transferencia');
}

function correo_comprobante_recibido(int $orden_id): void
{
    $o = datos_orden_correo($orden_id);
    // A la casilla de la plataforma: hay algo para verificar.
    $url = URL_PUBLICA . '/admin/transferencias';
    $cuerpo = '<h1 style="font-family:' . MAIL_TITULOS . ';font-weight:400;text-transform:uppercase;font-size:30px;margin:4px 0 12px">Comprobante para verificar</h1>
      <p style="margin:0 0 16px;color:#3a3c41"><strong>' . e($o['nombre']) . '</strong> subió el comprobante de la orden <strong>' . e($o['codigo']) . '</strong> por <strong>' . e(pesos((int)$o['total'])) . '</strong> (' . e($o['titulo']) . ', ' . e(fecha_corta($o['inicio'])) . ' h).</p>'
      . mail_boton('Verificar en el panel', $url);
    encolar_correo(CORREO_CONTACTO, 'Comprobante para verificar · ' . $o['codigo'] . ' · ' . pesos((int)$o['total']), correo_html($cuerpo), "Comprobante de {$o['nombre']}, orden {$o['codigo']}, " . pesos((int)$o['total']) . ".\n$url", 'aviso-admin');
}

function correo_transferencia_cancelada(int $orden_id): void
{
    $o = datos_orden_correo($orden_id);
    $cuerpo = '<h1 style="font-family:' . MAIL_TITULOS . ';font-weight:400;text-transform:uppercase;font-size:30px;margin:4px 0 12px">Tu reserva se canceló</h1>
      <p style="margin:0 0 16px;color:#3a3c41">La reserva de <strong>' . e($o['titulo']) . '</strong> (orden ' . e($o['codigo']) . ') se canceló: <strong>' . e($o['nota']) . '</strong></p>
      <p style="margin:0 0 18px;color:#3a3c41">Si ya habías transferido, respondé este correo con el comprobante y lo resolvemos.</p>'
      . mail_boton('Ver el evento', URL_PUBLICA . '/evento/' . $o['slug']);
    encolar_correo($o['email'], 'Se canceló tu reserva · ' . $o['titulo'], correo_html($cuerpo), "Tu reserva {$o['codigo']} se canceló: {$o['nota']}", 'transferencia');
}
