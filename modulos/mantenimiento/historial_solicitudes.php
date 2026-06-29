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

if (!tienePermiso('historial_solicitudes_mantenimiento', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$ticketModel = new Ticket();

// Obtener sucursales del líder ANTES del override
$sucursalesLiderPropias = obtenerSucursalesLider($_SESSION['usuario_id']);

// Determinar cuántas sucursales activas tiene como líder
$esLiderMultiSucursal = count($sucursalesLiderPropias) > 1;

// Para el filtro AJAX: si tiene 1, bloquear en esa; si tiene varias, no bloquear
if (!empty($sucursalesLiderPropias)) {
    // Nombre de la primera sucursal para compatibilidad con el filtro por defecto
    $codigo_sucursal_busqueda = $sucursalesLiderPropias[0]['nombre'];
} else {
    $codigo_sucursal_busqueda = '';
}

// Determinar si el filtro de sucursal está bloqueado (solo líderes sin permiso especial)
$filtro_sucursal_bloqueado = !tienePermiso('historial_solicitudes_mantenimiento', 'vista_todas_sucursales', $cargoOperario);

// Para líderes con múltiples sucursales: el filtro NO debe quedar bloqueado a solo 1
// sino permitir ver todas las que administra
$mostrarSelectorSucursal = $filtro_sucursal_bloqueado && count($sucursalesLiderPropias) > 1;

// Obtener sucursales (todas para el panel de acceso rápido con permisos)
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
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/historial_solicitudes.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <!-- Library for HEIC support -->
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
</head>

<body>

    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>

    <!-- Contenido principal -->
    <div class="main-container"> <!-- ya existe en el css de menu lateral -->
        <div class="sub-container"> <!-- Estructura estándar ERP -->
            <!-- todo el contenido existente -->
            <?php echo renderHeader($usuario, 'Historial de Solicitudes'); ?>
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
                                <?php if (tienePermiso('historial_solicitudes_mantenimiento', 'consulta_ia', $cargoOperario)): ?>
                                    <th style="width: 50px;">IA</th>
                                <?php endif; ?>
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
            <div class="btn-floating-pitaya" title="Herramientas">
                <i class="fas fa-wrench"></i>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const filtroSucursalBloqueado = <?php echo $filtro_sucursal_bloqueado ? 'true' : 'false'; ?>;
        const codigoSucursalBusqueda = '<?php echo $codigo_sucursal_busqueda; ?>';
        const sucursalesLiderPropias = <?php echo json_encode(array_column($sucursalesLiderPropias ?? [], 'nombre')); ?>;
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
            if (filtroSucursalBloqueado) {
                // Esperar a que el JS se cargue
                setTimeout(function () {
                    if (typeof filtrosActivos !== 'undefined') {
                        if (Array.isArray(sucursalesLiderPropias) && sucursalesLiderPropias.length > 0) {
                            filtrosActivos['nombre_sucursal'] = [...sucursalesLiderPropias];
                        } else if (codigoSucursalBusqueda) {
                            filtrosActivos['nombre_sucursal'] = [codigoSucursalBusqueda];
                        }
                        cargarDatos();
                    }
                }, 100);
            }
        });
    </script>

    <!-- Modal Registrar Trabajo Realizado (Finalizar Ticket) -->
    <div class="modal fade" id="finalizarTicketModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-tools me-2"></i>Registrar Trabajo Realizado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="stopCamera()"></button>
                </div>
                <div class="modal-body p-4 pt-3">
                    <form id="formFinalizarTicket">
                        <input type="hidden" name="ticket_id" id="finalizar_ticket_id">
                        <input type="hidden" name="status" value="finalizado">
                        
                        <div class="mb-3">
                            <label class="form-label required">📋 Describe el trabajo realizado</label>
                            <textarea name="trabajo_realizado" id="finalizar_trabajo_realizado" class="form-control" rows="4"
                                placeholder="Ej: Se reparó la llave del lavamanos del área de producción. Se cambió empaque y ajustaron conexiones." required></textarea>
                        </div>
                        
                        <div class="mb-0">
                            <label class="form-label required">📷 Fotos de Evidencia (mín. 1 foto)</label>
                            <div class="foto-upload-group mb-2">
                                <button type="button" class="btn btn-outline-primary"
                                    onclick="document.getElementById('finalizar_evidencia_input').click()">
                                    <i class="fas fa-image me-1"></i>Galería
                                </button>
                                <button type="button" class="btn btn-outline-success"
                                    onclick="startCamera('finalizar_evidencia')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" id="finalizar_evidencia_input" multiple accept="image/*" class="d-none"
                                onchange="previewEvidencia(this)">
                            <div id="finalizar_evidencia_previews" class="row g-2 mt-2"></div>

                            <div id="finalizar_evidencia_container"
                                class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black"
                                style="max-width: 420px; margin: 0 auto; cursor: crosshair;">
                                <video id="finalizar_evidencia_video" autoplay playsinline class="w-100" style="display:block;"></video>
                                <div class="ag-cam-grid"></div>
                                <div id="finalizar_evidencia_ring" class="ag-focus-ring"></div>
                                <div id="finalizar_evidencia_toast" class="ag-focus-toast">Toca para enfocar</div>
                                <div class="ag-cam-controls d-flex align-items-center justify-content-between px-3 py-2">
                                    <button type="button" id="finalizar_evidencia_torch" class="ag-btn-torch" style="display:none;"
                                        onclick="toggleCameraTorch('finalizar_evidencia')" title="Linterna">
                                        <i class="fas fa-bolt"></i>
                                    </button>
                                    <button type="button" class="ag-btn-capture"
                                        onclick="captureSnapshot('finalizar_evidencia')" title="Tomar foto">
                                        <i class="fas fa-circle" style="color:#e74c3c;"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill text-white border-secondary"
                                        onclick="stopCamera()">
                                        <i class="fas fa-times me-1"></i>Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal" onclick="stopCamera()">Cancelar</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="guardarFinalizacionDirecta()">Guardar Registro</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* ── Cámara Premium (Historial Solicitudes) ── */
        .ag-cam-grid {
            position: absolute; inset: 0; pointer-events: none;
            opacity: 0.15;
            background-image:
                linear-gradient(to right, #fff 1px, transparent 1px),
                linear-gradient(to bottom, #fff 1px, transparent 1px);
            background-size: 33.33% 33.33%;
        }
        .ag-focus-ring {
            position: absolute;
            width: 70px; height: 70px;
            border: 2px solid #FFD700;
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(1.6);
            opacity: 0; pointer-events: none;
            transition: transform 0.25s ease, opacity 0.25s ease;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.4);
        }
        .ag-focus-ring.focus-active {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
        .ag-focus-ring.focus-locked { border-color: #00FF88; opacity: 0.7; }
        .ag-focus-ring::before, .ag-focus-ring::after {
            content: ''; position: absolute;
            width: 10px; height: 10px;
            border-color: inherit; border-style: solid;
        }
        .ag-focus-ring::before { top: -1px; left: -1px; border-width: 2px 0 0 2px; }
        .ag-focus-ring::after  { bottom: -1px; right: -1px; border-width: 0 2px 2px 0; }
        .ag-focus-toast {
            position: absolute; bottom: 58px; left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.65); color: #fff;
            font-size: 0.72rem; padding: 3px 12px;
            border-radius: 20px; opacity: 0;
            transition: opacity 0.3s; pointer-events: none;
            white-space: nowrap;
        }
        .ag-cam-controls {
            background: #111; padding: 8px 14px 12px;
        }
        .ag-btn-torch {
            background: transparent; border: 1.5px solid #555;
            color: #aaa; border-radius: 50%;
            width: 42px; height: 42px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; transition: all 0.2s; cursor: pointer;
        }
        .ag-btn-torch.on {
            border-color: #FFD700; color: #FFD700;
            box-shadow: 0 0 8px rgba(255,215,0,0.5);
        }
        .ag-btn-capture {
            width: 60px; height: 60px; border-radius: 50%;
            background: #fff; border: 4px solid rgba(255,255,255,0.4);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: #333;
            transition: transform 0.1s, background 0.1s;
            cursor: pointer;
        }
        .ag-btn-capture:active { transform: scale(0.92); background: #ddd; }
    </style>

    <script src="js/historial_solicitudes.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
    <!-- FAB Draggable: permite mover el botón flotante libremente en el viewport -->
    <script src="/core/assets/js/fab_button.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>