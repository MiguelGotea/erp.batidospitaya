<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Ticket.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
// Obtener cargo del operario para el menú
$cargoOperario = $usuario['CodNivelesCargos'];

$sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
$codigo_sucursal_busqueda = $sucursales[0]['nombre'];


if (!tienePermiso('historial_solicitudes_mantenimiento', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$ticketModel = new Ticket();

// Determinar si el filtro de sucursal está bloqueado (solo líderes ven su sucursal)
$filtro_sucursal_bloqueado = !tienePermiso('historial_solicitudes_mantenimiento', 'vista_todas_sucursales', $cargoOperario);

// Obtener sucursales
$sucursales = $ticketModel->getSucursales();

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

// Función para obtener color de estado
function getColorEstado($estado)
{
    switch ($estado) {
        case 'solicitado':
            return '#6c757d';
        case 'clasificado':
            return '#17a2b8';
        case 'agendado':
            return '#ffc107';
        case 'finalizado':
            return '#28a745';
        case 'cancelado':
            return '#dc3545';
        default:
            return '#6c757d';
    }
}

// Función para obtener texto de urgencia
function getTextoUrgencia($nivel)
{
    switch ($nivel) {
        case 1:
            return 'No Urgente';
        case 2:
            return 'Medio';
        case 3:
            return 'Urgente';
        case 4:
            return 'Crítico';
        default:
            return 'No Clasificado';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Solicitudes</title>

    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="css/historial_solicitudes.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>

    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <!-- Contenido principal -->
    <div class="main-container"> <!-- ya existe en el css de menu lateral -->
        <div class="sub-container"> <!-- Estructura estándar ERP -->
            <!-- todo el contenido existente -->
            <?php echo renderHeader($usuario, false, 'Historial de Solicitudes'); ?>
            <div class="container-fluid p-3">
                <!-- Header -->

                <!-- Panel de Acceso Rápido -->
                <?php if (tienePermiso('historial_solicitudes_mantenimiento', 'vista_todas_sucursales', $cargoOperario)): ?>
                    <div class="quick-access-wrapper">
                        <div class="quick-access-title">
                            <i class="bi bi-lightning-charge-fill"></i>
                            Accesos Rápidos por Sucursal
                        </div>
                        <div class="quick-access-chips" id="quickAccessChips">
                            <div class="branch-chip clear-filters" onclick="limpiarFiltrosAccesoRapido()">
                                <i class="bi bi-trash"></i> Limpiar Filtros
                            </div>
                            <?php foreach ($sucursales as $suc): ?>
                                <?php 
                                    // Si el filtro de sucursal está bloqueado, solo mostrar la sucursal del usuario
                                    if ($filtro_sucursal_bloqueado && $suc['nombre_sucursal'] !== $codigo_sucursal_busqueda) continue;
                                ?>
                                <div class="branch-chip" data-sucursal="<?php echo $suc['nombre_sucursal']; ?>" 
                                     onclick="aplicarAccesoRapido('<?php echo $suc['nombre_sucursal']; ?>', this)">
                                    <i class="bi bi-shop"></i>
                                    <?php echo $suc['nombre_sucursal']; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Botón Informe Global Excel -->
                        <div class="quick-access-export-row">
                            <button id="btnInformeGlobal" class="btn-informe-global" onclick="descargarInformeGlobal()" title="Descargar informe Excel con los filtros activos">
                                <i class="bi bi-file-earmark-excel-fill"></i>
                                <span>Informe Global</span>
                            </button>
                            <span class="informe-global-hint">
                                <i class="bi bi-info-circle"></i> Exporta todos los registros con los filtros aplicados actualmente
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tabla de solicitudes -->
                <div class="table-responsive">
                    <table class="table table-hover historial-table" id="tablaSolicitudes">
                        <thead>
                            <tr>
                                <th data-column="created_at" data-type="daterange">
                                    Solicitado
                                    <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="titulo" data-type="text">
                                    Título
                                    <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="descripcion" data-type="text">
                                    Descripción
                                    <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="resolucion">
                                    Resolución
                                </th>
                                <th data-column="nombre_sucursal" data-type="list">
                                    Sucursal
                                    <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="tipo_formulario" data-type="list">
                                    Tipo
                                    <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nivel_urgencia" data-type="urgency">
                                    Urgencia
                                    <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="tiempo_estimado" data-type="text" style="width: 100px;">
                                    Tiempo
                                    <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="status" data-type="list">
                                    Estado
                                    <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_inicio" data-type="daterange">
                                    Agendado
                                    <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 50px;">Foto</th>
                                <th style="width: 70px;">IA</th>
                            </tr>
                        </thead>
                        <tbody id="tablaSolicitudesBody">
                            <!-- Datos cargados vía AJAX -->
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Mostrar:</label>
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;"
                            onchange="cambiarRegistrosPorPagina()">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="mb-0">registros</span>
                    </div>
                    <div id="paginacion"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para fotos -->
    <div class="modal fade" id="modalFotos" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #0E544C; color: white;">
                    <h5 class="modal-title">Fotos de la Solicitud</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="carouselFotos" class="carousel slide">
                        <div class="carousel-inner" id="carouselFotosInner">
                            <!-- Fotos cargadas vía AJAX -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselFotos"
                            data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carouselFotos"
                            data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón Flotante con opciones (Solo si tiene permiso de nuevo_registro) -->
    <?php if (tienePermiso('historial_solicitudes_mantenimiento', 'nuevo_registro', $cargoOperario)): ?>
        <div class="fab-container">
            <div class="fab-options">
                <a href="formulario_equipos.php" class="fab-option">
                    <span class="fab-label">Mtto de Equipo</span>
                    <div class="fab-icon-holder"><i class="fas fa-laptop"></i></div>
                </a>
                <a href="formulario_mantenimiento.php" class="fab-option">
                    <span class="fab-label">Mtto de Area</span>
                    <div class="fab-icon-holder"><i class="fas fa-tools"></i></div>
                </a>
            </div>
            <div class="btn-floating-pitaya" title="Nueva Solicitud">
                <i class="fas fa-plus"></i>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const filtroSucursalBloqueado = <?php echo $filtro_sucursal_bloqueado ? 'true' : 'false'; ?>;
        const codigoSucursalBusqueda = '<?php echo $codigo_sucursal_busqueda; ?>';
        const cargoOperario = <?php echo $cargoOperario; ?>;
        const permisos = {
            'cambiar_urgencia': <?php echo tienePermiso('historial_solicitudes_mantenimiento', 'cambiar_urgencia', $cargoOperario) ? 'true' : 'false'; ?>,
            'super_edicion': <?php echo tienePermiso('historial_solicitudes_mantenimiento', 'super_edicion', $cargoOperario) ? 'true' : 'false'; ?>,
            'editar_resolucion': <?php echo tienePermiso('historial_solicitudes_mantenimiento', 'editar_resolucion', $cargoOperario) ? 'true' : 'false'; ?>,
            'consulta_ia': <?php echo tienePermiso('historial_solicitudes_mantenimiento', 'consulta_ia', $cargoOperario) ? 'true' : 'false'; ?>
        };

        function tienepermiso(accion) {
            return permisos[accion] === true;
        }

        // Aplicar filtro de sucursal automáticamente si está bloqueado
        $(document).ready(function () {
            if (filtroSucursalBloqueado && codigoSucursalBusqueda) {
                // Esperar a que el JS se cargue
                setTimeout(function () {
                    if (typeof filtrosActivos !== 'undefined') {
                        filtrosActivos['nombre_sucursal'] = [codigoSucursalBusqueda];
                        cargarDatos();
                    }
                }, 100);
            }
        });
    </script>
    <script src="js/historial_solicitudes.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>