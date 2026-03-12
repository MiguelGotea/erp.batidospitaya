<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('dashboard_rfm', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeDescargar = tienePermiso('dashboard_rfm', 'descargar', $cargoOperario);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard RFM 360° - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/dashboard_rfm.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Dashboard RFM & Segmentación'); ?>

            <div class="container-fluid py-4">

                <!-- 🔝 SECCIÓN 0 — Filtros Globales -->
                <div class="glass-card p-4 mb-4 shadow-sm border-0">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Rango de Análisis</label>
                            <div class="input-group input-group-sm">
                                <input type="date" id="fecha_inicio" class="form-control rounded-start-pill"
                                    name="fecha_inicio" value="<?php echo date('Y-m-d', strtotime('-90 days')); ?>">
                                <input type="date" id="fecha_fin" class="form-control rounded-end-pill" name="fecha_fin"
                                    value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Sucursal</label>
                            <select class="form-select form-select-sm rounded-pill" name="sucursal"
                                id="filtro_sucursal">
                                <option value="todas">Todas las Sucursales</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Tipo Cliente</label>
                            <select class="form-select form-select-sm rounded-pill" name="tipo_cliente"
                                id="tipo_cliente">
                                <option value="club">Solo Club</option>
                                <option value="general">Solo General</option>
                                <option value="todos">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Umbral Perdido (Días)</label>
                            <input type="number" class="form-control form-control-sm rounded-pill text-center"
                                name="umbral_perdido" id="umbral_perdido" value="60">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill shadow-sm">
                                <i class="fas fa-sync-alt me-2"></i> Actualizar Inteligencia
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 📌 SECCIÓN 1 — KPIs Resumen -->
                <div class="row g-3 mb-4">
                    <!-- Salud de la Base (Global) -->
                    <div class="col-xl-6">
                        <div class="d-flex align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-dark small text-uppercase letter-spacing-1">
                                <i class="fas fa-heartbeat me-2 text-danger"></i>Salud de la Base (Global)
                            </h6>
                            <hr class="flex-grow-1 ms-3 opacity-25">
                        </div>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <div class="glass-card kpi-card-new p-2 text-center position-relative h-100" data-bs-toggle="tooltip" data-bs-html="true" id="tipClubActivos">
                                    <span class="scope-badge scope-global">Global</span>
                                    <div class="icon-circle bg-primary-light text-primary mb-2 mx-auto sm"><i class="fas fa-users"></i></div>
                                    <div class="text-secondary x-small fw-bold">Activos</div>
                                    <h4 class="fw-bold mb-0" id="kpiTotalClub">-</h4>
                                    <div class="x-small fw-bold text-primary" id="kpiTotalClubPerc"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="glass-card kpi-card-new p-2 text-center position-relative h-100" data-bs-toggle="tooltip" data-bs-html="true" id="tipEnRiesgo">
                                    <span class="scope-badge scope-global">Global</span>
                                    <div class="icon-circle bg-warning-light text-warning mb-2 mx-auto sm"><i class="fas fa-exclamation-triangle"></i></div>
                                    <div class="text-secondary x-small fw-bold">En Riesgo</div>
                                    <h4 class="fw-bold mb-0" id="kpiEnRiesgo">-</h4>
                                    <div class="x-small fw-bold text-warning" id="kpiEnRiesgoPerc"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="glass-card kpi-card-new p-2 text-center position-relative h-100" data-bs-toggle="tooltip" data-bs-html="true" id="tipPerdidos">
                                    <span class="scope-badge scope-global">Global</span>
                                    <div class="icon-circle bg-danger-light text-danger mb-2 mx-auto sm"><i class="fas fa-user-slash"></i></div>
                                    <div class="text-secondary x-small fw-bold">Perdidos</div>
                                    <h4 class="fw-bold mb-0" id="kpiPerdidos">-</h4>
                                    <div class="x-small fw-bold text-danger" id="kpiPerdidosPerc"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="glass-card kpi-card-new p-2 text-center position-relative h-100" data-bs-toggle="tooltip" data-bs-html="true" id="tipChurnTotal">
                                    <span class="scope-badge scope-global">Global</span>
                                    <div class="icon-circle bg-red-light text-red mb-2 mx-auto sm"><i class="fas fa-door-open"></i></div>
                                    <div class="text-secondary x-small fw-bold">Tasa Churn</div>
                                    <h4 class="fw-bold mb-0" id="kpiChurn">-</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rendimiento (Periodo) -->
                    <div class="col-xl-6">
                        <div class="d-flex align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-dark small text-uppercase letter-spacing-1">
                                <i class="fas fa-chart-line me-2 text-success"></i>Rendimiento (Periodo)
                            </h6>
                            <hr class="flex-grow-1 ms-3 opacity-25">
                        </div>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <div class="glass-card kpi-card-new p-2 text-center position-relative h-100" data-bs-toggle="tooltip" data-bs-html="true" id="tipNuevos">
                                    <span class="scope-badge scope-period">Periodo</span>
                                    <div class="icon-circle bg-success-light text-success mb-2 mx-auto sm"><i class="fas fa-user-plus"></i></div>
                                    <div class="text-secondary x-small fw-bold">Nuevos</div>
                                    <h4 class="fw-bold mb-0" id="kpiNuevos">-</h4>
                                    <div class="x-small fw-bold" id="kpiNuevosTrend"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="glass-card kpi-card-new p-2 text-center position-relative h-100" data-bs-toggle="tooltip" data-bs-html="true" id="tipParticipation">
                                    <span class="scope-badge scope-period">Periodo</span>
                                    <div class="icon-circle bg-indigo-light text-indigo mb-2 mx-auto sm"><i class="fas fa-chart-pie"></i></div>
                                    <div class="text-secondary x-small fw-bold">Part. Ingresos</div>
                                    <h4 class="fw-bold mb-0" id="kpiParticipation">-</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="glass-card kpi-card-new p-2 text-center position-relative h-100" data-bs-toggle="tooltip" data-bs-html="true" id="tipTicket">
                                    <span class="scope-badge scope-period">Periodo</span>
                                    <div class="icon-circle bg-info-light text-info mb-2 mx-auto sm"><i class="fas fa-receipt"></i></div>
                                    <div class="text-secondary x-small fw-bold">Ticket Club</div>
                                    <h4 class="fw-bold mb-0" id="kpiTicket">-</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="glass-card kpi-card-new p-2 text-center position-relative h-100" data-bs-toggle="tooltip" data-bs-html="true" id="tipRetention">
                                    <span class="scope-badge scope-period">Periodo</span>
                                    <div class="icon-circle bg-teal-light text-teal mb-2 mx-auto sm"><i class="fas fa-percentage"></i></div>
                                    <div class="text-secondary x-small fw-bold">Retención</div>
                                    <h4 class="fw-bold mb-0" id="kpiRetention">-</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 📊 SECCIÓN 2 — Distribución de Segmentos -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-4">
                        <div class="glass-card p-4 h-100">
                            <h6 class="fw-bold mb-4">Distribución RFM</h6>
                            <canvas id="chartSegments" style="max-height: 250px;"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="glass-card p-4 h-100">
                            <h6 class="fw-bold mb-4">Evolución de Pedidos por Periodo</h6>
                            <canvas id="chartEvolution" style="max-height: 250px;"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="glass-card p-4 h-100">
                            <h6 class="fw-bold mb-4">Ingresos por Segmento</h6>
                            <canvas id="chartSegmentRevenue" style="max-height: 250px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- 👤 SECCIÓN 3 — Tabla Individual RFM -->
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Listado Maestro de Clientes</h5>
                        <div class="input-group input-group-sm w-25">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search"></i></span>
                            <input type="text" id="tableSearch" class="form-control border-start-0"
                                placeholder="Buscar por nombre o membresía...">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle premium-table" id="rfmTableMaster">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Sucursal</th>
                                    <th>Recencia</th>
                                    <th>Frecuencia</th>
                                    <th>Monetario</th>
                                    <th>Ticket</th>
                                    <th>Score RFM</th>
                                    <th>Segmento</th>
                                    <th>Antigüedad</th>
                                    <th>Últ. Prod</th>
                                    <th style="width: 50px;">Acciones</th>
                                </tr>
                                <!-- Fila de Filtros -->
                                <tr class="filter-row bg-light">
                                    <td><input type="text" class="form-control form-control-sm column-filter" data-column="ClienteNombre" placeholder="Filtrar..."></td>
                                    <td>
                                        <select class="form-select form-select-sm column-filter" data-column="Sucursal">
                                            <option value="">Todas</option>
                                        </select>
                                    </td>
                                    <td><input type="number" class="form-control form-control-sm column-filter" data-column="Recency" placeholder=">="></td>
                                    <td><input type="number" class="form-control form-control-sm column-filter" data-column="Frequency" placeholder=">="></td>
                                    <td><input type="number" class="form-control form-control-sm column-filter" data-column="Monetary" placeholder=">="></td>
                                    <td><input type="number" class="form-control form-control-sm column-filter" data-column="TicketPromedio" placeholder=">="></td>
                                    <td></td>
                                    <td>
                                        <select class="form-select form-select-sm column-filter" data-column="Segment">
                                            <option value="">Todos</option>
                                        </select>
                                    </td>
                                    <td><input type="number" class="form-control form-control-sm column-filter" data-column="Antiguedad" placeholder=">="></td>
                                    <td><input type="text" class="form-control form-control-sm column-filter" data-column="UltimoProducto" placeholder="..."></td>
                                    <td></td>
                                </tr>
                            </thead>
                            <tbody id="rfmTableBody"></tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-3 px-2">
                        <div class="small text-muted" id="paginationInfo">Mostrando 0 de 0 socios</div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="paginationControls">
                                <!-- Botones de paginación via JS -->
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- 🏪 SECCIÓN 4 — Análisis por Sucursal -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Productividad (RFM Score)</h6>
                            <canvas id="chartBranchScores"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4 text-nowrap">
                            <h6 class="fw-bold mb-4">Distribución de Segmentos (%)</h6>
                            <canvas id="chartBranchDistribution"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Ticket Promedio por Sucursal</h6>
                            <canvas id="chartBranchTicket"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Top 5 LTV por Sucursal</h6>
                            <div id="branchTopLTV" class="scroller" style="max-height: 300px;"></div>
                        </div>
                    </div>
                </div>

                <!-- 🧠 SECCIÓN 5 — Hábitos de Consumo -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-6">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Mapa de Calor: Intensidad de Consumo (Hora vs Día)</h6>
                            <canvas id="chartHeatmap"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Productos Más Vendidos (Club)</h6>
                            <div id="topProductsList"></div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Distribución por Medida</h6>
                            <div style="height: 200px; position: relative;">
                                <canvas id="chartHabitMeasure"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Distribución por Modalidad</h6>
                            <div style="height: 200px; position: relative;">
                                <canvas id="chartHabitModality"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Uso de Promociones</h6>
                            <div style="height: 200px; position: relative;">
                                <canvas id="chartHabitPromo"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 📤 SECCIÓN 6 — Exportación -->
                <div class="glass-card p-4 text-center border-dashed border-primary">
                    <h5 class="fw-bold mb-1">Centro de Exportación</h5>
                    <p class="text-muted small">Descarga los datos procesados para análisis externo</p>
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <?php if ($puedeDescargar): ?>
                            <button class="btn btn-success rounded-pill px-4" id="btnExportFull">
                                <i class="fas fa-file-excel me-2"></i> Tabla Maestra (Excel)
                            </button>
                            <button class="btn btn-outline-success rounded-pill px-4" id="btnExportSummary">
                                <i class="fas fa-file-pdf me-2"></i> Reporte Ejecutivo (PDF)
                            </button>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 mb-0">No tiene permisos de descarga habilitados.</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modales, Scripts y Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-gradient-primary text-white">
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-graduation-cap me-2"></i>Guía RFM 360°
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 scroller" style="max-height: 70vh; overflow-y: auto;">
                    <div class="doc-section">
                        <div class="doc-title text-primary"><i class="fas fa-layer-group"></i> Lógica del Panel: Salud
                            vs Rendimiento</div>
                        <p class="small text-muted">Para un análisis preciso, el panel separa los datos en dos
                            dimensiones clave:</p>
                        <ul class="doc-list">
                            <li><b>Datos de Salud (Globales):</b> Reflejan el estado real del negocio HOY. No dependen
                                de las fechas seleccionadas. (Ej: Quiénes están Perdidos o en Riesgo).</li>
                            <li><b>Datos de Rendimiento (Periodo):</b> Reflejan qué pasó en el rango de fechas elegido.
                                (Ej: Cuántas ventas hubo o cuántos socios nuevos se registraron).</li>
                        </ul>
                        <div class="alert alert-info py-2 small mb-0 mt-2">
                            <i class="fas fa-info-circle me-1"></i> <b>Nota sobre Porcentajes:</b> Los indicadores se
                            calculan sobre el <b>Universo Total</b> de socios. Si la suma de Activos + Perdidos no llega
                            al 100%, la diferencia representa a los socios registrados que <b>aún no han realizado su
                                primera compra</b>.
                        </div>
                    </div>

                    <div class="doc-section">
                        <div class="doc-title text-primary"><i class="fas fa-tachometer-alt"></i> Indicadores Clave
                            (KPIs)</div>
                        <div class="doc-table">
                            <table class="table table-sm table-borderless mb-0 small">
                                <thead>
                                    <tr>
                                        <th>Métrica</th>
                                        <th>Criterio de Cálculo</th>
                                        <th>Tipo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><b>Club Activos</b></td>
                                        <td>Socios con última compra &le; Umbral de Perdido.</td>
                                        <td><span class="method-tag tag-global">Global</span></td>
                                    </tr>
                                    <tr>
                                        <td><b>Nuevos Periodo</b></td>
                                        <td>Socios registrados entre Fecha Inicio y Fin.</td>
                                        <td><span class="method-tag tag-period">Periodo</span></td>
                                    </tr>
                                    <tr>
                                        <td><b>En Riesgo</b></td>
                                        <td>Socios con inactividad entre 50% y 100% del Umbral.</td>
                                        <td><span class="method-tag tag-global">Global</span></td>
                                    </tr>
                                    <tr>
                                        <td><b>Perdidos</b></td>
                                        <td>Socios con inactividad mayor al Umbral configurado.</td>
                                        <td><span class="method-tag tag-global">Global</span></td>
                                    </tr>
                                    <tr>
                                        <td><b>Tasa de Retención</b></td>
                                        <td>% de socios del periodo previo que volvieron en el actual.</td>
                                        <td><span class="method-tag tag-period">Periodo</span></td>
                                    </tr>
                                    <tr>
                                        <td><b>Part. Ingresos</b></td>
                                        <td>% de la venta que proviene de socios Club vs General.</td>
                                        <td><span class="method-tag tag-period">Periodo</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="doc-section">
                        <div class="doc-title text-primary"><i class="fas fa-chart-pie"></i> Visualizaciones y Gráficos
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="small fw-bold mb-1">Mapa de Calor</h6>
                                <p class="x-small text-muted mb-0">Cruza Hora vs Día para identificar picos de demanda.
                                    Ayuda a planificar turnos y promociones por hora.</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="small fw-bold mb-1">Distribución 100% Sug.</h6>
                                <p class="x-small text-muted mb-0">Normaliza las sucursales para ver qué tan "Sana" está
                                    la base de cada una (proporción de Leales vs Perdidos).</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="small fw-bold mb-1">Hábitos de Consumo</h6>
                                <p class="x-small text-muted mb-0">Analiza Medidas (S/M/L) y Modalidades
                                    (Delivery/Local) preferidas por los socios.</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="small fw-bold mb-1">Evolución de Pedidos</h6>
                                <p class="x-small text-muted mb-0">Muestra la tendencia de transacciones semana a semana
                                    dentro del periodo.</p>
                            </div>
                        </div>
                    </div>

                    <div class="doc-section">
                        <div class="doc-title text-primary"><i class="fas fa-users-cog"></i> Modelo RFM (Recencia,
                            Frecuencia, Monto)</div>
                        <p class="small mb-2">Cada cliente recibe una puntuación del 1 al 5 comparándolo con el resto de
                            la base:</p>
                        <ul class="doc-list">
                            <li><b>Recencia:</b> Tiempo desde el último pedido.</li>
                            <li><b>Frecuencia:</b> Cantidad de pedidos históricos (Lifetime).</li>
                            <li><b>Monetario:</b> Valor total invertido por el cliente (Lifetime).</li>
                        </ul>
                        <div class="alert alert-light border small py-2 mb-0">
                            <b>Nota:</b> Los segmentos "Campeones", "Leales" y "Estrategas" se basan en el
                            comportamiento histórico total para mayor estabilidad.
                        </div>
                    </div>

                    <div class="doc-section mb-0">
                        <div class="doc-title text-primary"><i class="fas fa-question-circle"></i> Ayuda Visual en Tabla
                        </div>
                        <ul class="doc-list">
                            <li><span class="text-danger fw-bold">Rojo:</span> Cliente en estado PERDIDO (superó el
                                umbral).</li>
                            <li><span class="text-warning fw-bold">Naranja:</span> Cliente EN RIESGO (próximo a
                                perderse).</li>
                            <li><span class="text-success fw-bold">Verde:</span> Cliente ACTIVO (compra reciente).</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/dashboard_rfm.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>