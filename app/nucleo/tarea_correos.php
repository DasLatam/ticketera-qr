<?php
// El trabajo de la tarea programada: despachar la cola y dejar la marca de
// la corrida en `ajustes` (se ve en Administración › Correos). Lo llaman
// cron_correos.php (consola) y la ruta /tarea/correos (web, con clave).
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/transferencias.php';
require_once __DIR__ . '/mercadopago.php';
// Primero se buscan los pagos que entraron: una reserva pagada a último
// momento no tiene que vencerse.
if (mp_conectado()) {
    try { mp_sincronizar(3); } catch (Throwable $ex) { error_log('[app mp] ' . $ex->getMessage()); guardar_ajuste('mp_error', ahora() . ' · ' . mb_substr($ex->getMessage(), 0, 200)); }
}
vencer_reservas();
$enviados_tarea = despachar_correos(30);
q("INSERT INTO ajustes (clave, valor) VALUES ('cron_correos', ?) ON CONFLICT (clave) DO UPDATE SET valor = excluded.valor", [ahora() . '|' . $enviados_tarea]);
if (PHP_SAPI === 'cli' && $enviados_tarea) echo date('Y-m-d H:i') . " enviados: $enviados_tarea\n";
