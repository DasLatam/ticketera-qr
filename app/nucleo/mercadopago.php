<?php
// Conciliación automática de transferencias con Mercado Pago (2026-10-03).
//
// No es cobrar con Mercado Pago (eso sigue apagado): es usar la API de la
// cuenta donde la gente transfiere para enterarse de qué plata entró y
// emparejarla con las reservas, cruzando cada pago con el comprobante que
// subió la persona (número de operación, CUIT, nombre) y sus datos de cuenta.
// Se descartó pedir centavos únicos por reserva: es incómodo para quien paga.
// Dos vías para enterarse de los pagos, para no depender de una sola:
//   · el aviso (webhook, tópico «payment») que Mercado Pago manda a
//     /api/mp/aviso, con la firma validada;
//   · una consulta de los pagos recibidos de los últimos días
//     (/v1/payments/search), cada 15 minutos con la tarea programada y con
//     el botón «Buscar pagos ahora».
//
// OJO, sin confirmar todavía con la cuenta real: la documentación de webhooks
// no lista las transferencias por alias/CVU. En la práctica se informan como
// pagos (account_money / bank_transfer), y así lo usa más gente, pero hay que
// probarlo con una transferencia real antes de confiar (pendiente).
//
// Todo pago informado se guarda en `pagos_mp`, se concilie o no: «entró plata
// y no sabemos de quién» también tiene que verse.

const MP_API = 'https://api.mercadopago.com';

function mp_archivo(): string
{
    return DIR_DATOS . '/mercadopago.json';
}

/** Credenciales: fuera de la base y fuera de public_html. */
function mp_config(): array
{
    $c = is_file(mp_archivo()) ? (json_decode((string)file_get_contents(mp_archivo()), true) ?: []) : [];
    return $c + ['access_token' => '', 'webhook_secret' => '', 'user_id' => '', 'nickname' => '', 'api_base' => '', 'prueba' => false];
}

function mp_guardar_config(array $c): void
{
    file_put_contents(mp_archivo(), json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    @chmod(mp_archivo(), 0600);
}

function mp_conectado(): bool
{
    // Conectada = el token se probó contra Mercado Pago (mp_probar dejó el
    // user_id). Un token guardado que no funciona no cuenta.
    $c = mp_config();
    return $c['access_token'] !== '' && $c['user_id'] !== '';
}

/** Llamada a la API. En la copia local se puede apuntar a un Mercado Pago simulado. */
function mp_api(string $ruta, array $query = []): array
{
    $c = mp_config();
    $base = (!ES_PROD && $c['api_base'] !== '') ? rtrim($c['api_base'], '/') : MP_API;
    $ch = curl_init($base . $ruta . ($query ? '?' . http_build_query($query) : ''));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $c['access_token'], 'Accept: application/json'],
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    if ($cuerpo === false || $codigo >= 400) {
        throw new RuntimeException('Mercado Pago respondió ' . ($codigo ?: 'sin conexión') . ($error ? " ($error)" : '') . ': ' . mb_substr((string)$cuerpo, 0, 200));
    }
    return json_decode((string)$cuerpo, true) ?: [];
}

/** ¿Las credenciales funcionan? Devuelve el usuario de la cuenta. */
function mp_probar(): array
{
    $u = mp_api('/users/me');
    $c = mp_config();
    $c['user_id'] = (string)($u['id'] ?? '');
    $c['nickname'] = (string)($u['nickname'] ?? ($u['email'] ?? ''));
    // En el panel nuevo de Mercado Pago las credenciales de PRUEBA también
    // empiezan con APP_USR-: son de un usuario de prueba (TESTUSER…) que
    // nunca recibe transferencias reales. Se distingue por la etiqueta.
    $c['prueba'] = in_array('test_user', (array)($u['tags'] ?? []), true);
    mp_guardar_config($c);
    return $u;
}

/**
 * Firma del aviso: cabecera x-signature «ts=…,v1=…» y x-request-id. Se firma
 * con HMAC-SHA256 y la clave secreta de la aplicación el manifiesto
 * «id:<data.id>;request-id:<x-request-id>;ts:<ts>;». Sin clave cargada no se
 * acepta ningún aviso: alguien podría inventar pagos.
 */
