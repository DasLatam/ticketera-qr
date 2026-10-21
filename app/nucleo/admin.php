<?php
// Administración: ABM de eventos y sus funciones, imágenes, categorías,
// usuarios, reseñas, ventas y correo.

function adm_tablero(): void
{
    requerir_rol('admin');
    $proximas = filas("SELECT f.*, e.titulo, e.estado AS evento_estado, " . SQL_VENDIDAS_F . " AS vendidas, " . SQL_CAPACIDAD_F . " AS capacidad
                       FROM funciones f JOIN eventos e ON e.id = f.evento_id
                       WHERE COALESCE(f.fin, f.inicio) >= ? AND e.estado <> 'cancelado' AND f.estado = 'activa' ORDER BY f.inicio LIMIT 8", [ahora()]);
    $hace30 = date('Y-m-d H:i:s', strtotime('-30 days'));
    $k = [
        'ventas30' => (int)valor("SELECT COALESCE(SUM(precio),0) FROM entradas WHERE estado <> 'anulada' AND creado >= ?", [$hace30]),
        'entradas30' => (int)valor("SELECT COUNT(*) FROM entradas WHERE estado <> 'anulada' AND creado >= ?", [$hace30]),
        'activas' => (int)valor("SELECT COUNT(*) FROM funciones f JOIN eventos e ON e.id = f.evento_id WHERE e.estado = 'publicado' AND f.estado = 'activa' AND COALESCE(f.fin, f.inicio) >= ?", [ahora()]),
        'clientes' => (int)valor("SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente'"),
    ];
    $por_dia = [];
    foreach (filas("SELECT substr(creado,1,10) AS dia, COUNT(*) AS n, SUM(precio) AS monto FROM entradas
                    WHERE estado <> 'anulada' AND creado >= ? GROUP BY dia", [$hace30]) as $r) $por_dia[$r['dia']] = $r;
    $serie = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $serie[] = ['dia' => $d, 'n' => (int)($por_dia[$d]['n'] ?? 0), 'monto' => (int)($por_dia[$d]['monto'] ?? 0)];
    }
    $ultimas = filas('SELECT o.*, u.nombre, e.titulo, (SELECT COUNT(*) FROM entradas x WHERE x.orden_id = o.id) AS n
                      FROM ordenes o JOIN usuarios u ON u.id = o.usuario_id JOIN funciones f ON f.id = o.funcion_id JOIN eventos e ON e.id = f.evento_id
                      ORDER BY o.creado DESC LIMIT 8');
    require_once __DIR__ . '/correo.php';
    $correo = cupo_correo();
    $correo['pendientes'] = (int)valor("SELECT COUNT(*) FROM correos WHERE estado IN ('pendiente','error') AND intentos < 6");
    $transf = fila("SELECT SUM(comprobante IS NOT NULL) AS verificar, SUM(comprobante IS NULL) AS esperando, COALESCE(SUM(CASE WHEN comprobante IS NOT NULL THEN total END),0) AS monto
                    FROM ordenes WHERE estado = 'pendiente' AND medio_pago = 'transferencia'");
    vista('admin/tablero', compact('proximas', 'k', 'serie', 'ultimas', 'correo', 'transf'), ['titulo' => 'Administración', 'noindex' => true]);
}

function adm_eventos(): void
{
    requerir_rol('admin');
    $f = ['q' => trim($_GET['q'] ?? ''), 'estado' => $_GET['estado'] ?? '', 'cuando' => $_GET['cuando'] ?? 'proximos'];
    $where = ['1=1'];
    $p = [];
    if ($f['q'] !== '') { $where[] = '(e.titulo LIKE ? OR EXISTS (SELECT 1 FROM funciones f WHERE f.evento_id = e.id AND f.lugar LIKE ?))'; $p[] = "%{$f['q']}%"; $p[] = "%{$f['q']}%"; }
    if ($f['estado'] !== '') { $where[] = 'e.estado = ?'; $p[] = $f['estado']; }
    $ult = '(SELECT MAX(COALESCE(f.fin, f.inicio)) FROM funciones f WHERE f.evento_id = e.id)';
    if ($f['cuando'] === 'proximos') { $where[] = "($ult >= ? OR $ult IS NULL)"; $p[] = ahora(); }
    if ($f['cuando'] === 'pasados') { $where[] = "$ult < ?"; $p[] = ahora(); }
    $eventos = filas("SELECT e.*, c.nombre AS categoria,
                        (SELECT MIN(f.inicio) FROM funciones f WHERE f.evento_id = e.id AND COALESCE(f.fin, f.inicio) >= ?) AS proxima,
                        (SELECT MAX(f.inicio) FROM funciones f WHERE f.evento_id = e.id) AS ultima,
                        (SELECT COUNT(*) FROM funciones f WHERE f.evento_id = e.id) AS n_funciones,
                        (SELECT COUNT(*) FROM entradas x JOIN funciones f ON f.id = x.funcion_id WHERE f.evento_id = e.id AND x.estado <> 'anulada') AS vendidas,
                        (SELECT archivo FROM evento_imagenes i WHERE i.evento_id = e.id ORDER BY i.orden, i.id LIMIT 1) AS portada
                      FROM eventos e LEFT JOIN categorias c ON c.id = e.categoria_id
                      WHERE " . implode(' AND ', $where) . ' ORDER BY COALESCE(proxima, ultima) ' . ($f['cuando'] === 'pasados' ? 'DESC' : 'ASC'),
        [ahora(), ...$p]);
    foreach ($eventos as &$ev) $ev['puntaje'] = puntaje_evento($ev['id']);
    unset($ev);
    vista('admin/eventos', compact('eventos', 'f'), ['titulo' => 'Eventos', 'noindex' => true]);
}

// ── Evento ─────────────────────────────────────────────────────────────────

function evento_vacio(): array
{
    return ['id' => null, 'titulo' => '', 'slug' => '', 'bajada' => '', 'descripcion' => '', 'categoria_id' => null, 'max_por_compra' => 6,
        'estado' => 'borrador', 'visibilidad' => 'publica', 'organizador' => ORGANIZADOR, 'edad_minima' => '', 'vestimenta' => '',
        'meta_titulo' => '', 'meta_descripcion' => '', 'meta_palabras' => ''];
}

function funcion_vacia(?int $evento_id = null): array
{
    // Una función nueva arranca copiando lugar, aforo y tipos de la última:
    // es lo habitual cuando se agrega otra fecha del mismo evento.
    $ult = $evento_id ? fila('SELECT * FROM funciones WHERE evento_id = ? ORDER BY inicio DESC LIMIT 1', [$evento_id]) : null;
    $base = ['id' => null, 'evento_id' => $evento_id, 'inicio' => date('Y-m-d 21:00:00', strtotime('+14 days')), 'fin' => '', 'lugar' => '', 'direccion' => '',
        'ciudad' => 'CABA', 'lat' => null, 'lng' => null, 'aforo' => 100, 'estado' => 'activa'];
    $tipos = [['id' => '', 'nombre' => 'General', 'descripcion' => '', 'precio' => 0, 'cupo' => 100, 'activo' => 1, 'vendidas' => 0, 'precio_oferta' => null, 'oferta_hasta' => null]];
    if ($ult) {
        $dur = $ult['fin'] ? strtotime($ult['fin']) - strtotime($ult['inicio']) : null;
        $ini = max(strtotime($ult['inicio']) + 7 * 86400, strtotime('tomorrow ' . date('H:i', strtotime($ult['inicio']))));
        $base = array_merge($base, array_intersect_key($ult, array_flip(['lugar', 'direccion', 'ciudad', 'lat', 'lng', 'aforo'])));
        $base['inicio'] = date('Y-m-d H:i:s', $ini);
        $base['fin'] = $dur ? date('Y-m-d H:i:s', $ini + $dur) : '';
        $tipos = array_map(fn($t) => ['id' => '', 'vendidas' => 0] + $t, filas('SELECT nombre, descripcion, precio, cupo, activo, precio_oferta, oferta_hasta FROM tipos_entrada WHERE funcion_id = ? ORDER BY orden, id', [$ult['id']]));
    }
    return [$base, $tipos];
}

function datos_form_evento(array $e, array $extra = []): array
{
    $categorias = filas('SELECT * FROM categorias ORDER BY orden, nombre');
    $todas_etiquetas = array_column(filas('SELECT nombre FROM etiquetas ORDER BY nombre'), 'nombre');
    $imagenes = $e['id'] ? filas('SELECT * FROM evento_imagenes WHERE evento_id = ? ORDER BY orden, id', [$e['id']]) : [];
    $funciones = $e['id'] ? funciones_de((int)$e['id']) : [];
    $etiquetas_ev = $e['id'] ? implode(', ', array_column(filas('SELECT g.nombre FROM etiquetas g JOIN evento_etiquetas ee ON ee.etiqueta_id = g.id WHERE ee.evento_id = ? ORDER BY g.nombre', [$e['id']]), 'nombre')) : '';
    $vendidas = $e['id'] ? (int)valor("SELECT COUNT(*) FROM entradas x JOIN funciones f ON f.id = x.funcion_id WHERE f.evento_id = ? AND x.estado <> 'anulada'", [$e['id']]) : 0;
    return array_merge(compact('e', 'categorias', 'todas_etiquetas', 'imagenes', 'funciones', 'etiquetas_ev', 'vendidas'), $extra);
}

function adm_evento_form(?string $id = null): void
{
    requerir_rol('admin');
    if ($id) {
        $e = fila('SELECT * FROM eventos WHERE id = ?', [$id]) ?? no_encontrado();
        $vars = datos_form_evento($e);
    } else {
        [$fn, $tipos] = funcion_vacia();
        $vars = datos_form_evento(evento_vacio(), compact('fn', 'tipos'));
    }
    vista('admin/evento_form', $vars, ['titulo' => $id ? 'Editar evento' : 'Nuevo evento', 'noindex' => true, 'leaflet' => !$id]);
}

function fecha_form(?string $s): ?string
{
    $s = trim((string)$s);
    if ($s === '') return null;
    $t = strtotime($s);
    return $t ? date('Y-m-d H:i:s', $t) : null;
}

function leer_evento_post(): array
{
    $P = $_POST;
    return [
        'titulo' => trim($P['titulo'] ?? ''),
        'bajada' => trim($P['bajada'] ?? ''),
        'descripcion' => trim($P['descripcion'] ?? ''),
        'categoria_id' => ($P['categoria_id'] ?? '') !== '' ? (int)$P['categoria_id'] : null,
        'max_por_compra' => max(1, min(50, (int)($P['max_por_compra'] ?? 6))),
        'estado' => in_array($P['estado'] ?? '', ['borrador', 'publicado', 'cancelado'], true) ? $P['estado'] : 'borrador',
        'visibilidad' => ($P['visibilidad'] ?? '') === 'enlace' ? 'enlace' : 'publica',
        'organizador' => trim($P['organizador'] ?? ''),
        'edad_minima' => ($P['edad_minima'] ?? '') !== '' ? (int)$P['edad_minima'] : null,
        'vestimenta' => trim($P['vestimenta'] ?? ''),
        'meta_titulo' => trim($P['meta_titulo'] ?? ''),
        'meta_descripcion' => trim($P['meta_descripcion'] ?? ''),
        'meta_palabras' => trim($P['meta_palabras'] ?? ''),
    ];
}

/** Lee del POST la función y sus tipos. $propios: ids de tipos que pertenecen a esta función. */
function leer_funcion_post(array $propios): array
{
    $P = $_POST;
    $coord = fn($v, $min, $max) => is_numeric($v ?? null) && $v >= $min && $v <= $max ? round((float)$v, 6) : null;
    $fn = [
        'inicio' => fecha_form($P['inicio'] ?? ''),
        'fin' => fecha_form($P['fin'] ?? ''),
        'lugar' => trim($P['lugar'] ?? ''),
        'direccion' => trim($P['direccion'] ?? ''),
        'ciudad' => trim($P['ciudad'] ?? ''),
        'lat' => $coord($P['lat'] ?? null, -90, 90),
        'lng' => $coord($P['lng'] ?? null, -180, 180),
        'aforo' => max(0, (int)($P['aforo'] ?? 0)),
        'estado' => ($P['funcion_estado'] ?? '') === 'cancelada' ? 'cancelada' : 'activa',
    ];
    if ($fn['lat'] === null || $fn['lng'] === null) $fn['lat'] = $fn['lng'] = null;
    // Un id que no es de esta función se trata como fila nueva: el formulario
    // no puede tocar (ni consultar) tipos de otra.
    $tipos = [];
    foreach ((array)($P['tipo'] ?? []) as $t) {
        $nombre = trim($t['nombre'] ?? '');
        $tid = (int)($t['id'] ?? 0);
        if (!in_array($tid, $propios, true)) $tid = 0;
        if ($nombre === '' && !$tid) continue;
        $tipos[] = [
            'id' => $tid,
            'nombre' => $nombre ?: 'Entrada',
            'descripcion' => trim($t['descripcion'] ?? ''),
            'precio' => max(0, (int)preg_replace('/\D/', '', (string)($t['precio'] ?? '0'))),
            // Oferta: vacío = sin oferta. «hasta» vacío = sin vencimiento.
            'precio_oferta' => trim((string)($t['precio_oferta'] ?? '')) === '' ? null : max(0, (int)preg_replace('/\D/', '', (string)$t['precio_oferta'])),
            'oferta_hasta' => ($h = trim((string)($t['oferta_hasta'] ?? ''))) !== '' && strtotime($h) ? date('Y-m-d H:i:s', strtotime($h)) : null,
            'cupo' => max(0, (int)($t['cupo'] ?? 0)),
            'activo' => !empty($t['activo']) ? 1 : 0,
            'borrar' => !empty($t['borrar']),
            'vendidas' => $tid ? (int)valor("SELECT COUNT(*) FROM entradas WHERE tipo_id = ? AND estado <> 'anulada'", [$tid]) : 0,
        ];
    }
    return [$fn, $tipos];
}

function validar_funcion(array $fn, array $tipos): array
{
    $err = [];
    if (!$fn['inicio']) $err[] = 'La fecha y hora de inicio es obligatoria.';
    if ($fn['fin'] && $fn['inicio'] && $fn['fin'] <= $fn['inicio']) $err[] = 'El fin tiene que ser posterior al inicio.';
    if ($fn['aforo'] < 1) $err[] = 'El aforo tiene que ser al menos 1.';
    if (!array_filter($tipos, fn($t) => !$t['borrar'])) $err[] = 'Cargá al menos un tipo de entrada.';
    foreach ($tipos as $t) {
        if ($t['borrar'] && $t['vendidas'] > 0) $err[] = "«{$t['nombre']}» tiene {$t['vendidas']} entradas vendidas: no se puede borrar, desactivalo.";
        if (!$t['borrar'] && $t['cupo'] < $t['vendidas']) $err[] = "El cupo de «{$t['nombre']}» no puede ser menor que las {$t['vendidas']} vendidas.";
        if (!$t['borrar'] && $t['precio_oferta'] !== null && $t['precio_oferta'] >= $t['precio']) $err[] = "La oferta de «{$t['nombre']}» tiene que ser menor que su precio ({$t['precio']}).";
        if (!$t['borrar'] && $t['precio_oferta'] === null && $t['oferta_hasta'] !== null) $err[] = "«{$t['nombre']}» tiene fecha de oferta pero no precio de oferta.";
    }
    return $err;
}

function guardar_funcion(int $evento_id, ?int $id, array $fn, array $tipos): int
{
    $cols = array_keys($fn);
    if ($id) {
        q('UPDATE funciones SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ? AND evento_id = ?', [...array_values($fn), $id, $evento_id]);
    } else {
        q('INSERT INTO funciones (evento_id, ' . implode(',', $cols) . ', creado) VALUES (?,' . implode(',', array_fill(0, count($cols), '?')) . ',?)', [$evento_id, ...array_values($fn), ahora()]);
        $id = (int)db()->lastInsertId();
    }
    foreach ($tipos as $orden => $t) {
        if ($t['id'] && $t['borrar']) {
            q('DELETE FROM tipos_entrada WHERE id = ? AND funcion_id = ?', [$t['id'], $id]);
        } elseif ($t['id']) {
            q('UPDATE tipos_entrada SET nombre = ?, descripcion = ?, precio = ?, cupo = ?, activo = ?, orden = ?, precio_oferta = ?, oferta_hasta = ? WHERE id = ? AND funcion_id = ?',
                [$t['nombre'], $t['descripcion'], $t['precio'], $t['cupo'], $t['activo'], $orden, $t['precio_oferta'], $t['oferta_hasta'], $t['id'], $id]);
        } elseif (!$t['borrar']) {
            q('INSERT INTO tipos_entrada (funcion_id, nombre, descripcion, precio, cupo, activo, orden, precio_oferta, oferta_hasta) VALUES (?,?,?,?,?,?,?,?,?)',
                [$id, $t['nombre'], $t['descripcion'], $t['precio'], $t['cupo'], $t['activo'], $orden, $t['precio_oferta'], $t['oferta_hasta']]);
        }
    }
    return $id;
}

function adm_evento_guardar(?string $id = null): void
{
    requerir_rol('admin');
    $d = leer_evento_post();
    $slug_pedido = slug(($_POST['slug'] ?? '') !== '' ? $_POST['slug'] : $d['titulo']);
    $err = [];
    if (mb_strlen($d['titulo']) < 3) $err[] = 'El título es obligatorio.';
    // Al crear, el formulario trae también la primera función.
    $fn = $tipos = null;
    if (!$id) {
        [$fn, $tipos] = leer_funcion_post([]);
        $err = array_merge($err, validar_funcion($fn, $tipos));
    }
    if ($err) {
        foreach ($err as $x) flash($x, 'error');
        // Se vuelve a mostrar con lo que escribió, para no hacerle perder el trabajo.
        $e = $d + ['id' => $id, 'slug' => $_POST['slug'] ?? ''];
        $vars = datos_form_evento($e, $id ? [] : ['fn' => $fn, 'tipos' => $tipos]);
        $vars['etiquetas_ev'] = $_POST['etiquetas'] ?? '';
        vista('admin/evento_form', $vars, ['titulo' => $id ? 'Editar evento' : 'Nuevo evento', 'noindex' => true, 'leaflet' => !$id]);
        return;
    }

    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $slug = $slug_pedido;
        for ($n = 2; valor('SELECT 1 FROM eventos WHERE slug = ? AND id <> ?', [$slug, (int)$id]); $n++) $slug = "$slug_pedido-$n";
        $d['slug'] = $slug;
        $d['actualizado'] = ahora();
        if ($id) {
            $cols = array_keys($d);
            q('UPDATE eventos SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($d), $id]);
        } else {
            $d['creado'] = ahora();
            $cols = array_keys($d);
            q('INSERT INTO eventos (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
            $id = (string)$pdo->lastInsertId();
            guardar_funcion((int)$id, null, $fn, $tipos);
        }
        guardar_etiquetas((int)$id, (string)($_POST['etiquetas'] ?? ''));
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $ex;
    }
    if (!empty($_FILES['imagenes']['name'][0])) subir_imagenes((int)$id);
    flash('Evento guardado.');
    ir('/admin/eventos/' . $id);
}

function guardar_etiquetas(int $evento_id, string $texto): void
{
    q('DELETE FROM evento_etiquetas WHERE evento_id = ?', [$evento_id]);
    $vistos = [];
    foreach (preg_split('/[,;\n]+/', $texto) as $nombre) {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
        if ($nombre === '') continue;
        $s = slug($nombre);
        if (isset($vistos[$s])) continue;
        $vistos[$s] = true;
        $gid = valor('SELECT id FROM etiquetas WHERE slug = ?', [$s]);
        if (!$gid) {
            q('INSERT INTO etiquetas (nombre, slug) VALUES (?, ?)', [mb_strtolower($nombre), $s]);
            $gid = db()->lastInsertId();
        }
        q('INSERT OR IGNORE INTO evento_etiquetas (evento_id, etiqueta_id) VALUES (?, ?)', [$evento_id, $gid]);
    }
    // Etiquetas que ya no usa nadie: afuera, para que no ensucien el filtro.
    q('DELETE FROM etiquetas WHERE id NOT IN (SELECT etiqueta_id FROM evento_etiquetas)');
}

function adm_evento_borrar(string $id): void
{
    requerir_rol('admin');
    $e = fila('SELECT * FROM eventos WHERE id = ?', [$id]) ?? no_encontrado();
    $v = (int)valor('SELECT COUNT(*) FROM entradas x JOIN funciones f ON f.id = x.funcion_id WHERE f.evento_id = ?', [$id]);
    if ($v > 0) {
        // Con entradas emitidas no se borra: se perdería el registro de quién
        // pagó qué. Se cancela.
        flash("El evento tiene $v entradas emitidas: no se puede borrar. Cambiale el estado a «cancelado».", 'error');
        ir('/admin/eventos/' . $id);
    }
    foreach (filas('SELECT archivo FROM evento_imagenes WHERE evento_id = ?', [$id]) as $img) borrar_archivos_imagen($img['archivo']);
    q('DELETE FROM eventos WHERE id = ?', [$id]);
    q('DELETE FROM etiquetas WHERE id NOT IN (SELECT etiqueta_id FROM evento_etiquetas)');
    flash('Evento «' . $e['titulo'] . '» borrado.');
    ir('/admin/eventos');
}

function adm_evento_duplicar(string $id): void
{
    requerir_rol('admin');
    $e = fila('SELECT * FROM eventos WHERE id = ?', [$id]) ?? no_encontrado();
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    $base = $e['slug'] . '-copia';
    $slug = $base;
    for ($n = 2; valor('SELECT 1 FROM eventos WHERE slug = ?', [$slug]); $n++) $slug = "$base-$n";
    unset($e['id']);
    $e['slug'] = $slug;
    $e['titulo'] .= ' (copia)';
    $e['estado'] = 'borrador';
    $e['creado'] = $e['actualizado'] = ahora();
    $cols = array_keys($e);
    q('INSERT INTO eventos (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($e));
    $nuevo = (int)$pdo->lastInsertId();
    q('INSERT INTO evento_etiquetas (evento_id, etiqueta_id) SELECT ?, etiqueta_id FROM evento_etiquetas WHERE evento_id = ?', [$nuevo, $id]);
    // Una función de muestra, copiada de la última del original.
    [$fn, $tipos] = funcion_vacia((int)$id);
    guardar_funcion($nuevo, null, array_intersect_key($fn, array_flip(['inicio', 'fin', 'lugar', 'direccion', 'ciudad', 'lat', 'lng', 'aforo', 'estado'])),
        array_map(fn($t) => ['id' => 0, 'borrar' => false] + $t, $tipos));
    // Las imágenes se copian como archivos nuevos: si después se borra una en
    // la copia, no puede desaparecer del original.
    @mkdir(DIR_SUBIDAS . '/eventos/' . $nuevo, 0755, true);
    foreach (filas('SELECT * FROM evento_imagenes WHERE evento_id = ? ORDER BY orden, id', [$id]) as $img) {
        $nombre = $nuevo . '/' . bin2hex(random_bytes(6));
        foreach (['', '-m', '-og'] as $suf) @copy(DIR_SUBIDAS . "/eventos/{$img['archivo']}$suf.jpg", DIR_SUBIDAS . "/eventos/$nombre$suf.jpg");
        q('INSERT INTO evento_imagenes (evento_id, archivo, alt, orden) VALUES (?,?,?,?)', [$nuevo, $nombre, $img['alt'], $img['orden']]);
    }
    $pdo->exec('COMMIT');
    flash('Copia creada como borrador, con una función de muestra. Ajustá título y fechas antes de publicarla.');
    ir('/admin/eventos/' . $nuevo);
}

// ── Funciones ──────────────────────────────────────────────────────────────

function adm_funcion_form(string $evento_id, ?string $id = null): void
{
    requerir_rol('admin');
    $e = fila('SELECT * FROM eventos WHERE id = ?', [$evento_id]) ?? no_encontrado();
    if ($id) {
        $fn = fila('SELECT * FROM funciones WHERE id = ? AND evento_id = ?', [$id, $evento_id]) ?? no_encontrado();
        $tipos = tipos_con_stock($fn['id'], false); // crudos: el formulario edita el precio de lista
    } else {
        [$fn, $tipos] = funcion_vacia((int)$evento_id);
    }
    $vendidas = $id ? vendidas_funcion((int)$id) : 0;
    vista('admin/funcion_form', compact('e', 'fn', 'tipos', 'vendidas'), ['titulo' => ($id ? 'Función' : 'Nueva función') . ' · ' . $e['titulo'], 'noindex' => true, 'leaflet' => true]);
}

function adm_funcion_guardar(string $evento_id, ?string $id = null): void
{
    requerir_rol('admin');
    $e = fila('SELECT * FROM eventos WHERE id = ?', [$evento_id]) ?? no_encontrado();
    if ($id) fila('SELECT id FROM funciones WHERE id = ? AND evento_id = ?', [$id, $evento_id]) ?? no_encontrado();
    $propios = $id ? array_map('intval', array_column(filas('SELECT id FROM tipos_entrada WHERE funcion_id = ?', [$id]), 'id')) : [];
    [$fn, $tipos] = leer_funcion_post($propios);
    $err = validar_funcion($fn, $tipos);
    if ($err) {
        foreach ($err as $x) flash($x, 'error');
        $fn['id'] = $id;
        $vendidas = $id ? vendidas_funcion((int)$id) : 0;
        vista('admin/funcion_form', compact('e', 'fn', 'tipos', 'vendidas'), ['titulo' => 'Función · ' . $e['titulo'], 'noindex' => true, 'leaflet' => true]);
        return;
    }
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $nuevo = guardar_funcion((int)$evento_id, $id ? (int)$id : null, $fn, $tipos);
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $ex;
    }
    flash($id ? 'Función guardada.' : 'Función agregada.');
    ir('/admin/eventos/' . $evento_id . '#funciones');
}

function adm_funcion_borrar(string $evento_id, string $id): void
{
    requerir_rol('admin');
    $v = (int)valor('SELECT COUNT(*) FROM entradas WHERE funcion_id = ?', [$id]);
    if ($v > 0) {
        flash("La función tiene $v entradas emitidas: no se puede borrar. Marcala como cancelada.", 'error');
        ir("/admin/eventos/$evento_id/funciones/$id");
    }
    q('DELETE FROM funciones WHERE id = ? AND evento_id = ?', [$id, $evento_id]);
    flash('Función borrada.');
    ir("/admin/eventos/$evento_id#funciones");
}

// ── Imágenes ───────────────────────────────────────────────────────────────
// Toda imagen subida se vuelve a codificar a JPEG: así no viaja nada que el
// navegador no haya podido ver como imagen, y se le quita el EXIF (que en una
// foto de celular trae la ubicación). Salen tres tamaños: grande, mediano y
// uno de 1200×630 recortado al centro, que es el que muestran WhatsApp, X y
// Facebook al compartir el enlace.

function procesar_imagen(string $origen, string $destino_base): bool
{
    $info = @getimagesize($origen);
    if (!$info) return false;
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($origen),
        IMAGETYPE_PNG => @imagecreatefrompng($origen),
        IMAGETYPE_WEBP => @imagecreatefromwebp($origen),
        default => false,
    };
    if (!$img) return false;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = @exif_read_data($origen)['Orientation'] ?? 1;
        $img = match ((int)$o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
    }
    $w = imagesx($img);
    $h = imagesy($img);
    foreach (['' => 1800, '-m' => 720] as $suf => $max) {
        $k = min(1, $max / max($w, $h));
        $nw = (int)round($w * $k);
        $nh = (int)round($h * $k);
        $lienzo = imagecreatetruecolor($nw, $nh);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imageinterlace($lienzo, true);
        imagejpeg($lienzo, $destino_base . $suf . '.jpg', $suf === '' ? 84 : 80);
    }
    // 1200×630 (1,91:1): recorte centrado de lo que sobre.
    $ow = 1200; $oh = 630;
    $esc = max($ow / $w, $oh / $h);
    $sw = (int)round($ow / $esc); $sh = (int)round($oh / $esc);
    $lienzo = imagecreatetruecolor($ow, $oh);
    imagecopyresampled($lienzo, $img, 0, 0, (int)(($w - $sw) / 2), (int)(($h - $sh) / 2), $ow, $oh, $sw, $sh);
    // Un flyer vertical o cuadrado recortado así pierde el título: va entero
    // en el centro, sobre la misma imagen desenfocada y oscurecida.
    if ($w / $h < 1.3) {
        $chico = imagecreatetruecolor(120, 63);
        imagecopyresampled($chico, $lienzo, 0, 0, 0, 0, 120, 63, $ow, $oh);
        for ($i = 0; $i < 6; $i++) imagefilter($chico, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($chico, IMG_FILTER_BRIGHTNESS, -70);
        imagecopyresampled($lienzo, $chico, 0, 0, 0, 0, $ow, $oh, 120, 63);
        $pw = (int)round($w * $oh / $h);
        imagecopyresampled($lienzo, $img, (int)(($ow - $pw) / 2), 0, 0, 0, $pw, $oh, $w, $h);
    }
    imagejpeg($lienzo, $destino_base . '-og.jpg', 82);
    return true;
}

function subir_imagenes(int $evento_id): int
{
    $dir = DIR_SUBIDAS . '/eventos/' . $evento_id;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $orden = (int)valor('SELECT COALESCE(MAX(orden), -1) FROM evento_imagenes WHERE evento_id = ?', [$evento_id]);
    $ok = 0;
    $F = $_FILES['imagenes'];
    foreach ((array)$F['name'] as $i => $nombre) {
        if ($F['error'][$i] !== UPLOAD_ERR_OK) continue;
        // Ojo: «{$nombre}» con llaves. Sin ellas PHP toma el «»» (multibyte)
        // como parte del nombre de la variable y el texto sale vacío.
        if ($F['size'][$i] > 20 * 1024 * 1024) { flash("«{$nombre}» pesa más de 20 MB.", 'error'); continue; }
        $archivo = $evento_id . '/' . bin2hex(random_bytes(6));
        if (procesar_imagen($F['tmp_name'][$i], DIR_SUBIDAS . '/eventos/' . $archivo)) {
            q('INSERT INTO evento_imagenes (evento_id, archivo, alt, orden) VALUES (?,?,?,?)', [$evento_id, $archivo, null, ++$orden]);
            $ok++;
        } else {
            flash("«{$nombre}» no es una imagen JPG, PNG o WebP válida.", 'error');
        }
    }
    if ($ok) flash($ok === 1 ? 'Imagen agregada.' : "$ok imágenes agregadas.");
    return $ok;
}

function adm_imagenes_subir(string $id): void
{
    requerir_rol('admin');
    fila('SELECT id FROM eventos WHERE id = ?', [$id]) ?? no_encontrado();
    if (!empty($_FILES['imagenes']['name'][0])) subir_imagenes((int)$id);
    ir('/admin/eventos/' . $id . '#imagenes');
}

function borrar_archivos_imagen(string $archivo): void
{
    foreach (['', '-m', '-og'] as $suf) @unlink(DIR_SUBIDAS . "/eventos/$archivo$suf.jpg");
}

function adm_imagen_borrar(string $id): void
{
    requerir_rol('admin');
    $img = fila('SELECT * FROM evento_imagenes WHERE id = ?', [$id]) ?? no_encontrado();
    q('DELETE FROM evento_imagenes WHERE id = ?', [$id]);
    borrar_archivos_imagen($img['archivo']);
    flash('Imagen borrada.');
    ir('/admin/eventos/' . $img['evento_id'] . '#imagenes');
}

function adm_imagen_portada(string $id): void
{
    requerir_rol('admin');
    $img = fila('SELECT * FROM evento_imagenes WHERE id = ?', [$id]) ?? no_encontrado();
    q('UPDATE evento_imagenes SET orden = orden + 1 WHERE evento_id = ?', [$img['evento_id']]);
    q('UPDATE evento_imagenes SET orden = 0 WHERE id = ?', [$id]);
    flash('Portada actualizada.');
    ir('/admin/eventos/' . $img['evento_id'] . '#imagenes');
}

// ── Asistentes y entradas ──────────────────────────────────────────────────

function adm_asistentes(string $id): void
{
    requerir_rol('admin');
    $fn = fila('SELECT f.*, e.titulo, e.slug FROM funciones f JOIN eventos e ON e.id = f.evento_id WHERE f.id = ?', [$id]) ?? no_encontrado();
    $q = trim($_GET['q'] ?? '');
    $p = [$id];
    $extra = '';
    if ($q !== '') {
        $extra = ' AND (x.titular LIKE ? OR u.nombre LIKE ? OR u.email LIKE ? OR x.codigo LIKE ? OR o.codigo LIKE ?)';
        array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
    }
    $entradas = filas("SELECT x.*, t.nombre AS tipo, u.nombre AS comprador, u.email, u.telefono, o.codigo AS orden, p.nombre AS portero
                       FROM entradas x JOIN tipos_entrada t ON t.id = x.tipo_id JOIN usuarios u ON u.id = x.usuario_id
                       JOIN ordenes o ON o.id = x.orden_id LEFT JOIN usuarios p ON p.id = x.usada_por
                       WHERE x.funcion_id = ? $extra ORDER BY x.titular COLLATE NOCASE, x.id", $p);

    if (($_GET['formato'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="asistentes-' . $fn['slug'] . '-' . date('Y-m-d', strtotime($fn['inicio'])) . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM: sin esto Excel rompe las tildes
        fputcsv($out, ['Código', 'Titular', 'Tipo', 'Precio', 'Estado', 'Ingresó', 'Comprador', 'Email', 'Teléfono', 'Orden', 'Comprada'], ';', '"', '');
        foreach ($entradas as $x) {
            fputcsv($out, [$x['codigo'], $x['titular'], $x['tipo'], $x['precio'], $x['estado'], $x['usada_en'], $x['comprador'], $x['email'], $x['telefono'], $x['orden'], $x['creado']], ';', '"', '');
        }
        exit;
    }
    $resumen = fila("SELECT COUNT(*) AS emitidas, SUM(estado <> 'anulada') AS vigentes, SUM(estado = 'usada') AS ingresados,
                       SUM(estado = 'anulada') AS anuladas, SUM(CASE WHEN estado <> 'anulada' THEN precio ELSE 0 END) AS recaudado
                     FROM entradas WHERE funcion_id = ?", [$id]);
    $escaneos = filas('SELECT s.*, u.nombre AS portero, x.titular FROM escaneos s LEFT JOIN usuarios u ON u.id = s.usuario_id
                       LEFT JOIN entradas x ON x.id = s.entrada_id WHERE s.funcion_id = ? ORDER BY s.id DESC LIMIT 30', [$id]);
    vista('admin/asistentes', compact('fn', 'entradas', 'resumen', 'escaneos', 'q'), ['titulo' => 'Asistentes · ' . $fn['titulo'], 'noindex' => true]);
}

function adm_entrada_anular(string $id): void
{
    requerir_rol('admin');
    $x = fila('SELECT * FROM entradas WHERE id = ?', [$id]) ?? no_encontrado();
    q("UPDATE entradas SET estado = 'anulada' WHERE id = ? AND estado = 'valida'", [$id]);
    flash('Entrada ' . $x['codigo'] . ' anulada: ya no sirve para entrar y liberó su lugar.');
    ir('/admin/funciones/' . $x['funcion_id'] . '/asistentes');
}

function adm_entrada_reactivar(string $id): void
{
    requerir_rol('admin');
    $x = fila('SELECT * FROM entradas WHERE id = ?', [$id]) ?? no_encontrado();
    q("UPDATE entradas SET estado = 'valida', usada_en = NULL, usada_por = NULL WHERE id = ?", [$id]);
    flash('Entrada ' . $x['codigo'] . ' vuelve a estar válida.');
    ir('/admin/funciones/' . $x['funcion_id'] . '/asistentes');
}

// ── Reseñas ────────────────────────────────────────────────────────────────

function adm_resenas(): void
{
    requerir_rol('admin');
    $resenas = filas('SELECT r.*, u.nombre, u.email, e.titulo, e.slug, f.inicio, f.lugar FROM resenas r JOIN usuarios u ON u.id = r.usuario_id
                      JOIN funciones f ON f.id = r.funcion_id JOIN eventos e ON e.id = f.evento_id ORDER BY r.creado DESC LIMIT 300');
    vista('admin/resenas', compact('resenas'), ['titulo' => 'Reseñas', 'noindex' => true]);
}

function adm_resena_visible(string $id): void
{
    requerir_rol('admin');
    q('UPDATE resenas SET visible = 1 - visible WHERE id = ?', [$id]);
    flash('Reseña actualizada.');
    ir('/admin/resenas');
}

// ── Categorías ─────────────────────────────────────────────────────────────

function adm_categorias(): void
{
    requerir_rol('admin');
    $categorias = filas('SELECT c.*, (SELECT COUNT(*) FROM eventos e WHERE e.categoria_id = c.id) AS n FROM categorias c ORDER BY c.orden, c.nombre');
    vista('admin/categorias', compact('categorias'), ['titulo' => 'Categorías', 'noindex' => true]);
}

function adm_categoria_guardar(): void
{
    requerir_rol('admin');
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $orden = (int)($_POST['orden'] ?? 0);
    if ($nombre === '') {
        flash('La categoría necesita un nombre.', 'error');
        ir('/admin/categorias');
    }
    $s = slug($nombre);
    if (valor('SELECT 1 FROM categorias WHERE slug = ? AND id <> ?', [$s, $id])) {
        flash('Ya existe una categoría con ese nombre.', 'error');
        ir('/admin/categorias');
    }
    if ($id) q('UPDATE categorias SET nombre = ?, slug = ?, orden = ? WHERE id = ?', [$nombre, $s, $orden, $id]);
    else q('INSERT INTO categorias (nombre, slug, orden) VALUES (?,?,?)', [$nombre, $s, $orden]);
    flash('Categoría guardada.');
    ir('/admin/categorias');
}

function adm_categoria_borrar(string $id): void
{
    requerir_rol('admin');
    q('DELETE FROM categorias WHERE id = ?', [$id]);
    flash('Categoría borrada. Sus eventos quedaron sin categoría.');
    ir('/admin/categorias');
}

// ── Usuarios ───────────────────────────────────────────────────────────────

function adm_usuarios(): void
{
    requerir_rol('admin');
    $q = trim($_GET['q'] ?? '');
    $rol = $_GET['rol'] ?? '';
    $where = ['1=1'];
    $p = [];
    if ($q !== '') { $where[] = '(u.nombre LIKE ? OR u.email LIKE ? OR u.dni LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
    if (in_array($rol, ['cliente', 'puerta', 'admin'], true)) { $where[] = 'u.rol = ?'; $p[] = $rol; }
    $usuarios = filas("SELECT u.*, (SELECT COUNT(*) FROM entradas x WHERE x.usuario_id = u.id AND x.estado <> 'anulada') AS entradas
                       FROM usuarios u WHERE " . implode(' AND ', $where) . " ORDER BY CASE u.rol WHEN 'admin' THEN 0 WHEN 'puerta' THEN 1 ELSE 2 END, u.nombre LIMIT 300", $p);
    vista('admin/usuarios', compact('usuarios', 'q', 'rol'), ['titulo' => 'Usuarios', 'noindex' => true]);
}

function adm_usuario_guardar(string $id): void
{
    $yo = requerir_rol('admin');
    $u = fila('SELECT * FROM usuarios WHERE id = ?', [$id]) ?? no_encontrado();
    $rol = in_array($_POST['rol'] ?? '', ['cliente', 'puerta', 'admin'], true) ? $_POST['rol'] : $u['rol'];
    $activo = !empty($_POST['activo']) ? 1 : 0;
    if ($u['id'] == $yo['id'] && ($rol !== 'admin' || !$activo)) {
        flash('No podés sacarte el rol de administrador ni deshabilitarte a vos mismo.', 'error');
        ir('/admin/usuarios');
    }
    q('UPDATE usuarios SET rol = ?, activo = ? WHERE id = ?', [$rol, $activo, $id]);
    $clave = (string)($_POST['clave'] ?? '');
    if ($clave !== '') {
        if (mb_strlen($clave) < 8) {
            flash('La contraseña tiene que tener al menos 8 caracteres; no se cambió.', 'error');
            ir('/admin/usuarios');
        }
        q('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $id]);
    }
    flash('Usuario ' . $u['email'] . ' actualizado.');
    ir('/admin/usuarios?q=' . rawurlencode($u['email']));
}

// ── Ventas y correo ────────────────────────────────────────────────────────

function adm_ventas(): void
{
    requerir_rol('admin');
    $evento = (int)($_GET['evento'] ?? 0);
    $p = [];
    $where = '1=1';
    if ($evento) { $where = 'f.evento_id = ?'; $p[] = $evento; }
    $ordenes = filas("SELECT o.*, u.nombre, u.email, e.titulo, f.inicio, (SELECT COUNT(*) FROM entradas x WHERE x.orden_id = o.id) AS n,
                        (SELECT COUNT(*) FROM entradas x WHERE x.orden_id = o.id AND x.estado = 'anulada') AS anuladas
                      FROM ordenes o JOIN usuarios u ON u.id = o.usuario_id JOIN funciones f ON f.id = o.funcion_id JOIN eventos e ON e.id = f.evento_id
                      WHERE $where ORDER BY o.creado DESC LIMIT 500", $p);
    $eventos = filas('SELECT id, titulo FROM eventos ORDER BY titulo');
    vista('admin/ventas', compact('ordenes', 'eventos', 'evento'), ['titulo' => 'Ventas', 'noindex' => true]);
}

function adm_correos(): void
{
    requerir_rol('admin');
    require_once __DIR__ . '/correo.php';
    $cupo = cupo_correo();
    $estados = array_column(filas('SELECT estado, COUNT(*) AS n FROM correos GROUP BY estado'), 'n', 'estado');
    $correos = filas('SELECT id, para, asunto, motivo, estado, intentos, error, creado, enviado FROM correos ORDER BY id DESC LIMIT 100');
    $cron = valor("SELECT valor FROM ajustes WHERE clave = 'cron_correos'") ?: null;
    vista('admin/correos', compact('cupo', 'estados', 'correos', 'cron'), ['titulo' => 'Correos', 'noindex' => true]);
}

function adm_correos_despachar(): void
{
    requerir_rol('admin');
    require_once __DIR__ . '/correo.php';
    $n = despachar_correos(20);
    flash($n ? "Salieron $n correos." : 'No salió ninguno: no hay pendientes, se llegó a un tope o la casilla no responde (mirá el error de cada uno).', $n ? 'ok' : 'info');
    ir('/admin/correos');
}

/**
 * Correo de prueba a la propia casilla: la confirmación de la última compra,
 * tal cual la recibe un cliente (con imagen y QR). Prueba el camino completo y
 * muestra el diseño real, sin escribirle a nadie de afuera.
 */
function adm_correos_prueba(): void
{
    requerir_rol('admin');
    $orden = valor('SELECT id FROM ordenes ORDER BY creado DESC LIMIT 1');
    if (!$orden) {
        flash('No hay ninguna compra para usar de muestra.', 'error');
        ir('/admin/correos');
    }
    correo_compra((int)$orden, CORREO_CONTACTO, 'prueba');
    despachar_correos(5);
    $ult = fila("SELECT estado, error FROM correos WHERE motivo = 'prueba' ORDER BY id DESC LIMIT 1");
    flash($ult['estado'] === 'enviado' ? 'Correo de prueba (una compra de muestra) enviado a ' . CORREO_CONTACTO . '.' : 'No salió: ' . ($ult['error'] ?: $ult['estado']), $ult['estado'] === 'enviado' ? 'ok' : 'error');
    ir('/admin/correos');
}

/**
 * Plan B de la tarea programada, por web: si el `php` de la consola del hosting
 * no sirve, la tarea puede llamar a esta dirección con curl. La clave sale de
 * secretos.php (TAREA_CLAVE); sin clave configurada, la ruta no existe.
 */
function tarea_correos_web(): void
{
    if (!defined('TAREA_CLAVE') || TAREA_CLAVE === '' || !hash_equals(TAREA_CLAVE, (string)($_GET['clave'] ?? ''))) no_encontrado();
    require __DIR__ . '/tarea_correos.php';
    header('Content-Type: text/plain; charset=utf-8');
    echo "ok $enviados_tarea\n";
}

function adm_pagos(): void
{
    requerir_rol('admin');
    vista('admin/pagos', [], ['titulo' => 'Cobrar con Mercado Pago', 'noindex' => true]);
}

// ── Transferencias ─────────────────────────────────────────────────────────

function adm_transferencias(): void
{
    requerir_rol('admin');
    $q = trim($_GET['q'] ?? '');
    $sel = "SELECT o.*, u.nombre, u.email, u.telefono, u.dni, e.titulo, f.inicio, f.lugar, v.nombre AS verificador,
              (SELECT COUNT(*) FROM entradas x WHERE x.orden_id = o.id) AS n
            FROM ordenes o JOIN usuarios u ON u.id = o.usuario_id JOIN funciones f ON f.id = o.funcion_id JOIN eventos e ON e.id = f.evento_id
            LEFT JOIN usuarios v ON v.id = o.verificada_por WHERE o.medio_pago = 'transferencia'";
    $filtro = '';
    $p = [];
    if ($q !== '') {
        // Se busca por código, nombre, correo o importe («24000,37», «24.000»).
        $num = preg_replace('/[^\d,]/', '', $q);
        $cent = str_contains($num, ',') ? (int)str_replace(',', '', str_pad($num, strpos($num, ',') + 3, '0')) : (int)$num * 100;
        $filtro = ' AND (o.codigo LIKE ? OR u.nombre LIKE ? OR u.email LIKE ? OR o.importe_cent = ? OR o.total * 100 = CAST(? AS INTEGER))';
        $p = ["%$q%", "%$q%", "%$q%", $cent, $cent];
    }
    // Lo más urgente primero: la función que empieza antes.
    $pend = filas("$sel AND o.estado = 'pendiente' $filtro ORDER BY f.inicio, o.creado", $p);

    // Sugerencias del conciliador: pago informado + reserva todavía pendiente.
    $sugeridas = [];
    foreach (filas("SELECT * FROM pagos_mp WHERE orden_id IS NULL AND descartado = 0 AND resultado LIKE '{%' ORDER BY fecha") as $pg) {
        $r = json_decode($pg['resultado'], true);
        foreach ($pend as $o) if ((int)$o['id'] === (int)($r['sugerida'] ?? 0)) $sugeridas[] = ['pago' => $pg, 'orden' => $o, 'motivo' => $r['motivo'] ?? ''];
    }
    $con_sugerencia = array_map(fn($s) => (int)$s['orden']['id'], $sugeridas);
    $revisar = array_values(array_filter($pend, fn($o) => $o['comprobante'] && !in_array((int)$o['id'], $con_sugerencia, true)));
    $esperando = array_values(array_filter($pend, fn($o) => !$o['comprobante'] && !in_array((int)$o['id'], $con_sugerencia, true)));
    $sin_reserva = filas("SELECT * FROM pagos_mp WHERE orden_id IS NULL AND descartado = 0 AND estado = 'approved'
                          AND (resultado IS NULL OR resultado NOT LIKE '{%') ORDER BY fecha DESC LIMIT 200");
    $auto = filas("$sel AND o.conciliacion = 'mp-automatica' AND o.verificada_en >= ? $filtro ORDER BY o.verificada_en DESC LIMIT 200",
        array_merge([date('Y-m-d H:i:s', time() - 7 * 86400)], $p));
    $resueltas = filas("$sel AND o.estado <> 'pendiente' $filtro ORDER BY COALESCE(o.verificada_en, o.creado) DESC LIMIT 50", $p);

    $pestanas = ['sugeridas' => count($sugeridas), 'revisar' => count($revisar), 'esperando' => count($esperando),
                 'sin_reserva' => count($sin_reserva), 'auto' => count($auto), 'resueltas' => count($resueltas)];
    $tab = $_GET['tab'] ?? '';
    if (!isset($pestanas[$tab])) {
        // Sin pestaña elegida: la primera que necesita a una persona. Si se
        // está buscando, la primera que tenga resultados, sea cual sea.
        $orden_pest = $q !== '' ? array_keys($pestanas) : ['sugeridas', 'revisar', 'sin_reserva', 'esperando'];
        $tab = 'esperando';
        foreach ($orden_pest as $k) if ($pestanas[$k]) { $tab = $k; break; }
    }
    $mp = mp_config();
    vista('admin/transferencias', compact('sugeridas', 'revisar', 'esperando', 'sin_reserva', 'auto', 'resueltas', 'pestanas', 'tab', 'q', 'mp'),
        ['titulo' => 'Transferencias', 'noindex' => true]);
}

/** Configurar cobro: la cuenta donde transfieren y la conexión con Mercado Pago. Aparte de la bandeja. */
function adm_cobro(): void
{
    requerir_rol('admin');
    vista('admin/cobro', ['mp' => mp_config()], ['titulo' => 'Configurar cobro', 'noindex' => true]);
}

function adm_mp_guardar(): void
{
    requerir_rol('admin');
    $c = mp_config();
    foreach (['access_token', 'webhook_secret'] as $k) {
        $v = preg_replace('/\s+/', '', (string)($_POST[$k] ?? ''));
        if ($v === '' || str_starts_with($v, '••')) continue; // vacío o enmascarado = no cambiar
        // Lo más común es pegar otro dato de la aplicación (el nombre, la
        // Public Key, el Client ID): se avisa en el momento, sin guardarlo.
        if ($k === 'access_token' && str_starts_with($v, 'TEST-')) {
            flash('Ese es el Access Token de PRUEBA. Hace falta el de producción: Credenciales de producción › Access Token (empieza con APP_USR-).', 'error');
            ir('/admin/cobro#mercadopago');
        }
        if ($k === 'access_token' && !preg_match('/^APP_USR-[\w-]{20,}$/', $v)) {
            flash('Eso no parece un Access Token: tiene que empezar con APP_USR- y ser largo (unos 70 caracteres). ¿Lo copiaste de «Credenciales de producción › Access Token»? La Public Key no sirve.', 'error');
            ir('/admin/cobro#mercadopago');
        }
        if ($k === 'access_token' && $v !== $c[$k]) { $c['user_id'] = ''; $c['nickname'] = ''; guardar_ajuste('mp_error', ''); }
        $c[$k] = $v;
    }
    if (!empty($_POST['desconectar'])) $c = ['access_token' => '', 'webhook_secret' => '', 'user_id' => '', 'nickname' => '', 'api_base' => $c['api_base'] ?? ''];
    mp_guardar_config($c);
    guardar_ajuste('mp_auto_confirmar', !empty($_POST['mp_auto_confirmar']) ? '1' : '0');
    if ($c['access_token'] !== '' && empty($_POST['desconectar'])) {
        try {
            $u = mp_probar();
            guardar_ajuste('mp_error_conexion', '');
            if (mp_config()['prueba']) flash('Conectó, pero con una CUENTA DE PRUEBA (' . ($u['nickname'] ?? '') . '): copiaste las credenciales de prueba. Esa cuenta nunca recibe tus transferencias reales. Usá las de «Credenciales de producción».', 'error');
            else flash('Conectado a Mercado Pago como ' . ($u['nickname'] ?? $u['email'] ?? $u['id']) . '.');
        } catch (Throwable $ex) {
            $c = mp_config();
            $c['user_id'] = ''; $c['nickname'] = '';
            mp_guardar_config($c);
            $msj = preg_match('/respondió (401|403)/', $ex->getMessage())
                ? 'Mercado Pago no aceptó el Access Token. Revisá que sea el de producción (APP_USR-…) de esta aplicación y que las credenciales de producción estén activadas.'
                : 'No se pudo probar: ' . $ex->getMessage();
            guardar_ajuste('mp_error_conexion', ahora() . '|' . $msj);
            flash($msj, 'error');
        }
    } else {
        flash(!empty($_POST['desconectar']) ? 'Mercado Pago desconectado.' : 'Guardado.');
    }
    ir('/admin/cobro#mercadopago');
}

function adm_mp_sincronizar(): void
{
    requerir_rol('admin');
    try {
        $r = mp_sincronizar(7);
        flash("Mercado Pago: {$r['leidos']} pagos recibidos en 7 días · {$r['automaticas']} confirmadas solas · {$r['sugeridas']} sugeridas.");
    } catch (Throwable $ex) {
        flash('No se pudo consultar Mercado Pago: ' . $ex->getMessage(), 'error');
    }
    ir('/admin/transferencias');
}

function adm_pago_conciliar(string $pago_id): void
{
    $yo = requerir_rol('admin');
    $orden = (int)($_POST['orden_id'] ?? 0);
    flash(conciliar_a_mano($pago_id, $orden, (int)$yo['id']) ? 'Confirmada con el pago de Mercado Pago. Le mandamos las entradas.' : 'No se pudo: el pago ya se usó o la orden ya no está pendiente.', 'ok');
    ir('/admin/transferencias?tab=' . ($_POST['volver'] ?? 'sugeridas'));
}

function adm_confirmar_sugeridas(): void
{
    $yo = requerir_rol('admin');
    $n = 0;
    foreach ((array)($_POST['par'] ?? []) as $par) {
        [$pago, $orden] = explode(':', (string)$par) + [1 => 0];
        if (conciliar_a_mano($pago, (int)$orden, (int)$yo['id'])) $n++;
    }
    flash($n ? "Confirmadas $n reservas con su pago de Mercado Pago." : 'No se confirmó ninguna.', $n ? 'ok' : 'error');
    ir('/admin/transferencias?tab=sugeridas');
}

function adm_pago_descartar(string $pago_id): void
{
    requerir_rol('admin');
    q('UPDATE pagos_mp SET descartado = 1, resultado = ? WHERE id = ? AND orden_id IS NULL', ['descartado: ' . mb_substr(trim((string)($_POST['motivo'] ?? 'no corresponde a una reserva')), 0, 120), $pago_id]);
    flash('Pago marcado como «no corresponde a una reserva».');
    ir('/admin/transferencias?tab=sin_reserva');
}

function adm_transferencias_ajustes(): void
{
    requerir_rol('admin');
    foreach (['transf_titular', 'transf_cuit', 'transf_banco', 'transf_cbu', 'transf_alias', 'transf_tipo_cuenta', 'transf_sucursal', 'transf_cuenta'] as $k) {
        guardar_ajuste($k, mb_substr(trim((string)($_POST[$k] ?? '')), 0, 80));
    }
    guardar_ajuste('transf_activa', !empty($_POST['transf_activa']) ? '1' : '0');
    guardar_ajuste('transf_horas', (string)max(1, min(168, (int)($_POST['transf_horas'] ?? 24))));
    guardar_ajuste('transf_cierre_horas', (string)max(1, min(168, (int)($_POST['transf_cierre_horas'] ?? 12))));
    $pct = (float)str_replace(',', '.', (string)($_POST['cargo_pct'] ?? '0'));
    guardar_ajuste('cargo_pct', number_format(max(0, min(20, $pct)), 2, '.', ''));
    if (($_POST['transf_cbu'] ?? '') !== '' && !preg_match('/^\d{22}$/', preg_replace('/\D/', '', $_POST['transf_cbu']))) {
        flash('Ojo: un CBU o CVU tiene 22 dígitos. Revisalo.', 'error');
    }
    flash('Datos de cobro guardados.');
    ir('/admin/cobro#cuenta');
}

function adm_orden_aprobar(string $id): void
{
    $yo = requerir_rol('admin');
    $o = fila('SELECT * FROM ordenes WHERE id = ?', [$id]) ?? no_encontrado();
    if (aprobar_transferencia((int)$id, (int)$yo['id'])) flash('Pago de la orden ' . $o['codigo'] . ' aprobado. Le mandamos las entradas con su QR.');
    else flash('La orden ' . $o['codigo'] . ' ya no estaba pendiente.', 'error');
    ir('/admin/transferencias');
}

function adm_orden_rechazar(string $id): void
{
    $yo = requerir_rol('admin');
    $o = fila('SELECT * FROM ordenes WHERE id = ?', [$id]) ?? no_encontrado();
    $motivo = trim((string)($_POST['motivo'] ?? '')) ?: 'No pudimos verificar la transferencia.';
    if ($o['estado'] !== 'pendiente') {
        flash('La orden ' . $o['codigo'] . ' ya no estaba pendiente.', 'error');
        ir('/admin/transferencias');
    }
    cancelar_orden((int)$id, mb_substr($motivo, 0, 200), (int)$yo['id']);
    correo_transferencia_cancelada((int)$id);
    flash('Orden ' . $o['codigo'] . ' rechazada: se liberaron los lugares y le avisamos al comprador.');
    ir('/admin/transferencias');
}

/** Guarda el texto que el navegador del admin leyó del comprobante. */
function api_orden_ocr(string $id): void
{
    requerir_rol('admin');
    $texto = mb_substr((string)($_POST['texto'] ?? ''), 0, 20000);
    q('UPDATE ordenes SET ocr = ? WHERE id = ? AND comprobante IS NOT NULL', [$texto, $id]);
    // Con el texto nuevo puede aparecer la prueba que le faltaba a un pago.
    if (mp_conectado()) conciliar_pagos();
    json_resp(['ok' => true]);
}

/** Para «Asignar» un pago suelto: la reserva pendiente con ese código. */
function api_orden_por_codigo(string $codigo): void
{
    requerir_rol('admin');
    $o = fila("SELECT o.*, u.nombre FROM ordenes o JOIN usuarios u ON u.id = o.usuario_id WHERE o.codigo = ? AND o.estado = 'pendiente'", [strtoupper($codigo)]);
    json_resp($o ? ['id' => (int)$o['id'], 'nombre' => $o['nombre'], 'importe' => pesos_cent(importe_orden($o))] : ['id' => null]);
}
