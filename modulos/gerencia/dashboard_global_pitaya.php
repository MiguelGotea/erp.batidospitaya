<?php
/**
 * Dashboard Global Pitaya
 * modulos/gerencia/dashboard_global_pitaya.php
 * Vista ejecutiva de KPIs estratégicos de Batidos Pitaya
 */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_global_pitaya', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$mesActual = date('n');
$anioActual = date('Y');
$hoy = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Global · Batidos Pitaya</title>
    <meta name="description" content="Centro de inteligencia global — KPIs estratégicos de Batidos Pitaya.">

    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/dashboard_global_pitaya.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body id="dashboardGlobalPitaya">
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Dashboard Global Pitaya'); ?>

            <div class="da-wrapper">

                <!-- ══════════════════════════════════════════ -->
                <!--  HERO HEADER                              -->
                <!-- ══════════════════════════════════════════ -->
                <div class="da-hero">
                    <div class="da-hero-left">
                        <div class="da-hero-badge">
                            <i class="fas fa-chart-network"></i>
                            Inteligencia de Negocio &amp; Crecimiento
                        </div>
                        <h1 class="da-hero-title">Centro de Inteligencia <span>Pitaya</span></h1>
                        <p class="da-hero-sub">Visión estratégica en tiempo real · <?php echo date('d \d\e F, Y'); ?>
                        </p>
                    </div>
                    <div class="da-hero-right">
                        <div class="da-store-counter">
                            <div class="da-store-num" id="totalTiendasHero">14</div>
                            <div class="da-store-label">Tiendas Activas</div>
                            <div class="da-store-meta">Meta 2028: <strong>40</strong></div>
                        </div>
                        <div class="da-period-selector">
                            <label>Período</label>
                            <select id="selectorPeriodo" class="da-select">
                                <option value="mes_actual">Mes Actual</option>
                                <option value="mes_anterior">Mes Anterior</option>
                                <option value="trimestre">Trimestre</option>
                                <option value="anio">Año Completo</option>
                            </select>
                            <select id="selectorAnio" class="da-select">
                                <option value="<?php echo $anioActual; ?>"><?php echo $anioActual; ?></option>
                                <option value="<?php echo $anioActual - 1; ?>"><?php echo $anioActual - 1; ?></option>
                            </select>
                        </div>
                        <!-- Toggle Moneda -->
                        <div class="da-currency-bar">
                            <div class="da-tc-wrap">
                                <label class="da-tc-label">Tipo de Cambio</label>
                                <div class="da-tc-input-wrap">
                                    <span class="da-tc-prefix">US$ 1 =</span>
                                    <input type="number" id="inputTipoCambio" value="36.5" min="1" step="0.1"
                                        class="da-tc-input">
                                    <span class="da-tc-suffix">C$</span>
                                </div>
                            </div>
                            <div class="da-cur-toggle" id="currencyToggle">
                                <button class="da-cur-btn active" data-moneda="COR" id="btnCOR">C$</button>
                                <button class="da-cur-btn" data-moneda="USD" id="btnUSD">US$</button>
                            </div>
                        </div>
                        <button class="da-btn-refresh" id="btnActualizar">
                            <i class="fas fa-sync-alt"></i> Actualizar
                        </button>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  LOADER GLOBAL                            -->
                <!-- ══════════════════════════════════════════ -->
                <div id="daLoader" class="da-loader">
                    <div class="da-loader-ring"></div>
                    <span>Calculando indicadores…</span>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  SECCIÓN 1: KPIs FINANCIEROS TOPE         -->
                <!-- ══════════════════════════════════════════ -->
                <div class="da-section-title">
                    <i class="fas fa-chart-line"></i> Desempeño de Ventas
                    <span class="da-badge-period da-section-badge" id="badgePeriodoVentas">—</span>
                </div>

                <div class="da-kpi-grid" id="gridVentas">
                    <!-- Ventas Totales -->
                    <div class="da-kpi-card da-kpi-primary" id="cardVentasTotales">
                        <div class="da-kpi-icon-wrap primary">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label" id="labelVentasTotales">Ventas Totales</div>
                            <div class="da-kpi-valor" id="kpiVentasTotales">—</div>
                            <div class="da-kpi-trend" id="trendVentasTotales"></div>
                        </div>
                        <div class="da-kpi-sparkline">
                            <canvas id="sparkVentas" height="50"></canvas>
                        </div>
                    </div>

                    <!-- Meta vs Real -->
                    <div class="da-kpi-card" id="cardMetaVsReal">
                        <div class="da-kpi-icon-wrap warning">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Cumplimiento de Meta</div>
                            <div class="da-kpi-valor" id="kpiCumplimientoMeta">—</div>
                            <div class="da-kpi-sub" id="subCumplimientoMeta"></div>
                        </div>
                        <div class="da-progress-wrap">
                            <div class="da-progress-bar" id="progressMeta"></div>
                        </div>
                    </div>

                    <!-- Ticket Promedio -->
                    <div class="da-kpi-card" id="cardTicket">
                        <div class="da-kpi-icon-wrap teal">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Ticket Promedio</div>
                            <div class="da-kpi-valor" id="kpiTicketPromedio">—</div>
                            <div class="da-kpi-trend" id="trendTicket"></div>
                        </div>
                    </div>


                    <!-- Transacciones -->
                    <div class="da-kpi-card" id="cardTransacciones">
                        <div class="da-kpi-icon-wrap green">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Total Pedidos</div>
                            <div class="da-kpi-valor" id="kpiTransacciones">—</div>
                            <div class="da-kpi-trend" id="trendTransacciones"></div>
                        </div>
                    </div>

                    <!-- Venta Promedio por Tienda -->
                    <div class="da-kpi-card" id="cardVentaPorTienda">
                        <div class="da-kpi-icon-wrap orange">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Venta Prom. / Tienda</div>
                            <div class="da-kpi-valor" id="kpiVentaPorTienda">—</div>
                            <div class="da-kpi-sub">Promedio entre tiendas activas</div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  SECCIÓN 2: VENTAS POR TIENDA + GRÁFICA   -->
                <!-- ══════════════════════════════════════════ -->
                <div class="da-row-2col" style="grid-template-columns:2fr 1fr">

                    <!-- Gráfica Ventas Mensuales -->
                    <div class="da-card da-card-lg">
                        <div class="da-card-header">
                            <h3><i class="fas fa-chart-bar me-2"></i>Tendencia de Ventas</h3>
                            <div class="da-card-tabs" id="tabsTendencia">
                                <button class="da-tab active" data-tab="mensual">Mensual</button>
                            </div>
                        </div>
                        <div class="da-card-body">
                            <canvas id="chartTendenciaVentas" height="220"></canvas>
                        </div>
                    </div>

                    <!-- Ranking Tiendas -->
                    <div class="da-card">
                        <div class="da-card-header">
                            <h3><i class="fas fa-trophy me-2"></i>Ranking Tiendas</h3>
                            <span class="da-badge-period" id="badgePeriodoRanking">—</span>
                        </div>
                        <div class="da-card-body da-scroll">
                            <div id="rankingTiendas" class="da-ranking-list">
                                <div class="da-loading-row"><i class="fas fa-spinner fa-spin"></i> Cargando…</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  SECCIÓN 3: CLUB PITAYA / CLIENTES        -->
                <!-- ══════════════════════════════════════════ -->
                <div class="da-section-title">
                    <i class="fas fa-users"></i> Club Pitaya — Inteligencia de Clientes
                    <span class="da-badge-period da-section-badge" id="badgePeriodoClub">—</span>
                </div>

                <div class="da-kpi-grid da-kpi-grid-5" id="gridClub">

                    <div class="da-kpi-card" id="cardClubActivos">
                        <div class="da-kpi-icon-wrap teal">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Socios Activos</div>
                            <div class="da-kpi-valor" id="kpiSociosActivos">—</div>
                            <div class="da-kpi-sub" id="subSociosActivos"></div>
                        </div>
                    </div>

                    <div class="da-kpi-card" id="cardClubTotal">
                        <div class="da-kpi-icon-wrap primary">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Total Membresías</div>
                            <div class="da-kpi-valor" id="kpiTotalMembresias">—</div>
                            <div class="da-kpi-sub">Base registrada</div>
                        </div>
                    </div>

                    <div class="da-kpi-card" id="cardClubNuevos">
                        <div class="da-kpi-icon-wrap green">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Nuevos Socios</div>
                            <div class="da-kpi-valor" id="kpiNuevosSocios">—</div>
                            <div class="da-kpi-trend" id="trendNuevosSocios"></div>
                        </div>
                    </div>

                    <div class="da-kpi-card" id="cardChurn">
                        <div class="da-kpi-icon-wrap danger">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Churn Rate</div>
                            <div class="da-kpi-valor" id="kpiChurn">—</div>
                            <div class="da-kpi-sub">Socios perdidos (&gt;60 días)</div>
                        </div>
                    </div>

                    <div class="da-kpi-card" id="cardLTV">
                        <div class="da-kpi-icon-wrap purple">
                            <i class="fas fa-gem"></i>
                        </div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">LTV Promedio</div>
                            <div class="da-kpi-valor" id="kpiLTVPromedio">—</div>
                            <div class="da-kpi-sub">Valor de vida del cliente</div>
                        </div>
                    </div>
                </div>

                <!-- Gráficas Club -->
                <div class="da-row-3col">
                    <div class="da-card">
                        <div class="da-card-header">
                            <h3><i class="fas fa-chart-pie me-2"></i>Segmentos RFM</h3>
                            <span class="da-badge-period" id="badgePeriodoRFM">—</span>
                        </div>
                        <div class="da-card-body">
                            <canvas id="chartRFMSegmentos" height="200"></canvas>
                        </div>
                    </div>

                    <div class="da-card">
                        <div class="da-card-header">
                            <h3><i class="fas fa-user-friends me-2"></i>Participación Club en Ventas</h3>
                            <span class="da-badge-period" id="badgePeriodoParticipacion">—</span>
                        </div>
                        <div class="da-card-body">
                            <canvas id="chartParticipacionClub" height="200"></canvas>
                        </div>
                    </div>

                    <div class="da-card">
                        <div class="da-card-header">
                            <h3><i class="fas fa-calendar-alt me-2"></i>Nuevos Socios / Mes</h3>
                        </div>
                        <div class="da-card-body">
                            <canvas id="chartNuevosSocios" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  SECCIÓN 4: PRODUCTOS TOP                 -->
                <!-- ══════════════════════════════════════════ -->
                <div class="da-section-title">
                    <i class="fas fa-star"></i> Productos Estrella &amp; Mix de Ventas
                    <span class="da-badge-period da-section-badge" id="badgePeriodoProductos">—</span>
                </div>

                <div class="da-row-2col">
                    <div class="da-card">
                        <div class="da-card-header">
                            <h3><i class="fas fa-blender me-2"></i>Top 10 Productos</h3>
                            <span class="da-badge-period" id="badgePeriodoTop10">—</span>
                        </div>
                        <div class="da-card-body da-scroll">
                            <div id="topProductos" class="da-top-list"></div>
                        </div>
                    </div>

                    <div class="da-card">
                        <div class="da-card-header">
                            <h3><i class="fas fa-chart-doughnut me-2"></i>Mix por Categoría</h3>
                            <span class="da-badge-period" id="badgePeriodoMix">—</span>
                        </div>
                        <div class="da-card-body">
                            <canvas id="chartMixCategorias" height="220"></canvas>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  SECCIÓN 5: CRECIMIENTO / EXPANSIÓN       -->
                <!-- ══════════════════════════════════════════ -->
                <div class="da-section-title">
                    <i class="fas fa-rocket"></i> Plan de Expansión — Pitaya 2028
                </div>


                <!-- KPIs de expansión -->
                <div class="da-kpi-grid da-kpi-grid-5" style="margin-bottom:16px">
                    <div class="da-kpi-card">
                        <div class="da-kpi-icon-wrap teal"><i class="fas fa-store"></i></div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Tiendas Activas</div>
                            <div class="da-kpi-valor" id="expTiendasActivas">—</div>
                            <div class="da-kpi-sub">de 40 (meta 2028)</div>
                        </div>
                    </div>
                    <div class="da-kpi-card">
                        <div class="da-kpi-icon-wrap green"><i class="fas fa-percentage"></i></div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Avance Meta 2028</div>
                            <div class="da-kpi-valor" id="expAvancePct">—</div>
                            <div class="da-progress-wrap" style="margin-top:6px">
                                <div class="da-progress-bar" id="progressExpansion"></div>
                            </div>
                        </div>
                    </div>
                    <div class="da-kpi-card">
                        <div class="da-kpi-icon-wrap orange"><i class="fas fa-calendar-plus"></i></div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Aperturas necesarias/año</div>
                            <div class="da-kpi-valor" id="expApertNecesarias">—</div>
                            <div class="da-kpi-sub">para llegar a 40 en 2028</div>
                        </div>
                    </div>
                    <div class="da-kpi-card">
                        <div class="da-kpi-icon-wrap purple"><i class="fas fa-calendar-check"></i></div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Primera Apertura</div>
                            <div class="da-kpi-valor" id="expPrimeraApertura" style="font-size:1.2rem">—</div>
                        </div>
                    </div>
                    <div class="da-kpi-card">
                        <div class="da-kpi-icon-wrap primary"><i class="fas fa-chart-line"></i></div>
                        <div class="da-kpi-body">
                            <div class="da-kpi-label">Crecimiento Ventas Total</div>
                            <div class="da-kpi-valor" id="expCrecimientoVentas" style="color:#3fb950">—</div>
                        </div>
                    </div>
                </div>

                <!-- Métricas de viabilidad -->
                <div class="da-card da-card-full" style="margin-bottom:18px" id="cardViabilidad">
                    <div class="da-card-header">
                        <h3><i class="fas fa-traffic-light me-2"></i>Viabilidad Meta 2028 — Análisis de Ritmo Real</h3>
                        <span class="da-badge-period">Basado en historial de aperturas</span>
                    </div>
                    <div class="da-card-body">
                        <div class="da-kpi-grid da-kpi-grid-5" style="margin-bottom:0">
                            <div class="da-kpi-card">
                                <div class="da-kpi-icon-wrap primary"><i class="fas fa-tachometer-alt"></i></div>
                                <div class="da-kpi-body">
                                    <div class="da-kpi-label">Ritmo Histórico Promedio</div>
                                    <div class="da-kpi-valor" id="viaRitmoHistorico">—</div>
                                    <div class="da-kpi-sub">aperturas/año (desde inicio)</div>
                                </div>
                            </div>
                            <div class="da-kpi-card">
                                <div class="da-kpi-icon-wrap orange"><i class="fas fa-clock"></i></div>
                                <div class="da-kpi-body">
                                    <div class="da-kpi-label">Ritmo Reciente (últ. 2 años)</div>
                                    <div class="da-kpi-valor" id="viaRitmoReciente">—</div>
                                    <div class="da-kpi-sub">aperturas/año</div>
                                </div>
                            </div>
                            <div class="da-kpi-card">
                                <div class="da-kpi-icon-wrap warning"><i class="fas fa-bullseye"></i></div>
                                <div class="da-kpi-body">
                                    <div class="da-kpi-label">Ritmo Necesario</div>
                                    <div class="da-kpi-valor" id="viaRitmoNecesario">—</div>
                                    <div class="da-kpi-sub">para llegar a 40 en 2028</div>
                                </div>
                            </div>
                            <div class="da-kpi-card">
                                <div class="da-kpi-icon-wrap teal"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="da-kpi-body">
                                    <div class="da-kpi-label">Proyección Realista 2028</div>
                                    <div class="da-kpi-valor" id="viaProyeccionReciente">—</div>
                                    <div class="da-kpi-sub">tiendas al ritmo reciente</div>
                                </div>
                            </div>
                            <div class="da-kpi-card">
                                <div class="da-kpi-icon-wrap green"><i class="fas fa-check-double"></i></div>
                                <div class="da-kpi-body">
                                    <div class="da-kpi-label">Estado Viabilidad</div>
                                    <div class="da-kpi-valor" id="viaEstado" style="font-size:1.1rem">—</div>
                                    <div class="da-kpi-sub" id="viaRatio"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="da-card da-card-full" style="margin-bottom:16px">
                    <div class="da-card-header">
                        <h3><i class="fas fa-rocket me-2"></i>Crecimiento de Tiendas vs Meta 2028</h3>
                    </div>
                    <div class="da-card-body">
                        <canvas id="chartExpansionTiendas" height="160"></canvas>
                    </div>
                </div>
                <div class="da-card da-card-full" style="margin-bottom:16px">
                    <div class="da-card-header">
                        <h3><i class="fas fa-chart-area me-2"></i>Ventas Históricas por Año</h3>
                    </div>
                    <div class="da-card-body">
                        <canvas id="chartVentasAnio" height="160"></canvas>
                    </div>
                </div>

                <!-- Tabla historial de aperturas -->
                <div class="da-card da-card-full" style="margin-bottom:16px">
                    <div class="da-card-header">
                        <h3><i class="fas fa-history me-2"></i>Historial de Aperturas</h3>
                        <span class="da-badge-period">Orden cronológico desde la primera tienda</span>
                    </div>
                    <div class="da-card-body p-0">
                        <div class="table-responsive">
                            <table class="da-table" id="tablaAperturas">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tienda</th>
                                        <th>Fecha Apertura</th>
                                        <th class="text-end">Ventas Históricas</th>
                                        <th class="text-center">Años operando</th>
                                        <th class="text-center">% del total ventas</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyAperturas">
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted"><i
                                                class="fas fa-spinner fa-spin me-2"></i>Cargando…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


                <!-- ══════════════════════════════════════════ -->
                <!--  SECCIÓN 6: TABLA COMPARATIVA TIENDAS    -->
                <!-- ══════════════════════════════════════════ -->
                <div class="da-section-title">
                    <i class="fas fa-table"></i> Desempeño Comparativo por Tienda
                </div>

                <div class="da-card da-card-full">
                    <div class="da-card-header">
                        <h3><i class="fas fa-store-alt me-2"></i>Todas las Tiendas</h3>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <span class="da-badge-period" id="badgePeriodoTablaTiendas">—</span>
                            <input type="text" id="buscadorTiendas" class="da-input-search" placeholder="Buscar tienda…">
                        </div>
                    </div>
                    <div class="da-card-body p-0">
                        <div class="table-responsive">
                            <table class="da-table" id="tablaTiendas">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tienda</th>
                                        <th class="text-end">Ventas Período</th>
                                        <th class="text-end">Meta</th>
                                        <th class="text-center">Cumplimiento</th>
                                        <th class="text-end">Pedidos</th>
                                        <th class="text-end">Ticket Prom.</th>
                                        <th class="text-end">Socios Club</th>
                                        <th class="text-center">Tendencia</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyTiendas">
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            <i class="fas fa-spinner fa-spin me-2"></i>Cargando datos…
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  SECCIÓN 7: ALERTAS ESTRATÉGICAS          -->
                <!-- ══════════════════════════════════════════ -->
                <div class="da-section-title">
                    <i class="fas fa-exclamation-triangle"></i> Alertas &amp; Señales de Atención
                </div>

                <div id="panelAlertas" class="da-alertas-grid">
                    <div class="da-loading-row"><i class="fas fa-spinner fa-spin"></i> Analizando indicadores…</div>
                </div>

                <!-- Espaciado inferior -->
                <div style="height:40px"></div>

            </div><!-- /da-wrapper -->
        </div>
    </div>

    <!-- ══════════════════════════════════════════ -->
    <!--  MODAL DE AYUDA                           -->
    <!-- ══════════════════════════════════════════ -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header da-modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-network me-2"></i>Guía — Dashboard Global Pitaya
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">

                    <!-- ── REGLA DE ORO: Filtros de datos ── -->
                    <div class="alert alert-info border-0 mb-4" style="background:#e8f4fd;border-left:4px solid #0E544C !important;border-radius:8px">
                        <h6 class="fw-bold mb-2" style="color:#0E544C"><i class="fas fa-filter me-2"></i>Regla de Oro — ¿Qué datos se consideran?</h6>
                        <p class="small mb-2">Todo el dashboard filtra por <strong>tipo de canal</strong> y <strong>estado operativo</strong> de la sucursal según el contexto del indicador:</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 small">
                                <thead style="background:#0E544C;color:#fff">
                                    <tr>
                                        <th>Tipo de dato</th>
                                        <th class="text-center"><code style="color:#aff">sucursal = 1</code></th>
                                        <th class="text-center"><code style="color:#aff">activa = 1</code></th>
                                        <th>¿Por qué?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-success">
                                        <td><strong>KPIs del período actual</strong><br><small class="text-muted">Ventas, ticket, ranking, top productos, mix categorías, participación club</small></td>
                                        <td class="text-center">✅ Siempre</td>
                                        <td class="text-center">✅ Sí</td>
                                        <td class="small">Solo reflejan tiendas actualmente en operación. Una tienda cerrada no genera ventas hoy.</td>
                                    </tr>
                                    <tr class="table-warning">
                                        <td><strong>Histórico de ventas (gráficas de tendencia)</strong><br><small class="text-muted">Tendencia mensual 12 meses, ventas por año en expansión</small></td>
                                        <td class="text-center">✅ Siempre</td>
                                        <td class="text-center">❌ No</td>
                                        <td class="small">Si una tienda tuvo ventas antes de cerrar, esas ventas <em>sí ocurrieron</em> y deben reflejarse en el historial real.</td>
                                    </tr>
                                    <tr class="table-light">
                                        <td><strong>Métricas de Club Pitaya</strong><br><small class="text-muted">Socios activos, churn, LTV, RFM, universo</small></td>
                                        <td class="text-center">❌ N/A</td>
                                        <td class="text-center">❌ N/A</td>
                                        <td class="small">Son métricas de <em>clientes</em>, no de sucursales. Un cliente que compró en una tienda cerrada sigue siendo un cliente activo.</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Estado actual de tiendas</strong><br><small class="text-muted">Contador del encabezado, proyección de expansión</small></td>
                                        <td class="text-center">✅ Siempre</td>
                                        <td class="text-center">✅ Sí</td>
                                        <td class="small">Para contar <em>cuántas tiendas operan hoy</em> solo importan las activas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="small mt-2 mb-0 text-muted"><i class="fas fa-info-circle me-1"></i><strong>sucursal = 1</strong> diferencia las tiendas oficiales de la cadena de otros canales o entidades que también registran facturación en el sistema (ej. producción, ventas internas). Siempre se aplica.</p>
                    </div>

                    <!-- ── Secciones ── -->
                    <div class="row g-3">

                        <!-- Ventas y KPIs -->
                        <div class="col-md-6">
                            <div class="card border-0 h-100" style="background:#f8fffe">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-2" style="color:#0E544C"><i class="fas fa-chart-line me-2"></i>Ventas & KPIs del Período</h6>
                                    <p class="small text-muted mb-2">Fuente: <code>VentasGlobalesAccessCSV</code> filtrada por <code>sucursal=1 AND activa=1</code>. Solo pedidos <strong>no anulados</strong> (<code>Anulado=0</code>).</p>
                                    <ul class="small text-muted mb-0 ps-3">
                                        <li><strong>Ventas Totales:</strong> suma de <code>Precio</code> en el rango de fechas seleccionado.</li>
                                        <li><strong>Ticket Promedio:</strong> Ventas Totales ÷ pedidos únicos (<code>CodPedido</code> distintos).</li>
                                        <li><strong>Venta Prom./Tienda:</strong> Ventas Totales ÷ número de tiendas que facturaron en el período.</li>
                                        <li><strong>▲/▼ vs período anterior:</strong> compara automáticamente con el período inmediatamente anterior de igual duración.</li>
                                        <li><strong>Moneda:</strong> todos los valores están en C$ nativos; el toggle US$ divide por el tipo de cambio ingresado.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Tendencia de Ventas -->
                        <div class="col-md-6">
                            <div class="card border-0 h-100" style="background:#f8fffe">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-2" style="color:#0E544C"><i class="fas fa-chart-bar me-2"></i>Tendencia de Ventas (Gráfica)</h6>
                                    <p class="small text-muted mb-2">Filtro: <code>sucursal=1</code> — <strong>incluye tiendas cerradas</strong> si facturaron en ese mes.</p>
                                    <ul class="small text-muted mb-0 ps-3">
                                        <li><strong>Barras (Ventas reales):</strong> ventas totales por mes, últimos 12 meses completos.</li>
                                        <li><strong>Barra con * (Abr '26):</strong> mes actual — estimado extrapolando las ventas reales hasta ayer al mes completo.</li>
                                        <li><strong>Línea dorada (Venta/sucursal):</strong> ventas ÷ tiendas activas <em>ese mes</em>; eje derecho. Permite ver eficiencia por tienda independiente del número de sucursales.</li>
                                        <li><strong>Líneas punteadas (proyección):</strong> arrancan desde el último mes completo.
                                            <ul class="mt-1">
                                                <li><strong>Conservador:</strong> ritmo histórico de aperturas desde el inicio.</li>
                                                <li><strong>Moderado:</strong> ritmo de aperturas de los últimos 2 años.</li>
                                                <li><strong>Optimista:</strong> aperturas lineales exactas para llegar a 40 tiendas en Dic 2028.</li>
                                            </ul>
                                        </li>
                                        <li><strong>Tooltip en el mes de inicio de proyección:</strong> muestra "→ Proyección desde este punto ↗".</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Club Pitaya -->
                        <div class="col-md-6">
                            <div class="card border-0 h-100" style="background:#f8fffe">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-2" style="color:#0E544C"><i class="fas fa-users me-2"></i>Club Pitaya — Métricas de Clientes</h6>
                                    <p class="small text-muted mb-2">Fuente: <code>clientesclub</code> y <code>VentasGlobalesAccessCSV</code>. <strong>No se filtra por sucursal</strong> — son métricas del cliente, no de la tienda.</p>
                                    <ul class="small text-muted mb-0 ps-3">
                                        <li><strong>Socios Activos:</strong> clientes con al menos 1 compra en los últimos 60 días.</li>
                                        <li><strong>Churn Rate:</strong> clientes sin compra en más de 60 días ÷ universo total con al menos 1 compra histórica.</li>
                                        <li><strong>LTV Promedio:</strong> suma histórica de compras por cliente, promediada sobre todos los clientes.</li>
                                        <li><strong>Participación Club:</strong> ventas de clientes identificados (CodCliente > 0) ÷ ventas totales del período. <em>Este sí filtra sucursal=1 y activa=1.</em></li>
                                        <li><strong>RFM:</strong> Campeones (≤15 días inactivo, ≥10 compras), Fieles (≤30 días, ≥5 compras), En Riesgo (≤60 días), Perdidos (>60 días).</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Metas -->
                        <div class="col-md-6">
                            <div class="card border-0 h-100" style="background:#f8fffe">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-2" style="color:#0E544C"><i class="fas fa-bullseye me-2"></i>Metas & Cumplimiento</h6>
                                    <p class="small text-muted mb-2">Fuente: tabla <code>ventas_meta</code> cruzada con <code>sucursales</code> (sucursal=1, activa=1).</p>
                                    <ul class="small text-muted mb-0 ps-3">
                                        <li>Las metas se registran por sucursal y fecha en la tabla <code>ventas_meta</code>.</li>
                                        <li>El cumplimiento compara ventas reales del período vs la meta acumulada del mismo rango de fechas.</li>
                                        <li>Si una sucursal no tiene meta registrada, aparece como "Sin meta" en la tabla de tiendas.</li>
                                        <li><span style="color:#3fb950">■</span> Verde ≥100% &nbsp;|&nbsp; <span style="color:#e3b341">■</span> Ámbar ≥80% &nbsp;|&nbsp; <span style="color:#f85149">■</span> Rojo &lt;80%.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Productos y Mix -->
                        <div class="col-md-6">
                            <div class="card border-0 h-100" style="background:#f8fffe">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-2" style="color:#0E544C"><i class="fas fa-blender me-2"></i>Top Productos & Mix por Categoría</h6>
                                    <p class="small text-muted mb-2">Filtro: <code>sucursal=1 AND activa=1</code>, período seleccionado, solo pedidos no anulados.</p>
                                    <ul class="small text-muted mb-0 ps-3">
                                        <li><strong>Top 10:</strong> ordenados por monto facturado (<code>Precio</code>), no por unidades.</li>
                                        <li><strong>Mix Categorías:</strong> agrupado por <code>NombreGrupo</code>; los sin categoría aparecen como "Otro".</li>
                                        <li>Ambos reflejan exactamente el período y las tiendas del selector de período del encabezado.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Expansión -->
                        <div class="col-md-6">
                            <div class="card border-0 h-100" style="background:#f8fffe">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-2" style="color:#0E544C"><i class="fas fa-rocket me-2"></i>Expansión — Plan 2028</h6>
                                    <p class="small text-muted mb-2">Filtro historial de ventas: <code>sucursal=1</code> (sin filtro activa — incluye tiendas cerradas con facturación real). Estado actual de tiendas: <code>sucursal=1 AND activa=1</code>.</p>
                                    <ul class="small text-muted mb-0 ps-3">
                                        <li><strong>Tiendas Activas (encabezado):</strong> <code>sucursal=1 AND activa=1</code> — solo tiendas operando hoy.</li>
                                        <li><strong>Ventas por Año (gráfica histórica):</strong> incluye todo <code>sucursal=1</code> desde 2024. Las tiendas cerradas que operaron en ese año sí aportan.</li>
                                        <li><strong>Historial de Aperturas:</strong> ventas históricas por tienda solo con <code>sucursal=1</code>; incluye cerradas para mostrar el aporte real en su tiempo activo.</li>
                                        <li><strong>Ritmo Histórico:</strong> aperturas brutas desde el inicio ÷ años transcurridos.</li>
                                        <li><strong>Ritmo Reciente:</strong> aperturas en últimos 2 años ÷ 2.</li>
                                        <li><strong>Viabilidad:</strong> compara ritmo reciente vs aperturas/año necesarias para llegar a 40 en 2028.</li>
                                        <li><strong>Base de datos:</strong> ventas de facturación disponibles desde <strong>2024 en adelante</strong> por optimización de almacenamiento. Fechas de apertura en <code>sucursales</code> sí son completas desde la primera tienda.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Período -->
                        <div class="col-12">
                            <div class="card border-0" style="background:#fffbf0;border-left:4px solid #c9a227 !important">
                                <div class="card-body py-2">
                                    <h6 class="fw-bold mb-1" style="color:#7a6200"><i class="fas fa-calendar-alt me-2"></i>Selector de Período</h6>
                                    <div class="row small text-muted">
                                        <div class="col-md-3"><strong>Mes Actual:</strong> del 1° del mes a ayer (el sistema excluye el día de hoy ya que los datos se sincronizan nocturnamente).</div>
                                        <div class="col-md-3"><strong>Mes Anterior:</strong> del 1° al último día del mes pasado completo.</div>
                                        <div class="col-md-3"><strong>Trimestre:</strong> el trimestre calendario en curso hasta ayer.</div>
                                        <div class="col-md-3"><strong>Año Completo:</strong> del 1 Ene al 31 Dic del año seleccionado (o hasta ayer si es el año actual).</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /row -->
                </div><!-- /modal-body -->
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        const DA_CONFIG = {
            ajaxUrl: 'ajax/dashboard_global_pitaya_get_datos.php',
            mesActual: <?php echo $mesActual; ?>,
            anioActual: <?php echo $anioActual; ?>,
            hoy: '<?php echo $hoy; ?>'
        };
    </script>
    <script src="js/dashboard_global_pitaya.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>