function mp_firma_valida(string $data_id): bool
{
    $secreto = mp_config()['webhook_secret'];
    $firma = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $req = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    if ($secreto === '' || $firma === '') return false;
    $partes = [];
    foreach (explode(',', $firma) as $p) {
        [$k, $v] = array_map('trim', explode('=', $p, 2) + [1 => '']);
        $partes[$k] = $v;
    }
    if (empty($partes['ts']) || empty($partes['v1'])) return false;
    $id = ctype_alnum($data_id) ? strtolower($data_id) : $data_id;
    $manifiesto = 'id:' . $id . ';' . ($req !== '' ? 'request-id:' . $req . ';' : '') . 'ts:' . $partes['ts'] . ';';
    return hash_equals(hash_hmac('sha256', $manifiesto, $secreto), $partes['v1']);
}

/**
 * ¿Es plata que ENTRA a nuestra cuenta desde otra persona? La búsqueda de
 * Mercado Pago trae todo lo que pasa por la cuenta, en las dos direcciones:
 * compras con tarjeta, transferencias que mandamos, movimientos entre cuentas
 * propias (partition_transfer). Visto con una cuenta real: el
 * cobrador puede venir en collector_id o en collector.id según el pago.
 */
function mp_es_ingreso(array $p): bool
{
    $yo = mp_config()['user_id'];
    $cobra = (string)($p['collector_id'] ?? $p['collector']['id'] ?? '');
    if ($yo === '' || $cobra !== $yo) return false;         // cobró otro: lo pagamos nosotros
    // Ojo: NO descartar si payer.id es la propia cuenta. Una transferencia
    // desde un banco al CVU llega como account_fund con payer = collector
    // (visto con una transferencia real de prueba); quien transfirió de
    // verdad está en point_of_interaction.transaction_data.bank_info.payer.
    return ($p['operation_type'] ?? '') !== 'partition_transfer'; // movimiento entre cuentas propias
}

/** Guarda (o actualiza) un pago informado. Sólo plata que ENTRA a nuestra cuenta. */
function mp_guardar_pago(array $p): ?string
{
    if (!mp_es_ingreso($p)) return null;
    if (!isset($p['id'], $p['transaction_amount'])) return null;
    $fecha = $p['date_approved'] ?? $p['date_created'] ?? null;
    $fecha = $fecha ? date('Y-m-d H:i:s', strtotime($fecha)) : ahora();
    // Quien transfirió. En una transferencia desde otro banco, Mercado Pago
    // pone como payer a NUESTRA cuenta (mail y CUIT del dueño) y deja vacío
    // bank_info.payer (visto con una transferencia real de prueba):
    // esos datos nunca son del comprador. Sólo se usan los del payer si es
    // otra cuenta (una transferencia entre cuentas de Mercado Pago).
    $banco = $p['point_of_interaction']['transaction_data']['bank_info']['payer'] ?? [];
    $doc_banco = is_array($banco['identification'] ?? null) ? ($banco['identification']['number'] ?? '') : '';
    $propio = (string)($p['payer']['id'] ?? '') === mp_config()['user_id'];
    $pagador = (string)($banco['long_name'] ?? '');
    $doc = (string)$doc_banco;
    if (!$propio) {
        $pagador = $pagador ?: trim(($p['payer']['first_name'] ?? '') . ' ' . ($p['payer']['last_name'] ?? '')) ?: (string)($p['payer']['email'] ?? '');
        $doc = $doc ?: (string)($p['payer']['identification']['number'] ?? '');
    }
    q('INSERT INTO pagos_mp (id, fecha, importe_cent, estado, tipo, pagador, pagador_doc, descripcion, crudo, recibido) VALUES (?,?,?,?,?,?,?,?,?,?)
       ON CONFLICT (id) DO UPDATE SET estado = excluded.estado, fecha = excluded.fecha, crudo = excluded.crudo, pagador = excluded.pagador, pagador_doc = excluded.pagador_doc',
        [(string)$p['id'], $fecha, (int)round((float)$p['transaction_amount'] * 100), (string)($p['status'] ?? ''),
         trim(($p['payment_type_id'] ?? '') . ' / ' . ($p['operation_type'] ?? ''), ' /'), mb_substr($pagador, 0, 120),
         $doc, mb_substr((string)($p['description'] ?? ''), 0, 300),
         json_encode($p, JSON_UNESCAPED_UNICODE), ahora()]);
    return (string)$p['id'];
}

