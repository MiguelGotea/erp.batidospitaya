<?php
// /public_html/modulos/rh/exportar_tardanzas_detalle.php

// Limpiar cualquier output previo
if (ob_get_length()) ob_clean();
ob_start();

require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

verificarAutenticacion();

// Verificar acceso (mismos permisos que ver_marcaciones_todas.php)
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$esLider = verificarAccesoCargo([5, 43]);

if (!$esAdmin && !verificarAccesoCargo([12, 13, 5, 43, 8, 11, 17, 19, 21, 22, 28, 39, 30, 37])) {
    header('Location: /index.php');
    exit();
}

// Obtener parámetros del formulario
$modoVista = $_GET['modo'] ?? 'sucursal';
$sucursalParam = $_GET['sucursal'] ?? ($esLider ? '' : 'todas');
$fechaDesde = $_GET['desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
$operario_id = isset($_GET['operario_id']) ? intval($_GET['operario_id']) : 0;
$filtroActivo = $_GET['activo'] ?? 'todos';

// Para líderes: obtener su sucursal asignada
if ($esLider) {
    $sucursalesLider = obtenerSucursalesLider($_SESSION['usuario_id']);
    if (!empty($sucursalesLider)) {
        $sucursalParam = $sucursalesLider[0]['codigo'];
        $modoVista = 'sucursal';
    }
}

// Validar fechas
$fechaHoy = date('Y-m-d');
if ($fechaHasta > $fechaHoy) {
    $fechaHasta = $fechaHoy;
}

if (strtotime($fechaDesde) > strtotime($fechaHasta)) {
    die("Error: La fecha 'Desde' no puede ser mayor a la fecha 'Hasta'");
}

// Obtener tardanzas detalladas
$tardanzasDetalladas = obtenerTardanzasDetalladas($modoVista, $sucursalParam, $fechaDesde, $fechaHasta, $operario_id, $filtroActivo);

// Generar lista de meses en el rango
$mesesEnRango = generarListaMeses($fechaDesde, $fechaHasta);

// Configurar headers para Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="tardanzas_detalle_' . $fechaDesde . '_a_' . $fechaHasta . '.xls"');
header('Cache-Control: max-age=0');

// Función para generar lista de meses en el rango
function generarListaMeses($fechaDesde, $fechaHasta) {
    $meses = [];
    $inicio = new DateTime($fechaDesde);
    $fin = new DateTime($fechaHasta);
    
    // Establecer al primer día del mes de inicio
    $inicio->modify('first day of this month');
    
    while ($inicio <= $fin) {
        $meses[] = [
            'anio' => $inicio->format('Y'),
            'mes' => $inicio->format('m'),
            'nombre' => $inicio->format('F Y'),
            'corto' => $inicio->format('M Y')
        ];
        $inicio->modify('+1 month');
        $inicio->modify('first day of this month');
    }
    
    return $meses;
}

// Función para obtener tardanzas detalladas
function obtenerTardanzasDetalladas($modoVista, $codSucursal, $fechaDesde, $fechaHasta, $operario_id = 0, $filtroActivo = 'todos') {
    global $conn;
    
    try {
        // Base de la consulta para obtener tardanzas automáticas
        $sql = "
            SELECT 
                m.fecha,
                m.hora_ingreso,
                m.hora_salida,
                m.CodOperario,
                m.sucursal_codigo,
                s.nombre as nombre_sucursal,
                o.Nombre,
                o.Apellido,
                o.Apellido2,
                o.Operativo,
                hso.lunes_entrada,
                hso.martes_entrada,
                hso.miercoles_entrada,
                hso.jueves_entrada,
                hso.viernes_entrada,
                hso.sabado_entrada,
                hso.domingo_entrada,
                hso.lunes_estado,
                hso.martes_estado,
                hso.miercoles_estado,
                hso.jueves_estado,
                hso.viernes_estado,
                hso.sabado_estado,
                hso.domingo_estado,
                nc.Nombre as nombre_cargo,
                c.CodContrato,
                -- Obtener información de tardanza manual
                tm.id as tardanza_manual_id,
                tm.estado as estado_tardanza,
                tm.fecha_registro,
                tm.observaciones as observaciones_tardanza,
                tm.tipo_justificacion
            FROM marcaciones m
            JOIN Operarios o ON m.CodOperario = o.CodOperario
            JOIN sucursales s ON m.sucursal_codigo = s.codigo
            LEFT JOIN HorariosSemanalesOperaciones hso ON m.CodOperario = hso.cod_operario 
                AND m.sucursal_codigo = hso.cod_sucursal
            LEFT JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id 
                AND m.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
            LEFT JOIN AsignacionNivelesCargos anc ON m.CodOperario = anc.CodOperario 
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
            LEFT JOIN Contratos c ON m.CodOperario = c.cod_operario 
                AND (c.fecha_salida IS NULL OR c.fecha_salida = '0000-00-00' OR c.fecha_salida >= CURDATE())
            LEFT JOIN TardanzasManuales tm ON m.CodOperario = tm.cod_operario 
                AND m.fecha = tm.fecha_tardanza
                AND m.sucursal_codigo = tm.cod_sucursal
            WHERE m.fecha BETWEEN ? AND ?
            AND m.hora_ingreso IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM HorariosSemanalesOperaciones hso2
                JOIN SemanasSistema ss2 ON hso2.id_semana_sistema = ss2.id
                WHERE hso2.cod_operario = m.CodOperario
                AND hso2.cod_sucursal = m.sucursal_codigo
                AND m.fecha BETWEEN ss2.fecha_inicio AND ss2.fecha_fin
            )
        ";
        
        $params = [$fechaDesde, $fechaHasta];
        
        // Filtro por sucursal
        if ($modoVista === 'sucursal' && $codSucursal && $codSucursal !== 'todas') {
            $sql .= " AND m.sucursal_codigo = ?";
            $params[] = $codSucursal;
        }
        
        // Filtro por operario
        if ($operario_id > 0) {
            $sql .= " AND m.CodOperario = ?";
            $params[] = $operario_id;
        }
        
        // Filtro de activos/inactivos
        if ($filtroActivo === 'activos') {
            $sql .= " AND o.Operativo = 1";
        } elseif ($filtroActivo === 'inactivos') {
            $sql .= " AND o.Operativo = 0";
        }
        
        // Excluir cargos 27
        $sql .= " AND o.CodOperario NOT IN (
            SELECT DISTINCT anc2.CodOperario 
            FROM AsignacionNivelesCargos anc2
            WHERE anc2.CodNivelesCargos = 27
            AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
        )";
        
        $sql .= " ORDER BY m.fecha DESC, o.Nombre, o.Apellido";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $marcaciones = $stmt->fetchAll();
        
        // Procesar los resultados para identificar tardanzas automáticas
        $tardanzasAutomaticas = [];
        
        foreach ($marcaciones as $marcacion) {
            // Obtener hora programada según el día de la semana
            $diaSemana = date('N', strtotime($marcacion['fecha'])); // 1=lunes, 7=domingo
            $horaProgramada = '';
            $estadoDia = '';
            
            switch ($diaSemana) {
                case 1: // lunes
                    $horaProgramada = $marcacion['lunes_entrada'];
                    $estadoDia = $marcacion['lunes_estado'];
                    break;
                case 2: // martes
                    $horaProgramada = $marcacion['martes_entrada'];
                    $estadoDia = $marcacion['martes_estado'];
                    break;
                case 3: // miércoles
                    $horaProgramada = $marcacion['miercoles_entrada'];
                    $estadoDia = $marcacion['miercoles_estado'];
                    break;
                case 4: // jueves
                    $horaProgramada = $marcacion['jueves_entrada'];
                    $estadoDia = $marcacion['jueves_estado'];
                    break;
                case 5: // viernes
                    $horaProgramada = $marcacion['viernes_entrada'];
                    $estadoDia = $marcacion['viernes_estado'];
                    break;
                case 6: // sábado
                    $horaProgramada = $marcacion['sabado_entrada'];
                    $estadoDia = $marcacion['sabado_estado'];
                    break;
                case 7: // domingo
                    $horaProgramada = $marcacion['domingo_entrada'];
                    $estadoDia = $marcacion['domingo_estado'];
                    break;
            }
            
            // Solo considerar días activos
            if ($estadoDia !== 'Activo') {
                continue;
            }
            
            // Calcular diferencia en minutos
            $diferenciaMinutos = 0;
            $esTardanza = false;
            
            if (!empty($horaProgramada) && !empty($marcacion['hora_ingreso'])) {
                $horaProg = new DateTime($horaProgramada);
                $horaReal = new DateTime($marcacion['hora_ingreso']);
                $diferencia = $horaReal->diff($horaProg);
                $diferenciaMinutos = ($diferencia->invert ? 1 : -1) * ($diferencia->h * 60 + $diferencia->i);
                
                // Es tardanza si llegó más de 1 minuto tarde
                $esTardanza = $diferenciaMinutos > 1;
            }
            
            // Solo incluir si es tardanza
            if ($esTardanza) {
                // Obtener mes y año
                $mes = date('n', strtotime($marcacion['fecha'])); // 1-12
                $anio = date('Y', strtotime($marcacion['fecha']));
                $mesAnio = $anio . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT);
                
                // Verificar si hay tardanza manual registrada
                $tardanzaManualId = $marcacion['tardanza_manual_id'] ?? null;
                $tardanzaJustificada = false;
                $tardanzaReportada = false;
                
                if ($tardanzaManualId) {
                    $tardanzaReportada = true;
                    if ($marcacion['estado_tardanza'] === 'Justificado') {
                        $tardanzaJustificada = true;
                    }
                }
                
                $tardanzasAutomaticas[] = [
                    'fecha' => $marcacion['fecha'],
                    'mes_anio' => $mesAnio,
                    'mes' => $mes,
                    'anio' => $anio,
                    'dia_semana' => obtenerNombreDia($diaSemana),
                    'cod_operario' => $marcacion['CodOperario'],
                    'nombre_operario' => trim($marcacion['Nombre'] . ' ' . $marcacion['Apellido'] . ' ' . $marcacion['Apellido2']),
                    'nombre_sucursal' => $marcacion['nombre_sucursal'],
                    'nombre_cargo' => $marcacion['nombre_cargo'] ?? 'Sin cargo',
                    'cod_contrato' => $marcacion['CodContrato'] ?? '',
                    'hora_programada' => $horaProgramada,
                    'hora_real' => $marcacion['hora_ingreso'],
                    'diferencia_minutos' => $diferenciaMinutos,
                    'tardanza_manual_id' => $tardanzaManualId,
                    'tardanza_reportada' => $tardanzaReportada,
                    'tardanza_justificada' => $tardanzaJustificada,
                    'estado_tardanza' => $marcacion['estado_tardanza'] ?? null,
                    'fecha_registro_tardanza' => $marcacion['fecha_registro'] ?? '',
                    'observaciones_tardanza' => $marcacion['observaciones_tardanza'] ?? '',
                    'tipo_justificacion' => $marcacion['tipo_justificacion'] ?? '',
                    'estado_operario' => $marcacion['Operativo'] ? 'Activo' : 'Inactivo'
                ];
            }
        }
        
        // Ahora obtener información de tardanzas reportadas y justificadas para cada operario
        $resultadoFinal = [];
        
        foreach ($tardanzasAutomaticas as $tardanza) {
            $codOperario = $tardanza['cod_operario'];
            $mesAnio = $tardanza['mes_anio'];
            
            // Obtener tardanzas reportadas (todas las faltas_manual para este operario en este mes)
            $fechaDesdeMes = date('Y-m-01', strtotime($tardanza['fecha']));
            $fechaHastaMes = date('Y-m-t', strtotime($tardanza['fecha']));
            
            $sqlReportadas = "SELECT COUNT(*) as total 
                             FROM faltas_manual 
                             WHERE cod_operario = ? 
                             AND fecha_falta BETWEEN ? AND ?";
            
            $stmtReportadas = $conn->prepare($sqlReportadas);
            $stmtReportadas->execute([$codOperario, $fechaDesdeMes, $fechaHastaMes]);
            $tardanzasReportadas = $stmtReportadas->fetch()['total'] ?? 0;
            
            // Obtener tardanzas justificadas (faltas_manual que NO son 'No_Pagado' ni 'Pendiente' en este mes)
            $sqlJustificadas = "SELECT COUNT(*) as total 
                               FROM faltas_manual 
                               WHERE cod_operario = ? 
                               AND fecha_falta BETWEEN ? AND ?
                               AND tipo_falta NOT IN ('No_Pagado', 'Pendiente')";
            
            $stmtJustificadas = $conn->prepare($sqlJustificadas);
            $stmtJustificadas->execute([$codOperario, $fechaDesdeMes, $fechaHastaMes]);
            $tardanzasJustificadas = $stmtJustificadas->fetch()['total'] ?? 0;
            
            // Para este día específico, verificar si es tardanza ejecutada
            $esEjecutada = false;
            
            // Si NO tiene tardanza manual justificada para esta fecha específica, es ejecutada
            if (!$tardanza['tardanza_justificada']) {
                $esEjecutada = true;
            }
            
            $tardanza['tardanzas_totales'] = 1; // Esta es una tardanza automática
            $tardanza['tardanzas_reportadas_mes'] = $tardanzasReportadas;
            $tardanza['tardanzas_justificadas_mes'] = $tardanzasJustificadas;
            $tardanza['tardanzas_ejecutadas'] = $esEjecutada ? 1 : 0;
            $tardanza['estado_final'] = $esEjecutada ? 'Ejecutada' : ($tardanza['tardanza_justificada'] ? 'Justificada' : 'Reportada');
            
            $resultadoFinal[] = $tardanza;
        }
        
        return $resultadoFinal;
        
    } catch (Exception $e) {
        error_log("Error en obtenerTardanzasDetalladas: " . $e->getMessage());
        return [];
    }
}

