<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

date_default_timezone_set('America/Managua');

// Obtener todas las sucursales
$sucursales = obtenerTodasSucursales();

// Obtener datos para la vista
$semanaSeleccionada = $_GET['semana'] ?? null;
$sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);

// Si no hay semana seleccionada, usar la semana actual
if (!$semanaSeleccionada) {
    $semanaActual = obtenerSemanaActual();
    $semanaSeleccionada = $semanaActual['numero_semana'];
}

// Obtener información de la semana
$semana = obtenerSemanaPorNumero($semanaSeleccionada);

// Obtener operarios de la sucursal
$operarios = [];
$horariosLider = [];
$horariosOperaciones = [];

if ($sucursalSeleccionada && $semana) {
    $operarios = obtenerOperariosSucursalConHorario($sucursalSeleccionada, $semana['id']);
    $horariosLider = obtenerHorariosLiderPorSemanaYSucursal($semana['id'], $sucursalSeleccionada);
    
    // Obtener categorías de los operarios
    $codigosOperarios = array_column($operarios, 'CodOperario');
    if (!empty($codigosOperarios)) {
        $placeholders = implode(',', array_fill(0, count($codigosOperarios), '?'));
        $stmt = $conn->prepare("
            SELECT oc.CodOperario, co.NombreCategoria, co.Peso, co.idCategoria 
            FROM OperariosCategorias oc
            JOIN CategoriasOperarios co ON oc.idCategoria = co.idCategoria
            WHERE oc.CodOperario IN ($placeholders)
            AND (oc.FechaFin IS NULL OR oc.FechaFin >= CURDATE())
            AND oc.FechaInicio <= CURDATE()
            ORDER BY oc.FechaInicio DESC
        ");
        
        $stmt->execute($codigosOperarios);
        $categoriasOperarios = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
        
        // Asignar categorías a los operarios
        foreach ($operarios as &$operario) {
            $codOperario = $operario['CodOperario'];
            if (isset($categoriasOperarios[$codOperario])) {
                $operario['categoria'] = $categoriasOperarios[$codOperario][0];
            } else {
                $operario['categoria'] = [
                    'NombreCategoria' => 'Sin categoría',
                    'Peso' => '-',
                    'idCategoria' => 0
                ];
            }
        }
        unset($operario);
    }
    
    // Agregar operarios adicionales si existen en sesión
    if (isset($_SESSION['operarios_adicionales'])) {
        foreach ($_SESSION['operarios_adicionales'] as $opAdicional) {
            $operarios[] = $opAdicional;
        }
    }
    
    // Obtener horarios de operaciones
    $stmt = $conn->prepare("SELECT * FROM HorariosSemanalesOperaciones WHERE id_semana_sistema = ? AND cod_sucursal = ?");
    $stmt->execute([$semana['id'], $sucursalSeleccionada]);
    
    while ($row = $stmt->fetch()) {
        $horariosOperaciones[$row['cod_operario']] = $row;
    }
}

// Función para obtener eventos del calendario - MEJORADA
function obtenerEventosCalendario($operarios, $horariosOperaciones, $semana, $sucursalSeleccionada) {
    $eventos = [];
    
    foreach ($operarios as $operario) {
        $horario = $horariosOperaciones[$operario['CodOperario']] ?? null;
        if (!$horario) continue;
        
        $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
        $fechaBase = new DateTime($semana['fecha_inicio']);
        
        foreach ($dias as $index => $dia) {
            $estado = $horario["{$dia}_estado"] ?? 'Libre';
            $entrada = $horario["{$dia}_entrada"] ?? null;
            $salida = $horario["{$dia}_salida"] ?? null;
            
            // Solo crear evento si está activo y tiene horario
            if ($estado === 'Activo' && $entrada && $salida) {
                $fechaActual = clone $fechaBase;
                $fechaActual->modify("+$index days");
                
                $idCategoria = $operario['categoria']['idCategoria'] ?? 0;
                $nombreCompleto = "{$operario['Nombre']} {$operario['Apellido']}";
                
                $eventos[] = [
                    'id' => "{$operario['CodOperario']}_{$dia}_{$semana['id']}",
                    'title' => $nombreCompleto,
                    'start' => $fechaActual->format('Y-m-d') . 'T' . $entrada,
                    'end' => $fechaActual->format('Y-m-d') . 'T' . $salida,
                    'extendedProps' => [
                        'codOperario' => $operario['CodOperario'],
                        'dia' => $dia,
                        'estado' => $estado,
                        'comentario' => $horario["{$dia}_comentario"] ?? '',
                        'nombreCompleto' => $nombreCompleto,
                        'sucursal' => $sucursalSeleccionada,
                        'semana' => $semana['id'],
                        'categoriaId' => $idCategoria,
                        'esProgramado' => true
                    ],
                    'color' => obtenerColorCategoria($idCategoria),
                    'textColor' => '#000',
                    'classNames' => ['evento-calendario', 'evento-programado'],
                    'editable' => true, // Permitir edición directa
                    'durationEditable' => true, // Permitir cambiar duración
                    'startEditable' => true // Permitir mover
                ];
            }
        }
    }
    
    return $eventos;
}

// Función para generar color único por operario
function obtenerColorOperario($codOperario) {
    $colores = [
        '#E8F5E9', '#E3F2FD', '#FFF3E0', '#F1F8E9', '#F5F5F5',
        '#FFE8E8', '#E8F4FD', '#F0E8FF', '#E8FFE8', '#FFF8E8',
        '#E8FFFF', '#FFE8F5', '#F5E8FF', '#E8F8FF', '#FFF0E8'
    ];
    
    return $colores[$codOperario % count($colores)];
}

$eventosCalendario = obtenerEventosCalendario($operarios, $horariosOperaciones, $semana, $sucursalSeleccionada);

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
               total_horas, fecha_actualizacion
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

// Función para generar color por categoría
function obtenerColorCategoria($idCategoria) {
    $colores = [
        1 => '#E8F5E9',  // Líder - Verde muy claro
        2 => '#E3F2FD',  // Asistente de Líder - Azul muy claro  
        3 => '#FFF3E0',  // Experto - Naranja muy claro
        4 => '#F1F8E9',  // Junior - Verde claro suave
        5 => '#F5F5F5',  // Training - Gris claro
        0 => '#FFFFFF'   // Sin categoría - Blanco
    ];
    
    return $colores[$idCategoria] ?? '#FFFFFF';
}

// Función para obtener operarios no programados
function obtenerOperariosNoProgramados($operarios, $horariosOperaciones, $semana) {
    $noProgramados = [];
    
    foreach ($operarios as $operario) {
        $horario = $horariosOperaciones[$operario['CodOperario']] ?? null;
        $tieneHorario = false;
        
        if ($horario) {
            $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
            foreach ($dias as $dia) {
                $estado = $horario["{$dia}_estado"] ?? 'Libre';
                $entrada = $horario["{$dia}_entrada"] ?? null;
                if ($estado === 'Activo' && $entrada) {
                    $tieneHorario = true;
                    break;
                }
            }
        }
        
        if (!$tieneHorario) {
            $noProgramados[] = $operario;
        }
    }
    
    return $noProgramados;
}

// Obtener operarios no programados para el sidebar
$operariosNoProgramados = obtenerOperariosNoProgramados($operarios, $horariosOperaciones, $semana);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario de Horarios - Operaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
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

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
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
        
        .calendar-container {
            display: flex;
            gap: 20px;
            padding: 20px;
            min-height: calc(100vh - 100px);
        }
        
        .calendar-main {
            flex: 1;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            min-height: 600px;
        }
        
        #calendar {
            height: 100%;
        }
        
        .fc {
            font-family: 'Calibri', sans-serif;
        }
        
        .fc-button-primary {
            background-color: #51B8AC !important;
            border-color: #51B8AC !important;
        }
        
        .fc-button-primary:hover {
            background-color: #0E544C !important;
            border-color: #0E544C !important;
        }
        
        .fc-button-primary:not(:disabled):active,
        .fc-button-primary:not(:disabled).fc-button-active {
            background-color: #0E544C !important;
            border-color: #0E544C !important;
        }
        
        .sidebar {
            width: 350px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 140px);
        }
        
        .sidebar-header {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        
        .operarios-list {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            max-height: calc(100vh - 200px);
        }
        
        .operario-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: move;
            transition: all 0.3s ease;
            cursor: move;
        }
        
        .operario-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .operario-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            border: 1px solid #ddd;
        }
        
        .calendar-main.dragging {
            background: rgba(81, 184, 172, 0.1) !important;
            border: 2px dashed #51B8AC;
        }
        
        /* Estilos adicionales para funcionalidades mejoradas */
        .sidebar-section {
    margin-bottom: 25px;
}

