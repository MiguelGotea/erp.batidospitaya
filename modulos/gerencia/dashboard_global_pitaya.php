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
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold" style="color:#0E544C"><i
                                            class="fas fa-chart-line me-2"></i>Ventas</h6>
                                    <p class="small text-muted mb-0">Datos de <code>VentasGlobalesAccessCSV</code>. Solo
                                        pedidos <strong>no anulados</strong>. Usa el toggle <strong>C$ / US$</strong> en
                                        el encabezado para cambiar moneda. El tipo de cambio es configurable. El ticket
                                        promedio es monto total ÷ pedidos únicos.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold" style="color:#0E544C"><i class="fas fa-users me-2"></i>Club
                                        Pitaya</h6>
                                    <p class="small text-muted mb-0">Los <strong>Socios Activos</strong> son quienes
                                        compraron en los últimos 60 días. El <strong>Churn Rate</strong> = socios sin
                                        compra &gt;60 días / total con al menos 1 compra. El <strong>LTV</strong> es la
                                        suma histórica de compras por cliente.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold" style="color:#0E544C"><i class="fas fa-bullseye me-2"></i>Metas
                                    </h6>
                                    <p class="small text-muted mb-0">Las metas se registran en la tabla
                                        <code>ventas_meta</code> por sucursal y fecha. El cumplimiento compara las
                                        ventas reales del período con la meta acumulada del mismo período.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body">
                                    <h6 class="fw-bold" style="color:#0E544C"><i
                                            class="fas fa-rocket me-2"></i>Expansión 2028</h6>
                                    <p class="small text-muted mb-0">
                                        <strong>¿Qué es una tienda activa?</strong> Solo se cuentan sucursales con
                                        <code>sucursal=1</code> (canal de tienda, no canales alternativos) y
                                        <code>activa=1</code> (no cerradas). El contador del encabezado usa exactamente
                                        este filtro. Existen otras entidades que facturan (otros canales) que se excluyen.
                                        <br><br>
                                        <strong>Datos de ventas históricas:</strong> La base de datos solo conserva
                                        registros de facturación desde <strong>2024 en adelante</strong> por optimización
                                        de memoria. Las fechas de apertura en tabla <code>sucursales</code> sí son
                                        completas desde la primera tienda. Las gráficas de ventas por año reflejan solo
                                        2024+. <br><br>
                                        <strong>Viabilidad:</strong> Se calcula el ritmo histórico y reciente de
                                        aperturas para proyectar cuántas tiendas se tendrán en 2028 al ritmo actual, y
                                        cuántas aperturas/año serían necesarias para cumplir la meta de 40.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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