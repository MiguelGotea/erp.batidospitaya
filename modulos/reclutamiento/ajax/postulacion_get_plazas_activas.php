<?php
// postulacion_get_plazas_activas.php
// Obtiene el listado de plazas activas para mostrarlas en el modal de cambio de plaza.

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permiso básico de vista
    if (!tienePermiso('postulacion_plazas_activas', 'vista', $cargoOperario)) {
        throw new Exception('No tienes permiso para ver esta información.');
    }

    // Consultar las plazas activas y visibles en la web
    $sql = "SELECT 
                pc.id, 
                nc.Nombre as nombre_cargo, 
                s.nombre as sucursal_nombre, 
                pc.cargo, 
                pc.sucursal
            FROM plazas_cargos pc
            INNER JOIN NivelesCargos nc ON pc.cargo = nc.CodNivelesCargos
            LEFT JOIN sucursales s ON pc.sucursal = s.codigo
            WHERE pc.visible_web = 1
              AND (pc.cantidad_real + pc.cantidad_adicional) > 0
            ORDER BY nc.Nombre ASC, s.nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $plazas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'datos' => $plazas
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
