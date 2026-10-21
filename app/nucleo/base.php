<?php
// Núcleo: base de datos, sesión, seguridad y utilidades de presentación.

require_once __DIR__ . '/config.php';

// ── Base de datos ──────────────────────────────────────────────────────────

// Cambios de esquema sobre una base que ya existe. Se aplican solos, una vez,
// en orden, y PRAGMA user_version recuerda hasta dónde se llegó. Una base nueva
// sale de esquema.sql ya completa y arranca en la última versión. Nunca borrar
// ni reordenar entradas: sólo agregar al final.
const MIGRACIONES = [
    1 => 'ALTER TABLE correos ADD COLUMN adjuntos TEXT',
    // Pago por transferencia con comprobante (2026-10-03) y cargo por servicio.
    2 => 'ALTER TABLE ordenes ADD COLUMN cargo INTEGER NOT NULL DEFAULT 0',
    3 => 'ALTER TABLE ordenes ADD COLUMN vence TEXT',
    4 => 'ALTER TABLE ordenes ADD COLUMN comprobante TEXT',
    5 => 'ALTER TABLE ordenes ADD COLUMN comprobante_en TEXT',
    6 => 'ALTER TABLE ordenes ADD COLUMN verificada_por INTEGER',
    7 => 'ALTER TABLE ordenes ADD COLUMN verificada_en TEXT',
    8 => 'ALTER TABLE ordenes ADD COLUMN nota TEXT',
    // Texto leído del comprobante (OCR en el navegador del admin), para no
    // volver a leerlo cada vez que se abre la bandeja.
    9 => 'ALTER TABLE ordenes ADD COLUMN ocr TEXT',
    // Conciliación automática con Mercado Pago (2026-10-03). importe_cent fue
    // para pedir centavos únicos por reserva; se descartó el mismo día (se
    // cruza con el comprobante) y queda NULL en las nuevas.
    10 => 'ALTER TABLE ordenes ADD COLUMN importe_cent INTEGER',
    11 => 'ALTER TABLE ordenes ADD COLUMN mp_pago_id TEXT',
    12 => 'ALTER TABLE ordenes ADD COLUMN conciliacion TEXT',
    13 => "CREATE TABLE IF NOT EXISTS pagos_mp (
        id TEXT PRIMARY KEY, fecha TEXT, importe_cent INTEGER NOT NULL, estado TEXT, tipo TEXT,
        pagador TEXT, pagador_doc TEXT, descripcion TEXT, crudo TEXT,
        orden_id INTEGER REFERENCES ordenes(id), resultado TEXT, descartado INTEGER NOT NULL DEFAULT 0,
        recibido TEXT NOT NULL DEFAULT (datetime('now','localtime')))",
    14 => 'CREATE INDEX IF NOT EXISTS ix_pagos_mp_importe ON pagos_mp(importe_cent, orden_id)',
    15 => 'CREATE INDEX IF NOT EXISTS ix_ordenes_importe ON ordenes(importe_cent, estado)',
    // Ofertas y cupones. Precio de oferta por
    // tipo de entrada, vigente hasta una fecha (NULL = hasta que se saque).
    16 => 'ALTER TABLE tipos_entrada ADD COLUMN precio_oferta INTEGER',
    17 => 'ALTER TABLE tipos_entrada ADD COLUMN oferta_hasta TEXT',
    18 => "CREATE TABLE IF NOT EXISTS cupones (
        id INTEGER PRIMARY KEY, codigo TEXT NOT NULL UNIQUE COLLATE NOCASE, tipo TEXT NOT NULL CHECK (tipo IN ('pct','monto')),
        valor INTEGER NOT NULL, evento_id INTEGER REFERENCES eventos(id) ON DELETE CASCADE, usos_max INTEGER, por_persona INTEGER NOT NULL DEFAULT 1,
        desde TEXT, vence TEXT, activo INTEGER NOT NULL DEFAULT 1, nota TEXT,
        creado TEXT NOT NULL DEFAULT (datetime('now','localtime')))",
    19 => 'ALTER TABLE ordenes ADD COLUMN cupon_id INTEGER REFERENCES cupones(id)',
    20 => 'ALTER TABLE ordenes ADD COLUMN descuento INTEGER NOT NULL DEFAULT 0',
    // Botón de arrepentimiento (2026-10-04): un trámite por pedido, con código.
    21 => "CREATE TABLE IF NOT EXISTS arrepentimientos (
        id INTEGER PRIMARY KEY, codigo TEXT NOT NULL UNIQUE, orden_id INTEGER NOT NULL REFERENCES ordenes(id),
        nombre TEXT, email TEXT NOT NULL, reintegro TEXT, comentario TEXT,
        estado TEXT NOT NULL CHECK (estado IN ('a_revisar','aceptado','resuelto','rechazado')),
        importe INTEGER NOT NULL DEFAULT 0, detalle TEXT, ip TEXT, nota TEXT,
        resuelto_por INTEGER REFERENCES usuarios(id), resuelto_en TEXT,
        creado TEXT NOT NULL DEFAULT (datetime('now','localtime')))",
    22 => 'CREATE INDEX IF NOT EXISTS ix_arrepentimientos_orden ON arrepentimientos(orden_id)',
];

