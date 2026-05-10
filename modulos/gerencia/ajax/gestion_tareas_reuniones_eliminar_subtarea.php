<?php
/**
 * Eliminar subtarea
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

    // Obtener la subtarea
    $sql = "SELECT * FROM gestion_tareas_reuniones_items WHERE id = :id AND tipo = 'subtarea'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $subtarea = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subtarea) {
        throw new Exception('Subtarea no encontrada');
    }

    // Verificar permisos
    if ($subtarea['cod_cargo_asignado'] != $codCargo) {
        throw new Exception('No tiene permisos para eliminar esta subtarea');
    }

    $idPadre = $subtarea['id_padre'];

    // Eliminar subtarea (los archivos se eliminan por CASCADE)
    $sqlDelete = "DELETE FROM gestion_tareas_reuniones_items WHERE id = :id";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->execute([':id' => $id]);

    // Actualizar progreso de la tarea padre
    if ($idPadre) {
        actualizarProgresoTarea($conn, $idPadre);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Subtarea eliminada exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error en eliminar_subtarea: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Actualizar progreso de tarea basado en subtareas
 */
function actualizarProgresoTarea($conn, $idTarea)
{
    // Contar subtareas totales y finalizadas
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizadas
            FROM gestion_tareas_reuniones_items
            WHERE id_padre = :id_padre AND tipo = 'subtarea'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_padre' => $idTarea]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = intval($resultado['total']);
    $finalizadas = intval($resultado['finalizadas']);

    if ($total > 0) {
        $progreso = ($finalizadas / $total) * 100;

        // Si todas las subtareas están finalizadas, finalizar la tarea padre
        if ($finalizadas == $total) {
            $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                          SET estado = 'finalizado',
                              progreso = 100,
                              fecha_finalizacion = NOW(),
                              fecha_ultima_modificacion = NOW()
                          WHERE id = :id";
        } else {
            $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                          SET progreso = :progreso,
                              fecha_ultima_modificacion = NOW()
                          WHERE id = :id";
        }

        $stmtUpdate = $conn->prepare($sqlUpdate);
        $params = [':id' => $idTarea];

        if ($finalizadas != $total) {
            $params[':progreso'] = $progreso;
        }

        $stmtUpdate->execute($params);
    } else {
        // Si no hay subtareas, resetear progreso
        $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                      SET progreso = 0,
                          fecha_ultima_modificacion = NOW()
                      WHERE id = :id";

        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->execute([':id' => $idTarea]);
    }
}
?>