.sidebar-subtitle {
    color: #0E544C;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 2px solid #51B8AC;
}
        
        .operario-item.no-programado {
            background: #f8f9fa;
            border-left: 4px solid #6c757d;
            border: 1px solid #dee2e6;
        }
        
        .operario-item.programado {
            background: #e8f5e9;
            border-left: 4px solid #28a745;
            border: 1px solid #d4edda;
        }
        
        .fc-event-delete-btn:hover {
            background: #c82333 !important;
            transform: scale(1.1);
        }
        
        .evento-temporal {
            opacity: 0.7;
            border: 2px dashed #51B8AC !important;
        }
        
        .fc-timegrid-axis-frame {
            height: 25px !important;
            font-size: 11px !important;
        }
        
        /* Ancho de las Columnas Configuración del calendario para columnas uniformes */
        .fc-timegrid-col {
            min-width: 120px !important; /* Ancho mínimo uniforme para cada día */
            width: 120px !important;
        }
        
        /* Contenedor de eventos - permitir múltiples eventos sin superposición */
        .fc-timegrid-col-events {
            margin: 0 2px !important;
            position: relative !important;
        }
        
        /* Contenedor del contenido del evento */
        .fc-event-main {
            padding: 2px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: 100% !important;
        }
        
        .fc-event {
            font-size: 0.7em !important;
            padding: 1px 2px !important;
            margin: 0 0 1px 0 !important;
        }
        
        /* Ancho de Encabezados Distribución equitativa de columnas */
        .fc-col-header-cell {
            min-width: 120px !important;
            width: 120px !important;
        }
        
        .fc-day-today {
            background-color: rgba(81, 184, 172, 0.1) !important;
        }
        
        /* Mejorar contraste de bordes */
        .fc-timegrid-event {
            border: 1px solid rgba(0, 0, 0, 0.15) !important;
        }
        
        /* Efecto hover en eventos */
        .fc-timegrid-event:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
            z-index: 10 !important;
            cursor: pointer !important;
        }
        
        .fc-timegrid-slot {
            height: 25px !important;
        }
        
        /* Título del evento con texto vertical */
        .fc-event-title {
            writing-mode: vertical-rl !important; /* Texto vertical de derecha a izquierda */
            text-orientation: mixed !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            letter-spacing: 1px !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: clip !important;
            max-height: 100% !important;
            text-align: center !important;
            line-height: 1.2 !important;
            padding: 4px 0 !important;
        }
        
        /* Ocultar hora en eventos del calendario */
        .fc-event-time {
            font-size: 0.8em !important;
            display: none; /* Ocultar la hora en las tarjetas del calendario programado */
        }
        
        .fc-timegrid-slot-label {
            font-size: 11px !important;
            padding: 2px 4px !important;
        }
        
        /* Arreglar el espacio en la columna de tiempo */
        .fc-timegrid-axis {
            width: 50px !important;
        }
        
        .fc-timegrid-slots table {
            width: calc(100% - 50px) !important;
        }
        
        /* Estilos para drag & drop */
        .calendar-main.dragging-over {
            background: rgba(81, 184, 172, 0.1) !important;
            border: 2px dashed #51B8AC !important;
        }
        
        .operario-item.dragging {
            opacity: 0.5;
            transform: scale(0.95);
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
            
            .calendar-container {
                flex-direction: column;
                padding: 10px;
            }
            
            .sidebar {
                width: 100%;
                max-height: 400px;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .fc-timegrid-col {
                min-width: 80px !important;
                width: 80px !important;
            }
            
            .fc-col-header-cell {
                min-width: 80px !important;
                width: 80px !important;
            }
            
            .fc-timegrid-axis {
                width: 40px !important;
            }
            
            .fc-timegrid-slots table {
                width: calc(100% - 40px) !important;
            }
            
            .fc-event-title {
                font-size: 8px !important;
                writing-mode: horizontal-tb !important;
            }
            
            .sidebar-section {
        margin-bottom: 15px;
    }
    
    .operario-item {
        padding: 10px;
    }
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
        
        a.btn{
            text-decoration: none;
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
        
        /* Ajustes para vista responsive */
        @media (max-width: 1200px) {
            .fc-timegrid-col {
                min-width: 100px !important;
                width: 100px !important;
            }
            
            .fc-col-header-cell {
                min-width: 100px !important;
                width: 100px !important;
            }
            
            .fc-event-title {
                font-size: 10px !important;
                letter-spacing: 0.5px !important;
            }
        }
        
        /* Ajustes adicionales para mejorar legibilidad */
        .fc-timegrid-slot-label {
            font-size: 11px !important;
        }
        
        .fc-col-header-cell-cushion {
            padding: 8px 4px !important;
            font-size: 12px !important;
            padding: 2px 4px !important;
        }
        
        /* Mejorar el renderizado del texto vertical */
        .fc-event-title-container {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            height: 100% !important;
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
                    <a href="programar_horarios_operaciones.php" class="btn-agregar">
                        <i class="fas fa-calendar-check"></i> <span class="btn-text">Aprobar Horarios</span>
                    </a>
                    <a href="calendario_horarios2.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'calendario_horarios2.php' ? 'activo' : '' ?>">
                        <i class="fas fa-calendar-alt"></i> <span class="btn-text">Vista Calendario v2</span>
                    </a>
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
                                htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
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

        <div class="filters">
            <div class="filter-group">
                <label for="semana">No. Semana</label>
                <input type="number" id="semana" name="semana" 
                       min="1" max="1825"
                       value="<?= $semanaSeleccionada ?>" 
                       placeholder="Ej: 495">
            </div>
            
            <div class="filter-group">
                <label for="sucursal">Sucursal</label>
                <select id="sucursal" name="sucursal">
                    <?php foreach ($sucursales as $sucursal): ?>
                        <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sucursal['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <button type="button" onclick="cargarCalendario()" class="btn">
                    <i class="fas fa-search"></i> Cargar
                </button>
            </div>
            
            <!-- Formulario para agregar operarios adicionales -->
            <?php if ($sucursalSeleccionada && $semanaSeleccionada && $semana): ?>
                <div class="operario-agregar-container">
                    <select name="cod_operario" id="cod_operario_adicional" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 250px;">
                        <option value="">Seleccione colaborador adicional</option>
                        <?php 
                        $stmt = $conn->prepare("
                            SELECT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2 
                            FROM Operarios o
                            WHERE o.Operativo = 1
                            AND (o.Fin IS NULL OR o.Fin >= CURDATE())
                            AND o.CodOperario NOT IN (
                                SELECT anc.CodOperario 
                                FROM AsignacionNivelesCargos anc 
                                WHERE anc.CodNivelesCargos = 27
                            )
                            ORDER BY o.Nombre, o.Apellido, o.Apellido2
                        ");
                        $stmt->execute();
                        $todosOperarios = $stmt->fetchAll();
                        
                        foreach ($todosOperarios as $op): 
                            // Excluir operarios que ya están en la lista
                            $yaEnLista = false;
                            foreach ($operarios as $operarioExistente) {
                                if ($operarioExistente['CodOperario'] == $op['CodOperario']) {
                                    $yaEnLista = true;
                                    break;
                                }
                            }
                            
                            if (!$yaEnLista):
                        ?>
                            <option value="<?= $op['CodOperario'] ?>">
                                <?= htmlspecialchars($op['Nombre'] . ' ' . $op['Apellido'] . ' ' . $op['Apellido2'] . ' (' . $op['CodOperario'] . ')') ?>
                            </option>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </select>
                    
                    <button type="button" onclick="agregarOperarioCalendario()" class="btn" style="margin-left: 10px;">
                        <i class="fas fa-plus"></i> Agregar Colaborador
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="calendar-container">
            <!-- Calendario principal -->
            <div class="calendar-main">
                <div id='calendar'></div>
            </div>

            <!-- Sidebar con operarios -->
<div class="sidebar">
    <div class="sidebar-header">
        <h5 class="mb-1">
            <i class="fas fa-users me-2"></i>
            Colaboradores
        </h5>
        <small>Arrastra al calendario para programar</small>
    </div>
    
    <div class="operarios-list" id="operariosList">
        <?php if (empty($operarios)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-users-slash fa-3x mb-3"></i>
                <p>No hay colaboradores en esta sucursal</p>
            </div>
        <?php else: ?>
            <!-- Sección de Operarios Programados -->
            <div class="sidebar-section" style="display:none;">
                <h6 class="sidebar-subtitle">
                    <i class="fas fa-calendar-check me-2"></i>
                    Programados (<?= count($operarios) - count($operariosNoProgramados) ?>)
                </h6>
                <?php foreach ($operarios as $operario): 
                    $tieneHorario = false;
                    $horario = $horariosOperaciones[$operario['CodOperario']] ?? null;
                    if ($horario) {
                        $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
                        foreach ($dias as $dia) {
                            $estado = $horario["{$dia}_estado"] ?? 'Libre';
                            $entrada = $horario["{$dia}_entrada"] ?? null;
                            if ($estado === 'Activo' && $entrada) {
                                $tieneHorario = true;
                                break;
                            }
                        }
                    }
                    
                    if ($tieneHorario):
                        $idCategoria = $operario['categoria']['idCategoria'] ?? 0;
                        $nombreCorto = htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido']);
                ?>
                    <div class="operario-item programado" 
                         draggable="true" 
                         data-operario-id="<?= $operario['CodOperario'] ?>"
                         data-operario-nombre="<?= $nombreCorto ?>"
                         data-operario-categoria="<?= $idCategoria ?>">
                        
                        <div class="mb-1">
                            <span class="operario-color" 
                                  style="background: <?= obtenerColorCategoria($idCategoria) ?>"></span>
                            <strong><?= $nombreCorto ?> (<?= htmlspecialchars($operario['CodOperario']) ?>)</strong>
                        </div>
                        
                        <div class="text-muted small">
                            <i class="fas fa-tag me-1"></i>
                            <?= htmlspecialchars($operario['categoria']['NombreCategoria'] ?? 'Sin categoría') ?>
                        </div>
                    </div>
                <?php endif; 
                endforeach; ?>
            </div>

            <!-- Sección de Operarios No Programados -->
            <div class="sidebar-section">
                <h6 class="sidebar-subtitle">
                    <i class="fas fa-clock me-2"></i>
                    Sin Programar (<?= count($operariosNoProgramados) ?>)
                </h6>
                <?php foreach ($operariosNoProgramados as $operario): 
                    $idCategoria = $operario['categoria']['idCategoria'] ?? 0;
                    $nombreCorto = htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido']);
                ?>
                    <div class="operario-item no-programado" 
                         draggable="true" 
                         data-operario-id="<?= $operario['CodOperario'] ?>"
                         data-operario-nombre="<?= $nombreCorto ?>"
                         data-operario-categoria="<?= $idCategoria ?>">
                        
                        <div class="mb-1">
                            <span class="operario-color" 
                                  style="background: <?= obtenerColorCategoria($idCategoria) ?>"></span>
                            <strong><?= $nombreCorto ?> (<?= htmlspecialchars($operario['CodOperario']) ?>)</strong>
                        </div>
                        
                        <div class="text-muted small">
                            <i class="fas fa-tag me-1"></i>
                            <?= htmlspecialchars($operario['categoria']['NombreCategoria'] ?? 'Sin categoría') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
    
    <script>
    let calendar;
    let draggedOperario = null;
    const eventos = <?= json_encode($eventosCalendario) ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'es',
            initialView: 'timeGridWeek',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'timeGridWeek,timeGridDay,dayGridMonth'
            },
            height: 'auto',
            editable: true,
            droppable: true,
            events: eventos,
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            slotDuration: '00:30:00',
            allDaySlot: false,
            expandRows: true,
            eventOverlap: false,
            slotEventOverlap: false,
            nowIndicator: true,
            selectable: false, // Desactivar selección por click para evitar confusión
            
            eventDrop: function(info) {
                actualizarHorarioPorArrastre(info.event);
            },
            
            eventResize: function(info) {
                actualizarHorarioPorArrastre(info.event);
            },
            
            eventClick: function(info) {
                mostrarModalEditarHorario(info.event);
            },
            
            drop: function(info) {
                if (draggedOperario) {
                    programarHorarioOperario(draggedOperario, info.date);
                    draggedOperario = null;
                }
            },
            
            eventDidMount: function(info) {
                // Agregar tooltip
                info.el.title = `${info.event.extendedProps.nombreCompleto}\n${info.event.start.toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'})} - ${info.event.end.toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'})}`;
                
                // Agregar botón de eliminar rápido
                const deleteButton = document.createElement('span');
                deleteButton.innerHTML = '×';
                deleteButton.className = 'fc-event-delete-btn';
                deleteButton.style.cssText = `
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #dc3545;
                    color: white;
                    border-radius: 50%;
                    width: 18px;
                    height: 18px;
                    font-size: 12px;
                    line-height: 18px;
                    text-align: center;
                    cursor: pointer;
                    display: none;
                    z-index: 100;
                `;
                
                deleteButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    eliminarHorarioRapido(info.event);
                });
                
                info.el.appendChild(deleteButton);
                
                // Mostrar botón al hacer hover
                info.el.addEventListener('mouseenter', function() {
                    deleteButton.style.display = 'block';
                });
                
                info.el.addEventListener('mouseleave', function() {
                    deleteButton.style.display = 'none';
                });
            }
        });
        
        calendar.render();
        inicializarDragOperarios();
    });
    
    function inicializarDragOperarios() {
        const operarioItems = document.querySelectorAll('.operario-item');
        const calendarMain = document.querySelector('.calendar-main');
        
        operarioItems.forEach(item => {
            item.addEventListener('dragstart', function(e) {
                draggedOperario = {
                    id: this.dataset.operarioId,
                    nombre: this.dataset.operarioNombre,
                    categoriaId: this.dataset.operarioCategoria || 0
                };
                
                e.dataTransfer.effectAllowed = 'move';
                this.style.opacity = '0.5';
                calendarMain.classList.add('dragging');
            });
            
            item.addEventListener('dragend', function(e) {
                this.style.opacity = '1';
                calendarMain.classList.remove('dragging');
                draggedOperario = null;
            });
        });
        
        calendarMain.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
    }
    
    function cargarCalendario() {
        const semana = document.getElementById('semana').value;
        const sucursal = document.getElementById('sucursal').value;
        
        if (semana && sucursal) {
            window.location.href = 'calendario_horarios2.php?semana=' + semana + '&sucursal=' + sucursal;
        } else {
            alert('Por favor seleccione semana y sucursal');
        }
    }
    
    function programarHorarioOperario(operario, fecha) {
        const diaSemana = fecha.getDay();
        const dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        const dia = dias[diaSemana];
        
        // Mostrar modal para configurar horario
        const modalHtml = `
            <div class="modal fade" id="modalProgramarHorario" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header" style="background: #51B8AC; color: white;">
                            <h5 class="modal-title">
                                <i class="fas fa-clock me-2"></i>
                                Programar Horario
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>Colaborador:</strong> ${operario.nombre}<br>
                                <strong>Fecha:</strong> ${fecha.toLocaleDateString('es-ES')}<br>
                                <strong>Día:</strong> ${dia.charAt(0).toUpperCase() + dia.slice(1)}
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="hora_entrada" class="form-label">Hora Entrada:</label>
                                    <input type="time" class="form-control" id="hora_entrada" step="1800" min="06:00" max="22:00" value="08:00" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="hora_salida" class="form-label">Hora Salida:</label>
                                    <input type="time" class="form-control" id="hora_salida" step="1800" min="06:00" max="22:00" value="17:00" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 mt-3">
                                <label for="comentario" class="form-label">Comentario (opcional):</label>
                                <textarea class="form-control" id="comentario" rows="2"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="estado" class="form-label">Estado:</label>
                                <select class="form-control" id="estado">
                                    <option value="Activo">Activo</option>
                                    <option value="Vacaciones">Vacaciones</option>
                                    <option value="Libre">Libre</option>
                                    <option value="Feriado">Feriado</option>
                                    <option value="Comp.Feriado">Comp. Feriado</option>
                                    <option value="Otra.Tienda">Otra Tienda</option>
                                    <option value="Finalizado">Contrato Finalizado</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-primary" onclick="guardarHorarioProgramado('${operario.id}', '${dia}')">
                                <i class="fas fa-check me-2"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const existingModal = document.getElementById('modalProgramarHorario');
        if (existingModal) {
            existingModal.remove();
        }
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = new bootstrap.Modal(document.getElementById('modalProgramarHorario'));
        modal.show();
    }
    
    function guardarHorarioProgramado(codOperario, dia) {
        const horaEntrada = document.getElementById('hora_entrada').value;
        const horaSalida = document.getElementById('hora_salida').value;
        const comentario = document.getElementById('comentario').value;
        const estado = document.getElementById('estado').value;
        
        if (!horaEntrada || !horaSalida) {
            alert('❌ Debe especificar hora de entrada y salida');
            return;
        }
        
        if (horaEntrada >= horaSalida) {
            alert('❌ La hora de entrada debe ser anterior a la hora de salida');
            return;
        }

        // Obtener datos de la semana y sucursal
        const semana = document.getElementById('semana').value;
        const sucursal = document.getElementById('sucursal').value;

        // Enviar datos al servidor
        const formData = new FormData();
        formData.append('cod_operario', codOperario);
        formData.append('id_semana', semana);
        formData.append('cod_sucursal', sucursal);
        formData.append('dia', dia);
        formData.append('hora_entrada', horaEntrada);
        formData.append('hora_salida', horaSalida);
        formData.append('comentario', comentario);
        formData.append('estado', estado);

        fetch('guardar_horario_calendario2.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalProgramarHorario')).hide();
                
                // Recargar el calendario después de un breve delay
                setTimeout(() => {
                    cargarCalendario();
                }, 1000);
            } else {
                mostrarNotificacion(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión', 'error');
        });
    }
    
    function actualizarHorarioPorArrastre(evento) {
        const props = evento.extendedProps;
        const startTime = evento.start.toTimeString().substring(0,5);
        const endTime = evento.end.toTimeString().substring(0,5);
        
        const formData = new FormData();
        formData.append('cod_operario', props.codOperario);
        formData.append('id_semana', document.getElementById('semana').value);
        formData.append('cod_sucursal', document.getElementById('sucursal').value);
        formData.append('dia', props.dia);
        formData.append('hora_entrada', startTime);
        formData.append('hora_salida', endTime);
        formData.append('comentario', props.comentario);
        formData.append('estado', 'Activo');

        fetch('guardar_horario_calendario2.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion('Horario actualizado correctamente', 'success');
            } else {
                mostrarNotificacion('Error al actualizar: ' + data.message, 'error');
                evento.revert();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión', 'error');
            evento.revert();
        });
    }
    
    function eliminarHorarioRapido(evento) {
        if (confirm('¿Está seguro que desea eliminar este horario?')) {
            const props = evento.extendedProps;

            const formData = new FormData();
            formData.append('cod_operario', props.codOperario);
            formData.append('id_semana', document.getElementById('semana').value);
            formData.append('cod_sucursal', document.getElementById('sucursal').value);
            formData.append('dia', props.dia);

            fetch('eliminar_horario_calendario2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    evento.remove();
                    mostrarNotificacion('Horario eliminado', 'success');
                } else {
                    mostrarNotificacion('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión', 'error');
            });
        }
    }
    
    function mostrarModalEditarHorario(evento) {
        const props = evento.extendedProps;
        const startTime = evento.start.toTimeString().substring(0,5);
        const endTime = evento.end.toTimeString().substring(0,5);
        
        const modalHtml = `
            <div class="modal fade" id="modalEditarHorario" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header" style="background: #51B8AC; color: white;">
                            <h5 class="modal-title">
                                <i class="fas fa-edit me-2"></i>
                                Editar Horario
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <strong>Colaborador:</strong> ${props.nombreCompleto}<br>
                                <strong>Día:</strong> ${props.dia}<br>
                                <strong>Estado:</strong> ${props.estado}
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="edit_hora_entrada" class="form-label">Hora Entrada:</label>
                                    <input type="time" class="form-control" id="edit_hora_entrada" value="${startTime}" step="1800" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_hora_salida" class="form-label">Hora Salida:</label>
                                    <input type="time" class="form-control" id="edit_hora_salida" value="${endTime}" step="1800" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 mt-3">
                                <label for="edit_comentario" class="form-label">Comentario:</label>
                                <textarea class="form-control" id="edit_comentario" rows="2">${props.comentario}</textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_estado" class="form-label">Estado:</label>
                                <select class="form-control" id="edit_estado">
                                    <option value="Activo" ${props.estado === 'Activo' ? 'selected' : ''}>Activo</option>
                                    <option value="Vacaciones" ${props.estado === 'Vacaciones' ? 'selected' : ''}>Vacaciones</option>
                                    <option value="Libre" ${props.estado === 'Libre' ? 'selected' : ''}>Libre</option>
                                    <option value="Feriado" ${props.estado === 'Feriado' ? 'selected' : ''}>Feriado</option>
                                    <option value="Comp.Feriado" ${props.estado === 'Comp.Feriado' ? 'selected' : ''}>Comp. Feriado</option>
                                    <option value="Otra.Tienda" ${props.estado === 'Otra.Tienda' ? 'selected' : ''}>Otra Tienda</option>
                                    <option value="Finalizado" ${props.estado === 'Finalizado' ? 'selected' : ''}>Contrato Finalizado</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" onclick="eliminarHorario('${evento.id}', '${props.codOperario}', '${props.dia}')">
                                <i class="fas fa-trash me-2"></i>Eliminar
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-primary" onclick="actualizarHorario('${evento.id}', '${props.codOperario}', '${props.dia}')">
                                <i class="fas fa-save me-2"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const existingModal = document.getElementById('modalEditarHorario');
        if (existingModal) {
            existingModal.remove();
        }
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = new bootstrap.Modal(document.getElementById('modalEditarHorario'));
        modal.show();
    }
    
    function actualizarHorario(eventoId, codOperario, dia) {
        const horaEntrada = document.getElementById('edit_hora_entrada').value;
        const horaSalida = document.getElementById('edit_hora_salida').value;
        const comentario = document.getElementById('edit_comentario').value;
        const estado = document.getElementById('edit_estado').value;
        
        if (!horaEntrada || !horaSalida) {
            alert('❌ Debe especificar hora de entrada y salida');
            return;
        }

        if (horaEntrada >= horaSalida) {
            alert('❌ La hora de entrada debe ser anterior a la hora de salida');
            return;
        }

        // Obtener datos de la semana y sucursal
        const semana = document.getElementById('semana').value;
        const sucursal = document.getElementById('sucursal').value;

        // Enviar datos al servidor
        const formData = new FormData();
        formData.append('cod_operario', codOperario);
        formData.append('id_semana', semana);
        formData.append('cod_sucursal', sucursal);
        formData.append('dia', dia);
        formData.append('hora_entrada', horaEntrada);
        formData.append('hora_salida', horaSalida);
        formData.append('comentario', comentario);
        formData.append('estado', estado);

        fetch('guardar_horario_calendario2.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('modalEditarHorario')).hide();
                
                // Recargar el calendario
                setTimeout(() => {
                    cargarCalendario();
                }, 1000);
            } else {
                mostrarNotificacion(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión', 'error');
        });
    }
    
    function eliminarHorario(eventoId, codOperario, dia) {
        if (confirm('¿Está seguro que desea eliminar este horario?')) {
            // Obtener datos de la semana y sucursal
            const semana = document.getElementById('semana').value;
            const sucursal = document.getElementById('sucursal').value;

            const formData = new FormData();
            formData.append('cod_operario', codOperario);
            formData.append('id_semana', semana);
            formData.append('cod_sucursal', sucursal);
            formData.append('dia', dia);

            fetch('eliminar_horario_calendario2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarNotificacion(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarHorario')).hide();
                    
                    // Recargar el calendario
                    setTimeout(() => {
                        cargarCalendario();
                    }, 1000);
                } else {
                    mostrarNotificacion(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión', 'error');
            });
        }
    }
    
    function agregarOperarioCalendario() {
        const select = document.getElementById('cod_operario_adicional');
        const codOperario = select.value;
        
        if (!codOperario) {
            alert('Por favor seleccione un colaborador');
            return;
        }

        const semana = document.getElementById('semana').value;
        const sucursal = document.getElementById('sucursal').value;

        const formData = new FormData();
        formData.append('cod_operario', codOperario);
        formData.append('semana', semana);
        formData.append('sucursal', sucursal);
        formData.append('agregar_operario', '1');

        fetch('agregar_operario_calendario.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarNotificacion(data.message, 'success');
                setTimeout(() => {
                    cargarCalendario();
                }, 1000);
            } else {
                mostrarNotificacion(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión', 'error');
        });
    }
    
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