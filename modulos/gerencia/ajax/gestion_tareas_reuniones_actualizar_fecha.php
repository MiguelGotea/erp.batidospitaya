<?php
/**
 * Actualizar fecha meta de tarea
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo = $usuario['CodNivelesCargos'];

    $id = intval($_POST['id'] ?? 0);
    $fechaMeta = $_POST['fecha_meta'] ?? '';

    if ($id <= 0 || empty($fechaMeta)) {
        throw new Exception('Datos incompletos');
    }

    // Obtener el item
    $sql = "SELECT * FROM gestion_tareas_reuniones_items WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Item no encontrado');
    }

    // Verificar permisos (asignado o creador)
    $esAsignado = ($item['cod_cargo_asignado'] == $codCargo);
    $esCreador = ($item['cod_operario_creador'] == $codOperario);

    if (!$esAsignado && !$esCreador) {
        throw new Exception('No tiene permisos para editar la fecha');
    }

    // Actualizar fecha meta
    $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                  SET fecha_meta = :fecha_meta,
                      fecha_ultima_modificacion = NOW(),
                      cod_operario_ultima_modificacion = :cod_operario
                  WHERE id = :id";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':fecha_meta' => $fechaMeta,
        ':cod_operario' => $codOperario,
        ':id' => $id
    ]);

    // Si es subtarea y la nueva fecha es mayor que la del padre, actualizar el padre
    if ($item['tipo'] == 'subtarea' && $item['id_padre']) {
        $sqlPadre = "SELECT fecha_meta FROM gestion_tareas_reuniones_items WHERE id = :id";
        $stmtPadre = $conn->prepare($sqlPadre);
        $stmtPadre->execute([':id' => $item['id_padre']]);
        $padre = $stmtPadre->fetch(PDO::FETCH_ASSOC);

        if ($padre && $fechaMeta > $padre['fecha_meta']) {
            $sqlUpdatePadre = "UPDATE gestion_tareas_reuniones_items 
                               SET fecha_meta = :fecha_meta,
                                   fecha_ultima_modificacion = NOW()
                               WHERE id = :id";
            $stmtUpdatePadre = $conn->prepare($sqlUpdatePadre);
            $stmtUpdatePadre->execute([
                ':fecha_meta' => $fechaMeta,
                ':id' => $item['id_padre']
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Fecha actualizada exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error en actualizar_fecha: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>