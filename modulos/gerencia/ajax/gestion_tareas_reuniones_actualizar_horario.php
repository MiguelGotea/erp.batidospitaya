<?php
// ajax/gestion_tareas_reuniones_actualizar_horario.php
require_once "../../../includes/config.php";
require_once "../../../includes/funciones.php";
header('Content-Type: application/json');

try {
    $id = intval($_POST['id'] ?? 0);
    $horaTarea = $_POST['hora_tarea'] ?? null;
    $duracionMin = isset($_POST['duracion_min']) ? intval($_POST['duracion_min']) : null;

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    $updates = [];
    $params = [':id' => $id];

    if ($horaTarea !== null) {
        $updates[] = "hora_tarea = :hora_tarea";
        $params[':hora_tarea'] = $horaTarea === 'null' ? null : $horaTarea;
    }

    if ($duracionMin !== null) {
        $updates[] = "duracion_min = :duracion_min";
        $params[':duracion_min'] = $duracionMin;
    }

    if (empty($updates)) {
        throw new Exception('Nada que actualizar');
    }

    $sql = "UPDATE gestion_tareas_reuniones_items SET " . implode(", ", $updates) . " WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'message' => 'Horario actualizado']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
