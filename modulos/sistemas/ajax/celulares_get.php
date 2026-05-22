<?php
/**
 * AJAX celulares_get.php
 * Módulo: sistemas
 * Obtiene la lista completa de celulares asignados o un celular individual
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permiso de vista
    if (!tienePermiso('celulares_asignados', 'vista', $cargoOperario)) {
        echo json_encode([
            'success' => false,
            'error' => 'No tiene permisos para acceder a esta información.'
        ]);
        exit;
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        // Obtener un solo registro
        $sql = "
            SELECT 
                ca.*,
                s.nombre AS sucursal_nombre,
                nc.Nombre AS cargo_nombre,
                CONCAT(o.Nombre, ' ', COALESCE(o.Apellido, ''), ' ', COALESCE(o.Apellido2, '')) AS usuario_uso_nombre
            FROM Celulares_Asignados ca
            LEFT JOIN sucursales s ON ca.cod_sucursal = s.codigo
            LEFT JOIN NivelesCargos nc ON ca.cargo_asignado = nc.CodNivelesCargos
            LEFT JOIN Operarios o ON ca.usuario_uso = o.CodOperario
            WHERE ca.id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $celular = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($celular) {
            echo json_encode([
                'success' => true,
                'data' => $celular
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No se encontró el celular especificado.'
            ]);
        }
    } else {
        // Obtener listado general
        $sql = "
            SELECT 
                ca.id,
                ca.nombre,
                ca.modelo,
                ca.serie,
                ca.cod_sucursal,
                ca.no_sim,
                ca.departamento,
                ca.IMEI,
                ca.IMSI,
                ca.cargo_asignado,
                ca.usuario_uso,
                ca.fecha_registro,
                ca.fecha_modificada,
                s.nombre AS sucursal_nombre,
                nc.Nombre AS cargo_nombre,
                CONCAT(o.Nombre, ' ', COALESCE(o.Apellido, ''), ' ', COALESCE(o.Apellido2, '')) AS usuario_uso_nombre,
                CONCAT(oc.Nombre, ' ', COALESCE(oc.Apellido, '')) AS creador_nombre,
                CONCAT(om.Nombre, ' ', COALESCE(om.Apellido, '')) AS modificador_nombre
            FROM Celulares_Asignados ca
            LEFT JOIN sucursales s ON ca.cod_sucursal = s.codigo
            LEFT JOIN NivelesCargos nc ON ca.cargo_asignado = nc.CodNivelesCargos
            LEFT JOIN Operarios o ON ca.usuario_uso = o.CodOperario
            LEFT JOIN Operarios oc ON ca.usuario_creador = oc.CodOperario
            LEFT JOIN Operarios om ON ca.usuario_modifica = om.CodOperario
            ORDER BY ca.nombre ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $celulares = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $celulares
        ]);
    }

} catch (Exception $e) {
    error_log("Error en celulares_get.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar celulares: ' . $e->getMessage()
    ]);
}
