<?php
/**
 * suc_get_departamentos.php
 * GET: Lista todos los departamentos de la tabla departamentos
 * Permiso requerido: configuracion_sucursales > vista
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('configuracion_sucursales', 'vista', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso de acceso']);
        exit;
    }


    $sql = "SELECT codigo, nombre FROM departamentos ORDER BY nombre ASC";
    $stmt = $conn->query($sql);
    $departamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $departamentos]);

} catch (Exception $e) {
    error_log("Error en suc_get_departamentos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar departamentos: ' . $e->getMessage()]);
}
?>