/** Trae los pagos aprobados de los últimos días y los guarda. */
function mp_sincronizar(int $dias = 3): array
{
    $guardados = 0;
    $desde = gmdate('Y-m-d\TH:i:s.000\Z', time() - $dias * 86400);
    $hasta = gmdate('Y-m-d\TH:i:s.000\Z', time() + 3600);
    for ($offset = 0; $offset < 1000; $offset += 100) {
        $r = mp_api('/v1/payments/search', ['sort' => 'date_created', 'criteria' => 'desc', 'range' => 'date_created',
            'begin_date' => $desde, 'end_date' => $hasta, 'status' => 'approved', 'limit' => 100, 'offset' => $offset]);
        foreach ($r['results'] ?? [] as $p) if (mp_guardar_pago($p)) $guardados++;
        if (count($r['results'] ?? []) < 100) break;
    }
    // Los que entraron antes de afinar el filtro (y no se usaron) se borran.
    foreach (filas('SELECT id, crudo FROM pagos_mp WHERE orden_id IS NULL') as $v) {
        if (!mp_es_ingreso(json_decode((string)$v['crudo'], true) ?: [])) q('DELETE FROM pagos_mp WHERE id = ?', [$v['id']]);
    }
    guardar_ajuste('mp_ultima_sync', ahora());
    guardar_ajuste('mp_error', ''); // salió bien: el error anterior ya no vale
    $conc = conciliar_pagos();
    return ['leidos' => $guardados] + $conc;
}

/**
 * Lo que sirve del texto leído de un comprobante para cruzarlo con un pago:
 * los identificadores (números de operación y similares) y los CUIT/CUIL. El
 * texto lo lee el navegador (del comprador al subirlo o de quien verifica),
 * así que acá se vuelve a extraer todo: no se confía en lo que diga el JS.
 */
function datos_comprobante(?string $texto): array
{
    $t = mb_strtoupper((string)$texto);
    $ids = [];
    // Corridas alfanuméricas («A1B2C3D4E5»), códigos con guiones o puntos
    // («ZX9K-44AB-7731») y números escritos en grupos («1234 5678 9012»):
    // se comparan sin separadores. Letras separadas por espacios no se unen:
    // serían palabras.
    preg_match_all('/[A-Z0-9]+(?:[.\-][A-Z0-9]+)*/', $t, $m);
    foreach ($m[0] as $x) { $x = preg_replace('/[^A-Z0-9]/', '', $x); if (strlen($x) >= 6) $ids[$x] = true; }
    preg_match_all('/\d+(?:[ .\-]\d+)+/', $t, $m);
    foreach ($m[0] as $x) { $d = preg_replace('/\D/', '', $x); if (strlen($d) >= 6) $ids[$d] = true; }
    preg_match_all('/\b(20|23|24|27|30|33|34)[ \-]?(\d{8})[ \-]?(\d)\b/', $t, $m, PREG_SET_ORDER);
    $nuestro = preg_replace('/\D/', '', ajuste('transf_cuit'));
    $cuits = [];
    foreach ($m as $c) { $n = $c[1] . $c[2] . $c[3]; if ($n !== $nuestro) $cuits[$n] = true; }
    return ['ids' => $ids, 'cuits' => array_keys($cuits)];
}

/**
 * Los identificadores con que puede figurar este pago en un comprobante: el
 * número de operación de Mercado Pago y, si vino de un banco, los de la
 * transferencia (no está documentado en qué campo vienen, así que se toman
 * todos los que se llamen *transaction_id*, *transfer_id*, *reference*, *e2e*…).
 * Nunca los de la cuenta (collector, payer): se repetirían en otros pagos.
 */
