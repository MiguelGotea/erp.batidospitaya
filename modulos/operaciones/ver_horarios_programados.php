<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php';

if (!function_exists('obtenerHorariosLiderPorSemanaYSucursal')) {
    function obtenerHorariosLiderPorSemanaYSucursal($idSemana, $codSucursal) {
        global $conn;
        
        $stmt = $conn->prepare("
            SELECT cod_operario, 
                   lunes_estado, lunes_comentario, lunes_entrada, lunes_salida, lunes_horas,
                   martes_estado, martes_comentario, martes_entrada, martes_salida, martes_horas,
                   miercoles_estado, miercoles_comentario, miercoles_entrada, miercoles_salida, miercoles_horas,
                   jueves_estado, jueves_comentario, jueves_entrada, jueves_salida, jueves_horas,
                   viernes_estado, viernes_comentario, viernes_entrada, viernes_salida, viernes_horas,
                   sabado_estado, sabado_comentario, sabado_entrada, sabado_salida, sabado_horas,
                   domingo_estado, domingo_comentario, domingo_entrada, domingo_salida, domingo_horas,
                   total_horas
            FROM HorariosSemanales
            WHERE id_semana_sistema = ? AND cod_sucursal = ?
        ");
        $stmt->execute([$idSemana, $codSucursal]);
        
        $resultados = [];
        while ($row = $stmt->fetch()) {
            $resultados[$row['cod_operario']] = $row;
        }
        return $resultados;
    }
}

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Redireccionar según el cargo (ahora para cargo 11 - Jefe de Operaciones y acá el 21 como supervisor)
if (!$esAdmin && !verificarAccesoCargo([11])) {
    header('Location: /index.php');
    exit();
}

// Obtener todas las sucursales (el jefe de operaciones puede ver todas)
$sucursales = obtenerTodasSucursales();

// Obtener semana actual del sistema
$semanaActual = obtenerSemanaActual();

// Obtener datos para la vista
$semanaSeleccionada = $_GET['semana'] ?? $semanaActual['numero_semana'];
$sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);

// Obtener operarios y horarios si hay sucursal y semana seleccionada
$operarios = [];
$semana = null;
$horariosLider = [];
$horariosOperaciones = [];
if ($sucursalSeleccionada && $semanaSeleccionada) {
    $semana = obtenerSemanaPorNumero($semanaSeleccionada);
    if ($semana) {
        // Obtener operarios de la sucursal
        $operarios = obtenerOperariosSucursal($sucursalSeleccionada, $semana['fecha_inicio'], $semana['fecha_fin']);
        
        $horariosLider = obtenerHorariosLiderPorSemanaYSucursal($semana['id'], $sucursalSeleccionada);
        $horariosOperaciones = obtenerHorariosOperacionesPorSemanaYSucursal($semana['id'], $sucursalSeleccionada);
    }
}

// Función para obtener todos los horarios de operaciones para una semana y sucursal
function obtenerHorariosOperacionesPorSemanaYSucursal($idSemana, $codSucursal) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT cod_operario, 
               lunes_estado, lunes_comentario, lunes_entrada, lunes_salida, lunes_horas,
               martes_estado, martes_comentario, martes_entrada, martes_salida, martes_horas,
               miercoles_estado, miercoles_comentario, miercoles_entrada, miercoles_salida, miercoles_horas,
               jueves_estado, jueves_comentario, jueves_entrada, jueves_salida, jueves_horas,
               viernes_estado, viernes_comentario, viernes_entrada, viernes_salida, viernes_horas,
               sabado_estado, sabado_comentario, sabado_entrada, sabado_salida, sabado_horas,
               domingo_estado, domingo_comentario, domingo_entrada, domingo_salida, domingo_horas,
               total_horas, confirmado
        FROM HorariosSemanalesOperaciones
        WHERE id_semana_sistema = ? AND cod_sucursal = ?
    ");
    $stmt->execute([$idSemana, $codSucursal]);
    
    $resultados = [];
    while ($row = $stmt->fetch()) {
        $resultados[$row['cod_operario']] = $row;
    }
    return $resultados;
}

// Obtener estado del horario
$estadoHorario = 'pendiente'; // Por defecto
$totalOperarios = 0;
$operariosConfirmados = 0;

if ($sucursalSeleccionada && $semanaSeleccionada && $semana) {
    // Contar operarios con horarios confirmados
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_operarios,
        SUM(CASE WHEN confirmado = 1 THEN 1 ELSE 0 END) as operarios_confirmados
        FROM HorariosSemanalesOperaciones 
        WHERE id_semana_sistema = ? AND cod_sucursal = ?");
    $stmt->execute([$semana['id'], $sucursalSeleccionada]);
    $result = $stmt->fetch();
    
    $totalOperarios = $result['total_operarios'] ?? 0;
    $operariosConfirmados = $result['operarios_confirmados'] ?? 0;
    
    if ($totalOperarios > 0) {
        if ($operariosConfirmados == $totalOperarios) {
            $estadoHorario = 'publicado';
        } elseif ($operariosConfirmados > 0) {
            $estadoHorario = 'parcial';
        }
    }
}

