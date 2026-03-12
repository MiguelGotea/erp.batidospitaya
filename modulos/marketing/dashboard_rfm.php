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
                        <div class="glass-card p-3 h-100 shadow-sm border-0">
                            <div class="d-flex align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-dark small text-uppercase letter-spacing-1">
                                    <i class="fas fa-heartbeat me-2 text-danger"></i>Salud de la Base (Global)
                                </h6>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="glass-card kpi-card-new p-2 text-center position-relative h-100 bg-white bg-opacity-50 border-0"
                                        data-bs-toggle="tooltip" data-bs-html="true" id="tipClubActivos">
                                        <div class="icon-circle bg-primary-light text-primary mb-2 mx-auto sm"><i
                                                class="fas fa-users"></i></div>
                                        <div class="text-secondary x-small fw-bold">Activos</div>
                                        <h4 class="fw-bold mb-0" id="kpiTotalClub">-</h4>
                                        <div class="x-small fw-bold text-primary" id="kpiTotalClubPerc"></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="glass-card kpi-card-new p-2 text-center position-relative h-100 bg-white bg-opacity-50 border-0"
                                        data-bs-toggle="tooltip" data-bs-html="true" id="tipEnRiesgo">
                                        <div class="icon-circle bg-warning-light text-warning mb-2 mx-auto sm"><i
                                                class="fas fa-exclamation-triangle"></i></div>
                                        <div class="text-secondary x-small fw-bold">En Riesgo</div>
                                        <h4 class="fw-bold mb-0" id="kpiEnRiesgo">-</h4>
                                        <div class="x-small fw-bold text-warning" id="kpiEnRiesgoPerc"></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="glass-card kpi-card-new p-2 text-center position-relative h-100 bg-white bg-opacity-50 border-0"
                                        data-bs-toggle="tooltip" data-bs-html="true" id="tipPerdidos">
                                        <div class="icon-circle bg-danger-light text-danger mb-2 mx-auto sm"><i
                                                class="fas fa-user-slash"></i></div>
                                        <div class="text-secondary x-small fw-bold">Perdidos</div>
                                        <h4 class="fw-bold mb-0" id="kpiPerdidos">-</h4>
                                        <div class="x-small fw-bold text-danger" id="kpiPerdidosPerc"></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="glass-card kpi-card-new p-2 text-center position-relative h-100 bg-white bg-opacity-50 border-0"
                                        data-bs-toggle="tooltip" data-bs-html="true" id="tipChurnTotal">
                                        <div class="icon-circle bg-red-light text-red mb-2 mx-auto sm"><i
                                                class="fas fa-door-open"></i></div>
                                        <div class="text-secondary x-small fw-bold">Tasa Churn</div>
                                        <h4 class="fw-bold mb-0" id="kpiChurn">-</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rendimiento (Periodo) -->
                    <div class="col-xl-6">
                        <div class="glass-card p-3 h-100 shadow-sm border-0">
                            <div class="d-flex align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-dark small text-uppercase letter-spacing-1">
                                    <i class="fas fa-chart-line me-2 text-success"></i>Rendimiento (Periodo)
                                </h6>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="glass-card kpi-card-new p-2 text-center position-relative h-100 bg-white bg-opacity-50 border-0"
                                        data-bs-toggle="tooltip" data-bs-html="true" id="tipNuevos">
                                        <div class="icon-circle bg-success-light text-success mb-2 mx-auto sm"><i
                                                class="fas fa-user-plus"></i></div>
                                        <div class="text-secondary x-small fw-bold">Nuevos</div>
                                        <h4 class="fw-bold mb-0" id="kpiNuevos">-</h4>
                                        <div class="x-small fw-bold" id="kpiNuevosTrend"></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="glass-card kpi-card-new p-2 text-center position-relative h-100 bg-white bg-opacity-50 border-0"
                                        data-bs-toggle="tooltip" data-bs-html="true" id="tipParticipation">
                                        <div class="icon-circle bg-indigo-light text-indigo mb-2 mx-auto sm"><i
                                                class="fas fa-chart-pie"></i></div>
                                        <div class="text-secondary x-small fw-bold">Part. Ingresos</div>
                                        <h4 class="fw-bold mb-0" id="kpiParticipation">-</h4>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="glass-card kpi-card-new p-2 text-center position-relative h-100 bg-white bg-opacity-50 border-0"
                                        data-bs-toggle="tooltip" data-bs-html="true" id="tipTicket">
                                        <div class="icon-circle bg-info-light text-info mb-2 mx-auto sm"><i
                                                class="fas fa-receipt"></i></div>
                                        <div class="text-secondary x-small fw-bold">Ticket Club</div>
                                        <h4 class="fw-bold mb-0" id="kpiTicket">-</h4>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="glass-card kpi-card-new p-2 text-center position-relative h-100 bg-white bg-opacity-50 border-0"
                                        data-bs-toggle="tooltip" data-bs-html="true" id="tipRetention">
                                        <div class="icon-circle bg-teal-light text-teal mb-2 mx-auto sm"><i
                                                class="fas fa-percentage"></i></div>
                                        <div class="text-secondary x-small fw-bold">Retención</div>
                                        <h4 class="fw-bold mb-0" id="kpiRetention">-</h4>
                                    </div>
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
                                <tr class="text-dark bg-light opacity-75">
                                    <th data-column="ClienteNombre" data-type="text">
                                        Cliente <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this, event)"></i>
                                    </th>
                                    <th data-column="Sucursal" data-type="list">
                                        Sucursal <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this, event)"></i>
                                    </th>
                                    <th data-column="Recency" data-type="number">
                                        Recencia <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this, event)"></i>
                                    </th>
                                    <th data-column="Frequency" data-type="number">
                                        Frecuencia <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this, event)"></i>
                                    </th>
                                    <th data-column="Monetary" data-type="number">
                                        Monetario <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this, event)"></i>
                                    </th>
                                    <th data-column="TicketPromedio" data-type="number">
                                        Ticket <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this, event)"></i>
                                    </th>
                                    <th>Score RFM</th>
                                    <th data-column="Segment" data-type="list">
                                        Segmento <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this, event)"></i>
                                    </th>
                                    <th data-column="Antiguedad" data-type="number">
                                        Antigüedad <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this, event)"></i>
                                    </th>
                                    <th data-column="UltimoProducto" data-type="text">
                                        Últ. Prod <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this, event)"></i>
                                    </th>
                                    <th style="width: 50px;">Acciones</th>
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
                    </div>

                    <div class="doc-section">
                        <div class="doc-title text-primary"><i class="fas fa-database"></i> Definición de la Base
                            (Universo)</div>
                        <p class="small mb-2">Para garantizar la integridad de los datos, el dashboard utiliza dos
                            conceptos de "Base":</p>
                        <ul class="doc-list small">
                            <li><b>Universo con Compra:</b> Es el total de socios únicos que han tenido al menos una
                                transacción no anulada. Esta es nuestra base real de clientes.</li>
                            <li><b>Registro Total:</b> Todos los socios en la base de datos (incluye los que nunca han
                                comprado). Se usa solo para auditoría.</li>
                        </ul>
                    </div>

                    <div class="doc-section">
                        <div class="doc-title text-primary"><i class="fas fa-tachometer-alt"></i> Indicadores Clave
                            (KPIs)</div>
                        <div class="doc-table">
                            <table class="table table-sm table-borderless mb-0 small">
                                <thead>
                                    <tr class="border-bottom">
                                        <th colspan="3" class="text-secondary py-2">Salud de la Base (Datos Globales)
                                        </th>
                                    </tr>
                                    <tr>
                                        <th>Métrica</th>
                                        <th>Criterio de Cálculo</th>
                                        <th>Referencia (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><b>Club Activos</b></td>
                                        <td>Socios con última compra &le; Umbral de Perdido.</td>
                                        <td>Sobre Universo con Compra</td>
                                    </tr>
                                    <tr>
                                        <td><b>En Riesgo</b></td>
                                        <td>Inactividad entre 50% y 100% del Umbral.</td>
                                        <td>Sobre Socios Activos</td>
                                    </tr>
                                    <tr>
                                        <td><b>Perdidos</b></td>
                                        <td>Socios con inactividad > Umbral seleccionado.</td>
                                        <td>Sobre Universo con Compra</td>
                                    </tr>
                                    <tr>
                                        <td><b>Tasa Churn</b></td>
                                        <td>Socios que habiendo comprado alguna vez, hoy son "Perdidos".</td>
                                        <td>Sobre Universo con Compra</td>
                                    </tr>
                                </tbody>
                                <thead>
                                    <tr class="border-bottom">
                                        <th colspan="3" class="text-secondary py-2">Rendimiento (Datos del Periodo)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><b>Nuevos Periodo</b></td>
                                        <td>Socios registrados entre Fecha Inicio y Fin.</td>
                                        <td>Nuevos Socios</td>
                                    </tr>
                                    <tr>
                                        <td><b>Ticket Club</b></td>
                                        <td>Promedio invertido por socios en cada transacción.</td>
                                        <td>Consignación Real</td>
                                    </tr>
                                    <tr>
                                        <td><b>Tasa Retención</b></td>
                                        <td>% de socios del periodo previo que repitieron compra.</td>
                                        <td>Fidelización Histórica</td>
                                    </tr>
                                    <tr>
                                        <td><b>Part. Ingresos</b></td>
                                        <td>% de la venta total proveniente de socios Club.</td>
                                        <td>Transacción Completa</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-info py-2 px-3 mb-4 border-0 shadow-sm" style="background: rgba(81, 184, 172, 0.1);">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-map-marker-alt text-teal me-2"></i>
                                <h6 class="fw-bold mb-0 small text-teal">Modelos de Atribución por Sucursal</h6>
                            </div>
                            <p class="small mb-2 text-dark opacity-75">Cuando se elige una sucursal específica, el sistema aplica dos criterios distintos según el tipo de métrica:</p>
                            <ul class="small mb-0 text-dark opacity-75 ps-3">
                                <li><b>Atribución de Origen (Home Branch):</b> Aplica a KPIs de <b>Salud (RFM y Listado Maestro)</b>. Mide el comportamiento de los socios que fueron captados originalmente por esa sucursal, sin importar dónde compren.</li>
                                <li><b>Atribución de Transacción (Operativo):</b> Aplica a KPIs de <b>Rendimiento y Gráficas</b>. Mide las ventas y tickets que ocurrieron físicamente en el local seleccionado.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="doc-section">
                        <div class="doc-title text-primary"><i class="fas fa-chart-pie"></i> Visualizaciones y Fidelidad
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="small fw-bold mb-1">Evolución de Pedidos</h6>
                                <p class="x-small text-muted mb-0">Muestra la actividad de los Socios Club a lo largo del tiempo, agrupada por semanas.</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="small fw-bold mb-1">Distribución por Medida</h6>
                                <p class="x-small text-muted mb-0">Análisis exclusivo para las categorías de <b>Batido y Limonada</b>. Muestra la preferencia de tamaños.</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="small fw-bold mb-1">Uso de Promociones</h6>
                                <p class="x-small text-muted mb-0">Consumo con cupón vs regular para: <b>Batido, Limonada, Bowl, Membresía, Store y Waffles</b>.</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="small fw-bold mb-1">Mapa de Calor</h6>
                                <p class="x-small text-muted mb-0">Intensidad de tráfico de Socios Club por horas. Basado en pedidos únicos por franja horaria.</p>
                            </div>
                        </div>
                    </div>

                    <div class="doc-section">
                        <div class="doc-title text-primary"><i class="fas fa-users-cog"></i> Modelo RFM (Recencia,
                            Frecuencia, Monto)</div>
                        <p class="small mb-2">Cada cliente recibe una puntuación del 1 al 5 comparándolo con el resto de
                            la base:</p>
                        <ul class="doc-list small">
                            <li><b>Recencia:</b> Tiempo transcurrido desde el último pedido.</li>
                            <li><b>Frecuencia:</b> Cantidad de pedidos históricos (Transacciones completas).</li>
                            <li><b>Monetario:</b> Valor total invertido por el cliente (Sumatoria de Facturas).</li>
                        </ul>
                        <div class="alert alert-light border small py-2 mb-0">
                            <b>Nota de Integridad:</b> Un pedido se considera "Club" si al menos una línea está asociada
                            a una membresía. Se contabiliza el monto total de la factura.
                        </div>
                    </div>

                    <div class="doc-section mb-0">
                        <div class="doc-title text-primary"><i class="fas fa-question-circle"></i> Estado de Clientes en
                            Tabla</div>
                        <ul class="doc-list small">
                            <li><span class="text-danger fw-bold">Rojo (Perdido):</span> Superó el umbral de días de
                                inactividad.</li>
                            <li><span class="text-warning fw-bold">Naranja (En Riesgo):</span> Ha pasado más de la mitad
                                del umbral sin comprar.</li>
                            <li><span class="text-success fw-bold">Verde (Activo):</span> Compra reciente dentro del
                                margen esperado.</li>
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