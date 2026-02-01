<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar acceso al módulo (cargos con permiso: 8 contabilidad, 13 rrhh, 11 operaciones)
// Excluir código de cargo 5 (líder de sucursal)

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([8, 11, 13, 16, 39, 30, 37]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 11, 13, 16, 39, 30, 37]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener parámetros de filtro
$fechaDesde = $_GET['desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['hasta'] ?? date('Y-m-t');
$busqueda = $_GET['busqueda'] ?? '';

// Validar fechas
if (strtotime($fechaDesde) > strtotime($fechaHasta)) {
    $_SESSION['error'] = "La fecha 'Desde' no puede ser mayor a la fecha 'Hasta'";
    header('Location: tf_operarios.php');
    exit();
}

// Obtener todos los operarios activos con filtro de búsqueda
$operarios = obtenerOperariosConFiltro($busqueda);

// Procesar cada operario para calcular tardanzas y faltas
$reporte = [];
foreach ($operarios as $operario) {
    $codOperario = $operario['CodOperario'];

    // Obtener marcaciones en el rango de fechas
    $marcaciones = obtenerMarcacionesRango($codOperario, $fechaDesde, $fechaHasta);

    // Obtener tardanzas manuales justificadas
    $tardanzasJustificadas = obtenerTardanzasJustificadas($codOperario, $fechaDesde, $fechaHasta);

    // Obtener faltas manuales NO PAGADAS (solo estas restan de las automáticas)
    $faltasNoPagadas = obtenerFaltasReportadas($codOperario, $fechaDesde, $fechaHasta);

    // Calcular estadísticas
    $tardanzasTotales = 0;
    $faltasTotales = 0;
    $diasProgramadosActivos = 0;

    foreach ($marcaciones as $marcacion) {
        // Contar días programados como activos
        if ($marcacion['estado_dia'] === 'Activo') {
            $diasProgramadosActivos++;
        }

        // Verificar si es tardanza (marcación después de la hora programada + 1 minuto)
        if ($marcacion['hora_entrada_programada'] && $marcacion['hora_ingreso']) {
            $horaProgramada = new DateTime($marcacion['hora_entrada_programada']);
            $horaMarcada = new DateTime($marcacion['hora_ingreso']);

            // Calcular diferencia en minutos
            $diferencia = $horaMarcada->diff($horaProgramada);
            $minutosDiferencia = ($diferencia->invert ? 1 : -1) * ($diferencia->h * 60 + $diferencia->i);

            if ($minutosDiferencia > 1) { // Más de 1 minuto de tardanza
                $tardanzasTotales++;
            }
        }
    }

    // FALTAS TOTALES = Días programados activos - Días con marcación de entrada
    // Esto identifica días que deberían tener trabajo pero no tienen marcación
    $diasConMarcacionEntrada = array_filter($marcaciones, function ($m) {
        return !empty($m['hora_ingreso']);
    });

    $faltasTotales = $diasProgramadosActivos - count($diasConMarcacionEntrada);
    if ($faltasTotales < 0)
        $faltasTotales = 0;

    // Calcular valores ejecutados
    $tardanzasEjecutadas = $tardanzasTotales - count($tardanzasJustificadas);
    $faltasEjecutadas = $faltasTotales - count($faltasNoPagadas);

    // Agregar al reporte
    $reporte[] = [
        'codigo' => $codOperario,
        'nombre_completo' => obtenerNombreCompletoOperario($operario),
        'tardanzas_totales' => $tardanzasTotales,
        'tardanzas_justificadas' => count($tardanzasJustificadas),
        'tardanzas_ejecutadas' => $tardanzasEjecutadas > 0 ? $tardanzasEjecutadas : 0,
        'faltas_totales' => $faltasTotales,
        'faltas_reportadas' => count($faltasNoPagadas), // Ahora solo cuenta No_Pagado
        'faltas_ejecutadas' => $faltasEjecutadas > 0 ? $faltasEjecutadas : 0
    ];
}

/**
 * Obtiene operarios con filtro de búsqueda, se puede agregar la línea where Operativo = 1, para enlistar solo los activos
 */
function obtenerOperariosConFiltro($busqueda = '')
{
    global $conn;

    $sql = "SELECT CodOperario, Nombre, Nombre2, Apellido, Apellido2 
            FROM Operarios";

    $params = [];

    if (!empty($busqueda)) {
        // Usar WHERE si no hay otras condiciones, o AND si ya hay WHERE
        $sql .= " WHERE (CONCAT(Nombre, ' ', Apellido) LIKE ? OR CodOperario = ?)";
        $params[] = "%$busqueda%";
        $params[] = $busqueda;
    }

    $sql .= " ORDER BY Nombre, Apellido";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Obtiene marcaciones de un operario en un rango de fechas con horarios programados
 */
function obtenerMarcacionesRango($codOperario, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "
        SELECT 
            m.fecha,
            m.hora_ingreso,
            m.hora_salida,
            m.sucursal_codigo,
            s.nombre as nombre_sucursal,
            DAYOFWEEK(m.fecha) as dia_semana,
            -- Obtener horario programado y estado para el día
            CASE DAYOFWEEK(m.fecha)
                WHEN 2 THEN (SELECT lunes_entrada FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 3 THEN (SELECT martes_entrada FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 4 THEN (SELECT miercoles_entrada FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 5 THEN (SELECT jueves_entrada FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 6 THEN (SELECT viernes_entrada FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 7 THEN (SELECT sabado_entrada FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 1 THEN (SELECT domingo_entrada FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
            END as hora_entrada_programada,
            CASE DAYOFWEEK(m.fecha)
                WHEN 2 THEN (SELECT lunes_salida FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 3 THEN (SELECT martes_salida FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 4 THEN (SELECT miercoles_salida FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 5 THEN (SELECT jueves_salida FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 6 THEN (SELECT viernes_salida FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 7 THEN (SELECT sabado_salida FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 1 THEN (SELECT domingo_salida FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
            END as hora_salida_programada,
            CASE DAYOFWEEK(m.fecha)
                WHEN 2 THEN (SELECT lunes_estado FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 3 THEN (SELECT martes_estado FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 4 THEN (SELECT miercoles_estado FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 5 THEN (SELECT jueves_estado FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 6 THEN (SELECT viernes_estado FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 7 THEN (SELECT sabado_estado FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
                WHEN 1 THEN (SELECT domingo_estado FROM HorariosSemanalesOperaciones h 
                             WHERE h.cod_operario = m.CodOperario 
                             AND h.cod_sucursal = m.sucursal_codigo
                             AND h.id_semana_sistema = (SELECT id FROM SemanasSistema 
                                                       WHERE fecha_inicio <= m.fecha AND fecha_fin >= m.fecha LIMIT 1))
            END as estado_dia
        FROM marcaciones m
        JOIN sucursales s ON m.sucursal_codigo = s.codigo
        WHERE m.CodOperario = ?
        AND m.fecha BETWEEN ? AND ?
        ORDER BY m.fecha";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario, $fechaDesde, $fechaHasta]);
    return $stmt->fetchAll();
}

/**
 * Obtiene tardanzas manuales justificadas para un operario en un rango de fechas
 */
function obtenerTardanzasJustificadas($codOperario, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "SELECT id, fecha_tardanza as fecha, observaciones 
            FROM TardanzasManuales 
            WHERE cod_operario = ? 
            AND fecha_tardanza BETWEEN ? AND ?
            AND estado = 'Justificado'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario, $fechaDesde, $fechaHasta]);
    return $stmt->fetchAll();
}

/**
 * Obtiene faltas manuales no pagadas para un operario en un rango de fechas
 */
function obtenerFaltasReportadas($codOperario, $fechaDesde, $fechaHasta)
{
    global $conn;

    $sql = "SELECT id, fecha_falta as fecha, observaciones 
            FROM faltas_manual 
            WHERE cod_operario = ? 
            AND fecha_falta BETWEEN ? AND ?
            AND tipo_falta = 'No_Pagado'";  // SOLO faltas No_Pagado

    $stmt = $conn->prepare($sql);
    $stmt->execute([$codOperario, $fechaDesde, $fechaHasta]);
    return $stmt->fetchAll();
}

// Verificar si se solicitó la exportación a Excel
if (isset($_GET['exportar_excel'])) {
    // Obtener parámetros de filtro
    $fechaDesde = $_GET['desde'] ?? date('Y-m-01');
    $fechaHasta = $_GET['hasta'] ?? date('Y-m-t');
    $busqueda = $_GET['busqueda'] ?? '';

    // Obtener los datos con los mismos filtros
    $operarios = obtenerOperariosConFiltro($busqueda);
    $reporte = [];

    foreach ($operarios as $operario) {
        $codOperario = $operario['CodOperario'];

        // Obtener marcaciones en el rango de fechas
        $marcaciones = obtenerMarcacionesRango($codOperario, $fechaDesde, $fechaHasta);

        // Obtener tardanzas manuales justificadas
        $tardanzasJustificadas = obtenerTardanzasJustificadas($codOperario, $fechaDesde, $fechaHasta);

        // Obtener faltas manuales no pagadas
        $faltasReportadas = obtenerFaltasReportadas($codOperario, $fechaDesde, $fechaHasta);

        // Calcular estadísticas
        $tardanzasTotales = 0;
        $faltasTotales = 0;

        foreach ($marcaciones as $marcacion) {
            if ($marcacion['hora_entrada_programada'] && $marcacion['hora_ingreso']) {
                $horaProgramada = new DateTime($marcacion['hora_entrada_programada']);
                $horaMarcada = new DateTime($marcacion['hora_ingreso']);

                $diferencia = $horaMarcada->diff($horaProgramada);
                $minutosDiferencia = ($diferencia->invert ? 1 : -1) * ($diferencia->h * 60 + $diferencia->i);

                if ($minutosDiferencia > 1) {
                    $tardanzasTotales++;
                }
            }

            if ($marcacion['estado_dia'] === 'Activo' && !$marcacion['hora_ingreso'] && !$marcacion['hora_salida']) {
                $faltasTotales++;
            }
        }

        $tardanzasEjecutadas = $tardanzasTotales - count($tardanzasJustificadas);
        $faltasEjecutadas = $faltasTotales - count($faltasReportadas);

        $reporte[] = [
            'codigo' => $codOperario,
            'nombre_completo' => obtenerNombreCompletoOperario($operario),
            'tardanzas_totales' => $tardanzasTotales,
            'tardanzas_justificadas' => count($tardanzasJustificadas),
            'tardanzas_ejecutadas' => $tardanzasEjecutadas > 0 ? $tardanzasEjecutadas : 0,
            'faltas_totales' => $faltasTotales,
            'faltas_reportadas' => count($faltasReportadas),
            'faltas_ejecutadas' => $faltasEjecutadas > 0 ? $faltasEjecutadas : 0
        ];
    }

    // Configurar headers para descarga de archivo Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="reporte_tardanzas_faltas_' . date('Y-m-d') . '.xls"');

    // Iniciar salida
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Colaborador/a</th>';
    echo '<th>Tardanzas Totales</th>';
    echo '<th>Tardanzas Justificadas</th>';
    echo '<th>Tardanzas Ejecutadas</th>';
    echo '<th>Faltas Totales</th>';
    echo '<th>Faltas Reportadas</th>';
    echo '<th>Faltas Ejecutadas</th>';
    echo '</tr>';

    foreach ($reporte as $item) {
        echo '<tr>';
        echo '<td>' . $item['codigo'] . '</td>';
        echo '<td>' . htmlspecialchars($item['nombre_completo']) . '</td>';
        echo '<td>' . $item['tardanzas_totales'] . '</td>';
        echo '<td>' . $item['tardanzas_justificadas'] . '</td>';
        echo '<td>' . $item['tardanzas_ejecutadas'] . '</td>';
        echo '<td>' . $item['faltas_totales'] . '</td>';
        echo '<td>' . $item['faltas_reportadas'] . '</td>';
        echo '<td>' . $item['faltas_ejecutadas'] . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Tardanzas y Faltas por Operario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }

        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 5px;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
            margin: 1px auto;
            flex-wrap: wrap;
        }

        .logo {
            height: 50px;
        }

        .logo-container {
            flex-shrink: 0;
            margin-right: auto;
        }

        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            flex-grow: 1;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
            flex-shrink: 0;
        }

        .btn-agregar.activo {
            background-color: #51B8AC;
            color: white;
            font-weight: normal;
        }

        .btn-agregar:hover {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .btn-logout {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #0E544C;
        }

        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
        }

        .subtitle {
            color: #51B8AC;
            font-size: 1.2rem !important;
            margin-bottom: 20px;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }

        select,
        input,
        button {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 15px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #0E544C;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #0E544C;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .text-center {
            text-align: center;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .search-box {
            position: relative;
            min-width: 250px;
        }

        .search-box i {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .tardanza {
            color: #dc3545;
            font-weight: bold;
        }

        .falta {
            color: #dc3545;
            font-weight: bold;
        }

        .justificado {
            color: #28a745;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }

            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }

            .logo-container {
                order: 1;
                margin-right: 0;
            }

            .user-info {
                order: 2;
                margin-left: auto;
            }

            .btn-agregar {
                padding: 6px 10px;
                font-size: 13px;
            }

            .filtros-form {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }
        }

        .ejecutado {
            color: #dc3545;
            font-weight: bold;
            background-color: #fff3cd;
        }

        @media (max-width: 480px) {
            .btn-agregar {
                flex-grow: 1;
                justify-content: center;
                white-space: normal;
                text-align: center;
                padding: 8px 5px;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-end;
            }
        }

        a.btn {
            text-decoration: none;
        }

        /* Nuevos estilos para los filtros */
        .filtros-container {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .filtros-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filtro-group {
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .filtro-group label {
            margin-bottom: 5px;
            text-align: left;
            font-weight: bold;
        }

        .filtro-group select,
        .filtro-group input {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
            width: 100%;
        }

        .filtro-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .filtro-buttons button {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-aplicar {
            background-color: #51B8AC;
            color: white;
        }

        .btn-aplicar:hover {
            background-color: #0E544C;
        }

        .btn-limpiar {
            background-color: #f1f1f1;
            color: #333;
        }

        .btn-limpiar:hover {
            background-color: #ddd;
        }

        .btn-agregar.excel {
            background-color: transparent;
            color: #1d6f42;
            border: 1px solid #1d6f42;
        }

        .btn-agregar.excel:hover {
            background-color: #1d6f42;
            color: white;
        }

        /* Estilos para el autocompletado */
        #operarios-sugerencias {
            width: calc(100% - 2px);
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-top: -1px;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            background-color: white;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        #operarios-sugerencias div {
            padding: 8px;
            cursor: pointer;
        }

        #operarios-sugerencias div:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>

                <div class="buttons-container">
                    <?php if ($esAdmin || verificarAccesoCargo([8, 5, 13, 16])): ?>
                        <a href="../lideres/faltas_manual.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'faltas_manual.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-times"></i> <span class="btn-text">Faltas/Ausencias</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($esAdmin || verificarAccesoCargo([8, 13, 16])): ?>
                        <a href="../rh/tf_operarios.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'tf_operarios.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Totales</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($esAdmin || verificarAccesoCargo([5, 11, 16, 27, 8])): ?>
                        <a href="../operaciones/tardanzas_manual.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == '../operaciones/tardanzas_manual.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Tardanzas</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($esAdmin || verificarAccesoCargo([11, 8, 16])): ?>
                        <a href="../operaciones/horas_extras_manual.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'horas_extras_manual.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Horas Extras</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($esAdmin || verificarAccesoCargo([8, 11, 16])): ?>
                        <a href="../operaciones/feriados.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'feriados.php' ? 'activo' : '' ?>">
                            <i class="fas fa-calendar-day"></i> <span class="btn-text">Feriados</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($esAdmin || verificarAccesoCargo([8, 16])): ?>
                        <a href="../operaciones/viaticos.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'viaticos.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-check-alt"></i> <span class="btn-text">Viáticos</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($esAdmin || verificarAccesoCargo([5, 16])): ?>
                        <a href="programar_horarios_lider.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'programar_horarios_lider.php' ? 'activo' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Generar Horarios</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="user-info">
                    <div class="user-avatar">
                        <?= $esAdmin ?
                            strtoupper(substr($usuario['nombre'], 0, 1)) :
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= $esAdmin ?
                                htmlspecialchars($usuario['nombre']) :
                                htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?>
                        </div>
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="../../../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>

        <?php if (isset($_SESSION['exito'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['exito'] ?>
                <?php unset($_SESSION['exito']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="filtros-container">
            <form method="get" action="tf_operarios.php" class="filtros-form">
                <div class="filtro-group">
                    <label for="busqueda">Colaborador</label>
                    <input type="text" id="busqueda" name="busqueda" placeholder="Escriba para buscar..."
                        value="<?= htmlspecialchars($busqueda) ?>">
                    <div id="operarios-sugerencias"></div>
                </div>

                <div class="filtro-group">
                    <label for="desde">Desde</label>
                    <input type="date" id="desde" name="desde" value="<?= $fechaDesde ?>">
                </div>

                <div class="filtro-group">
                    <label for="hasta">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="<?= $fechaHasta ?>">
                </div>

                <div class="filtro-buttons">
                    <button type="submit" class="btn-aplicar">
                        <i class="fas fa-search"></i> Buscar
                    </button>

                    <?php if ($esAdmin || verificarAccesoCargo([8, 13, 11])): ?>
                        <a style="display:none;" href="tf_operarios.php?<?= http_build_query([
                            'desde' => $fechaDesde,
                            'hasta' => $fechaHasta,
                            'busqueda' => $busqueda,
                            'exportar_excel' => 1
                        ]) ?>" class="btn-agregar excel">
                            <i class="fas fa-file-excel"></i> Exportar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-container">
            <?php if (empty($reporte)): ?>
                <div class="no-results">
                    No se encontraron resultados para los filtros seleccionados.
                </div>
            <?php else: ?>
                <table id="tabla-reporte">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Colaborador/a</th>
                            <th>Tardanzas Totales</th>
                            <th>Tardanzas Justificadas</th>
                            <th>Tardanzas Ejecutadas</th>
                            <th>Faltas Totales</th>
                            <th>Faltas Reportadas</th>
                            <th>Faltas Ejecutadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reporte as $item): ?>
                            <tr>
                                <td><?= $item['codigo'] ?></td>
                                <td><?= htmlspecialchars($item['nombre_completo']) ?></td>
                                <td class="text-center <?= $item['tardanzas_totales'] > 0 ? 'tardanza' : '' ?>">
                                    <?= $item['tardanzas_totales'] ?>
                                </td>
                                <td class="text-center <?= $item['tardanzas_justificadas'] > 0 ? 'justificado' : '' ?>">
                                    <?= $item['tardanzas_justificadas'] ?>
                                </td>
                                <td class="text-center <?= $item['tardanzas_ejecutadas'] > 0 ? 'ejecutado' : '' ?>">
                                    <?= $item['tardanzas_ejecutadas'] ?>
                                </td>
                                <td class="text-center <?= $item['faltas_totales'] > 0 ? 'falta' : '' ?>">
                                    <?= $item['faltas_totales'] ?>
                                </td>
                                <td class="text-center <?= $item['faltas_reportadas'] > 0 ? 'justificado' : '' ?>">
                                    <?= $item['faltas_reportadas'] ?>
                                </td>
                                <td class="text-center <?= $item['faltas_ejecutadas'] > 0 ? 'ejecutado' : '' ?>">
                                    <?= $item['faltas_ejecutadas'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Función para obtener operarios para el autocompletado
        function obtenerOperariosAutocompletado() {
            return fetch('ajax.php?action=obtener_operarios_autocompletado')
                .then(response => response.json())
                .then(data => {
                    return data;
                })
                .catch(error => {
                    console.error('Error al obtener operarios:', error);
                    return [];
                });
        }

        // Función para buscar operarios
        function buscarOperarios(texto, operarios) {
            if (!texto) {
                return operarios;
            }
            return operarios.filter(op =>
                op.nombre.toLowerCase().includes(texto.toLowerCase()) ||
                op.codigo.toString().includes(texto)
            );
        }

        // Inicializar autocompletado cuando el documento esté listo
        document.addEventListener('DOMContentLoaded', function () {
            const busquedaInput = document.getElementById('busqueda');
            const sugerenciasDiv = document.getElementById('operarios-sugerencias');
            let operariosData = [];

            // Cargar datos de operarios
            obtenerOperariosAutocompletado().then(data => {
                operariosData = data;
            });

            // Modificar el evento input del campo búsqueda
            busquedaInput.addEventListener('input', function () {
                const texto = this.value.trim();

                // Si el campo está vacío, ocultar sugerencias
                if (texto === '') {
                    sugerenciasDiv.style.display = 'none';
                    return;
                }

                const resultados = buscarOperarios(texto, operariosData);

                sugerenciasDiv.innerHTML = '';

                if (resultados.length > 0) {
                    resultados.forEach(op => {
                        const div = document.createElement('div');
                        div.textContent = `${op.nombre}`;
                        div.setAttribute('data-codigo', op.codigo);
                        div.setAttribute('data-nombre', op.nombre);
                        div.style.padding = '8px';
                        div.style.cursor = 'pointer';
                        div.addEventListener('click', function () {
                            busquedaInput.value = op.nombre;
                            sugerenciasDiv.style.display = 'none';
                        });
                        div.addEventListener('mouseover', function () {
                            this.style.backgroundColor = '#f5f5f5';
                        });
                        div.addEventListener('mouseout', function () {
                            this.style.backgroundColor = 'white';
                        });
                        sugerenciasDiv.appendChild(div);
                    });
                    sugerenciasDiv.style.display = 'block';
                } else {
                    sugerenciasDiv.style.display = 'none';
                }
            });

            // Ocultar sugerencias al hacer clic fuera
            document.addEventListener('click', function (e) {
                if (e.target !== busquedaInput && !sugerenciasDiv.contains(e.target)) {
                    sugerenciasDiv.style.display = 'none';
                }
            });

            // Manejar tecla Enter en el input
            busquedaInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const texto = this.value.trim();
                    const resultados = buscarOperarios(texto, operariosData);
                    if (resultados.length > 0) {
                        this.value = resultados[0].nombre;
                    }
                    sugerenciasDiv.style.display = 'none';
                }
            });
        });

        // Aplicar filtros
        function aplicarFiltros() {
            const desde = document.getElementById('desde').value;
            const hasta = document.getElementById('hasta').value;
            const busqueda = document.getElementById('busqueda').value;

            // Validar fechas
            if (new Date(desde) > new Date(hasta)) {
                alert('La fecha "Desde" no puede ser mayor a la fecha "Hasta"');
                return;
            }

            window.location.href = `tf_operarios.php?desde=${desde}&hasta=${hasta}&busqueda=${encodeURIComponent(busqueda)}`;
        }

        // Mostrar notificaciones si hay en sesión
        <?php if (isset($_SESSION['exito'])): ?>
            mostrarNotificacion('<?= $_SESSION['exito'] ?>', 'success');
            <?php unset($_SESSION['exito']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            mostrarNotificacion('<?= $_SESSION['error'] ?>', 'error');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        // Función para mostrar notificaciones
        function mostrarNotificacion(mensaje, tipo = 'info') {
            const estilos = {
                success: { background: '#d4edda', color: '#155724', icon: 'check-circle' },
                error: { background: '#f8d7da', color: '#721c24', icon: 'exclamation-circle' },
                info: { background: '#e2e3e5', color: '#383d41', icon: 'info-circle' }
            };

            const estilo = estilos[tipo] || estilos.info;

            const notificacion = document.createElement('div');
            notificacion.style.position = 'fixed';
            notificacion.style.top = '20px';
            notificacion.style.right = '20px';
            notificacion.style.padding = '15px';
            notificacion.style.borderRadius = '4px';
            notificacion.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            notificacion.style.backgroundColor = estilo.background;
            notificacion.style.color = estilo.color;
            notificacion.style.zIndex = '1000';
            notificacion.style.display = 'flex';
            notificacion.style.alignItems = 'center';
            notificacion.style.gap = '10px';
            notificacion.style.maxWidth = '300px';
            notificacion.innerHTML = `
                <i class="fas fa-${estilo.icon}" style="font-size: 1.2rem;"></i>
                <span>${mensaje}</span>
            `;

            document.body.appendChild(notificacion);

            setTimeout(() => {
                notificacion.style.opacity = '0';
                notificacion.style.transition = 'opacity 0.5s ease';
                setTimeout(() => notificacion.remove(), 500);
            }, 3000);
        }
    </script>
</body>

</html>