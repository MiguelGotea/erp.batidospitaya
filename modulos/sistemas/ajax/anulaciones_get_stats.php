<?php
/**
 * erp/modulos/sistemas/ajax/anulaciones_get_stats.php
 * Obtiene estadísticas de las solicitudes de anulación.
 */

require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

try {
    /** @var PDO $pdo */
    global $conn;
    $pdo = $conn;

    // 1. Pendientes totales
    $stmtPendientes = $pdo->query("SELECT COUNT(*) FROM AnulacionPedidosHost WHERE Status = 0");
    $pendientes = (int)$stmtPendientes->fetchColumn();

    // 2. Aprobadas hoy
    $stmtAprobadasHoy = $pdo->query("SELECT COUNT(*) FROM AnulacionPedidosHost WHERE Status = 1 AND DATE(FechaAprobacion) = CURDATE()");
    $aprobadasHoy = (int)$stmtAprobadasHoy->fetchColumn();

    // 3. Pendientes críticas (más de 1 hora sin respuesta)
    $stmtCriticas = $pdo->query("SELECT COUNT(*) FROM AnulacionPedidosHost WHERE Status = 0 AND HoraSolicitada < (NOW() - INTERVAL 1 HOUR)");
    $criticas = (int)$stmtCriticas->fetchColumn();

    // 4. Ejecutadas hoy (por las tiendas)
    $stmtEjecutadasHoy = $pdo->query("SELECT COUNT(*) FROM AnulacionPedidosHost WHERE Status = 1 AND EjecutadoEnTienda = 1 AND DATE(HoraEjecutadaTienda) = CURDATE()");
    $ejecutadasHoy = (int)$stmtEjecutadasHoy->fetchColumn();

    echo json_encode([
        'success' => true,
        'stats' => [
            'pendientes' => $pendientes,
            'aprobadasHoy' => $aprobadasHoy,
            'criticas' => $criticas,
            'ejecutadasHoy' => $ejecutadasHoy
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
