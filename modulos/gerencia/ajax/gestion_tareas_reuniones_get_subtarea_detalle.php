<?php
/**
 * Obtener detalles de una subtarea específica para el modal de vista
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    $sql = "SELECT 
                i.*,
                o_creador.Nombre as nombre_creador,
                o_creador.Apellido as apellido_creador,
                o_ultima.Nombre as nombre_finalizador,
                o_ultima.Apellido as apellido_finalizador
            FROM gestion_tareas_reuniones_items i
            LEFT JOIN Operarios o_creador ON i.cod_operario_creador = o_creador.CodOperario
            LEFT JOIN Operarios o_ultima ON i.cod_operario_ultima_modificacion = o_ultima.CodOperario
            WHERE i.id = :id AND i.tipo = 'subtarea'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $subtarea = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subtarea) {
        throw new Exception('Subtarea no encontrada');
    }

    // Obtener archivos de la subtarea
    $sqlArchivos = "SELECT * FROM gestion_tareas_reuniones_archivos WHERE id_item = :id";
    $stmtArchivos = $conn->prepare($sqlArchivos);
    $stmtArchivos->execute([':id' => $id]);
    $archivos = $stmtArchivos->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'subtarea' => $subtarea,
        'archivos' => $archivos
    ]);

} catch (Exception $e) {
    error_log("Error en get_subtarea_detalle: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>