<?php
/**
 * AJAX correos_get.php
 * Módulo: sistemas
 * Obtiene la lista completa de correos corporativos o un correo individual
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permiso de vista
    if (!tienePermiso('correos_corporativos', 'vista', $cargoOperario)) {
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
                cc.*,
                nc.Nombre AS cargo_nombre,
                CONCAT(o.Nombre, ' ', COALESCE(o.Apellido, ''), ' ', COALESCE(o.Apellido2, '')) AS asignado_a_nombre
            FROM Correos_Corporativos cc
            LEFT JOIN NivelesCargos nc ON cc.cargo_asignado = nc.CodNivelesCargos
            LEFT JOIN Operarios o ON cc.asignado_a = o.CodOperario
            WHERE cc.id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $correo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($correo) {
            echo json_encode([
                'success' => true,
                'data' => $correo
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No se encontró el correo especificado.'
            ]);
        }
    } else {
        // Obtener listado general
        $sql = "
            SELECT 
                cc.id,
                cc.correo,
                cc.proveedor,
                cc.nombre_usuario,
                cc.password_correo,
                cc.cargo_asignado,
                cc.asignado_a,
                cc.fecha_asignacion,
                cc.departamento,
                cc.estado,
                cc.observaciones,
                cc.fecha_registro,
                cc.fecha_modificada,
                nc.Nombre AS cargo_nombre,
                CONCAT(o.Nombre, ' ', COALESCE(o.Apellido, ''), ' ', COALESCE(o.Apellido2, '')) AS asignado_a_nombre,
                CONCAT(oc.Nombre, ' ', COALESCE(oc.Apellido, '')) AS creador_nombre,
                CONCAT(om.Nombre, ' ', COALESCE(om.Apellido, '')) AS modificador_nombre
            FROM Correos_Corporativos cc
            LEFT JOIN NivelesCargos nc ON cc.cargo_asignado = nc.CodNivelesCargos
            LEFT JOIN Operarios o ON cc.asignado_a = o.CodOperario
            LEFT JOIN Operarios oc ON cc.usuario_creador = oc.CodOperario
            LEFT JOIN Operarios om ON cc.usuario_modifica = om.CodOperario
            ORDER BY cc.correo ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $correos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $correos
        ]);
    }

} catch (Exception $e) {
    error_log("Error en correos_get.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al cargar correos: ' . $e->getMessage()
    ]);
}
