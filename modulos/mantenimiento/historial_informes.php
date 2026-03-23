<?php
// historial_informes.php

require_once 'models/Ticket.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('agenda_mantenimiento', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeVerTodos = tienePermiso('agenda_mantenimiento', 'todos_colaboradores', $cargoOperario);
$esAdminCaja = tienePermiso('agenda_mantenimiento', 'caja_chica', $cargoOperario);
$puedeGenerarReembolso = tienePermiso('agenda_mantenimiento', 'generar_reembolso', $cargoOperario);
$puedeVerReporteSemanal = tienePermiso('agenda_mantenimiento', 'reporte_semanal', $cargoOperario);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Informes Diarios</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <link rel="stylesheet" href="../../core/assets/css/modales_premium.css">
    <style>
        :root {
            --color-header-tabla: #0E544C;
            --color-principal: #51B8AC;
        }

        .card-informe {
            transition: all 0.2s;
            border-radius: 12px;
        }

        .status-badge {
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
        }

        .table-premium thead th {
            background: var(--color-header-tabla) !important;
            color: white !important;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border-top: none;
            position: relative;
            padding: 12px 15px;
        }

        .filter-icon {
            cursor: pointer;
            margin-left: 5px;
            opacity: 0.7;
            transition: 0.2s;
        }

        .filter-icon:hover,
        .filter-icon.active {
            opacity: 1;
            color: var(--color-principal);
        }

        .filter-icon.has-filter {
            color: #ffc107;
            opacity: 1;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: 0.2s;
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        .pagination-btn {
            border: 1px solid #dee2e6;
            background: white;
            padding: 5px 12px;
            margin: 0 2px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.2s;
        }

        .pagination-btn.active {
            background: var(--color-header-tabla);
            color: white;
            border-color: var(--color-header-tabla);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Control de Informes Diarios'); ?>

            <div class="container-fluid p-4">
                <div class="mb-4 d-flex justify-content-end align-items-center gap-3 no-print">
                    <?php if ($puedeVerReporteSemanal): ?>
                        <div class="d-flex align-items-center bg-white p-2 rounded-pill shadow-sm border px-3 gap-2">
                            <div class="d-flex align-items-center gap-1">
                                <label class="small fw-bold text-muted mb-0">Semana:</label>
                                <input type="number" id="inputSemanaReporte" class="form-control form-control-sm border-0 bg-light rounded-pill text-center fw-bold" style="width: 70px;" placeholder="#">
                                <span id="spanSemanaActual" class="badge bg-secondary rounded-pill opacity-75" style="font-size: 0.7rem;"></span>
                            </div>
                            <div class="vr mx-1"></div>
                            <div class="d-flex align-items-center gap-1">
                                <label class="small fw-bold text-muted mb-0">C$:</label>
                                <input type="number" id="inputCostoKmReporte" class="form-control form-control-sm border-0 bg-light rounded-pill text-center fw-bold" style="width: 60px;" value="5">
                            </div>
                            <button onclick="abrirModalReporte()" class="btn btn-pitaya btn-sm rounded-pill px-3 ms-2">
                                <i class="fas fa-file-invoice-dollar me-1"></i>Ver Reporte
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Botón Flotante Nueva Solicitud -->
                <a href="agenda_colaborador.php" class="btn-floating-pitaya" title="Nuevo Reporte de Hoy">
                    <i class="fas fa-plus"></i>
                </a>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-premium mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4" data-column="fecha" data-type="daterange">
                                            Fecha
                                            <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th data-column="Nombre" data-type="list">
                                            Colaborador
                                            <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th>KM Recorrido</th>
                                        <th>Caja Chica</th>
                                        <th data-column="estado" data-type="list">
                                            Estado
                                            <i class="fas fa-filter filter-icon" onclick="toggleFilter(this)"></i>
                                        </th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaInformesBody">
                                    <!-- Cargado vía AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 p-3">
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="d-flex align-items-center gap-2">
                                <label class="mb-0 small">Mostrar:</label>
                                <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;"
                                    onchange="cambiarRegistrosPorPagina()">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                            <div id="paginacion" class="d-flex align-items-center"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL VALIDAR CAJA CHICA (ADMIN) -->
    <div class="modal fade" id="validarCajaModal" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-premium border-0 rounded-4">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-cash-register me-2 text-success"></i>Validar Caja
                        Chica</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formCaja">
                        <input type="hidden" name="informe_id" id="caja_informe_id">
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Monto Entregado (Caja Chica) *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" name="monto" id="caja_monto"
                                    class="form-control form-control-lg" required>
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label small fw-bold">Foto del Voucher / Comprobante *</label>
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1"
                                    onclick="document.getElementById('caja_foto_input').click()">
                                    <i class="fas fa-upload me-1"></i>Galería
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-success flex-grow-1"
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
                                class="mt-2 d-none border rounded-3 overflow-hidden position-relative bg-black">
                                <video id="cam_caja_video" autoplay playsinline class="w-100"></video>
                                <button type="button"
                                    class="btn btn-success btn-sm position-absolute bottom-0 start-50 translate-middle-x mb-2"
                                    onclick="captureSnapshot('cam_caja')">
                                    <i class="fas fa-circle"></i>
                                </button>
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

    <!-- Modal de Ayuda Universal -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white"
                    style="background-color: var(--color-header-tabla) !important;">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Historial de Informes Diarios
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-list-alt me-2"></i> Visibilidad de Informes
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        - Los <strong>Colaboradores</strong> solo pueden ver sus propios informes.<br>
                                        - Los <strong>Administradores</strong> pueden ver el historial de todo el equipo
                                        utilizando los filtros en los encabezados.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-cash-register me-2"></i> Caja Chica
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Los administradores pueden validar el monto entregado y adjuntar el voucher una
                                        vez que el colaborador abre su jornada.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-edit me-2"></i> Estados del Informe
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        - <strong>Abierto:</strong> El informe aún puede ser editado por el
                                        colaborador.<br>
                                        - <strong>Finalizado:</strong> El informe ya no es editable y está listo para
                                        auditoría.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="fas fa-filter me-2"></i> Filtros de Encabezado
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Haz clic en el ícono de embudo (<i class="fas fa-filter"></i>) en los
                                        encabezados
                                        de Fecha, Colaborador o Estado para filtrar y ordenar los datos dinámicamente.
                                        <i class="fas fa-filter"></i>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Reporte Semanal -->
    <style>
        #modalReporteSemanal .modal-dialog {
            max-width: 98% !important;
            width: fit-content !important;
            margin: 1.75rem auto;
        }
        @media (max-width: 768px) {
            #modalReporteSemanal .modal-dialog {
                width: auto !important;
                margin: 0.5rem;
            }
        }
        #modalReporteSemanal .table td, #modalReporteSemanal .table th {
            white-space: nowrap;
        }
    </style>
    <div class="modal fade no-print" id="modalReporteSemanal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-white border-0 py-3">
                    <h5 class="modal-title fw-bold text-primary">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Resumen de KM y Costos Semanal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div id="rangoFechasTexto" class="text-muted small mb-3 fw-bold ps-2 border-start border-primary border-4">---</div>
                    
                    <div id="alertaReembolso" class="alert alert-warning d-none" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Esta semana ya cuenta con una solicitud de reembolso vinculada. No se recomienda generar duplicados.
                    </div>

                    <div class="table-responsive bg-white rounded-4 shadow-sm">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-white">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 300px;">Detalle</th>
                                    <th class="text-center py-3">Registros</th>
                                    <th class="text-center py-3">KM Totales</th>
                                    <th class="text-end pe-4 py-3">Total Estimado</th>
                                </tr>
                            </thead>
                            <tbody id="tablaCuerpoModal">
                                <!-- Datos cargados por AJAX -->
                            </tbody>
                            <tfoot id="tablaPieModal" class="table-light">
                                <!-- Totales -->
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                    <button id="btnIrAReembolso" class="btn btn-pitaya px-4 rounded-pill" onclick="enviarAReembolso()">
                        <i class="fas fa-external-link-alt me-2"></i>Verificar y Generar Orden de Reembolso
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const actualUserId = <?= $usuario['CodOperario'] ?>;
        const esAdminCaja = <?= $esAdminCaja ? 'true' : 'false' ?>;
        const puedeVerTodos = <?= $puedeVerTodos ? 'true' : 'false' ?>;
        const puedeGenerarReembolso = <?= $puedeGenerarReembolso ? 'true' : 'false' ?>;
        
        const depreciacionFija = 150.00;
        let infoSemanaActual = null;

        $(document).ready(function() {
            cargarSemanaActual();
        });

        async function cargarSemanaActual() {
            try {
                const response = await $.post('ajax/reporte_semanal_handler.php', { action: 'get_current_week' });
                if (response.success) {
                    $('#inputSemanaReporte').val(response.numero_semana);
                    $('#spanSemanaActual').text('Semana Actual: ' + response.numero_semana);
                }
            } catch (err) { console.error(err); }
        }

        async function abrirModalReporte() {
            const numSemana = $('#inputSemanaReporte').val();
            const costoKm = parseFloat($('#inputCostoKmReporte').val()) || 5;

            if (!numSemana) {
                Swal.fire('Atención', 'Ingresa el número de semana', 'warning');
                return;
            }

            Swal.fire({
                title: 'Generando Resumen...',
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const res = await $.post('ajax/reporte_semanal_handler.php', {
                    action: 'get_datos_semanales',
                    numero_semana: numSemana
                });

                if (res.success) {
                    infoSemanaActual = res;
                    $('#rangoFechasTexto').text(`Semana #${numSemana} | Rango: ${res.rango.desde} al ${res.rango.hasta}`);
                    
                    // Verificar si ya existe reembolso
                    const yaReembolsado = res.resumen.some(r => r.reembolso_id !== null && r.reembolso_id > 0);
                    if (yaReembolsado) {
                        $('#alertaReembolso').removeClass('d-none');
                        $('#btnIrAReembolso').addClass('disabled').attr('title', 'Ya se ha generado un reembolso para esta semana');
                    } else {
                        $('#alertaReembolso').addClass('d-none');
                        $('#btnIrAReembolso').removeClass('disabled').removeAttr('title');
                    }

                    let html = '';
                    let sumTotalKm = 0, sumTotalComb = 0, sumTotalDep = 0, sumTotalFinal = 0;

                    const agrupado = {};
                    res.detalle.forEach(d => {
                        const key = d.cod_operario;
                        if (!agrupado[key]) agrupado[key] = { name: `${d.Nombre} ${d.Apellido}`, logs: [] };
                        agrupado[key].logs.push(d);
                    });

                    for (const opId in agrupado) {
                        const op = agrupado[opId];
                        const resumenOp = res.resumen.find(r => r.CodOperario == opId);
                        const kmTotalOp = parseFloat(resumenOp.km_total) || 0;
                        const combustibleOp = kmTotalOp * costoKm;
                        const depOp = depreciacionFija;
                        const totalOp = combustibleOp + depOp;

                        sumTotalKm += kmTotalOp;
                        sumTotalComb += combustibleOp;
                        sumTotalDep += depOp;
                        sumTotalFinal += totalOp;

                        html += `
                            <tr class="table-light">
                                <td colspan="4" class="ps-4 fw-bold text-dark" style="background-color: #f8fcfb;">
                                    <i class="fas fa-user-circle me-2 text-primary"></i>${op.name}
                                </td>
                            </tr>
                        `;

                        op.logs.forEach(log => {
                            const kmDia = (parseFloat(log.km_final) || 0) - (parseFloat(log.km_inicial) || 0);
                            const costoDia = kmDia * costoKm;
                            html += `
                                <tr class="small text-muted border-0">
                                    <td class="ps-5 py-1">${log.fecha}</td>
                                    <td class="text-center py-1">${parseFloat(log.km_inicial).toLocaleString()} → ${parseFloat(log.km_final).toLocaleString()}</td>
                                    <td class="text-center py-1 fw-bold">${kmDia} km</td>
                                    <td class="text-end pe-4 py-1">C$ ${costoDia.toFixed(2)}</td>
                                </tr>
                            `;
                        });

                        html += `
                            <tr class="fw-bold border-bottom shadow-sm">
                                <td colspan="2" class="text-end text-primary ps-5 py-2">Total Colaborador (incluye C$ 150 fijo):</td>
                                <td class="text-center text-primary py-2">${kmTotalOp.toLocaleString()} km</td>
                                <td class="text-end pe-4 text-dark py-2">C$ ${totalOp.toFixed(2)}</td>
                            </tr>
                        `;
                    }

                    $('#tablaCuerpoModal').html(html);
                    $('#tablaPieModal').html(`
                        <tr>
                            <td colspan="2" class="text-end fw-bold py-3 ps-4 fs-5">TOTAL SEMANAL ESTIMADO:</td>
                            <td class="text-center fw-bold text-primary py-3 fs-5">${sumTotalKm.toLocaleString()} km</td>
                            <td class="text-end pe-4 fw-bold text-pitaya py-3 fs-5">C$ ${sumTotalFinal.toFixed(2)}</td>
                        </tr>
                    `);

                    Swal.close();
                    const modal = new bootstrap.Modal(document.getElementById('modalReporteSemanal'));
                    modal.show();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (err) {
                console.error(err);
                Swal.fire('Error', 'No se pudo cargar la información', 'error');
            }
        }

        function enviarAReembolso() {
            const numSemana = $('#inputSemanaReporte').val();
            const costoKm = $('#inputCostoKmReporte').val() || 5;
            const anio = new Date().getFullYear();

            const url = `../compras/reembolsos_ia_nuevo.php?id=15&from_km=1&semana=${numSemana}&anio=${anio}&costo=${costoKm}`;
            window.location.href = url;
        }
    </script>
    <script src="js/historial_informes.js?v=<?= mt_rand(1, 10000) ?>"></script>
</body>

</html>