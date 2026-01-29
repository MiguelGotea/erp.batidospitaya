<?php
/**
 * Función para calcular indicadores especiales por fórmula
 */
function calcularIndicadorEspecial($idIndicador, $numeroSemana, $conn)
{
    switch ($idIndicador) {
        case 6: // Promedio rating Google
            $query = "SELECT 
                AVG(
                    CASE UPPER(TRIM(r.starRating))
                        WHEN 'ONE' THEN 1
                        WHEN 'TWO' THEN 2
                        WHEN 'THREE' THEN 3
                        WHEN 'FOUR' THEN 4
                        WHEN 'FIVE' THEN 5
                        ELSE NULL
                    END
                ) AS resultado
            FROM 
                SemanasSistema s
            LEFT JOIN 
                ResenasGoogle r ON DATE(STR_TO_DATE(r.createTime, '%Y-%m-%d %H:%i:%s')) 
                    BETWEEN s.fecha_inicio AND s.fecha_fin
            WHERE 
                s.numero_semana = ?
                AND r.starRating IS NOT NULL
                AND r.starRating IN ('ONE', 'TWO', 'THREE', 'FOUR', 'FIVE')";
            $stmt = $conn->prepare($query);
            $stmt->execute([$numeroSemana]);
            $result = $stmt->fetch();
            return $result ? $result['resultado'] : null;

        case 7: // Porcentaje ventas mostrador
            $query = "SELECT 
                CASE 
                    WHEN SUM(CASE WHEN v.Anulado = 0 THEN v.Precio ELSE 0 END) > 0 
                    THEN ROUND(
                        SUM(CASE WHEN v.Anulado = 0 AND (b.CodGrupo = 7 OR b.CodGrupo = 5) THEN v.Precio ELSE 0 END) * 100.0 /
                        SUM(CASE WHEN v.Anulado = 0 THEN v.Precio ELSE 0 END), 
                        2
                    )
                    ELSE 0 
                END AS resultado
            FROM 
                VentasGlobalesAccessCSV v,
                SemanasSistema s,
                DBBatidos b
            WHERE 
                v.Fecha BETWEEN s.fecha_inicio AND s.fecha_fin
                AND v.CodProducto = b.CodBatido
                AND s.numero_semana = ?
                AND v.local IN ('2','4','5','7','9','10','11','12','13','16','17','20')
                AND v.Modalidad != 'PEDIDOSYA'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$numeroSemana]);
            $result = $stmt->fetch();
            return $result ? $result['resultado'] : null;

        case 8: // Porcentaje reclamos sin investigación
            $query = "SELECT 
                ROUND(
                    COUNT(CASE WHEN r.id NOT IN (SELECT reclamo_id FROM reportes_investigacion) OR r.id IN (SELECT reclamo_id FROM reportes_investigacion WHERE resolucion = 'Equipo de Tienda') THEN 1 END) * 100.0 /
                    COUNT(*),
                    2
                ) AS resultado
            FROM 
                reclamos r,
                SemanasSistema s
            WHERE 
                r.fecha_reclamo BETWEEN s.fecha_inicio AND s.fecha_fin
                AND s.numero_semana = ?
                AND r.id IS NOT NULL";
            $stmt = $conn->prepare($query);
            $stmt->execute([$numeroSemana]);
            $result = $stmt->fetch();
            return $result ? $result['resultado'] : null;

        case 13: // Ventas totales
            $query = "SELECT 
                SUM(CASE WHEN v.Anulado = 0 THEN (v.Precio / 7) ELSE 0 END) AS resultado
            FROM 
                VentasGlobalesAccessCSV v,
                SemanasSistema s
            WHERE 
                v.Fecha BETWEEN s.fecha_inicio AND s.fecha_fin
                AND s.numero_semana = ?
                AND v.local IN ('2','4','5','7','9','10','11','12','13','16','17','20')";
            $stmt = $conn->prepare($query);
            $stmt->execute([$numeroSemana]);
            $result = $stmt->fetch();
            return $result ? $result['resultado'] : null;

        case 27: // Solicitudes mantenimiento general
            $query = "SELECT 
                COUNT(*) AS resultado
            FROM 
                mtto_tickets t,
                SemanasSistema s
            WHERE 
                DATE(t.created_at) BETWEEN s.fecha_inicio AND s.fecha_fin
                AND s.numero_semana = ?
                AND (t.nivel_urgencia = 4 OR t.nivel_urgencia IS NULL)
                AND t.tipo_formulario = 'mantenimiento_general'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$numeroSemana]);
            $result = $stmt->fetch();
            return $result ? $result['resultado'] : null;

        case 28: // Promedio días atención
            $query = "SELECT 
                AVG(
                    CASE 
                        WHEN t.fecha_finalizacion IS NOT NULL 
                        THEN TIMESTAMPDIFF(DAY, t.created_at, t.fecha_finalizacion)
                        ELSE TIMESTAMPDIFF(DAY, t.created_at, CONCAT(s.fecha_fin, ' 23:59:59'))
                    END
                ) AS resultado
            FROM 
                mtto_tickets t,
                SemanasSistema s
            WHERE 
                DATE(t.created_at) BETWEEN s.fecha_inicio AND s.fecha_fin
                AND s.numero_semana = ?
                AND t.nivel_urgencia = 4
                AND t.tipo_formulario = 'mantenimiento_general'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$numeroSemana]);
            $result = $stmt->fetch();
            return $result ? $result['resultado'] : null;

        case 29: // Solicitudes cambio equipos
            $query = "SELECT 
                COUNT(*) AS resultado
            FROM 
                mtto_tickets t,
                SemanasSistema s
            WHERE 
                DATE(t.created_at) BETWEEN s.fecha_inicio AND s.fecha_fin
                AND s.numero_semana = ?
                AND t.tipo_formulario = 'cambio_equipos'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$numeroSemana]);
            $result = $stmt->fetch();
            return $result ? $result['resultado'] : null;

        default:
            return null;
    }
}

