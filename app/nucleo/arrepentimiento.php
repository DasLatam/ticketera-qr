<?php
// Botón de arrepentimiento (pedido de los clientes, 2026-10-04).
//
// Regla que pasaron: 10 días corridos desde que se reciben las entradas o el
// comprobante (lo que pase primero) y, como mínimo, 24 h antes de la función.
// El link tiene que estar a la vista y no pedir cuenta: quien no ingresó usa
// el código de la orden y el correo de la compra.
//
// Lo que cumple la regla se resuelve en el momento (se anula la compra y se
// liberan los lugares); lo que no, NO se rechaza solo: queda «a revisar» y lo
// decide una persona. Toda solicitud recibe un código de trámite.

const ARREPENTIMIENTO_DIAS = 10;
const ARREPENTIMIENTO_HORAS_ANTES = 24;

const ARREPENTIMIENTO_ESTADOS = [
    'a_revisar' => ['A revisar', 'borrador'],
    'aceptado' => ['Falta reintegrar', 'cancelado'],
    'resuelto' => ['Resuelto', 'publicado'],
    'rechazado' => ['Rechazado', 'cancelado'],
];

/** Orden con lo que hace falta para decidir. */
function orden_para_arrepentimiento(string $codigo): ?array
{
    return fila("SELECT o.*, u.email, u.nombre, e.titulo, f.inicio, f.estado AS funcion_estado, e.estado AS evento_estado,
                   (SELECT COUNT(*) FROM entradas x WHERE x.orden_id = o.id AND x.estado = 'usada') AS usadas
                 FROM ordenes o JOIN usuarios u ON u.id = o.usuario_id JOIN funciones f ON f.id = o.funcion_id
                 JOIN eventos e ON e.id = f.evento_id WHERE o.codigo = ?", [strtoupper(trim($codigo))]);
}

/** Desde cuándo corren los 10 días: cuando recibió las entradas (orden confirmada). */
function arrepentimiento_desde(array $o): string
{
    return $o['estado'] === 'pagada' ? ($o['verificada_en'] ?: $o['creado']) : $o['creado'];
}

function arrepentimiento_limite(array $o): string
{
    $por_dias = strtotime(arrepentimiento_desde($o)) + ARREPENTIMIENTO_DIAS * 86400;
    $por_funcion = strtotime($o['inicio']) - ARREPENTIMIENTO_HORAS_ANTES * 3600;
    return date('Y-m-d H:i:s', $o['estado'] === 'pagada' ? min($por_dias, $por_funcion) : $por_funcion);
}

/** Por qué no entra en la regla, o null si entra. */
function arrepentimiento_impedimento(array $o): ?string
{
    if ($o['evento_estado'] === 'cancelado' || $o['funcion_estado'] === 'cancelada') return null; // se devuelve igual
    if ((int)$o['usadas']) return 'Alguna entrada de la compra ya se usó en la puerta.';
    if (strtotime($o['inicio']) - time() < ARREPENTIMIENTO_HORAS_ANTES * 3600)
        return 'Faltan menos de ' . ARREPENTIMIENTO_HORAS_ANTES . ' horas para la función.';
    if ($o['estado'] === 'pagada' && time() > strtotime(arrepentimiento_desde($o)) + ARREPENTIMIENTO_DIAS * 86400)
        return 'Pasaron más de ' . ARREPENTIMIENTO_DIAS . ' días corridos desde que recibiste las entradas.';
    return null;
}

/** Hay plata que devolver (o que mirar si entró). */
function arrepentimiento_con_dinero(array $o): bool
{
    return $o['estado'] === 'pagada' ? importe_total_orden($o) > 0 : $o['comprobante'] !== null;
}

function importe_total_orden(array $o): int
{
    return (int)$o['total'] + (int)$o['cargo'];
}

/** Anula la compra y libera los lugares. Devuelve false si ya estaba cancelada. */
function anular_por_arrepentimiento(int $orden_id, string $tramite): bool
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $st = q("UPDATE ordenes SET estado = 'cancelada', nota = ? WHERE id = ? AND estado IN ('pendiente','pagada')",
            ["Arrepentimiento (trámite {$tramite}).", $orden_id]);
        $ok = (bool)$st->rowCount();
        if ($ok) q("UPDATE entradas SET estado = 'anulada' WHERE orden_id = ? AND estado = 'valida'", [$orden_id]);
        $pdo->exec('COMMIT');
        return $ok;
    } catch (Throwable $ex) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $ex;
    }
}

