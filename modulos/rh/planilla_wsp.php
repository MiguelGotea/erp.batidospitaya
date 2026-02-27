<?php
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('envio_wsp_planilla', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeCrear = tienePermiso('envio_wsp_planilla', 'nueva_programacion', $cargoOperario);
$puedeEliminar = tienePermiso('envio_wsp_planilla', 'eliminar_programacion', $cargoOperario);
$puedeResetSesion = tienePermiso('envio_wsp_planilla', 'resetear_sesion', $cargoOperario);

require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones WhatsApp — Planilla</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/planilla_wsp.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Notificaciones WhatsApp — Planilla'); ?>

            <div class="container-fluid p-3">

                <!-- ── Barra superior ── -->
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">

                    <!-- Badge estado VPS -->
                    <div class="wsp-status-badge" id="vpsStatusBadge" onclick="verificarQR()">
                        <span class="wsp-dot" id="vpsDot"></span>
                        <span id="vpsStatusTexto">Verificando servicio...</span>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-2">
                        <?php if ($puedeResetSesion): ?>
                            <button class="btn btn-outline-warning" onclick="confirmarResetSesion()"
                                title="Cambiar número de WhatsApp vinculado a planilla">
                                <i class="bi bi-arrow-repeat me-1"></i> Cambiar Número
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary btn-sm" onclick="cargarPlanillas()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                        </button>
                    </div>
                </div>

                <!-- ── Tabla de planillas ── -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header wsp-card-header py-2 d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-text text-white"></i>
                        <span class="text-white fw-bold">Planillas Disponibles</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="tablaPlanillas">
                                <thead>
                                    <tr class="wsp-card-header">
                                        <th style="width:200px">Fecha de Planilla</th>
                                        <th class="text-center" style="width:110px">Boletas</th>
                                        <th class="text-center" style="width:130px">Estado WSP</th>
                                        <th>Fecha Envío Prog.</th>
                                        <th style="width:180px">Progreso</th>
                                        <th class="text-center" style="width:100px">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="cuerpoTablaPlanillas">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Cargando planillas...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info small mt-3 py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Cómo funciona:</strong> Haz clic en cualquier fila para ver los colaboradores detectados.
                    Luego usa
                    <i class="bi bi-send-plus"></i> para programar el envío de la notificación de planilla.
                    Las variables <code>{{nombre}}</code> y <code>{{fecha_planilla}}</code> se reemplazan
                    automáticamente por los datos de cada colaborador.
                </div>

            </div><!-- /container-fluid -->
        </div>
    </div>

    <!-- ══════════════════════════════════════════
         MODAL: Programar / Editar envío
    ══════════════════════════════════════════ -->
    <div class="modal fade" id="modalProgramar" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header wsp-modal-header">
                    <h5 class="modal-title text-white">
                        <i class="bi bi-whatsapp me-2"></i>
                        <span id="modalTitulo">Programar envío</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Col izquierda: formulario -->
                        <div class="col-md-7">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Mensaje *
                                    <small class="text-muted fw-normal ms-1">
                                        Variables:
                                        <code onclick="insertarVariable('{{nombre}}')"
                                            class="wsp-var-chip">{{nombre}}</code>
                                        <code onclick="insertarVariable('{{fecha_planilla}}')"
                                            class="wsp-var-chip">{{fecha_planilla}}</code>
                                    </small>
                                </label>
                                <textarea class="form-control" id="campMensaje" rows="7"
                                    placeholder="Hola {{nombre}}, te informamos que ya puedes consultar tu planilla del {{fecha_planilla}} en el portal."
                                    oninput="actualizarPreview()"></textarea>
                                <small class="text-muted"><span id="contadorCaracteres">0</span>/1000 caracteres</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Imagen adjunta <small class="text-muted">(opcional,
                                        máx 5MB)</small></label>
                                <input type="file" class="form-control" id="campImagen"
                                    accept="image/jpeg,image/png,image/webp" onchange="previsualizarImagen(this)">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Fecha y hora de envío *</label>
                                <input type="datetime-local" class="form-control" id="campFechaEnvio">
                                <small class="text-muted">El servicio revisará cada 60s — el envío inicia dentro del
                                    minuto programado.</small>
                            </div>
                        </div>

                        <!-- Col derecha: preview + resumen -->
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Vista previa</label>
                            <div class="wsp-preview" id="previewMensaje">
                                <div class="wsp-preview-header">
                                    <div class="wsp-preview-avatar">🧋</div>
                                    <div>
                                        <strong>Batidos Pitaya</strong><br>
                                        <small>Planilla</small>
                                    </div>
                                </div>
                                <div class="wsp-preview-bubble" id="previewBubble">
                                    <em class="text-muted">El mensaje aparecerá aquí...</em>
                                </div>
                                <div id="previewImagenContainer" class="wsp-preview-img-container d-none">
                                    <img id="previewImagen" src="" alt="Imagen adjunta">
                                </div>
                            </div>

                            <!-- Resumen -->
                            <div class="card border-0 bg-light mt-3 p-3">
                                <h6 class="fw-bold mb-2"><i class="bi bi-people me-1"></i>Resumen</h6>
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <th>Fecha planilla:</th>
                                        <td id="resumenFecha">—</td>
                                    </tr>
                                    <tr>
                                        <th>Destinatarios:</th>
                                        <td id="resumenDestinatarios">—</td>
                                    </tr>
                                    <tr>
                                        <th>Estado inicial:</th>
                                        <td><span class="badge badge-programada">Programada</span></td>
                                    </tr>
                                </table>
                                <div class="alert alert-warning py-2 small mt-2 mb-0">
                                    <i class="bi bi-shield-check me-1"></i>
                                    <strong>Anti-ban:</strong> Delays 8–25s entre mensajes. Horario: 7AM–8PM.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-success" onclick="guardarProgramacion()">
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
                        <i class="bi bi-qr-code me-2"></i> Escanear QR — Planilla
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-muted small">Escanea desde WhatsApp → Dispositivos Vinculados</p>
                    <img id="qrImage" src="" alt="QR WhatsApp" style="width:100%;max-width:260px;display:none">
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
                        <i class="fas fa-info-circle me-2"></i> Guía — Notificaciones WSP de Planilla
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                                        El sistema detecta automáticamente todas las fechas de planilla con boletas
                                        emitidas.
                                        Solo debes configurar el mensaje y la fecha/hora de envío para cada planilla.
                                        El VPS (wsp-planilla) enviará los mensajes automáticamente.
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
                                        Usa <code>{{nombre}}</code> para el nombre del colaborador y
                                        <code>{{fecha_planilla}}</code> para la fecha de la planilla (ej: 15-Feb-2026).
                                        Se reemplazan individualmente para cada destinatario.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="bi bi-telephone me-2"></i> Teléfonos
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Se usa el <strong>teléfono corporativo</strong> del colaborador. Si no tiene, se
                                        usa el celular personal.
                                        Colaboradores sin ningún teléfono registrado <strong>no recibirán el
                                            mensaje</strong>.
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
                                        El badge superior muestra el estado del servidor VPS (wsp-planilla).
                                        Si aparece "QR Pendiente", haz clic en el badge para escanear el código desde tu
                                        celular.
                                        Es un número <strong>separado</strong> del número de campañas de marketing.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small mt-2">
                        <strong><i class="fas fa-info-circle me-1"></i> Horario de envío:</strong>
                        Los mensajes se envían automáticamente entre <strong>7:00 AM y 8:00 PM</strong> (Hora
                        Nicaragua).
                        Si programa un envío fuera de ese horario, se ejecutará al inicio del siguiente período
                        permitido.
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
    <script src="js/planilla_wsp.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>