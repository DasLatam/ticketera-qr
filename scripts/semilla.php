<?php
// Carga la base desde cero.
//
//   php scripts/semilla.php --ejemplo                      datos de ejemplo (borra todo)
//   php scripts/semilla.php --vacio --email X --nombre "Y"  arranque real: sólo el administrador
//
// Las dos BORRAN la base, la cola de correo y las imágenes subidas. Las
// credenciales que crea quedan en datos/credenciales.env (fuera de la carpeta
// pública y fuera del repo) y se muestran por pantalla. Las cuentas de ejemplo
// usan @demo.invalid: ese dominio no existe por norma (RFC 2606) y la cola de
// correo lo descarta sin intentar.

if (PHP_SAPI !== 'cli') exit("Sólo por consola.\n");
require __DIR__ . '/../app/nucleo/base.php';
require __DIR__ . '/../app/nucleo/admin.php';

$args = getopt('', ['ejemplo', 'vacio', 'email:', 'nombre:']);
if (!isset($args['ejemplo']) && !isset($args['vacio'])) exit("Uso: --ejemplo | --vacio --email correo --nombre \"Nombre\"\n");
if (ES_PROD && isset($args['ejemplo'])) exit("MODO_DEMO está en false: los datos de ejemplo no se cargan en una instalación real.\n");

// ── Borrar todo ────────────────────────────────────────────────────────────
foreach (glob(ARCHIVO_BASE . '*') as $f) unlink($f);
foreach (glob(DIR_DATOS . '/comprobantes/*') as $f) unlink($f);
$dir_ev = DIR_SUBIDAS . '/eventos';
if (is_dir($dir_ev)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir_ev, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() ? rmdir($f) : unlink($f);
}
@mkdir($dir_ev, 0755, true);
db(); // crea el esquema

function clave_humana(): string
{
    return 'Clave-' . codigo_aleatorio(4) . '-' . codigo_aleatorio(4);
}

function crear_usuario(string $nombre, string $email, string $clave, string $rol, ?string $creado = null): int
{
    q('INSERT INTO usuarios (nombre, email, clave_hash, rol, acepto_terminos, creado) VALUES (?,?,?,?,?,?)',
        [$nombre, $email, password_hash($clave, PASSWORD_DEFAULT), $rol, $creado ?? ahora(), $creado ?? ahora()]);
    return (int)db()->lastInsertId();
}

$cred_dir = DIR_DATOS;
@mkdir($cred_dir, 0700, true);
$cred = [];

if (isset($args['vacio'])) {
    $email = $args['email'] ?? exit("Falta --email\n");
    $clave = clave_humana();
    crear_usuario($args['nombre'] ?? 'Administración', mb_strtolower($email), $clave, 'admin');
    foreach (['Fiestas', 'Música en vivo', 'Gastronomía', 'Experiencias', 'Bienestar', 'Corporativos'] as $i => $c) {
        q('INSERT INTO categorias (nombre, slug, orden) VALUES (?,?,?)', [$c, slug($c), $i + 1]);
    }
    file_put_contents("$cred_dir/credenciales.env", "# Generado " . date('Y-m-d H:i') . " por semilla.php --vacio\nADMIN_EMAIL=$email\nADMIN_CLAVE=$clave\n");
    chmod("$cred_dir/credenciales.env", 0600);
    echo "Base vacía lista. Administrador: $email / $clave\n";
    exit;
}

// ── Datos de ejemplo ───────────────────────────────────────────────────────
mt_srand(20261002);
$pdo = db();
$pdo->exec('BEGIN');

$ids = [];
foreach ([['Administración', 'admin@demo.invalid', 'admin'], ['Control de puerta', 'puerta@demo.invalid', 'puerta'],
          ['Cliente de prueba', 'cliente@demo.invalid', 'cliente']] as [$n, $em, $rol]) {
    $c = clave_humana();
    $ids[$rol] = crear_usuario($n, $em, $c, $rol, date('Y-m-d H:i:s', strtotime('-120 days')));
    $cred[] = strtoupper($rol) . "_EMAIL=$em\n" . strtoupper($rol) . "_CLAVE=$c";
}

