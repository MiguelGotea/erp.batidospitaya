<?php
/**
 * Cancelar tarea o reunión
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

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
        throw new Exception('Item no encontrado');
    }

    // Verificar permisos
    $permisoCancelar = tienePermiso('gestion_tareas_reuniones', 'cancelar_tarea_reunion', $codCargo);
    $esCreador = ($item['cod_operario_creador'] == $codOperario);

    if (!$permisoCancelar && !$esCreador) {
        throw new Exception('No tiene permisos para cancelar este item');
    }

    // Actualizar estado
    $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                  SET estado = 'cancelado', 
                      fecha_ultima_modificacion = NOW(),
                      cod_operario_ultima_modificacion = :cod_operario
                  WHERE id = :id";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':id' => $id,
        ':cod_operario' => $codOperario
    ]);

    $tipoTexto = $item['tipo'] == 'reunion' ? 'Reunión' : 'Tarea';

    echo json_encode([
        'success' => true,
        'message' => $tipoTexto . ' cancelada exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error en cancelar: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>