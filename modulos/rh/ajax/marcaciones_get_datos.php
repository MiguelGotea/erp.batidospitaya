<?php
/**
 * AJAX Endpoint: Obtener datos de marcaciones con filtros y paginación
 * Ubicación: /modulos/rh/ajax/marcaciones_get_datos.php
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
$esOperaciones = tienePermiso('historial_marcaciones_globales', 'permisosoperaciones', $usuario['CodNivelesCargos']);
$esCDS = tienePermiso('historial_marcaciones_globales', 'permisoscds', $usuario['CodNivelesCargos']);
$esContabilidad = tienePermiso('historial_marcaciones_globales', 'permisoscontabilidad', $usuario['CodNivelesCargos']);

// Obtener parámetros
$pagina = isset($_POST['pagina']) ? intval($_POST['pagina']) : 1;
$registros_por_pagina = isset($_POST['registros_por_pagina']) ? intval($_POST['registros_por_pagina']) : 25;
$filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
$orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'desc'];

// Fechas por defecto (mes actual)
$fechaHoy = date('Y-m-d');
$fechaDesde = isset($filtros['fecha']['desde']) ? $filtros['fecha']['desde'] : date('Y-m-01');
$fechaHasta = isset($filtros['fecha']['hasta']) ? $filtros['fecha']['hasta'] : $fechaHoy;

// Asegurar que la fecha hasta no sea mayor a hoy
if ($fechaHasta > $fechaHoy) {
    $fechaHasta = $fechaHoy;
}

// Construir consulta base
$sql = "
SELECT 
    m.*,
    ss.numero_semana,
    s.nombre as nombre_sucursal,
    CONCAT(TRIM(o.Nombre), ' ', TRIM(IFNULL(o.Apellido, ''))) as nombre_completo,
    o.CodOperario,
    nc.Cargo as nombre_cargo,
    hso.lunes_entrada, hso.lunes_salida,
    hso.martes_entrada, hso.martes_salida,
    hso.miercoles_entrada, hso.miercoles_salida,
    hso.jueves_entrada, hso.jueves_salida,
    hso.viernes_entrada, hso.viernes_salida,
    hso.sabado_entrada, hso.sabado_salida,
    hso.domingo_entrada, hso.domingo_salida,
    CASE DAYOFWEEK(m.fecha)
        WHEN 1 THEN hso.domingo_estado
        WHEN 2 THEN hso.lunes_estado
        WHEN 3 THEN hso.martes_estado
        WHEN 4 THEN hso.miercoles_estado
        WHEN 5 THEN hso.jueves_estado
        WHEN 6 THEN hso.viernes_estado
        WHEN 7 THEN hso.sabado_estado
    END as estado_dia,
    CASE DAYOFWEEK(m.fecha)
        WHEN 1 THEN hso.domingo_entrada
        WHEN 2 THEN hso.lunes_entrada
        WHEN 3 THEN hso.martes_entrada
        WHEN 4 THEN hso.miercoles_entrada
        WHEN 5 THEN hso.jueves_entrada
        WHEN 6 THEN hso.viernes_entrada
        WHEN 7 THEN hso.sabado_entrada
    END as hora_entrada_programada,
    CASE DAYOFWEEK(m.fecha)
        WHEN 1 THEN hso.domingo_salida
        WHEN 2 THEN hso.lunes_salida
        WHEN 3 THEN hso.martes_salida
        WHEN 4 THEN hso.miercoles_salida
        WHEN 5 THEN hso.jueves_salida
        WHEN 6 THEN hso.viernes_salida
        WHEN 7 THEN hso.sabado_salida
    END as hora_salida_programada
FROM marcaciones m
LEFT JOIN SemanasSistema ss ON m.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
LEFT JOIN sucursales s ON m.sucursal_codigo = s.codigo
LEFT JOIN Operarios o ON m.CodOperario = o.CodOperario
LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
    AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
LEFT JOIN HorariosSemanalesOperaciones hso ON m.CodOperario = hso.cod_operario 
    AND hso.id_semana_sistema = ss.id
    AND hso.cod_sucursal = m.sucursal_codigo
WHERE m.fecha BETWEEN ? AND ?
";

$params = [$fechaDesde, $fechaHasta];

// Aplicar restricciones de permisos
if ($esLider) {
    // Líderes solo ven su sucursal
    $sucursalesLider = obtenerSucursalesLider($usuario['CodOperario']);
    if (!empty($sucursalesLider)) {
        $sucursalLider = $sucursalesLider[0]['codigo'];
        $sql .= " AND EXISTS (
            SELECT 1 FROM AsignacionNivelesCargos anc_asig
            WHERE anc_asig.CodOperario = m.CodOperario
            AND anc_asig.Sucursal = ?
            AND (anc_asig.Fin IS NULL OR anc_asig.Fin >= CURDATE())
            AND anc_asig.CodNivelesCargos != 27
        )";
        $params[] = $sucursalLider;
    }
} elseif ($esCDS) {
    // CDS solo ve sucursal 6 y cargos específicos
    $sql .= " AND m.sucursal_codigo = '6'";
    $sql .= " AND EXISTS (
        SELECT 1 FROM AsignacionNivelesCargos anc_cds
        WHERE anc_cds.CodOperario = m.CodOperario
        AND anc_cds.CodNivelesCargos IN (23, 20, 34)
        AND (anc_cds.Fin IS NULL OR anc_cds.Fin >= CURDATE())
    )";
}

// Aplicar filtros
if (!empty($filtros)) {
    // Filtro de semana (number)
    if (isset($filtros['numero_semana'])) {
        if (isset($filtros['numero_semana']['min']) && $filtros['numero_semana']['min'] !== '') {
            $sql .= " AND ss.numero_semana >= ?";
            $params[] = intval($filtros['numero_semana']['min']);
        }
        if (isset($filtros['numero_semana']['max']) && $filtros['numero_semana']['max'] !== '') {
            $sql .= " AND ss.numero_semana <= ?";
            $params[] = intval($filtros['numero_semana']['max']);
        }
    }

    // Filtro de sucursal (list)
    if (isset($filtros['nombre_sucursal']) && is_array($filtros['nombre_sucursal']) && !empty($filtros['nombre_sucursal'])) {
        $placeholders = implode(',', array_fill(0, count($filtros['nombre_sucursal']), '?'));
        $sql .= " AND s.codigo IN ($placeholders)";
        $params = array_merge($params, $filtros['nombre_sucursal']);
    }

    // Filtro de colaborador (list)
    if (isset($filtros['nombre_completo']) && is_array($filtros['nombre_completo']) && !empty($filtros['nombre_completo'])) {
        $placeholders = implode(',', array_fill(0, count($filtros['nombre_completo']), '?'));
        $sql .= " AND o.CodOperario IN ($placeholders)";
        $params = array_merge($params, $filtros['nombre_completo']);
    }

    // Filtro de cargo (list)
    if (isset($filtros['nombre_cargo']) && is_array($filtros['nombre_cargo']) && !empty($filtros['nombre_cargo'])) {
        $placeholders = implode(',', array_fill(0, count($filtros['nombre_cargo']), '?'));
        $sql .= " AND nc.CodNivelesCargos IN ($placeholders)";
        $params = array_merge($params, $filtros['nombre_cargo']);
    }
}

// Aplicar ordenamiento
$columnas_permitidas = ['numero_semana', 'nombre_sucursal', 'nombre_completo', 'nombre_cargo', 'fecha'];
if ($orden['columna'] && in_array($orden['columna'], $columnas_permitidas)) {
    $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';

    // Mapear columnas a sus equivalentes SQL
    $columna_sql = $orden['columna'];
    if ($orden['columna'] === 'nombre_completo') {
        $columna_sql = 'o.Nombre';
    } elseif ($orden['columna'] === 'nombre_sucursal') {
        $columna_sql = 's.nombre';
    } elseif ($orden['columna'] === 'nombre_cargo') {
        $columna_sql = 'nc.Cargo';
    }

    $sql .= " ORDER BY $columna_sql $direccion";
} else {
    $sql .= " ORDER BY m.fecha DESC, ss.numero_semana DESC";
}

// Contar total de registros (sin paginación)
try {
    $stmt_count = $conn->prepare($sql);
    $stmt_count->execute($params);
    $total_registros = $stmt_count->rowCount();

    // Aplicar paginación
    $offset = ($pagina - 1) * $registros_por_pagina;
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $registros_por_pagina;
    $params[] = $offset;

    // Ejecutar consulta con paginación
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'datos' => $datos,
        'total_registros' => $total_registros
    ]);

} catch (PDOException $e) {
    error_log("Error en marcaciones_get_datos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}
