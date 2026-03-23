<?php
// reporte_semanal_km.php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso reporte_semanal
if (!tienePermiso('agenda_mantenimiento', 'reporte_semanal', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Semanal de KM y Costos</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css">
    <style>
        :root {
            --color-header-tabla: #0E544C;
            --color-principal: #51B8AC;
        }

        body {
            background-color: #f4f7f6;
        }

        .card-reporte {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .table-premium thead th {
            background: var(--color-header-tabla) !important;
            color: white !important;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 12px 15px;
        }

        .total-row {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            .main-container {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .card-reporte {
                box-shadow: none !important;
            }

            .table-premium thead th {
                background: #0E544C !important;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Reporte Semanal de KM y Costos'); ?>

            <div class="container-fluid p-4">
                <!-- Filtros del Reporte -->
                <div class="card card-reporte mb-4 no-print">
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Número de Semana</label>
                                <div class="input-group input-group-lg">
                                    <input type="number" id="inputSemana" class="form-control rounded-start-3" placeholder="Ej: 12">
                                    <span class="input-group-text bg-light small text-muted" id="infoSemanaActual">Hoy: S-</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Costo por KM Actual</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">C$</span>
                                    <input type="number" id="inputCostoKm" step="0.01" value="5" class="form-control rounded-end-3">
                                </div>
                            </div>
                            <div class="col-md-6 d-flex gap-2">
                                <button onclick="cargarReporte()" class="btn btn-primary btn-lg rounded-pill px-4 flex-grow-1" style="background-color: var(--color-header-tabla); border:none;">
                                    <i class="fas fa-sync-alt me-2"></i>Generar y Guardar
                                </button>
                                <button onclick="window.print()" class="btn btn-outline-dark btn-lg rounded-pill px-4">
                                    <i class="fas fa-print me-2"></i>Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de Resultados -->
                <div id="resultadoReporte" class="card card-reporte overflow-hidden d-none">
                    <div class="card-header bg-white border-0 p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0 fw-bold text-dark">Resumen de Consumo Semanal</h5>
                                <p class="text-muted small mb-0" id="rangoFechasTexto"></p>
                            </div>
                            <img src="../../core/assets/img/icon12.png" height="40" class="d-none d-print-block">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-premium mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Colaborador</th>
                                        <th class="text-center">KM Recorridos</th>
                                        <th class="text-center">Costo Combustible</th>
                                        <th class="text-center">Depreciación Fija</th>
                                        <th class="text-end pe-4">Total a Pagar</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaCuerpo">
                                    <!-- Cargado vía AJAX -->
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td class="ps-4 text-end">TOTALES:</td>
                                        <td id="totalKm" class="text-center">0</td>
                                        <td id="totalCombustible" class="text-center">C$ 0.00</td>
                                        <td id="totalDepreciacion" class="text-center">C$ 0.00</td>
                                        <td id="totalFinal" class="text-end pe-4">C$ 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 p-4 text-muted small">
                        * La depreciación fija de C$ 150 se aplica por operario que haya tenido actividad en la semana.<br>
                        * El costo de combustible se calcula multiplicando los KM totales por el costo de KM asignado.
                    </div>
                </div>

                <!-- Vista vacía -->
                <div id="vistaVacia" class="text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-file-invoice text-muted opacity-25" style="font-size: 5rem;"></i>
                    </div>
                    <h5 class="text-muted">Seleccione una semana para generar el reporte</h5>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const depreciacionFija = 150.00;

        $(document).ready(function() {
            cargarSemanaActual();
        });

        async function cargarSemanaActual() {
            try {
                const response = await $.post('ajax/reporte_semanal_handler.php', { action: 'get_current_week' });
                if (response.success && response.numero_semana) {
                    $('#infoSemanaActual').text(`Hoy: S-${response.numero_semana}`);
                    $('#inputSemana').val(response.numero_semana);
                    // Opcional: cargar reporte automáticamente al entrar
                }
            } catch (error) {
                console.error(error);
            }
        }

        async function cargarReporte() {
            const numSemana = $('#inputSemana').val();
            const costoKm = parseFloat($('#inputCostoKm').val()) || 0;

            if (!numSemana) {
                Swal.fire('Atención', 'Número de semana requerido', 'warning');
                return;
            }

            Swal.fire({
                title: 'Generando reporte...',
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                // 1. Guardar costo KM en los registros de esa semana
                const saveRes = await $.post('ajax/reporte_semanal_handler.php', { 
                    action: 'guardar_costo_km',
                    numero_semana: numSemana,
                    costo_km: costoKm
                });

                if (!saveRes.success) {
                    Swal.fire('Error', saveRes.message, 'error');
                    return;
                }

                // 2. Obtener datos para la tabla
                const res = await $.post('ajax/reporte_semanal_handler.php', { 
                    action: 'get_datos_semanales',
                    numero_semana: numSemana
                });

                if (res.success) {
                    $('#vistaVacia').addClass('d-none');
                    $('#resultadoReporte').removeClass('d-none');
                    $('#rangoFechasTexto').text(`Semana #${numSemana} | Rango: ${res.rango.desde} al ${res.rango.hasta}`);

                    let html = '';
                    let sumTotalKm = 0;
                    let sumTotalComb = 0;
                    let sumTotalDep = 0;
                    let sumTotalFinal = 0;

                    // Agrupar detalle por operario
                    const agrupado = {};
                    res.detalle.forEach(d => {
                        const key = d.cod_operario;
                        if (!agrupado[key]) agrupado[key] = { name: `${d.Nombre} ${d.Apellido}`, logs: [] };
                        agrupado[key].logs.push(d);
                    });

                    // Iterar por cada operario que tiene datos
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

                        // Fila de encabezado de Colaborador
                        html += `
                            <tr class="table-light">
                                <td colspan="5" class="ps-4 fw-bold text-dark" style="background-color: #f0f7f6;">
                                    <i class="fas fa-user-circle me-2 text-primary"></i>${op.name}
                                </td>
                            </tr>
                            <tr class="small text-muted bg-white">
                                <th class="ps-5 border-0">Fecha</th>
                                <th class="text-center border-0">KM Inicial</th>
                                <th class="text-center border-0">KM Final</th>
                                <th class="text-center border-0">KM del Día</th>
                                <th class="text-end pe-4 border-0">Subtotal Combustible</th>
                            </tr>
                        `;

                        // Filas de detalle diario
                        op.logs.forEach(log => {
                            const kmDia = (parseFloat(log.km_final) || 0) - (parseFloat(log.km_inicial) || 0);
                            const costoDia = kmDia * costoKm;
                            html += `
                                <tr class="bg-white">
                                    <td class="ps-5">${log.fecha}</td>
                                    <td class="text-center">${parseFloat(log.km_inicial).toLocaleString()}</td>
                                    <td class="text-center">${parseFloat(log.km_final).toLocaleString()}</td>
                                    <td class="text-center fw-bold">${kmDia.toLocaleString()} km</td>
                                    <td class="text-end pe-4 text-muted">C$ ${costoDia.toFixed(2)}</td>
                                </tr>
                            `;
                        });

                        // Fila de resumen del colaborador
                        html += `
                            <tr class="total-row bg-white border-bottom shadow-sm">
                                <td class="ps-4 text-end text-primary fw-bold" colspan="3">Resumen ${op.name}:</td>
                                <td class="text-center fw-bold text-primary">${kmTotalOp.toLocaleString()} km</td>
                                <td class="text-end pe-4 fw-bold text-dark">
                                    <div class="small text-muted">Combustible: C$ ${combustibleOp.toFixed(2)}</div>
                                    <div class="small text-muted border-bottom mb-1">Deprec. Fija: C$ ${depOp.toFixed(2)}</div>
                                    <div class="fs-6">Total: C$ ${totalOp.toFixed(2)}</div>
                                </td>
                            </tr>
                            <tr><td colspan="5" style="height: 20px;" class="bg-light border-0"></td></tr>
                        `;
                    }

                    if (res.resumen.length === 0) {
                        html = '<tr><td colspan="5" class="text-center py-5 text-muted">No se encontraron informes para esta semana</td></tr>';
                    }

                    $('#tablaCuerpo').html(html);
                    $('#totalKm').text(sumTotalKm.toLocaleString() + ' km');
                    $('#totalCombustible').text('C$ ' + sumTotalComb.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#totalDepreciacion').text('C$ ' + sumTotalDep.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                    $('#totalFinal').text('C$ ' + sumTotalFinal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));

                    Swal.close();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Error al procesar la solicitud', 'error');
                console.error(error);
            }
        }
    </script>
</body>

</html>
