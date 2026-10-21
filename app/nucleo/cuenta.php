<?php
// Registro, ingreso y datos de la cuenta.

function volver_seguro(?string $v): string
{
    // Sólo rutas internas: un ?volver=https://otro.sitio sería un redirect abierto.
    return ($v && str_starts_with($v, '/') && !str_starts_with($v, '//')) ? $v : '/mis-entradas';
}

function destino_por_rol(array $u, ?string $volver): string
{
    if ($volver) return volver_seguro($volver);
    return match ($u['rol']) {
        'admin' => '/admin',
        'puerta' => '/puerta',
        default => '/mis-entradas',
    };
}

function pag_ingresar(): void
{
    if (usuario()) ir(destino_por_rol(usuario(), $_GET['volver'] ?? null));
    vista('ingresar', ['volver' => $_GET['volver'] ?? '', 'email' => ''], ['titulo' => 'Ingresar', 'noindex' => true]);
}

function demasiados_intentos(string $clave): bool
{
    q('DELETE FROM intentos_ingreso WHERE creado < ?', [time() - 900]);
    return (int)valor('SELECT COUNT(*) FROM intentos_ingreso WHERE clave = ?', [$clave]) >= 8;
}

function acc_ingresar(): void
{
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');
    $volver = $_POST['volver'] ?? '';
    $ip = 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? '');
    if (demasiados_intentos($ip) || demasiados_intentos('em:' . $email)) {
        flash('Demasiados intentos. Esperá unos minutos y probá de nuevo.', 'error');
        vista('ingresar', compact('volver', 'email'), ['titulo' => 'Ingresar', 'noindex' => true]);
        return;
    }
    if (!captcha_valido()) {
        flash('Tildá «No soy un robot» antes de ingresar.', 'error');
        vista('ingresar', compact('volver', 'email'), ['titulo' => 'Ingresar', 'noindex' => true]);
        return;
    }
    $u = fila('SELECT * FROM usuarios WHERE email = ?', [$email]);
    if (!$u || !password_verify($clave, $u['clave_hash']) || !$u['activo']) {
        q('INSERT INTO intentos_ingreso (clave, creado) VALUES (?,?), (?,?)', [$ip, time(), 'em:' . $email, time()]);
        flash($u && !$u['activo'] ? 'La cuenta está deshabilitada.' : 'El correo o la contraseña no coinciden.', 'error');
        vista('ingresar', compact('volver', 'email'), ['titulo' => 'Ingresar', 'noindex' => true]);
        return;
    }
    entrar_como($u['id']);
    ir(destino_por_rol($u, $volver));
}

function pag_registro(): void
{
    if (usuario()) ir('/mis-entradas');
    vista('registro', ['volver' => $_GET['volver'] ?? '', 'd' => []], ['titulo' => 'Crear cuenta', 'noindex' => true]);
}

function acc_registro(): void
{
    $d = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'email' => mb_strtolower(trim($_POST['email'] ?? '')),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'dni' => preg_replace('/\D/', '', $_POST['dni'] ?? ''),
    ];
    $clave = (string)($_POST['clave'] ?? '');
    $volver = $_POST['volver'] ?? '';
    $err = [];
    if (mb_strlen($d['nombre']) < 3) $err[] = 'Escribí tu nombre y apellido.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'El correo no parece válido.';
    elseif (valor('SELECT 1 FROM usuarios WHERE email = ?', [$d['email']])) $err[] = 'Ya hay una cuenta con ese correo. Probá ingresar.';
    if (mb_strlen($clave) < 8) $err[] = 'La contraseña tiene que tener al menos 8 caracteres.';
    if ($d['dni'] !== '' && (strlen($d['dni']) < 7 || strlen($d['dni']) > 9)) $err[] = 'El DNI no parece válido.';
    if (!captcha_valido()) $err[] = 'Tildá «No soy un robot» antes de crear la cuenta.';
    if (empty($_POST['terminos'])) $err[] = 'Para crear la cuenta tenés que aceptar las Condiciones de Servicio y la Política de Privacidad.';
    if ($err) {
        foreach ($err as $x) flash($x, 'error');
        vista('registro', compact('volver', 'd'), ['titulo' => 'Crear cuenta', 'noindex' => true]);
        return;
    }
    q('INSERT INTO usuarios (nombre, email, clave_hash, telefono, dni, rol, acepto_terminos, creado) VALUES (?,?,?,?,?,?,?,?)',
        [$d['nombre'], $d['email'], password_hash($clave, PASSWORD_DEFAULT), $d['telefono'] ?: null, $d['dni'] ?: null, 'cliente', ahora(), ahora()]);
    $uid = (int)db()->lastInsertId();
    entrar_como($uid);
    correo_bienvenida(['nombre' => $d['nombre'], 'email' => $d['email']]);
    flash('Cuenta creada. ¡Bienvenido!');
    ir(volver_seguro($volver));
}

