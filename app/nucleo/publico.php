<?php
// Lado del público: catálogo, mapa, ficha del evento, compra y reseñas.

function filtros_catalogo(): array
{
    return [
        'q' => trim($_GET['q'] ?? ''),
        'cat' => $_GET['cat'] ?? '',
        'cuando' => $_GET['cuando'] ?? '',
        'etiqueta' => $_GET['etiqueta'] ?? '',
        'disp' => ($_GET['disp'] ?? '1') === '1',
        'orden' => $_GET['orden'] ?? 'fecha',
    ];
}

/**
 * Funciones futuras de eventos publicados y públicos que cumplen los filtros,
 * con los datos del evento. El catálogo las agrupa por evento; el mapa las
 * usa sueltas, porque cada función puede estar en otro lugar.
 */
function buscar_funciones(array $f): array
{
    $where = ["e.estado = 'publicado'", "e.visibilidad = 'publica'", "f.estado = 'activa'", "COALESCE(f.fin, f.inicio) >= :ahora"];
    $p = [':ahora' => ahora()];
    if ($f['q'] !== '') {
        $where[] = "(e.titulo LIKE :q OR e.bajada LIKE :q OR e.descripcion LIKE :q OR f.lugar LIKE :q OR f.ciudad LIKE :q
                     OR EXISTS (SELECT 1 FROM evento_etiquetas ee JOIN etiquetas g ON g.id = ee.etiqueta_id WHERE ee.evento_id = e.id AND g.nombre LIKE :q))";
        $p[':q'] = '%' . $f['q'] . '%';
    }
    if ($f['cat'] !== '') {
        $where[] = 'c.slug = :cat';
        $p[':cat'] = $f['cat'];
    }
    if ($f['etiqueta'] !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM evento_etiquetas ee JOIN etiquetas g ON g.id = ee.etiqueta_id WHERE ee.evento_id = e.id AND g.slug = :et)';
        $p[':et'] = $f['etiqueta'];
    }
    $hoy = strtotime('today');
    $viernes = strtotime('friday this week', $hoy);
    $rangos = [
        'hoy' => [$hoy, strtotime('tomorrow')],
        'finde' => [max($hoy, $viernes), strtotime('monday next week', $hoy)],
        'semana' => [$hoy, strtotime('+7 days', $hoy)],
        'mes' => [$hoy, strtotime('+30 days', $hoy)],
    ];
    if (isset($rangos[$f['cuando']])) {
        $where[] = 'f.inicio >= :desde AND f.inicio < :hasta';
        $p[':desde'] = date('Y-m-d H:i:s', $rangos[$f['cuando']][0]);
        $p[':hasta'] = date('Y-m-d H:i:s', $rangos[$f['cuando']][1]);
    }
    return filas("SELECT f.*, f.id AS funcion_id, e.slug, e.titulo, e.bajada, e.organizador, e.creado AS evento_creado, c.nombre AS categoria, c.slug AS categoria_slug,
                    " . SQL_DESDE_F . " AS desde, " . SQL_DISPONIBLES_F . " AS disponibles, " . SQL_CAPACIDAD_F . " AS capacidad,
                    (SELECT archivo FROM evento_imagenes i WHERE i.evento_id = e.id ORDER BY i.orden, i.id LIMIT 1) AS portada
                  FROM funciones f JOIN eventos e ON e.id = f.evento_id LEFT JOIN categorias c ON c.id = e.categoria_id
                  WHERE " . implode(' AND ', $where) . " ORDER BY f.inicio", $p);
}

