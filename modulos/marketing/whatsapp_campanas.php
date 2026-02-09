<?php
/**
 * WhatsApp Marketing - Batidos Pitaya ERP
 * Gestión de campañas de WhatsApp y envío de mensajes
 * 
 * @author Sistema ERP Pitaya
 * @version 1.0
 */

// Includes obligatorios
require_once('../../core/auth/auth.php');
require_once('../../core/database/conexion.php');
require_once('../../core/helpers/funciones.php');
require_once('../../core/permissions/permissions.php');

// Obtener datos del usuario logueado
$codOperario = $_SESSION['usuario_id'];
$codNivelCargo = $_SESSION['cargo_cod'];

// Verificar permiso de vista
verificarPermisoORedireccionar('whatsapp_campanas', 'vista', $codNivelCargo);

// Obtener permisos del usuario para esta herramienta
$permisos = obtenerPermisosHerramienta('whatsapp_campanas', $codNivelCargo);
$puedeCrear = $permisos['crear'] ?? false;
$puedeEditar = $permisos['editar'] ?? false;
$puedeEliminar = $permisos['eliminar'] ?? false;
$puedeEnviar = $permisos['enviar'] ?? false;
$puedeConfigurar = $permisos['configurar'] ?? false;

// Obtener configuración del servidor WhatsApp
try {
    $stmtConfig = $conn->prepare("SELECT * FROM whatsapp_config ORDER BY id DESC LIMIT 1");
    $stmtConfig->execute();
    $configWA = $stmtConfig->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $configWA = null;
}

// Obtener plantillas activas
try {
    $stmtPlantillas = $conn->prepare("SELECT id, nombre, tipo FROM whatsapp_plantillas WHERE activa = 1 ORDER BY nombre");
    $stmtPlantillas->execute();
    $plantillas = $stmtPlantillas->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $plantillas = [];
}

// Obtener sucursales para filtros
try {
    $stmtSucursales = $conn->prepare("SELECT DISTINCT nombre_sucursal FROM clientesclub WHERE nombre_sucursal IS NOT NULL AND nombre_sucursal != '' ORDER BY nombre_sucursal");
    $stmtSucursales->execute();
    $sucursales = $stmtSucursales->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $sucursales = [];
}

// Título de la página para el header
$tituloPagina = "WhatsApp Marketing";
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $tituloPagina; ?> - Pitaya ERP</title>

    <!-- CSS Global -->
    <link rel="stylesheet" href="../../core/assets/css/global_tools.css">
    <!-- CSS Específico -->
    <link rel="stylesheet" href="css/whatsapp_campanas.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>