function acc_salir(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    ir('/');
}

function pag_cuenta(): void
{
    $u = requerir_login();
    vista('cuenta', compact('u'), ['titulo' => 'Mi cuenta', 'noindex' => true]);
}

function acc_cuenta(): void
{
    $u = requerir_login();
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $dni = preg_replace('/\D/', '', $_POST['dni'] ?? '');
    if (mb_strlen($nombre) < 3) {
        flash('Escribí tu nombre y apellido.', 'error');
        ir('/cuenta');
    }
    q('UPDATE usuarios SET nombre = ?, telefono = ?, dni = ? WHERE id = ?', [$nombre, $telefono ?: null, $dni ?: null, $u['id']]);
    $nueva = (string)($_POST['clave_nueva'] ?? '');
    if ($nueva !== '') {
        if (!password_verify((string)($_POST['clave_actual'] ?? ''), $u['clave_hash'])) {
            flash('La contraseña actual no coincide; no se cambió.', 'error');
            ir('/cuenta');
        }
        if (mb_strlen($nueva) < 8) {
            flash('La contraseña nueva tiene que tener al menos 8 caracteres.', 'error');
            ir('/cuenta');
        }
        q('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
    }
    flash('Datos guardados.');
    ir('/cuenta');
}

// ── Recupero de contraseña ─────────────────────────────────────────────────
// El token viaja sólo en el correo; en la base se guarda su hash. Vence en una
// hora y sirve una vez. La respuesta es la misma exista o no la cuenta, para
// no confirmarle a nadie qué correos están registrados.

function pag_recuperar(): void
{
    vista('recuperar', ['enviado' => false], ['titulo' => 'Recuperar contraseña', 'noindex' => true]);
}

function acc_recuperar(): void
{
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $ip = 'rec:' . ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!captcha_valido()) {
        flash('Tildá «No soy un robot» para pedir el enlace.', 'error');
        vista('recuperar', ['enviado' => false], ['titulo' => 'Recuperar contraseña', 'noindex' => true]);
        return;
    }
    if (!demasiados_intentos($ip)) {
        q('INSERT INTO intentos_ingreso (clave, creado) VALUES (?,?)', [$ip, time()]);
        $u = fila('SELECT * FROM usuarios WHERE email = ? AND activo = 1', [$email]);
        if ($u) {
            $token = bin2hex(random_bytes(24));
            q('DELETE FROM recuperos WHERE usuario_id = ? OR vence < ?', [$u['id'], time()]);
            q('INSERT INTO recuperos (token_hash, usuario_id, vence) VALUES (?,?,?)', [hash('sha256', $token), $u['id'], time() + 3600]);
            correo_recupero($u, $token);
        }
    }
    vista('recuperar', ['enviado' => true], ['titulo' => 'Recuperar contraseña', 'noindex' => true]);
}

function recupero_valido(string $token): ?array
{
    return fila('SELECT r.*, u.email FROM recuperos r JOIN usuarios u ON u.id = r.usuario_id WHERE r.token_hash = ? AND r.usado = 0 AND r.vence > ?',
        [hash('sha256', $token), time()]);
}

function pag_recuperar_token(string $token): void
{
    $r = recupero_valido($token);
    vista('recuperar_clave', compact('r', 'token'), ['titulo' => 'Nueva contraseña', 'noindex' => true]);
}

function acc_recuperar_token(string $token): void
{
    $r = recupero_valido($token);
    $clave = (string)($_POST['clave'] ?? '');
    if (!$r) ir('/recuperar/' . $token);
    if (mb_strlen($clave) < 8) {
        flash('La contraseña tiene que tener al menos 8 caracteres.', 'error');
        ir('/recuperar/' . $token);
    }
    q('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $r['usuario_id']]);
    q('UPDATE recuperos SET usado = 1 WHERE token_hash = ?', [$r['token_hash']]);
    entrar_como((int)$r['usuario_id']);
    flash('Contraseña actualizada.');
    ir('/mis-entradas');
}