// Función auxiliar para obtener nombre del día
function obtenerNombreDia($numeroDia) {
    $dias = [
        1 => 'Lunes',
        2 => 'Martes', 
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo'
    ];
    
    return $dias[$numeroDia] ?? 'Desconocido';
}

// Función para formatear hora
function formatoHoraExcel($hora) {
    if (empty($hora)) return '';
    return date('h:i A', strtotime($hora));
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte Detallado de Tardanzas</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background-color: #0E544C;
            color: white;
            font-weight: bold;
        }
        .tardanza-ejecutada {
            background-color: #ffcccc;
        }
        .tardanza-justificada {
            background-color: #ccffcc;
        }
        .tardanza-reportada {
            background-color: #ffffcc;
        }
        .resumen {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
        }
        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .mes-header {
            background-color: #e8f4f8;
            font-weight: bold;
            text-align: center;
        }
        .subtotal {
            background-color: #f9f9f9;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php if (empty($tardanzasDetalladas)): ?>
        <div class="resumen">
            <h2>No se encontraron tardanzas en el rango de fechas seleccionado</h2>
            <p><strong>Período:</strong> <?= formatoFecha($fechaDesde) ?> al <?= formatoFecha($fechaHasta) ?></p>
        </div>
    <?php else: ?>
        <!-- Resumen del reporte -->
        <div class="resumen">
            <h2>Reporte Detallado de Tardanzas</h2>
            <p><strong>Período:</strong> <?= formatoFecha($fechaDesde) ?> al <?= formatoFecha($fechaHasta) ?></p>
            <p><strong>Total de registros:</strong> <?= count($tardanzasDetalladas) ?></p>
            <p><strong>Meses incluidos:</strong> 
                <?php 
                $nombresMeses = array_map(function($mes) {
                    return date('F Y', strtotime($mes['anio'] . '-' . $mes['mes'] . '-01'));
                }, $mesesEnRango);
                echo implode(', ', $nombresMeses);
                ?>
            </p>
            <p><strong>Generado:</strong> <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <!-- Tabla principal -->
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Mes</th>
                    <th>Día</th>
                    <th>Código</th>
                    <th>Colaborador</th>
                    <th>Sucursal</th>
                    <th>Cargo</th>
                    <th>Contrato</th>
                    <th>Estado</th>
                    <th>Hora Programada</th>
                    <th>Hora Real</th>
                    <th>Diferencia (min)</th>
                    <th>Tardanza Total</th>
                    <th>Tardanzas Reportadas (Mes)</th>
                    <th>Tardanzas Justificadas (Mes)</th>
                    <th>Tardanza Ejecutada</th>
                    <th>Estado Final</th>
                    <th>Fecha Registro</th>
                    <th>Tipo Justificación</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $contador = 1;
                $totales = [
                    'tardanzas_totales' => 0,
                    'tardanzas_reportadas' => 0,
                    'tardanzas_justificadas' => 0,
                    'tardanzas_ejecutadas' => 0
                ];
                
                // Agrupar por operario y mes para acumular valores
                $porOperarioMes = [];
                $porSucursalMes = [];
                $mesActual = '';
                
                foreach ($tardanzasDetalladas as $tardanza): 
                    // Determinar clase CSS según estado final
                    $claseFila = '';
                    switch ($tardanza['estado_final']) {
                        case 'Ejecutada':
                            $claseFila = 'tardanza-ejecutada';
                            break;
                        case 'Justificada':
                            $claseFila = 'tardanza-justificada';
                            break;
                        case 'Reportada':
                            $claseFila = 'tardanza-reportada';
                            break;
                    }
                    
                    // Acumular totales generales
                    $totales['tardanzas_totales'] += $tardanza['tardanzas_totales'];
                    $totales['tardanzas_reportadas'] += $tardanza['tardanzas_reportadas_mes'];
                    $totales['tardanzas_justificadas'] += $tardanza['tardanzas_justificadas_mes'];
                    $totales['tardanzas_ejecutadas'] += $tardanza['tardanzas_ejecutadas'];
                    
                    // Agrupar por operario y mes
                    $codOperario = $tardanza['cod_operario'];
                    $mesAnio = $tardanza['mes_anio'];
                    $sucursal = $tardanza['nombre_sucursal'];
                    
                    $keyOperarioMes = $codOperario . '_' . $mesAnio;
                    if (!isset($porOperarioMes[$keyOperarioMes])) {
                        $porOperarioMes[$keyOperarioMes] = [
                            'nombre' => $tardanza['nombre_operario'],
                            'sucursal' => $tardanza['nombre_sucursal'],
                            'cargo' => $tardanza['nombre_cargo'],
                            'mes' => $mesAnio,
                            'tardanzas_totales' => 0,
                            'tardanzas_reportadas' => $tardanza['tardanzas_reportadas_mes'],
                            'tardanzas_justificadas' => $tardanza['tardanzas_justificadas_mes'],
                            'tardanzas_ejecutadas' => 0
                        ];
                    }
                    $porOperarioMes[$keyOperarioMes]['tardanzas_totales']++;
                    $porOperarioMes[$keyOperarioMes]['tardanzas_ejecutadas'] += $tardanza['tardanzas_ejecutadas'];
                    
                    // Agrupar por sucursal y mes
                    $keySucursalMes = $sucursal . '_' . $mesAnio;
                    if (!isset($porSucursalMes[$keySucursalMes])) {
                        $porSucursalMes[$keySucursalMes] = [
                            'sucursal' => $sucursal,
                            'mes' => $mesAnio,
                            'tardanzas_totales' => 0,
                            'tardanzas_reportadas' => 0,
                            'tardanzas_justificadas' => 0,
                            'tardanzas_ejecutadas' => 0
                        ];
                    }
                    $porSucursalMes[$keySucursalMes]['tardanzas_totales']++;
                    $porSucursalMes[$keySucursalMes]['tardanzas_reportadas'] += $tardanza['tardanzas_reportadas_mes'];
                    $porSucursalMes[$keySucursalMes]['tardanzas_justificadas'] += $tardanza['tardanzas_justificadas_mes'];
                    $porSucursalMes[$keySucursalMes]['tardanzas_ejecutadas'] += $tardanza['tardanzas_ejecutadas'];
                ?>
                    <tr class="<?= $claseFila ?>">
                        <td><?= $contador++ ?></td>
                        <td><?= formatoFecha($tardanza['fecha']) ?></td>
                        <td><?= date('F Y', strtotime($tardanza['mes_anio'] . '-01')) ?></td>
                        <td><?= $tardanza['dia_semana'] ?></td>
                        <td><?= $tardanza['cod_operario'] ?></td>
                        <td><?= htmlspecialchars($tardanza['nombre_operario']) ?></td>
                        <td><?= htmlspecialchars($tardanza['nombre_sucursal']) ?></td>
                        <td><?= htmlspecialchars($tardanza['nombre_cargo']) ?></td>
                        <td><?= $tardanza['cod_contrato'] ?></td>
                        <td><?= $tardanza['estado_operario'] ?></td>
                        <td><?= formatoHoraExcel($tardanza['hora_programada']) ?></td>
                        <td><?= formatoHoraExcel($tardanza['hora_real']) ?></td>
                        <td style="text-align: right;"><?= $tardanza['diferencia_minutos'] ?></td>
                        <td style="text-align: center;"><?= $tardanza['tardanzas_totales'] ?></td>
                        <td style="text-align: center;"><?= $tardanza['tardanzas_reportadas_mes'] ?></td>
                        <td style="text-align: center;"><?= $tardanza['tardanzas_justificadas_mes'] ?></td>
                        <td style="text-align: center;"><?= $tardanza['tardanzas_ejecutadas'] ?></td>
                        <td><?= $tardanza['estado_final'] ?></td>
                        <td><?= $tardanza['fecha_registro_tardanza'] ?></td>
                        <td><?= htmlspecialchars($tardanza['tipo_justificacion']) ?></td>
                        <td><?= htmlspecialchars($tardanza['observaciones_tardanza']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <!-- Totales generales -->
            <tfoot>
                <tr class="total-row">
                    <td colspan="13" style="text-align: right; font-weight: bold;">TOTALES GENERALES:</td>
                    <td style="text-align: center; font-weight: bold;"><?= $totales['tardanzas_totales'] ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= $totales['tardanzas_reportadas'] ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= $totales['tardanzas_justificadas'] ?></td>
                    <td style="text-align: center; font-weight: bold;"><?= $totales['tardanzas_ejecutadas'] ?></td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>
        
        <!-- Resumen por Colaborador dividido por Mes -->
        <div style="margin-top: 30px; page-break-before: always;">
            <h3>Resumen por Colaborador - Desglose por Mes</h3>
            <?php
            // Agrupar por colaborador primero
            $porColaborador = [];
            foreach ($porOperarioMes as $key => $datos) {
                $codOperario = explode('_', $key)[0];
                if (!isset($porColaborador[$codOperario])) {
                    $porColaborador[$codOperario] = [
                        'nombre' => $datos['nombre'],
                        'sucursal' => $datos['sucursal'],
                        'cargo' => $datos['cargo'],
                        'meses' => []
                    ];
                }
                $porColaborador[$codOperario]['meses'][$datos['mes']] = $datos;
            }
            
            foreach ($porColaborador as $codOperario => $colaborador): 
                // Ordenar meses cronológicamente
                uksort($colaborador['meses'], function($a, $b) {
                    return strtotime($a) - strtotime($b);
                });
            ?>
                <h4 style="background-color: #e8f4f8; padding: 5px; margin-top: 15px;">
                    <?= htmlspecialchars($colaborador['nombre']) ?> (<?= $codOperario ?>) - 
                    <?= htmlspecialchars($colaborador['sucursal']) ?> - <?= htmlspecialchars($colaborador['cargo']) ?>
                </h4>
                <table style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Tardanzas Totales</th>
                            <th>Tardanzas Reportadas</th>
                            <th>Tardanzas Justificadas</th>
                            <th>Tardanzas Ejecutadas</th>
                            <th>% Ejecutadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalesColaborador = [
                            'tardanzas_totales' => 0,
                            'tardanzas_reportadas' => 0,
                            'tardanzas_justificadas' => 0,
                            'tardanzas_ejecutadas' => 0
                        ];
                        
                        foreach ($colaborador['meses'] as $mes => $datosMes):
                            $porcentajeEjecutadas = $datosMes['tardanzas_totales'] > 0 ? 
                                round(($datosMes['tardanzas_ejecutadas'] / $datosMes['tardanzas_totales']) * 100, 2) : 0;
                            
                            // Acumular totales del colaborador
                            $totalesColaborador['tardanzas_totales'] += $datosMes['tardanzas_totales'];
                            $totalesColaborador['tardanzas_reportadas'] += $datosMes['tardanzas_reportadas'];
                            $totalesColaborador['tardanzas_justificadas'] += $datosMes['tardanzas_justificadas'];
                            $totalesColaborador['tardanzas_ejecutadas'] += $datosMes['tardanzas_ejecutadas'];
                        ?>
                            <tr>
                                <td><?= date('F Y', strtotime($mes . '-01')) ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_totales'] ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_reportadas'] ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_justificadas'] ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_ejecutadas'] ?></td>
                                <td style="text-align: center; <?= $porcentajeEjecutadas > 50 ? 'color: #dc3545;' : 'color: #28a745;' ?>">
                                    <?= $porcentajeEjecutadas ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td style="text-align: right; font-weight: bold;">TOTAL:</td>
                            <td style="text-align: center; font-weight: bold;"><?= $totalesColaborador['tardanzas_totales'] ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= $totalesColaborador['tardanzas_reportadas'] ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= $totalesColaborador['tardanzas_justificadas'] ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= $totalesColaborador['tardanzas_ejecutadas'] ?></td>
                            <td style="text-align: center; font-weight: bold;">
                                <?= $totalesColaborador['tardanzas_totales'] > 0 ? 
                                    round(($totalesColaborador['tardanzas_ejecutadas'] / $totalesColaborador['tardanzas_totales']) * 100, 2) : 0 ?>%
                            </td>
                        </tr>
                    </tfoot>
                </table>
            <?php endforeach; ?>
        </div>
        
        <!-- Resumen por Sucursal dividido por Mes -->
        <div style="margin-top: 30px; page-break-before: always;">
            <h3>Resumen por Sucursal - Desglose por Mes</h3>
            <?php
            // Agrupar por sucursal primero
            $porSucursal = [];
            foreach ($porSucursalMes as $key => $datos) {
                $sucursal = $datos['sucursal'];
                if (!isset($porSucursal[$sucursal])) {
                    $porSucursal[$sucursal] = [
                        'meses' => []
                    ];
                }
                $porSucursal[$sucursal]['meses'][$datos['mes']] = $datos;
            }
            
            foreach ($porSucursal as $nombreSucursal => $sucursal): 
                // Ordenar meses cronológicamente
                uksort($sucursal['meses'], function($a, $b) {
                    return strtotime($a) - strtotime($b);
                });
            ?>
                <h4 style="background-color: #e8f4f8; padding: 5px; margin-top: 15px;">
                    <?= htmlspecialchars($nombreSucursal) ?>
                </h4>
                <table style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Tardanzas Totales</th>
                            <th>Tardanzas Reportadas</th>
                            <th>Tardanzas Justificadas</th>
                            <th>Tardanzas Ejecutadas</th>
                            <th>% Ejecutadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalesSucursal = [
                            'tardanzas_totales' => 0,
                            'tardanzas_reportadas' => 0,
                            'tardanzas_justificadas' => 0,
                            'tardanzas_ejecutadas' => 0
                        ];
                        
                        foreach ($sucursal['meses'] as $mes => $datosMes):
                            $porcentajeEjecutadas = $datosMes['tardanzas_totales'] > 0 ? 
                                round(($datosMes['tardanzas_ejecutadas'] / $datosMes['tardanzas_totales']) * 100, 2) : 0;
                            
                            // Acumular totales de la sucursal
                            $totalesSucursal['tardanzas_totales'] += $datosMes['tardanzas_totales'];
                            $totalesSucursal['tardanzas_reportadas'] += $datosMes['tardanzas_reportadas'];
                            $totalesSucursal['tardanzas_justificadas'] += $datosMes['tardanzas_justificadas'];
                            $totalesSucursal['tardanzas_ejecutadas'] += $datosMes['tardanzas_ejecutadas'];
                        ?>
                            <tr>
                                <td><?= date('F Y', strtotime($mes . '-01')) ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_totales'] ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_reportadas'] ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_justificadas'] ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_ejecutadas'] ?></td>
                                <td style="text-align: center; <?= $porcentajeEjecutadas > 50 ? 'color: #dc3545;' : 'color: #28a745;' ?>">
                                    <?= $porcentajeEjecutadas ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td style="text-align: right; font-weight: bold;">TOTAL:</td>
                            <td style="text-align: center; font-weight: bold;"><?= $totalesSucursal['tardanzas_totales'] ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= $totalesSucursal['tardanzas_reportadas'] ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= $totalesSucursal['tardanzas_justificadas'] ?></td>
                            <td style="text-align: center; font-weight: bold;"><?= $totalesSucursal['tardanzas_ejecutadas'] ?></td>
                            <td style="text-align: center; font-weight: bold;">
                                <?= $totalesSucursal['tardanzas_totales'] > 0 ? 
                                    round(($totalesSucursal['tardanzas_ejecutadas'] / $totalesSucursal['tardanzas_totales']) * 100, 2) : 0 ?>%
                            </td>
                        </tr>
                    </tfoot>
                </table>
            <?php endforeach; ?>
        </div>
        
        <!-- Tabla comparativa por mes para todas las sucursales -->
        <div style="margin-top: 30px; page-break-before: always;">
            <h3>Comparativa Mensual - Todas las Sucursales</h3>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2">Mes</th>
                        <?php foreach ($porSucursal as $nombreSucursal => $datos): ?>
                            <th colspan="4" style="text-align: center;"><?= htmlspecialchars($nombreSucursal) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($porSucursal as $nombreSucursal => $datos): ?>
                            <th>Total</th>
                            <th>Report.</th>
                            <th>Justif.</th>
                            <th>Ejec.</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Obtener todos los meses únicos
                    $todosMeses = [];
                    foreach ($porSucursalMes as $datos) {
                        $todosMeses[$datos['mes']] = date('F Y', strtotime($datos['mes'] . '-01'));
                    }
                    ksort($todosMeses);
                    
                    foreach ($todosMeses as $mesKey => $mesNombre):
                    ?>
                        <tr>
                            <td><?= $mesNombre ?></td>
                            <?php foreach ($porSucursal as $nombreSucursal => $datosSucursal): 
                                $datosMes = $datosSucursal['meses'][$mesKey] ?? [
                                    'tardanzas_totales' => 0,
                                    'tardanzas_reportadas' => 0,
                                    'tardanzas_justificadas' => 0,
                                    'tardanzas_ejecutadas' => 0
                                ];
                            ?>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_totales'] ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_reportadas'] ?></td>
                                <td style="text-align: center;"><?= $datosMes['tardanzas_justificadas'] ?></td>
                                <td style="text-align: center; <?= $datosMes['tardanzas_totales'] > 0 && ($datosMes['tardanzas_ejecutadas'] / $datosMes['tardanzas_totales']) > 0.5 ? 'color: #dc3545;' : 'color: #28a745;' ?>">
                                    <?= $datosMes['tardanzas_ejecutadas'] ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</body>
</html>