/** Lo que ya pasó, la función más reciente de cada evento primero. */
function funciones_pasadas(int $limite = 8): array
{
    $filas = filas("SELECT f.*, f.id AS funcion_id, e.slug, e.titulo, e.bajada, e.organizador, c.nombre AS categoria,
                      (SELECT archivo FROM evento_imagenes i WHERE i.evento_id = e.id ORDER BY i.orden, i.id LIMIT 1) AS portada
                    FROM funciones f JOIN eventos e ON e.id = f.evento_id LEFT JOIN categorias c ON c.id = e.categoria_id
                    WHERE e.estado = 'publicado' AND e.visibilidad = 'publica' AND f.estado = 'activa' AND COALESCE(f.fin, f.inicio) < ?
                    ORDER BY f.inicio DESC LIMIT 60", [ahora()]);
    $vistos = [];
    foreach ($filas as $fn) {
        if (isset($vistos[$fn['evento_id']])) continue;
        $fn['puntaje'] = puntaje_evento((int)$fn['evento_id']);
        $vistos[$fn['evento_id']] = $fn;
        if (count($vistos) >= $limite) break;
    }
    return array_values($vistos);
}

function categorias_y_etiquetas(): array
{
    $vivo = "e.estado = 'publicado' AND e.visibilidad = 'publica' AND EXISTS (SELECT 1 FROM funciones f WHERE f.evento_id = e.id AND f.estado = 'activa' AND COALESCE(f.fin, f.inicio) >= ?)";
    $categorias = filas("SELECT c.*, (SELECT COUNT(*) FROM eventos e WHERE e.categoria_id = c.id AND $vivo) AS n FROM categorias c ORDER BY c.orden, c.nombre", [ahora()]);
    $etiquetas = filas("SELECT g.nombre, g.slug, COUNT(*) AS n FROM etiquetas g JOIN evento_etiquetas ee ON ee.etiqueta_id = g.id
                        JOIN eventos e ON e.id = ee.evento_id AND $vivo GROUP BY g.id ORDER BY n DESC, g.nombre LIMIT 14", [ahora()]);
    return [$categorias, $etiquetas];
}

/**
 * Catálogo en lista o en mapa: los dos modos son la misma página con el mismo
 * encabezado, los mismos filtros y los mismos resultados; sólo cambia cómo se
 * muestran abajo (grilla de tarjetas o mapa). Así, pasar de uno a otro no
 * mueve nada de arriba.
 */
function pag_catalogo(string $modo = 'lista'): void
{
    $f = filtros_catalogo();
    $funciones = buscar_funciones($f);
    // Una tarjeta por evento: su próxima función (con lugar, si se pide sólo
    // disponibles) y cuántas fechas más tiene.
    $eventos = [];
    foreach ($funciones as $fn) {
        $id = $fn['evento_id'];
        $con_lugar = $fn['disponibles'] > 0;
        if (!isset($eventos[$id])) {
            $eventos[$id] = $fn + ['fechas' => 0, 'hay_lugar' => false, 'desde_min' => null];
        }
        $ev = &$eventos[$id];
        $ev['fechas']++;
        if ($con_lugar && !$ev['hay_lugar']) {
            // La tarjeta muestra la primera fecha CON lugar, no una agotada.
            $ev = array_merge($ev, array_intersect_key($fn, array_flip(['funcion_id', 'inicio', 'fin', 'lugar', 'ciudad', 'disponibles', 'capacidad'])));
            $ev['hay_lugar'] = true;
        }
        if ($fn['desde'] !== null && $con_lugar) $ev['desde_min'] = $ev['desde_min'] === null ? (int)$fn['desde'] : min($ev['desde_min'], (int)$fn['desde']);
        unset($ev);
    }
    foreach ($eventos as $id => $ev) $eventos[$id]['puntaje'] = puntaje_evento($id);
    $total = count($eventos);
    if ($f['disp']) $eventos = array_filter($eventos, fn($e) => $e['hay_lugar']);
    $ocultos = $total - count($eventos);
    $eventos = array_values($eventos);
    if ($f['orden'] === 'precio') usort($eventos, fn($a, $b) => ($a['desde_min'] ?? PHP_INT_MAX) <=> ($b['desde_min'] ?? PHP_INT_MAX));
    if ($f['orden'] === 'nuevos') usort($eventos, fn($a, $b) => strcmp($b['evento_creado'], $a['evento_creado']));
    if ($f['orden'] === 'valoracion') usort($eventos, fn($a, $b) => ($b['puntaje']['prom'] ?? 0) <=> ($a['puntaje']['prom'] ?? 0));

    // En el mapa va cada función por separado: un mismo evento puede estar en
    // dos lugares.
    $puntos = [];
    if ($modo === 'mapa') {
        foreach ($funciones as $fn) {
            if ($fn['lat'] === null || ($f['disp'] && $fn['disponibles'] <= 0)) continue;
            [$txt, $cls] = estado_stock((int)$fn['disponibles'], (int)$fn['capacidad']);
            $puntos[] = [
                'lat' => (float)$fn['lat'], 'lng' => (float)$fn['lng'],
                'titulo' => $fn['titulo'], 'url' => '/evento/' . $fn['slug'] . '?f=' . $fn['funcion_id'],
                'fecha' => fecha_corta($fn['inicio']) . ' h', 'lugar' => trim($fn['lugar'] . ($fn['ciudad'] ? ', ' . $fn['ciudad'] : ''), ', '),
                'imagen' => imagen_url($fn['portada'], 'm'), 'stock' => $txt, 'clase' => $cls,
                'precio' => $fn['desde'] === null ? '' : ((int)$fn['desde'] === 0 ? 'Sin cargo' : 'Desde ' . pesos((int)$fn['desde'])),
            ];
        }
    }
    [$categorias, $etiquetas] = categorias_y_etiquetas();
    // Con pocos eventos (un organizador con una fecha por mes) el buscador y los
    // filtros sobran: se muestran desde que hay más de seis o si ya se filtró.
    // Depende del total, no del modo, así lista y mapa quedan iguales arriba.
    $hay_filtros = $f['q'] !== '' || $f['cat'] !== '' || $f['cuando'] !== '' || $f['etiqueta'] !== '' || !$f['disp'] || $f['orden'] !== 'fecha';
    $con_filtros = $hay_filtros || $total > 6;
    // El destacado (el próximo con lugar, como flyer) y lo que ya pasó, sólo
    // en la lista sin filtrar.
    $destacado = null;
    $pasados = [];
    if ($modo === 'lista' && !$hay_filtros) {
        foreach ($eventos as $ev) if ($ev['hay_lugar']) { $destacado = $ev; break; }
        $pasados = funciones_pasadas();
    }
    vista('catalogo', compact('eventos', 'categorias', 'etiquetas', 'f', 'ocultos', 'modo', 'puntos', 'con_filtros', 'destacado', 'pasados'), [
        'titulo' => $modo === 'mapa' ? 'Mapa de eventos' : null,
        'descripcion' => $modo === 'mapa' ? 'Todos los eventos por ubicación.' : (ORGANIZADOR !== '' ? 'Entradas para los eventos de ' . ORGANIZADOR : 'Entradas para eventos') . ': comprá y entrá con el QR desde tu celular.',
        'canonical' => URL_PUBLICA . ($modo === 'mapa' ? '/mapa' : '/'),
        'imagen' => URL_PUBLICA . '/estaticos/compartir.jpg',
        'leaflet' => $modo === 'mapa',
    ]);
}

/** Para los buscadores: portada, legales y cada evento público con
 *  funciones por venir. */
function pag_sitemap(): void
{
    $urls = ['/', '/mapa', '/condiciones', '/privacidad', '/cookies', '/arrepentimiento'];
    foreach (filas("SELECT DISTINCT e.slug FROM eventos e JOIN funciones f ON f.evento_id = e.id
                    WHERE e.estado = 'publicado' AND e.visibilidad = 'publica' AND f.estado = 'activa' AND COALESCE(f.fin, f.inicio) >= ?", [ahora()]) as $e) {
        $urls[] = '/evento/' . $e['slug'];
    }
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) echo '  <url><loc>' . e(URL_PUBLICA . $u) . "</loc></url>\n";
    echo "</urlset>\n";
    exit;
}

function pag_mapa(): void
{
    pag_catalogo('mapa');
}

function evento_por_slug(string $slug): array
{
    $e = fila("SELECT e.*, c.nombre AS categoria, c.slug AS categoria_slug FROM eventos e
               LEFT JOIN categorias c ON c.id = e.categoria_id WHERE e.slug = ?", [$slug]);
    // Un borrador sólo lo ve el administrador (vista previa).
    if (!$e || ($e['estado'] === 'borrador' && !es_rol('admin'))) no_encontrado();
    return $e;
}

function pag_evento(string $slug): void
{
    $e = evento_por_slug($slug);
    $imagenes = filas('SELECT * FROM evento_imagenes WHERE evento_id = ? ORDER BY orden, id', [$e['id']]);
    $etiquetas = filas('SELECT g.* FROM etiquetas g JOIN evento_etiquetas ee ON ee.etiqueta_id = g.id WHERE ee.evento_id = ? ORDER BY g.nombre', [$e['id']]);
    $todas = funciones_de($e['id']);
    $proximas = array_values(array_filter($todas, fn($fn) => !terminada($fn) && $fn['estado'] === 'activa'));

    // Función elegida: la del ?f=, o la primera próxima con lugar, o la primera próxima.
    $sel = null;
    $pedida = (int)($_GET['f'] ?? 0);
    foreach ($todas as $fn) if ($fn['id'] === $pedida) $sel = $fn;
    if (!$sel) foreach ($proximas as $fn) if ($fn['disponibles'] > 0) { $sel = $fn; break; }
    $sel ??= $proximas[0] ?? (end($todas) ?: null);

    $tipos = [];
    if ($sel) {
        $tipos = array_values(array_filter(tipos_con_stock($sel['id']), fn($t) => $t['activo']));
        // El aforo puede ser menor que la suma de cupos: ningún tipo ofrece más
        // de lo que queda en el lugar.
        foreach ($tipos as &$t) $t['quedan'] = min($t['quedan'], (int)$sel['disponibles']);
        unset($t);
    }
    // Sin tarjeta, una función paga sólo se vende si todavía hay tiempo para
    // verificar una transferencia.
    $sin_medio = $sel && !tarjeta_activa() && max(array_column($tipos, 'precio') ?: [0]) > 0
        && !transferencia_disponible($sel['inicio']);
    $mias = 0;
    if (usuario()) {
        $mias = (int)valor("SELECT COUNT(*) FROM entradas x JOIN funciones f ON f.id = x.funcion_id WHERE f.evento_id = ? AND x.usuario_id = ? AND x.estado <> 'anulada' AND COALESCE(f.fin, f.inicio) >= ?",
            [$e['id'], usuario()['id'], ahora()]);
    }

    // Reseñas: de todas las funciones del evento, cada una con su fecha y lugar.
    $puntaje = puntaje_evento($e['id']);
    $resenas = filas('SELECT r.*, u.nombre, f.inicio, f.lugar, f.ciudad FROM resenas r JOIN usuarios u ON u.id = r.usuario_id
                      JOIN funciones f ON f.id = r.funcion_id WHERE f.evento_id = ? AND r.visible = 1 ORDER BY f.inicio DESC, r.id DESC LIMIT 60', [$e['id']]);
    $distribucion = array_fill(1, 5, 0);
    foreach (filas('SELECT r.puntaje, COUNT(*) AS n FROM resenas r JOIN funciones f ON f.id = r.funcion_id WHERE f.evento_id = ? AND r.visible = 1 GROUP BY r.puntaje', [$e['id']]) as $r) {
        $distribucion[(int)$r['puntaje']] = (int)$r['n'];
    }
    // ¿Puede reseñar? Sólo quien pasó por la puerta en una función ya
    // terminada de este evento y todavía no la reseñó.
    $para_resenar = [];
    if (usuario()) {
        $para_resenar = filas("SELECT DISTINCT f.* FROM entradas x JOIN funciones f ON f.id = x.funcion_id
                               WHERE f.evento_id = ? AND x.usuario_id = ? AND x.estado = 'usada' AND COALESCE(f.fin, f.inicio) < ?
                                 AND NOT EXISTS (SELECT 1 FROM resenas r WHERE r.funcion_id = f.id AND r.usuario_id = x.usuario_id)
                               ORDER BY f.inicio DESC", [$e['id'], usuario()['id'], ahora()]);
    }

    $portada = $imagenes[0]['archivo'] ?? null;
    $canonical = URL_PUBLICA . '/evento/' . $e['slug'];
    $desc = $e['meta_descripcion'] ?: $e['bajada'];
    $jsonld = [];
    foreach ($proximas ?: ($sel ? [$sel] : []) as $fn) {
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $e['titulo'],
            'startDate' => date('c', strtotime($fn['inicio'])),
            'eventStatus' => $e['estado'] === 'cancelado' || $fn['estado'] === 'cancelada' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'description' => $desc,
            'location' => [
                '@type' => 'Place',
                'name' => $fn['lugar'] ?: $fn['ciudad'],
                'address' => ['@type' => 'PostalAddress', 'streetAddress' => $fn['direccion'], 'addressLocality' => $fn['ciudad'], 'addressCountry' => 'AR'],
            ],
        ];
        if ($fn['lat'] !== null) $ld['location']['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => (float)$fn['lat'], 'longitude' => (float)$fn['lng']];
        if ($fn['fin']) $ld['endDate'] = date('c', strtotime($fn['fin']));
        if ($portada) $ld['image'] = [URL_PUBLICA . imagen_url($portada)];
        if ($e['organizador']) $ld['organizer'] = ['@type' => 'Organization', 'name' => $e['organizador'], 'url' => URL_PUBLICA];
        if ($fn['desde'] !== null) $ld['offers'] = ['@type' => 'Offer', 'price' => (string)$fn['desde'], 'priceCurrency' => 'ARS',
            'availability' => $fn['disponibles'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'url' => $canonical . '?f=' . $fn['id'], 'validFrom' => date('c', strtotime($e['creado']))];
        if ($puntaje['n']) $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (string)$puntaje['prom'], 'reviewCount' => $puntaje['n'], 'bestRating' => '5', 'worstRating' => '1'];
        $jsonld[] = $ld;
    }

    // Lo que se ve al compartir: título, fecha de la función y lugar.
    $og_desc = $sel ? ucfirst(fecha_corta($sel['inicio'])) . ' h' . ($sel['lugar'] ? ' · ' . $sel['lugar'] : '') . ($desc ? ' — ' . $desc : '') : $desc;
    vista('evento', compact('e', 'imagenes', 'etiquetas', 'todas', 'proximas', 'sel', 'tipos', 'mias', 'sin_medio', 'puntaje', 'resenas', 'distribucion', 'para_resenar', 'canonical'), [
        'titulo' => $e['meta_titulo'] ?: $e['titulo'],
        'descripcion' => $desc,
        'og_descripcion' => $og_desc,
        'palabras' => $e['meta_palabras'],
        'imagen' => $portada ? URL_PUBLICA . imagen_url($portada, 'og') : null,
        'imagen_ancho' => 1200, 'imagen_alto' => 630,
        'canonical' => $canonical,
        'og_tipo' => 'event',
        'jsonld' => count($jsonld) === 1 ? $jsonld[0] : $jsonld,
        'noindex' => $e['visibilidad'] === 'enlace' || $e['estado'] !== 'publicado',
        'leaflet' => $sel && $sel['lat'] !== null,
    ]);
}

/** El paso del evento al checkout: guarda la selección y pide login si hace falta. */
function acc_elegir_entradas(string $slug): void
{
    $e = evento_por_slug($slug);
    $fid = (int)($_POST['funcion_id'] ?? 0);
    if (!valor('SELECT 1 FROM funciones WHERE id = ? AND evento_id = ?', [$fid, $e['id']])) no_encontrado();
    $cant = array_filter(array_map('intval', (array)($_POST['cant'] ?? [])), fn($n) => $n > 0);
    if (!$cant) {
        flash('Elegí al menos una entrada.', 'error');
        ir("/evento/$slug?f=$fid#entradas");
    }
    $_SESSION['carrito'] = ['funcion_id' => $fid, 'cant' => $cant];
    ir('/checkout');
}

/** Valida el carrito contra el stock actual. Devuelve [función+evento, renglones, total, errores, n]. */
function armar_carrito(): array
{
    $c = $_SESSION['carrito'] ?? null;
    if (!$c || empty($c['funcion_id'])) ir('/');
    $fn = fila('SELECT f.*, e.titulo, e.slug, e.estado AS evento_estado, e.max_por_compra FROM funciones f JOIN eventos e ON e.id = f.evento_id WHERE f.id = ?', [$c['funcion_id']]);
    if (!$fn || $fn['evento_estado'] !== 'publicado' || $fn['estado'] !== 'activa') {
        unset($_SESSION['carrito']);
        flash('Esa función ya no está a la venta.', 'error');
        ir('/');
    }
    $tipos = [];
    foreach (tipos_con_stock($fn['id']) as $t) $tipos[$t['id']] = $t;
    $quedan = min($fn['aforo'], array_sum(array_map(fn($t) => $t['activo'] ? $t['cupo'] : 0, $tipos))) - vendidas_funcion($fn['id']);
    $errores = [];
    $renglones = [];
    $total = 0;
    $n = 0;
    foreach ($c['cant'] as $tid => $cant) {
        $t = $tipos[$tid] ?? null;
        if (!$t || !$t['activo']) {
            $errores[] = 'Uno de los tipos de entrada ya no está disponible.';
            continue;
        }
        if ($cant > $t['quedan']) $errores[] = $t['quedan'] > 0 ? "De «{$t['nombre']}» quedan {$t['quedan']}." : "«{$t['nombre']}» se agotó.";
        $renglones[] = ['tipo' => $t, 'cant' => $cant, 'subtotal' => $cant * $t['precio']];
        $total += $cant * $t['precio'];
        $n += $cant;
    }
    if ($n > $fn['max_por_compra']) $errores[] = "Podés comprar hasta {$fn['max_por_compra']} entradas por compra.";
    if ($n > $quedan) $errores[] = $quedan > 0 ? "Quedan $quedan lugares para esta función." : 'La función se agotó.';
    if (terminada($fn)) $errores[] = 'La función ya terminó.';
    // Cupón: el total que vuelve ya lo descuenta. Si dejó de valer (venció,
    // se agotó), vuelve el error y el que llama decide.
    $cup = [];
    if (!empty($c['cupon']) && usuario()) {
        $cup = cupon_aplicar($c['cupon'], $fn, $total, (int)usuario()['id']);
        if (!empty($cup['descuento'])) $total -= $cup['descuento'];
    }
    return [$fn, $renglones, $total, $errores, $n, $cup];
}

function pag_checkout(): void
{
    $u = requerir_login();
    [$fn, $renglones, $total, $errores, $n, $cup] = armar_carrito();
    if (!empty($cup['error'])) {
        unset($_SESSION['carrito']['cupon']);
        flash($cup['error'] . ' Lo sacamos de la compra.', 'error');
        ir('/checkout');
    }
    $portada = valor('SELECT archivo FROM evento_imagenes WHERE evento_id = ? ORDER BY orden, id LIMIT 1', [$fn['evento_id']]);
    $tarjeta = tarjeta_activa();
    $cargo = $tarjeta ? cargo_servicio($total) : 0;
    $transf = $total > 0 && transferencia_disponible($fn['inicio']);
    vista('checkout', compact('u', 'fn', 'renglones', 'total', 'errores', 'n', 'portada', 'cargo', 'transf', 'tarjeta', 'cup'), ['titulo' => 'Confirmar compra', 'noindex' => true]);
}

function acc_confirmar_compra(): void
{
    $u = requerir_login();
    if (empty($_POST['acepto'])) {
        flash('Tenés que aceptar las condiciones de la compra.', 'error');
        ir('/checkout');
    }
    $titulares = array_map(fn($s) => mb_substr(trim((string)$s), 0, 80), (array)($_POST['titular'] ?? []));
    // Dos medios: tarjeta (Mercado Pago, con cargo por servicio que paga el
    // comprador) o transferencia (sin cargo, se confirma a mano).
    $medio = ($_POST['medio'] ?? '') === 'transferencia' ? 'transferencia' : 'tarjeta';

    $pdo = db();
    // BEGIN IMMEDIATE toma el candado de escritura antes de leer el stock: dos
    // compras simultáneas de la última entrada no pueden pasar las dos.
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        [$fn, $renglones, $total, $errores, , $cup] = armar_carrito();
        if (!empty($cup['error'])) {
            // El cupón se revalida bajo el candado: dos compras no pasan el tope.
            unset($_SESSION['carrito']['cupon']);
            $errores[] = $cup['error'] . ' Lo sacamos de la compra: revisá el total.';
        }
        if ($errores) {
            $pdo->exec('ROLLBACK');
            foreach ($errores as $err) flash($err, 'error');
            ir('/checkout');
        }
        $cupon_id = $cup['cupon']['id'] ?? null;
        $descuento = (int)($cup['descuento'] ?? 0);
        // Entradas sin cargo: no hay nada que pagar. Con la tarjeta apagada
        // (decisión de 2026-10-03), todo lo que se paga va por transferencia.
        if ($total === 0) $medio = 'gratis';
        elseif (!tarjeta_activa()) $medio = 'transferencia';
        if ($medio === 'transferencia' && !transferencia_disponible($fn['inicio'])) {
            $pdo->exec('ROLLBACK');
            flash('La venta online para esta función ya cerró: el pago por transferencia necesita tiempo para verificarse.', 'error');
            ir('/checkout');
        }
        do $codigo = codigo_aleatorio(8); while (valor('SELECT 1 FROM ordenes WHERE codigo = ?', [$codigo]));
        if ($medio === 'transferencia') {
            q('INSERT INTO ordenes (codigo, usuario_id, funcion_id, total, cargo, estado, medio_pago, vence, importe_cent, cupon_id, descuento, creado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$codigo, $u['id'], $fn['id'], $total, 0, 'pendiente', 'transferencia', vencimiento_transferencia($fn['inicio']), null, $cupon_id, $descuento, ahora()]);
        } else {
            $cargo = $medio === 'gratis' ? 0 : cargo_servicio($total);
            q('INSERT INTO ordenes (codigo, usuario_id, funcion_id, total, cargo, estado, medio_pago, cupon_id, descuento, creado) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$codigo, $u['id'], $fn['id'], $total + $cargo, $cargo, 'pagada', $medio === 'gratis' ? 'sin cargo' : (PAGO_SIMULADO ? 'prueba' : 'mercadopago'), $cupon_id, $descuento, ahora()]);
        }
        $orden_id = (int)$pdo->lastInsertId();
        // Lo que se pagó por cada entrada: con cupón, el descuento se reparte
        // en proporción y la última absorbe el redondeo, así las ventas
        // (que suman entradas.precio) dan lo cobrado.
        $precios = [];
        foreach ($renglones as $r) for ($k = 0; $k < $r['cant']; $k++) $precios[] = (int)$r['tipo']['precio'];
        $subtotal = array_sum($precios);
        if ($descuento && $subtotal) {
            foreach ($precios as &$pr) $pr = (int)round($pr * ($subtotal - $descuento) / $subtotal);
            unset($pr);
            $precios[count($precios) - 1] += ($subtotal - $descuento) - array_sum($precios);
        }
        $i = 0;
        foreach ($renglones as $r) {
            for ($k = 0; $k < $r['cant']; $k++, $i++) {
                do $cod = codigo_aleatorio(10); while (valor('SELECT 1 FROM entradas WHERE codigo = ?', [$cod]));
                $titular = ($titulares[$i] ?? '') !== '' ? $titulares[$i] : $u['nombre'];
                q('INSERT INTO entradas (codigo, orden_id, funcion_id, tipo_id, usuario_id, titular, precio, creado) VALUES (?,?,?,?,?,?,?,?)',
                    [$cod, $orden_id, $fn['id'], $r['tipo']['id'], $u['id'], $titular, $precios[$i], ahora()]);
            }
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        // inTransaction() no ve un BEGIN hecho con exec(): se intenta igual.
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $ex;
    }
    unset($_SESSION['carrito']);
    if ($medio === 'transferencia') {
        correo_transferencia_reserva($orden_id);
        flash('Reservamos tus entradas. Transferí y subí el comprobante para confirmarlas.');
    } else {
        correo_compra($orden_id);
        flash('¡Listo! Tus entradas ya están en tu cuenta y te las mandamos por correo.');
    }
    ir('/orden/' . $codigo);
}

function pag_orden(string $codigo): void
{
    $u = requerir_login();
    $o = fila('SELECT o.*, e.titulo, e.slug, f.inicio, f.lugar, f.direccion, f.ciudad FROM ordenes o JOIN funciones f ON f.id = o.funcion_id
               JOIN eventos e ON e.id = f.evento_id WHERE o.codigo = ?', [$codigo]);
    if (!$o || ($o['usuario_id'] != $u['id'] && $u['rol'] !== 'admin')) no_encontrado();
    $entradas = filas('SELECT x.*, t.nombre AS tipo FROM entradas x JOIN tipos_entrada t ON t.id = x.tipo_id WHERE x.orden_id = ? ORDER BY x.id', [$o['id']]);
    vista('orden', compact('o', 'entradas'), ['titulo' => 'Compra ' . $o['codigo'], 'noindex' => true]);
}

function pag_mis_entradas(): void
{
    $u = requerir_login();
    $todas = filas("SELECT x.*, t.nombre AS tipo, e.titulo, e.slug, f.inicio, f.fin, f.lugar, f.ciudad, f.evento_id, f.estado AS funcion_estado, e.estado AS evento_estado,
                      (SELECT archivo FROM evento_imagenes i WHERE i.evento_id = e.id ORDER BY i.orden, i.id LIMIT 1) AS portada,
                      (SELECT 1 FROM resenas r WHERE r.funcion_id = f.id AND r.usuario_id = x.usuario_id) AS resenada,
                      o.estado AS orden_estado, o.codigo AS orden_codigo
                    FROM entradas x JOIN funciones f ON f.id = x.funcion_id JOIN eventos e ON e.id = f.evento_id JOIN tipos_entrada t ON t.id = x.tipo_id
                    JOIN ordenes o ON o.id = x.orden_id
                    WHERE x.usuario_id = ? ORDER BY f.inicio, x.id", [$u['id']]);
    $proximos = [];
    $pasados = [];
    foreach ($todas as $x) {
        if (terminada($x)) $pasados[$x['funcion_id']][] = $x;
        else $proximos[$x['funcion_id']][] = $x;
    }
    $pasados = array_reverse($pasados, true);
    vista('mis_entradas', compact('proximos', 'pasados'), ['titulo' => 'Mis entradas', 'noindex' => true]);
}

function pag_entrada(string $codigo): void
{
    $u = requerir_login();
    $x = fila("SELECT x.*, t.nombre AS tipo, e.titulo, e.slug, f.inicio, f.lugar, f.direccion, f.ciudad, o.codigo AS orden, o.estado AS orden_estado
               FROM entradas x JOIN funciones f ON f.id = x.funcion_id JOIN eventos e ON e.id = f.evento_id
               JOIN tipos_entrada t ON t.id = x.tipo_id JOIN ordenes o ON o.id = x.orden_id WHERE x.codigo = ?", [$codigo]);
    if (!$x || ($x['usuario_id'] != $u['id'] && $u['rol'] !== 'admin')) no_encontrado();
    // Las otras entradas de la misma compra: desde un solo celular se puede
    // hacer entrar a todo el grupo, pasando de una a otra.
    $hermanas = array_column(filas('SELECT codigo FROM entradas WHERE orden_id = ? ORDER BY id', [$x['orden_id']]), 'codigo');
    $pos = array_search($x['codigo'], $hermanas, true);
    vista('entrada', compact('x', 'hermanas', 'pos'), ['titulo' => 'Entrada · ' . $x['titulo'], 'noindex' => true, 'cuerpo_clase' => 'pantalla-entrada']);
}

function acc_resena(string $slug): void
{
    $u = requerir_login();
    $e = evento_por_slug($slug);
    $fid = (int)($_POST['funcion_id'] ?? 0);
    $puntaje = (int)($_POST['puntaje'] ?? 0);
    $texto = trim(mb_substr((string)($_POST['texto'] ?? ''), 0, 1200));
    $valida = valor("SELECT 1 FROM entradas x JOIN funciones f ON f.id = x.funcion_id WHERE f.id = ? AND f.evento_id = ? AND x.usuario_id = ?
                     AND x.estado = 'usada' AND COALESCE(f.fin, f.inicio) < ?", [$fid, $e['id'], $u['id'], ahora()]);
    if (!$valida) {
        flash('Sólo pueden opinar quienes asistieron a esa función.', 'error');
        ir("/evento/$slug#resenas");
    }
    if ($puntaje < 1 || $puntaje > 5) {
        flash('Elegí de una a cinco estrellas.', 'error');
        ir("/evento/$slug#resenas");
    }
    q('INSERT INTO resenas (funcion_id, usuario_id, puntaje, texto, creado) VALUES (?,?,?,?,?)
       ON CONFLICT (funcion_id, usuario_id) DO UPDATE SET puntaje = excluded.puntaje, texto = excluded.texto', [$fid, $u['id'], $puntaje, $texto ?: null, ahora()]);
    flash('¡Gracias por tu opinión!');
    ir("/evento/$slug#resenas");
}

function pag_legal(string $cual): void
{
    $titulos = ['condiciones' => 'Condiciones de Servicio', 'privacidad' => 'Política de Privacidad', 'cookies' => 'Política de Cookies'];
    vista('legal/' . $cual, [], ['titulo' => $titulos[$cual], 'canonical' => URL_PUBLICA . '/' . $cual]);
}

/** Estado de una entrada, para que la pantalla del QR se entere cuando la queman en la puerta. */
function api_entrada_estado(string $codigo): void
{
    $u = usuario();
    $x = $u ? fila('SELECT x.estado, x.usada_en, x.usuario_id, o.estado AS pago FROM entradas x JOIN ordenes o ON o.id = x.orden_id WHERE x.codigo = ?', [$codigo]) : null;
    if (!$x || ($x['usuario_id'] != $u['id'] && $u['rol'] !== 'admin')) json_resp(['estado' => null], 404);
    json_resp(['estado' => $x['estado'], 'pago' => $x['pago'], 'usada_en' => $x['usada_en'] ? fecha_corta($x['usada_en']) . ' h' : null]);
}

// ── Comprobante de transferencia ───────────────────────────────────────────

function acc_comprobante(string $codigo): void
{
    $u = requerir_login();
    $o = fila('SELECT * FROM ordenes WHERE codigo = ?', [$codigo]);
    if (!$o || $o['usuario_id'] != $u['id']) no_encontrado();
    if ($o['estado'] !== 'pendiente' || $o['medio_pago'] !== 'transferencia') {
        flash('Esta compra ya no espera un comprobante.', 'error');
        ir('/orden/' . $codigo);
    }
    [$nombre, $error] = guardar_comprobante($o, $_FILES['comprobante'] ?? []);
    if ($error) {
        flash($error, 'error');
        ir('/orden/' . $codigo . '#comprobante');
    }
    // Si reemplaza uno anterior, el viejo se borra.
    if ($o['comprobante']) @unlink(dir_comprobantes() . '/' . $o['comprobante']);
    q('UPDATE ordenes SET comprobante = ?, comprobante_en = ?, ocr = NULL WHERE id = ?', [$nombre, ahora(), $o['id']]);
    // Con Mercado Pago conectado la mayoría se confirma sola y lo que queda
    // se ve en la insignia del panel: un correo por comprobante gastaría el
    // tope de la casilla (100/h) justo cuando hay volumen.
    if (!mp_conectado()) correo_comprobante_recibido((int)$o['id']);
    flash('Recibimos el comprobante. Lo estamos cruzando con los pagos de la cuenta.');
    ir('/orden/' . $codigo);
}

/**
 * El celular del comprador lee el comprobante apenas lo sube (ocr.js) y manda
 * el texto. Se cruza en el momento con los pagos de Mercado Pago: si el pago
 * ya entró, la compra queda confirmada antes de que cierre la página. El
 * texto es del comprador, así que sólo sirve para encontrar un pago real de
 * la cuenta (ver conciliar_pagos), nunca para confirmar por sí solo.
 */
function api_comprador_ocr(string $codigo): void
{
    $u = usuario();
    $o = $u ? fila('SELECT * FROM ordenes WHERE codigo = ?', [$codigo]) : null;
    if (!$o || $o['usuario_id'] != $u['id']) json_resp(['ok' => false], 404);
    if ($o['estado'] !== 'pendiente' || !$o['comprobante'] || $o['ocr'] !== null) json_resp(['ok' => true, 'confirmada' => $o['estado'] === 'pagada']);
    q('UPDATE ordenes SET ocr = ? WHERE id = ? AND ocr IS NULL', [mb_substr((string)($_POST['texto'] ?? ''), 0, 20000), $o['id']]);
    if (mp_conectado()) {
        conciliar_pagos();
        // Mercado Pago no siempre avisa de una transferencia que entra al CVU
        // (en las pruebas reales no llegó ningún aviso): si
        // todavía no está, se le pregunta ahora, como mucho una vez cada 20 s
        // entre todos los compradores, en vez de esperar la tarea de 15 min.
        $ultima = strtotime(ajuste('mp_ultima_sync') ?: '2000-01-01');
        if (valor('SELECT estado FROM ordenes WHERE id = ?', [$o['id']]) === 'pendiente' && time() - $ultima > 20) {
            guardar_ajuste('mp_ultima_sync', ahora()); // el candado, antes de llamar
            try { mp_sincronizar(1); } catch (Throwable $ex) { error_log('[app mp] ' . $ex->getMessage()); }
        }
    }
    json_resp(['ok' => true, 'confirmada' => valor('SELECT estado FROM ordenes WHERE id = ?', [$o['id']]) === 'pagada']);
}

/** El archivo del comprobante: sólo lo ven quien compró y la administración. */
function ver_comprobante(string $codigo): void
{
    $u = requerir_login();
    $o = fila('SELECT * FROM ordenes WHERE codigo = ?', [$codigo]);
    if (!$o || !$o['comprobante'] || ($o['usuario_id'] != $u['id'] && $u['rol'] !== 'admin')) no_encontrado();
    $f = dir_comprobantes() . '/' . $o['comprobante'];
    if (!is_file($f)) no_encontrado();
    header('Content-Type: ' . tipo_comprobante($o['comprobante']));
    header('Content-Disposition: inline; filename="comprobante-' . $o['codigo'] . '.' . pathinfo($f, PATHINFO_EXTENSION) . '"');
    header('Cache-Control: private, no-store');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}
