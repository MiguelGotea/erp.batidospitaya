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
        nc.CodNivelesCargos as codigo_cargo
    FROM HorariosSemanalesOperaciones hso
    JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
    JOIN sucursales s ON hso.cod_sucursal = s.codigo
    JOIN Operarios o ON hso.cod_operario = o.CodOperario
    LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
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

    // Aplicar restricciones de permisos para horarios
    if ($esLider) {
        $sucursalesLider = obtenerSucursalesLider($usuario['CodOperario']);
        if (!empty($sucursalesLider)) {
            $sucursalLider = $sucursalesLider[0]['codigo'];
            $sqlHorarios .= " AND EXISTS (
                SELECT 1 FROM AsignacionNivelesCargos anc_asig
                WHERE anc_asig.CodOperario = hso.cod_operario
                AND anc_asig.Sucursal = ?
                AND (anc_asig.Fin IS NULL OR anc_asig.Fin >= CURDATE())
                AND anc_asig.CodNivelesCargos != 27
            )";
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

    // PASO 2: Obtener marcaciones reales para este rango y operarios/sucursales

    // Si hay filtro de semana, obtener el rango real de esas semanas para traer todas las marcaciones
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

    $sqlMarcaciones = "
    SELECT 
        m.id,
        m.fecha,
        m.hora_ingreso,
        m.hora_salida,
        m.CodOperario,
        m.sucursal_codigo,
        s.sucursal as nombre_sucursal,
        nc.Nombre as nombre_cargo,
        nc.CodNivelesCargos as codigo_cargo
    FROM marcaciones m
    JOIN Operarios o ON m.CodOperario = o.CodOperario
    JOIN sucursales s ON m.sucursal_codigo = s.codigo
    LEFT JOIN SemanasSistema ss ON m.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
    LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
    LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
    WHERE m.fecha BETWEEN ? AND ?
    ";

    $paramsMarcaciones = [$fechaDesdeMarcaciones, $fechaHastaMarcaciones];

    // Aplicar restricciones de permisos para marcaciones
    if ($esLider) {
        $sucursalesLider = obtenerSucursalesLider($usuario['CodOperario']);
        if (!empty($sucursalesLider)) {
            $sucursalLider = $sucursalesLider[0]['codigo'];
            $sqlMarcaciones .= " AND EXISTS (
                SELECT 1 FROM AsignacionNivelesCargos anc_asig
                WHERE anc_asig.CodOperario = m.CodOperario
                AND anc_asig.Sucursal = ?
                AND (anc_asig.Fin IS NULL OR anc_asig.Fin >= CURDATE())
                AND anc_asig.CodNivelesCargos != 27
            )";
            $paramsMarcaciones[] = $sucursalLider;
        }
    } elseif ($esCDS) {
        $sqlMarcaciones .= " AND m.sucursal_codigo = '6'";
        $sqlMarcaciones .= " AND EXISTS (
            SELECT 1 FROM AsignacionNivelesCargos anc_cds
            WHERE anc_cds.CodOperario = m.CodOperario
            AND anc_cds.CodNivelesCargos IN (23, 20, 34)
            AND (anc_cds.Fin IS NULL OR anc_cds.Fin >= CURDATE())
        )";
    } elseif ($esOperaciones) {
        $sqlMarcaciones .= " AND s.sucursal = 1";
    }

    $stmtMarcaciones = $conn->prepare($sqlMarcaciones);
    $stmtMarcaciones->execute($paramsMarcaciones);
    $marcaciones = $stmtMarcaciones->fetchAll(PDO::FETCH_ASSOC);

    // PASO 2.5: Precargar justificaciones existentes para optimizar
    $sqlTardanzasExistentes = "SELECT cod_operario, fecha_tardanza, cod_sucursal FROM TardanzasManuales WHERE fecha_tardanza BETWEEN ? AND ?";
    $stmtTardanzas = $conn->prepare($sqlTardanzasExistentes);
    $stmtTardanzas->execute([$fechaDesdeMarcaciones, $fechaHastaMarcaciones]);
    $tardanzasExistentes = $stmtTardanzas->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

    $sqlFaltasExistentes = "SELECT cod_operario, fecha_falta, cod_sucursal FROM faltas_manual WHERE fecha_falta BETWEEN ? AND ?";
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

            // Obtener datos del día específico
            $estadoDia = $horario[$nombreDia . '_estado'];
            $horaEntradaProgramada = $horario[$nombreDia . '_entrada'];
            $horaSalidaProgramada = $horario[$nombreDia . '_salida'];

            // Solo incluir días que tienen horario programado
            if (!empty($estadoDia) && $estadoDia !== 'Inactivo') {
                // Buscar marcaciones para este día y operario
                $marcacionesDelDia = array_filter($marcaciones, function ($m) use ($horario, $fechaStr) {
                    return $m['CodOperario'] == $horario['cod_operario'] &&
                        $m['fecha'] == $fechaStr &&
                        $m['sucursal_codigo'] == $horario['cod_sucursal'];
                });

                if (count($marcacionesDelDia) > 0) {
                    // Crear un registro por cada marcación
                    foreach ($marcacionesDelDia as $marcacion) {
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
                            'tardanza_solicitada' => false,
                            'falta_solicitada' => false
                        ];

                        // Verificar si hay tardanza solicitada
                        $op = $horario['cod_operario'];
                        if (isset($tardanzasExistentes[$op])) {
                            foreach ($tardanzasExistentes[$op] as $te) {
                                if ($te['fecha_tardanza'] == $fechaStr && $te['cod_sucursal'] == $horario['cod_sucursal']) {
                                    $resultado[count($resultado) - 1]['tardanza_solicitada'] = true;
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
                        'tardanza_solicitada' => false,
                        'falta_solicitada' => ($estadoDia === 'Vacaciones')
                    ];

                    // Verificar si hay falta solicitada
                    $op = $horario['cod_operario'];
                    if (isset($faltasExistentes[$op])) {
                        foreach ($faltasExistentes[$op] as $fe) {
                            if ($fe['fecha_falta'] == $fechaStr && $fe['cod_sucursal'] == $horario['cod_sucursal']) {
                                $resultado[count($resultado) - 1]['falta_solicitada'] = true;
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
                // Si es hoy, no se considera incidencia aún (día en curso)
                if ($r['fecha'] === $fechaHoyPHP) {
                    $tieneIncidencia = false;
                } else {
                    $esTardanza = false;
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

                    $esFalta = !$r['tiene_marcacion'];
                    $tieneIncidencia = ($esTardanza || $esFalta);
                }

                if ($incidenciasFiltro === 'con_incidencia') {
                    return $tieneIncidencia;
                } else if ($incidenciasFiltro === 'sin_incidencia') {
                    return !$tieneIncidencia;
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