// Mapear estados a clases y mensajes
$estados = [
    'publicado' => [
        'clase' => 'status-published',
        'mensaje' => 'Horario actual ya fue publicado'
    ],
    'parcial' => [
        'clase' => 'status-partial',
        'mensaje' => 'Horario parcialmente aprobado (' . $operariosConfirmados . '/' . $totalOperarios . ' operarios)'
    ],
    'pendiente' => [
        'clase' => 'status-pending',
        'mensaje' => 'Horario actual pendiente de aprobación'
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualización de Horarios - Operaciones y Supervisión</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
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
        
        .current-week {
            font-size: 0.9rem !important;
            color: #666;
            margin-bottom: 5px;
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
        
        select, input, button {
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
            table-layout: fixed;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        th {
            background-color: #0E544C;
            color: white;
            text-align: center;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .day-header {
            font-weight: bold;
        }

        .day-date {
            font-size: 0.8rem !important;
            color: #ffffff;
            text-align: center;
        }
        
        td:first-child {
            width: 150px;
        }

        td:not(:first-child) {
            width: calc((100% - 150px) / 8);
        }
        
        .status-activo {
            background-color: #d4edda; /* Verde claro */
            color: #155724; /* Verde oscuro para texto */
            text-align: center; /* Texto centrado */
        }
    
        /* Efecto hover para filas */
        tr:hover {
            background-color: rgba(81, 184, 172, 0.1) !important;
            transition: background-color 0.3s ease;
        }

        .status-display {
            text-align: center;
            width: 100%;
            padding: 5px;
            border-radius: 4px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .comment-display {
            width: 100%;
            padding: 5px;
            margin-bottom: 5px;
            font-size: 0.8rem !important;
        }

        .time-display {
            width: 100%;
            padding: 5px;
            margin-bottom: 3px;
        }

        .hours-display {
            display: inline-block;
            width: 100%;
            text-align: center;
            font-weight: bold;
            padding: 5px;
            border-radius: 4px;
            margin-top: 3px;
        }
        
        .normal-hours {
            background-color: #d4edda; /* Verde claro */
            color: #155724; /* Verde oscuro */
        }
    
        /* Horario extendido (amarillo - después de 8:00 PM) */
        .extended-hours {
            background-color: #fff3cd; /* Amarillo claro */
            color: #856404; /* Amarillo oscuro */
        }
    
        /* Estado inactivo (azul) */
        .inactive-hours {
            background-color: #53a1fa; /* Azul */
            color: white;
        }

        .total-hours {
            text-align: center;
            align-content: center;
            font-weight: bold;
            background-color: #e9ecef;
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
        
        .day-cell {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .day-cell-left {
            flex: 1;
        }
        
        .day-cell-right {
            margin-top: auto;
        }
        
        .original-value {
            font-size: 0.8rem !important;
            color: #666;
            border-top: 1px dashed #ccc;
            padding-top: 3px;
            margin-top: 3px;
        }
        
        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
        
        .status-indicator {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            margin-left: 15px;
            font-weight: bold;
        }
        
        .status-published {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-partial {
            background-color: #ffeeba;
            color: #856404;
        }
        
        .status-pending {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .confirmed-icon {
            color: #28a745;
            margin-left: 5px;
        }
        
        tr[data-sin-lider="true"] {
            border-left: 4px solid #ffc107 !important;
            background-color: rgba(255, 193, 7, 0.05);
        }
        
        tr[data-sin-lider="true"]:hover {
            background-color: rgba(255, 193, 7, 0.1) !important;
        }
        
        .diferencia {
            font-size: 0.8rem !important;
            color: #666;
            display: block;
            margin-top: 2px;
        }
        
        .diferencia-roja {
            color: #dc3545;
        }
        
        .diferencia-verde {
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 class="title">Visualización de Horarios Semanales - Operaciones y Supervisión</h1>
                <?php if ($semanaActual): ?>
                    <div style="font-weight:bold;" class="current-week">
                        Semana actual del sistema: <?= $semanaActual['numero_semana'] ?> 
                        (<?= formatoFecha($semanaActual['fecha_inicio']) ?> al <?= formatoFecha($semanaActual['fecha_fin']) ?>)
                    </div>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <span><?= htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?></span>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Regresar a Módulo
                </a>
            </div>
        </div>
        
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
        
        <div class="filters">
            <div class="filter-group">
                <label for="semana">Número de Semana</label>
                <div style="display: flex; gap: 5px;">
                    <input type="number" id="semana" name="semana" 
                           min="1" max="1825"
                           value="<?= $semanaSeleccionada ?>" 
                           placeholder="Ej: 495"
                           style="flex: 1;">
                    <button type="button" onclick="cambiarSemana()" class="btn" style="width: auto;">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </div>
            
            <div class="filter-group">
                <label for="sucursal">Sucursal</label>
                <select id="sucursal" name="sucursal" onchange="cambiarSucursal()">
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sucursal['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <span class="status-indicator <?= $estados[$estadoHorario]['clase'] ?>">
                    <?= $estados[$estadoHorario]['mensaje'] ?>
                </span>
            </div>
        </div>
        
        <?php if ($semanaSeleccionada && $sucursalSeleccionada && $semana): ?>
            <?php if (empty($horariosLider)): ?>
                <div class="alert alert-info">
                    No se ha registrado horario por parte del líder de la sucursal "<?= htmlspecialchars(array_column($sucursales, 'nombre', 'codigo')[$sucursalSeleccionada]) ?>" en la semana seleccionada.
                </div>
                
            <?php else: ?>
                <div style="font-weight:bold;" class="subtitle">
                    Visualizando horarios para la semana <?= $semanaSeleccionada ?> 
                    (<?= formatoFecha($semana['fecha_inicio']) ?> al <?= formatoFecha($semana['fecha_fin']) ?>)
                    | Sucursal: <?= htmlspecialchars(array_column($sucursales, 'nombre', 'codigo')[$sucursalSeleccionada]) ?>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2">Colaborador</th>
                                <?php 
                                $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                                $fechasSemana = [];
                                $fechaActual = new DateTime($semana['fecha_inicio']);
                                
                                foreach ($diasSemana as $dia) {
                                    echo '<th class="day-header">' . $dia . '</th>';
                                    $fechasSemana[] = $fechaActual->format('Y-m-d');
                                    $fechaActual->modify('+1 day');
                                }
                                ?>
                                <th rowspan="2">Total Horas</th>
                            </tr>
                            <tr>
                                <?php 
                                $fechaActual = new DateTime($semana['fecha_inicio']);
                                foreach ($diasSemana as $dia) {
                                    echo '<th class="day-date">' . formatoFecha($fechaActual->format('Y-m-d')) . '</th>';
                                    $fechaActual->modify('+1 day');
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($operarios as $operario): 
                                $horarioLider = $horariosLider[$operario['CodOperario']] ?? null;
                                $horarioOperaciones = $horariosOperaciones[$operario['CodOperario']] ?? null;
                                
                                // Determinar qué horario mostrar (prioridad a operaciones si existe)
                                $horarioMostrar = $horarioOperaciones ?: $horarioLider;
                                
                                // Calcular total de horas
                                $totalHoras = 0;
                                $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
                                foreach ($dias as $dia) {
                                    $totalHoras += $horarioMostrar["{$dia}_horas"] ?? 0;
                                }
                                
                                // Calcular diferencia con horario del líder si hay ambos
                                $diferenciaTotal = 0;
                                if ($horarioOperaciones && $horarioLider) {
                                    $diferenciaTotal = $totalHoras - ($horarioLider['total_horas'] ?? 0);
                                }
                            ?>
                                <tr data-operario="<?= $operario['CodOperario'] ?>"
                                    <?= !$horarioLider ? 'data-sin-lider="true"' : '' ?>>
                                    <td style="font-weight:bold;">
                                        <?= htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido']) ?> 
                                        (<?= htmlspecialchars($operario['CodOperario']) ?>)
                                        <?php if ($horarioOperaciones && $horarioOperaciones['confirmado']): ?>
                                            <i class="fas fa-check-circle confirmed-icon" title="Horario confirmado"></i>
                                        <?php endif; ?>
                                        <?php if (!$horarioLider): ?>
                                            <span style="color: #ffc107; font-size: 0.8rem; display: block;">
                                                <i class="fas fa-exclamation-triangle"></i> Sin horario del líder
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php 
                                    foreach ($dias as $dia): 
                                        $estado = $horarioMostrar["{$dia}_estado"] ?? 'Activo';
                                        $comentario = $horarioMostrar["{$dia}_comentario"] ?? '';
                                        $entrada = $horarioMostrar["{$dia}_entrada"] ?? null;
                                        $salida = $horarioMostrar["{$dia}_salida"] ?? null;
                                        $horasDia = $horarioMostrar["{$dia}_horas"] ?? 0;
                                        
                                        // Valores originales del líder (para mostrar como referencia si hay diferencia)
                                        $estadoLider = $horarioLider["{$dia}_estado"] ?? 'Activo';
                                        $comentarioLider = $horarioLider["{$dia}_comentario"] ?? '';
                                        $entradaLider = $horarioLider["{$dia}_entrada"] ?? '';
                                        $salidaLider = $horarioLider["{$dia}_salida"] ?? '';
                                        $horasDiaLider = $horarioLider["{$dia}_horas"] ?? 0;
                                        
                                        // Calcular diferencia con horario del líder si hay ambos
                                        $diferenciaDia = 0;
                                        $mostrarDiferencia = false;
                                        if ($horarioOperaciones && $horarioLider) {
                                            $diferenciaDia = $horasDia - $horasDiaLider;
                                            $mostrarDiferencia = ($diferenciaDia != 0);
                                        }
                                    ?>
                                        <td>
                                            <div class="day-cell">
                                                <div class="day-cell-left">
                                                    <div class="status-display <?= $estado == 'Activo' ? 'status-activo' : 'inactive-hours' ?>">
                                                        <?= $estado ?>
                                                    </div>
                                                    
                                                    <?php if ($comentario): ?>
                                                        <div class="comment-display">
                                                            <?= htmlspecialchars($comentario) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Mostrar diferencia con horario del líder si aplica -->
                                                    <?php if ($mostrarDiferencia): ?>
                                                        <div style="display:none;" class="original-value">
                                                            <strong>Líder:</strong> 
                                                            <?= $estadoLider ?>
                                                            <?= $comentarioLider ? "<small>" . htmlspecialchars($comentarioLider) . "</small>" : '' ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="day-cell-right">
                                                    <?php if ($entrada && $salida): ?>
                                                        <div class="time-display">
                                                            <strong>Entrada:</strong> <?= formatoHoraAmPm($entrada) ?>
                                                        </div>
                                                        <div class="time-display">
                                                            <strong>Salida:</strong> <?= formatoHoraAmPm($salida) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <span class="hours-display 
                                                        <?= ($estado != 'Activo') ? 'inactive-hours' : 
                                                           (($salida && substr($salida, 0, 2) >= 20) ? 'extended-hours' : 'normal-hours') ?>">
                                                        <?= number_format($horasDia, 2) ?>
                                                        <?php if ($mostrarDiferencia): ?>
                                                            <span style="display:none;" class="diferencia <?= $diferenciaDia > 0 ? 'diferencia-verde' : 'diferencia-roja' ?>">
                                                                <?= $diferenciaDia > 0 ? '+' : '' ?><?= number_format($diferenciaDia, 2) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                    
                                                    <!-- Mostrar horas originales del líder si hay diferencia -->
                                                    <?php if ($mostrarDiferencia): ?>
                                                        <div style="display:none;" class="original-value">
                                                            <strong>Horas líder:</strong> <?= number_format($horasDiaLider, 2) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                    
                                    <td class="total-hours">
                                        <?= number_format($totalHoras, 2) ?>
                                        <?php if ($horarioOperaciones && $horarioLider && $diferenciaTotal != 0): ?>
                                            <span style="display:none;" class="diferencia <?= $diferenciaTotal > 0 ? 'diferencia-verde' : 'diferencia-roja' ?>">
                                                <?= $diferenciaTotal > 0 ? '+' : '' ?><?= number_format($diferenciaTotal, 2) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($horarioOperaciones && $horarioLider): ?>
                                            <div style="display:none;" class="original-value">
                                                <strong>Total líder:</strong> <?= number_format($horarioLider['total_horas'], 2) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php elseif ($sucursalSeleccionada && !$semanaSeleccionada): ?>
            <div style="text-align: center; padding: 20px; color: #666;">
                Ingrese un número de semana para ver los horarios
            </div>
        <?php elseif ($semanaSeleccionada && !$semana): ?>
            <div style="text-align: center; padding: 20px; color: #dc3545;">
                La semana ingresada no existe en el sistema
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Cambiar semana en la URL
        function cambiarSemana() {
            var semana = document.getElementById('semana').value;
            var sucursal = document.getElementById('sucursal').value;
            
            if (semana) {
                window.location.href = 'ver_horarios_programados.php?semana=' + semana + '&sucursal=' + sucursal;
            } else {
                alert('Por favor ingrese un número de semana');
            }
        }
        
        // Cambiar sucursal en la URL
        function cambiarSucursal() {
            var semana = document.getElementById('semana').value;
            var sucursal = document.getElementById('sucursal').value;
            
            if (semana && sucursal) {
                window.location.href = 'ver_horarios_programados.php?semana=' + semana + '&sucursal=' + sucursal;
            }
        }
        
        // Función para mostrar notificaciones bonitas
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