function migrar(PDO $pdo): void
{
    $ultima = max(array_keys(MIGRACIONES));
    if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() >= $ultima) return;
    // Candado de escritura y se relee la versión: dos visitas a la vez no
    // pueden aplicar la misma migración dos veces.
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
        foreach (MIGRACIONES as $n => $sql) {
            if ($n > $v) $pdo->exec($sql);
        }
        $pdo->exec('PRAGMA user_version = ' . $ultima);
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}
        throw $ex;
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    if (!is_dir(DIR_DATOS)) mkdir(DIR_DATOS, 0750, true);
    $nueva = !file_exists(ARCHIVO_BASE);
    $pdo = new PDO('sqlite:' . ARCHIVO_BASE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 8000;');
    if ($nueva) {
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec(file_get_contents(__DIR__ . '/esquema.sql'));
        $pdo->exec('PRAGMA user_version = ' . max(array_keys(MIGRACIONES)));
    } else {
        migrar($pdo);
    }
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}

function fila(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r ?: null;
}

function filas(string $sql, array $p = []): array
{
    return q($sql, $p)->fetchAll();
}

function valor(string $sql, array $p = [])
{
    return q($sql, $p)->fetchColumn();
}

function ahora(): string
{
    return date('Y-m-d H:i:s');
}

// ── Sesión, usuario y roles ────────────────────────────────────────────────

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('tqr');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => ES_PROD,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (ES_PROD) {
        // El /tmp de un hosting compartido lo limpia quien quiera y cuando
        // quiera: las sesiones viven junto a la base.
        $dir = DIR_DATOS . '/sesiones';
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
    }
    session_start();
}

function usuario(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $id = $_SESSION['uid'] ?? null;
    $u = $id ? fila('SELECT * FROM usuarios WHERE id = ? AND activo = 1', [$id]) : null;
    return $u;
}

function es_rol(string ...$roles): bool
{
    $u = usuario();
    return $u && in_array($u['rol'], $roles, true);
}

function requerir_login(): array
{
    $u = usuario();
    if (!$u) {
        flash('Ingresá con tu cuenta para seguir.', 'info');
        ir('/ingresar?volver=' . rawurlencode($_SERVER['REQUEST_URI']));
    }
    return $u;
}

function requerir_rol(string ...$roles): array
{
    $u = requerir_login();
    if (!in_array($u['rol'], $roles, true)) {
        http_response_code(403);
        vista('error', ['titulo' => 'Sin permiso', 'mensaje' => 'Tu cuenta no tiene acceso a esta sección.']);
        exit;
    }
    return $u;
}

function entrar_como(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
}

// ── CSRF ───────────────────────────────────────────────────────────────────

function csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf() . '">';
}