function ids_pago(array $p): array
{
    $ids = [(string)($p['id'] ?? '') => true];
    $recorrer = function ($v, string $clave) use (&$recorrer, &$ids) {
        if (is_array($v)) {
            foreach ($v as $k => $w) if (!in_array((string)$k, ['payer', 'collector', 'collector_id', 'payer_id', 'user_id'], true)) $recorrer($w, (string)$k);
            return;
        }
        if (!is_scalar($v) || !preg_match('/transaction_id|transfer_id|reference|e2e|end_to_end|operation_number|trace|movement_id/i', $clave)) return;
        $x = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$v));
        if (strlen($x) >= 8) $ids[$x] = true;
    };
    $recorrer($p, '');
    unset($ids['']);
    return array_keys($ids);
}

/**
 * ¿Algún identificador del pago figura en el comprobante? Los números puros
 * (número de pago, bank_transfer_id) tienen que coincidir EXACTO: los de
 * Mercado Pago son casi correlativos y un dígito de diferencia es otro pago.
 * Los códigos largos con letras (transaction_id / e2e_id de la transferencia,
 * 20+ caracteres al azar) se aceptan con hasta 2 errores de lectura: el OCR
 * confunde 4/A, 0/O, 1/I y mete o pierde algún carácter (visto con una
 * captura del Banco Ciudad, 2026-10-03). También si la lectura cortó el final.
 */
function id_en_comprobante(array $ids_pago, array $leidos): bool
{
    $norm = fn(string $x) => strtr($x, ['O' => '0', 'I' => '1', 'L' => '1']);
    foreach ($ids_pago as $id) {
        $id = (string)$id; // PHP vuelve número las claves numéricas
        if (isset($leidos[$id])) return true;
        if (ctype_digit($id) || strlen($id) < 16) continue;
        $n = $norm($id);
        foreach (array_keys($leidos) as $t) {
            $t = (string)$t;
            if (strlen($t) < 12 || abs(strlen($t) - strlen($id)) > 4) continue;
            $m = $norm($t);
            // Si la lectura cortó el final, se compara contra el principio del
            // id, probando largos alrededor del leído (puede haber metido o
            // perdido un carácter antes del corte).
            for ($l = strlen($m) - 2; $l <= strlen($m) + 2; $l++) {
                if ($l > strlen($n) || $l < 12) continue;
                if (levenshtein($m, $l === strlen($n) ? $n : substr($n, 0, $l)) <= 2) return true;
            }
        }
    }
    return false;
}

/** ¿El nombre de quien transfirió se parece al de quien compró? Dos palabras en común (o una, si es la única). */
function mismo_nombre(string $a, string $b): bool
{
    $partes = fn($s) => array_values(array_filter(preg_split('/[^a-z]+/', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s)), fn($w) => strlen($w) >= 3));
    $pa = $partes($a); $pb = $partes($b);
    if (!$pa || !$pb) return false;
    $comunes = count(array_intersect($pa, $pb));
    return $comunes >= 2 || ($comunes === 1 && min(count($pa), count($pb)) === 1);
}

/**
 * El conciliador: cruza cada pago aprobado todavía sin orden con las reservas
 * pendientes del MISMO importe, mirando lo que dice el comprobante que subió
 * cada una y los datos de la persona. Pruebas, de más a menos fuertes:
 *  · el número de operación del pago aparece en el comprobante;
 *  · el código de la orden aparece en el concepto del pago;
 *  · el documento de quien transfirió es el DNI de la cuenta que compró, o
 *    figura en el comprobante y además coincide el nombre;
 *  · sólo el nombre, o sólo el importe con una única reserva posible.
 * Con una prueba fuerte y una sola reserva, se confirma sola (si el ajuste lo
 * permite); con las débiles queda sugerida para que una persona la confirme.
 * Corre cuando llega un pago, cuando se lee un comprobante y en la tarea
 * programada. Nunca usa un pago para dos órdenes.
 */
