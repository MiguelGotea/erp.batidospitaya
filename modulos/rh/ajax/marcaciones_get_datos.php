<?php
/**
 * AJAX Endpoint: Obtener datos de marcaciones con horarios programados
 * Combina horarios programados con marcaciones reales para mostrar faltas
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

try {
    // PASO 1: Obtener horarios programados
    $sqlHorarios = "
    SELECT 
        hso.id,
        hso.cod_operario,
        hso.cod_sucursal,
        ss.numero_semana,
        ss.fecha_inicio,
        ss.fecha_fin,
        hso.lunes_estado, hso.lunes_entrada, hso.lunes_salida,
        hso.martes_estado, hso.martes_entrada, hso.martes_salida,
        hso.miercoles_estado, hso.miercoles_entrada, hso.miercoles_salida,
        hso.jueves_estado, hso.jueves_entrada, hso.jueves_salida,
        hso.viernes_estado, hso.viernes_entrada, hso.viernes_salida,
        hso.sabado_estado, hso.sabado_entrada, hso.sabado_salida,
        hso.domingo_estado, hso.domingo_entrada, hso.domingo_salida,
        s.nombre as nombre_sucursal,
        o.Nombre, o.Apellido, o.Apellido2,
        nc.Nombre as nombre_cargo,
        nc.CodNivelesCargos as codigo_cargo,
        c.fecha_salida
    FROM HorariosSemanalesOperaciones hso
    JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
    JOIN sucursales s ON hso.cod_sucursal = s.codigo
    JOIN Operarios o ON hso.cod_operario = o.CodOperario
    LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
    LEFT JOIN (
        SELECT cod_operario, MAX(fecha_salida) as fecha_salida 
        FROM Contratos 
        WHERE fecha_salida IS NOT NULL
        GROUP BY cod_operario
    ) c ON o.CodOperario = c.cod_operario
    WHERE 1=1
    ";

    $paramsHorarios = [];

    // Aplicar filtro de semana O filtro de fecha (mutuamente excluyentes)
    if (isset($filtros['numero_semana']) && !empty($filtros['numero_semana'])) {
        // Filtrar por número de semana
        $semanaMin = isset($filtros['numero_semana']['min']) ? intval($filtros['numero_semana']['min']) : null;
        $semanaMax = isset($filtros['numero_semana']['max']) ? intval($filtros['numero_semana']['max']) : null;

        if ($semanaMin !== null && $semanaMax !== null) {
            $sqlHorarios .= " AND ss.numero_semana BETWEEN ? AND ?";
            $paramsHorarios[] = $semanaMin;
            $paramsHorarios[] = $semanaMax;
        } elseif ($semanaMin !== null) {
            $sqlHorarios .= " AND ss.numero_semana >= ?";
            $paramsHorarios[] = $semanaMin;
        } elseif ($semanaMax !== null) {
            $sqlHorarios .= " AND ss.numero_semana <= ?";
            $paramsHorarios[] = $semanaMax;
        }
    } else {
        // Filtrar por rango de fechas
        $sqlHorarios .= " AND ss.fecha_inicio <= ? AND ss.fecha_fin >= ?";
        $paramsHorarios[] = $fechaHasta;
        $paramsHorarios[] = $fechaDesde;
    }

    // Filtro por sucursal (general)
    if (isset($filtros['sucursal']) && !empty($filtros['sucursal']) && $filtros['sucursal'] !== 'todas') {
        $sqlHorarios .= " AND hso.cod_sucursal = ?";
        $paramsHorarios[] = $filtros['sucursal'];
    }

    // Filtro por operario
    if (isset($filtros['operario']) && !empty($filtros['operario']) && $filtros['operario'] !== 'todos') {
        $sqlHorarios .= " AND hso.cod_operario = ?";
        $paramsHorarios[] = $filtros['operario'];
    }

    // Aplicar restricciones de permisos para horarios
    if ($esLider) {
        $sucursalesLider = obtenerSucursalesLider($usuario['CodOperario']);
        if (!empty($sucursalesLider)) {
            $sucursalLider = $sucursalesLider[0]['codigo'];
            // Filtrar por la sucursal que define el equipo de trabajo (HSO)
            $sqlHorarios .= " AND hso.cod_sucursal = ?";
            $paramsHorarios[] = $sucursalLider;
        }
    } elseif ($esCDS) {
        $sqlHorarios .= " AND hso.cod_sucursal = '6'";
        $sqlHorarios .= " AND EXISTS (
            SELECT 1 FROM AsignacionNivelesCargos anc_cds
            WHERE anc_cds.CodOperario = hso.cod_operario
            AND anc_cds.CodNivelesCargos IN (23, 20, 34)
            AND (anc_cds.Fin IS NULL OR anc_cds.Fin >= CURDATE())
        )";
    } elseif ($esOperaciones) {
        $sqlHorarios .= " AND s.sucursal = 1";
    }

    $stmtHorarios = $conn->prepare($sqlHorarios);
    $stmtHorarios->execute($paramsHorarios);
    $horariosProgramados = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);

    // Obtener configuración de estados (Con Marcación / Sin Marcación)
    $sqlEstadosReg = "SELECT codigo, tipo FROM tipo_estado_horario";
    $estadosConfig = $conn->query($sqlEstadosReg)->fetchAll(PDO::FETCH_KEY_PAIR);
    // Asegurar que 'Activo' y 'Otra.Tienda' por defecto requieran marcación si no están en la tabla
    if (!isset($estadosConfig['Activo']))
        $estadosConfig['Activo'] = 'con_marcacion';
    if (!isset($estadosConfig['Otra.Tienda']))
        $estadosConfig['Otra.Tienda'] = 'con_marcacion';

    // PASO 2: Obtener marcaciones reales para los operarios encontrados
    $marcaciones = [];
    if (!empty($horariosProgramados)) {
        $operariosIds = array_unique(array_column($horariosProgramados, 'cod_operario'));

        if (!empty($operariosIds)) {
            $fechaDesdeMarcaciones = $fechaDesde;
            $fechaHastaMarcaciones = $fechaHasta;

            if (isset($filtros['numero_semana'])) {
                $stmtSemanas = $conn->prepare("
                    SELECT MIN(fecha_inicio) as minima, MAX(fecha_fin) as maxima 
                    FROM SemanasSistema 
                    WHERE (numero_semana >= ? OR ? = '') AND (numero_semana <= ? OR ? = '')
                ");
                $minSem = isset($filtros['numero_semana']['min']) ? $filtros['numero_semana']['min'] : '';
                $maxSem = isset($filtros['numero_semana']['max']) ? $filtros['numero_semana']['max'] : '';
                $stmtSemanas->execute([$minSem, $minSem, $maxSem, $maxSem]);
                $rangoSemanas = $stmtSemanas->fetch(PDO::FETCH_ASSOC);

                if ($rangoSemanas['minima']) {
                    $fechaDesdeMarcaciones = $rangoSemanas['minima'];
                    $fechaHastaMarcaciones = $rangoSemanas['maxima'];
                    if ($fechaHastaMarcaciones > $fechaHoy)
                        $fechaHastaMarcaciones = $fechaHoy;
                }
            }

            $placeholders = implode(',', array_fill(0, count($operariosIds), '?'));
            $sqlMarcaciones = "
            SELECT 
                m.id, m.fecha, m.hora_ingreso, m.hora_salida, m.CodOperario, m.sucursal_codigo,
                s.nombre as nombre_sucursal
            FROM marcaciones m
            JOIN sucursales s ON m.sucursal_codigo = s.codigo
            WHERE m.fecha BETWEEN ? AND ?
            AND m.CodOperario IN ($placeholders)
            ";

            $paramsMarcaciones = array_merge([$fechaDesdeMarcaciones, $fechaHastaMarcaciones], $operariosIds);
            $stmtMarcaciones = $conn->prepare($sqlMarcaciones);
            $stmtMarcaciones->execute($paramsMarcaciones);
            $marcaciones = $stmtMarcaciones->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // PASO 2.5: Precargar justificaciones existentes para optimizar
    $sqlTardanzasExistentes = "SELECT cod_operario, fecha_tardanza, cod_sucursal, estado, tipo_justificacion, observaciones FROM TardanzasManuales WHERE fecha_tardanza BETWEEN ? AND ?";
    $stmtTardanzas = $conn->prepare($sqlTardanzasExistentes);
    $stmtTardanzas->execute([$fechaDesdeMarcaciones, $fechaHastaMarcaciones]);
    $tardanzasExistentes = $stmtTardanzas->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    $sqlFaltasExistentes = "
        SELECT f.cod_operario, f.fecha_falta, f.cod_sucursal, f.tipo_falta, f.observaciones_rrhh as observaciones, t.tipo_status
        FROM faltas_manual f
        LEFT JOIN tipos_falta t ON f.tipo_falta = t.codigo
        WHERE f.fecha_falta BETWEEN ? AND ?
    ";
    $stmtFaltasEx = $conn->prepare($sqlFaltasExistentes);
    $stmtFaltasEx->execute([$fechaDesdeMarcaciones, $fechaHastaMarcaciones]);
    $faltasExistentes = $stmtFaltasEx->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    // PASO 3: Combinar horarios programados con marcaciones
    $resultado = [];
    $diasSemana = ['', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    foreach ($horariosProgramados as $horario) {
        $fechaInicio = new DateTime($horario['fecha_inicio']);
        $fechaFin = new DateTime($horario['fecha_fin']);

        // Generar registros para cada día de la semana
        for ($fecha = clone $fechaInicio; $fecha <= $fechaFin; $fecha->modify('+1 day')) {
            $fechaStr = $fecha->format('Y-m-d');

            // Si hay filtro de semana, NO aplicar filtro de fecha
            // Si NO hay filtro de semana, aplicar filtro de fecha
            if (isset($filtros['numero_semana']) && !empty($filtros['numero_semana'])) {
                // Solo excluir fechas futuras
                if ($fechaStr > $fechaHoy) {
                    continue;
                }
            } else {
                // Aplicar filtro de fecha normal
                if ($fechaStr < $fechaDesde || $fechaStr > $fechaHasta || $fechaStr > $fechaHoy) {
                    continue;
                }
            }

            $diaSemana = $fecha->format('N'); // 1=lunes, 7=domingo
            $nombreDia = $diasSemana[$diaSemana];

            // SI YA SALIÓ DE LA EMPRESA, NO MOSTRAR NADA PARA FECHAS POSTERIORES
            if (!empty($horario['fecha_salida']) && $fechaStr > $horario['fecha_salida']) {
                continue;
            }

            // Obtener datos del día específico
            $estadoDia = $horario[$nombreDia . '_estado'];
            $horaEntradaProgramada = $horario[$nombreDia . '_entrada'];
            $horaSalidaProgramada = $horario[$nombreDia . '_salida'];

            // Solo incluir días que tienen horario programado
            if (!empty($estadoDia) && $estadoDia !== 'Inactivo') {
                // Buscar marcaciones para este día y operario (en cualquier sucursal)
                $marcacionesDelDia = array_filter($marcaciones, function ($m) use ($horario, $fechaStr) {
                    return $m['CodOperario'] == $horario['cod_operario'] &&
                        $m['fecha'] == $fechaStr;
                });

                if (count($marcacionesDelDia) > 0) {
                    // Crear un registro por cada marcación
                    foreach ($marcacionesDelDia as $marcacion) {
                        // Calcular horas trabajadas para este registro
                        $horasTrabajadas = 0;
                        if ($marcacion['hora_ingreso'] && $marcacion['hora_salida']) {
                            $inicio = new DateTime($marcacion['hora_ingreso']);
                            $fin = new DateTime($marcacion['hora_salida']);
                            if ($fin < $inicio)
                                $fin->modify('+1 day');
                            $diff = $fin->diff($inicio);
                            $horasTrabajadas = $diff->h + ($diff->i / 60);
                        }

                        $resultado[] = [
                            'id' => $marcacion['id'],
                            'fecha' => $fechaStr,
                            'numero_semana' => $horario['numero_semana'],
                            'nombre_sucursal' => $horario['nombre_sucursal'],
                            'sucursal_codigo' => $horario['cod_sucursal'],
                            'nombre_completo' => trim($horario['Nombre'] . ' ' . ($horario['Apellido'] ?? '')),
                            'CodOperario' => $horario['cod_operario'],
                            'nombre_cargo' => $horario['nombre_cargo'],
                            'codigo_cargo' => $horario['codigo_cargo'],
                            'hora_ingreso' => $marcacion['hora_ingreso'],
                            'hora_salida' => $marcacion['hora_salida'],
                            'hora_entrada_programada' => $horaEntradaProgramada,
                            'hora_salida_programada' => $horaSalidaProgramada,
                            'estado_dia' => $estadoDia,
                            'tiene_horario' => true,
                            'tiene_marcacion' => true,
                            'requiere_marcacion' => (($estadosConfig[$estadoDia] ?? 'sin_marcacion') === 'con_marcacion'),
                            'horas_trabajadas' => $horasTrabajadas,
                            'tardanza_solicitada' => false,
                            'falta_solicitada' => false,
                            'tardanza_data' => null,
                            'falta_data' => null,
                            // Información de la sucursal donde marcó realmente
                            'sucursal_marcacion_codigo' => $marcacion['sucursal_codigo'],
                            'sucursal_marcacion_nombre' => $marcacion['nombre_sucursal']
                        ];

                        // Verificar si hay tardanza solicitada
                        $op = $horario['cod_operario'];
                        if (isset($tardanzasExistentes[$op])) {
                            foreach ($tardanzasExistentes[$op] as $te) {
                                if ($te['fecha_tardanza'] == $fechaStr && $te['cod_sucursal'] == $horario['cod_sucursal']) {
                                    $resultado[count($resultado) - 1]['tardanza_solicitada'] = true;
                                    $resultado[count($resultado) - 1]['tardanza_data'] = [
                                        'estado' => $te['estado'],
                                        'tipo' => $te['tipo_justificacion'],
                                        'observaciones' => $te['observaciones']
                                    ];
                                    break;
                                }
                            }
                        }
                    }
                } else {
                    // No hay marcación - FALTA POTENCIAL
                    $resultado[] = [
                        'id' => null,
                        'fecha' => $fechaStr,
                        'numero_semana' => $horario['numero_semana'],
                        'nombre_sucursal' => $horario['nombre_sucursal'],
                        'sucursal_codigo' => $horario['cod_sucursal'],
                        'nombre_completo' => trim($horario['Nombre'] . ' ' . ($horario['Apellido'] ?? '')),
                        'CodOperario' => $horario['cod_operario'],
                        'nombre_cargo' => $horario['nombre_cargo'],
                        'codigo_cargo' => $horario['codigo_cargo'],
                        'hora_ingreso' => null,
                        'hora_salida' => null,
                        'hora_entrada_programada' => $horaEntradaProgramada,
                        'hora_salida_programada' => $horaSalidaProgramada,
                        'estado_dia' => $estadoDia,
                        'tiene_horario' => true,
                        'tiene_marcacion' => false,
                        'requiere_marcacion' => (($estadosConfig[$estadoDia] ?? 'sin_marcacion') === 'con_marcacion'),
                        'horas_trabajadas' => 0,
                        'tardanza_solicitada' => false,
                        'falta_solicitada' => ($estadoDia === 'Vacaciones'),
                        'tardanza_data' => null,
                        'falta_data' => null
                    ];

                    // Verificar si hay falta solicitada
                    $op = $horario['cod_operario'];
                    if (isset($faltasExistentes[$op])) {
                        foreach ($faltasExistentes[$op] as $fe) {
                            if ($fe['fecha_falta'] == $fechaStr && $fe['cod_sucursal'] == $horario['cod_sucursal']) {
                                $resultado[count($resultado) - 1]['falta_solicitada'] = true;
                                $resultado[count($resultado) - 1]['falta_data'] = [
                                    'tipo' => $fe['tipo_falta'],
                                    'observaciones' => $fe['observaciones'],
                                    'tipo_status' => $fe['tipo_status']
                                ];
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    // PASO 4: Aplicar filtros a los resultados combinados
    $incidenciasFiltro = isset($_POST['incidencias']) ? $_POST['incidencias'] : 'todos';

    if (!empty($filtros) || $incidenciasFiltro !== 'todos') {
        // Filtro de incidencias (Tri-state)
        if ($incidenciasFiltro !== 'todos') {
            $fechaHoyPHP = date('Y-m-d');
            $resultado = array_filter($resultado, function ($r) use ($incidenciasFiltro, $fechaHoyPHP) {
                $esTardanza = false;
                $esFalta = false;

                // Si es hoy, no se considera incidencia aún (día en curso)
                if ($r['fecha'] === $fechaHoyPHP) {
                    return false;
                } else {
                    if (!empty($r['hora_ingreso']) && !empty($r['hora_entrada_programada'])) {
                        $ingreso = new DateTime($r['hora_ingreso']);
                        $programada = new DateTime($r['hora_entrada_programada']);
                        $intervalo = $programada->diff($ingreso);
                        $minutosDiferencia = ($intervalo->days * 24 * 60) + ($intervalo->h * 60) + $intervalo->i;

                        // Si la fecha de ingreso es mayor y la diferencia es > 1 minuto
                        if ($ingreso > $programada && $minutosDiferencia > 1) {
                            $esTardanza = true;
                        }
                    }

                    $esFalta = (!$r['tiene_marcacion'] && ($r['requiere_marcacion'] ?? false));
                    $tieneIncidencia = ($esTardanza || $esFalta);
                }

                if ($incidenciasFiltro === 'tardanzas') {
                    return ($esTardanza || $r['tardanza_solicitada']);
                } else if ($incidenciasFiltro === 'faltas') {
                    return ($esFalta || $r['falta_solicitada']);
                }
                return true;
            });
        }

        // Filtro de semana
        if (isset($filtros['numero_semana'])) {
            if (isset($filtros['numero_semana']['min']) && $filtros['numero_semana']['min'] !== '') {
                $resultado = array_filter($resultado, function ($r) use ($filtros) {
                    return $r['numero_semana'] >= intval($filtros['numero_semana']['min']);
                });
            }
            if (isset($filtros['numero_semana']['max']) && $filtros['numero_semana']['max'] !== '') {
                $resultado = array_filter($resultado, function ($r) use ($filtros) {
                    return $r['numero_semana'] <= intval($filtros['numero_semana']['max']);
                });
            }
        }

        // Filtro de sucursal
        if (isset($filtros['nombre_sucursal']) && is_array($filtros['nombre_sucursal']) && !empty($filtros['nombre_sucursal'])) {
            $resultado = array_filter($resultado, function ($r) use ($filtros) {
                return in_array($r['sucursal_codigo'], $filtros['nombre_sucursal']);
            });
        }

        // Filtro de colaborador
        if (isset($filtros['nombre_completo']) && is_array($filtros['nombre_completo']) && !empty($filtros['nombre_completo'])) {
            $resultado = array_filter($resultado, function ($r) use ($filtros) {
                return in_array($r['CodOperario'], $filtros['nombre_completo']);
            });
        }

        // Filtro de cargo
        if (isset($filtros['nombre_cargo']) && is_array($filtros['nombre_cargo']) && !empty($filtros['nombre_cargo'])) {
            $resultado = array_filter($resultado, function ($r) use ($filtros) {
                return in_array($r['codigo_cargo'], $filtros['nombre_cargo']);
            });
        }

        // Filtro de Turno Programado (estado_dia)
        if (isset($filtros['estado_dia']) && is_array($filtros['estado_dia']) && !empty($filtros['estado_dia'])) {
            $resultado = array_filter($resultado, function ($r) use ($filtros) {
                return in_array($r['estado_dia'], $filtros['estado_dia']);
            });
        }
    }

    // Reindexar array después de filtros
    $resultado = array_values($resultado);

    // PASO 4.5: Calcular totales por operario y semana para el periodo filtrado
    $totalesSemanales = [];
    foreach ($resultado as $r) {
        $key = $r['CodOperario'] . '_' . ($r['numero_semana'] ?? '0');
        if (!isset($totalesSemanales[$key]))
            $totalesSemanales[$key] = 0;
        $totalesSemanales[$key] += $r['horas_trabajadas'];
    }

    // Agregar el total a cada registro
    foreach ($resultado as &$r) {
        $key = $r['CodOperario'] . '_' . ($r['numero_semana'] ?? '0');
        $r['total_horas_periodo'] = number_format($totalesSemanales[$key], 2);
    }
    unset($r); // Romper referencia

    // PASO 5: Aplicar ordenamiento
    if ($orden['columna']) {
        $columna = $orden['columna'];
        $direccion = strtoupper($orden['direccion']) === 'DESC' ? -1 : 1;

        usort($resultado, function ($a, $b) use ($columna, $direccion) {
            $valA = $a[$columna] ?? '';
            $valB = $b[$columna] ?? '';

            if ($valA == $valB)
                return 0;
            return ($valA < $valB ? -1 : 1) * $direccion;
        });
    } else {
        // Ordenamiento por defecto: fecha DESC
        usort($resultado, function ($a, $b) {
            return strcmp($b['fecha'], $a['fecha']);
        });
    }

    // PASO 6: Aplicar paginación
    $total_registros = count($resultado);
    $offset = ($pagina - 1) * $registros_por_pagina;
    $datos_paginados = array_slice($resultado, $offset, $registros_por_pagina);

    echo json_encode([
        'success' => true,
        'datos' => $datos_paginados,
        'total_registros' => $total_registros
    ]);

} catch (PDOException $e) {
    error_log("Error en marcaciones_get_datos.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}