$nombres = ['Lucía', 'Martín', 'Sofía', 'Joaquín', 'Valentina', 'Tomás', 'Camila', 'Facundo', 'Julieta', 'Nicolás', 'Florencia', 'Santiago',
    'Agustina', 'Matías', 'Carolina', 'Ignacio', 'Paula', 'Federico', 'Micaela', 'Gonzalo', 'Rocío', 'Lautaro', 'Antonella', 'Diego',
    'Belén', 'Ezequiel', 'Milagros', 'Franco', 'Delfina', 'Bruno', 'Victoria', 'Emiliano', 'Pilar', 'Andrés', 'Malena', 'Ramiro'];
$apellidos = ['González', 'Rodríguez', 'Fernández', 'López', 'Martínez', 'Pérez', 'Gómez', 'Sánchez', 'Romero', 'Díaz', 'Álvarez', 'Torres',
    'Ruiz', 'Ramírez', 'Flores', 'Benítez', 'Acosta', 'Medina', 'Herrera', 'Aguirre', 'Pereyra', 'Ríos', 'Molina', 'Castro', 'Ortiz', 'Silva'];
$persona = fn() => $nombres[mt_rand(0, count($nombres) - 1)] . ' ' . $apellidos[mt_rand(0, count($apellidos) - 1)];
$clientes = [$ids['cliente']];
$clave_clientes = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT); // nadie entra como ellos
for ($i = 0; $i < 70; $i++) {
    $n = $persona();
    [$nom, $ape] = explode(' ', $n);
    q('INSERT INTO usuarios (nombre, email, clave_hash, telefono, rol, acepto_terminos, creado) VALUES (?,?,?,?,?,?,?)',
        [$n, slug($nom) . '.' . slug($ape) . mt_rand(10, 99) . '@demo.invalid', $clave_clientes, '11 ' . mt_rand(3000, 6999) . '-' . mt_rand(1000, 9999), 'cliente',
         $c = date('Y-m-d H:i:s', strtotime('-' . mt_rand(1, 110) . ' days')), $c]);
    $clientes[] = (int)$pdo->lastInsertId();
}

$cats = [];
foreach (['Fiestas', 'Música en vivo', 'Gastronomía', 'Experiencias', 'Bienestar', 'Corporativos'] as $i => $c) {
    q('INSERT INTO categorias (nombre, slug, orden) VALUES (?,?,?)', [$c, slug($c), $i + 1]);
    $cats[$c] = (int)$pdo->lastInsertId();
}

// Lugares con coordenadas aproximadas reales.
$L = [
    'terraza' => ['Terraza Gurruchaga', 'Gurruchaga 1650', 'CABA', -34.58745, -58.43003],
    'galpon' => ['Galpón Barracas', 'Av. Montes de Oca 900', 'CABA', -34.64207, -58.37893],
    'casa_palermo' => ['Casa Escondida · Palermo', 'Thames 1200', 'CABA', -34.59312, -58.43264],
    'casa_santelmo' => ['Casa Escondida · San Telmo', 'Defensa 900', 'CABA', -34.61954, -58.37198],
    'biblioteca' => ['Biblioteca Casona Belgrano', 'Juramento 2200', 'CABA', -34.56203, -58.45601],
    'patio' => ['Patio Thames', 'Thames 2100', 'CABA', -34.58652, -58.42688],
    'sotano' => ['Vinoteca El Sótano', 'Honduras 5400', 'CABA', -34.58588, -58.43201],
    'jardin' => ['Jardín Las Lomas', 'Av. Fondo de la Legua 1000', 'San Isidro', -34.48803, -58.54902],
    'barro' => ['Taller Barro', 'Darwin 1100', 'CABA', -34.59152, -58.44297],
    'costanera' => ['Costanera Vicente López', 'Paseo de la Costa', 'Vicente López', -34.51803, -58.47197],
    'sanssouci' => ['Palacio de las Glicinas', 'Av. de Ejemplo 1000', 'Victoria', -34.46014, -58.53198],
    'trastienda' => ['Bar El Altillo', 'Balcarce 400', 'CABA', -34.61331, -58.37072],
    'recoleta' => ['Showroom Recoleta', 'Av. Alvear 1800', 'CABA', -34.58801, -58.38803],
    'club' => ['Club Social Villa Crespo', 'Padilla 800', 'CABA', -34.59702, -58.43803],
    'cine_centro' => ['Terraza Cine · Centro', 'Av. Corrientes 1500', 'CABA', -34.60431, -58.38797],
    'cine_nunez' => ['Terraza Cine · Núñez', 'Av. del Libertador 7400', 'CABA', -34.54503, -58.46102],
];