function verificar_csrf(): void
{
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!hash_equals(csrf(), (string)$t)) {
        if (str_starts_with(ruta_actual(), '/api/')) json_resp(['resultado' => 'error', 'mensaje' => 'La sesión venció: recargá la página.'], 400);
        http_response_code(400);
        vista('error', ['titulo' => 'La página venció', 'mensaje' => 'Volvé atrás, recargá la página y probá de nuevo.']);
        exit;
    }
}

// ── Respuestas ─────────────────────────────────────────────────────────────

function ir(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

function flash(string $texto, string $tipo = 'ok'): void
{
    $_SESSION['flash'][] = [$tipo, $texto];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function json_resp(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Renderiza una vista dentro del layout. $meta: titulo, descripcion, imagen, canonical, jsonld, cuerpo_clase. */
function vista(string $nombre, array $vars = [], array $meta = []): void
{
    extract($vars);
    ob_start();
    require DIR_APP . '/vistas/' . $nombre . '.php';
    $contenido = ob_get_clean();
    $meta += ['titulo' => $vars['titulo'] ?? null];
    require DIR_APP . '/vistas/layout.php';
}

function no_encontrado(): never
{
    http_response_code(404);
    vista('error', ['titulo' => 'No encontrado', 'mensaje' => 'La página que buscás no existe o ya no está disponible.']);
    exit;
}

// ── Presentación ───────────────────────────────────────────────────────────

/** El nombre de la app como marca: la última palabra va en el color de acento. */
function marca_html(): string
{
    $partes = preg_split('/\s+/u', trim(APP_NOMBRE));
    $ultima = array_pop($partes);
    return ($partes ? '<span class="marca-pre">' . e(implode(' ', $partes)) . '</span>' : '') . '<span class="marca-ya">' . e($ultima) . '</span>';
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pesos(int $n): string
{
    return $n === 0 ? 'Sin cargo' : '$ ' . number_format($n, 0, ',', '.');
}

const DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
const DIAS_LARGOS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const MESES_LARGOS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/** «sáb 18 oct · 22:00» */
function fecha_corta(string $f): string
{
    $t = strtotime($f);
    return DIAS[date('w', $t)] . ' ' . date('j', $t) . ' ' . MESES[date('n', $t) - 1] . ' · ' . date('H:i', $t);
}

/** «sábado 18 de octubre de 2026» */
function fecha_larga(string $f): string
{
    $t = strtotime($f);
    return DIAS_LARGOS[date('w', $t)] . ' ' . date('j', $t) . ' de ' . MESES_LARGOS[date('n', $t) - 1] . ' de ' . date('Y', $t);
}

function hora(string $f): string
{
    return date('H:i', strtotime($f));
}

function slug(string $s): string
{
    $s = strtr(mb_strtolower(trim($s)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'evento';
}

/** Párrafos a partir de texto plano con líneas en blanco. */
function parrafos(?string $texto): string
{
    $out = '';
    foreach (preg_split("/\R{2,}/", trim((string)$texto)) as $p) {
        if ($p !== '') $out .= '<p>' . nl2br(e($p)) . '</p>';
    }
    return $out;
}

/** Tamaños: g (1800 px), m (720 px), og (1200×630, para compartir). */
/** URL de un estático con la versión publicada: «/estaticos/app.css?v=…». */
function estatico(string $ruta): string
{
    return '/estaticos/' . $ruta . '?v=' . rawurlencode(VERSION_APP);
}

function imagen_url(?string $archivo, string $tamano = 'g'): ?string
{
    if (!$archivo) return null;
    $suf = ['g' => '', 'm' => '-m', 'og' => '-og'][$tamano] ?? '';
    return URL_SUBIDAS . '/eventos/' . $archivo . $suf . '.jpg';
}

function codigo_aleatorio(int $largo): string
{
    // Sin 0/O ni 1/I/L: los códigos se dictan y se tipean en la puerta.
    $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $largo; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
    return $s;
}

function ruta_actual(): string
{
    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
}

function activo(string $prefijo): string
{
    $r = ruta_actual();
    $si = $prefijo === '/' ? $r === '/' : str_starts_with($r, $prefijo);
    return $si ? ' aria-current="page"' : '';
}

// ── Ajustes editables desde el panel ───────────────────────────────────────

const AJUSTES_DEFECTO = [
    'transf_activa' => '1',
    // Para transferir alcanzan alias o CBU/CVU; el resto es para depositar en
    // cajero o ventanilla.
    'transf_alias' => '',
    'transf_cbu' => '',
    'transf_titular' => '',
    'transf_cuit' => '',
    'transf_banco' => '',
    'transf_tipo_cuenta' => '',
    'transf_sucursal' => '',
    'transf_cuenta' => '',
    'transf_horas' => '24',        // horas para subir el comprobante antes de que se caiga la reserva
    'transf_cierre_horas' => '6',  // no se ofrece transferencia si la función empieza antes de esto
    // Tarjeta: apagada. El cobro con tarjeta NO está integrado (es simulado):
    // el medio real es la transferencia; la propuesta de integración con
    // Mercado Pago queda explicada en /admin/pagos.
    'tarjeta_activa' => '0',
    // Conciliación con Mercado Pago: si entra un pago con el importe exacto de
    // una sola reserva, se confirma solo. Con 0, queda «sugerida».
    'mp_auto_confirmar' => '1',
    'cargo_pct' => '4.10',         // cargo por servicio con tarjeta (comisión de Mercado Pago con IVA)
];

/** Con el pago con tarjeta simulado, en producción real nunca se ofrece:
 *  sería una entrada sin cobrar. */
function tarjeta_activa(): bool
{
    return ajuste('tarjeta_activa') === '1' && !(PAGO_SIMULADO && !MODO_DEMO);
}

function ajuste(string $clave): string
{
    static $cache = null;
    $cache ??= array_column(filas('SELECT clave, valor FROM ajustes'), 'valor', 'clave');
    return (string)($cache[$clave] ?? AJUSTES_DEFECTO[$clave] ?? '');
}

function guardar_ajuste(string $clave, string $valor): void
{
    q('INSERT INTO ajustes (clave, valor) VALUES (?, ?) ON CONFLICT (clave) DO UPDATE SET valor = excluded.valor', [$clave, $valor]);
}

/** Cargo por servicio para que, descontada la comisión, quede el precio publicado. */
function cargo_servicio(int $total): int
{
    $tasa = (float)str_replace(',', '.', ajuste('cargo_pct')) / 100;
    if ($total <= 0 || $tasa <= 0 || $tasa >= 0.5) return 0;
    return (int)ceil($total / (1 - $tasa) - $total);
}

/** ¿Se puede ofrecer transferencia para una función que empieza en $inicio? */
function transferencia_disponible(string $inicio): bool
{
    return ajuste('transf_activa') === '1'
        && (ajuste('transf_alias') !== '' || ajuste('transf_cbu') !== '')
        && strtotime($inicio) - time() > (int)ajuste('transf_cierre_horas') * 3600;
}

// ── Stock ──────────────────────────────────────────────────────────────────
// Una entrada ocupa lugar mientras no esté anulada. El disponible de una
// función es lo que queda por aforo o por la suma de cupos de sus tipos
// activos, lo que se acabe primero.

const SQL_CAPACIDAD_F = "MIN(f.aforo, (SELECT COALESCE(SUM(t.cupo),0) FROM tipos_entrada t WHERE t.funcion_id = f.id AND t.activo = 1))";
const SQL_VENDIDAS_F = "(SELECT COUNT(*) FROM entradas x WHERE x.funcion_id = f.id AND x.estado <> 'anulada')";
const SQL_DISPONIBLES_F = "MAX(0, " . SQL_CAPACIDAD_F . " - " . SQL_VENDIDAS_F . ")";
// Precio vigente de un tipo: el de oferta mientras no venza (oferta_hasta
// NULL = sin vencimiento). La hora va como literal porque la de SQLite
// ('localtime') depende del servidor y la de PHP, de la configuración.
define('SQL_PRECIO_T', "(CASE WHEN t.precio_oferta IS NOT NULL AND (t.oferta_hasta IS NULL OR t.oferta_hasta > '" . ahora() . "') THEN t.precio_oferta ELSE t.precio END)");
define('SQL_DESDE_F', "(SELECT MIN(" . SQL_PRECIO_T . ") FROM tipos_entrada t WHERE t.funcion_id = f.id AND t.activo = 1)");

function oferta_vigente(array $t): bool
{
    return ($t['precio_oferta'] ?? null) !== null && $t['precio_oferta'] !== '' && (empty($t['oferta_hasta']) || $t['oferta_hasta'] > ahora());
}

/**
 * Tipos de una función con su stock. Con $con_oferta (lo público: ficha y
 * checkout), «precio» es el VIGENTE y el de lista queda en «precio_lista».
 * El panel pide los crudos: si no, al guardar pisaría el precio de lista con
 * el de oferta.
 */
function tipos_con_stock(int $funcion_id, bool $con_oferta = true): array
{
    $tipos = filas("SELECT t.*, (SELECT COUNT(*) FROM entradas x WHERE x.tipo_id = t.id AND x.estado <> 'anulada') AS vendidas
                    FROM tipos_entrada t WHERE t.funcion_id = ? ORDER BY t.orden, t.id", [$funcion_id]);
    foreach ($tipos as &$t) {
        $t['quedan'] = max(0, $t['cupo'] - $t['vendidas']);
        $t['precio_lista'] = (int)$t['precio'];
        $t['en_oferta'] = $con_oferta && oferta_vigente($t);
        if ($t['en_oferta']) $t['precio'] = (int)$t['precio_oferta'];
    }
    return $tipos;
}

function vendidas_funcion(int $funcion_id): int
{
    return (int)valor("SELECT COUNT(*) FROM entradas WHERE funcion_id = ? AND estado <> 'anulada'", [$funcion_id]);
}

/** Funciones de un evento con capacidad, vendidas y disponibles. */
function funciones_de(int $evento_id, bool $solo_futuras = false): array
{
    $extra = $solo_futuras ? " AND COALESCE(f.fin, f.inicio) >= '" . ahora() . "'" : '';
    return filas("SELECT f.*, " . SQL_CAPACIDAD_F . " AS capacidad, " . SQL_VENDIDAS_F . " AS vendidas, " . SQL_DISPONIBLES_F . " AS disponibles,
                    " . SQL_DESDE_F . " AS desde,
                    (SELECT COUNT(*) FROM entradas x WHERE x.funcion_id = f.id AND x.estado = 'usada') AS ingresados
                  FROM funciones f WHERE f.evento_id = ? $extra ORDER BY f.inicio", [$evento_id]);
}

/** Texto y clase del estado de stock para mostrar. */
function estado_stock(int $quedan, int $total): array
{
    if ($quedan <= 0) return ['Agotado', 'agotado'];
    if ($quedan <= max(10, (int)ceil($total * 0.1))) return ["Últimas $quedan", 'ultimas'];
    return ['Disponible', 'disponible'];
}

function terminada(array $f): bool
{
    return strtotime($f['fin'] ?: $f['inicio']) < time();
}

/** Promedio y cantidad de reseñas visibles de un evento (todas sus funciones). */
function puntaje_evento(int $evento_id): array
{
    $r = fila('SELECT AVG(r.puntaje) AS prom, COUNT(*) AS n FROM resenas r JOIN funciones f ON f.id = r.funcion_id
               WHERE f.evento_id = ? AND r.visible = 1', [$evento_id]);
    return ['prom' => $r['n'] ? round((float)$r['prom'], 1) : null, 'n' => (int)$r['n']];
}

function estrellas(float $p, string $clase = ''): string
{
    $pct = max(0, min(100, $p / 5 * 100));
    return '<span class="estrellas ' . $clase . '" style="--p:' . $pct . '%" role="img" aria-label="' . str_replace('.', ',', (string)$p) . ' de 5"></span>';
}

function url_absoluta(string $ruta): string
{
    return (ES_PROD ? URL_PUBLICA : 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) . $ruta;
}
