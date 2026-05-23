<?php
/**
 * AJAX celulares_get_catalogos.php
 * Módulo: sistemas
 * Retorna catálogos de Operarios activos, Niveles de Cargos y Sucursales en formato JSON
 *
 * Nota: se usa CONCAT_WS + NULLIF para que operarios sin Nombre2 o Apellido2
 * aparezcan correctamente en los selectores (CONCAT_WS omite NULLs y cadenas vacías).
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

    // 1. Obtener Operarios activos
    // CONCAT_WS omite NULLs automáticamente. NULLIF convierte cadenas vacías en NULL
    // para que tampoco aparezcan como espacios extra en el nombre.
    $sqlOperarios = "
        SELECT
            CodOperario,
            CONCAT_WS(' ',
                NULLIF(TRIM(Nombre),   ''),
                NULLIF(TRIM(Nombre2),  ''),
                NULLIF(TRIM(Apellido), ''),
                NULLIF(TRIM(Apellido2),'')
            ) AS nombre_completo
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

    // 3. Obtener Sucursales
    $sqlSucursales = "
        SELECT codigo, nombre
        FROM sucursales
        ORDER BY nombre ASC
    ";
    $stmtSuc = $conn->prepare($sqlSucursales);
    $stmtSuc->execute();
    $sucursales = $stmtSuc->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'operarios' => $operarios,
        'cargos' => $cargos,
        'sucursales' => $sucursales
    ]);

} catch (Exception $e) {
    error_log("Error en celulares_get_catalogos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener catálogos: ' . $e->getMessage()
    ]);
}
