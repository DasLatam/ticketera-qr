<?php
// Control de acceso: el rol «puerta» elige la función y escanea el QR; la
// entrada se quema en el servidor.

function pag_puerta(): void
{
    requerir_rol('puerta', 'admin');
    // Primero las que están por empezar o en curso; las terminadas hace más
    // de 12 h ya no tienen puerta.
    $funciones = filas("SELECT f.*, e.titulo, " . SQL_VENDIDAS_F . " AS vendidas,
                          (SELECT COUNT(*) FROM entradas x WHERE x.funcion_id = f.id AND x.estado = 'usada') AS ingresados
                        FROM funciones f JOIN eventos e ON e.id = f.evento_id
                        WHERE e.estado = 'publicado' AND f.estado = 'activa' AND COALESCE(f.fin, f.inicio) >= ?
                        ORDER BY f.inicio LIMIT 40", [date('Y-m-d H:i:s', time() - 12 * 3600)]);
    vista('puerta', compact('funciones'), ['titulo' => 'Puerta', 'noindex' => true]);
}

function pag_puerta_escaner(string $id): void
{
    requerir_rol('puerta', 'admin');
    $fn = fila('SELECT f.*, e.titulo FROM funciones f JOIN eventos e ON e.id = f.evento_id WHERE f.id = ?', [$id]) ?? no_encontrado();
    vista('puerta_escaner', compact('fn'), ['titulo' => 'Puerta · ' . $fn['titulo'], 'noindex' => true, 'cuerpo_clase' => 'pantalla-puerta']);
}

function resumen_puerta(int $funcion_id): array
{
    $r = fila("SELECT SUM(estado <> 'anulada') AS vendidas, SUM(estado = 'usada') AS ingresados FROM entradas WHERE funcion_id = ?", [$funcion_id]);
    return ['vendidas' => (int)$r['vendidas'], 'ingresados' => (int)$r['ingresados']];
}

function api_puerta_resumen(string $id): void
{
    requerir_rol('puerta', 'admin');
    json_resp(resumen_puerta((int)$id));
}

function api_puerta_validar(): void
{
    $u = usuario();
    if (!$u || !in_array($u['rol'], ['puerta', 'admin'], true)) json_resp(['resultado' => 'error', 'mensaje' => 'Sesión vencida: volvé a ingresar.'], 401);
    $funcion_id = (int)($_POST['funcion_id'] ?? 0);
    $leido = trim((string)($_POST['codigo'] ?? ''));
    // El QR trae «TQR:CODIGO»; tipeado a mano viene sólo el código.
    $codigo = strtoupper(preg_replace('/^TQR:/i', '', $leido));
    $codigo = preg_replace('/[^A-Z0-9]/', '', $codigo);

    $res = function (string $resultado, string $mensaje, ?array $x = null) use ($funcion_id, $leido, $u) {
        q('INSERT INTO escaneos (funcion_id, entrada_id, codigo_leido, resultado, usuario_id, creado) VALUES (?,?,?,?,?,?)',
            [$funcion_id, $x['id'] ?? null, mb_substr($leido, 0, 64), $resultado, $u['id'], ahora()]);
        json_resp([
            'resultado' => $resultado,
            'mensaje' => $mensaje,
            'titular' => $x['titular'] ?? null,
            'tipo' => $x['tipo'] ?? null,
            'codigo' => $x['codigo'] ?? null,
            'usada_en' => !empty($x['usada_en']) ? date('H:i', strtotime($x['usada_en'])) . ' h' : null,
        ] + resumen_puerta($funcion_id));
    };

    if ($codigo === '') $res('inexistente', 'No se leyó ningún código.');
    $sql = 'SELECT x.*, t.nombre AS tipo, e.titulo AS evento, f.inicio, o.estado AS pago FROM entradas x JOIN ordenes o ON o.id = x.orden_id JOIN tipos_entrada t ON t.id = x.tipo_id
            JOIN funciones f ON f.id = x.funcion_id JOIN eventos e ON e.id = f.evento_id WHERE x.codigo = ?';
    $x = fila($sql, [$codigo]);
    if (!$x) $res('inexistente', 'La entrada no existe.');
    if ((int)$x['funcion_id'] !== $funcion_id) {
        $res('otro_evento', 'Es de otra función: ' . $x['evento'] . ', ' . fecha_corta($x['inicio']) . ' h.', ['usada_en' => null] + $x);
    }
    if ($x['estado'] === 'anulada') $res('anulada', 'La entrada fue anulada.', $x);
    // Transferencia todavía sin verificar: la entrada existe (reserva el
    // lugar) pero no se pagó. No entra.
    if ($x['pago'] !== 'pagada') $res('pendiente_pago', 'El pago de esta entrada todavía no se verificó.', $x);

    // El UPDATE condicionado es el candado: si dos puertas leen la misma
    // entrada a la vez, sólo una consigue cambiarle el estado.
    $st = q("UPDATE entradas SET estado = 'usada', usada_en = ?, usada_por = ? WHERE id = ? AND estado = 'valida'", [ahora(), $u['id'], $x['id']]);
    if ($st->rowCount() === 1) $res('ok', 'Adelante.', ['usada_en' => null] + $x);
    $res('ya_usada', 'Esta entrada ya ingresó.', fila($sql, [$codigo]));
}
