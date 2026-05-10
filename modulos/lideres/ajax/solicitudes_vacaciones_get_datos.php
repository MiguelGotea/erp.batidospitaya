<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// ajax/solicitudes_vacaciones_get_datos.php
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    // Obtener cargos del usuario
    $cargosUsuario = obtenerCargosUsuario($_SESSION['usuario_id']);
    $esCargo11 = in_array(11, $cargosUsuario);
    $esCargo13 = in_array(13, $cargosUsuario);
    $esCargo28 = in_array(28, $cargosUsuario);
    
    // Construir WHERE base según permisos
    $where = ['1=1'];
    $params = [];
    
    // Aplicar filtros de jerarquía
    if ($esCargo11) {
        // Cargo 11: ver solicitudes de cargos 5 y 43
        $where[] = "(sv.solicitado_por IN (
            SELECT DISTINCT anc.CodOperario 
            FROM AsignacionNivelesCargos anc 
            WHERE anc.CodNivelesCargos IN (5, 43)
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        ) OR sv.solicitado_por = :usuario_actual OR sv.aprobado_operaciones_por = :usuario_actual2)";
        $params[':usuario_actual'] = $_SESSION['usuario_id'];
        $params[':usuario_actual2'] = $_SESSION['usuario_id'];
        
    } elseif ($esCargo13 || $esCargo28) {
        // RH: ver solicitudes aprobadas por operaciones o pendientes que no son de líderes
        $where[] = "((sv.estado = 'Aprobado_Operaciones' AND sv.solicitado_por IN (
            SELECT DISTINCT anc.CodOperario 
            FROM AsignacionNivelesCargos anc 
            WHERE anc.CodNivelesCargos IN (5, 43)
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        )) OR (sv.estado = 'Pendiente' AND sv.solicitado_por NOT IN (
            SELECT DISTINCT anc.CodOperario 
            FROM AsignacionNivelesCargos anc 
            WHERE anc.CodNivelesCargos IN (5, 43)
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        )) OR sv.solicitado_por = :usuario_actual OR sv.aprobado_rh_por = :usuario_actual2)";
        $params[':usuario_actual'] = $_SESSION['usuario_id'];
        $params[':usuario_actual2'] = $_SESSION['usuario_id'];
        
    } else {
        // Otros usuarios solo ven sus propias solicitudes
        $where[] = "sv.solicitado_por = :usuario_actual";
        $params[':usuario_actual'] = $_SESSION['usuario_id'];
    }
    
    // Filtros adicionales
    
    // Filtro de texto (colaborador)
    if (isset($filtros['colaborador']) && $filtros['colaborador'] !== '') {
        $where[] = "(o.Nombre LIKE :colaborador OR o.Apellido LIKE :colaborador OR o.Apellido2 LIKE :colaborador)";
        $params[":colaborador"] = '%' . $filtros['colaborador'] . '%';
    }
    
    // Filtro de lista (sucursal)
    if (isset($filtros['sucursal']) && is_array($filtros['sucursal']) && count($filtros['sucursal']) > 0) {
        $placeholders = [];
        foreach ($filtros['sucursal'] as $idx => $valor) {
            $key = ":sucursal_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "sv.cod_sucursal IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas (fecha_inicio)
    if (isset($filtros['fecha_inicio']) && is_array($filtros['fecha_inicio'])) {
        if (!empty($filtros['fecha_inicio']['desde'])) {
            $where[] = "sv.fecha_inicio >= :fecha_inicio_desde";
            $params[':fecha_inicio_desde'] = $filtros['fecha_inicio']['desde'];
        }
        if (!empty($filtros['fecha_inicio']['hasta'])) {
            $where[] = "sv.fecha_inicio <= :fecha_inicio_hasta";
            $params[':fecha_inicio_hasta'] = $filtros['fecha_inicio']['hasta'];
        }
    }
    
    // Filtro de rango de fechas (fecha_fin)
    if (isset($filtros['fecha_fin']) && is_array($filtros['fecha_fin'])) {
        if (!empty($filtros['fecha_fin']['desde'])) {
            $where[] = "sv.fecha_fin >= :fecha_fin_desde";
            $params[':fecha_fin_desde'] = $filtros['fecha_fin']['desde'];
        }
        if (!empty($filtros['fecha_fin']['hasta'])) {
            $where[] = "sv.fecha_fin <= :fecha_fin_hasta";
            $params[':fecha_fin_hasta'] = $filtros['fecha_fin']['hasta'];
        }
    }
    
    // Filtro de lista (estado)
    if (isset($filtros['estado']) && is_array($filtros['estado']) && count($filtros['estado']) > 0) {
        $placeholders = [];
        foreach ($filtros['estado'] as $idx => $valor) {
            $key = ":estado_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "sv.estado IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas (fecha_solicitud)
    if (isset($filtros['fecha_solicitud']) && is_array($filtros['fecha_solicitud'])) {
        if (!empty($filtros['fecha_solicitud']['desde'])) {
            $where[] = "sv.fecha_solicitud >= :fecha_solicitud_desde";
            $params[':fecha_solicitud_desde'] = $filtros['fecha_solicitud']['desde'];
        }
        if (!empty($filtros['fecha_solicitud']['hasta'])) {
            $where[] = "sv.fecha_solicitud <= :fecha_solicitud_hasta";
            $params[':fecha_solicitud_hasta'] = $filtros['fecha_solicitud']['hasta'];
        }
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    // Construir ORDER BY
    $orderClause = 'ORDER BY sv.fecha_solicitud DESC';
    if ($orden['columna']) {
        $columnas_validas = [
            'colaborador' => "CONCAT(o.Nombre, ' ', o.Apellido, ' ', o.Apellido2)",
            'sucursal' => 's.nombre',
            'fecha_inicio' => 'sv.fecha_inicio',
            'fecha_fin' => 'sv.fecha_fin',
            'estado' => 'sv.estado',
            'fecha_solicitud' => 'sv.fecha_solicitud'
        ];
        
        if (isset($columnas_validas[$orden['columna']])) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $orderClause = "ORDER BY {$columnas_validas[$orden['columna']]} $direccion";
        }
    }
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM solicitudes_vacaciones sv
                 JOIN Operarios o ON sv.cod_operario = o.CodOperario
                 JOIN sucursales s ON sv.cod_sucursal = s.codigo
                 LEFT JOIN Operarios sol ON sv.solicitado_por = sol.CodOperario
                 $whereClause";
    
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "SELECT 
                sv.id,
                sv.cod_operario,
                sv.fecha_inicio,
                sv.fecha_fin,
                sv.estado,
                sv.observaciones,
                sv.foto_soporte,
                sv.fecha_solicitud,
                sv.solicitado_por,
                sv.aprobado_operaciones_por,
                sv.aprobado_rh_por,
                o.Nombre AS operario_nombre,
                o.Apellido AS operario_apellido,
                o.Apellido2 AS operario_apellido2,
                s.nombre AS sucursal_nombre,
                sol.Nombre AS solicitante_nombre,
                sol.Apellido AS solicitante_apellido
            FROM solicitudes_vacaciones sv
            JOIN Operarios o ON sv.cod_operario = o.CodOperario
            JOIN sucursales s ON sv.cod_sucursal = s.codigo
            LEFT JOIN Operarios sol ON sv.solicitado_por = sol.CodOperario
            $whereClause
            $orderClause
            LIMIT :offset, :limit";
    
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    
    $stmt->execute();
    $datos = $stmt->fetchAll();
    
    // Agregar información de permisos para cada solicitud
    foreach ($datos as &$solicitud) {
        $solicitud['puede_aprobar'] = false;
        
        if ($solicitud['estado'] === 'Pendiente' && $esCargo11) {
            // Verificar si el solicitante es cargo 5 o 43
            $stmtCargo = $conn->prepare("
                SELECT COUNT(*) as es_lider 
                FROM AsignacionNivelesCargos 
                WHERE CodOperario = ? 
                AND CodNivelesCargos IN (5, 43)
                AND (Fin IS NULL OR Fin >= CURDATE())
            ");
            $stmtCargo->execute([$solicitud['solicitado_por']]);
            $esLider = $stmtCargo->fetch()['es_lider'] > 0;
            $solicitud['puede_aprobar'] = $esLider;
        } elseif ($solicitud['estado'] === 'Aprobado_Operaciones' && ($esCargo13 || $esCargo28)) {
            $solicitud['puede_aprobar'] = true;
        }
    }
    
    echo json_encode([
        'success' => true,
        'datos' => $datos,
        'total_registros' => $totalRegistros
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}