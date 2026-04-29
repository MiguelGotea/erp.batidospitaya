<?php
// toggle_link_status.php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $idPostulacion = (int) ($input['id_postulacion'] ?? 0);
    $nuevoStatus = $input['status'] ?? 'activo';

    if ($idPostulacion <= 0) {
        throw new Exception('ID de postulación inválido');
    }

    if (!in_array($nuevoStatus, ['activo', 'deshabilitado'])) {
        throw new Exception('Estatus inválido');
    }

    $sql = "UPDATE solicitud_empleo SET link_status = :status WHERE id_postulacion = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':status' => $nuevoStatus,
        ':id' => $idPostulacion
    ]);

    echo json_encode(['success' => true, 'status' => $nuevoStatus]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>