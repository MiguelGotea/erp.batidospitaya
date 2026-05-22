<?php
/**
 * AJAX correos_get_catalogos.php
 * Módulo: sistemas
 * Retorna catálogos de Operarios activos y Niveles de Cargos en formato JSON
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permiso de vista para la herramienta
    if (!tienePermiso('correos_corporativos', 'vista', $cargoOperario)) {
        echo json_encode([
            'success' => false,
            'error' => 'No tiene permisos para acceder a esta información.'
        ]);
        exit;
    }

    // 1. Obtener Operarios activos
    // Se filtran los operarios que no han finalizado contrato (Fin es NULL o en el futuro)
    $sqlOperarios = "
        SELECT CodOperario, Nombre, Nombre2, Apellido, Apellido2
        FROM Operarios
        WHERE Fin IS NULL OR Fin >= CURDATE()
        ORDER BY Nombre ASC, Apellido ASC
    ";
    $stmtOps = $conn->prepare($sqlOperarios);
    $stmtOps->execute();
    $operarios = $stmtOps->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener Niveles de Cargos
    $sqlCargos = "
        SELECT CodNivelesCargos, Nombre, Area
        FROM NivelesCargos
        ORDER BY Nombre ASC
    ";
    $stmtCargos = $conn->prepare($sqlCargos);
    $stmtCargos->execute();
    $cargos = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'operarios' => $operarios,
        'cargos' => $cargos
    ]);

} catch (Exception $e) {
    error_log("Error en correos_get_catalogos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener catálogos: ' . $e->getMessage()
    ]);
}
