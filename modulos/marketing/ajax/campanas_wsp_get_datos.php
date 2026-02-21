<?php
/**
 * campanas_wsp_get_datos.php
 * Retorna campañas paginadas para la tabla
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('campanas_wsp', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$rpp = in_array((int) ($_GET['rpp'] ?? 25), [25, 50, 100]) ? (int) $_GET['rpp'] : 25;
$offset = ($pagina - 1) * $rpp;

try {
    // Total
    $total = $conn->query("SELECT COUNT(*) c FROM wsp_campanas_")->fetch_assoc()['c'];

    // Datos paginados
    $stmt = $conn->prepare("
        SELECT 
            id, nombre, mensaje, imagen_url,
            DATE_FORMAT(fecha_envio, '%Y-%m-%d %H:%i') AS fecha_envio,
            estado, total_destinatarios, total_enviados, total_errores,
            DATE_FORMAT(fecha_creacion, '%d-%b-%y') AS fecha_creacion
        FROM wsp_campanas_
        ORDER BY fecha_creacion DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ii', $rpp, $offset);
    $stmt->execute();
    $campanas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'campanas' => $campanas, 'total' => (int) $total]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
