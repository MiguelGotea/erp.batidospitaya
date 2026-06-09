<?php
// resumen_semanal_informes_mantenimiento.php
require_once 'models/Ticket.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('agenda_mantenimiento', 'reporte_semanal', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeGenerarReembolso = tienePermiso('agenda_mantenimiento', 'generar_reembolso', $cargoOperario);

// Parámetros GET
$numSemana = isset($_GET['semana']) ? intval($_GET['semana']) : null;
$anio      = isset($_GET['anio'])   ? intval($_GET['anio'])   : intval(date('Y'));
$costoKm   = isset($_GET['costo'])  ? floatval($_GET['costo']) : 5.0;
$depFija   = 150.00;

// Si no viene semana, obtener la actual
if (!$numSemana) {
    $db   = (new Ticket())->getDb()->getConnection();
    $stmt = $db->prepare("SELECT numero_semana FROM SemanasSistema WHERE :hoy BETWEEN fecha_inicio AND fecha_fin AND anio = :anio LIMIT 1");
    $stmt->execute([':hoy' => date('Y-m-d'), ':anio' => $anio]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $numSemana = $row ? intval($row['numero_semana']) : 1;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen Semanal de Informes — Mantenimiento</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css">
    <link rel="stylesheet" href="../../core/assets/css/modales_premium.css">
    <style>
        :root {
            --color-primary: #0E544C;
            --color-accent:  #51B8AC;
        }
        .page-hero {
            background: linear-gradient(135deg, var(--color-primary) 0%, #1a7a70 100%);
            color: #fff;
            padding: 2rem 2.5rem;
            border-radius: 0 0 1.5rem 1.5rem;
        }
        .controls-bar {
            background: #fff;
            border-radius: 1rem;
            padding: .75rem 1.25rem;
            box-shadow: 0 2px 12px rgba(0,0,0,.07);
            gap: .75rem;
        }
        .section-card {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .section-card .card-header {
            background: var(--color-primary);
            color: #fff;
            font-weight: 700;
            padding: .85rem 1.25rem;
            font-size: .9rem;
            letter-spacing: .4px;
        }
        /* KM Summary table */
        .tbl-km thead th {
            background: #f0faf9;
            color: var(--color-primary);
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 2px solid #c3e8e5;
        }
        .tbl-km tfoot td { background: #e6f5f3; font-weight: 700; }
        .op-header-row td {
            background: #f8fcfb;
            font-weight: 700;
            font-size: .85rem;
            color: var(--color-primary);
            border-top: 2px solid #d4eeeb;
        }
        /* Detalle informes */
        .informe-block { border-radius: .75rem; overflow: hidden; margin-bottom: 1.5rem; border: 1px solid #e4f0ef; }
        .informe-block-header {
            background: linear-gradient(90deg, #0E544C 0%, #1a7a70 100%);
            color: #fff;
            padding: .7rem 1.1rem;
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .km-badge { background: rgba(255,255,255,.2); border-radius: 2rem; padding: .2rem .7rem; font-size: .8rem; }
        .visita-card { border-radius: .5rem; border: 1px solid #e0efee; margin-bottom: .75rem; overflow: hidden; }
        .visita-card-header { background: #f0faf9; padding: .5rem .85rem; font-weight: 600; font-size: .85rem; color: var(--color-primary); border-bottom: 1px solid #d4eeeb; display: flex; justify-content: space-between; align-items: center; }
        .visita-card-body { padding: .75rem .85rem; }
        .info-chip { display: inline-flex; align-items: center; gap: .35rem; background: #f5f5f5; border-radius: 2rem; padding: .2rem .65rem; font-size: .78rem; color: #555; margin-right: .4rem; }
        .tarea-row { display: flex; align-items: flex-start; gap: .6rem; padding: .4rem 0; border-bottom: 1px dashed #eee; }
        .tarea-row:last-child { border-bottom: none; }
        .compra-row { display: flex; justify-content: space-between; align-items: center; padding: .35rem .5rem; background: #fffdf5; border-radius: .4rem; margin-bottom: .3rem; font-size: .82rem; }
        .stat-pill { background: #e8f5e9; color: #2e7d32; border-radius: 2rem; padding: .15rem .7rem; font-size: .78rem; font-weight: 600; }
        .stat-pill.warn { background: #fff3e0; color: #e65100; }
        #loadingState, #emptyState { display: none; }
        @media print {
            .no-print { display: none !important; }
            .informe-block { break-inside: avoid; }
        }
    </style>
</head>
<body>
<?php echo renderMenuLateral($cargoOperario); ?>
<div class="main-container">
    <div class="sub-container">
        <?php echo renderHeader($usuario, 'Resumen Semanal de Informes'); ?>

        <!-- HERO -->
        <div class="page-hero no-print">
            <div class="d-flex align-items-center gap-3 mb-1">
                <h4 class="mb-0 fw-bold"><i class="fas fa-chart-bar me-2"></i>Resumen Semanal de Informes</h4>
            </div>
            <p class="mb-0 opacity-75 small mt-1">Kilometrajes, costos consolidados y detalle de visitas por colaborador</p>
        </div>

        <div class="container-fluid px-4 py-4">

            <!-- CONTROLES -->
            <div class="controls-bar d-flex flex-wrap align-items-center mb-4 no-print">
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted mb-0">Semana:</label>
                    <input type="number" id="inputSemana" class="form-control form-control-sm rounded-pill text-center fw-bold border-0 bg-light" style="width:75px" value="<?= $numSemana ?>">
                </div>
                <div class="vr mx-2"></div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted mb-0">Año:</label>
                    <input type="number" id="inputAnio" class="form-control form-control-sm rounded-pill text-center fw-bold border-0 bg-light" style="width:85px" value="<?= $anio ?>">
                </div>
                <div class="vr mx-2"></div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-muted mb-0">C$/km:</label>
                    <input type="number" id="inputCosto" step="0.01" class="form-control form-control-sm rounded-pill text-center fw-bold border-0 bg-light" style="width:75px" value="<?= $costoKm ?>">
                </div>
                <button onclick="cargarResumen()" class="btn btn-sm ms-2 rounded-pill px-4 text-white" style="background:var(--color-primary)">
                    <i class="fas fa-sync-alt me-1"></i>Actualizar
                </button>
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1">
                    <i class="fas fa-print me-1"></i>Imprimir
                </button>
            </div>

            <!-- ESTADO CARGA -->
            <div id="loadingState" class="text-center py-5">
                <div class="spinner-border text-secondary" style="width:2.5rem;height:2.5rem"></div>
                <p class="text-muted mt-3">Cargando datos...</p>
            </div>
            <div id="emptyState" class="text-center py-5">
                <i class="fas fa-folder-open fa-3x text-muted mb-3 d-block"></i>
                <p class="text-muted">No hay informes finalizados con km registrados para esta semana.</p>
            </div>

            <!-- RANGO -->
            <div id="rangoTexto" class="text-muted small mb-3 fw-bold ps-2 border-start border-4" style="border-color:var(--color-primary)!important;display:none"></div>

            <!-- TABLA RESUMEN KM -->
            <div id="seccionKm" class="section-card card mb-4" style="display:none">
                <div class="card-header">
                    <i class="fas fa-tachometer-alt me-2"></i>Resumen de Kilometraje y Costos
                    <?php if ($puedeGenerarReembolso): ?>
                    <button id="btnReembolso" class="btn btn-sm btn-light rounded-pill px-3 float-end" onclick="irAReembolso()">
                        <i class="fas fa-file-invoice-dollar me-1"></i>Generar Reembolso
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover tbl-km mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3" style="width:220px">Colaborador / Día</th>
                                    <th class="py-3">Sucursales</th>
                                    <th class="text-center py-3">KM Inicial → Final</th>
                                    <th class="text-center py-3 fw-bold">KM Día</th>
                                    <th class="text-end pe-4 py-3">Costo Estimado</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyKm"></tbody>
                            <tfoot id="tfootKm"></tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- DETALLE POR INFORME -->
            <div id="seccionDetalle" style="display:none">
                <h5 class="fw-bold mb-3" style="color:var(--color-primary)">
                    <i class="fas fa-clipboard-list me-2"></i>Detalle de Visitas por Informe
                </h5>
                <div id="detalleContainer"></div>
            </div>

        </div><!-- /container-fluid -->
    </div>
</div>

<!-- MODAL ZOOM -->
<div class="modal fade" id="zoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-body text-center p-0 position-relative">
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
<script>
const DEP_FIJA   = <?= $depFija ?>;
let datosGlobal  = null;

$(document).ready(function () {
    cargarResumen();
});

function cargarResumen() {
    const semana = $('#inputSemana').val();
    const anio   = $('#inputAnio').val();
    const costo  = parseFloat($('#inputCosto').val()) || 5;

    if (!semana) { Swal.fire('Atención', 'Ingresa el número de semana', 'warning'); return; }

    $('#loadingState').show();
    $('#emptyState,#rangoTexto,#seccionKm,#seccionDetalle').hide();

    Promise.all([
        $.post('ajax/reporte_semanal_handler.php',    { action: 'get_datos_semanales', numero_semana: semana, anio }),
        $.post('ajax/resumen_semanal_get_detalle.php', { numero_semana: semana, anio })
    ]).then(([resKm, resDet]) => {
        $('#loadingState').hide();

        if (!resKm.success)  { Swal.fire('Error', resKm.message,  'error'); return; }
        if (!resDet.success) { Swal.fire('Error', resDet.message, 'error'); return; }

        if (!resKm.detalle || resKm.detalle.length === 0) {
            $('#emptyState').show(); return;
        }

        datosGlobal = { resKm, resDet, costo, semana, anio };

        $('#rangoTexto').text(`Semana #${semana} | ${resKm.rango.desde} al ${resKm.rango.hasta}`).show();

        renderKm(resKm, costo);
        renderDetalle(resDet.informes);

        $('#seccionKm,#seccionDetalle').show();

    }).catch(err => {
        $('#loadingState').hide();
        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
        console.error(err);
    });
}

/* ===================== TABLA KM ===================== */
function renderKm(resKm, costo) {
    const tbody = $('#tbodyKm').empty();
    const tfoot = $('#tfootKm').empty();

    const agrupado = {};
    resKm.detalle.forEach(d => {
        const k = d.cod_operario;
        if (!agrupado[k]) agrupado[k] = { name: `${d.Nombre} ${d.Apellido}`, logs: [] };
        agrupado[k].logs.push(d);
    });

    let sumKm = 0, sumCosto = 0, sumTotal = 0;

    for (const opId in agrupado) {
        const op       = agrupado[opId];
        const resumenOp = resKm.resumen.find(r => r.CodOperario == opId);
        const kmOp      = parseFloat(resumenOp?.km_total) || 0;
        const combOp    = kmOp * costo;
        const totalOp   = combOp + DEP_FIJA;

        sumKm    += kmOp;
        sumCosto += combOp;
        sumTotal += totalOp;

        tbody.append(`
            <tr class="op-header-row">
                <td colspan="5" class="ps-4 py-2">
                    <i class="fas fa-user-circle me-2" style="color:var(--color-accent)"></i>${op.name}
                </td>
            </tr>
        `);

        op.logs.forEach(log => {
            const kmDia  = (parseFloat(log.km_final) || 0) - (parseFloat(log.km_inicial) || 0);
            const costDia = kmDia * costo;
            const fotoHtml = log.km_foto_final
                ? `<i class="fas fa-camera ms-1 text-muted" style="cursor:pointer" onclick="zoomFoto('uploads/informes/${log.km_foto_final}')"></i>` : '';
            tbody.append(`
                <tr class="small text-muted">
                    <td class="ps-5 py-1">${log.fecha}</td>
                    <td class="py-1 small">${log.sucursales_list || '<span class="opacity-50">—</span>'}</td>
                    <td class="text-center py-1">${fmtNum(log.km_inicial)} → ${fmtNum(log.km_final)} ${fotoHtml}</td>
                    <td class="text-center py-1 fw-bold">${kmDia} km</td>
                    <td class="text-end pe-4 py-1">C$ ${costDia.toFixed(2)}</td>
                </tr>
            `);
        });

        tbody.append(`
            <tr class="fw-bold border-bottom">
                <td colspan="3" class="text-end py-2 ps-5" style="color:var(--color-primary)">Total ${op.name} (incluye C$ ${DEP_FIJA} fijo):</td>
                <td class="text-center py-2" style="color:var(--color-primary)">${fmtNum(kmOp)} km</td>
                <td class="text-end pe-4 py-2">C$ ${totalOp.toFixed(2)}</td>
            </tr>
        `);
    }

    tfoot.append(`
        <tr>
            <td colspan="3" class="text-end fw-bold py-3 ps-4 fs-6">TOTAL SEMANAL ESTIMADO:</td>
            <td class="text-center fw-bold py-3" style="color:var(--color-primary)">${fmtNum(sumKm)} km</td>
            <td class="text-end pe-4 fw-bold py-3" style="color:var(--color-primary)">C$ ${sumTotal.toFixed(2)}</td>
        </tr>
    `);
}

/* ===================== DETALLE INFORMES ===================== */
function renderDetalle(informes) {
    const container = $('#detalleContainer').empty();

    // Agrupar por operario
    const grupos = {};
    informes.forEach(inf => {
        const k = inf.CodOperario;
        if (!grupos[k]) grupos[k] = { name: `${inf.Nombre} ${inf.Apellido}`, informes: [] };
        grupos[k].informes.push(inf);
    });

    for (const opId in grupos) {
        const g = grupos[opId];
        const opBlock = $(`<div class="mb-4">
            <h6 class="fw-bold mb-3" style="color:var(--color-primary)">
                <i class="fas fa-user-circle me-2" style="color:var(--color-accent)"></i>${g.name}
            </h6>
        </div>`);

        g.informes.forEach(inf => {
            const kmRecorrido = (parseFloat(inf.km_final) - parseFloat(inf.km_inicial)).toFixed(2);
            const fotoIni = inf.km_foto_inicial ? `<img src="uploads/informes/${inf.km_foto_inicial}" class="rounded shadow-sm ms-1" style="width:36px;height:36px;object-fit:cover;cursor:zoom-in" onclick="zoomFoto(this.src)">` : '';
            const fotoFin = inf.km_foto_final   ? `<img src="uploads/informes/${inf.km_foto_final}"   class="rounded shadow-sm ms-1" style="width:36px;height:36px;object-fit:cover;cursor:zoom-in" onclick="zoomFoto(this.src)">` : '';

            let visitasHtml = '';
            if (!inf.visitas || inf.visitas.length === 0) {
                visitasHtml = '<p class="text-muted small fst-italic ps-2">Sin visitas registradas.</p>';
            } else {
                inf.visitas.forEach(v => {
                    let tareasHtml = '';
                    if (v.tareas && v.tareas.length > 0) {
                        v.tareas.forEach(t => {
                            const badge = t.completado_100 == 1
                                ? '<span class="badge bg-success rounded-pill" style="font-size:.7rem">100% Hecho</span>'
                                : '<span class="badge bg-warning text-dark rounded-pill" style="font-size:.7rem">Parcial</span>';
                            let fotosT = '';
                            if (t.fotos) {
                                t.fotos.split('||').forEach(f => {
                                    fotosT += `<img src="uploads/evidencias/${f}" class="rounded-1 ms-1" style="width:28px;height:28px;object-fit:cover;cursor:zoom-in" onclick="zoomFoto(this.src)">`;
                                });
                            }
                            tareasHtml += `
                                <div class="tarea-row">
                                    ${badge}
                                    <div class="flex-grow-1">
                                        <small class="fw-bold d-block">${escHtml(t.trabajo_realizado || 'Sin descripción')}</small>
                                        <small class="text-muted">${escHtml(t.trabajo_realizado || '')}</small>
                                    </div>
                                    <div>${fotosT}</div>
                                </div>`;
                        });
                    } else {
                        tareasHtml = '<small class="text-muted fst-italic">Sin tareas registradas.</small>';
                    }

                    let comprasHtml = '';
                    if (v.compras && v.compras.length > 0) {
                        v.compras.forEach(c => {
                            const fotoC = c.foto_factura ? `<img src="uploads/compras/${c.foto_factura}" class="rounded-1 ms-2" style="width:26px;height:26px;object-fit:cover;cursor:zoom-in" onclick="zoomFoto(this.src)">` : '';
                            comprasHtml += `<div class="compra-row"><span><i class="fas fa-file-invoice me-1 text-muted"></i>${escHtml(c.detalle)}</span><div class="d-flex align-items-center"><span class="fw-bold text-danger">C$ ${parseFloat(c.monto).toFixed(2)}</span>${fotoC}</div></div>`;
                        });
                    }

                    const llegada = v.hora_llegada ? v.hora_llegada.substring(0,5) : '—';
                    const salida  = v.hora_salida  ? v.hora_salida.substring(0,5)  : '—';
                    const reemb   = v.reembolso_id ? '<span class="stat-pill ms-2"><i class="fas fa-check-circle me-1"></i>Reembolsado</span>' : '';

                    visitasHtml += `
                        <div class="visita-card">
                            <div class="visita-card-header">
                                <span><i class="fas fa-map-marker-alt me-2 text-danger"></i>${escHtml(v.nombre_sucursal || v.cod_sucursal)}</span>
                                <div>
                                    <span class="info-chip"><i class="far fa-clock"></i>${llegada} – ${salida}</span>
                                    ${reemb}
                                </div>
                            </div>
                            <div class="visita-card-body">
                                ${v.materiales_stock ? `<p class="small mb-2 text-muted"><i class="fas fa-boxes me-1"></i><strong>Materiales:</strong> ${escHtml(v.materiales_stock)}</p>` : ''}
                                <div class="mb-2">
                                    <small class="fw-bold text-uppercase text-muted d-block mb-1" style="font-size:.7rem;letter-spacing:.5px">Trabajos realizados</small>
                                    ${tareasHtml}
                                </div>
                                ${comprasHtml ? `<div class="mt-2"><small class="fw-bold text-uppercase text-muted d-block mb-1" style="font-size:.7rem;letter-spacing:.5px">Facturas y gastos</small>${comprasHtml}</div>` : ''}
                            </div>
                        </div>`;
                });
            }

            const block = $(`
                <div class="informe-block">
                    <div class="informe-block-header">
                        <i class="fas fa-calendar-day fa-lg"></i>
                        <div>
                            <span class="fw-bold">${inf.fecha}</span>
                            <span class="km-badge ms-2">KM: ${fmtNum(inf.km_inicial)} → ${fmtNum(inf.km_final)} (${kmRecorrido} km)</span>
                        </div>
                        <div class="ms-auto d-flex align-items-center gap-2">
                            ${fotoIni}${fotoFin}
                            ${inf.monto_caja_chica > 0 ? `<span class="km-badge"><i class="fas fa-wallet me-1"></i>Caja: C$ ${parseFloat(inf.monto_caja_chica).toFixed(2)}</span>` : ''}
                        </div>
                    </div>
                    <div class="p-3">${visitasHtml}</div>
                </div>
            `);
            opBlock.append(block);
        });

        container.append(opBlock);
    }
}

/* ===================== HELPERS ===================== */
function irAReembolso() {
    if (!datosGlobal) return;
    const { semana, anio, costo } = datosGlobal;
    window.open(`../compras/reembolsos_ia_nuevo.php?id=15&from_km=1&semana=${semana}&anio=${anio}&costo=${costo}`, '_blank');
}

function zoomFoto(src) {
    $('#zoomImg').attr('src', src);
    new bootstrap.Modal(document.getElementById('zoomModal')).show();
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtNum(n) {
    return parseFloat(n || 0).toLocaleString('es-NI');
}
</script>
</body>
</html>