// ── Público ────────────────────────────────────────────────────────────────

function pag_arrepentimiento(): void
{
    $u = usuario();
    $hecho = $_SESSION['arrepentimiento_hecho'] ?? null;
    unset($_SESSION['arrepentimiento_hecho']);
    $tramite = $hecho ? fila('SELECT a.*, o.codigo AS orden FROM arrepentimientos a JOIN ordenes o ON o.id = a.orden_id WHERE a.codigo = ?', [$hecho]) : null;
    // Con sesión, se elige la compra de una lista (las que todavía no pasaron).
    $mias = $u ? filas("SELECT o.codigo, o.estado, o.total, o.cargo, e.titulo, f.inicio FROM ordenes o JOIN funciones f ON f.id = o.funcion_id
                        JOIN eventos e ON e.id = f.evento_id WHERE o.usuario_id = ? AND o.estado IN ('pendiente','pagada') AND f.inicio > ?
                        ORDER BY f.inicio", [$u['id'], ahora()]) : [];
    $d = $_SESSION['arrepentimiento_form'] ?? ['orden' => strtoupper((string)($_GET['orden'] ?? '')), 'email' => $u['email'] ?? ''];
    unset($_SESSION['arrepentimiento_form']);
    vista('arrepentimiento', compact('u', 'tramite', 'mias', 'd'), ['titulo' => 'Botón de arrepentimiento', 'canonical' => URL_PUBLICA . '/arrepentimiento']);
}

function acc_arrepentimiento(): void
{
    $u = usuario();
    $d = [
        'orden' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['orden'] ?? ''))),
        'email' => mb_strtolower(trim((string)($_POST['email'] ?? ''))),
        'nombre' => trim((string)($_POST['nombre'] ?? '')),
        'reintegro' => trim((string)($_POST['reintegro'] ?? '')),
        'comentario' => trim((string)($_POST['comentario'] ?? '')),
    ];
    $volver = function (array $err) use ($d) {
        foreach ($err as $x) flash($x, 'error');
        $_SESSION['arrepentimiento_form'] = $d;
        ir('/arrepentimiento');
    };
    // Con sesión no hace falta el captcha ni el correo: la compra tiene que ser suya.
    if (!$u && !captcha_valido()) $volver(['Tildá «No soy un robot» antes de enviar.']);
    $o = $d['orden'] !== '' ? orden_para_arrepentimiento($d['orden']) : null;
    $email = $u ? $u['email'] : $d['email'];
    if (!$o || !hash_equals(mb_strtolower($o['email']), $email)) {
        $volver([$u ? 'Elegí una de tus compras.' : 'No encontramos una compra con ese código y ese correo. El código está en el correo de la compra y en la página de la orden.']);
    }
    $previo = valor("SELECT codigo FROM arrepentimientos WHERE orden_id = ? AND estado <> 'rechazado'", [$o['id']]);
    if ($previo) $volver(["Esta compra ya tiene un pedido de arrepentimiento: trámite {$previo}."]);
    if ($o['estado'] === 'cancelada') $volver(['Esa compra ya está cancelada: no hay nada que anular.']);
    $con_dinero = arrepentimiento_con_dinero($o);
    if ($con_dinero && mb_strlen($d['reintegro']) < 6) $volver(['Esa compra ya tiene un pago o un comprobante: escribí el alias o el CBU/CVU donde querés recibir el reintegro.']);

    $tramite = 'A' . codigo_aleatorio(7);
    $impedimento = arrepentimiento_impedimento($o);
    $importe = $o['estado'] === 'pagada' ? importe_total_orden($o) : 0;
    if ($impedimento === null) {
        anular_por_arrepentimiento((int)$o['id'], $tramite);
        $estado = $con_dinero ? 'aceptado' : 'resuelto';
    } else {
        $estado = 'a_revisar';
    }
    q('INSERT INTO arrepentimientos (codigo, orden_id, nombre, email, reintegro, comentario, estado, importe, detalle, ip, creado)
       VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$tramite, $o['id'], $d['nombre'] ?: $o['nombre'], $o['email'], $d['reintegro'] ?: null, mb_substr($d['comentario'], 0, 1000) ?: null,
         $estado, $importe, $impedimento, $_SERVER['REMOTE_ADDR'] ?? null, ahora()]);
    correo_arrepentimiento($tramite);
    correo_arrepentimiento_admin($tramite);
    $_SESSION['arrepentimiento_hecho'] = $tramite;
    ir('/arrepentimiento');
}

