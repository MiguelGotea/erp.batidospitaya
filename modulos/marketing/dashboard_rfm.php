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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard RFM - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
            
            <div class="container-fluid p-4">
                <!-- Filtros -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm glass-card">
                            <div class="card-body d-flex flex-wrap gap-3 align-items-center">
                                <div class="filter-group">
                                    <label class="small fw-bold text-muted mb-1">Periodo</label>
                                    <div class="input-group input-group-sm">
                                        <input type="date" id="fecha_inicio" class="form-control" value="<?php echo date('Y-m-d', strtotime('-90 days')); ?>">
                                        <span class="input-group-text">a</span>
                                        <input type="date" id="fecha_fin" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="filter-group">
                                    <label class="small fw-bold text-muted mb-1">Sucursal</label>
                                    <select id="filtro_sucursal" class="form-select form-select-sm">
                                        <option value="">Todas las sucursales</option>
                                        <!-- Se cargará dinámicamente -->
                                    </select>
                                </div>
                                <div class="ms-auto pt-3">
                                    <button class="btn btn-primary btn-sm px-4" onclick="cargarDatos()">
                                        <i class="fas fa-sync-alt me-2"></i>Actualizar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen Global -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="kpi-card glass-card color-1">
                            <div class="kpi-icon"><i class="fas fa-users"></i></div>
                            <div class="kpi-info">
                                <h4 id="kpi_total_club">0</h4>
                                <p>Clientes Club Totales</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="kpi-card glass-card color-2">
                            <div class="kpi-icon"><i class="fas fa-user-check"></i></div>
                            <div class="kpi-info">
                                <h4 id="kpi_activos">0</h4>
                                <p>Clientes Activos (60d)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="kpi-card glass-card color-3">
                            <div class="kpi-icon"><i class="fas fa-percentage"></i></div>
                            <div class="kpi-info">
                                <h4 id="kpi_churn">0%</h4>
                                <p>Tasa de Churn</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="kpi-card glass-card color-4">
                            <div class="kpi-icon"><i class="fas fa-receipt"></i></div>
                            <div class="kpi-info">
                                <h4 id="kpi_ticket">C$ 0.00</h4>
                                <p>Ticket Promedio</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos Principales -->
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm glass-card h-100">
                            <div class="card-header bg-transparent border-0 pt-4 px-4">
                                <h5 class="fw-bold mb-0">Distribución de Segmentos RFM</h5>
                                <p class="small text-muted mb-0">Comportamiento según Recencia, Frecuencia y Monto</p>
                            </div>
                            <div class="card-body p-4">
                                <canvas id="chart_segmentos" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm glass-card h-100">
                            <div class="card-header bg-transparent border-0 pt-4 px-4">
                                <h5 class="fw-bold mb-0">Hábitos de Compra</h5>
                            </div>
                            <div class="card-body p-4">
                                <div class="habit-item mb-4">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted small">Producto Favorito</span>
                                        <span class="fw-bold" id="habit_fav_product">-</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: 100%;"></div>
                                    </div>
                                </div>
                                <div class="habit-item mb-4">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted small">Medida Preferida</span>
                                        <span class="fw-bold" id="habit_fav_size">-</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-info" style="width: 100%;"></div>
                                    </div>
                                </div>
                                <div class="habit-item mb-4">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted small">Modalidad</span>
                                        <span class="fw-bold" id="habit_fav_modalidad">-</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-warning" style="width: 100%;"></div>
                                    </div>
                                </div>
                                <hr class="my-4 opacity-10">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <p class="mb-0 small text-muted">Uso de Promociones</p>
                                        <h5 class="mb-0 fw-bold" id="habit_perc_promo">0%</h5>
                                    </div>
                                    <div class="icon-circle bg-primary-light text-primary">
                                        <i class="fas fa-tag"></i>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-0 small text-muted">Canjes de Puntos</p>
                                        <h5 class="mb-0 fw-bold" id="habit_redenciones">0</h5>
                                    </div>
                                    <div class="icon-circle bg-danger-light text-danger">
                                        <i class="fas fa-gift"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comparativa Club vs General -->
                <div class="row g-4 mt-2">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm glass-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="icon-square bg-teal me-3">
                                        <i class="fas fa-hand-holding-usd"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-0">Ingresos Totales (LTV)</h5>
                                        <p class="small text-muted mb-0">Club vs Clientes Generales</p>
                                    </div>
                                </div>
                                <canvas id="chart_ingresos" style="max-height: 250px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="main-header mb-0">Dashboard RFM <span class="badge bg-teal fs-6 align-middle">PREMIUM</span></h1>
                <p class="text-muted small">Análisis avanzado de segmentación y lealtad</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm rounded-pill px-3" onclick="$('#pageHelpModal').modal('show')">
                    <i class="fas fa-question-circle me-1"></i> Ayuda
                </button>
                <button class="btn btn-success btn-sm rounded-pill px-3" id="btnExportar">
                    <i class="fas fa-file-excel me-1"></i> Exportar Datos
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="glass-card p-3 mb-4">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Rango de Fecha</label>
                    <div class="input-group input-group-sm">
                        <input type="date" class="form-control" name="fecha_inicio" value="<?php echo date('Y-m-d', strtotime('-90 days')); ?>">
                        <span class="input-group-text">a</span>
                        <input type="date" class="form-control" name="fecha_fin" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Sucursal</label>
                    <select class="form-select form-select-sm" name="sucursal" id="selectSucursal">
                        <option value="">Todas las Sucursales</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100 rounded-pill">
                        <i class="fas fa-sync-alt me-1"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>

        <!-- KPI Cards -->
        <div class="row g-4 mb-4" id="kpiContainer">
            <div class="col-md-3 col-lg">
                <div class="glass-card kpi-card">
                    <i class="fas fa-users"></i>
                    <div class="card-title">Total Club</div>
                    <h2 id="kpiTotalClub">-</h2>
                    <div class="small text-muted mt-2">Miembros únicos</div>
                </div>
            </div>
            <div class="col-md-3 col-lg">
                <div class="glass-card kpi-card">
                    <i class="fas fa-user-check"></i>
                    <div class="card-title">Activos (60d)</div>
                    <h2 id="kpiActivos">-</h2>
                    <div class="small text-success mt-2">En el periodo</div>
                </div>
            </div>
            <div class="col-md-3 col-lg">
                <div class="glass-card kpi-card">
                    <i class="fas fa-receipt"></i>
                    <div class="card-title">Ticket Promedio</div>
                    <h2 id="kpiTicket">-</h2>
                    <div class="small text-primary mt-2">Monto factura</div>
                </div>
            </div>
            <div class="col-md-3 col-lg">
                <div class="glass-card kpi-card">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="card-title">Antigüedad Prom.</div>
                    <h2 id="kpiAntiguedad">-</h2>
                    <div class="small text-info mt-2">Días suscritos</div>
                </div>
            </div>
            <div class="col-md-3 col-lg">
                <div class="glass-card kpi-card">
                    <i class="fas fa-user-slash"></i>
                    <div class="card-title">Tasa Churn</div>
                    <h2 id="kpiChurn">-</h2>
                    <div class="small text-danger mt-2">% Inactividad</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Gráfico Segmentos -->
            <div class="col-lg-7">
                <div class="glass-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Distribución de Segmentos</h5>
                        <div class="dropdown">
                            <button class="btn btn-link link-dark p-0" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Ver Detalles</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="chartSegments"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6 scroller" style="max-height: 300px; overflow-y: auto;">
                            <div id="segmentLegend">
                                <!-- Leyenda se llena con JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hábitos de Compra -->
            <div class="col-lg-5">
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-4">Hábitos del Periodo</h5>
                    <div class="list-group list-group-flush bg-transparent">
                        <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center border-bottom border-white border-opacity-25">
                            <div>
                                <div class="small text-muted">Producto Estrella</div>
                                <div class="fw-bold text-dark" id="habitProduct">-</div>
                            </div>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center border-bottom border-white border-opacity-25">
                            <div>
                                <div class="small text-muted">Medida Preferida</div>
                                <div class="fw-bold text-dark" id="habitSize">-</div>
                            </div>
                            <i class="fas fa-expand-arrows-alt text-primary"></i>
                        </div>
                        <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center border-bottom border-white border-opacity-25">
                            <div>
                                <div class="small text-muted">Modalidad Top</div>
                                <div class="fw-bold text-dark" id="habitModalidad">-</div>
                            </div>
                            <i class="fas fa-store text-success"></i>
                        </div>
                        <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="small text-muted">% Uso de Promociones</div>
                                <div class="fw-bold text-dark" id="habitPromo">-</div>
                            </div>
                            <i class="fas fa-percentage text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Clientes y Otros Rankings -->
        <div class="row g-4 mt-2">
            <div class="col-lg-8">
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-4">🏆 Top 10 Clientes Club (LTV)</h5>
                    <div class="table-responsive">
                        <table class="premium-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Cliente</th>
                                    <th>Frecuencia</th>
                                    <th>Monetario</th>
                                    <th>Segmento</th>
                                </tr>
                            </thead>
                            <tbody id="tableTopClients">
                                <!-- Datos via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-4">Resumen Económico</h5>
                    <div class="p-4 rounded-4 bg-teal text-white mb-3">
                        <div class="small opacity-75">Ingresos Club</div>
                        <h3 class="fw-bold mb-0" id="ingresoClub">$ 0.00</h3>
                    </div>
                    <div class="p-4 rounded-4 bg-secondary text-white border-white border-opacity-25">
                        <div class="small opacity-75">Ingresos General</div>
                        <h3 class="fw-bold mb-0" id="ingresoGeneral">$ 0.00</h3>
                    </div>
                    <div class="mt-4 p-3 border border-white border-opacity-50 rounded-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small">Participación Club</span>
                            <span class="fw-bold" id="percClub">0%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-teal" id="progressClub" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Guía del Dashboard RFM</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-chart-line me-2"></i> ¿Qué es RFM?
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Es una técnica de segmentación basada en:
                                        <br>• <b>Recencia:</b> Días desde la última compra.
                                        <br>• <b>Frecuencia:</b> Cantidad de pedidos únicos.
                                        <br>• <b>Monto:</b> Gasto total acumulado (LTV).
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-user-check me-2"></i> Clientes Activos
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Se consideran activos aquellos clientes con al menos una compra en los últimos <b>60 días</b>.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
                        Los cálculos excluyen pedidos anulados (`Anulado = 0`) y filtran productos por categoría según corresponda.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Ajuste de posición y capas para Modal de Ayuda */
        #pageHelpModal {
            z-index: 1060 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        #pageHelpModal .modal-content {
            border-radius: 15px;
            overflow: hidden;
            border: none;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
    </style>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/dashboard_rfm.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>