<body>
    <!-- Header Universal -->
    <?php
    $headerConfig = ['titulo' => $tituloPagina];
    require_once('../../core/layout/header_universal.php');
    ?>

    <!-- Menú Lateral -->
    <?php require_once('../../core/layout/menu_lateral.php');
    renderMenuLateral($codNivelCargo, 'whatsapp_campanas.php');
    ?>

    <!-- Contenedor Principal -->
    <div class="main-container">
        <div class="sub-container">

            <!-- Panel de Estado de Conexión -->
            <div class="wa-status-panel" id="statusPanel">
                <div class="wa-status-header">
                    <h3><i class="fab fa-whatsapp"></i> Estado de WhatsApp</h3>
                    <button type="button" class="btn-refresh" onclick="verificarEstado()" title="Actualizar estado">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="wa-status-body">
                    <div class="wa-status-indicator" id="statusIndicator">
                        <span class="status-dot disconnected"></span>
                        <span class="status-text">Verificando conexión...</span>
                    </div>
                    <div class="wa-status-stats" id="statusStats">
                        <div class="stat-item">
                            <span class="stat-label">Mensajes hoy:</span>
                            <span class="stat-value" id="statMensajesHoy">--</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">En cola:</span>
                            <span class="stat-value" id="statEnCola">--</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Límite hora:</span>
                            <span class="stat-value" id="statLimiteHora">--/50</span>
                        </div>
                    </div>
                    <div class="wa-qr-container" id="qrContainer" style="display: none;">
                        <p class="qr-instruction">Escanea el código QR con WhatsApp:</p>
                        <img id="qrImage" src="" alt="Código QR" class="qr-image">
                        <p class="qr-help">Abre WhatsApp → Menú → Dispositivos vinculados → Vincular dispositivo</p>
                    </div>
                </div>
                <?php if ($puedeConfigurar): ?>
                    <div class="wa-status-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="abrirConfiguracion()">
                            <i class="fas fa-cog"></i> Configurar
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="reiniciarConexion()">
                            <i class="fas fa-power-off"></i> Reiniciar
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pestañas de Navegación -->
            <ul class="nav nav-tabs wa-tabs" id="waTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="cumpleanos-tab" data-toggle="tab" href="#cumpleanos" role="tab">
                        <i class="fas fa-birthday-cake"></i> Cumpleaños
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="campanas-tab" data-toggle="tab" href="#campanas" role="tab">
                        <i class="fas fa-bullhorn"></i> Campañas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="plantillas-tab" data-toggle="tab" href="#plantillas" role="tab">
                        <i class="fas fa-file-alt"></i> Plantillas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="historial-tab" data-toggle="tab" href="#historial" role="tab">
                        <i class="fas fa-history"></i> Historial
                    </a>
                </li>
            </ul>

            <!-- Contenido de Pestañas -->
            <div class="tab-content wa-tab-content" id="waTabContent">

                <!-- TAB: Cumpleaños -->
                <div class="tab-pane fade show active" id="cumpleanos" role="tabpanel">
                    <div class="wa-section">
                        <div class="wa-section-header">
                            <h4>🎂 Cumpleañeros del Día</h4>
                            <div class="wa-section-actions">
                                <?php if ($puedeEnviar): ?>
                                    <button type="button" class="btn btn-success btn-enviar-todos"
                                        onclick="enviarCumpleanosHoy()">
                                        <i class="fab fa-whatsapp"></i> Enviar Felicitaciones del Día
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Filtros de cumpleaños -->
                        <div class="wa-filtros">
                            <div class="filtro-grupo">
                                <label>Ver:</label>
                                <select id="filtroPeriodoCumple" class="form-control form-control-sm"
                                    onchange="cargarCumpleanos()">
                                    <option value="hoy">Hoy</option>
                                    <option value="semana">Esta Semana</option>
                                    <option value="mes">Este Mes</option>
                                </select>
                            </div>
                            <div class="filtro-grupo">
                                <label>Sucursal:</label>
                                <select id="filtroSucursalCumple" class="form-control form-control-sm"
                                    onchange="cargarCumpleanos()">
                                    <option value="">Todas</option>
                                    <?php foreach ($sucursales as $suc): ?>
                                        <option value="<?php echo htmlspecialchars($suc); ?>">
                                            <?php echo htmlspecialchars($suc); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filtro-grupo">
                                <label>Estado:</label>
                                <select id="filtroEstadoCumple" class="form-control form-control-sm"
                                    onchange="cargarCumpleanos()">
                                    <option value="">Todos</option>
                                    <option value="pendiente">Sin enviar</option>
                                    <option value="enviado">Enviados</option>
                                </select>
                            </div>
                            <div class="filtro-info">
                                <span id="totalCumpleanos">0</span> cumpleañeros
                            </div>
                        </div>

                        <!-- Tabla de cumpleañeros -->
                        <div class="tabla-responsive">
                            <table class="tabla-wa" id="tablaCumpleanos">
                                <thead>
                                    <tr>
                                        <th class="col-check">
                                            <input type="checkbox" id="checkAllCumple" onchange="toggleAllCumple(this)">
                                        </th>
                                        <th>Nombre</th>
                                        <th>Teléfono</th>
                                        <th>Sucursal</th>
                                        <th>Fecha Nacimiento</th>
                                        <th>Edad</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyCumpleanos">
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <div class="loading-spinner">
                                                <i class="fas fa-spinner fa-spin"></i> Cargando...
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Selección de plantilla para envío -->
                        <div class="wa-envio-config" id="configEnvioCumple">
                            <div class="config-row">
                                <label>Plantilla de mensaje:</label>
                                <select id="plantillaCumple" class="form-control">
                                    <?php foreach ($plantillas as $pl): ?>
                                        <?php if ($pl['tipo'] == 'cumpleanos'): ?>
                                            <option value="<?php echo $pl['id']; ?>">
                                                <?php echo htmlspecialchars($pl['nombre']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline-info"
                                    onclick="previsualizarPlantilla('plantillaCumple')">
                                    <i class="fas fa-eye"></i> Vista previa
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Campañas -->
                <div class="tab-pane fade" id="campanas" role="tabpanel">
                    <div class="wa-section">
                        <div class="wa-section-header">
                            <h4>📢 Campañas de Marketing</h4>
                            <?php if ($puedeCrear): ?>
                                <button type="button" class="btn btn-success" onclick="abrirModalCampana()">
                                    <i class="fas fa-plus"></i> Nueva Campaña
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Filtros de campañas -->
                        <div class="wa-filtros">
                            <div class="filtro-grupo">
                                <label>Estado:</label>
                                <select id="filtroEstadoCampana" class="form-control form-control-sm"
                                    onchange="cargarCampanas()">
                                    <option value="">Todos</option>
                                    <option value="borrador">Borrador</option>
                                    <option value="programada">Programada</option>
                                    <option value="en_proceso">En Proceso</option>
                                    <option value="completada">Completada</option>
                                    <option value="pausada">Pausada</option>
                                    <option value="cancelada">Cancelada</option>
                                </select>
                            </div>
                            <div class="filtro-grupo">
                                <label>Tipo:</label>
                                <select id="filtroTipoCampana" class="form-control form-control-sm"
                                    onchange="cargarCampanas()">
                                    <option value="">Todos</option>
                                    <option value="cumpleanos">Cumpleaños</option>
                                    <option value="promocion">Promoción</option>
                                    <option value="masiva">Masiva</option>
                                    <option value="personalizada">Personalizada</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tabla de campañas -->
                        <div class="tabla-responsive">
                            <table class="tabla-wa" id="tablaCampanas">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Destinatarios</th>
                                        <th>Enviados</th>
                                        <th>Fallidos</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyCampanas">
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <div class="loading-spinner">
                                                <i class="fas fa-spinner fa-spin"></i> Cargando...
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB: Plantillas -->
                <div class="tab-pane fade" id="plantillas" role="tabpanel">
                    <div class="wa-section">
                        <div class="wa-section-header">
                            <h4>📝 Plantillas de Mensajes</h4>
                            <?php if ($puedeCrear): ?>
                                <button type="button" class="btn btn-success" onclick="abrirModalPlantilla()">
                                    <i class="fas fa-plus"></i> Nueva Plantilla
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="wa-plantillas-grid" id="gridPlantillas">
                            <!-- Se carga dinámicamente -->
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i> Cargando plantillas...
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB: Historial -->
                <div class="tab-pane fade" id="historial" role="tabpanel">
                    <div class="wa-section">
                        <div class="wa-section-header">
                            <h4>📋 Historial de Mensajes</h4>
                            <button type="button" class="btn btn-outline-secondary" onclick="exportarHistorial()">
                                <i class="fas fa-file-excel"></i> Exportar
                            </button>
                        </div>

                        <!-- Filtros de historial -->
                        <div class="wa-filtros">
                            <div class="filtro-grupo">
                                <label>Fecha:</label>
                                <input type="date" id="filtroFechaDesde" class="form-control form-control-sm"
                                    onchange="cargarHistorial()">
                                <span>a</span>
                                <input type="date" id="filtroFechaHasta" class="form-control form-control-sm"
                                    onchange="cargarHistorial()">
                            </div>
                            <div class="filtro-grupo">
                                <label>Estado:</label>
                                <select id="filtroEstadoHistorial" class="form-control form-control-sm"
                                    onchange="cargarHistorial()">
                                    <option value="">Todos</option>
                                    <option value="enviado">Enviado</option>
                                    <option value="entregado">Entregado</option>
                                    <option value="fallido">Fallido</option>
                                    <option value="pendiente">Pendiente</option>
                                </select>
                            </div>
                            <div class="filtro-grupo">
                                <label>Buscar:</label>
                                <input type="text" id="filtroTextoHistorial" class="form-control form-control-sm"
                                    placeholder="Nombre o teléfono..." onkeyup="debounceCargarHistorial()">
                            </div>
                        </div>

                        <!-- Tabla de historial -->
                        <div class="tabla-responsive">
                            <table class="tabla-wa" id="tablaHistorial">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Destinatario</th>
                                        <th>Teléfono</th>
                                        <th>Campaña</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyHistorial">
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <div class="loading-spinner">
                                                <i class="fas fa-spinner fa-spin"></i> Cargando...
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="wa-paginacion" id="paginacionHistorial">
                            <!-- Se genera dinámicamente -->
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Modal: Nueva/Editar Campaña -->
    <div class="modal fade" id="modalCampana" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCampanaTitle">Nueva Campaña</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formCampana">
                        <input type="hidden" id="campanaId" name="id" value="">

                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="campanaNombre">Nombre de la Campaña <span class="required">*</span></label>
                                <input type="text" class="form-control" id="campanaNombre" name="nombre" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="campanaTipo">Tipo <span class="required">*</span></label>
                                <select class="form-control" id="campanaTipo" name="tipo" required>
                                    <option value="promocion">Promoción</option>
                                    <option value="masiva">Masiva</option>
                                    <option value="personalizada">Personalizada</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="campanaPlantilla">Plantilla de Mensaje</label>
                                <select class="form-control" id="campanaPlantilla" name="plantilla_id">
                                    <option value="">-- Seleccionar plantilla --</option>
                                    <?php foreach ($plantillas as $pl): ?>
                                        <option value="<?php echo $pl['id']; ?>">
                                            <?php echo htmlspecialchars($pl['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="campanaFecha">Fecha Programada</label>
                                <input type="datetime-local" class="form-control" id="campanaFecha"
                                    name="fecha_programada">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="campanaImagen">URL de Imagen (opcional)</label>
                            <input type="url" class="form-control" id="campanaImagen" name="imagen_url"
                                placeholder="https://ejemplo.com/imagen.jpg">
                        </div>

                        <hr>
                        <h6>Selección de Destinatarios</h6>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="campanaSegmento">Segmento</label>
                                <select class="form-control" id="campanaSegmento" name="segmento"
                                    onchange="actualizarConteoDestinatarios()">
                                    <option value="todos">Todos los miembros</option>
                                    <option value="sucursal">Por sucursal</option>
                                    <option value="activos">Clientes activos (últimos 60 días)</option>
                                    <option value="inactivos">Clientes inactivos (+60 días)</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4" id="grupSucursal" style="display:none;">
                                <label for="campanaSucursal">Sucursal</label>
                                <select class="form-control" id="campanaSucursal" name="sucursal"
                                    onchange="actualizarConteoDestinatarios()">
                                    <option value="">-- Seleccionar --</option>
                                    <?php foreach ($sucursales as $suc): ?>
                                        <option value="<?php echo htmlspecialchars($suc); ?>">
                                            <?php echo htmlspecialchars($suc); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Total Destinatarios</label>
                                <div class="destinatarios-count" id="conteoDestinatarios">
                                    <i class="fas fa-users"></i> <span>0</span> clientes
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCampana()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <?php if ($puedeEnviar): ?>
                        <button type="button" class="btn btn-success" onclick="guardarYEnviarCampana()">
                            <i class="fab fa-whatsapp"></i> Guardar y Enviar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Nueva/Editar Plantilla -->
    <div class="modal fade" id="modalPlantilla" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPlantillaTitle">Nueva Plantilla</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formPlantilla">
                        <input type="hidden" id="plantillaId" name="id" value="">

                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="plantillaNombre">Nombre <span class="required">*</span></label>
                                <input type="text" class="form-control" id="plantillaNombre" name="nombre" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="plantillaTipo">Tipo <span class="required">*</span></label>
                                <select class="form-control" id="plantillaTipo" name="tipo" required>
                                    <option value="cumpleanos">Cumpleaños</option>
                                    <option value="promocion">Promoción</option>
                                    <option value="bienvenida">Bienvenida</option>
                                    <option value="reactivacion">Reactivación</option>
                                    <option value="personalizada">Personalizada</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="plantillaMensaje">Mensaje <span class="required">*</span></label>
                            <textarea class="form-control" id="plantillaMensaje" name="mensaje" rows="6" required
                                placeholder="Escribe tu mensaje aquí..."></textarea>
                            <small class="form-text text-muted">
                                Variables disponibles: <code>{nombre}</code>, <code>{apellido}</code>,
                                <code>{sucursal}</code>, <code>{puntos}</code>
                            </small>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label for="plantillaImagen">URL de Imagen (opcional)</label>
                                <input type="url" class="form-control" id="plantillaImagen" name="imagen_url"
                                    placeholder="https://ejemplo.com/imagen.jpg">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="plantillaActiva">Estado</label>
                                <select class="form-control" id="plantillaActiva" name="activa">
                                    <option value="1">Activa</option>
                                    <option value="0">Inactiva</option>
                                </select>
                            </div>
                        </div>

                        <!-- Vista previa -->
                        <div class="plantilla-preview">
                            <h6>Vista Previa:</h6>
                            <div class="preview-phone">
                                <div class="preview-message" id="previewMensaje">
                                    El mensaje aparecerá aquí...
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarPlantilla()">
                        <i class="fas fa-save"></i> Guardar Plantilla
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Configuración del Servidor -->
    <div class="modal fade" id="modalConfig" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cog"></i> Configuración del Servidor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formConfig">
                        <div class="form-group">
                            <label for="configUrl">URL del Servidor VPS</label>
                            <input type="url" class="form-control" id="configUrl" name="servidor_url"
                                value="<?php echo htmlspecialchars($configWA['servidor_url'] ?? ''); ?>"
                                placeholder="http://IP:3000">
                        </div>
                        <div class="form-group">
                            <label for="configToken">Token de Autenticación</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="configToken" name="servidor_token"
                                    value="<?php echo htmlspecialchars($configWA['servidor_token'] ?? ''); ?>">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary"
                                        onclick="togglePasswordVisibility('configToken')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Estos datos los encuentras en el archivo <code>.env</code> de tu servidor VPS.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-info" onclick="probarConexion()">
                        <i class="fas fa-plug"></i> Probar Conexión
                    </button>
                    <button type="button" class="btn btn-primary" onclick="guardarConfiguracion()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Ver Mensaje -->
    <div class="modal fade" id="modalVerMensaje" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle del Mensaje</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="contenidoMensaje">
                    <!-- Se carga dinámicamente -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda Universal -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> Ayuda - WhatsApp Marketing</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="help-section">
                        <h6><i class="fas fa-birthday-cake"></i> Cumpleaños</h6>
                        <p>Envía felicitaciones automáticas a los clientes en su día especial. El sistema detecta
                            automáticamente los cumpleañeros del día/semana/mes.</p>
                        <ul>
                            <li><strong>Envío individual:</strong> Clic en el botón de WhatsApp en cada fila</li>
                            <li><strong>Envío masivo:</strong> Selecciona varios clientes y usa "Enviar Felicitaciones"
                            </li>
                        </ul>
                    </div>

                    <div class="help-section">
                        <h6><i class="fas fa-bullhorn"></i> Campañas</h6>
                        <p>Crea campañas de marketing para enviar mensajes a segmentos específicos de clientes.</p>
                        <ul>
                            <li><strong>Promociones:</strong> Para ofertas especiales</li>
                            <li><strong>Masivas:</strong> Para comunicados generales</li>
                            <li><strong>Personalizadas:</strong> Segmentadas por sucursal o actividad</li>
                        </ul>
                    </div>

                    <div class="help-section">
                        <h6><i class="fas fa-file-alt"></i> Plantillas</h6>
                        <p>Crea y administra plantillas de mensajes reutilizables.</p>
                        <p><strong>Variables disponibles:</strong></p>
                        <ul>
                            <li><code>{nombre}</code> - Nombre del cliente</li>
                            <li><code>{apellido}</code> - Apellido del cliente</li>
                            <li><code>{sucursal}</code> - Sucursal preferida</li>
                            <li><code>{puntos}</code> - Puntos acumulados</li>
                        </ul>
                    </div>

                    <div class="help-section">
                        <h6><i class="fas fa-shield-alt"></i> Límites Anti-Baneo</h6>
                        <p>Para proteger el número de WhatsApp, el sistema aplica estos límites:</p>
                        <ul>
                            <li>Máximo 50 mensajes por hora</li>
                            <li>Máximo 200 mensajes por día</li>
                            <li>Delay de 30-90 segundos entre mensajes</li>
                            <li>Horario: 8:00 AM - 8:00 PM</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Variables PHP para JavaScript -->
    <script>
        const CONFIG = {
            puedeCrear: <?php echo $puedeCrear ? 'true' : 'false'; ?>,
            puedeEditar: <?php echo $puedeEditar ? 'true' : 'false'; ?>,
            puedeEliminar: <?php echo $puedeEliminar ? 'true' : 'false'; ?>,
            puedeEnviar: <?php echo $puedeEnviar ? 'true' : 'false'; ?>,
            puedeConfigurar: <?php echo $puedeConfigurar ? 'true' : 'false'; ?>,
            ajaxBase: 'ajax/'
        };
    </script>

    <!-- JavaScript específico -->
    <script src="js/whatsapp_campanas.js"></script>
</body>

</html>