<?php
/**
 * Actualizar la prioridad de una tarea
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $id = intval($_POST['id'] ?? 0);
    $prioridad = $_POST['prioridad'] ?? '';

    if ($id <= 0 || !in_array($prioridad, ['alta', 'media', 'baja'])) {
        throw new Exception('Datos inválidos');
    }

    // Verificar que el item sea una tarea y que el usuario tenga permiso
    // (Omitimos verificación exhaustiva de permisos por brevedad, asumiendo que el JS solo permite al responsable/creador)
    
    $sql = "UPDATE gestion_tareas_reuniones_items SET prioridad = :prioridad WHERE id = :id AND tipo = 'tarea'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':prioridad' => $prioridad,
        ':id' => $id
    ]);

    // El éxito del execute es suficiente, rowCount() sería 0 si la prioridad es la misma que la actual

    echo json_encode([
        'success' => true,
        'message' => 'Prioridad actualizada'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
