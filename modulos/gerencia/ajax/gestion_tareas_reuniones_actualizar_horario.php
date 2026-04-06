<?php
// ajax/gestion_tareas_reuniones_actualizar_horario.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/helpers/funciones.php';
header('Content-Type: application/json');


try {
    global $conn;
    $id = intval($_POST['id'] ?? 0);
    $horaTarea = $_POST['hora_tarea'] ?? null;
    $duracionMin = isset($_POST['duracion_min']) ? intval($_POST['duracion_min']) : null;

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Obtener info actual para saber si es reunión o tarea
    $stmtInfo = $conn->prepare("SELECT tipo, fecha_reunion FROM gestion_tareas_reuniones_items WHERE id = ?");
    $stmtInfo->execute([$id]);
    $item = $stmtInfo->fetch();

    if (!$item)
        throw new Exception('Item no encontrado');

    $updates = [];
    $params = [':id' => $id];

    if ($horaTarea !== null) {
        if ($item['tipo'] === 'reunion') {
            // Actualizar solo la parte de la hora de fecha_reunion
            $fechaSolo = substr($item['fecha_reunion'] ?? date('Y-m-d'), 0, 10);
            $nuevaFechaHora = $fechaSolo . ' ' . $horaTarea . ':00';
            $updates[] = "fecha_reunion = :fecha_reunion";
            $params[':fecha_reunion'] = $nuevaFechaHora;
        } else {
            $updates[] = "hora_tarea = :hora_tarea";
            $params[':hora_tarea'] = $horaTarea === 'null' ? null : $horaTarea;
        }
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