// ── Panel ──────────────────────────────────────────────────────────────────

function adm_arrepentimientos(): void
{
    requerir_rol('admin');
    $lista = filas("SELECT a.*, o.codigo AS orden, o.medio_pago, o.comprobante, o.estado AS orden_estado, e.titulo, f.inicio, r.nombre AS resolvio
                    FROM arrepentimientos a JOIN ordenes o ON o.id = a.orden_id JOIN funciones f ON f.id = o.funcion_id
                    JOIN eventos e ON e.id = f.evento_id LEFT JOIN usuarios r ON r.id = a.resuelto_por
                    ORDER BY CASE a.estado WHEN 'a_revisar' THEN 0 WHEN 'aceptado' THEN 1 ELSE 2 END, a.creado DESC LIMIT 300");
    vista('admin/arrepentimientos', compact('lista'), ['titulo' => 'Arrepentimientos', 'noindex' => true]);
}

/** a_revisar → se acepta igual (anula la compra) o se rechaza con motivo; aceptado → reintegrado. */
function adm_arrepentimiento_resolver(string $id, string $accion): void
{
    $yo = requerir_rol('admin');
    $a = fila('SELECT * FROM arrepentimientos WHERE id = ?', [(int)$id]) ?? no_encontrado();
    $o = fila('SELECT * FROM ordenes WHERE id = ?', [$a['orden_id']]);
    $nota = trim((string)($_POST['nota'] ?? ''));
    $nuevo = null;
    if ($accion === 'aceptar' && $a['estado'] === 'a_revisar') {
        $habia_dinero = arrepentimiento_con_dinero($o);
        anular_por_arrepentimiento((int)$o['id'], $a['codigo']);
        $nuevo = $habia_dinero ? 'aceptado' : 'resuelto';
        if ($o['estado'] === 'pagada') q('UPDATE arrepentimientos SET importe = ? WHERE id = ?', [importe_total_orden($o), $a['id']]);
    } elseif ($accion === 'rechazar' && $a['estado'] === 'a_revisar') {
        if ($nota === '') { flash('Para rechazar, escribí el motivo: le llega a quien lo pidió.', 'error'); ir('/admin/arrepentimientos'); }
        $nuevo = 'rechazado';
    } elseif ($accion === 'reintegrado' && $a['estado'] === 'aceptado') {
        $nuevo = 'resuelto';
    }
    if (!$nuevo) { flash('Ese trámite ya cambió de estado.', 'error'); ir('/admin/arrepentimientos'); }
    q('UPDATE arrepentimientos SET estado = ?, nota = ?, resuelto_por = ?, resuelto_en = ? WHERE id = ?',
        [$nuevo, $nota ?: null, $yo['id'], ahora(), $a['id']]);
    correo_arrepentimiento($a['codigo'], $accion === 'reintegrado' ? 'reintegrado' : null);
    flash('Trámite ' . $a['codigo'] . ': ' . strtolower(ARREPENTIMIENTO_ESTADOS[$nuevo][0]) . '. Se le avisó por correo.');
    ir('/admin/arrepentimientos');
}

function arrepentimientos_por_atender(): int
{
    return (int)valor("SELECT COUNT(*) FROM arrepentimientos WHERE estado IN ('a_revisar','aceptado')");
}

// ── Correos ────────────────────────────────────────────────────────────────

function correo_arrepentimiento(string $tramite, ?string $momento = null): void
{
    $a = fila('SELECT a.*, o.codigo AS orden, o.estado AS orden_estado, e.titulo, f.inicio FROM arrepentimientos a JOIN ordenes o ON o.id = a.orden_id
               JOIN funciones f ON f.id = o.funcion_id JOIN eventos e ON e.id = f.evento_id WHERE a.codigo = ?', [$tramite]);
    $p = fn($t) => '<p style="margin:0 0 14px;color:#3a3c41">' . $t . '</p>';
    $compra = '<strong>' . e($a['titulo']) . '</strong> (' . e(fecha_corta($a['inicio'])) . ' h, orden ' . e($a['orden']) . ')';
    $importe = (int)$a['importe'] ? '<strong>' . e(pesos((int)$a['importe'])) . '</strong>' : 'lo que hayas transferido';
    [$titulo, $asunto, $cuerpo] = match (true) {
        $momento === 'reintegrado' => ['Te devolvimos el dinero', 'Reintegro hecho',
            $p("Hicimos el reintegro de {$importe} de tu compra de {$compra} a " . e($a['reintegro']) . '.')
            . ($a['nota'] ? $p('Detalle: ' . e($a['nota'])) : '')],
        $a['estado'] === 'a_revisar' => ['Recibimos tu pedido', 'Recibimos tu pedido de arrepentimiento',
            $p("Recibimos tu pedido de arrepentimiento de la compra de {$compra}.")
            . $p('Lo revisa una persona porque ' . e(lcfirst(rtrim($a['detalle'], '.'))) . '. Te respondemos por este medio. Mientras tanto tus entradas siguen vigentes.')],
        $a['estado'] === 'rechazado' => ['No pudimos aceptar tu pedido', 'Tu pedido de arrepentimiento',
            $p("Revisamos tu pedido de arrepentimiento de la compra de {$compra} y no lo pudimos aceptar: <strong>" . e($a['nota']) . '</strong>')
            . $p('Tus entradas siguen vigentes. Si no estás de acuerdo, respondé este correo.')],
        default => ['Anulamos tu compra', 'Anulamos tu compra',
            $p("Anulamos tu compra de {$compra}: las entradas ya no sirven y los lugares volvieron a la venta.")
            . ($a['estado'] === 'aceptado'
                ? $p("Vamos a devolverte {$importe} por transferencia a <strong>" . e($a['reintegro']) . '</strong>. Te avisamos cuando esté hecho.')
                : '')],
    };
    $cuerpo = '<p style="margin:0 0 4px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:' . MAIL_ACENTO . ';font-weight:700">Arrepentimiento · trámite ' . e($tramite) . '</p>
      <h1 style="font-family:' . MAIL_TITULOS . ';font-weight:400;text-transform:uppercase;font-size:30px;line-height:1.2;margin:0 0 12px">' . $titulo . '</h1>' . $cuerpo
      . '<p style="margin:0;font-size:13px;color:' . MAIL_TENUE . '">Guardá el código de trámite <strong>' . e($tramite) . '</strong> para cualquier consulta.</p>';
    $texto = "$titulo. Trámite $tramite, orden {$a['orden']} ({$a['titulo']}). " . strip_tags($cuerpo);
    encolar_correo($a['email'], $asunto . ' · trámite ' . $tramite, correo_html($cuerpo), $texto, 'arrepentimiento');
}

function correo_arrepentimiento_admin(string $tramite): void
{
    $a = fila('SELECT a.*, o.codigo AS orden, e.titulo FROM arrepentimientos a JOIN ordenes o ON o.id = a.orden_id
               JOIN funciones f ON f.id = o.funcion_id JOIN eventos e ON e.id = f.evento_id WHERE a.codigo = ?', [$tramite]);
    $que = ['a_revisar' => 'para revisar: ' . $a['detalle'], 'aceptado' => 'compra anulada, falta reintegrar ' . pesos((int)$a['importe']) . ' a ' . $a['reintegro'],
            'resuelto' => 'compra anulada, sin dinero que devolver'][$a['estado']] ?? $a['estado'];
    $cuerpo = '<h1 style="font-family:' . MAIL_TITULOS . ';font-weight:400;text-transform:uppercase;font-size:30px;margin:4px 0 12px">Arrepentimiento ' . e($tramite) . '</h1>
      <p style="margin:0 0 16px;color:#3a3c41"><strong>' . e($a['nombre']) . '</strong> se arrepintió de la orden <strong>' . e($a['orden']) . '</strong> (' . e($a['titulo']) . '): ' . e($que) . '.</p>'
      . mail_boton('Ver en el panel', URL_PUBLICA . '/admin/arrepentimientos');
    encolar_correo(CORREO_CONTACTO, 'Arrepentimiento ' . $tramite . ' · ' . $a['orden'], correo_html($cuerpo), "Arrepentimiento $tramite, orden {$a['orden']}: $que.", 'aviso-admin');
}
