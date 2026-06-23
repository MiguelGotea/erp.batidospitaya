<?php
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

try {

    $pagina = isset($_POST['pagina']) ? (int) $_POST['pagina'] : 1;
    $registros_por_pagina = isset($_POST['registros_por_pagina']) ? (int) $_POST['registros_por_pagina'] : 25;
    $filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
    $orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => null, 'direccion' => 'asc'];

    $offset = ($pagina - 1) * $registros_por_pagina;

    // Construir WHERE
    $where = ["o.CodOperario IS NOT NULL"];

    // FILTRO GLOBAL SEGURO: Excluir colaboradores cuyo Cargo actual sea ID 27
    $subqueryCargoIdFiltroGlobal = "
        COALESCE(
            (SELECT anc_ext.CodNivelesCargos 
             FROM AsignacionNivelesCargos anc_ext
             WHERE anc_ext.CodOperario = o.CodOperario 
             AND anc_ext.CodNivelesCargos != 2
             AND (anc_ext.Fin IS NULL OR anc_ext.Fin >= CURDATE())
             ORDER BY anc_ext.CodAsignacionNivelesCargos DESC
             LIMIT 1),
            (SELECT anc_ext.CodNivelesCargos 
             FROM AsignacionNivelesCargos anc_ext
             WHERE anc_ext.CodOperario = o.CodOperario 
             AND (anc_ext.Fin IS NULL OR anc_ext.Fin >= CURDATE())
             ORDER BY anc_ext.CodAsignacionNivelesCargos DESC
             LIMIT 1),
            0
        )
    ";

    $where[] = "$subqueryCargoIdFiltroGlobal != 27";

    $params = [];

    // Filtro de código
    if (isset($filtros['CodOperario']) && $filtros['CodOperario'] !== '') {
        $where[] = "o.CodOperario LIKE :cod_operario";
        $params[":cod_operario"] = '%' . $filtros['CodOperario'] . '%';
    }

    // Filtro de nombre completo
    if (isset($filtros['nombre_completo']) && $filtros['nombre_completo'] !== '') {
        $where[] = "CONCAT(
            TRIM(o.Nombre), ' ',
            IFNULL(TRIM(o.Nombre2), ''), ' ',
            TRIM(o.Apellido), ' ',
            IFNULL(TRIM(o.Apellido2), '')
        ) LIKE :nombre_completo";
        $params[":nombre_completo"] = '%' . $filtros['nombre_completo'] . '%';
    }

    // Filtro de Cédula
    if (isset($filtros['Cedula']) && $filtros['Cedula'] !== '') {
        $where[] = "o.Cedula LIKE :cedula";
        $params[":cedula"] = '%' . $filtros['Cedula'] . '%';
    }

    // Filtro de seguro INSS
    if (isset($filtros['codigo_inss']) && $filtros['codigo_inss'] !== '') {
        $where[] = "o.codigo_inss LIKE :codigo_inss";
        $params[":codigo_inss"] = '%' . $filtros['codigo_inss'] . '%';
    }

    // Filtro de teléfonos
    if (isset($filtros['telefonos']) && $filtros['telefonos'] !== '') {
        $where[] = "(o.Celular LIKE :telefonos OR o.telefono_corporativo LIKE :telefonos2)";
        $params[":telefonos"] = '%' . $filtros['telefonos'] . '%';
        $params[":telefonos2"] = '%' . $filtros['telefonos'] . '%';
    }

    // Filtro de cargo (lista)
    if (isset($filtros['cargo_nombre']) && is_array($filtros['cargo_nombre']) && count($filtros['cargo_nombre']) > 0) {
        $placeholders = [];
        foreach ($filtros['cargo_nombre'] as $idx => $valor) {
            $key = ":cargo_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }

        // Subconsulta para obtener el cargo del operario
        $subqueryCargo = "
            COALESCE(
                (SELECT nc.Nombre 
                 FROM AsignacionNivelesCargos anc
                 JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                 WHERE anc.CodOperario = o.CodOperario 
                 AND anc.CodNivelesCargos != 2
                 AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                 ORDER BY anc.CodNivelesCargos DESC
                 LIMIT 1),
                (SELECT nc.Nombre 
                 FROM AsignacionNivelesCargos anc
                 JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                 WHERE anc.CodOperario = o.CodOperario 
                 AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                 ORDER BY anc.CodNivelesCargos DESC
                 LIMIT 1),
                'Sin cargo definido'
            )
        ";

        $where[] = "$subqueryCargo IN (" . implode(',', $placeholders) . ")";
    }

    // Filtro de estado (según la nueva lógica de contratos)
    if (isset($filtros['Operativo']) && is_array($filtros['Operativo']) && count($filtros['Operativo']) > 0) {
        $statusConds = [];
        foreach ($filtros['Operativo'] as $valor) {
            if ($valor == '1') {
                // Activo: Sin fecha de salida o fecha de salida futura
                $statusConds[] = "(uc.fecha_salida IS NULL OR uc.fecha_salida > CURDATE())";
            } else if ($valor == '0') {
                // Inactivo: Con fecha de salida hoy o en el pasado
                $statusConds[] = "(uc.fecha_salida IS NOT NULL AND uc.fecha_salida <= CURDATE())";
            }
        }
        if (!empty($statusConds)) {
            $where[] = "(" . implode(' OR ', $statusConds) . ")";
        }
    }

    // Filtro de sucursal contrato (lista)
    if (isset($filtros['nombre_sucursal']) && is_array($filtros['nombre_sucursal']) && count($filtros['nombre_sucursal']) > 0) {
        $placeholders = [];
        foreach ($filtros['nombre_sucursal'] as $idx => $valor) {
            $key = ":sucursal_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "COALESCE(s.nombre, 'Sin tienda') IN (" . implode(',', $placeholders) . ")";
    }

    // Filtro de sucursal actual (lista)
    if (isset($filtros['sucursal_actual_nombre']) && is_array($filtros['sucursal_actual_nombre']) && count($filtros['sucursal_actual_nombre']) > 0) {
        $placeholders = [];
        foreach ($filtros['sucursal_actual_nombre'] as $idx => $valor) {
            $key = ":suc_act_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }

        $subquerySucursalActual = "
            (SELECT s2.nombre 
             FROM AsignacionNivelesCargos anc2
             JOIN sucursales s2 ON anc2.Sucursal = s2.codigo
             WHERE anc2.CodOperario = o.CodOperario 
             AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
             ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC
             LIMIT 1)
        ";

        $where[] = "COALESCE($subquerySucursalActual, 'Sin tienda') IN (" . implode(',', $placeholders) . ")";
    }

    // Filtro de fecha inicio contrato (rango)
    if (isset($filtros['fecha_inicio_ultimo_contrato']) && is_array($filtros['fecha_inicio_ultimo_contrato'])) {
        if (!empty($filtros['fecha_inicio_ultimo_contrato']['desde'])) {
            $where[] = "uc.inicio_contrato >= :fecha_inicio_desde";
            $params[':fecha_inicio_desde'] = $filtros['fecha_inicio_ultimo_contrato']['desde'];
        }
        if (!empty($filtros['fecha_inicio_ultimo_contrato']['hasta'])) {
            $where[] = "uc.inicio_contrato <= :fecha_inicio_hasta";
            $params[':fecha_inicio_hasta'] = $filtros['fecha_inicio_ultimo_contrato']['hasta'];
        }
    }

    // Filtro de fecha fin contrato (rango) - ELIMINADO de UI pero mantenemos lógica por si acaso o lo cambiamos a fecha_salida
    if (isset($filtros['fecha_salida_ultimo']) && is_array($filtros['fecha_salida_ultimo'])) {
        if (!empty($filtros['fecha_salida_ultimo']['desde'])) {
            $where[] = "uc.fecha_salida >= :fecha_salida_desde";
            $params[':fecha_salida_desde'] = $filtros['fecha_salida_ultimo']['desde'];
        }
        if (!empty($filtros['fecha_salida_ultimo']['hasta'])) {
            $where[] = "uc.fecha_salida <= :fecha_salida_hasta";
            $params[':fecha_salida_hasta'] = $filtros['fecha_salida_ultimo']['hasta'];
        }
    }

    // Filtro de última fecha laborada (rango)
    if (isset($filtros['ultima_fecha_laborada']) && is_array($filtros['ultima_fecha_laborada'])) {
        $subqueryUltimaFechaMarcacion = "(SELECT MAX(fecha) FROM marcaciones WHERE CodOperario = o.CodOperario AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL) AND fecha <= CURDATE())";
        if (!empty($filtros['ultima_fecha_laborada']['desde'])) {
            $where[] = "$subqueryUltimaFechaMarcacion >= :ultima_fecha_desde";
            $params[':ultima_fecha_desde'] = $filtros['ultima_fecha_laborada']['desde'];
        }
        if (!empty($filtros['ultima_fecha_laborada']['hasta'])) {
            $where[] = "$subqueryUltimaFechaMarcacion <= :ultima_fecha_hasta";
            $params[':ultima_fecha_hasta'] = $filtros['ultima_fecha_laborada']['hasta'];
        }
    }

    // Filtro de tiempo trabajado (lista con rangos)
    if (isset($filtros['tiempo_trabajado_dias']) && is_array($filtros['tiempo_trabajado_dias']) && count($filtros['tiempo_trabajado_dias']) > 0) {
        $condiciones = [];
        $exprDias = "DATEDIFF(COALESCE(uc.fecha_salida, IF(uc.fin_contrato IS NOT NULL AND uc.fin_contrato < CURDATE(), uc.fin_contrato, CURDATE())), uc.inicio_contrato)";
        foreach ($filtros['tiempo_trabajado_dias'] as $rango) {
            switch ($rango) {
                case 'menos_6_meses':
                    $condiciones[] = "$exprDias < 180";
                    break;
                case '6_meses_1_año':
                    $condiciones[] = "($exprDias >= 180 AND $exprDias < 365)";
                    break;
                case '1_2_años':
                    $condiciones[] = "($exprDias >= 365 AND $exprDias < 730)";
                    break;
                case '2_5_años':
                    $condiciones[] = "($exprDias >= 730 AND $exprDias < 1825)";
                    break;
                case 'mas_5_años':
                    $condiciones[] = "$exprDias >= 1825";
                    break;
            }
        }
        if (!empty($condiciones)) {
            $where[] = "(" . implode(' OR ', $condiciones) . ")";
        }
    }

    // Filtro de tiempo restante (lista con categorías)
    if (isset($filtros['tiempo_restante_categoria']) && is_array($filtros['tiempo_restante_categoria']) && count($filtros['tiempo_restante_categoria']) > 0) {
        $condiciones = [];
        foreach ($filtros['tiempo_restante_categoria'] as $categoria) {
            switch ($categoria) {
                case 'vencido':
                    $condiciones[] = "(uc.fin_contrato IS NOT NULL AND uc.fin_contrato != '0000-00-00' AND uc.fin_contrato < CURDATE())";
                    break;
                case 'menos_1_mes':
                    $condiciones[] = "(uc.fin_contrato IS NOT NULL AND uc.fin_contrato != '0000-00-00' AND DATEDIFF(uc.fin_contrato, CURDATE()) BETWEEN 0 AND 30)";
                    break;
                case '1_3_meses':
                    $condiciones[] = "(uc.fin_contrato IS NOT NULL AND uc.fin_contrato != '0000-00-00' AND DATEDIFF(uc.fin_contrato, CURDATE()) BETWEEN 31 AND 90)";
                    break;
                case '3_6_meses':
                    $condiciones[] = "(uc.fin_contrato IS NOT NULL AND uc.fin_contrato != '0000-00-00' AND DATEDIFF(uc.fin_contrato, CURDATE()) BETWEEN 91 AND 180)";
                    break;
                case '6_12_meses':
                    $condiciones[] = "(uc.fin_contrato IS NOT NULL AND uc.fin_contrato != '0000-00-00' AND DATEDIFF(uc.fin_contrato, CURDATE()) BETWEEN 181 AND 365)";
                    break;
                case 'mas_1_año':
                    $condiciones[] = "(uc.fin_contrato IS NOT NULL AND uc.fin_contrato != '0000-00-00' AND DATEDIFF(uc.fin_contrato, CURDATE()) > 365)";
                    break;
                case 'indefinido':
                    $condiciones[] = "(uc.fin_contrato IS NULL OR uc.fin_contrato = '0000-00-00')";
                    break;
            }
        }
        if (!empty($condiciones)) {
            $where[] = "(" . implode(' OR ', $condiciones) . ")";
        }
    }

    // Filtro de cantidad de hijos (lista)
    if (isset($filtros['cantidad_hijos']) && is_array($filtros['cantidad_hijos']) && count($filtros['cantidad_hijos']) > 0) {
        $placeholders = [];
        foreach ($filtros['cantidad_hijos'] as $idx => $valor) {
            $key = ":cant_hijos_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "COALESCE(o.cantidad_hijos, 0) IN (" . implode(',', $placeholders) . ")";
    }

    // Filtro de talla camisa (lista)
    if (isset($filtros['talla_camisa']) && is_array($filtros['talla_camisa']) && count($filtros['talla_camisa']) > 0) {
        $placeholders = [];
        foreach ($filtros['talla_camisa'] as $idx => $valor) {
            $key = ":talla_cam_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "COALESCE(o.talla_camisa, 'Sin talla') IN (" . implode(',', $placeholders) . ")";
    }

    // Filtro de fecha vencimiento certificado salud (rango)
    if (isset($filtros['fecha_vencimiento_salud']) && is_array($filtros['fecha_vencimiento_salud'])) {
        if (!empty($filtros['fecha_vencimiento_salud']['desde'])) {
            $where[] = "(SELECT MAX(fecha_vencimiento) FROM ArchivosAdjuntos WHERE cod_operario = o.CodOperario AND id_tipo_documento = 2) >= :venc_salud_desde";
            $params[':venc_salud_desde'] = $filtros['fecha_vencimiento_salud']['desde'];
        }
        if (!empty($filtros['fecha_vencimiento_salud']['hasta'])) {
            $where[] = "(SELECT MAX(fecha_vencimiento) FROM ArchivosAdjuntos WHERE cod_operario = o.CodOperario AND id_tipo_documento = 2) <= :venc_salud_hasta";
            $params[':venc_salud_hasta'] = $filtros['fecha_vencimiento_salud']['hasta'];
        }
    }

    // Filtro de mes de contrato (lista)
    if (isset($filtros['mes_contrato']) && is_array($filtros['mes_contrato']) && count($filtros['mes_contrato']) > 0) {
        $placeholders = [];
        foreach ($filtros['mes_contrato'] as $idx => $valor) {
            $key = ":mes_contrato_$idx";
            $placeholders[] = $key;
            $params[$key] = $valor;
        }
        $where[] = "MONTH(uc.inicio_contrato) IN (" . implode(',', $placeholders) . ")";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Construir ORDER BY
    $orderClause = '';
    if ($orden['columna']) {
        $columnas_validas = [
            'CodOperario',
            'nombre_completo',
            'Cedula',
            'codigo_inss',
            'cargo_nombre',
            'telefonos',
            'Operativo',
            'nombre_sucursal',
            'fecha_inicio_ultimo_contrato',
            'fecha_salida_ultimo',
            'tiempo_trabajado_dias',
            'ultima_fecha_laborada',
            'cantidad_hijos',
            'talla_camisa',
            'mes_contrato',
            'fecha_vencimiento_salud'
        ];
        if (in_array($orden['columna'], $columnas_validas)) {
            $direccion = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';

            // Usar el alias correcto de la columna o la expresión calculada
            if ($orden['columna'] === 'Operativo') {
                // Ordenar por el estado calculado: 1 (Activo) o 0 (Inactivo)
                $orderClause = "ORDER BY (CASE WHEN uc.fecha_salida IS NULL OR uc.fecha_salida > CURDATE() THEN 1 ELSE 0 END) $direccion";
            } elseif ($orden['columna'] === 'nombre_completo') {
                $orderClause = "ORDER BY nombre_completo $direccion";
            } elseif ($orden['columna'] === 'cargo_nombre') {
                // Subconsulta para ordenar por cargo
                $subqueryCargo = "
                    COALESCE(
                        (SELECT nc.Nombre 
                         FROM AsignacionNivelesCargos anc
                         JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                         WHERE anc.CodOperario = o.CodOperario 
                         AND anc.CodNivelesCargos != 2
                         AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                         ORDER BY anc.CodNivelesCargos DESC
                         LIMIT 1),
                        (SELECT nc.Nombre 
                         FROM AsignacionNivelesCargos anc
                         JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                         WHERE anc.CodOperario = o.CodOperario 
                         AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                         ORDER BY anc.CodNivelesCargos DESC
                         LIMIT 1),
                        'Sin cargo definido'
                    )
                ";
                $orderClause = "ORDER BY $subqueryCargo $direccion";
            } elseif ($orden['columna'] === 'nombre_sucursal') {
                $orderClause = "ORDER BY s.nombre $direccion";
            } elseif ($orden['columna'] === 'sucursal_actual_nombre') {
                $subquerySucursalActual = "
                    (SELECT s2.nombre 
                     FROM AsignacionNivelesCargos anc2
                     JOIN sucursales s2 ON anc2.Sucursal = s2.codigo
                     WHERE anc2.CodOperario = o.CodOperario 
                     AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
                     ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC
                     LIMIT 1)
                ";
                $orderClause = "ORDER BY $subquerySucursalActual $direccion";
            } elseif ($orden['columna'] === 'fecha_inicio_ultimo_contrato') {
                $orderClause = "ORDER BY uc.inicio_contrato $direccion";
            } elseif ($orden['columna'] === 'fecha_salida_ultimo') {
                $orderClause = "ORDER BY (CASE WHEN uc.fecha_salida IS NULL OR uc.fecha_salida = '0000-00-00' THEN 1 ELSE 0 END) ASC, uc.fecha_salida $direccion";
            } elseif ($orden['columna'] === 'ultima_fecha_laborada') {
                $subqueryUltimaFechaMarcacion = "(SELECT MAX(fecha) FROM marcaciones WHERE CodOperario = o.CodOperario AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL) AND fecha <= CURDATE())";
                $orderClause = "ORDER BY $subqueryUltimaFechaMarcacion $direccion";
            } elseif ($orden['columna'] === 'tiempo_trabajado_dias') {
                $orderClause = "ORDER BY tiempo_trabajado_dias $direccion";
            } elseif ($orden['columna'] === 'Cedula') {
                $orderClause = "ORDER BY o.Cedula $direccion";
            } elseif ($orden['columna'] === 'codigo_inss') {
                $orderClause = "ORDER BY o.codigo_inss $direccion";
            } elseif ($orden['columna'] === 'mes_contrato') {
                $orderClause = "ORDER BY MONTH(uc.inicio_contrato) $direccion";
            } elseif ($orden['columna'] === 'fecha_vencimiento_salud') {
                $orderClause = "ORDER BY (SELECT MAX(fecha_vencimiento) FROM ArchivosAdjuntos WHERE cod_operario = o.CodOperario AND id_tipo_documento = 2) $direccion";
            } else {
                $orderClause = "ORDER BY {$orden['columna']} $direccion";
            }
        }
    } else {
        $orderClause = "ORDER BY o.Nombre, o.Apellido";
    }

    // Consulta de conteo - SIMPLIFICADA sin subconsultas correlated
    $sqlCount = "
        SELECT COUNT(DISTINCT o.CodOperario) as total 
        FROM Operarios o
        LEFT JOIN Contratos uc ON uc.cod_operario = o.CodOperario 
            AND uc.CodContrato = (
                SELECT CodContrato 
                FROM Contratos 
                WHERE cod_operario = o.CodOperario
                ORDER BY inicio_contrato DESC, CodContrato DESC
                LIMIT 1
            )
        LEFT JOIN sucursales s ON uc.cod_sucursal_contrato = s.codigo
        $whereClause
    ";

    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];

    // Consulta principal - SIMPLIFICADA
    $sql = "
        SELECT 
            o.CodOperario,
            CONCAT(
                TRIM(o.Nombre), ' ',
                IFNULL(TRIM(o.Nombre2), ''), ' ',
                TRIM(o.Apellido), ' ',
                IFNULL(TRIM(o.Apellido2), '')
            ) as nombre_completo,
            o.Cedula,
            o.codigo_inss,
            o.cantidad_hijos,
            o.talla_camisa,
            o.Celular,
            o.telefono_corporativo,
            -- Estado calculado basado en el último contrato
            IF(uc.fecha_salida IS NULL OR uc.fecha_salida > CURDATE(), 1, 0) as Operativo,
            COALESCE(
                (SELECT nc.Nombre 
                 FROM AsignacionNivelesCargos anc
                 JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                 WHERE anc.CodOperario = o.CodOperario 
                 AND anc.CodNivelesCargos != 2
                 AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                 ORDER BY anc.CodNivelesCargos DESC
                 LIMIT 1),
                (SELECT nc.Nombre 
                 FROM AsignacionNivelesCargos anc
                 JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                 WHERE anc.CodOperario = o.CodOperario 
                 AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                 ORDER BY anc.CodNivelesCargos DESC
                 LIMIT 1),
                'Sin cargo definido'
            ) as cargo_nombre,
            COALESCE(s.nombre, 'Sin tienda') as nombre_sucursal,
            COALESCE(
                (SELECT s2.nombre 
                 FROM AsignacionNivelesCargos anc2
                 JOIN sucursales s2 ON anc2.Sucursal = s2.codigo
                 WHERE anc2.CodOperario = o.CodOperario 
                 AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
                 ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC
                 LIMIT 1),
                (SELECT CONCAT(s2.nombre, ' (última tienda)') 
                 FROM AsignacionNivelesCargos anc2
                 JOIN sucursales s2 ON anc2.Sucursal = s2.codigo
                 WHERE anc2.CodOperario = o.CodOperario 
                 ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC
                 LIMIT 1),
                'Sin tienda'
            ) as sucursal_actual_nombre,
            uc.CodContrato,
            uc.codigo_manual_contrato,
            uc.inicio_contrato as fecha_inicio_ultimo_contrato,
            uc.fin_contrato as fecha_fin_ultimo_contrato,
            uc.fecha_salida as fecha_salida_ultimo,
            (SELECT MAX(fecha) FROM marcaciones WHERE CodOperario = o.CodOperario AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL) AND fecha <= CURDATE()) as ultima_fecha_laborada,
            DATEDIFF(
                COALESCE(
                    uc.fecha_salida,
                    IF(uc.fin_contrato IS NOT NULL AND uc.fin_contrato < CURDATE(), 
                       uc.fin_contrato, 
                       CURDATE())
                ),
                uc.inicio_contrato
            ) as tiempo_trabajado_dias,
            MONTH(uc.inicio_contrato) as mes_contrato,
            (SELECT MAX(fecha_vencimiento) FROM ArchivosAdjuntos WHERE cod_operario = o.CodOperario AND id_tipo_documento = 2) as fecha_vencimiento_salud
        FROM Operarios o
        LEFT JOIN Contratos uc ON uc.cod_operario = o.CodOperario 
            AND uc.CodContrato = (
                SELECT MAX(CodContrato) 
                FROM Contratos 
                WHERE cod_operario = o.CodOperario
            )
        LEFT JOIN sucursales s ON uc.cod_sucursal_contrato = s.codigo
        $whereClause
        $orderClause
        LIMIT :offset, :limit
    ";

    $stmt = $conn->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);

    $stmt->execute();
    $datos = $stmt->fetchAll();

    require_once '../editar_colaborador_componentes/logic/compliance_logic.php';

    // Procesar datos para agregar campos calculados
    foreach ($datos as &$row) {
        // Calcular porcentaje de llenado global (DP, DC, Contrato, INSS)
        $llenado = calcularPorcentajeLlenadoGlobal($row['CodOperario']);
        $row['porcentaje_llenado'] = $llenado['porcentaje'];

        // Calcular cumplimiento de Expediente Digital
        $expediente = calcularPorcentajeCumplimiento($row['CodOperario'], 'expediente-digital');
        $row['expediente_digital'] = $expediente;

        // Calcular tiempo trabajado texto
        $row['tiempo_trabajado_texto'] = calcularTiempoTrabajadoTexto(
            $row['fecha_inicio_ultimo_contrato'],
            $row['fecha_fin_ultimo_contrato'],
            $row['fecha_salida_ultimo'],
            $row['Operativo'] == 1
        );

        // Calcular tiempo restante HTML
        $row['tiempo_restante_html'] = calcularTiempoRestanteHTML(
            $row['fecha_fin_ultimo_contrato'],
            $row['Operativo'] == 1,
            $row['fecha_salida_ultimo']
        );

        // Determinar categoría de tiempo restante
        $row['tiempo_restante_categoria'] = determinarCategoriaTiempoRestante(
            $row['fecha_fin_ultimo_contrato'],
            $row['Operativo'] == 1,
            $row['fecha_salida_ultimo']
        );

        // Mostrar fecha de salida si está inactivo
        if ($row['Operativo'] == 0 && !empty($row['fecha_salida_ultimo'])) {
            $row['fecha_fin_display'] = formatearFechaCorta($row['fecha_salida_ultimo']);
        } else {
            $row['fecha_fin_display'] = null;
        }

        // Limpiar fechas con valor cero o nulas para el certificado de salud
        if (!empty($row['fecha_vencimiento_salud']) && strpos($row['fecha_vencimiento_salud'], '0000-00-00') !== false) {
            $row['fecha_vencimiento_salud'] = null;
        }
    }

    // Filtrar por porcentaje_llenado (post-proceso, porque se calcula en PHP)
    if (isset($filtros['porcentaje_llenado']) && is_array($filtros['porcentaje_llenado'])) {
        $porcDesde = isset($filtros['porcentaje_llenado']['desde']) && $filtros['porcentaje_llenado']['desde'] !== null
            ? (float) $filtros['porcentaje_llenado']['desde'] : null;
        $porcHasta = isset($filtros['porcentaje_llenado']['hasta']) && $filtros['porcentaje_llenado']['hasta'] !== null
            ? (float) $filtros['porcentaje_llenado']['hasta'] : null;

        $datos = array_values(array_filter($datos, function ($row) use ($porcDesde, $porcHasta) {
            $p = (float) ($row['porcentaje_llenado'] ?? 0);
            if ($porcDesde !== null && $p < $porcDesde) return false;
            if ($porcHasta !== null && $p > $porcHasta) return false;
            return true;
        }));

        // Ajustar total_registros al subconjunto filtrado
        $totalRegistros = count($datos);

        // Aplicar paginacion manual al subconjunto
        $datos = array_slice($datos, $offset, $registros_por_pagina);
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

// Funciones auxiliares
function calcularTiempoTrabajadoTexto($fechaInicio, $fechaFin, $fechaSalida, $estaActivo)
{
    if (empty($fechaInicio) || $fechaInicio == '0000-00-00') {
        return 'Sin contrato';
    }

    try {
        $inicio = new DateTime($fechaInicio);
        $hoy = new DateTime();

        $fin = $hoy;

        if (!empty($fechaSalida) && $fechaSalida != '0000-00-00') {
            $salidaObj = new DateTime($fechaSalida);
            if ($salidaObj <= $hoy) {
                $fin = $salidaObj;
            }
        } elseif (!$estaActivo && !empty($fechaFin) && $fechaFin != '0000-00-00') {
            $finObj = new DateTime($fechaFin);
            if ($finObj <= $hoy) {
                $fin = $finObj;
            }
        } elseif ($estaActivo && !empty($fechaFin) && $fechaFin != '0000-00-00') {
            $finObj = new DateTime($fechaFin);
            if ($finObj < $hoy) {
                $fin = $finObj;
            }
        }

        if ($fin < $inicio) {
            $fin = clone $inicio;
        }

        if ($fin > $hoy) {
            $fin = $hoy;
        }

        $diferencia = $inicio->diff($fin);

        $años = $diferencia->y;
        $meses = $diferencia->m;
        $dias = $diferencia->d;

        $resultado = [];
        if ($años > 0) {
            $resultado[] = $años . ' año' . ($años > 1 ? 's' : '');
        }
        if ($meses > 0) {
            $resultado[] = $meses . ' mes' . ($meses > 1 ? 'es' : '');
        }
        if ($dias > 0 && empty($resultado)) {
            $resultado[] = $dias . ' día' . ($dias > 1 ? 's' : '');
        }

        return empty($resultado) ? 'Menos de 1 día' : implode(', ', $resultado);

    } catch (Exception $e) {
        return 'Error';
    }
}

function calcularTiempoRestanteHTML($fechaFin, $estaActivo, $fechaSalida)
{
    if (!empty($fechaSalida) && $fechaSalida != '0000-00-00') {
        $fechaSalidaObj = new DateTime($fechaSalida);
        $hoy = new DateTime();
        if ($fechaSalidaObj <= $hoy) {
            return '<span class="status-inactivo">Finalizado</span>';
        }
    }

    if (!$estaActivo) {
        return '<span class="status-inactivo">Inactivo</span>';
    }

    if (empty($fechaFin) || $fechaFin == '0000-00-00') {
        return '<span class="status-success">Indefinido</span>';
    }

    try {
        $fin = new DateTime($fechaFin);
        $actual = new DateTime();

        if ($fin < $actual) {
            return '<span class="status-inactivo">Vencido</span>';
        }

        $diferencia = $actual->diff($fin);
        $diasTotales = $diferencia->days;

        if ($diasTotales <= 7) {
            if ($diasTotales == 0) {
                return '<span class="status-inactivo">Vence hoy</span>';
            } else {
                return '<span class="status-inactivo">' . $diasTotales . ' día' . ($diasTotales > 1 ? 's' : '') . '</span>';
            }
        } elseif ($diasTotales <= 30) {
            return '<span class="status-alerta">' . $diasTotales . ' días</span>';
        } elseif ($diferencia->y == 0) {
            if ($diferencia->m == 0) {
                return '<span class="status-info">' . $diferencia->d . ' días</span>';
            } else {
                return '<span class="status-info">' . $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '') . '</span>';
            }
        } else {
            if ($diferencia->m == 0) {
                return '<span class="status-success">' . $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '') . '</span>';
            } else {
                return '<span class="status-success">' . $diferencia->y . ' año' . ($diferencia->y > 1 ? 's' : '') . ', ' . $diferencia->m . ' mes' . ($diferencia->m > 1 ? 'es' : '') . '</span>';
            }
        }
    } catch (Exception $e) {
        return '<span class="status-inactivo">Error</span>';
    }
}

function determinarCategoriaTiempoRestante($fechaFin, $estaActivo, $fechaSalida)
{
    if (!$estaActivo || (!empty($fechaSalida) && $fechaSalida != '0000-00-00')) {
        return 'vencido';
    }

    if (empty($fechaFin) || $fechaFin == '0000-00-00') {
        return 'indefinido';
    }

    try {
        $fin = new DateTime($fechaFin);
        $actual = new DateTime();

        if ($fin < $actual) {
            return 'vencido';
        }

        $diasTotales = $actual->diff($fin)->days;

        if ($diasTotales <= 30) {
            return 'menos_1_mes';
        } elseif ($diasTotales <= 90) {
            return '1_3_meses';
        } elseif ($diasTotales <= 180) {
            return '3_6_meses';
        } elseif ($diasTotales <= 365) {
            return '6_12_meses';
        } else {
            return 'mas_1_año';
        }
    } catch (Exception $e) {
        return 'vencido';
    }
}

function formatearFechaCorta($fecha)
{
    if (empty($fecha) || $fecha === '0000-00-00') {
        return '';
    }

    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    try {
        $timestamp = strtotime($fecha);
        if ($timestamp === false) {
            return '';
        }

        $fechaObj = new DateTime($fecha);
        $mes = $meses[(int) $fechaObj->format('m') - 1];
        return $fechaObj->format('d') . '-' . $mes . '-' . $fechaObj->format('y');
    } catch (Exception $e) {
        return '';
    }
}
?>