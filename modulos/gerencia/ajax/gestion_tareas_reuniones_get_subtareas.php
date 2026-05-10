<?php
/**
 * Obtener subtareas de una tarea
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $idPadre = intval($_POST['id_padre'] ?? 0);

    if ($idPadre <= 0) {
        throw new Exception('ID inválido');
    }

    $sql = "SELECT * FROM gestion_tareas_reuniones_items 
            WHERE id_padre = :id_padre AND tipo = 'subtarea'
            ORDER BY fecha_meta ASC, id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_padre' => $idPadre]);
    $subtareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'subtareas' => $subtareas
    ]);

} catch (Exception $e) {
    error_log("Error en get_subtareas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>