<?php
/**
 * ajax/conexion_monitor_get.php
 * Devuelve el estado actual de todas las PCs que envían ping.
 * Usado por el panel de monitoreo en tiempo real (polling cada 15s).
 */

require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

try {
    $ahora = date('Y-m-d H:i:s');

    // Estado actual de cada PC (último ping por equipo)
    $sqlEstados = "
        SELECT
            p.sucursal_codigo,
            p.pc_nombre,
            p.pc_usuario,
            p.ip_local,
            p.ip_publica,
            p.version_access,
            p.modulo_activo,
            p.ping_at,
            TIMESTAMPDIFF(SECOND, p.ping_at, :ahora1)  AS segundos_sin_ping,
            CASE
                WHEN TIMESTAMPDIFF(SECOND, p.ping_at, :ahora2) <= 300  THEN 'online'
                WHEN TIMESTAMPDIFF(SECOND, p.ping_at, :ahora3) <= 1800  THEN 'alerta'
                ELSE 'offline'
            END AS estado,
            COALESCE(s.nombre, p.sucursal_codigo) AS nombre_sucursal
        FROM sistemas_ping_log p
        INNER JOIN (
            SELECT sucursal_codigo, pc_nombre, MAX(ping_at) AS ultimo_ping
            FROM sistemas_ping_log
            GROUP BY sucursal_codigo, pc_nombre
        ) latest
            ON  p.sucursal_codigo = latest.sucursal_codigo
            AND p.pc_nombre       = latest.pc_nombre
            AND p.ping_at         = latest.ultimo_ping
        LEFT JOIN sucursales s ON s.codigo = p.sucursal_codigo
        WHERE s.sucursal = 1
        ORDER BY s.nombre ASC, p.pc_nombre ASC
    ";
    $stmtEstados = $conn->prepare($sqlEstados);
    $stmtEstados->execute([
        ':ahora1' => $ahora,
        ':ahora2' => $ahora,
        ':ahora3' => $ahora
    ]);
    $pcs = $stmtEstados->fetchAll(PDO::FETCH_ASSOC);

    // ── Sincronizar ip_direccion en sucursales ──────────────────────────────
    // Actualiza sucursales.ip_direccion con la ip_publica más reciente del ping,
    // SOLO cuando el valor realmente cambió (no en cada ping, sino en cada poll).
    $sqlSyncIp = "
        UPDATE sucursales s
        INNER JOIN (
            SELECT p.sucursal_codigo, p.ip_publica
            FROM sistemas_ping_log p
            INNER JOIN (
                SELECT sucursal_codigo, MAX(ping_at) AS ultimo_ping
                FROM sistemas_ping_log
                GROUP BY sucursal_codigo
            ) latest
                ON  p.sucursal_codigo = latest.sucursal_codigo
                AND p.ping_at         = latest.ultimo_ping
            WHERE p.ip_publica IS NOT NULL AND p.ip_publica != ''
        ) ip_actual ON s.codigo = ip_actual.sucursal_codigo
        SET s.ip_direccion = ip_actual.ip_publica
        WHERE (s.ip_direccion IS NULL OR s.ip_direccion != ip_actual.ip_publica)
    ";
    $stmtSync = $conn->exec($sqlSyncIp);
    $ips_actualizadas = (int) $stmtSync; // filas realmente modificadas
    // ───────────────────────────────────────────────────────────────────────


    // Resumen por estado
    $resumen = ['online' => 0, 'alerta' => 0, 'offline' => 0, 'total' => count($pcs)];
    foreach ($pcs as $pc) {
        $resumen[$pc['estado']]++;
    }

    // Actividad reciente (últimos 30 pings)
    $sqlReciente = "
        SELECT
            sucursal_codigo,
            pc_nombre,
            ping_at,
            COALESCE(s.nombre, pl.sucursal_codigo) AS nombre_sucursal
        FROM sistemas_ping_log pl
        INNER JOIN sucursales s ON s.codigo = pl.sucursal_codigo
        WHERE s.sucursal = 1
        ORDER BY ping_at DESC
        LIMIT 30
    ";
    $stmtReciente = $conn->query($sqlReciente);
    $actividad = $stmtReciente->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'pcs' => $pcs,
        'resumen' => $resumen,
        'actividad' => $actividad,
        'ips_actualizadas' => $ips_actualizadas,
        'server_time' => date('Y-m-d H:i:s'),
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    error_log("[conexion_monitor_get.php] " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}
?>