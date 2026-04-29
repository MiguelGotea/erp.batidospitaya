<?php
// postulacion_plazas_activas_get_datos.php

require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $registros_por_pagina = (int) ($input['registros_por_pagina'] ?? 10);

    // Obtener plazas visibles en web con postulaciones
    // Solo mostrar si: (cantidad_real - cantidad_cubierta + cantidad_adicional) > 0
    $sql = "SELECT 
                pc.id as id_plaza,
                nc.Nombre as nombre_cargo,
                nc.Area as area,
                s.nombre as sucursal_nombre,
                d.nombre as departamento_nombre,
                pc.cantidad_real,
                pc.cantidad_adicional,
                pc.salario_propuesto,
                pc.nivel_urgencia,
                pc.cargo as id_cargo,
                pc.sucursal as id_sucursal,
                (SELECT COUNT(DISTINCT anc.CodOperario) 
                 FROM AsignacionNivelesCargos anc
                 INNER JOIN Contratos c ON anc.CodOperario = c.cod_operario
                 WHERE (
                     (pc.cargo IN (2, 44, 45, 46, 47) AND anc.CodNivelesCargos IN (2, 44, 45, 46, 47)) OR 
                     (pc.cargo IN (5, 43) AND anc.CodNivelesCargos IN (5, 43)) OR
                     (pc.cargo NOT IN (2, 44, 45, 46, 47, 5, 43) AND anc.CodNivelesCargos = pc.cargo)
                 )
                 AND (anc.Sucursal = pc.sucursal OR pc.sucursal IS NULL OR pc.sucursal = 0)
                 AND anc.Fecha <= CURDATE()
                 AND (anc.Fin IS NULL OR anc.Fin = '' OR anc.Fin >= CURDATE())
                 AND c.Finalizado = 0
                ) as cantidad_cubierta,
                (SELECT COUNT(*) 
                 FROM postulacion_plaza pp 
                 WHERE (
                     (pc.cargo IN (2, 44, 45, 46, 47) AND pp.cargo_aplicado IN (2, 44, 45, 46, 47)) OR 
                     (pc.cargo IN (5, 43) AND pp.cargo_aplicado IN (5, 43)) OR
                     (pc.cargo NOT IN (2, 44, 45, 46, 47, 5, 43) AND pp.cargo_aplicado = pc.cargo)
                 )
                 AND (pp.sucursal_aplicada = pc.sucursal OR (pp.sucursal_aplicada IS NULL AND pc.sucursal IS NULL))
                 AND pp.status = 'solicitado'
                ) as cvs_recibidos,
                (GREATEST(0, (pc.cantidad_real - 
                    (SELECT COUNT(DISTINCT anc.CodOperario) 
                     FROM AsignacionNivelesCargos anc
                     INNER JOIN Contratos c ON anc.CodOperario = c.cod_operario
                     WHERE (
                         (pc.cargo IN (2, 44, 45, 46, 47) AND anc.CodNivelesCargos IN (2, 44, 45, 46, 47)) OR 
                         (pc.cargo IN (5, 43) AND anc.CodNivelesCargos IN (5, 43)) OR
                         (pc.cargo NOT IN (2, 44, 45, 46, 47, 5, 43) AND anc.CodNivelesCargos = pc.cargo)
                     )
                     AND (anc.Sucursal = pc.sucursal OR pc.sucursal IS NULL OR pc.sucursal = 0)
                     AND anc.Fecha <= CURDATE()
                     AND (anc.Fin IS NULL OR anc.Fin = '' OR anc.Fin >= CURDATE())
                     AND c.Finalizado = 0
                    )
                )) + pc.cantidad_adicional) as plazas_abiertas
            FROM plazas_cargos pc
            INNER JOIN NivelesCargos nc ON pc.cargo = nc.CodNivelesCargos
            LEFT JOIN sucursales s ON pc.sucursal = s.codigo
            LEFT JOIN departamentos d ON s.cod_departamento = d.codigo
            WHERE pc.visible_web = 1
            AND (pc.cantidad_real + pc.cantidad_adicional) > 0
            HAVING plazas_abiertas > 0
            ORDER BY pc.nivel_urgencia DESC, cvs_recibidos DESC";
 
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $plazas_base = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar resultados en PHP para manejar la lógica de cargos masivos
    $datos_agrupados = [];
    foreach ($plazas_base as $row) {
        $cargo_id = intval($row['id_cargo']);
        $es_vendedor = in_array($cargo_id, [2, 44, 45, 46, 47]);
        $es_lider = in_array($cargo_id, [5, 43]);
        
        $id_estandar = $cargo_id;
        if ($es_vendedor) $id_estandar = 2;
        else if ($es_lider) $id_estandar = 5;

        // Llave de agrupación: cargo_estandar + departamento
        // Si no es masivo, usamos id_plaza para mantenerlo individual
        if ($es_vendedor || $es_lider) {
            $key = $id_estandar . '_' . ($row['departamento_nombre'] ?? 'Sin Dept');
        } else {
            $key = 'indiv_' . $row['id_plaza'];
        }

        if (!isset($datos_agrupados[$key])) {
            $datos_agrupados[$key] = $row;
            $datos_agrupados[$key]['es_agrupado'] = ($es_vendedor || $es_lider);
        } else {
            // Acumular valores para el grupo
            $datos_agrupados[$key]['plazas_abiertas'] += $row['plazas_abiertas'];
            $datos_agrupados[$key]['cvs_recibidos'] += $row['cvs_recibidos'];
            $datos_agrupados[$key]['cantidad_real'] += $row['cantidad_real'];
            $datos_agrupados[$key]['cantidad_adicional'] += $row['cantidad_adicional'];
            $datos_agrupados[$key]['cantidad_cubierta'] += $row['cantidad_cubierta'];
            
            // Mantener la urgencia más alta
            if ($row['nivel_urgencia'] > $datos_agrupados[$key]['nivel_urgencia']) {
                $datos_agrupados[$key]['nivel_urgencia'] = $row['nivel_urgencia'];
            }
        }
    }

    // Convertir de vuelta a array indexado y limitar según registros_por_pagina
    $datos = array_values($datos_agrupados);
    $datos = array_slice($datos, 0, $registros_por_pagina);

    // --- Cálculo de Indicadores ---

    // 1. Plazas Abiertas (total de spots abiertos para plazas visibles)
    $stmtAbiertas = $conn->query("SELECT SUM(GREATEST(0, (pc.cantidad_real - 
                    (SELECT COUNT(DISTINCT anc.CodOperario) 
                     FROM AsignacionNivelesCargos anc
                     INNER JOIN Contratos c ON anc.CodOperario = c.cod_operario
                     WHERE (
                         (pc.cargo IN (2, 44, 45, 46, 47) AND anc.CodNivelesCargos IN (2, 44, 45, 46, 47)) OR 
                         (pc.cargo IN (5, 43) AND anc.CodNivelesCargos IN (5, 43)) OR
                         (pc.cargo NOT IN (2, 44, 45, 46, 47, 5, 43) AND anc.CodNivelesCargos = pc.cargo)
                     )
                     AND (anc.Sucursal = pc.sucursal OR pc.sucursal IS NULL OR pc.sucursal = 0)
                     AND anc.Fecha <= CURDATE()
                     AND (anc.Fin IS NULL OR anc.Fin = '' OR anc.Fin >= CURDATE())
                     AND c.Finalizado = 0
                    )
                )) + pc.cantidad_adicional) as total_abiertas
            FROM plazas_cargos pc
            WHERE pc.visible_web = 1 AND (pc.cantidad_real + pc.cantidad_adicional) > 0");
    $totalAbiertas = (int) $stmtAbiertas->fetchColumn();

    // 2. Plazas en Etapa Entrevista (Aprobados por RH pero aún no por Jefe)
    $stmtEntrevista = $conn->query("SELECT COUNT(DISTINCT pp.id) 
                                   FROM postulacion_plaza pp
                                   INNER JOIN postulacion_evaluacion_rh erh ON pp.id = erh.id_postulacion
                                   LEFT JOIN postulacion_evaluacion_jefe eja ON pp.id = eja.id_postulacion
                                   WHERE erh.veredicto = 'aprobado' 
                                   AND (eja.id IS NULL OR eja.veredicto != 'aprobado')");
    $totalEntrevista = (int) $stmtEntrevista->fetchColumn();

    // 3. Plazas en Elección (Aprobados por Jefe Inmediato - Selección Final)
    $stmtEleccion = $conn->query("SELECT COUNT(DISTINCT pp.id) 
                                 FROM postulacion_plaza pp
                                 INNER JOIN postulacion_evaluacion_jefe eja ON pp.id = eja.id_postulacion
                                 WHERE eja.veredicto = 'aprobado'");
    $totalEleccion = (int) $stmtEleccion->fetchColumn();

    // 4. Total Plazas Cubiertas (Total de colaboradores/operarios activos)
    $stmtCubiertas = $conn->query("SELECT COUNT(DISTINCT anc.CodOperario) FROM AsignacionNivelesCargos anc
                                  INNER JOIN Contratos c ON anc.CodOperario = c.cod_operario
                                  WHERE anc.Fecha <= CURDATE() 
                                  AND (anc.Fin IS NULL OR anc.Fin = '' OR anc.Fin >= CURDATE())
                                  AND c.Finalizado = 0");
    $totalCubiertas = (int) $stmtCubiertas->fetchColumn();

    echo json_encode([
        'success' => true,
        'datos' => $datos,
        'indicadores' => [
            'plazas_abiertas' => $totalAbiertas,
            'en_entrevista' => $totalEntrevista,
            'en_eleccion' => $totalEleccion,
            'total_cubiertas' => $totalCubiertas
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
