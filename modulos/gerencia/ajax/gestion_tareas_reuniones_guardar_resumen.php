<?php
/**
 * Guardar resumen de reunión
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    $id = intval($_POST['id'] ?? 0);
    $resumen = $_POST['resumen'] ?? '';

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Obtener reunión
    $sql = "SELECT * FROM gestion_tareas_reuniones_items WHERE id = :id AND tipo = 'reunion'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $reunion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reunion) {
        throw new Exception('Reunión no encontrada');
    }

    // Verificar que es el creador
    if ($reunion['cod_operario_creador'] != $codOperario) {
        throw new Exception('Solo el creador puede editar el resumen');
    }

    // Actualizar resumen
    $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                  SET resumen_reunion = :resumen,
                      fecha_ultima_modificacion = NOW(),
                      cod_operario_ultima_modificacion = :cod_operario
                  WHERE id = :id";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':resumen' => $resumen,
        ':cod_operario' => $codOperario,
        ':id' => $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Resumen guardado exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error en guardar_resumen: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>