<?php
/**
 * Aprobar tarea solicitada
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo = $usuario['CodNivelesCargos'];

    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Obtener el item
    $sql = "SELECT * FROM gestion_tareas_reuniones_items WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Tarea no encontrada');
    }

    // Verificar que es tarea solicitada y el usuario es el asignado
    if ($item['tipo'] != 'tarea') {
        throw new Exception('Solo se pueden aprobar tareas');
    }

    if ($item['estado'] != 'solicitado') {
        throw new Exception('La tarea no está en estado solicitado');
    }

    if ($item['cod_cargo_asignado'] != $codCargo) {
        throw new Exception('No tiene permisos para aprobar esta tarea');
    }

    // Actualizar estado a en_progreso
    $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                  SET estado = 'en_progreso',
                      fecha_ultima_modificacion = NOW(),
                      cod_operario_ultima_modificacion = :cod_operario
                  WHERE id = :id";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':cod_operario' => $codOperario,
        ':id' => $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Tarea aprobada exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error en aprobar: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>