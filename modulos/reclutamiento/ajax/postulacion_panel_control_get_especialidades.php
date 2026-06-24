<?php
// postulacion_panel_control_get_especialidades.php
// Retorna la lista de valores únicos de especialidad_area en NivelesCargos

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT DISTINCT especialidad_area
            FROM NivelesCargos
            WHERE especialidad_area IS NOT NULL
              AND especialidad_area != ''
            ORDER BY especialidad_area ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success'       => true,
        'especialidades' => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
