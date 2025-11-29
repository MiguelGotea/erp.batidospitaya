<?php
// programacion_solicitudes.php
$version = "1.0.22"; // Incrementa cuando hagas cambios

require_once '/config/database.php';
require_once '/models/Ticket.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
// Incluir el header universal
require_once '../../includes/header_universal.php';

//verificarAutenticacion();   //no usar 

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

//verificarAccesoModulo('operaciones');  //no usar
//verificarAccesoCargo([11, 16]);  //no usar

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo(35, 16) && !$esAdmin) {
    header('Location: ../index.php');
    exit();
}

$ticketModel = new Ticket();

// Obtener semana actual (518 por defecto)
$semana_actual = isset($_GET['semana']) ? intval($_GET['semana']) : 518;

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
function getColorUrgencia($nivel) {
    switch($nivel) {
        case 1: return '#28a745';
        case 2: return '#ffc107';
        case 3: return '#fd7e14';
        case 4: return '#dc3545';
        default: return '#8b8b8bff';
    }
}

// Obtener equipos de trabajo únicos históricos
$sql_equipos = "
    SELECT DISTINCT tipo_usuario
    FROM mtto_tickets_colaboradores
    WHERE tipo_usuario IS NOT NULL
    ORDER BY tipo_usuario
";
$tipos_disponibles = $db->fetchAll($sql_equipos);

// Construir equipos de trabajo (combinaciones únicas)
$sql_combinaciones = "
    SELECT ticket_id, GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR ' + ') as equipo
    FROM mtto_tickets_colaboradores
    WHERE tipo_usuario IS NOT NULL
    GROUP BY ticket_id
";
$combinaciones = $db->fetchAll($sql_combinaciones);

$equipos_trabajo = ['Cambio de Equipos']; // Siempre incluir este grupo
$equipos_unicos = [];

foreach ($combinaciones as $comb) {
    if (!empty($comb['equipo']) && !in_array($comb['equipo'], $equipos_unicos)) {
        $equipos_unicos[] = $comb['equipo'];
    }
}

// Eliminar duplicados cuando hay múltiples del mismo tipo (ej: "Jefe + Jefe" → "Jefe")
$equipos_normalizados = [];
foreach ($equipos_unicos as $equipo) {
    $tipos = explode(' + ', $equipo);
    $tipos_unicos = array_unique($tipos);
    sort($tipos_unicos);
    $equipo_normalizado = implode(' + ', $tipos_unicos);
    
    if (!in_array($equipo_normalizado, $equipos_normalizados)) {
        $equipos_normalizados[] = $equipo_normalizado;
    }
}

sort($equipos_normalizados);
$equipos_trabajo = array_merge($equipos_trabajo, $equipos_normalizados);

// Obtener tickets programados de la semana
$sql_tickets = "
    SELECT t.*, 
           s.nombre as nombre_sucursal,
           CAST(t.fecha_inicio AS DATE) as fecha_inicio,
           CAST(t.fecha_final AS DATE) as fecha_final,
           GROUP_CONCAT(DISTINCT tc.tipo_usuario ORDER BY tc.tipo_usuario SEPARATOR ' + ') as equipo_trabajo
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
    $fecha_inicio_semana, $fecha_fin_semana,
    $fecha_inicio_semana, $fecha_fin_semana,
    $fecha_inicio_semana, $fecha_fin_semana
]);

// Agrupar tickets por equipo de trabajo
$tickets_por_equipo = [];
foreach ($equipos_trabajo as $equipo) {
    $tickets_por_equipo[$equipo] = [];
}

foreach ($tickets_programados as $ticket) {
    // Determinar equipo
    if ($ticket['tipo_formulario'] === 'cambio_equipos') {
        $equipo_key = 'Cambio de Equipos';
    } else {
        // Normalizar equipo
        $tipos = !empty($ticket['equipo_trabajo']) ? explode(' + ', $ticket['equipo_trabajo']) : [];
        $tipos_unicos = array_unique($tipos);
        sort($tipos_unicos);
        $equipo_key = implode(' + ', $tipos_unicos);
        
        if (empty($equipo_key)) {
            $equipo_key = 'Sin Equipo';
            if (!in_array($equipo_key, $equipos_trabajo)) {
                $equipos_trabajo[] = $equipo_key;
                $tickets_por_equipo[$equipo_key] = [];
            }
        }
    }
    
    if (!isset($tickets_por_equipo[$equipo_key])) {
        $tickets_por_equipo[$equipo_key] = [];
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/programacion_solicitudes.css?v=<?php echo $version; ?>">
</head>
<body>
    <div class="container-fluid p-3">
        <!-- Header -->
        <!-- Renderizar header universal -->
        <?php echo renderHeader($usuario, $esAdmin, '[Anexar titulo de pagina]'); ?>

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
                                <small><?php echo date('d/m', strtotime($fecha)); ?></small>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipos_trabajo as $equipo): ?>
                        <tr data-equipo="<?php echo htmlspecialchars($equipo); ?>">
                            <td class="equipo-label"><?php echo htmlspecialchars($equipo); ?></td>
                            <?php foreach ($fechas as $fecha): ?>
                                <td class="calendar-cell" 
                                    data-fecha="<?php echo $fecha; ?>"
                                    data-equipo-trabajo="<?php echo htmlspecialchars($equipo); ?>"
                                    ondragover="handleDragOver(event)"
                                    ondrop="handleDrop(event)">
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

            <!-- Lista de tickets pendientes -->
            <div id="listaPendientes">
                <?php foreach ($tickets_pendientes as $t): ?>
                    <div class="ticket-pendiente" 
                         draggable="true"
                         data-ticket-id="<?php echo $t['id']; ?>"
                         data-fecha-inicio="null"
                         data-fecha-final="null"
                         data-tipo-formulario="<?php echo $t['tipo_formulario']; ?>"
                         data-sucursal="<?php echo $t['cod_sucursal']; ?>"
                         ondragstart="handleDragStart(event)"
                         onclick="mostrarDetallesTicket(<?php echo $t['id']; ?>)">
                        
                        <div class="ticket-content">
                            <strong class="ticket-titulo"><?php echo htmlspecialchars($t['titulo']); ?></strong>
                            <small class="ticket-sucursal d-block text-muted">
                                <?php echo htmlspecialchars($t['nombre_sucursal']); ?>
                            </small>
                            <small class="badge-tipo">
                                <?php echo $t['tipo_formulario'] === 'cambio_equipos' ? 'Cambio Equipo' : 'Mantenimiento'; ?>
                            </small>
                        </div>
                        
                        <?php if ($t['nivel_urgencia']): ?>
                            <span class="badge-urgencia" 
                                  style="background-color: <?php echo getColorUrgencia($t['nivel_urgencia']); ?>">
                                <?php echo $t['nivel_urgencia']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/programacion_solicitudes.js?v=<?php echo $version; ?>"></script>
    
    <script>
    // Datos de tickets para JavaScript
    const ticketsPorEquipo = <?php echo json_encode($tickets_por_equipo, JSON_UNESCAPED_UNICODE); ?>;
    const fechasSemana = <?php echo json_encode($fechas, JSON_UNESCAPED_UNICODE); ?>;
    
    // Renderizar tickets en el cronograma
    document.addEventListener('DOMContentLoaded', function() {
        renderizarCronograma();
    });
    </script>
</body>
</html>