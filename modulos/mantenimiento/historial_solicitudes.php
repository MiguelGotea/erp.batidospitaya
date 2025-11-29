<?php
// historial_solicitudes.php
$version = "1.0.2";

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Ticket.php';

$ticketModel = new Ticket();

// Variables de filtro (completar manualmente)
$codigo_sucursal_busqueda = 12; // Ej: 'SUC001'
$cargoOperario = 5; // 5 = filtrado por sucursal, otro = sin filtro

// Determinar si se filtra por sucursal
$filtrar_sucursal = ($cargoOperario == 5 && !empty($codigo_sucursal_busqueda));

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

// Función para obtener color de status
function getColorStatus($status) {
    switch($status) {
        case 'solicitado': return '#17a2b8';
        case 'clasificado': return '#ffc107';
        case 'agendado': return '#28a745';
        case 'finalizado': return '#6c757d';
        default: return '#6c757d';
    }
}

// Obtener sucursales para filtro
$sucursales = $ticketModel->getSucursales();
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
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Historial de Solicitudes</h4>
            <div class="d-flex gap-2 align-items-center">
                <label class="text-white mb-0">Registros por página:</label>
                <select id="registrosPorPagina" class="form-select form-select-sm" style="width: auto;">
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <!-- Filtros superiores -->
        <div class="filtros-container mb-3">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Filtro de Sucursal</label>
                    <select id="filtroSucursalGlobal" class="form-select" <?php echo $filtrar_sucursal ? 'disabled' : ''; ?>>
                        <option value="">Todas las sucursales</option>
                        <?php foreach ($sucursales as $suc): ?>
                            <option value="<?php echo $suc['cod_sucursal']; ?>" 
                                <?php echo ($filtrar_sucursal && $suc['cod_sucursal'] == $codigo_sucursal_busqueda) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($suc['nombre_sucursal']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-hover tabla-solicitudes" id="tablaSolicitudes">
                <thead>
                    <tr>
                        <th>
                            Solicitado
                            <button class="btn-filtro" onclick="abrirFiltro('created_at', 'Solicitado')">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </th>
                        <th>
                            Título
                            <button class="btn-filtro" onclick="abrirFiltro('titulo', 'Título')">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </th>
                        <th>
                            Descripción
                            <button class="btn-filtro" onclick="abrirFiltro('descripcion', 'Descripción')">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </th>
                        <th>
                            Sucursal
                            <button class="btn-filtro" onclick="abrirFiltro('nombre_sucursal', 'Sucursal')">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </th>
                        <th>
                            Tipo
                            <button class="btn-filtro" onclick="abrirFiltro('tipo_formulario', 'Tipo')">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </th>
                        <th>
                            Urgencia
                            <button class="btn-filtro" onclick="abrirFiltro('nivel_urgencia', 'Urgencia')">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </th>
                        <th>
                            Estado
                            <button class="btn-filtro" onclick="abrirFiltro('status', 'Estado')">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </th>
                        <th>
                            Agendado
                            <button class="btn-filtro" onclick="abrirFiltro('fecha_inicio', 'Agendado')">
                                <i class="bi bi-funnel"></i>
                            </button>
                        </th>
                        <th>Fotos</th>
                    </tr>
                </thead>
                <tbody id="tablaSolicitudesBody">
                    <!-- Contenido dinámico -->
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div id="infoPaginacion">Mostrando 0 de 0 registros</div>
            <nav>
                <ul class="pagination mb-0" id="paginacion">
                    <!-- Botones de paginación dinámicos -->
                </ul>
            </nav>
        </div>
    </div>

    <!-- Modal de Filtro -->
    <div class="modal fade" id="modalFiltro" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="modalFiltroTitulo">Filtro</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label fw-bold">Ordenar</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="aplicarOrden('ASC')">
                                <i class="bi bi-sort-alpha-down"></i> ASC
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="aplicarOrden('DESC')">
                                <i class="bi bi-sort-alpha-up"></i> DESC
                            </button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold">Buscar</label>
                        <input type="text" class="form-control form-control-sm" id="inputBuscarFiltro" 
                               placeholder="Ingrese texto..." onkeyup="filtrarOpciones()">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold">Opciones</label>
                        <div id="listaOpciones" class="lista-opciones">
                            <!-- Opciones dinámicas -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="limpiarFiltro()">Limpiar</button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal" 
                            style="background-color: #51B8AC; border: none;">Aplicar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Fotos -->
    <div class="modal fade" id="modalFotos" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fotos de la Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="carouselFotos" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner" id="carouselFotosInner">
                            <!-- Fotos dinámicas -->
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselFotos" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carouselFotos" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const FILTRAR_SUCURSAL = <?php echo $filtrar_sucursal ? 'true' : 'false'; ?>;
        const CODIGO_SUCURSAL_BUSQUEDA = '<?php echo $codigo_sucursal_busqueda; ?>';
    </script>
    <script src="js/historial_solicitudes.js?v=<?php echo $version; ?>"></script>
</body>
</html>