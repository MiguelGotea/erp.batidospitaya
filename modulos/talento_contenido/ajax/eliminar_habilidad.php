<?php
// eliminar_habilidad.php - Eliminar una habilidad del catálogo global
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('talento_contenido', 'eliminar', $cargoOperario)) {
        throw new Exception("No tienes privilegios para eliminar habilidades.");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $habilidad_id = isset($input['id']) ? intval($input['id']) : 0;

    if ($habilidad_id <= 0) {
        throw new Exception("ID de habilidad inválido.");
    }

    // Iniciar transacción para garantizar consistencia
    $conn->beginTransaction();

    // 1. Eliminar relaciones en la tabla intermedia de plazas
    $stmtRel = $conn->prepare("DELETE FROM plazas_habilidades_talento WHERE habilidad_id = :habilidad_id");
    $stmtRel->bindValue(':habilidad_id', $habilidad_id, PDO::PARAM_INT);
    $stmtRel->execute();

    // 2. Eliminar la habilidad de la tabla catálogo
    $stmt = $conn->prepare("DELETE FROM habilidades_talento WHERE id = :id");
    $stmt->bindValue(':id', $habilidad_id, PDO::PARAM_INT);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Habilidad eliminada correctamente del catálogo y de las plazas asociadas."
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