// Funciones: [días desde hoy, hora, horas de duración, lugar, fracción vendida, aforo]
$eventos = [
    ['noche-de-terraza', 'Noche de terraza', 'Fiestas',
        'Una terraza en Palermo, un DJ que no apura y la ciudad de fondo.',
        "Abrimos la terraza para una noche tranquila: buena música, tragos de autor y la ciudad iluminada como telón. Es un encuentro chico, pensado para conversar y bailar sin empujones.\n\nEl cupo es limitado a propósito. Se recomienda llegar antes de las 22 h, cuando empieza el set principal.\n\nHay guardarropa y opciones sin alcohol en la barra.",
        [['General', 18000, 90, 'Acceso a la terraza'], ['Con trago de bienvenida', 24000, 30, 'Incluye un trago de autor']],
        ['terraza', 'dj', 'palermo'], 18, 'Casual elegante', 'Colectivo Azotea', 'publica', 'publicado',
        [[-21, '22:00', 6, 'terraza', 0.95, 120], [-7, '22:00', 6, 'terraza', 0.9, 120], [0, null, 6, 'terraza', 0.62, 120], [14, '22:00', 6, 'terraza', 0.2, 120]]],
    ['techno-galpon', 'Galpón · techno hasta el amanecer', 'Fiestas',
        'Sistema de sonido de verdad, luces mínimas y cinco horas de techno.',
        "Un galpón recuperado en Barracas, un sistema de sonido calibrado para el lugar y tres artistas locales que se pasan la posta hasta que sale el sol.\n\nNo hay zona VIP ni mesas: la pista es una sola. Se pide no filmar los sets.\n\nLa entrada se valida con el QR; el ingreso cierra a las 3 h.",
        [['Preventa', 15000, 150, 'Hasta agotar'], ['General', 20000, 150, '']],
        ['techno', 'electrónica', 'after'], 18, null, 'Ciclo Nocturno', 'publica', 'publicado',
        [[-28, '23:59', 7, 'galpon', 0.85, 300], [3, '23:59', 7, 'galpon', 0.97, 300]]],
    ['cena-a-ciegas', 'Cena a ciegas', 'Gastronomía',
        'Seis pasos de un menú que no conocés hasta que llega a la mesa.',
        "La chef prepara un menú de seis pasos que se revela plato por plato. La única pista es la estación: productos de primavera, de huertas cercanas.\n\nLa cena se repite en dos casas, en Palermo y en San Telmo, con el mismo menú. Avisá en la compra si tenés alguna restricción alimentaria y la cocina adapta tu plato.\n\nEl maridaje es opcional y se elige con la entrada.",
        [['Menú', 45000, 16, 'Seis pasos'], ['Menú con maridaje', 62000, 16, 'Seis pasos y cuatro copas']],
        ['menú por pasos', 'chef', 'cena'], 18, null, 'Casa Escondida', 'publica', 'publicado',
        [[-16, '21:00', 3, 'casa_palermo', 1.0, 32], [-9, '21:00', 3, 'casa_santelmo', 0.9, 32], [5, '21:00', 3, 'casa_palermo', 0.45, 32], [12, '21:00', 3, 'casa_santelmo', 0.25, 32]]],
    ['jazz-en-la-biblioteca', 'Jazz en la biblioteca', 'Música en vivo',
        'Un trío de jazz entre estanterías, con sillas para todos.',
        "Un trío acústico toca standards y composiciones propias en la sala de lectura de una casona de Belgrano. Sin amplificación: la sala tiene la acústica justa.\n\nEl ciclo es mensual. Las sillas no están numeradas. Se sirve café y vino durante el intervalo.",
        [['Platea', 12000, 80, '']],
        ['jazz', 'acústico', 'belgrano'], null, null, 'Ciclo Sala de Lectura', 'publica', 'publicado',
        [[-50, '20:30', 2, 'biblioteca', 0.9, 80], [-22, '20:30', 2, 'biblioteca', 0.8, 80], [8, '20:30', 2, 'biblioteca', 0.3, 80], [36, '20:30', 2, 'biblioteca', 0.05, 80]]],
    ['after-office-palermo', 'After office en el patio', 'Corporativos',
        'Para cortar la semana: patio, música y picada.',
        "Un after office pensado para equipos y para quienes vienen solos. Patio con mesas largas, picada para compartir y música a un volumen que deja conversar.\n\nLas empresas pueden comprar hasta diez entradas en una sola compra.",
        [['General', 9000, 150, 'Incluye picada']],
        ['after office', 'networking', 'patio'], 18, null, null, 'publica', 'publicado',
        [[-5, '19:00', 4, 'patio', 0.7, 150], [2, '19:00', 4, 'patio', 0.55, 150], [9, '19:00', 4, 'patio', 0.15, 150]]],
    ['cata-vinos-naturales', 'Cata de vinos naturales', 'Gastronomía',
        'Ocho etiquetas de productores chicos, con quienes los hacen.',
        "Dos productores de Mendoza y uno de la Patagonia presentan ocho vinos de baja intervención y cuentan cómo trabajan. La cata es guiada y se acompaña con quesos de pequeña escala.\n\nCupo reducido para que todos puedan preguntar.",
        [['Cata', 28000, 40, 'Ocho vinos y tabla de quesos']],
        ['vino', 'cata', 'natural'], 18, null, 'El Sótano', 'publica', 'publicado',
        [[12, '19:30', 2.5, 'sotano', 0.88, 40]]],
    ['brunch-de-domingo', 'Brunch de domingo', 'Gastronomía',
        'Mesa larga, pan de masa madre y café de especialidad.',
        "Un brunch sin apuro en un jardín de San Isidro. Panadería de masa madre, huevos de campo, frutas de estación y café de especialidad preparado en el momento.\n\nLos chicos menores de 10 años no pagan entrada.",
        [['Adulto', 16000, 50, ''], ['Menor de 10 a 15 años', 8000, 10, '']],
        ['brunch', 'familia', 'zona norte'], null, null, null, 'publica', 'publicado',
        [[-11, '11:00', 3, 'jardin', 0.8, 60], [6, '11:00', 3, 'jardin', 0.2, 60]]],
    ['taller-ceramica', 'Taller de cerámica para principiantes', 'Experiencias',
        'Tres horas de torno y modelado. Te llevás tu pieza.',
        "Un taller introductorio con dos ceramistas: técnicas básicas de modelado a mano y una primera vuelta en el torno. Los materiales están incluidos.\n\nLas piezas se hornean después del taller y se retiran a las dos semanas.",
        [['Lugar en el taller', 35000, 14, 'Materiales y horneado incluidos']],
        ['cerámica', 'taller', 'manos'], 16, 'Ropa que se pueda manchar', 'Taller Barro', 'publica', 'publicado',
        [[-18, '15:00', 3, 'barro', 1.0, 14], [9, '15:00', 3, 'barro', 0.5, 14], [23, '15:00', 3, 'barro', 0.1, 14]]],
    ['yoga-al-amanecer', 'Yoga al amanecer', 'Bienestar',
        'Una práctica suave mirando el río, para todos los niveles.',
        "Una clase de yoga suave frente al río, al salir el sol. No hace falta experiencia previa.\n\nTraé tu mat si tenés; si no, hay algunos para prestar. Al terminar hay té y frutas.",
        [['Clase', 7000, 40, '']],
        ['yoga', 'aire libre', 'río'], null, null, null, 'publica', 'publicado',
        [[-3, '07:00', 1.5, 'costanera', 0.6, 40], [4, '07:00', 1.5, 'costanera', 0.35, 40], [11, '07:00', 1.5, 'costanera', 0.1, 40]]],
    ['gala-fin-de-anio', 'Gala de fin de año', 'Fiestas',
        'Cena, orquesta y brindis para despedir el año.',
        "Una noche de etiqueta para cerrar el año: recepción, cena de tres pasos, orquesta en vivo y brindis a medianoche.\n\nLas mesas son de diez personas. Si comprás para un grupo, indicá los nombres en cada entrada y los sentamos juntos.",
        [['Individual', 95000, 200, 'Cena y barra libre'], ['Mesa preferencial', 120000, 50, 'Cerca de la pista']],
        ['gala', 'cena', 'fin de año'], 18, 'Etiqueta', 'Productora de Ejemplo', 'publica', 'publicado',
        [[60, '21:30', 6, 'sanssouci', 0.15, 250]]],
    ['stand-up-intimo', 'Stand up íntimo', 'Experiencias',
        'Tres comediantes, cincuenta sillas y material nuevo.',
        "Tres comediantes prueban material nuevo frente a un público chico. Se pide no grabar: muchos chistes todavía se están armando.\n\nLa barra abre una hora antes.",
        [['General', 10000, 50, '']],
        ['humor', 'stand up'], 18, null, null, 'publica', 'publicado',
        [[-13, '21:00', 1.5, 'trastienda', 1.0, 50], [1, '21:00', 1.5, 'trastienda', 1.0, 50], [15, '21:00', 1.5, 'trastienda', 0.3, 50]]],
    ['lanzamiento-producto', 'Presentación privada · colección de verano', 'Corporativos',
        'Evento por invitación: se accede sólo con el enlace.',
        "Presentación de la colección de verano para clientes y prensa. Este evento no aparece en el catálogo: se comparte sólo con el enlace.\n\nAl ingresar se entrega un obsequio por persona.",
        [['Invitado', 0, 100, 'Sin cargo, con registro']],
        ['moda', 'lanzamiento'], null, 'Elegante', 'Marca de ejemplo', 'enlace', 'publicado',
        [[15, '19:00', 3, 'recoleta', 0.1, 100]]],
    ['cumbia-y-vermut', 'Cumbia y vermut', 'Música en vivo',
        'Banda en vivo, vermut tirado y pista al aire libre.',
        "Una tarde larga de cumbia en vivo con vermut tirado. Borrador: falta confirmar la banda.",
        [['General', 11000, 200, '']],
        ['cumbia', 'vermut', 'aire libre'], null, null, null, 'publica', 'borrador',
        [[10, '18:00', 5, 'club', 0, 200]]],
    ['cine-bajo-estrellas', 'Cine bajo las estrellas', 'Experiencias',
        'Un clásico en pantalla grande, en una terraza con reposeras.',
        "Proyectamos un clásico en una terraza con reposeras, mantas y pochoclo. Auriculares inalámbricos para cada asistente.\n\nEl ciclo rota entre dos terrazas, en el Centro y en Núñez.",
        [['Reposera', 9000, 70, 'Incluye pochoclo']],
        ['cine', 'terraza', 'al aire libre'], null, null, 'Ciclo Terraza Cine', 'publica', 'publicado',
        [[-34, '20:30', 3, 'cine_centro', 0.95, 70], [-20, '20:30', 3, 'cine_nunez', 0.85, 70], [-6, '20:30', 3, 'cine_centro', 0.92, 70], [7, '20:30', 3, 'cine_nunez', 0.4, 70], [21, '20:30', 3, 'cine_centro', 0.1, 70]]],
];

