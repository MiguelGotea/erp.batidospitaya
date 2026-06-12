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
$puedeGenerarReembolso = tienePermiso('agenda_mantenimiento', 'generar_reembolso', $cargoOperario);

if ($puedeVerTodosColaboradores || $puedeGenerarReembolso) {
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

// Obtener información del colaborador para el título
$nombreAMostrar = $usuario['Nombre'] . ' ' . $usuario['Apellido'];
if ($informeActual) {
    $nombreAMostrar = $informeActual['Nombre'] . ' ' . $informeActual['Apellido'];
} elseif ($colaborador_filtro != $usuario['CodOperario']) {
    $infoColab = $ticketModel->getColaboradorInfo($colaborador_filtro);
    if ($infoColab) {
        $nombreAMostrar = $infoColab['Nombre'] . ' ' . $infoColab['Apellido'];
    }
}

// Obtener sucursales para el selector de visitas
$sucursales = $ticketModel->getSucursales();



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
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/agenda_colaborador.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="../../core/assets/css/modales_premium.css">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Informe Diario de Mantenimiento'); ?>

            <div class="container-fluid px-md-5 py-3">
                <div class="container-fluid p-0">

                    <div class="mb-4"></div>

                    <!-- PANEL DE CONTROL DEL INFORME -->
                    <div
                        class="report-status-card mb-4 p-4 rounded-4 shadow-sm border-0 
                            <?= !$informeActual ? 'bg-light' : ($informeActual['estado'] === 'creado' ? 'bg-primary bg-opacity-10' : 'bg-success bg-opacity-10') ?>">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <h4 class="mb-1">
                                    <i class="fas fa-clipboard-list me-2"></i>
                                    Informe de <?= htmlspecialchars($nombreAMostrar) ?>
                                    - <?= date('d/m/Y', strtotime($fechaHoy)) ?>
                                </h4>
                                <p class="mb-0 text-muted">
                                    <?php if (!$informeActual): ?>
                                        <span class="badge bg-secondary fs-6">⚪ Sin Iniciar</span>
                                        <span class="d-block mt-1 small">Toca el botón de abajo para iniciar tu jornada.</span>
                                    <?php elseif ($informeActual['estado'] === 'creado'): ?>
                                        <span class="badge bg-primary fs-6">🟢 En Curso — Abierto</span>
                                        <span class="d-block mt-1 small">Puedes agregar tiendas, tareas y facturas.</span>
                                    <?php else: ?>
                                        <span class="badge bg-success fs-6">✅ Informe Cerrado</span>
                                        <span class="d-block mt-1 small">El informe está finalizado y no admite cambios.</span>
                                    <?php endif; ?>
                                </p>
                            </div>


                            <div class="d-flex gap-2 flex-column flex-sm-row w-100 w-sm-auto">
                                <?php if (!$informeActual && ($colaborador_filtro == $usuario['CodOperario'] || $puedeVerTodosColaboradores)): ?>
                                    <button class="btn btn-primary btn-iniciar-informe" onclick="modalApertura()">
                                        <i class="fas fa-play me-2"></i>Iniciar mi Informe del Día
                                    </button>
                                <?php elseif ($informeActual && $informeActual['estado'] === 'creado' && ($colaborador_filtro == $usuario['CodOperario'] || $puedeVerTodosColaboradores)): ?>
                                    <button class="btn btn-outline-danger btn-finalizar-informe"
                                        onclick="modalCierre(<?= $informeActual['id'] ?>)">
                                        <i class="fas fa-flag-checkered me-2"></i>Finalizar y Cerrar Informe
                                    </button>
                                <?php endif; ?>


                            </div>
                        </div>

                        <?php if ($informeActual): ?>
                            <hr class="my-3 opacity-10">
                            <div class="row g-4 text-center align-items-center">
                                <!-- COLUMNA KILOMETRAJE -->
                                <!-- KM TOGGLE -->
                                <hr class="my-2 opacity-10">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="toggleKm"
                                            <?= (!empty($informeActual['km_inicial']) || !empty($informeActual['km_final'])) ? 'checked' : '' ?>
                                            onchange="toggleKilometraje(this.checked)">
                                        <label class="form-check-label small fw-bold text-muted" for="toggleKm">
                                            <i class="fas fa-road me-1"></i>Registrar Kilometraje
                                        </label>
                                    </div>
                                </div>
                                <div id="kmSection" class="<?= (!empty($informeActual['km_inicial']) || !empty($informeActual['km_final'])) ? '' : 'd-none' ?>">
                                    <div class="row g-0 text-center mb-2 border rounded-3 py-2 bg-white">
                                        <div class="col-6 border-end">
                                            <small class="visita-info-label mb-1">KM Inicial</small>
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <?php if (!empty($informeActual['km_inicial'])): ?>
                                                    <span class="fw-bold fs-5"><?= number_format($informeActual['km_inicial'], 2) ?></span>
                                                    <?php if ($informeActual['km_foto_inicial']): ?>
                                                        <img src="uploads/informes/<?= $informeActual['km_foto_inicial'] ?>"
                                                            class="rounded shadow-sm"
                                                            style="width: 45px; height: 45px; object-fit: cover; cursor: zoom-in;"
                                                            onclick="zoomFoto(this.src)">
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if ($informeActual['estado'] === 'creado'): ?>
                                                        <button class="btn btn-sm btn-outline-primary mt-1 rounded-pill"
                                                            onclick="modalRegistrarKmInicial(<?= $informeActual['id'] ?>)">
                                                            <i class="fas fa-plus me-1"></i>Registrar
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <small class="visita-info-label mb-1">KM Final</small>
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <?php if (!empty($informeActual['km_final'])): ?>
                                                    <span class="fw-bold fs-5"><?= number_format($informeActual['km_final'], 2) ?></span>
                                                    <?php if ($informeActual['km_foto_final']): ?>
                                                        <img src="uploads/informes/<?= $informeActual['km_foto_final'] ?>"
                                                            class="rounded shadow-sm"
                                                            style="width: 45px; height: 45px; object-fit: cover; cursor: zoom-in;"
                                                            onclick="zoomFoto(this.src)">
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if ($informeActual['estado'] === 'creado'): ?>
                                                        <button class="btn btn-sm btn-outline-danger mt-1 rounded-pill"
                                                            onclick="modalRegistrarKmFinal(<?= $informeActual['id'] ?>)">
                                                            <i class="fas fa-plus me-1"></i>Registrar
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">—</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- STATS ROW -->
                                <hr class="my-3 opacity-10">
                                <div class="row g-4 text-center align-items-center">
                                    <!-- COLUMNA CAJA CHICA -->
                                    <div class="col-md-12">
                                        <?php
                                        $totalGastado = 0;
                                        foreach ($informeActual['visitas'] as $v) {
                                            foreach ($v['compras'] as $c)
                                                $totalGastado += $c['monto'];
                                        }
                                        $saldoActual = $informeActual['monto_caja_chica'] - $totalGastado;
                                        ?>
                                        <div class="d-flex flex-column gap-1 text-start">
                                            <div class="d-flex justify-content-between px-3">
                                                <small class="visita-info-label small opacity-75">Caja Chica (Ingreso):</small>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold text-dark">C$<?= number_format($informeActual['monto_caja_chica'], 2) ?></span>
                                                    <?php if ($informeActual['monto_caja_chica'] == 0 && tienePermiso('agenda_mantenimiento', 'caja_chica', $cargoOperario) && $informeActual['estado'] === 'creado'): ?>
                                                        <button class="btn btn-sm btn-outline-success p-0 px-2 rounded-pill"
                                                            style="font-size: 0.75rem;"
                                                            onclick="modalValidarCaja(<?= $informeActual['id'] ?>, 0)">
                                                            <i class="fas fa-plus me-1"></i>Registrar
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between px-3">
                                                <small class="visita-info-label small opacity-75">Total Gastado:</small>
                                                <span class="fw-bold text-danger">C$<?= number_format($totalGastado, 2) ?></span>
                                            </div>
                                            <div class="bg-white rounded-pill py-1 px-3 mt-1 d-flex justify-content-between shadow-sm border mx-2">
                                                <small class="visita-info-label">Saldo Actual:</small>
                                                <span class="fw-bold fs-5 text-success">C$<?= number_format($saldoActual, 2) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>


                    <!-- LISTA DE VISITAS/PROGRESO -->
                    <?php if ($informeActual): ?>
                            <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5><i class="fas fa-map-marker-alt text-danger me-2"></i>Mis Visitas del Día</h5>
                                    <?php if ($informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                        <button class="btn btn-sm btn-primary rounded-pill btn-agregar-tienda"
                                            onclick="modalNuevaVisita(<?= $informeActual['id'] ?>)">
                                            <i class="fas fa-plus me-1"></i>Agregar Tienda
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="visitas-timeline">
                                    <?php if (empty($informeActual['visitas'])): ?>
                                        <div class="alert alert-light border text-center empty-state rounded-4">
                                            <i class="fas fa-store-slash fa-2x"></i>
                                            <p class="text-muted mb-0">Aún no has registrado visitas a tiendas hoy.<br><small>Presiona <strong>"+ Agregar Tienda"</strong> para comenzar.</small></p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($informeActual['visitas'] as $v): ?>
                                            <?php $canEdit = ($informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']); ?>
                                            <div class="visita-item card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
                                                <div
                                                    class="card-header bg-white border-bottom-0 pt-3 px-4 d-flex justify-content-between align-items-start gap-3">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-0 text-primary fw-bold">
                                                            <?= htmlspecialchars($v['nombre_sucursal']) ?>
                                                        </h6>
                                                    </div>
                                                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                                                        <?php if ($informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                                                onclick="modalNuevaTarea(<?= $v['id'] ?>, '<?= $v['cod_sucursal'] ?>')">
                                                                <i class="fas fa-tools me-1"></i>Tarea
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-success rounded-pill px-3"
                                                                onclick="modalNuevaCompra(<?= $v['id'] ?>)">
                                                                <i class="fas fa-file-invoice-dollar me-1"></i>Factura
                                                            </button>
                                                            <button class="btn btn-link btn-sm text-danger p-0 ms-1"
                                                                onclick="eliminarVisita(<?= $v['id'] ?>)" title="Eliminar Visita">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($informeActual['estado'] === 'finalizado' && $puedeGenerarReembolso && !empty($v['compras'])): ?>
                                                            <?php if (!$v['reembolso_id']): ?>
                                                                <button class="btn btn-sm btn-primary rounded-pill px-3"
                                                                    onclick="generarReembolsoDesdeVisita(<?= $v['id'] ?>)">
                                                                    <i class="fas fa-hand-holding-usd me-1"></i>Generar Reembolso
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="badge bg-success rounded-pill px-3 py-2" style="font-size: 0.75rem;">
                                                                    <i class="fas fa-check-circle me-1"></i>Reembolso Procesado
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="card-body px-4 pb-4">
                                                    <!-- BLOQUE LOGÍSTICA (Arribo, Salida, Materiales) -->
                                                    <div class="visita-logistica-box mb-3">
                                                        <div class="row g-3">
                                                            <div class="col-md-auto">
                                                                <div class="visita-info-label mb-1">Arribo</div>
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <i class="far fa-clock text-primary"></i>
                                                                    <input type="time"
                                                                        class="form-control form-control-sm border-0 bg-white py-0 px-1"
                                                                        style="width: 115px;"
                                                                        value="<?= date('H:i', strtotime($v['hora_llegada'])) ?>"
                                                                        onchange="actualizarVisitaInline(<?= $v['id'] ?>, 'hora_llegada', this.value)"
                                                                        <?= !$canEdit ? 'disabled' : '' ?>>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-auto border-start ps-md-4">
                                                                <div class="visita-info-label mb-1">Término</div>
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <i class="fas fa-history text-primary"></i>
                                                                    <input type="time"
                                                                        class="form-control form-control-sm border-0 bg-white py-0 px-1"
                                                                        style="width: 115px;"
                                                                        value="<?= $v['hora_salida'] ? date('H:i', strtotime($v['hora_salida'])) : '' ?>"
                                                                        onchange="actualizarVisitaInline(<?= $v['id'] ?>, 'hora_salida', this.value)"
                                                                        <?= !$canEdit ? 'disabled' : '' ?>>
                                                                </div>
                                                            </div>
                                                            <div class="col-md border-start ps-md-4">
                                                                <div class="visita-info-label mb-1">Materiales Usados</div>
                                                                <div class="d-flex align-items-start gap-1">
                                                                    <i class="fas fa-boxes text-primary mt-1"></i>
                                                                    <textarea class="form-control form-control-sm border-0 bg-white"
                                                                        rows="1" placeholder="Ninguno..."
                                                                        onchange="actualizarVisitaInline(<?= $v['id'] ?>, 'materiales_stock', this.value)"
                                                                        <?= !$canEdit ? 'disabled' : '' ?>><?= htmlspecialchars($v['materiales_stock']) ?></textarea>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <hr class="section-divider">
                                                    <!-- Tareas -->
                                                    <div class="mb-2">
                                                        <small class="visita-info-label">TRABAJOS REALIZADOS:</small>
                                                    </div>
                                                    <div class="visita-tareas mb-4">
                                                        <?php foreach ($v['tareas'] as $tarea): ?>
                                                            <div class="p-2 border-bottom d-flex align-items-center gap-3">
                                                                <span
                                                                    class="badge bg-<?= $tarea['completado_100'] ? 'success' : 'warning text-dark' ?> rounded-pill">
                                                                    <?= $tarea['completado_100'] ? '100% Hecho' : 'Parcial' ?>
                                                                </span>
                                                                <div class="flex-grow-1">
                                                                    <small
                                                                        class="text-dark fw-bold d-block"><?= htmlspecialchars($tarea['titulo']) ?></small>
                                                                    <small class="text-muted text-truncate d-block"
                                                                        style="max-width: 300px;"><?= htmlspecialchars($tarea['trabajo_realizado']) ?></small>
                                                                </div>
                                                                <div class="d-flex gap-1 align-items-center">
                                                                    <?php foreach ($tarea['fotos'] as $f): ?>
                                                                        <img src="uploads/evidencias/<?= $f['foto'] ?>" class="rounded-1"
                                                                            style="width: 30px; height: 30px; object-fit: cover; cursor: zoom-in;"
                                                                            onclick="zoomFoto(this.src)">
                                                                    <?php endforeach; ?>
                                                                    <?php if ($informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                                                        <button class="btn btn-link btn-sm text-danger p-1 ms-1"
                                                                            onclick="eliminarTarea(<?= $tarea['id'] ?>)"
                                                                            title="Eliminar Tarea">
                                                                            <i class="fas fa-trash-alt"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <!-- Compras -->
                                                    <?php if (!empty($v['compras'])): ?>
                                                        <div class="mb-2">
                                                            <small class="visita-info-label">FACTURAS Y GASTOS:</small>
                                                        </div>
                                                        <div class="visita-compras p-3 bg-light rounded-3 mb-2">
                                                            <?php foreach ($v['compras'] as $c): ?>
                                                                <div
                                                                    class="d-flex justify-content-between align-items-center small py-1 border-bottom border-white">
                                                                    <span><i class="fas fa-file-invoice me-1"></i>
                                                                        <?= htmlspecialchars($c['detalle']) ?></span>
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <span class="fw-bold">C$<?= number_format($c['monto'], 2) ?></span>
                                                                        <img src="uploads/compras/<?= $c['foto_factura'] ?>"
                                                                            class="rounded-1"
                                                                            style="width: 25px; height: 25px; object-fit: cover; cursor: zoom-in;"
                                                                            onclick="zoomFoto(this.src)">
                                                                        <?php if ($informeActual['estado'] === 'creado' && $colaborador_filtro == $usuario['CodOperario']): ?>
                                                                            <button class="btn btn-link btn-sm text-danger p-0 ms-1"
                                                                                onclick="eliminarCompra(<?= $c['id'] ?>)"
                                                                                title="Eliminar Factura">
                                                                                <i class="fas fa-trash-alt"></i>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </div><!-- /visitas-timeline -->
                                </div><!-- /col-12 -->
                            </div><!-- /row -->
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
                    <h5 class="modal-title fw-bold"><i class="fas fa-play me-2"></i>Iniciar Informe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <i class="fas fa-clipboard-list fa-3x text-primary opacity-75"></i>
                    </div>
                    <p class="mb-2 fw-bold fs-5">¿Listo para comenzar tu jornada?</p>
                    <p class="text-muted">Se abrirá tu informe de hoy. Luego podrás ir agregando cada tienda que visites y las tareas que hagas.</p>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="guardarApertura()">
                        <i class="fas fa-play me-2"></i>Confirmar Inicio
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
                    <h5 class="modal-title fw-bold"><i class="fas fa-map-marker-alt text-danger me-2"></i>Registrar
                        Parada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <form id="formVisita">
                        <input type="hidden" name="informe_id" id="visita_informe_id">
                        <div class="mb-3">
                            <label class="form-label required">🏪 Tienda Visitada</label>
                            <select name="cod_sucursal" class="form-select" required>
                                <option value="">— Seleccione la tienda —</option>
                                <?php foreach ($sucursales as $s): ?>
                                    <option value="<?= $s['cod_sucursal'] ?>"><?= htmlspecialchars($s['nombre_sucursal']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" onclick="guardarVisita()">Guardar
                        Parada</button>
                </div>
            </div>
        </div>
    </div>

    
    <!-- MODAL TAREA/TICKET RESULTADO -->
    <div class="modal fade" id="tareaModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-tools me-2"></i>Registrar Trabajo
                        Realizado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-3">
                    <form id="formTarea">
                        <input type="hidden" name="visita_id" id="tarea_visita_id">
                        <div class="mb-3">
                            <label class="form-label required">🎫 Ticket de Trabajo (de la Agenda)</label>
                            <select name="ticket_id" class="form-select" required>
                                <option value="">Seleccione una parada para cargar tickets...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">✅ ¿Cómo quedó el trabajo?</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="completado_100" id="done100" value="1" checked>
                                <label class="btn btn-outline-success" for="done100">✅ Completado 100%</label>
                                <input type="radio" class="btn-check" name="completado_100" id="donePartial" value="0">
                                <label class="btn btn-outline-warning" for="donePartial">⚠️ Parcial / Pendiente</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">📋 Describe el trabajo realizado</label>
                            <textarea name="trabajo_realizado" class="form-control" rows="3" required
                                placeholder="Ej: Se reparó la llave del lavamanos del área de producción. Se cambió empaque y ajustaron conexiones."></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label required">📷 Fotos de Evidencia (mín. 1 foto)</label>
                            <div class="foto-upload-group mb-2">
                                <button type="button" class="btn btn-outline-primary"
                                    onclick="document.getElementById('evidencia_input').click()">
                                    <i class="fas fa-image me-1"></i>Galería
                                </button>
                                <button type="button" class="btn btn-outline-success"
                                    onclick="startCamera('cam_evidencia')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" id="evidencia_input" multiple accept="image/*" class="d-none"
                                onchange="previewEvidencia(this)">
                            <div id="evidencia_previews" class="row g-2 mt-2"></div>

                            <div id="cam_evidencia_container"
                                class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black"
                                style="max-width: 420px; margin: 0 auto; cursor: crosshair;">
                                <video id="cam_evidencia_video" autoplay playsinline class="w-100" style="display:block;"></video>
                                <!-- Rejilla tercios -->
                                <div class="ag-cam-grid"></div>
                                <!-- Anillo de enfoque táctil -->
                                <div id="cam_evidencia_ring" class="ag-focus-ring"></div>
                                <!-- Toast de enfoque -->
                                <div id="cam_evidencia_toast" class="ag-focus-toast">Toca para enfocar</div>
                                <!-- Controles inferiores -->
                                <div class="ag-cam-controls d-flex align-items-center justify-content-between px-3 py-2">
                                    <button type="button" id="cam_evidencia_torch" class="ag-btn-torch" style="display:none;"
                                        onclick="toggleCameraTorch('cam_evidencia')" title="Linterna">
                                        <i class="fas fa-bolt"></i>
                                    </button>
                                    <button type="button" class="ag-btn-capture"
                                        onclick="captureSnapshot('cam_evidencia')" title="Tomar foto">
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
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="guardarTarea()">Guardar
                        Registro</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CIERRE DE INFORME -->
    <div class="modal fade" id="cierreModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header bg-danger text-white p-3 px-4 border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-flag-checkered me-2"></i>Finalizar Informe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3">
                        <i class="fas fa-clipboard-check fa-3x text-danger opacity-75"></i>
                    </div>
                    <p class="mb-2 fw-bold fs-5">¿Finalizar y cerrar el informe de hoy?</p>
                    <p class="text-muted">Una vez cerrado, <strong>no podrás agregar más tiendas ni tareas</strong>. Asegúrate de haber registrado todo.</p>
                    <div class="alert alert-warning rounded-3 d-flex align-items-center gap-2 mt-3 text-start">
                        <i class="fas fa-exclamation-triangle flex-shrink-0"></i>
                        <span class="small fw-bold">Esta acción es irreversible.</span>
                    </div>
                    <form id="formCierre">
                        <input type="hidden" name="informe_id" id="cierre_informe_id">
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" onclick="guardarCierre()">
                        <i class="fas fa-flag-checkered me-2"></i>Confirmar Cierre
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL KM INICIAL -->
    <div class="modal fade" id="kmInicialModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header bg-primary text-white p-3 px-4 border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-road me-2"></i>Registrar KM Inicial</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formKmInicial">
                        <input type="hidden" name="informe_id" id="km_inicial_informe_id">
                        <div class="mb-4">
                            <label class="form-label required">📍 Kilometraje Inicial del Vehículo</label>
                            <input type="number" step="0.01" class="form-control form-control-lg"
                                name="km_inicial" required placeholder="Ej: 45000.0">
                        </div>
                        <div class="mb-0">
                            <label class="form-label required">📷 Foto del Odómetro (al salir)</label>
                            <div class="foto-upload-group mb-2">
                                <button type="button" class="btn btn-outline-primary"
                                    onclick="document.getElementById('km_ini_foto_input').click()">
                                    <i class="fas fa-image me-1"></i>Galería
                                </button>
                                <button type="button" class="btn btn-outline-success"
                                    onclick="startCamera('cam_km_ini')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" id="km_ini_foto_input" name="km_foto_inicial" accept="image/*"
                                class="d-none" onchange="previewFile(this, 'preview_km_ini')">
                            <input type="hidden" name="km_foto_inicial_cam" id="cam_km_ini_data">
                            <div id="preview_km_ini" class="text-center mt-2 d-none">
                                <img src="" class="img-thumbnail rounded-3" style="max-height: 180px;">
                            </div>
                            <div id="cam_km_ini_container"
                                class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black"
                                style="cursor: crosshair;">
                                <video id="cam_km_ini_video" autoplay playsinline class="w-100" style="display:block;"></video>
                                <div class="ag-cam-grid"></div>
                                <div id="cam_km_ini_ring" class="ag-focus-ring"></div>
                                <div id="cam_km_ini_toast" class="ag-focus-toast">Toca para enfocar</div>
                                <div class="ag-cam-controls d-flex align-items-center justify-content-between px-3 py-2">
                                    <button type="button" id="cam_km_ini_torch" class="ag-btn-torch" style="display:none;"
                                        onclick="toggleCameraTorch('cam_km_ini')" title="Linterna">
                                        <i class="fas fa-bolt"></i>
                                    </button>
                                    <button type="button" class="ag-btn-capture"
                                        onclick="captureSnapshot('cam_km_ini')" title="Tomar foto">
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
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary rounded-pill px-4" onclick="guardarKmInicial()">
                        <i class="fas fa-save me-2"></i>Guardar KM Inicial
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL KM FINAL -->
    <div class="modal fade" id="kmFinalModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header bg-danger text-white p-3 px-4 border-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-road me-2"></i>Registrar KM Final</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formKmFinal">
                        <input type="hidden" name="informe_id" id="km_final_informe_id">
                        <div class="mb-4">
                            <label class="form-label required">📍 Kilometraje Final del Vehículo</label>
                            <input type="number" step="0.01" class="form-control form-control-lg"
                                name="km_final" required placeholder="Ej: 45250.0">
                        </div>
                        <div class="mb-0">
                            <label class="form-label required">📷 Foto del Odómetro (al llegar)</label>
                            <div class="foto-upload-group mb-2">
                                <button type="button" class="btn btn-outline-primary"
                                    onclick="document.getElementById('km_fin_foto_input').click()">
                                    <i class="fas fa-image me-1"></i>Galería
                                </button>
                                <button type="button" class="btn btn-outline-success"
                                    onclick="startCamera('cam_km_fin')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" id="km_fin_foto_input" name="km_foto_final" accept="image/*"
                                class="d-none" onchange="previewFile(this, 'preview_km_fin')">
                            <input type="hidden" name="km_foto_final_cam" id="cam_km_fin_data">
                            <div id="preview_km_fin" class="text-center mt-2 d-none">
                                <img src="" class="img-thumbnail rounded-3" style="max-height: 180px;">
                            </div>
                            <div id="cam_km_fin_container"
                                class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black"
                                style="cursor: crosshair;">
                                <video id="cam_km_fin_video" autoplay playsinline class="w-100" style="display:block;"></video>
                                <div class="ag-cam-grid"></div>
                                <div id="cam_km_fin_ring" class="ag-focus-ring"></div>
                                <div id="cam_km_fin_toast" class="ag-focus-toast">Toca para enfocar</div>
                                <div class="ag-cam-controls d-flex align-items-center justify-content-between px-3 py-2">
                                    <button type="button" id="cam_km_fin_torch" class="ag-btn-torch" style="display:none;"
                                        onclick="toggleCameraTorch('cam_km_fin')" title="Linterna">
                                        <i class="fas fa-bolt"></i>
                                    </button>
                                    <button type="button" class="ag-btn-capture"
                                        onclick="captureSnapshot('cam_km_fin')" title="Tomar foto">
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
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4" onclick="guardarKmFinal()">
                        <i class="fas fa-save me-2"></i>Guardar KM Final
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL COMPRA / FACTURA -->
    <div class="modal fade" id="compraModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold text-success"><i
                            class="fas fa-file-invoice-dollar me-2"></i>Registrar Compra/Gasto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <form id="formCompra">
                        <input type="hidden" name="visita_id" id="compra_visita_id">
                        <div class="mb-3">
                            <label class="form-label required">💰 Monto de la Factura</label>
                            <div class="input-group">
                                <span class="input-group-text">C$</span>
                                <input type="number" step="0.01" name="monto" class="form-control form-control-lg"
                                    required placeholder="0.00">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">📝 ¿Qué se compró?</label>
                            <input type="text" name="detalle" class="form-control" required
                                placeholder="Ej: Tornillos para estante del área de batidos">
                        </div>
                        <div class="mb-0">
                            <label class="form-label required">📷 Foto de la Factura</label>
                            <div class="foto-upload-group mb-2">
                                <button type="button" class="btn btn-outline-primary"
                                    onclick="document.getElementById('compra_foto_input').click()">
                                    <i class="fas fa-image me-1"></i>Galería
                                </button>
                                <button type="button" class="btn btn-outline-success"
                                    onclick="startCamera('cam_compra')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" id="compra_foto_input" name="foto_factura" accept="image/*"
                                class="d-none" onchange="previewFile(this, 'preview_compra')">
                            <input type="hidden" name="foto_factura_cam" id="cam_compra_data">

                            <div id="preview_compra" class="text-center mt-2 d-none">
                                <img src="" class="img-thumbnail rounded-3" style="max-height: 150px;">
                            </div>
                            <div id="cam_compra_container"
                                class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black"
                                style="cursor: crosshair;">
                                <video id="cam_compra_video" autoplay playsinline class="w-100" style="display:block;"></video>
                                <div class="ag-cam-grid"></div>
                                <div id="cam_compra_ring" class="ag-focus-ring"></div>
                                <div id="cam_compra_toast" class="ag-focus-toast">Toca para enfocar</div>
                                <div class="ag-cam-controls d-flex align-items-center justify-content-between px-3 py-2">
                                    <button type="button" id="cam_compra_torch" class="ag-btn-torch" style="display:none;"
                                        onclick="toggleCameraTorch('cam_compra')" title="Linterna">
                                        <i class="fas fa-bolt"></i>
                                    </button>
                                    <button type="button" class="ag-btn-capture"
                                        onclick="captureSnapshot('cam_compra')" title="Tomar foto">
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
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success rounded-pill px-4" onclick="guardarCompra()">Guardar
                        Factura</button>
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
                    <button type="button" class="btn btn-dark btn-sm rounded-circle position-absolute top-0 end-0 m-3"
                        data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL REGISTRAR CAJA CHICA (OPERARIO) -->
    <div class="modal fade" id="validarCajaModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-cash-register me-2 text-success"></i>Registrar Caja
                        Chica</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formCaja">
                        <input type="hidden" name="informe_id" id="caja_informe_id">
                        <div class="mb-4">
                            <label class="form-label required">💵 Monto Recibido (Caja Chica)</label>
                            <div class="input-group">
                                <span class="input-group-text">C$</span>
                                <input type="number" step="0.01" name="monto" id="caja_monto"
                                    class="form-control form-control-lg" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label required">📷 Foto del Voucher / Comprobante</label>
                            <div class="foto-upload-group mb-2">
                                <button type="button" class="btn btn-outline-primary"
                                    onclick="document.getElementById('caja_foto_input').click()">
                                    <i class="fas fa-image me-1"></i>Galería
                                </button>
                                <button type="button" class="btn btn-outline-success"
                                    onclick="startCamera('cam_caja')">
                                    <i class="fas fa-camera me-1"></i>Cámara
                                </button>
                            </div>
                            <input type="file" name="foto_caja" id="caja_foto_input" class="d-none"
                                accept="image/*" onchange="previewFile(this, 'preview_caja')">
                            <input type="hidden" name="foto_caja_cam" id="cam_caja_data">

                            <div id="preview_caja" class="text-center mt-2 d-none">
                                <img src="" class="img-thumbnail rounded-3" style="max-height: 180px;">
                            </div>

                            <div id="cam_caja_container"
                                class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black"
                                style="cursor: crosshair;">
                                <video id="cam_caja_video" autoplay playsinline class="w-100" style="display:block;"></video>
                                <div class="ag-cam-grid"></div>
                                <div id="cam_caja_ring" class="ag-focus-ring"></div>
                                <div id="cam_caja_toast" class="ag-focus-toast">Toca para enfocar</div>
                                <div class="ag-cam-controls d-flex align-items-center justify-content-between px-3 py-2">
                                    <button type="button" id="cam_caja_torch" class="ag-btn-torch" style="display:none;"
                                        onclick="toggleCameraTorch('cam_caja')" title="Linterna">
                                        <i class="fas fa-bolt"></i>
                                    </button>
                                    <button type="button" class="ag-btn-capture"
                                        onclick="captureSnapshot('cam_caja')" title="Tomar foto">
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
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success rounded-pill px-4"
                        onclick="guardarValidacionCaja()">Confirmar Entrega</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== MODAL DE AYUDA ===== -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1"
         aria-labelledby="pageHelpModalLabel" aria-hidden="true"
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>Guía de Informe Diario de Mantenimiento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted small mb-3">Sigue estos pasos cada vez que salgas a trabajar:</p>

                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex gap-3 align-items-start p-3 bg-light rounded-3">
                            <span class="badge bg-primary rounded-circle fs-5 px-3 py-2">1</span>
                            <div>
                                <div class="fw-bold">Inicia tu Informe</div>
                                <small class="text-muted">Toca "Iniciar mi Informe del Día" al comenzar tu jornada.</small>
                            </div>
                        </div>
                        <div class="d-flex gap-3 align-items-start p-3 bg-light rounded-3">
                            <span class="badge bg-primary rounded-circle fs-5 px-3 py-2">2</span>
                            <div>
                                <div class="fw-bold">Agrega cada Tienda que visitas</div>
                                <small class="text-muted">Por cada tienda: registra hora de llegada, salida y materiales usados.</small>
                            </div>
                        </div>
                        <div class="d-flex gap-3 align-items-start p-3 bg-light rounded-3">
                            <span class="badge bg-primary rounded-circle fs-5 px-3 py-2">3</span>
                            <div>
                                <div class="fw-bold">Registra tus Tareas</div>
                                <small class="text-muted">Selecciona el ticket, describe qué hiciste y adjunta al menos 1 foto.</small>
                            </div>
                        </div>
                        <div class="d-flex gap-3 align-items-start p-3 bg-light rounded-3">
                            <span class="badge bg-primary rounded-circle fs-5 px-3 py-2">4</span>
                            <div>
                                <div class="fw-bold">Registra tus Facturas (si aplica)</div>
                                <small class="text-muted">Agrega monto, detalle y foto de cada factura o compra del día.</small>
                            </div>
                        </div>
                        <div class="d-flex gap-3 align-items-start p-3 bg-danger bg-opacity-10 rounded-3 border border-danger border-opacity-25">
                            <span class="badge bg-danger rounded-circle fs-5 px-3 py-2">5</span>
                            <div>
                                <div class="fw-bold text-danger">Finaliza tu Informe al terminar</div>
                                <small class="text-muted">Registra el KM final y toma foto del odómetro. <strong>Una vez finalizado no se puede editar.</strong></small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3 small rounded-3">
                        <i class="fas fa-lock me-1"></i> <strong>Para finalizar necesitas:</strong> que todas las tiendas tengan hora de salida y materiales registrados.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        #pageHelpModal { z-index: 1060 !important; }
        .modal-backdrop { z-index: 1050 !important; }

        /* ── Cámara Premium (Agenda Colaborador) ── */
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/agenda_colaborador.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

</body>

</html>