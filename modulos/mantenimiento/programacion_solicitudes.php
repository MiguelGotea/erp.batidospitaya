<?php
// programacion_solicitudes.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Ticket.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/menu_lateral.php';
require_once '../../includes/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
// Obtener cargo del operario para el menú
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo usando sistema de permisos
if (!tienePermiso('calendario_solicitudes_mantenimiento', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$ticketModel = new Ticket();

// Usar CURDATE() para obtener la fecha actual
$sql_semanaactualhoy = "SELECT fecha, numero_semana 
               FROM FechasSistema 
               WHERE fecha = CURDATE()";
$resultadossemanaactualhoy = $db->fetchAll($sql_semanaactualhoy);
$semanaactualhoy = isset($resultadossemanaactualhoy[0]['numero_semana']) ? $resultadossemanaactualhoy[0]['numero_semana'] : null;

// Obtener semana actual (518 por defecto)
$semana_actual = isset($_GET['semana']) ? intval($_GET['semana']) : $semanaactualhoy;

// Obtener fechas de la semana desde FechasSistema
$sql_fechas = "SELECT CAST(fecha AS DATE) as fecha, numero_semana 
               FROM FechasSistema 
               WHERE numero_semana = ? 
               AND DAYOFWEEK(fecha) BETWEEN 2 AND 7
               ORDER BY fecha";
$fechas_semana = $db->fetchAll($sql_fechas, [$semana_actual]);

if (empty($fechas_semana)) {
    die("No se encontraron fechas para la semana $semana_actual");
}

// Extraer solo las fechas
$fechas = array_column($fechas_semana, 'fecha');
$fecha_inicio_semana = $fechas[0];
$fecha_fin_semana = end($fechas);

// Función para obtener color de urgencia
function getColorUrgencia($nivel)
{
    switch ($nivel) {
        case 1:
            return '#28a745';
        case 2:
            return '#ffc107';
        case 3:
            return '#fd7e14';
        case 4:
            return '#dc3545';
        default:
            return '#8b8b8bff';
    }
}

// Obtener cargos del área "Proyectos" para el modal
$sql_cargos_proyectos = "
    SELECT CodNivelesCargos, Nombre
    FROM NivelesCargos
    WHERE Area = 'Proyectos'
    ORDER BY Nombre
";
$cargos_proyectos = $db->fetchAll($sql_cargos_proyectos);

// Mapeo de nombres de filas a códigos de cargo
$mapeo_filas_cargos = [
    'Líder de Infraestructura y Expansión Comercial' => [35],
    'Jefe de Mantenimiento' => [14],
    'Conductor' => [20],
    'Líder de Infraestructura y Expansión Comercial + Jefe de Mantenimiento' => [35, 14],
    'Cambio de Equipos' => [35]
];

// Definir equipos de trabajo predefinidos basados en cargos
$equipos_trabajo = [
    'Cambio de Equipos',
    'Líder de Infraestructura y Expansión Comercial',
    'Jefe de Mantenimiento',
    'Conductor',
    'Líder de Infraestructura y Expansión Comercial + Jefe de Mantenimiento'
];

// Obtener tickets programados de la semana
$sql_tickets = "
    SELECT t.*, 
           s.nombre as nombre_sucursal,
           CAST(t.fecha_inicio AS DATE) as fecha_inicio,
           CAST(t.fecha_final AS DATE) as fecha_final,
           GROUP_CONCAT(DISTINCT tc.CodNivelesCargo ORDER BY tc.CodNivelesCargo SEPARATOR ',') as cargos_asignados
    FROM mtto_tickets t
    LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
    LEFT JOIN mtto_tickets_colaboradores tc ON t.id = tc.ticket_id
    WHERE t.fecha_inicio IS NOT NULL 
    AND t.fecha_final IS NOT NULL
    AND (
        (CAST(t.fecha_inicio AS DATE) BETWEEN ? AND ?)
        OR (CAST(t.fecha_final AS DATE) BETWEEN ? AND ?)
        OR (CAST(t.fecha_inicio AS DATE) <= ? AND CAST(t.fecha_final AS DATE) >= ?)
    )
    GROUP BY t.id
    ORDER BY s.nombre
";

$tickets_programados = $db->fetchAll($sql_tickets, [
    $fecha_inicio_semana,
    $fecha_fin_semana,
    $fecha_inicio_semana,
    $fecha_fin_semana,
    $fecha_inicio_semana,
    $fecha_fin_semana
]);

// Agrupar tickets por equipo de trabajo
$tickets_por_equipo = [];
foreach ($equipos_trabajo as $equipo) {
    $tickets_por_equipo[$equipo] = [];
}

foreach ($tickets_programados as $ticket) {
    // Determinar equipo según cargos asignados o tipo de formulario
    if ($ticket['tipo_formulario'] === 'cambio_equipos') {
        $equipo_key = 'Cambio de Equipos';
    } else {
        // Mapear cargos a nombre de equipo
        $cargos = !empty($ticket['cargos_asignados']) ? explode(',', $ticket['cargos_asignados']) : [];
        sort($cargos);
        $cargos_str = implode(',', $cargos);

        // Buscar en mapeo
        $equipo_key = 'Sin Equipo';
        foreach ($mapeo_filas_cargos as $nombre_equipo => $cargos_equipo) {
            sort($cargos_equipo);
            if (implode(',', $cargos_equipo) === $cargos_str) {
                $equipo_key = $nombre_equipo;
                break;
            }
        }

        // Si no existe el equipo, agregarlo
        if (!isset($tickets_por_equipo[$equipo_key])) {
            $equipos_trabajo[] = $equipo_key;
            $tickets_por_equipo[$equipo_key] = [];
        }
    }

    $tickets_por_equipo[$equipo_key][] = $ticket;
}

// Obtener tickets sin programar
$tickets_pendientes = $ticketModel->getTicketsWithoutDates();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programación de Solicitudes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/programacion_solicitudes.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <!-- Contenido principal -->
    <div class="main-container"> <!-- ya existe en el css de menu lateral -->
        <div class="sub-container"> <!-- Estructura estándar ERP -->
            <!-- todo el contenido existente -->
            <!-- Renderizar header universal -->
            <?php echo renderHeader($usuario, false, 'Programacion de Solicitudes'); ?>
            <div class="container-fluid p-3">
                <!-- Navegación de semanas -->
                <div class="d-flex justify-content-center align-items-center gap-3 mb-4">
                    <a href="?semana=<?php echo $semana_actual - 1; ?>" class="btn btn-nav-week">
                        <i class="bi bi-chevron-left"></i> Anterior
                    </a>
                    <div class="week-display">
                        <?php echo date('d/m/Y', strtotime($fecha_inicio_semana)); ?> -
                        <?php echo date('d/m/Y', strtotime($fecha_fin_semana)); ?>
                    </div>
                    <a href="?semana=<?php echo $semana_actual + 1; ?>" class="btn btn-nav-week">
                        Siguiente <i class="bi bi-chevron-right"></i>
                    </a>

                    <button class="btn btn-nav-week " onclick="toggleSidebar()">
                        <i class="bi bi-list-task"></i> Solicitudes Pendientes
                    </button>
                </div>

                <!-- Cronograma -->
                <div class="table-responsive">
                    <table class="table table-bordered calendar-table">
                        <thead style="background-color: #0E544C; color: white;">
                            <tr>
                                <th style="width: 150px;">Equipo de Trabajo</th>
                                <?php foreach ($fechas as $fecha):
                                    $dia_semana = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][date('N', strtotime($fecha))];
                                    ?>
                                    <th class="text-center">
                                        <?php echo $dia_semana; ?><br>
                                        <small>
                                            <?php echo date('d/m', strtotime($fecha)); ?>
                                        </small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipos_trabajo as $equipo): ?>
                                <tr data-equipo="<?php echo htmlspecialchars($equipo); ?>">
                                    <td class="equipo-label">
                                        <?php echo htmlspecialchars($equipo); ?>
                                    </td>
                                    <?php foreach ($fechas as $fecha): ?>
                                        <td class="calendar-cell" data-fecha="<?php echo $fecha; ?>"
                                            data-equipo-trabajo="<?php echo htmlspecialchars($equipo); ?>"
                                            ondragover="handleDragOver(event)" ondrop="handleDrop(event)">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sidebar de solicitudes pendientes -->
            <div id="sidebarPendientes" class="sidebar-pendientes">
                <div class="sidebar-header" style="background-color: #0E544C; color: white;">
                    <h5>Solicitudes Pendientes</h5>
                    <button class="btn btn-sm btn-close btn-close-white" onclick="toggleSidebar()"></button>
                </div>
                <div class="sidebar-body">
                    <!-- Filtro de sucursales -->
                    <div class="mb-3">
                        <select class="form-select form-select-sm" id="filtroSucursal" onchange="filtrarPendientes()">
                            <option value="">Todas las sucursales</option>
                            <?php
                            $sucursales = $ticketModel->getSucursales();
                            foreach ($sucursales as $suc):
                                ?>
                                <option value="<?php echo $suc['cod_sucursal']; ?>">
                                    <?php echo htmlspecialchars($suc['nombre_sucursal']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Pestañas -->
                    <ul class="nav nav-tabs mb-3" id="tabsPendientes" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-mantenimiento" data-bs-toggle="tab"
                                data-bs-target="#contenido-mantenimiento" type="button" role="tab">
                                Mantenimiento
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-cambio-equipo" data-bs-toggle="tab"
                                data-bs-target="#contenido-cambio-equipo" type="button" role="tab">
                                Cambio Equipo
                            </button>
                        </li>
                    </ul>

                    <!-- Contenido de pestañas -->
                    <div class="tab-content" id="contenidoTabsPendientes">
                        <!-- Pestaña Mantenimiento -->
                        <div class="tab-pane fade show active" id="contenido-mantenimiento" role="tabpanel">
                            <?php foreach ($tickets_pendientes as $t): ?>
                                <?php if ($t['tipo_formulario'] === 'mantenimiento_general'): ?>
                                    <div class="ticket-pendiente" draggable="true" data-ticket-id="<?php echo $t['id']; ?>"
                                        data-fecha-inicio="null" data-fecha-final="null"
                                        data-tipo-formulario="<?php echo $t['tipo_formulario']; ?>"
                                        data-sucursal="<?php echo $t['cod_sucursal']; ?>" ondragstart="handleDragStart(event)"
                                        onclick="mostrarDetallesTicket(<?php echo $t['id']; ?>)">

                                        <div class="ticket-content">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong class="ticket-titulo">
                                                    <?php echo htmlspecialchars($t['titulo']); ?>
                                                </strong>
                                                <?php if ($t['nivel_urgencia']): ?>
                                                    <span class="badge-urgencia ms-2"
                                                        style="background-color: <?php echo getColorUrgencia($t['nivel_urgencia']); ?>">
                                                        <?php echo $t['nivel_urgencia']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="ticket-descripcion d-block text-muted mb-1">
                                                <?php echo htmlspecialchars(substr($t['descripcion'], 0, 80)) . (strlen($t['descripcion']) > 80 ? '...' : ''); ?>
                                            </small>
                                            <span class="badge-sucursal">
                                                📍 <?php echo htmlspecialchars($t['nombre_sucursal']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pestaña Cambio Equipo -->
                        <div class="tab-pane fade" id="contenido-cambio-equipo" role="tabpanel">
                            <?php foreach ($tickets_pendientes as $t): ?>
                                <?php if ($t['tipo_formulario'] === 'cambio_equipos'): ?>
                                    <div class="ticket-pendiente" draggable="true" data-ticket-id="<?php echo $t['id']; ?>"
                                        data-fecha-inicio="null" data-fecha-final="null"
                                        data-tipo-formulario="<?php echo $t['tipo_formulario']; ?>"
                                        data-sucursal="<?php echo $t['cod_sucursal']; ?>" ondragstart="handleDragStart(event)"
                                        onclick="mostrarDetallesTicket(<?php echo $t['id']; ?>)">

                                        <div class="ticket-content">
                                            <strong class="ticket-titulo d-block mb-1">
                                                <?php echo htmlspecialchars($t['titulo']); ?>
                                            </strong>
                                            <small class="ticket-descripcion d-block text-muted mb-1">
                                                <?php echo htmlspecialchars(substr($t['descripcion'], 0, 80)) . (strlen($t['descripcion']) > 80 ? '...' : ''); ?>
                                            </small>
                                            <span class="badge-sucursal">
                                                📍 <?php echo htmlspecialchars($t['nombre_sucursal']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Colaboradores -->
    <div class="modal fade" id="modalColaboradores" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #0E544C; color: white;">
                    <h5 class="modal-title">Asignar Colaboradores</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ticketIdColaboradores">

                    <!-- Lista de colaboradores asignados -->
                    <div id="listaColaboradoresAsignados" class="mb-3"></div>

                    <!-- Agregar nuevo colaborador -->
                    <div class="card">
                        <div class="card-body">
                            <h6>Agregar Colaborador</h6>
                            <div class="row g-2">
                                <div class="col-10">
                                    <select class="form-select" id="selectCargo">
                                        <option value="">Seleccionar cargo...</option>
                                        <?php foreach ($cargos_proyectos as $cargo): ?>
                                            <option value="<?php echo $cargo['CodNivelesCargos']; ?>">
                                                <?php echo htmlspecialchars($cargo['Nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-2">
                                    <button class="btn btn-success w-100" onclick="agregarColaborador()">
                                        <i class="bi bi-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/programacion_solicitudes.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

    <script>
        // Datos de tickets para JavaScript
        const ticketsPorEquipo = <?php echo json_encode($tickets_por_equipo, JSON_UNESCAPED_UNICODE); ?>;
        const fechasSemana = <?php echo json_encode($fechas, JSON_UNESCAPED_UNICODE); ?>;

        // Renderizar tickets en el cronograma
        document.addEventListener('DOMContentLoaded', function () {
            renderizarCronograma();
        });
    </script>
</body>

</html>