/**
 * Función para actualizar automáticamente los indicadores calculados
 */
function actualizarIndicadoresCalculados($numeroSemana, $conn)
{
    // Obtener indicadores automáticos desde la base de datos
    $query = "SELECT id FROM IndicadoresSemanales WHERE automatico = 1 AND activo = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $indicadoresCalculados = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($indicadoresCalculados as $idIndicador) {
        $resultado = calcularIndicadorEspecial($idIndicador, $numeroSemana, $conn);

        if ($resultado !== null) {
            // Buscar el ID de la semana
            $query = "SELECT id FROM SemanasSistema WHERE numero_semana = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$numeroSemana]);
            $semana = $stmt->fetch();

            if ($semana) {
                // Verificar si ya existe
                $query = "SELECT id FROM IndicadoresSemanalesResultados 
                          WHERE id_indicador = ? AND semana = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$idIndicador, $semana['id']]);
                $existe = $stmt->fetch();

                if ($existe) {
                    // Actualizar
                    $query = "UPDATE IndicadoresSemanalesResultados 
                              SET numerador_dato = ?, denominador_dato = 1, fecha_registro = NOW()
                              WHERE id_indicador = ? AND semana = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$resultado, $idIndicador, $semana['id']]);
                } else {
                    // Insertar
                    $query = "INSERT INTO IndicadoresSemanalesResultados 
                              (id_indicador, semana, numerador_dato, denominador_dato, fecha_registro)
                              VALUES (?, ?, ?, 1, NOW())";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$idIndicador, $semana['id'], $resultado]);
                }
            }
        }
    }
}

/**
 * Función para obtener resultado de indicador (unificada) considerando EnUso
 */
function obtenerResultadoIndicador($indicador, $resultadoBD, $conn, $semanaId = null)
{
    // Verificar si el indicador es automático
    $esAutomatico = isset($indicador['automatico']) && $indicador['automatico'] == 1;

    if ($esAutomatico && $semanaId) {
        // Obtener número de semana desde el ID
        $query = "SELECT numero_semana FROM SemanasSistema WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$semanaId]);
        $semana = $stmt->fetch();

        if ($semana) {
            $resultado = calcularIndicadorEspecial($indicador['id'], $semana['numero_semana'], $conn);
            // Si EnUso = 1 y resultado es null, usar 0
            if ($indicador['EnUso'] == 1 && $resultado === null) {
                $resultado = 0;
            }
            return $resultado;
        }
    }

    // Si no es indicador calculado, usar la lógica normal
    if (!$resultadoBD) {
        // Si EnUso = 1, retornar 0 en lugar de null
        if ($indicador['EnUso'] == 1) {
            return 0;
        }
        return null;
    }

    if ($indicador['divide'] == 1) {
        if ($resultadoBD['numerador_dato'] !== null && $resultadoBD['denominador_dato'] !== null && $resultadoBD['denominador_dato'] != 0) {
            return $resultadoBD['numerador_dato'] / $resultadoBD['denominador_dato'];
        }
        // Si EnUso = 1 y hay error en división, retornar 0
        if ($indicador['EnUso'] == 1) {
            return 0;
        }
        return null;
    } else {
        // Si no divide, mostrar el valor que no sea null
        if ($resultadoBD['numerador_dato'] !== null) {
            return $resultadoBD['numerador_dato'];
        } elseif ($resultadoBD['denominador_dato'] !== null) {
            return $resultadoBD['denominador_dato'];
        }
        // Si EnUso = 1 y no hay valores, retornar 0
        if ($indicador['EnUso'] == 1) {
            return 0;
        }
        return null;
    }
}
?>