<?php
// agenda_colaborador.php

require_once 'models/Ticket.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo
if (!tienePermiso('agenda_mantenimiento', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$ticketModel = new Ticket();
$fechaHoy = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$esHoy = ($fechaHoy === date('Y-m-d'));

// Verificar si tiene permiso para ver todos los colaboradores
$puedeVerTodosColaboradores = tienePermiso('agenda_mantenimiento', 'todos_colaboradores', $cargoOperario);

if ($puedeVerTodosColaboradores) {
    $colaboradoresDisponibles = $ticketModel->getColaboradoresAsignados();
    $colaborador_filtro = isset($_GET['colaborador']) ? intval($_GET['colaborador']) : $usuario['CodOperario'];
} else {
    $colaborador_filtro = $usuario['CodOperario'];
    $colaboradoresDisponibles = [];
}

// Obtener informe del día para el colaborador seleccionado
$informeActual = null;
if ($colaborador_filtro) {
    $informeActual = $ticketModel->getInformeDiarioPorFecha($colaborador_filtro, $fechaHoy);
    if ($informeActual) {
        $informeActual = $ticketModel->getDetalleInformeCompleto($informeActual['id']);
    }
}

// Obtener sucursales para el selector de visitas
$sucursales = $ticketModel->getSucursales();

// Obtener tickets pendientes (solicitados/agendados) del colaborador para el panel lateral
$ticketsPendientes = [];
if ($colaborador_filtro) {
    $todosTickets = $ticketModel->getTicketsPorColaborador($colaborador_filtro, "2016-01-01");
    foreach ($todosTickets as $t) {
        if ($t['status'] === 'agendado' || $t['status'] === 'solicitado') {
            $ticketsPendientes[] = $t;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda e Informe Diario</title>

    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/agenda_colaborador.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="../../core/assets/css/modales_premium.css">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Informe Diario de Mantenimiento'); ?>

            <div class="container-fluid p-3">
                <div class="container">
                    
                    <div class="mb-4"></div>

                    <!-- PANEL DE CONTROL DEL INFORME -->
                        <div class="report-status-card mb-4 p-4 rounded-4 shadow-sm border-0 
                            <?= !$informeActual ? 'bg-light' : ($informeActual['estado'] === 'creado' ? 'bg-primary bg-opacity-10' : 'bg-success bg-opacity-10') ?>">
                            
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <h4 class="mb-1">
                                        <i class="fas fa-clipboard-list me-2"></i>
                                        Informe de <?= htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?> - <?= date('d/m/Y') ?>
                                    </h4>
                                    <p class="mb-0 text-muted">
                                        <?php if (!$informeActual): ?>
                                            <span class="badge bg-secondary">Sin Iniciar</span>
                                            Debe abrir su informe registrando el kilometraje inicial.
                                        <?php elseif ($informeActual['estado'] === 'creado'): ?>
                                            <span class="badge bg-primary">En Transcurso (Abierto)</span>
                                            Puede registrar visitas, compras y tareas.
                                        <?php else: ?>
                                            <span class="badge bg-success">Informe Finalizado</span>
                                            El informe está cerrado y no admite ediciones.
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="d-flex gap-2">
                                    <?php if (!$informeActual && $colaborador_filtro == $usuario['CodOperario']): ?>
                                        <button class="btn btn-primary px-4 rounded-pill" onclick="modalApertura()">
                                            <i class="fas fa-play me-2"></i>Iniciar Informe
                                        </button>
                                    <?php elseif ($informeActual && $informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                        <button class="btn btn-outline-danger px-4 rounded-pill" onclick="modalCierre(<?= $informeActual['id'] ?>)">
                                            <i class="fas fa-stop me-2"></i>Finalizar Informe
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($informeActual): ?>
                                        <a href="imprimir_informe.php?id=<?= $informeActual['id'] ?>" target="_blank" class="btn btn-dark px-4 rounded-pill">
                                            <i class="fas fa-print me-2"></i>Imprimir Reporte
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($informeActual): ?>
                                <hr class="my-3 opacity-10">
                                <div class="row g-4 text-center">
                                    <div class="col-6 col-md-3 border-end">
                                        <small class="text-muted d-block">KM Inicial</small>
                                        <span class="fw-bold"><?= number_format($informeActual['km_inicial'], 2) ?></span>
                                    </div>
                                    <div class="col-6 col-md-3 border-end">
                                        <small class="text-muted d-block">Caja Chica</small>
                                        <span class="fw-bold fs-5 text-success">C$<?= number_format($informeActual['monto_caja_chica'], 2) ?></span>
                                    </div>
                                    <div class="col-6 col-md-3 border-end">
                                        <small class="text-muted d-block">Sucursales Visitas</small>
                                        <span class="fw-bold"><?= count($informeActual['visitas']) ?></span>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Tareas Hechas</small>
                                        <?php 
                                            $totalT = 0; 
                                            foreach($informeActual['visitas'] as $v) $totalT += count($v['tareas']); 
                                        ?>
                                        <span class="fw-bold"><?= $totalT ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- LISTA DE VISITAS/PROGRESO -->
                        <?php if ($informeActual): ?>
                            <div class="row">
                                <div class="col-lg-8">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5><i class="fas fa-map-marker-alt text-danger me-2"></i>Mis Visitas del Día</h5>
                                        <?php if ($informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                            <button class="btn btn-sm btn-primary rounded-pill" onclick="modalNuevaVisita(<?= $informeActual['id'] ?>)">
                                                <i class="fas fa-plus me-1"></i>Agregar Sucursal
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <div class="visitas-timeline">
                                        <?php if (empty($informeActual['visitas'])): ?>
                                            <div class="alert alert-light border text-center py-4 rounded-4">
                                                <i class="fas fa-truck-loading fa-2x text-muted mb-2"></i>
                                                <p class="text-muted mb-0">Aún no has registrado visitas a sucursales hoy.</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($informeActual['visitas'] as $v): ?>
                                                <div class="visita-item card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
                                                    <div class="card-header bg-white border-bottom-0 pt-3 px-4 d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-0 text-primary fw-bold"><?= htmlspecialchars($v['nombre_sucursal']) ?></h6>
                                                            <small class="text-muted">
                                                                <i class="far fa-clock me-1"></i> 
                                                                Arribo: <?= date('h:i A', strtotime($v['hora_llegada'])) ?>
                                                                <?php if ($v['hora_salida']): ?>
                                                                    - Salida: <?= date('h:i A', strtotime($v['hora_salida'])) ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <?php if ($informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="modalNuevaTarea(<?= $v['id'] ?>, '<?= $v['cod_sucursal'] ?>')">
                                                                    <i class="fas fa-tools me-1"></i>Tarea
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="modalNuevaCompra(<?= $v['id'] ?>)">
                                                                    <i class="fas fa-file-invoice-dollar me-1"></i>Factura
                                                                </button>
                                                                <button class="btn btn-link btn-sm text-danger p-0 ms-1" onclick="eliminarVisita(<?= $v['id'] ?>)" title="Eliminar Visita">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="card-body px-4 pb-3">
                                                        <!-- Tareas -->
                                                        <div class="visita-tareas mb-3">
                                                            <?php foreach ($v['tareas'] as $tarea): ?>
                                                                <div class="p-2 border-bottom d-flex align-items-center gap-3">
                                                                    <span class="badge bg-<?= $tarea['completado_100'] ? 'success' : 'warning text-dark' ?> rounded-pill">
                                                                        <?= $tarea['completado_100'] ? '100% Hecho' : 'Parcial' ?>
                                                                    </span>
                                                                    <div class="flex-grow-1">
                                                                        <small class="text-dark fw-bold d-block"><?= htmlspecialchars($tarea['titulo']) ?></small>
                                                                        <small class="text-muted text-truncate d-block" style="max-width: 300px;"><?= htmlspecialchars($tarea['trabajo_realizado']) ?></small>
                                                                    </div>
                                                                    <div class="d-flex gap-1 align-items-center">
                                                                        <?php foreach ($tarea['fotos'] as $f): ?>
                                                                            <img src="uploads/evidencias/<?= $f['foto'] ?>" class="rounded-1" style="width: 30px; height: 30px; object-fit: cover; cursor: zoom-in;" onclick="zoomFoto(this.src)">
                                                                        <?php endforeach; ?>
                                                                        <?php if ($informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                                                            <button class="btn btn-link btn-sm text-danger p-1 ms-1" onclick="eliminarTarea(<?= $tarea['id'] ?>)" title="Eliminar Tarea">
                                                                                <i class="fas fa-trash-alt"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>

                                                        <!-- Compras -->
                                                        <?php if (!empty($v['compras'])): ?>
                                                            <div class="visita-compras p-2 bg-light rounded-3 mb-2">
                                                                <small class="text-muted fw-bold d-block mb-1">FACTURAS/COMPRAS:</small>
                                                                <?php foreach ($v['compras'] as $c): ?>
                                                                    <div class="d-flex justify-content-between align-items-center small py-1 border-bottom border-white">
                                                                        <span><i class="fas fa-file-invoice me-1"></i> <?= htmlspecialchars($c['detalle']) ?></span>
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span class="fw-bold">C$<?= number_format($c['monto'], 2) ?></span>
                                                                            <img src="uploads/compras/<?= $c['foto_factura'] ?>" class="rounded-1" style="width: 25px; height: 25px; object-fit: cover; cursor: zoom-in;" onclick="zoomFoto(this.src)">
                                                                            <?php if ($informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                                                                <button class="btn btn-link btn-sm text-danger p-0 ms-1" onclick="eliminarCompra(<?= $c['id'] ?>)" title="Eliminar Factura">
                                                                                    <i class="fas fa-trash-alt"></i>
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($v['materiales_stock']): ?>
                                                            <div class="mt-2 small">
                                                                <span class="text-muted fw-bold">Materiales Stock:</span>
                                                                <p class="mb-0 text-muted"><?= htmlspecialchars($v['materiales_stock']) ?></p>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="visita-footer mt-3 pt-3 border-top d-flex gap-2">
                                                            <?php if (!$v['hora_salida'] && $informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                                                <button class="btn btn-sm btn-outline-info rounded-pill px-3" onclick="modalRegistrarSalida(<?= $v['id'] ?>)">
                                                                    <i class="fas fa-sign-out-alt me-1"></i>Registrar Salida
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if (!$v['materiales_stock'] && $informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="modalRegistrarMateriales(<?= $v['id'] ?>)">
                                                                    <i class="fas fa-box-open me-1"></i>Materiales Stock
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-lg-4">
                                    <h5 class="mb-3"><i class="fas fa-tasks text-primary me-2"></i>Agenda Pendiente</h5>
                                    <div class="pending-list">
                                        <?php if (empty($ticketsPendientes)): ?>
                                            <div class="text-center bg-white p-4 rounded-4 shadow-sm">
                                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                                <p class="text-muted small">No hay tickets pendientes asignados.</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($ticketsPendientes as $tp): ?>
                                                <div class="card border-0 shadow-sm mb-3 rounded-4 overflow-hidden border-start border-4
                                                    <?= $tp['tipo_formulario'] === 'cambio_equipos' ? 'border-danger' : 'border-info' ?>">
                                                    <div class="card-body p-3">
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <small class="text-muted fw-bold"><?= htmlspecialchars($tp['nombre_sucursal']) ?></small>
                                                            <?php if ($tp['nivel_urgencia'] == 4): ?>
                                                                <span class="badge bg-danger rounded-pill">CRÍTICO</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <h6 class="mb-1 fw-bold small"><?= htmlspecialchars($tp['titulo']) ?></h6>
                                                        <p class="small text-muted mb-0" style="font-size: 0.8em;"><?= htmlspecialchars($tp['descripcion']) ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL APERTURA -->
    <div class="modal fade" id="aperturaModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 overflow-hidden rounded-4">
                <div class="modal-header bg-primary text-white p-3 px-4 border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-play me-2"></i>Apertura de Informe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formApertura">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Kilometraje Inicial *</label>
                            <input type="number" step="0.01" class="form-control form-control-lg rounded-3" name="km_inicial" required placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Foto del Odómetro *</label>
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" onclick="document.getElementById('km_foto_input').click()">
                                    <i class="fas fa-upload me-1"></i>Subir
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm flex-grow-1" onclick="startCamera('cam_apertura')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" id="km_foto_input" name="km_foto_inicial" accept="image/*" class="d-none" onchange="previewFile(this, 'preview_apertura')">
                            <input type="hidden" name="km_foto_inicial_cam" id="cam_apertura_data">
                            
                            <div id="preview_apertura" class="text-center mt-2 d-none">
                                <img src="" class="img-thumbnail rounded-3" style="max-height: 200px;">
                            </div>
                            <div id="cam_apertura_container" class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black">
                                <video id="cam_apertura_video" autoplay playsinline class="w-100"></video>
                                <button type="button" class="btn btn-success btn-sm position-absolute bottom-0 start-50 translate-middle-x mb-2" onclick="captureSnapshot('cam_apertura')">
                                    <i class="fas fa-circle"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="guardarApertura()">
                        Confirmar Inicio
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL NUEVA VISITA -->
    <div class="modal fade" id="visitaModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-map-marker-alt text-danger me-2"></i>Registrar Parada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <form id="formVisita">
                        <input type="hidden" name="informe_id" id="visita_informe_id">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Sucursal Visitada *</label>
                            <select name="cod_sucursal" class="form-select rounded-3" required>
                                <option value="">Seleccionar tienda...</option>
                                <?php foreach ($sucursales as $s): ?>
                                    <option value="<?= $s['cod_sucursal'] ?>"><?= htmlspecialchars($s['nombre_sucursal']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 row">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Hora Llegada *</label>
                                <input type="time" name="hora_llegada" class="form-control rounded-3" value="<?= date('H:i') ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Hora Salida (Opcional)</label>
                                <input type="time" name="hora_salida" class="form-control rounded-3">
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">Materiales del Stock Usados</label>
                            <textarea name="materiales_stock" class="form-control rounded-3" rows="2" placeholder="Ej: 3 tornillos, 1m cable..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" onclick="guardarVisita()">Guardar Parada</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL TAREA/TICKET RESULTADO -->
    <div class="modal fade" id="tareaModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-tools me-2"></i>Registrar Trabajo Realizado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-3">
                    <form id="formTarea">
                        <input type="hidden" name="visita_id" id="tarea_visita_id">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Seleccionar Ticket de la Agenda *</label>
                            <select name="ticket_id" class="form-select rounded-3" required>
                                <option value="">Seleccione una parada para cargar tickets...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Grado de Finalización *</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="completado_100" id="done100" value="1" checked>
                                <label class="btn btn-outline-success" for="done100">Completado 100%</label>
                                <input type="radio" class="btn-check" name="completado_100" id="donePartial" value="0">
                                <label class="btn btn-outline-warning" for="donePartial">Parcial / Pendiente</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Detalle del Trabajo Realizado *</label>
                            <textarea name="trabajo_realizado" class="form-control rounded-3" rows="3" required placeholder="Explica detalladamente qué hiciste..."></textarea>
                        </div>
                        
                        <div class="mb-0">
                            <label class="form-label small fw-bold">Fotos de Evidencia (Múltiples permitsas, mín 1) *</label>
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('evidencia_input').click()">
                                    <i class="fas fa-file-image me-1"></i>Galería
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="startCamera('cam_evidencia')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" id="evidencia_input" multiple accept="image/*" class="d-none" onchange="previewEvidencia(this)">
                            <div id="evidencia_previews" class="row g-2 mt-2"></div>
                            
                            <div id="cam_evidencia_container" class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black" style="max-width: 400px; margin: 0 auto;">
                                <video id="cam_evidencia_video" autoplay playsinline class="w-100"></video>
                                <button type="button" class="btn btn-success btn-sm position-absolute bottom-0 start-50 translate-middle-x mb-2" onclick="captureSnapshot('cam_evidencia')">
                                    <i class="fas fa-circle"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="guardarTarea()">Guardar Registro</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CIERRE DE INFORME -->
    <div class="modal fade" id="cierreModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header bg-danger text-white p-3 px-4 border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-stop me-2"></i>Finalizar Informe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small">Al finalizar, no podrá agregar más visitas o fotos al reporte de hoy.</p>
                    <form id="formCierre">
                        <input type="hidden" name="informe_id" id="cierre_informe_id">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Kilometraje Final *</label>
                            <input type="number" step="0.01" class="form-control form-control-lg rounded-3" name="km_final" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold">Foto del Odómetro (Final) *</label>
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" onclick="document.getElementById('km_fin_input').click()">
                                    <i class="fas fa-upload me-1"></i>Subir
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm flex-grow-1" onclick="startCamera('cam_cierre')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" id="km_fin_input" name="km_foto_final" accept="image/*" class="d-none" onchange="previewFile(this, 'preview_cierre')">
                            <input type="hidden" name="km_foto_final_cam" id="cam_cierre_data">
                            
                            <div id="preview_cierre" class="text-center mt-2 d-none">
                                <img src="" class="img-thumbnail rounded-3" style="max-height: 180px;">
                            </div>
                            <div id="cam_cierre_container" class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black">
                                <video id="cam_cierre_video" autoplay playsinline class="w-100"></video>
                                <button type="button" class="btn btn-success btn-sm position-absolute bottom-0 start-50 translate-middle-x mb-2" onclick="captureSnapshot('cam_cierre')">
                                    <i class="fas fa-circle"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" onclick="guardarCierre()">Finalizar Informe</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL COMPRA / FACTURA -->
    <div class="modal fade" id="compraModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-success"><i class="fas fa-file-invoice-dollar me-2"></i>Registrar Compra/Gasto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <form id="formCompra">
                        <input type="hidden" name="visita_id" id="compra_visita_id">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Monto de la Factura *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">C$</span>
                                <input type="number" step="0.01" name="monto" class="form-control form-control-lg" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Detalle de lo Comprado *</label>
                            <input type="text" name="detalle" class="form-control" required placeholder="Ej: Tornillería p/ estante">
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">Foto de la Factura *</label>
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('compra_foto_input').click()">
                                    <i class="fas fa-upload me-1"></i>Galería
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="startCamera('cam_compra')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" id="compra_foto_input" name="foto_factura" accept="image/*" class="d-none" onchange="previewFile(this, 'preview_compra')">
                            <input type="hidden" name="foto_factura_cam" id="cam_compra_data">
                            
                            <div id="preview_compra" class="text-center mt-2 d-none">
                                <img src="" class="img-thumbnail rounded-3" style="max-height: 150px;">
                            </div>
                            <div id="cam_compra_container" class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black">
                                <video id="cam_compra_video" autoplay playsinline class="w-100"></video>
                                <button type="button" class="btn btn-success btn-sm position-absolute bottom-0 start-50 translate-middle-x mb-2" onclick="captureSnapshot('cam_compra')">
                                    <i class="fas fa-circle"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success rounded-pill px-4" onclick="guardarCompra()">Guardar Factura</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ZOOM IMAGEN -->
    <div class="modal fade" id="zoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-transparent border-0 shadow-none">
                <div class="modal-body text-center p-0">
                    <img id="zoomImg" src="" class="img-fluid rounded-4 shadow-lg">
                    <button type="button" class="btn btn-dark btn-sm rounded-circle position-absolute top-0 end-0 m-3" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/agenda_colaborador.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
    <!-- MODAL REGISTRAR SALIDA -->
    <div class="modal fade" id="salidaModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-info"><i class="fas fa-sign-out-alt me-2"></i>Registrar Hora de Salida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <form id="formSalida">
                        <input type="hidden" name="visita_id" id="salida_visita_id">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Hora de Salida *</label>
                            <input type="time" class="form-control form-control-lg rounded-3" name="hora_salida" required value="<?= date('H:i') ?>">
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-link link-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-info text-white rounded-pill px-4" onclick="guardarSalida()">
                        Guardar Salida
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL REGISTRAR MATERIALES -->
    <div class="modal fade" id="materialesModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-secondary"><i class="fas fa-box-open me-2"></i>Materiales de Stock Usados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <form id="formMateriales">
                        <input type="hidden" name="visita_id" id="materiales_visita_id">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Listado de Materiales *</label>
                            <textarea class="form-control rounded-3" name="materiales_stock" rows="4" required placeholder="Escriba aquí los materiales utilizados de su propio stock..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-link link-secondary text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-secondary rounded-pill px-4" onclick="guardarMateriales()">
                        Guardar Materiales
                    </button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>