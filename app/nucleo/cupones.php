<?php
// Cupones de descuento: un código que el
// comprador escribe en el checkout. Porcentaje o monto fijo, opcionalmente
// sólo para un evento, con tope de usos, una vez por persona y fechas.
//
// Los usos se cuentan sobre las órdenes NO canceladas: una reserva por
// transferencia que vence o se rechaza devuelve el uso. La compra vuelve a
// validar el cupón dentro del mismo BEGIN IMMEDIATE que el stock, así que dos
// compras simultáneas no pasan el tope.

function cupon_usos(int $cupon_id, ?int $usuario_id = null): int
{
    return (int)valor("SELECT COUNT(*) FROM ordenes WHERE cupon_id = ? AND estado <> 'cancelada'" . ($usuario_id ? ' AND usuario_id = ?' : ''),
        $usuario_id ? [$cupon_id, $usuario_id] : [$cupon_id]);
}

/**
 * ¿Vale este código para esta compra? Devuelve ['cupon' => fila, 'descuento' => pesos]
 * o ['error' => 'texto para el comprador'].
 */
function cupon_aplicar(string $codigo, array $fn, int $subtotal, int $usuario_id): array
{
    $c = fila('SELECT * FROM cupones WHERE codigo = ? AND activo = 1', [trim($codigo)]);
    if (!$c) return ['error' => 'Ese cupón no existe o ya no está activo.'];
    if ($c['desde'] && $c['desde'] > ahora()) return ['error' => 'Ese cupón todavía no está vigente: vale desde el ' . fecha_corta($c['desde']) . ' h.'];
    if ($c['vence'] && $c['vence'] <= ahora()) return ['error' => 'Ese cupón venció el ' . fecha_corta($c['vence']) . ' h.'];
    if ($c['evento_id'] && (int)$c['evento_id'] !== (int)$fn['evento_id']) return ['error' => 'Ese cupón no vale para este evento.'];
    if ($c['usos_max'] !== null && cupon_usos((int)$c['id']) >= (int)$c['usos_max']) return ['error' => 'Ese cupón ya se usó todas las veces que permitía.'];
    if ($c['por_persona'] && cupon_usos((int)$c['id'], $usuario_id) > 0) return ['error' => 'Ya usaste ese cupón en otra compra.'];
    if ($subtotal <= 0) return ['error' => 'Esta compra no tiene nada que descontar.'];
    $desc = $c['tipo'] === 'pct' ? (int)floor($subtotal * min(100, (int)$c['valor']) / 100) : min((int)$c['valor'], $subtotal);
    return ['cupon' => $c, 'descuento' => $desc];
}

function cupon_texto(array $c): string
{
    return $c['tipo'] === 'pct' ? (int)$c['valor'] . ' % menos' : pesos((int)$c['valor']) . ' menos';
}

/** Desde el checkout: aplicar o quitar el código. Queda en el carrito de la sesión. */
function acc_checkout_cupon(): void
{
    requerir_login();
    if (empty($_SESSION['carrito'])) ir('/');
    if (!empty($_POST['quitar'])) {
        unset($_SESSION['carrito']['cupon']);
        ir('/checkout');
    }
    $codigo = mb_substr(trim((string)($_POST['cupon'] ?? '')), 0, 40);
    if ($codigo === '') ir('/checkout');
    $_SESSION['carrito']['cupon'] = $codigo;
    // armar_carrito lo valida; si no sirve, lo saca y avisa.
    [, , , , , $cup] = armar_carrito();
    if (!empty($cup['error'])) {
        unset($_SESSION['carrito']['cupon']);
        flash($cup['error'], 'error');
        ir('/checkout?cupon=1#cupon'); // con el campo abierto para corregirlo
    }
    flash('Cupón aplicado: ' . cupon_texto($cup['cupon']) . '.');
    ir('/checkout');
}

// ── Panel ──────────────────────────────────────────────────────────────────

function adm_cupones(): void
{
    requerir_rol('admin');
    $cupones = filas("SELECT c.*, e.titulo AS evento,
                        (SELECT COUNT(*) FROM ordenes o WHERE o.cupon_id = c.id AND o.estado <> 'cancelada') AS usos,
                        (SELECT COALESCE(SUM(o.descuento), 0) FROM ordenes o WHERE o.cupon_id = c.id AND o.estado <> 'cancelada') AS descontado
                      FROM cupones c LEFT JOIN eventos e ON e.id = c.evento_id ORDER BY c.activo DESC, c.creado DESC");
    $eventos = filas("SELECT id, titulo FROM eventos WHERE estado <> 'cancelado' ORDER BY titulo");
    vista('admin/cupones', compact('cupones', 'eventos'), ['titulo' => 'Cupones', 'noindex' => true]);
}

function adm_cupon_crear(): void
{
    requerir_rol('admin');
    $P = $_POST;
    $codigo = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($P['codigo'] ?? '')));
    $tipo = ($P['tipo'] ?? '') === 'monto' ? 'monto' : 'pct';
    $valor = (int)preg_replace('/\D/', '', (string)($P['valor'] ?? ''));
    $fecha = fn($k) => ($v = trim((string)($P[$k] ?? ''))) !== '' && strtotime($v) ? date('Y-m-d H:i:s', strtotime($v)) : null;
    $err = [];
    if (strlen($codigo) < 3) $err[] = 'El código tiene que tener al menos 3 letras o números.';
    elseif (valor('SELECT 1 FROM cupones WHERE codigo = ?', [$codigo])) $err[] = "Ya existe un cupón «{$codigo}».";
    if ($valor < 1) $err[] = 'Poné cuánto descuenta.';
    if ($tipo === 'pct' && $valor > 100) $err[] = 'Un porcentaje no puede pasar de 100.';
    if ($fecha('desde') && $fecha('vence') && $fecha('vence') <= $fecha('desde')) $err[] = 'El vencimiento tiene que ser posterior al inicio.';
    if ($err) {
        foreach ($err as $x) flash($x, 'error');
        $_SESSION['cupon_form'] = $P;
        ir('/admin/cupones#nuevo');
    }
    q('INSERT INTO cupones (codigo, tipo, valor, evento_id, usos_max, por_persona, desde, vence, nota, creado) VALUES (?,?,?,?,?,?,?,?,?,?)', [
        $codigo, $tipo, $valor, (int)($P['evento_id'] ?? 0) ?: null, (int)($P['usos_max'] ?? 0) ?: null, !empty($P['por_persona']) ? 1 : 0,
        $fecha('desde'), $fecha('vence'), mb_substr(trim((string)($P['nota'] ?? '')), 0, 120), ahora()]);
    flash("Cupón {$codigo} creado.");
    ir('/admin/cupones');
}

function adm_cupon_activo(string $id): void
{
    requerir_rol('admin');
    q('UPDATE cupones SET activo = 1 - activo WHERE id = ?', [$id]);
    ir('/admin/cupones');
}

function adm_cupon_borrar(string $id): void
{
    requerir_rol('admin');
    // Con compras hechas no se borra (las órdenes lo referencian): se desactiva.
    if (valor('SELECT 1 FROM ordenes WHERE cupon_id = ?', [$id])) flash('Ese cupón ya se usó: desactivalo en vez de borrarlo.', 'error');
    else { q('DELETE FROM cupones WHERE id = ?', [$id]); flash('Cupón borrado.'); }
    ir('/admin/cupones');
}
