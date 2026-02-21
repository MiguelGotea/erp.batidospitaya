<?php
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('campanas_wsp', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeCrear = tienePermiso('campanas_wsp', 'nueva_campana', $cargoOperario);
$puedeEliminar = tienePermiso('campanas_wsp', 'eliminar_campana', $cargoOperario);

require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campañas WhatsApp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/campanas_wsp.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Campañas WhatsApp'); ?>

            <div class="container-fluid p-3">

                <!-- ── Barra superior ── -->
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">

                    <!-- Estado del VPS -->
                    <div class="wsp-status-badge" id="vpsStatusBadge" onclick="verificarQR()">
                        <span class="wsp-dot" id="vpsDot"></span>
                        <span id="vpsStatusTexto">Verificando servicio...</span>
                    </div>

                    <!-- Botón nueva campaña -->
                    <?php if ($puedeCrear): ?>
                        <button class="btn btn-success" onclick="abrirModalNueva()">
                            <i class="bi bi-plus-circle me-1"></i> Nueva Campaña
                        </button>
                    <?php endif; ?>
                </div>

                <!-- ── Tabla de campañas ── -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header wsp-card-header">
                        <i class="bi bi-whatsapp me-2"></i> Campañas de Mensajería
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="tablaCampanas">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Fecha Envío</th>
                                        <th class="text-center">Destinatarios</th>
                                        <th class="text-center">Progreso</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center" style="width:100px">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpoTablaCampanas">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Cargando campañas...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div class="d-flex justify-content-between align-items-center mt-2 px-3 pb-3">
                            <div class="d-flex align-items-center gap-2">
                                <label class="mb-0 small">Mostrar:</label>
                                <select class="form-select form-select-sm" id="registrosPorPagina" style="width:auto"
                                    onchange="cargarCampanas(1)">
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span class="mb-0 small">registros</span>
                            </div>
                            <div id="paginacion"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════
         MODAL: Nueva Campaña (Wizard 3 pasos)
    ══════════════════════════════════════════ -->
    <div class="modal fade" id="modalNuevaCampana" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header wsp-modal-header">
                    <h5 class="modal-title text-white">
                        <i class="bi bi-whatsapp me-2"></i>
                        Nueva Campaña WhatsApp
                        <span class="badge bg-white text-dark ms-2" id="wizardStepBadge">Paso 1 de 3</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <!-- Indicador de pasos -->
                <div class="wsp-wizard-steps p-3">
                    <div class="wsp-step active" id="step-ind-1">
                        <div class="wsp-step-num">1</div>
                        <div class="wsp-step-label">Mensaje</div>
                    </div>
                    <div class="wsp-step-line"></div>
                    <div class="wsp-step" id="step-ind-2">
                        <div class="wsp-step-num">2</div>
                        <div class="wsp-step-label">Destinatarios</div>
                    </div>
                    <div class="wsp-step-line"></div>
                    <div class="wsp-step" id="step-ind-3">
                        <div class="wsp-step-num">3</div>
                        <div class="wsp-step-label">Programar</div>
                    </div>
                </div>

                <div class="modal-body">
                    <!-- PASO 1: Mensaje -->
                    <div id="wizardPaso1">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Nombre de la campaña *</label>
                                    <input type="text" class="form-control" id="campNombre"
                                        placeholder="Ej: Promo Febrero 2026">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Mensaje *
                                        <small class="text-muted fw-normal ms-1">
                                            Variables disponibles:
                                            <code onclick="insertarVariable('{{nombre}}')"
                                                class="wsp-var-chip">{{nombre}}</code>
                                            <code onclick="insertarVariable('{{sucursal}}')"
                                                class="wsp-var-chip">{{sucursal}}</code>
                                        </small>
                                    </label>
                                    <textarea class="form-control" id="campMensaje" rows="6"
                                        placeholder="Hola {{nombre}}, te informamos que..."
                                        oninput="actualizarPreview()"></textarea>
                                    <small class="text-muted"><span id="contadorCaracteres">0</span>/1000
                                        caracteres</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Imagen adjunta <small
                                            class="text-muted">(opcional, máx 5MB)</small></label>
                                    <input type="file" class="form-control" id="campImagen"
                                        accept="image/jpeg,image/png,image/webp" onchange="previsualizarImagen(this)">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Vista previa</label>
                                <div class="wsp-preview" id="previewMensaje">
                                    <div class="wsp-preview-header">
                                        <div class="wsp-preview-avatar">🧋</div>
                                        <div>
                                            <strong>Batidos Pitaya</strong><br>
                                            <small>en línea</small>
                                        </div>
                                    </div>
                                    <div class="wsp-preview-bubble" id="previewBubble">
                                        <em class="text-muted">El mensaje aparecerá aquí...</em>
                                    </div>
                                    <div id="previewImagenContainer" class="wsp-preview-img-container d-none">
                                        <img id="previewImagen" src="" alt="Imagen adjunta">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 2: Destinatarios -->
                    <div id="wizardPaso2" class="d-none">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Filtrar por sucursal</label>
                                <select class="form-select" id="filtroSucursal" onchange="buscarClientes()">
                                    <option value="">— Todas —</option>
                                </select>

                                <label class="form-label fw-bold mt-3">Buscar cliente</label>
                                <input type="text" class="form-control" id="buscarClienteInput"
                                    placeholder="Nombre o celular..." oninput="buscarClientes()">

                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-success w-100"
                                        onclick="agregarTodosVisibles()">
                                        <i class="bi bi-person-plus-fill me-1"></i>
                                        Agregar todos los resultados
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-2">* Solo clientes con celular registrado</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    Clientes disponibles
                                    <span class="badge bg-secondary" id="contDisponibles">0</span>
                                </label>
                                <div class="wsp-lista-clientes" id="listaDisponibles">
                                    <div class="text-center text-muted py-3">Usa el filtro para buscar clientes</div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">
                                    Seleccionados
                                    <span class="badge bg-success" id="contSeleccionados">0</span>
                                </label>
                                <div class="wsp-lista-clientes" id="listaSeleccionados">
                                    <div class="text-center text-muted py-3">Sin destinatarios aún</div>
                                </div>
                                <button class="btn btn-sm btn-outline-danger w-100 mt-2" onclick="limpiarSeleccion()">
                                    <i class="bi bi-x-circle me-1"></i> Limpiar selección
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- PASO 3: Programar envío -->
                    <div id="wizardPaso3" class="d-none">
                        <div class="row g-3 justify-content-center">
                            <div class="col-md-6">
                                <div class="card border-0 bg-light p-4">
                                    <h6 class="fw-bold mb-3">
                                        <i class="bi bi-calendar-check me-2 text-success"></i>
                                        Programar envío
                                    </h6>
                                    <label class="form-label">Fecha y hora de envío *</label>
                                    <input type="datetime-local" class="form-control mb-3" id="campFechaEnvio">
                                    <small class="text-muted">El servicio revisará cada 60s — el envío iniciará dentro
                                        del minuto programado.</small>

                                    <hr>
                                    <h6 class="fw-bold mt-2">Resumen de campaña</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th>Nombre:</th>
                                            <td id="resNombre">—</td>
                                        </tr>
                                        <tr>
                                            <th>Destinatarios:</th>
                                            <td id="resDestinatarios">0</td>
                                        </tr>
                                        <tr>
                                            <th>Tiene imagen:</th>
                                            <td id="resImagen">No</td>
                                        </tr>
                                        <tr>
                                            <th>Estado inicial:</th>
                                            <td><span class="badge bg-primary">Programada</span></td>
                                        </tr>
                                    </table>

                                    <div class="alert alert-warning py-2 small">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        <strong>Anti-ban:</strong> El servicio enviará con delays de 8–25s entre
                                        mensajes.
                                        Máximo 150 mensajes por día.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" id="btnAntes" onclick="pasoAnterior()" style="display:none">
                        <i class="bi bi-arrow-left me-1"></i> Anterior
                    </button>
                    <button class="btn btn-primary" id="btnSiguiente" onclick="pasoSiguiente()">
                        Siguiente <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                    <button class="btn btn-success d-none" id="btnGuardar" onclick="guardarCampana()">
                        <i class="bi bi-check-circle me-1"></i> Guardar y Programar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: QR WhatsApp -->
    <div class="modal fade" id="modalQR" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content text-center">
                <div class="modal-header wsp-modal-header">
                    <h5 class="modal-title text-white">
                        <i class="bi bi-qr-code me-2"></i> Escanear QR
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-muted small">Escanea desde WhatsApp → Dispositivos Vinculados</p>
                    <img id="qrImage" src="" alt="QR WhatsApp" style="width:100%;max-width:260px">
                    <div id="qrLoading" class="py-4">
                        <div class="spinner-border text-success"></div>
                        <p class="mt-2 small">Generando QR...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i> Guía de Campañas WhatsApp
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="bi bi-whatsapp me-2"></i> ¿Cómo funciona?
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Crea una campaña con un mensaje, selecciona clientes del Club Pitaya y programa
                                        la fecha de envío. El servicio en el VPS leerá la campaña y enviará los mensajes
                                        automáticamente.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="bi bi-shield-exclamation me-2"></i> Anti-Ban
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El sistema envía con delays de 8–25 segundos entre mensajes y un máximo de 150
                                        mensajes por día para evitar bloqueos de WhatsApp.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="bi bi-braces me-2"></i> Variables del mensaje
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Usa <code>{{nombre}}</code> y <code>{{sucursal}}</code> en el mensaje para
                                        personalizarlo. Se reemplazarán con el nombre y sucursal de cada cliente.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-danger border-bottom pb-2 fw-bold">
                                        <i class="bi bi-display me-2"></i> Estado del servicio
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El badge en la parte superior muestra si el VPS está conectado a WhatsApp. Si
                                        aparece "QR Pendiente", haz clic para escanear desde tu celular.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i> Horario de envío:</strong>
                        Los mensajes se envían entre 8:00 AM y 8:00 PM (hora Nicaragua). Las campañas programadas fuera
                        de ese horario esperarán hasta las 8am.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }
    </style>

    <script>
        // Variables PHP → JS
        const PUEDE_CREAR = <?php echo $puedeCrear ? 'true' : 'false'; ?>;
        const PUEDE_ELIMINAR = <?php echo $puedeEliminar ? 'true' : 'false'; ?>;
    </script>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/campanas_wsp.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>