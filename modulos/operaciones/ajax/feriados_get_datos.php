<?php
require_once '../../../core/database/conexion.php';
require_once '../../../core/helpers/funciones.php';
header('Content-Type: application/json');

try {
    $pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];
    
    $offset = ($pagina - 1) * $registros_por_pagina;
    
    // Obtener fechas base del filtro principal
    $fechaDesdeBase = isset($filtros['fecha_base']['desde']) ? $filtros['fecha_base']['desde'] : date('Y-m-d', strtotime('-1 month'));
    $fechaHastaBase = isset($filtros['fecha_base']['hasta']) ? $filtros['fecha_base']['hasta'] : date('Y-m-d');
    
    // Construir WHERE
    $where = [];
    $params = [
        ':desde' => $fechaDesdeBase,
        ':hasta' => $fechaHastaBase
    ];
    
    // Filtro de sucursal (ID del selector superior)
    if (isset($filtros['sucursal_id']) && $filtros['sucursal_id'] !== '' && $filtros['sucursal_id'] !== '0') {
        $where[] = "s.codigo = :sucursal_id";
        $params[":sucursal_id"] = $filtros['sucursal_id'];
    }

    // Filtro de operario (ID del selector superior)
    if (isset($filtros['operario_id']) && $filtros['operario_id'] !== '' && $filtros['operario_id'] !== '0') {
        $where[] = "o.CodOperario = :operario_id";
        $params[":operario_id"] = $filtros['operario_id'];
    }

    // Filtro de texto (nombre_operario)
    if (isset($filtros['nombre_operario']) && $filtros['nombre_operario'] !== '') {
        $where[] = "CONCAT(IFNULL(o.Nombre, ''), ' ', IFNULL(o.Nombre2, ''), ' ', IFNULL(o.Apellido, ''), ' ', IFNULL(o.Apellido2, '')) LIKE :nombre_operario";
        $params[":nombre_operario"] = '%' . $filtros['nombre_operario'] . '%';
    }
    
    // Filtro de texto (sucursal_nombre)
    if (isset($filtros['sucursal_nombre']) && $filtros['sucursal_nombre'] !== '') {
        $where[] = "s.nombre LIKE :sucursal_nombre";
        $params[":sucursal_nombre"] = '%' . $filtros['sucursal_nombre'] . '%';
    }

    // Filtro de texto (feriado_nombre)
    if (isset($filtros['feriado_nombre']) && $filtros['feriado_nombre'] !== '') {
        $where[] = "f.nombre LIKE :feriado_nombre";
        $params[":feriado_nombre"] = '%' . $filtros['feriado_nombre'] . '%';
    }
    
    // Filtro de lista (sucursal_nombre)
    if (isset($filtros['sucursal_nombre_list']) && is_array($filtros['sucursal_nombre_list']) && count($filtros['sucursal_nombre_list']) > 0) {
        $placeholders = [];
        foreach ($filtros['sucursal_nombre_list'] as $idx => $valor) {
            $key = ":suc_list_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "s.nombre IN (" . implode(',', $placeholders) . ")";
    }

    // Filtro de lista (feriado_tipo)
    if (isset($filtros['feriado_tipo']) && is_array($filtros['feriado_tipo']) && count($filtros['feriado_tipo']) > 0) {
        $placeholders = [];
        foreach ($filtros['feriado_tipo'] as $idx => $valor) {
            $key = ":tipo_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "f.tipo IN (" . implode(',', $placeholders) . ")";
    }

    // Filtro de lista (estado)
    if (isset($filtros['estado']) && is_array($filtros['estado']) && count($filtros['estado']) > 0) {
        $placeholders = [];
        foreach ($filtros['estado'] as $idx => $valor) {
            $key = ":estado_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        // El estado puede venir de FeriadosStatus o ser determinado por marcación
        // Aquí simplificamos: si el filtro es "Pendiente", "Con Marcación" o "Sin marcación", 
        // tenemos que manejar la lógica de COALESCE
        $where[] = "COALESCE(fs.estado, CASE WHEN m.id IS NOT NULL THEN 'Con Marcación' ELSE 'Sin marcación' END) IN (" . implode(',', $placeholders) . ")";
    }
    
    // Filtro de rango de fechas (fecha_feriado)
    if (isset($filtros['fecha']) && is_array($filtros['fecha'])) {
        if (!empty($filtros['fecha']['desde'])) {
            $where[] = "f.fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filtros['fecha']['desde'];
        }
        if (!empty($filtros['fecha']['hasta'])) {
            $where[] = "f.fecha <= :fecha_hasta";
            $params[':fecha_hasta'] = $filtros['fecha']['hasta'];
        }
    }

    // Filtro de rango de fechas (inicio_contrato)
    if (isset($filtros['inicio_contrato']) && is_array($filtros['inicio_contrato'])) {
        if (!empty($filtros['inicio_contrato']['desde'])) {
            $where[] = "c.inicio_contrato >= :inicio_contrato_desde";
            $params[':inicio_contrato_desde'] = $filtros['inicio_contrato']['desde'];
        }
        if (!empty($filtros['inicio_contrato']['hasta'])) {
            $where[] = "c.inicio_contrato <= :inicio_contrato_hasta";
            $params[':inicio_contrato_hasta'] = $filtros['inicio_contrato']['hasta'];
        }
    }
    
    $whereClause = count($where) > 0 ? ' AND ' . implode(' AND ', $where) : '';
    
    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = [
            'nombre_operario' => "nombre_operario",
            'sucursal_nombre' => "s.nombre",
            'inicio_contrato' => "c.inicio_contrato",
            'fecha' => "f.fecha",
            'feriado_nombre' => "f.nombre",
            'feriado_tipo' => "f.tipo",
            'estado' => "estado"
        ];
        
        if (isset($columnas_validas[$orden['columna']])) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
            $columna_real = $columnas_validas[$orden['columna']];
            $orderClause = "ORDER BY {$columna_real} $direccion";
        }
    } else {
        $orderClause = "ORDER BY f.fecha DESC, nombre_operario ASC";
    }

    // Consulta base con todas las columnas necesarias
    $baseQuery = "
        SELECT 
            o.CodOperario as cod_operario,
            CONCAT(IFNULL(o.Nombre, ''), ' ', IFNULL(o.Nombre2, ''), ' ', IFNULL(o.Apellido, ''), ' ', IFNULL(o.Apellido2, '')) as nombre_operario,
            s.codigo as sucursal_codigo,
            s.nombre as sucursal_nombre,
            d.nombre as sucursal_departamento,
            d.codigo as sucursal_cod_departamento,
            f.fecha,
            f.nombre as feriado_nombre,
            f.tipo as feriado_tipo,
            COALESCE(df.nombre, 'Nacional') as departamento_nombre,
            m.id as id_marcacion,
            m.hora_ingreso,
            m.hora_salida,
            COALESCE(fs.estado, CASE WHEN m.id IS NOT NULL THEN 'Con Marcación' ELSE 'Sin marcación' END) as estado,
            fs.observaciones,
            fs.id as id_aprobacion,
            fs.cod_contrato,
            fs.fecha_creacion,
            c.inicio_contrato
        FROM Operarios o
        INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        INNER JOIN sucursales s ON anc.Sucursal = s.codigo
        INNER JOIN departamentos d ON s.cod_departamento = d.codigo
        CROSS JOIN feriadosnic f
        LEFT JOIN departamentos df ON f.departamento_codigo = df.codigo
        LEFT JOIN marcaciones m ON o.CodOperario = m.CodOperario AND f.fecha = m.fecha AND s.codigo = m.sucursal_codigo
        LEFT JOIN FeriadosStatus fs ON (o.CodOperario = fs.cod_operario AND f.fecha = fs.fecha_feriado)
        LEFT JOIN (
            SELECT cod_operario, MAX(inicio_contrato) as inicio_contrato
            FROM Contratos
            GROUP BY cod_operario
        ) c ON o.CodOperario = c.cod_operario
        WHERE o.Operativo = 1
        AND f.fecha BETWEEN :desde AND :hasta
        AND (f.tipo = 'Nacional' OR (f.tipo = 'Departamental' AND f.departamento_codigo = s.cod_departamento))
        AND (anc.Fin IS NULL OR anc.Fin >= f.fecha)
        AND (anc.Fecha <= f.fecha)
        AND o.CodOperario NOT IN (
            SELECT DISTINCT anc2.CodOperario 
            FROM AsignacionNivelesCargos anc2
            WHERE anc2.CodNivelesCargos = 27
            AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
        )
        $whereClause
    ";
    
    // Consulta de conteo
    $sqlCount = "SELECT COUNT(*) as total FROM ($baseQuery) as subquery";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Consulta de datos con paginación
    $sql = "$baseQuery $orderClause LIMIT :offset, :limit";
    
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
    
    $stmt->execute();
    $datos = $stmt->fetchAll();

    // Calcular horas trabajadas en el server para mayor precisión
    foreach ($datos as &$row) {
        $row['horas_trabajadas'] = 0;
        if ($row['hora_ingreso'] && $row['hora_salida']) {
            $entrada = new DateTime($row['hora_ingreso']);
            $salida = new DateTime($row['hora_salida']);
            $diferencia = $salida->diff($entrada);
            $row['horas_trabajadas'] = $diferencia->h + ($diferencia->i / 60);
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