function conciliar_pagos(): array
{
    $auto = 0;
    $sugeridas = 0;
    $pagos = filas("SELECT * FROM pagos_mp WHERE orden_id IS NULL AND descartado = 0 AND estado = 'approved' ORDER BY fecha");
    foreach ($pagos as $p) {
        $imp = (int)$p['importe_cent'];
        $crudo = json_decode((string)$p['crudo'], true) ?: [];
        $ids = ids_pago($crudo);
        $doc = preg_replace('/\D/', '', (string)$p['pagador_doc']);
        // Ojo: PDO pasa los valores como texto y SQLite sólo los convierte a
        // número contra una COLUMNA; contra o.total * 100 no coinciden nunca.
        $todas = filas("SELECT o.*, u.dni, u.nombre FROM ordenes o JOIN usuarios u ON u.id = o.usuario_id
            WHERE o.estado = 'pendiente' AND o.medio_pago = 'transferencia'
              AND COALESCE(o.importe_cent, o.total * 100) = CAST(? AS INTEGER)
              AND NOT EXISTS (SELECT 1 FROM pagos_mp m WHERE m.orden_id = o.id)", [$imp]);
        // Para las pruebas débiles, la reserva tiene que existir antes que el
        // pago (10 min de margen por relojes): si no, a una reserva nueva le
        // tocaría cualquier ingreso viejo del mismo importe. Con el número de
        // operación en el comprobante no hace falta: el comprobante prueba
        // cuál es el pago (en una prueba se reservó a las 22:51 y se subió el de una
        // transferencia de las 22:34; también pasa si se le venció la reserva
        // y vuelve a reservar con el mismo comprobante).
        $limite = date('Y-m-d H:i:s', strtotime($p['fecha']) + 600);
        $candidatas = array_values(array_filter($todas, fn($o) => $o['creado'] <= $limite));

        $fuertes = [];
        $debiles = [];
        foreach ($todas as $o) {
            if (id_en_comprobante($ids, datos_comprobante($o['ocr'])['ids'])) $fuertes[] = [$o, 'el número de operación figura en el comprobante'];
        }
        foreach ($fuertes ? [] : $candidatas as $o) {
            $c = datos_comprobante($o['ocr']);
            $dni = preg_replace('/\D/', '', (string)$o['dni']);
            $nombre = $p['pagador'] !== '' && mismo_nombre($p['pagador'], $o['nombre']);
            $motivo = null;
            // (El número de operación ya se miró arriba, en todas.)
            if ($o['codigo'] !== '' && stripos((string)$p['descripcion'], $o['codigo']) !== false) $motivo = 'el código de la orden figura en el concepto';
            elseif ($doc !== '' && $dni !== '' && str_contains($doc, $dni)) $motivo = 'transfirió alguien con el DNI de quien compró';
            elseif ($doc !== '' && $nombre && array_filter($c['cuits'], fn($x) => str_contains($x, $doc) || str_contains($doc, $x))) $motivo = 'el CUIT y el nombre del comprobante son los de quien transfirió';
            if ($motivo) { $fuertes[] = [$o, $motivo]; continue; }
            if ($nombre) $debiles[] = [$o, 'el nombre de quien transfirió coincide con el de quien compró'];
        }

        if (count($fuertes) === 1) {
            [$orden, $motivo] = $fuertes[0];
            $seguro = true;
        } elseif (count($fuertes) > 1) {
            [$orden, $motivo, $seguro] = [null, count($fuertes) . ' reservas del mismo importe con pruebas: revisar a mano', false];
        } elseif (count($debiles) === 1) {
            [$orden, $motivo] = $debiles[0];
            $seguro = false;
        } elseif (count($candidatas) === 1) {
            [$orden, $motivo, $seguro] = [$candidatas[0], 'es la única reserva pendiente por ese importe', false];
        } else {
            $orden = null;
            $seguro = false;
            $motivo = $candidatas ? count($candidatas) . ' reservas por ese importe y nada que las distinga (ni el comprobante, ni el DNI, ni el nombre)'
                : ($todas ? count($todas) . ' reserva(s) por ese importe, pero hechas después del pago y sin su número de operación en el comprobante' : 'ninguna reserva pendiente por ese importe');
        }

        if ($orden && $seguro && ajuste('mp_auto_confirmar') === '1') {
            $st = q('UPDATE pagos_mp SET orden_id = ?, resultado = ? WHERE id = ? AND orden_id IS NULL', [$orden['id'], 'confirmada sola: ' . $motivo, $p['id']]);
            if ($st->rowCount() && aprobar_transferencia((int)$orden['id'], null, 'mp-automatica', $p['id'])) $auto++;
        } elseif ($orden) {
            q('UPDATE pagos_mp SET resultado = ? WHERE id = ?', [json_encode(['sugerida' => (int)$orden['id'], 'motivo' => $motivo], JSON_UNESCAPED_UNICODE), $p['id']]);
            $sugeridas++;
        } else {
            q('UPDATE pagos_mp SET resultado = ? WHERE id = ?', [$motivo, $p['id']]);
        }
    }
    return ['automaticas' => $auto, 'sugeridas' => $sugeridas];
}

/** Confirma una orden con un pago informado (desde la bandeja). */
function conciliar_a_mano(string $pago_id, int $orden_id, int $quien): bool
{
    $p = fila('SELECT * FROM pagos_mp WHERE id = ? AND orden_id IS NULL', [$pago_id]);
    $o = fila("SELECT * FROM ordenes WHERE id = ? AND estado = 'pendiente'", [$orden_id]);
    if (!$p || !$o) return false;
    q('UPDATE pagos_mp SET orden_id = ?, resultado = ? WHERE id = ?', [$orden_id, 'confirmada a mano', $pago_id]);
    return aprobar_transferencia($orden_id, $quien, 'mp-sugerida', $pago_id);
}

/** El aviso de Mercado Pago. Responde rápido: si tarda, Mercado Pago reintenta. */
function api_mp_aviso(): void
{
    $cuerpo = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $tipo = $_GET['type'] ?? $_GET['topic'] ?? $cuerpo['type'] ?? $cuerpo['topic'] ?? '';
    $id = (string)($_GET['data_id'] ?? $_GET['data.id'] ?? $cuerpo['data']['id'] ?? $_GET['id'] ?? '');
    // El último aviso queda anotado para «Configurar cobro»: al conectar la
    // cuenta, es la forma de saber si Mercado Pago llega y si la firma da.
    $anotar = fn(string $r) => guardar_ajuste('mp_ultimo_aviso', ahora() . '|' . $r);
    // La firma se revisa en TODO aviso, aunque no sea de pagos: el simulador
    // del panel de Mercado Pago a veces sólo manda «mp-connect», y así igual
    // sirve para saber si la clave secreta está bien.
    if ($tipo !== 'payment' || $id === '') {
        $t = '«' . mb_substr((string)$tipo, 0, 30) . '»';
        if (mp_conectado() && mp_firma_valida($id)) $anotar("firma ok · aviso $t (no es de pagos: no hay nada que hacer)");
        else $anotar(mp_conectado() ? "la firma no coincide con la clave secreta · aviso $t" : "aviso $t, pero falta conectar la cuenta");
        json_resp(['ok' => true, 'ignorado' => $tipo]);
    }
    if (!mp_conectado() || !mp_firma_valida($id)) {
        error_log('[app mp] aviso con firma inválida o sin credenciales, id ' . $id);
        $anotar(mp_conectado() ? 'rechazado: la firma no coincide con la clave secreta' : 'rechazado: falta conectar la cuenta');
        json_resp(['ok' => false], 401);
    }
    try {
        $guardado = mp_guardar_pago(mp_api('/v1/payments/' . rawurlencode($id)));
        if ($guardado) conciliar_pagos();
        $anotar($guardado ? 'ok: pago ' . $id . ' recibido' : 'ok: pago ' . $id . ' (no es un ingreso a esta cuenta)');
    } catch (Throwable $ex) {
        error_log('[app mp] ' . $ex->getMessage());
        // 200 igual: el pago se va a levantar en la próxima consulta. La
        // «simulación» del panel de Mercado Pago cae acá: manda un id que no existe.
        $anotar('firma ok; el pago ' . $id . ' no se pudo leer (si fue la simulación, es lo esperado)');
    }
    json_resp(['ok' => true]);
}
