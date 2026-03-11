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
                    <div class="row align-items-center mb-3">
                        <div class="col">
                            <h4 class="fw-bold mb-0 text-gradient-primary">RFM Intelligence <span class="badge bg-primary-light text-primary fs-6 ms-2">v2.0</span></h4>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="$('#pageHelpModal').modal('show')">
                                <i class="fas fa-question-circle me-1"></i> Guía
                            </button>
                        </div>
                    </div>
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Rango de Análisis</label>
                            <div class="input-group input-group-sm">
                                <input type="date" id="fecha_inicio" class="form-control rounded-start-pill" name="fecha_inicio" value="<?php echo date('Y-m-d', strtotime('-90 days')); ?>">
                                <input type="date" id="fecha_fin" class="form-control rounded-end-pill" name="fecha_fin" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Sucursal</label>
                            <select class="form-select form-select-sm rounded-pill" name="sucursal" id="filtro_sucursal">
                                <option value="todas">Todas las Sucursales</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Tipo Cliente</label>
                            <select class="form-select form-select-sm rounded-pill" name="tipo_cliente" id="tipo_cliente">
                                <option value="club">Solo Club</option>
                                <option value="general">Solo General</option>
                                <option value="todos">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Umbral Perdido (Días)</label>
                            <input type="number" class="form-control form-control-sm rounded-pill text-center" name="umbral_perdido" id="umbral_perdido" value="60">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill shadow-sm">
                                <i class="fas fa-sync-alt me-2"></i> Actualizar Inteligencia
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 📌 SECCIÓN 1 — KPIs Resumen -->
                <div class="row g-3 mb-4" id="kpiGrid">
                    <div class="col-md-3 col-xl-1-5">
                        <div class="glass-card kpi-card-new p-3 text-center">
                            <div class="icon-circle bg-primary-light text-primary mb-2 mx-auto"><i class="fas fa-users"></i></div>
                            <div class="text-secondary small">Club Activos</div>
                            <h3 class="fw-bold mb-0" id="kpiTotalClub">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-xl-1-5">
                        <div class="glass-card kpi-card-new p-3 text-center">
                            <div class="icon-circle bg-success-light text-success mb-2 mx-auto"><i class="fas fa-user-plus"></i></div>
                            <div class="text-secondary small">Nuevos Periodo</div>
                            <h3 class="fw-bold mb-0" id="kpiNuevos">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-xl-1-5">
                        <div class="glass-card kpi-card-new p-3 text-center">
                            <div class="icon-circle bg-warning-light text-warning mb-2 mx-auto"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="text-secondary small">En Riesgo</div>
                            <h3 class="fw-bold mb-0" id="kpiEnRiesgo">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-xl-1-5">
                        <div class="glass-card kpi-card-new p-3 text-center">
                            <div class="icon-circle bg-danger-light text-danger mb-2 mx-auto"><i class="fas fa-user-slash"></i></div>
                            <div class="text-secondary small">Perdidos</div>
                            <h3 class="fw-bold mb-0" id="kpiPerdidos">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-xl-1-5">
                        <div class="glass-card kpi-card-new p-3 text-center">
                            <div class="icon-circle bg-info-light text-info mb-2 mx-auto"><i class="fas fa-receipt"></i></div>
                            <div class="text-secondary small">Ticket Club</div>
                            <h3 class="fw-bold mb-0" id="kpiTicket">-</h3>
                        </div>
                    </div>
                    <div class="col-md-3 col-xl-1-5">
                        <div class="glass-card kpi-card-new p-3 text-center">
                            <div class="icon-circle bg-teal-light text-teal mb-2 mx-auto"><i class="fas fa-percentage"></i></div>
                            <div class="text-secondary small">Retención</div>
                            <h3 class="fw-bold mb-0" id="kpiRetention">-</h3>
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
                    <div class="col-lg-8">
                        <div class="glass-card p-4 h-100">
                            <h6 class="fw-bold mb-4">Evolución de Pedidos por Periodo</h6>
                            <canvas id="chartEvolution" style="max-height: 250px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- 👤 SECCIÓN 3 — Tabla Individual RFM -->
                <div class="glass-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Listado Maestro de Clientes</h5>
                        <div class="input-group input-group-sm w-25">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search"></i></span>
                            <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Buscar por nombre o membresía...">
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
                                    <th>Scores R-F-M</th>
                                    <th>Total</th>
                                    <th>Segmento</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="rfmTableBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- 🏪 SECCIÓN 4 — Análisis por Sucursal -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Productividad por Sucursal (RFM Score)</h6>
                            <canvas id="chartBranchScores"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Distribución por Sucursal</h6>
                            <canvas id="chartBranchDistribution"></canvas>
                        </div>
                    </div>
                </div>

                <!-- 🧠 SECCIÓN 5 — Hábitos de Consumo -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-7">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Mapa de Calor: Intensidad de Consumo (Hora vs Día)</h6>
                            <div id="heatmapContainer" style="height: 350px;">
                                <canvas id="chartHeatmap"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="glass-card p-4">
                            <h6 class="fw-bold mb-4">Top 10 Productos Preferidos</h6>
                            <div id="topProductsList"></div>
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
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-graduation-cap me-2"></i>Guía RFM 360°</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 scroller" style="max-height: 70vh;">
                    <h6>¿Cómo interpretar los scores?</h6>
                    <p class="small text-muted">Cada cliente recibe de 1 a 5 puntos en cada categoría basándose en su posición relativa al resto de los socios.</p>
                    <ul>
                        <li><b>R (Recencia):</b> 5 = Compró recientemente; 1 = Compró hace mucho.</li>
                        <li><b>F (Frecuencia):</b> 5 = Cliente muy habitual; 1 = Compra esporádica.</li>
                        <li><b>M (Monto):</b> 5 = Ticket alto acumulado; 1 = Gasto bajo.</li>
                    </ul>
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
