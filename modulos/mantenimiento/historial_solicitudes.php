<?php
// historial_solicitudes.php
$version = "1.0.17";

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Ticket.php';
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
// Incluir el menú lateral
require_once '../../includes/menu_lateral.php';
// Incluir el header universal
require_once '../../includes/header_universal.php';

$usuario = obtenerUsuarioActual();
// Obtener cargo del operario para el menú
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

$sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
$codigo_sucursal_busqueda=$sucursales[0]['nombre'];

// Verificar acceso al módulo (cargos con permiso para ver marcaciones)
if (!verificarAccesoCargo([35, 16, 5, 11, 2]) && !$esAdmin) {
    header('Location: ../index.php');
    exit();
}


$ticketModel = new Ticket();

// Determinar si el filtro de sucursal está bloqueado
$filtro_sucursal_bloqueado = ($cargoOperario == 5);

// Obtener sucursales
$sucursales = $ticketModel->getSucursales();

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

// Función para obtener color de estado
function getColorEstado($estado) {
    switch($estado) {
        case 'solicitado': return '#6c757d';
        case 'clasificado': return '#17a2b8';
        case 'agendado': return '#ffc107';
        case 'finalizado': return '#28a745';
        default: return '#6c757d';
    }
}

// Función para obtener texto de urgencia
function getTextoUrgencia($nivel) {
    switch($nivel) {
        case 1: return 'No Urgente';
        case 2: return 'Medio';
        case 3: return 'Urgente';
        case 4: return 'Crítico';
        default: return 'No Clasificado';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Solicitudes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/historial_solicitudes.css?v=<?php echo $version; ?>">
</head>
<body>

    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <!-- Contenido principal -->
    <div class="main-container">   <!-- ya existe en el css de menu lateral -->
        <div class="contenedor-principal"> <!-- ya existe en el css de menu lateral -->
            <!-- todo el contenido existente -->
            <?php echo renderHeader($usuario, $esAdmin, 'Historial de Solicitudes'); ?>
            <div class="container-fluid p-3">
                <!-- Header -->

                <!-- Tabla de solicitudes -->
                <div class="table-responsive">
                    <table class="table table-hover historial-table" id="tablaSolicitudes">
                        <thead>
                            <tr>
                                <th data-column="created_at" data-type="date">
                                    Solicitado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="titulo" data-type="text">
                                    Título
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="descripcion" data-type="text">
                                    Descripción
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nombre_sucursal" data-type="list">
                                    Sucursal
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="tipo_formulario" data-type="list">
                                    Tipo
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="nivel_urgencia" data-type="urgency">
                                    Urgencia
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="status" data-type="list">
                                    Estado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="fecha_inicio" data-type="date">
                                    Agendado
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th style="width: 80px;">Foto</th>
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
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;" onchange="cambiarRegistrosPorPagina()">
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
                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselFotos" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carouselFotos" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const filtroSucursalBloqueado = <?php echo $filtro_sucursal_bloqueado ? 'true' : 'false'; ?>;
        const codigoSucursalBusqueda = '<?php echo $codigo_sucursal_busqueda; ?>';
        const cargoOperario = <?php echo $cargoOperario; ?>;
        
        // Aplicar filtro de sucursal automáticamente si está bloqueado
        $(document).ready(function() {
            if (filtroSucursalBloqueado && codigoSucursalBusqueda) {
                // Esperar a que el JS se cargue
                setTimeout(function() {
                    if (typeof filtrosActivos !== 'undefined') {
                        filtrosActivos['nombre_sucursal'] = [codigoSucursalBusqueda];
                        cargarDatos();
                    }
                }, 100);
            }
        });
    </script>
    <script src="js/historial_solicitudes.js?v=<?php echo $version; ?>"></script>
</body>
</html>