$hoy = strtotime('today');
$funciones_creadas = [];
foreach ($eventos as [$foto, $titulo, $cat, $bajada, $desc, $tipos, $tags, $edad, $vest, $org, $vis, $estado, $funcs]) {
    $primera = min(array_column($funcs, 0));
    $creado = date('Y-m-d H:i:s', $hoy + ($primera - mt_rand(20, 35)) * 86400 + 11 * 3600);
    q('INSERT INTO eventos (slug, titulo, bajada, descripcion, categoria_id, max_por_compra, estado, visibilidad, organizador, edad_minima, vestimenta,
        meta_titulo, meta_descripcion, meta_palabras, creado, actualizado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [slug($titulo), $titulo, $bajada, $desc, $cats[$cat], $cat === 'Corporativos' ? 10 : 6, $estado, $vis, $org, $edad, $vest, null, $bajada, implode(', ', $tags), $creado, $creado]);
    $eid = (int)$pdo->lastInsertId();
    guardar_etiquetas($eid, implode(',', $tags));
    @mkdir(DIR_SUBIDAS . "/eventos/$eid", 0755, true);
    foreach (['', '-2'] as $k => $suf) {
        $archivo = "$eid/" . bin2hex(random_bytes(6));
        procesar_imagen(__DIR__ . "/../semilla/fotos/$foto$suf.jpg", DIR_SUBIDAS . "/eventos/$archivo");
        q('INSERT INTO evento_imagenes (evento_id, archivo, alt, orden) VALUES (?,?,?,?)', [$eid, $archivo, $titulo, $k]);
    }
    foreach ($funcs as [$dias, $hora, $dur, $lugar, $frac, $aforo]) {
        // La función de hoy empieza en una hora, para poder probar la puerta.
        $ini = $hora === null ? strtotime(date('Y-m-d H:00', time() + 3600)) : strtotime(date('Y-m-d', $hoy + $dias * 86400) . " $hora");
        [$ln, $ld, $lc, $lat, $lng] = $L[$lugar];
        q('INSERT INTO funciones (evento_id, inicio, fin, lugar, direccion, ciudad, lat, lng, aforo, creado) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$eid, date('Y-m-d H:i:s', $ini), date('Y-m-d H:i:s', $ini + (int)($dur * 3600)), $ln, $ld, $lc, $lat, $lng, $aforo, $creado]);
        $fid = (int)$pdo->lastInsertId();
        foreach ($tipos as $k => [$tn, $tp, $tc, $td]) {
            // Los cupos se escalan si el aforo de esta función es otro.
            q('INSERT INTO tipos_entrada (funcion_id, nombre, descripcion, precio, cupo, orden) VALUES (?,?,?,?,?,?)', [$fid, $tn, $td ?: null, $tp, $tc, $k]);
        }
        $funciones_creadas[] = [$fid, $eid, $frac, $ini, $creado, $estado, $foto];
    }
}

// Ventas: cada función vende la fracción indicada, repartida entre el alta del
// evento y el día de la función (o hoy, si todavía no llegó).
$n_ordenes = 0;
foreach ($funciones_creadas as [$fid, $eid, $frac, $ini, $creado, $estado]) {
    if ($estado !== 'publicado' || $frac <= 0) continue;
    $tipos = filas('SELECT * FROM tipos_entrada WHERE funcion_id = ? ORDER BY orden', [$fid]);
    $aforo = (int)valor('SELECT aforo FROM funciones WHERE id = ?', [$fid]);
    $max_compra = (int)valor('SELECT max_por_compra FROM eventos WHERE id = ?', [$eid]);
    $objetivo = (int)round(min($aforo, array_sum(array_column($tipos, 'cupo'))) * $frac);
    $vendidas = array_fill_keys(array_column($tipos, 'id'), 0);
    $total = 0;
    $desde = strtotime($creado);
    $hasta = min(time() - 600, $ini - 1800);
    while ($total < $objetivo) {
        $libres = array_values(array_filter($tipos, fn($t) => $vendidas[$t['id']] < $t['cupo']));
        if (!$libres) break;
        $t = $libres[mt_rand(0, count($libres) - 1)];
        $n = min(mt_rand(1, 100) <= 55 ? mt_rand(1, 2) : mt_rand(2, min(4, $max_compra)), $objetivo - $total, $t['cupo'] - $vendidas[$t['id']]);
        $cuando = date('Y-m-d H:i:s', $desde + (int)(($hasta - $desde) * (mt_rand(0, 1000) / 1000) ** 0.75));
        $uid = $clientes[mt_rand(1, count($clientes) - 1)];
        $comprador = valor('SELECT nombre FROM usuarios WHERE id = ?', [$uid]);
        do $cod = codigo_aleatorio(8); while (valor('SELECT 1 FROM ordenes WHERE codigo = ?', [$cod]));
        q('INSERT INTO ordenes (codigo, usuario_id, funcion_id, total, estado, medio_pago, creado) VALUES (?,?,?,?,?,?,?)',
            [$cod, $uid, $fid, $n * $t['precio'], 'pagada', 'prueba', $cuando]);
        $oid = (int)$pdo->lastInsertId();
        for ($k = 0; $k < $n; $k++) {
            do $c = codigo_aleatorio(10); while (valor('SELECT 1 FROM entradas WHERE codigo = ?', [$c]));
            $usada = null;
            if ($ini < time() && mt_rand(1, 100) <= 91) $usada = date('Y-m-d H:i:s', $ini + mt_rand(-900, 5400));
            q('INSERT INTO entradas (codigo, orden_id, funcion_id, tipo_id, usuario_id, titular, precio, estado, usada_en, usada_por, creado) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$c, $oid, $fid, $t['id'], $uid, $k === 0 ? $comprador : $persona(), $t['precio'], $usada ? 'usada' : 'valida', $usada, $usada ? $ids['puerta'] : null, $cuando]);
            if ($usada) q('INSERT INTO escaneos (funcion_id, entrada_id, codigo_leido, resultado, usuario_id, creado) VALUES (?,?,?,?,?,?)',
                [$fid, $pdo->lastInsertId(), "TQR:$c", 'ok', $ids['puerta'], $usada]);
        }
        $vendidas[$t['id']] += $n;
        $total += $n;
        $n_ordenes++;
    }
}

// Reseñas: una parte de quienes pasaron por la puerta opina al día siguiente
// (o unos días después). Cada una queda atada a su función.
$textos = [
    5 => ['Impecable de punta a punta. Entramos en dos minutos con el QR.', 'Volvería mañana. Muy bien organizado y con la gente justa.', 'Superó lo que esperaba. El lugar, la música y la atención, todo bien.', 'Una joya. Se nota el cuidado en cada detalle.', null],
    4 => ['Muy lindo. Un poco de demora al principio, después todo fluyó.', 'Buena experiencia; la próxima llego más temprano.', 'Recomendable. El sonido podría estar un poco más alto.', null],
    3 => ['Estuvo bien, aunque esperaba algo más por el precio.', 'Correcto. Había más gente de la que imaginaba.'],
    2 => ['No me convenció: mucha espera para la barra.'],
];
$n_resenas = 0;
foreach ($funciones_creadas as [$fid, $eid, $frac, $ini]) {
    if ($ini + 6 * 3600 > time()) continue;
    $asistentes = array_column(filas("SELECT DISTINCT usuario_id FROM entradas WHERE funcion_id = ? AND estado = 'usada'", [$fid]), 'usuario_id');
    foreach ($asistentes as $uid) {
        if (mt_rand(1, 100) > 38) continue;
        $r = mt_rand(1, 100);
        $p = $r <= 58 ? 5 : ($r <= 88 ? 4 : ($r <= 97 ? 3 : 2));
        $tx = $textos[$p][mt_rand(0, count($textos[$p]) - 1)];
        $cuando = date('Y-m-d H:i:s', min(time() - 600, $ini + 86400 * mt_rand(1, 3) + mt_rand(0, 36000)));
        q('INSERT INTO resenas (funcion_id, usuario_id, puntaje, texto, creado) VALUES (?,?,?,?,?)', [$fid, $uid, $p, $tx, $cuando]);
        $n_resenas++;
    }
}

// El cliente de prueba: entradas para la función de hoy (para probar el QR
// contra el escáner) y una pasada en la que entró, para probar la reseña.
$cli = function (string $slug, int $n, string $cual, bool $usada = false) use ($ids, $pdo) {
    $f = fila('SELECT f.* FROM funciones f JOIN eventos e ON e.id = f.evento_id WHERE e.slug = ? ORDER BY ' . ($cual === 'ultima_pasada' ? "CASE WHEN f.inicio < datetime('now','localtime') THEN 0 ELSE 1 END, f.inicio DESC" : 'f.inicio') . ' LIMIT 1', [$slug]);
    if ($cual !== 'ultima_pasada') $f = fila("SELECT f.* FROM funciones f JOIN eventos e ON e.id = f.evento_id WHERE e.slug = ? AND f.inicio >= ? ORDER BY f.inicio LIMIT 1", [$slug, date('Y-m-d H:i:s', time() - 3600)]);
    $t = fila('SELECT * FROM tipos_entrada WHERE funcion_id = ? ORDER BY orden LIMIT 1', [$f['id']]);
    $cuando = date('Y-m-d H:i:s', min(time() - 86400, strtotime($f['inicio']) - 86400 * 3));
    q('INSERT INTO ordenes (codigo, usuario_id, funcion_id, total, estado, medio_pago, creado) VALUES (?,?,?,?,?,?,?)', [codigo_aleatorio(8), $ids['cliente'], $f['id'], $n * $t['precio'], 'pagada', 'prueba', $cuando]);
    $oid = (int)$pdo->lastInsertId();
    for ($k = 0; $k < $n; $k++) {
        q('INSERT INTO entradas (codigo, orden_id, funcion_id, tipo_id, usuario_id, titular, precio, estado, usada_en, usada_por, creado) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [codigo_aleatorio(10), $oid, $f['id'], $t['id'], $ids['cliente'], $k ? 'Acompañante de prueba' : 'Cliente de prueba', $t['precio'],
             $usada ? 'usada' : 'valida', $usada ? date('Y-m-d H:i:s', strtotime($f['inicio']) + 600) : null, $usada ? $ids['puerta'] : null, $cuando]);
    }
};
$cli('noche-de-terraza', 2, 'hoy');
$cli('jazz-en-la-biblioteca', 1, 'proxima');
$cli('cine-bajo-las-estrellas', 2, 'ultima_pasada', true);

// Transferencias de ejemplo: datos de cobro claramente ficticios (sin CBU:
// uno inventado podría ser la cuenta real de alguien) y tres reservas, dos
// con comprobante para verificar y una esperándolo.
foreach (['transf_activa' => '1', 'transf_alias' => 'alias.de.ejemplo', 'transf_titular' => 'Cuenta de ejemplo (demo)', 'transf_banco' => 'Billetera de ejemplo'] as $k => $v) guardar_ajuste($k, $v);
require_once __DIR__ . '/../app/nucleo/transferencias.php';
$fs = filas("SELECT f.id, f.inicio FROM funciones f JOIN eventos e ON e.id = f.evento_id WHERE e.estado = 'publicado' AND e.visibilidad = 'publica' AND f.inicio > ? ORDER BY f.inicio LIMIT 3 OFFSET 2", [date('Y-m-d H:i:s', time() + 30 * 3600)]);
foreach ($fs as $k => $f) {
    $t = fila('SELECT * FROM tipos_entrada WHERE funcion_id = ? ORDER BY orden LIMIT 1', [$f['id']]);
    $uid = $clientes[mt_rand(1, count($clientes) - 1)];
    $n = 2;
    $cod = codigo_aleatorio(8);
    $creado = date('Y-m-d H:i:s', time() - (3 - $k) * 3600);
    $comp = $k < 2 ? $cod . '-' . bin2hex(random_bytes(5)) . '.jpg' : null;
    if ($comp) copy(__DIR__ . '/../semilla/comprobante-ejemplo.jpg', dir_comprobantes() . '/' . $comp);
    q('INSERT INTO ordenes (codigo, usuario_id, funcion_id, total, estado, medio_pago, vence, comprobante, comprobante_en, creado) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$cod, $uid, $f['id'], $n * $t['precio'], 'pendiente', 'transferencia', date('Y-m-d H:i:s', strtotime($creado) + 24 * 3600), $comp, $comp ? date('Y-m-d H:i:s', strtotime($creado) + 1800) : null, $creado]);
    $oid = (int)$pdo->lastInsertId();
    $nombre = valor('SELECT nombre FROM usuarios WHERE id = ?', [$uid]);
    for ($i = 0; $i < $n; $i++) {
        q('INSERT INTO entradas (codigo, orden_id, funcion_id, tipo_id, usuario_id, titular, precio, creado) VALUES (?,?,?,?,?,?,?,?)',
            [codigo_aleatorio(10), $oid, $f['id'], $t['id'], $uid, $i ? $persona() : $nombre, $t['precio'], $creado]);
    }
}

$pdo->exec('COMMIT');
$pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');

file_put_contents("$cred_dir/credenciales.env", "# Datos de ejemplo — generado " . date('Y-m-d H:i') . " por semilla.php --ejemplo\n" . implode("\n", $cred) . "\n");
echo implode("\n", $cred) . "\n";
chmod("$cred_dir/credenciales.env", 0600);
printf("Listo: %d eventos, %d funciones, %d clientes, %d compras, %d entradas, %d reseñas.\nCredenciales en %s/credenciales.env\n",
    valor('SELECT COUNT(*) FROM eventos'), valor('SELECT COUNT(*) FROM funciones'), count($clientes), $n_ordenes + 3,
    valor('SELECT COUNT(*) FROM entradas'), $n_resenas, $cred_dir);
