<?php
/**
 * get_grupos_batidos.php
 * Retorna todos los grupos de GrupoProductosVenta, ordenados por prioridad.
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $usuario = obtenerUsuarioActual();
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    if (!tienePermiso('visor_recetas', 'vista', $usuario['CodNivelesCargos'])) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            CodGrupo,
            NombreGrupo,
            Tipo,
            prioridad,
            alias
        FROM GrupoProductosVenta
        ORDER BY prioridad ASC, NombreGrupo ASC
    ");
    $stmt->execute();
    $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $grupos]);

} catch (Exception $e) {
    error_log("Error en get_grupos_batidos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al obtener grupos']);
}
?>