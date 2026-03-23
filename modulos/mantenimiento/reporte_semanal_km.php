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
            cargarSemanas();
        });

        async function cargarSemanas() {
            try {
                const response = await $.post('ajax/reporte_semanal_handler.php', { action: 'get_semanas' });
                if (response.success) {
                    let html = '<option value="">Seleccione una semana...</option>';
                    response.semanas.forEach(s => {
                        html += `<option value="${s.id}" data-inicio="${s.fecha_inicio}" data-fin="${s.fecha_fin}">S${s.numero_semana} (${s.fecha_inicio} al ${s.fecha_fin})</option>`;
                    });
                    $('#semanaSelector').html(html);
                }
            } catch (error) {
                console.error(error);
            }
        }

        async function cargarReporte() {
            const semanaId = $('#semanaSelector').val();
            const costoKm = parseFloat($('#inputCostoKm').val()) || 0;

            if (!semanaId) {
                Swal.fire('Atención', 'Selección de semana requerida', 'warning');
                return;
            }

            Swal.fire({
                title: 'Generando reporte...',
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                // 1. Guardar costo KM en los registros de esa semana
                await $.post('ajax/reporte_semanal_handler.php', { 
                    action: 'guardar_costo_km',
                    semana_id: semanaId,
                    costo_km: costoKm
                });

                // 2. Obtener datos para la tabla
                const res = await $.post('ajax/reporte_semanal_handler.php', { 
                    action: 'get_datos_semanales',
                    semana_id: semanaId
                });

                if (res.success) {
                    $('#vistaVacia').addClass('d-none');
                    $('#resultadoReporte').removeClass('d-none');
                    $('#rangoFechasTexto').text(`Rango: ${res.rango.desde} al ${res.rango.hasta}`);
                    
                    let html = '';
                    let sumKm = 0;
                    let sumComb = 0;
                    let sumDep = 0;
                    let sumTotal = 0;

                    res.datos.forEach(row => {
                        const km = parseFloat(row.km_total) || 0;
                        const combustible = km * costoKm;
                        const dep = depreciacionFija; // El usuario pidió 150 fijo semanal
                        const total = combustible + dep;

                        sumKm += km;
                        sumComb += combustible;
                        sumDep += dep;
                        sumTotal += total;

                        // Si ya había un costo guardado y el input está en 0, sugerir usar el guardado o informar
                        if (row.costo_km_guardado > 0 && costoKm == 0) {
                            // Opcional: llenar el input si viene en 0
                            $('#inputCostoKm').val(row.costo_km_guardado);
                        }

                        html += `
                            <tr>
                                <td class="ps-4 fw-bold text-dark">${row.Nombre} ${row.Apellido}</td>
                                <td class="text-center">${km.toLocaleString()} km</td>
                                <td class="text-center">$${combustible.toFixed(2)}</td>
                                <td class="text-center">$${dep.toFixed(2)}</td>
                                <td class="text-end pe-4 fw-bold text-dark">$${total.toFixed(2)}</td>
                            </tr>
                        `;
                    });

                    if (res.datos.length === 0) {
                        html = '<tr><td colspan="5" class="text-center py-4">No se encontraron informes para esta semana</td></tr>';
                    }

                    $('#tablaCuerpo').html(html);
                    $('#totalKm').text(sumKm.toLocaleString() + ' km');
                    $('#totalCombustible').text('$' + sumComb.toFixed(2));
                    $('#totalDepreciacion').text('$' + sumDep.toFixed(2));
                    $('#totalFinal').text('$' + sumTotal.toFixed(2));

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
