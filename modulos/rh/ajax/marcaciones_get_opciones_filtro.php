<?php
/**
 * AJAX Endpoint: Obtener opciones para filtros tipo "list"
 * Ubicación: /modulos/rh/ajax/marcaciones_get_opciones_filtro.php
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!tienePermiso('historial_marcaciones_globales', 'vista', $usuario['CodNivelesCargos'])) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit();
}

// Obtener permisos del usuario
$esLider = tienePermiso('historial_marcaciones_globales', 'permisoslider', $usuario['CodNivelesCargos']);
$esCDS = tienePermiso('historial_marcaciones_globales', 'permisoscds', $usuario['CodNivelesCargos']);
$esOperaciones = tienePermiso('historial_marcaciones_globales', 'permisosoperaciones', $usuario['CodNivelesCargos']);


$columna = $_POST['columna'] ?? '';
$opciones = [];

try {
    switch ($columna) {
        case 'nombre_sucursal':
            // Obtener sucursales según permisos
            if ($esLider) {
                // Líderes solo ven su sucursal
                $sucursalesLider = obtenerSucursalesLider($usuario['CodOperario']);
                if (!empty($sucursalesLider)) {
                    $opciones = array_map(function ($s) {
                        return ['valor' => $s['codigo'], 'texto' => $s['nombre']];
                    }, $sucursalesLider);
                }
            } elseif ($esCDS) {
                // CDS solo ve sucursal 6
                $stmt = $conn->query("SELECT codigo as valor, nombre as texto FROM sucursales WHERE codigo = '6'");
                $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($esOperaciones) {
                // Operaciones solo ve sucursales físicas (sucursal = 1)
                $stmt = $conn->query("SELECT codigo as valor, nombre as texto FROM sucursales WHERE sucursal = 1 ORDER BY nombre");
                $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Otros usuarios ven todas las sucursales
                $stmt = $conn->query("SELECT codigo as valor, nombre as texto FROM sucursales ORDER BY nombre");
                $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'nombre_completo':
            // Obtener colaboradores según permisos
            if ($esLider) {
                // Líderes solo ven colaboradores de su sucursal
                $sucursalesLider = obtenerSucursalesLider($usuario['CodOperario']);
                if (!empty($sucursalesLider)) {
                    $sucursalLider = $sucursalesLider[0]['codigo'];
                    $stmt = $conn->prepare("
                        SELECT DISTINCT
                            o.CodOperario as valor,
                            CONCAT(TRIM(o.Nombre), ' ', TRIM(IFNULL(o.Apellido, '')), ' (', o.CodOperario, ')') as texto
                        FROM Operarios o
                        INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                        WHERE anc.Sucursal = ?
                        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                        AND anc.CodNivelesCargos != 27
                        AND o.Operativo = 1
                        ORDER BY o.Nombre, o.Apellido
                    ");
                    $stmt->execute([$sucursalLider]);
                    $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } elseif ($esCDS) {
                // CDS solo ve cargos específicos en sucursal 6
                $stmt = $conn->query("
                    SELECT DISTINCT
                        o.CodOperario as valor,
                        CONCAT(TRIM(o.Nombre), ' ', TRIM(IFNULL(o.Apellido, '')), ' (', o.CodOperario, ')') as texto
                    FROM Operarios o
                    INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                    WHERE anc.Sucursal = '6'
                    AND anc.CodNivelesCargos IN (23, 20, 34)
                    AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                    AND o.Operativo = 1
                    ORDER BY o.Nombre, o.Apellido
                ");
                $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Otros usuarios ven todos los colaboradores activos
                $stmt = $conn->query("
                    SELECT DISTINCT
                        o.CodOperario as valor,
                        CONCAT(TRIM(o.Nombre), ' ', TRIM(IFNULL(o.Apellido, '')), ' (', o.CodOperario, ')') as texto
                    FROM Operarios o
                    WHERE o.Operativo = 1
                    ORDER BY o.Nombre, o.Apellido
                ");
                $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;

        case 'nombre_cargo':
            // Obtener cargos que tienen colaboradores activos según permisos
            $whereCargos = "";
            $paramsCargos = [];

            if ($esLider) {
                $sucursalesLider = obtenerSucursalesLider($usuario['CodOperario']);
                if (!empty($sucursalesLider)) {
                    $whereCargos = " AND anc.Sucursal = ? ";
                    $paramsCargos[] = $sucursalesLider[0]['codigo'];
                }
            } elseif ($esCDS) {
                $whereCargos = " AND anc.Sucursal = '6' AND anc.CodNivelesCargos IN (23, 20, 34) ";
            } elseif ($esOperaciones) {
                $whereCargos = " AND s.sucursal = 1 ";
            }

            $stmt = $conn->prepare("
                SELECT DISTINCT
                    nc.CodNivelesCargos as valor,
                    nc.Nombre as texto
                FROM NivelesCargos nc
                INNER JOIN AsignacionNivelesCargos anc ON nc.CodNivelesCargos = anc.CodNivelesCargos
                INNER JOIN Operarios o ON anc.CodOperario = o.CodOperario
                INNER JOIN sucursales s ON anc.Sucursal = s.codigo
                WHERE o.Operativo = 1
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                AND nc.CodNivelesCargos != 27
                $whereCargos
                ORDER BY nc.Nombre
            ");
            $stmt->execute($paramsCargos);
            $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'estado_dia':
            // Obtener estados de día únicos
            $opciones = [
                ['valor' => 'Activo', 'texto' => 'Activo'],
                ['valor' => 'Libre', 'texto' => 'Libre'],
                ['valor' => 'Vacaciones', 'texto' => 'Vacaciones'],
                ['valor' => 'Feriado', 'texto' => 'Feriado'],
                ['valor' => 'Otra.Tienda', 'texto' => 'Otra Tienda'],
                ['valor' => 'Inactivo', 'texto' => 'Inactivo']
            ];
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Columna no válida']);
            exit();
    }

    echo json_encode([
        'success' => true,
        'opciones' => $opciones
    ]);

} catch (PDOException $e) {
    error_log("Error en marcaciones_get_opciones_filtro.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener opciones: ' . $e->getMessage()
    ]);
}
