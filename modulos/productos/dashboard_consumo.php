<?php
/* ============================================================
   DASHBOARD CONSUMO DE INSUMOS — Análisis, Proyección y Planificación
   modulos/productos/dashboard_consumo.php
   ============================================================ */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_consumo_insumos', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}


$puedeExportar = tienePermiso('dashboard_consumo_insumos', 'exportar_consumo', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consumo de Insumos · Pitaya ERP</title>
    <meta name="description"
        content="Dashboard profesional de análisis de consumo histórico, proyección y planificación de insumos por local y semana.">

    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/dashboard_consumo.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Análisis de Consumo de Insumos'); ?>

            <div class="dc-wrapper p-3">

                <!-- ══════════════════════════════════════════ -->
                <!--  PANEL DE FILTROS                         -->
                <!-- ══════════════════════════════════════════ -->
                <div class="dc-filtros-card card border-0 shadow-sm mb-3">
                    <div class="card-body py-2 px-3">
                        <div class="row g-2 align-items-end">

                            <!-- Semana Desde -->
                            <div class="col-6 col-md-2 col-lg-2">
                                <label class="dc-label" for="filtroSemanaDesde">
                                    <i class="fas fa-hashtag me-1"></i>Semana Desde
                                </label>
                                <input type="number" class="form-control form-control-sm dc-input-semana"
                                    id="filtroSemanaDesde" min="1" max="9999" placeholder="Ej: 10">
                            </div>

                            <!-- Semana Hasta -->
                            <div class="col-6 col-md-2 col-lg-2">
                                <label class="dc-label" for="filtroSemanaHasta">
                                    <i class="fas fa-hashtag me-1"></i>Semana Hasta
                                </label>
                                <input type="number" class="form-control form-control-sm dc-input-semana"
                                    id="filtroSemanaHasta" min="1" max="9999" placeholder="Ej: 14">
                            </div>

                            <!-- Tiendas — Custom Pill Dropdown -->
                            <div class="col-12 col-md-4 col-lg-4">
                                <label class="dc-label" for="dcSucTrigger">
                                    <i class="fas fa-store me-1"></i>Tiendas
                                </label>
                                <!-- Trigger visible -->
                                <div class="dc-suc-trigger" id="dcSucTrigger" tabindex="0" role="button"
                                    aria-haspopup="listbox" aria-expanded="false">
                                    <div class="dc-suc-trigger-inner">
                                        <span class="dc-suc-placeholder" id="dcSucPlaceholder">
                                            <i class="fas fa-store me-1" style="opacity:.45"></i>Todas las tiendas
                                        </span>
                                        <div class="dc-suc-pills" id="dcSucPills" style="display:none"></div>
                                    </div>
                                    <div class="dc-suc-trigger-right">
                                        <span class="dc-suc-count-badge" id="dcSucCountBadge"
                                            style="display:none"></span>
                                        <button class="dc-suc-clear" id="dcSucClear" title="Quitar todas"
                                            style="display:none" type="button">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <i class="fas fa-chevron-down dc-suc-chevron" id="dcSucChevron"></i>
                                    </div>
                                </div>
                                <!-- Dropdown panel -->
                                <div class="dc-suc-dropdown" id="dcSucDropdown" role="listbox"
                                    aria-multiselectable="true">
                                    <div class="dc-suc-search-wrap">
                                        <i class="fas fa-search dc-suc-search-icon"></i>
                                        <input type="text" class="dc-suc-search" id="dcSucSearch"
                                            placeholder="Buscar tienda…" autocomplete="off">
                                    </div>
                                    <div class="dc-suc-actions">
                                        <button type="button" class="dc-suc-action-btn" id="dcSucSelAll"><i
                                                class="fas fa-check-double me-1"></i>Todas</button>
                                        <button type="button" class="dc-suc-action-btn" id="dcSucNone"><i
                                                class="fas fa-times me-1"></i>Ninguna</button>
                                    </div>
                                    <div class="dc-suc-list" id="dcSucList"></div>
                                </div>
                                <!-- Select oculto para compatibilidad con el JS existente -->
                                <select id="filtroSucursales" multiple style="display:none"></select>
                            </div>



                            <!-- Semana Actual y Botones -->
                            <div
                                class="col-12 col-md-12 col-lg-4 d-flex gap-2 justify-content-end align-items-center flex-nowrap">
                                <div id="badgeSemanaActual" class="dc-badge-semana-actual" style="display:none">
                                    <i class="fas fa-calendar-check text-primary"></i>
                                    Sem. Actual: <strong id="semanaActualNum">—</strong>
                                    <span class="dc-sem-rango" id="semanaActualRango"></span>
                                </div>

                                <div class="dc-btn-group-actions">
                                    <button class="btn btn-sm dc-btn-primary" id="btnAplicar">
                                        <i class="fas fa-search me-1"></i>Analizar
                                    </button>
                                    <?php if ($puedeExportar): ?>
                                        <button class="btn btn-sm dc-btn-export" id="btnExportar" disabled>
                                            <i class="fas fa-file-csv me-1"></i>CSV
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div><!-- /row filtros -->


                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════ -->
            <!--  ESTADO INICIAL / CARGA                   -->
            <!-- ══════════════════════════════════════════ -->
            <div id="panelInicial" class="dc-empty-state">
                <div class="dc-empty-icon"><i class="fas fa-chart-bar"></i></div>
                <h5>Configura tus filtros</h5>
                <p class="text-muted">Selecciona el rango de semanas y haz clic en <strong>Analizar</strong> para ver el
                    consumo histórico de insumos.</p>
            </div>

            <div id="panelLoader" class="dc-loader d-none">
                <div class="dc-loader-inner">
                    <div class="spinner-border dc-spinner" role="status"></div>
                    <div class="dc-loader-text">Calculando consumo…<br>
                        <small class="text-muted">Procesando recetas y traducciones Access→ERP</small>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════ -->
            <!--  PANEL PRINCIPAL (oculto hasta cargar)    -->
            <!-- ══════════════════════════════════════════ -->
            <div id="panelDatos" class="d-none">

                <!-- ═══════════════════════════════════════════════ -->
                <!-- HEADER: ALERTAS                                -->
                <!-- ═══════════════════════════════════════════════ -->
                <h5 class="fw-bold mb-3 mt-1" style="color:#0E544C">
                    <i class="fas fa-bell me-2" style="color:#e67e22"></i>Monitor de Alertas
                </h5>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- PANEL DE ALERTAS DE CRECIMIENTO SOSTENIDO      -->
                <!-- ═══════════════════════════════════════════════ -->
                <div id="panelCrecimiento" class="dc-crec-panel mb-3" style="display:none">
                    <div class="dc-crec-header" id="crecimientoHeader">
                        <div class="dc-alertas-header-left">
                            <span class="dc-crec-icon"><i class="fas fa-chart-line"></i></span>
                            <span class="dc-alertas-titulo">Crecimiento Sostenido Detectado</span>
                            <span class="dc-alertas-badge" id="crecimientoBadge">0</span>
                            <span class="dc-alertas-hint" id="crecimientoHint"></span>
                        </div>
                        <div class="dc-alertas-header-right">
                            <span class="dc-alertas-sigma-label">Umbral β/μ:</span>
                            <div class="dc-sigma-btns" id="crecUmbralBtns">
                                <button class="dc-sigma-btn" data-slope="0.03"
                                    title="Sensible: detecta pendientes leves (≥3%/sem)">3%</button>
                                <button class="dc-sigma-btn active" data-slope="0.06"
                                    title="Balance recomendado (≥6%/sem)">6%</button>
                                <button class="dc-sigma-btn" data-slope="0.12"
                                    title="Solo crecimiento claro (≥12%/sem)">12%</button>
                            </div>
                            <span class="dc-alertas-sigma-label ms-1">Ver:</span>
                            <div class="dc-sigma-btns" id="crecSevBtns">
                                <button class="dc-sigma-btn" data-sev="todos"
                                    title="Mostrar todos los detectados">Todos</button>
                                <button class="dc-sigma-btn" data-sev="notable"
                                    title="Notable y Crítico">Not+Crít</button>
                                <button class="dc-sigma-btn active" data-sev="critico"
                                    title="Solo Críticos">Críticos</button>
                            </div>
                            <button class="dc-alertas-toggle rotated" id="crecimientoToggle" title="Expandir / Contraer">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="dc-alertas-body collapsed" id="crecimientoBody">
                        <div id="crecimientoContenido"></div>
                    </div>
                </div>


                <!-- ═══════════════════════════════════════════════ -->
                <!-- PANEL DE ALERTAS DE SOBRECONSUMO               -->
                <!-- ═══════════════════════════════════════════════ -->
                <div id="panelAlertas" class="dc-alertas-panel mb-3" style="display:none">
                    <div class="dc-alertas-header" id="alertasHeader">
                        <div class="dc-alertas-header-left">
                            <span class="dc-alertas-icon"><i class="fas fa-exclamation-triangle"></i></span>
                            <span class="dc-alertas-titulo">Sobreconsumo Detectado</span>
                            <span class="dc-alertas-badge" id="alertasBadge">0</span>
                            <span class="dc-alertas-hint" id="alertasHint"></span>
                        </div>
                        <div class="dc-alertas-header-right">
                            <span class="dc-alertas-sigma-label">Umbral σ:</span>
                            <div class="dc-sigma-btns">
                                <button class="dc-sigma-btn" data-k="1"
                                    title="Más sensible: detecta desviaciones leves">1σ</button>
                                <button class="dc-sigma-btn active" data-k="1.5"
                                    title="Balance entre sensibilidad y precisión">1.5σ</button>
                                <button class="dc-sigma-btn" data-k="2" title="Solo spikes severos">2σ</button>
                            </div>
                            <button class="dc-alertas-toggle rotated" id="alertasToggle" title="Expandir / Contraer">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="dc-alertas-body collapsed" id="alertasBody">
                        <div id="alertasContenido"></div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- HEADER: ANALISIS INDIVIDUAL                             -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <h5 class="fw-bold mb-3 mt-4" style="color:#0E544C">
                    <i class="fas fa-search-plus me-2" style="color:#2980b9"></i>Análisis Individual de Insumos
                </h5>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- BARRA DE CONTROL Y GRAFICOS ASOCIADOS                   -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <div class="dc-filtros-card card border-0 shadow-sm mb-3" style="overflow:visible">
                    <div class="card-body py-2 px-3">
                        <div class="row g-2 align-items-end">

                            <!-- Selector de Insumo -->
                            <div class="col-12 col-md-6 col-lg-5" style="position:relative">
                                <label class="dc-label" for="dcInsumoSearch">
                                    <i class="fas fa-box me-1" style="color:var(--neu-accent)"></i>Insumo Analizado
                                </label>
                                <div class="dc-suc-trigger" id="dcInsumoTrigger">
                                    <div class="dc-suc-trigger-inner">
                                        <input type="text" class="dc-suc-search w-100" id="dcInsumoSearch"
                                            placeholder="Escribe para buscar un insumo..." autocomplete="off"
                                            style="background:transparent; border:none; outline:none; font-size:.84rem; font-weight:600; color:var(--neu-accent-dark);">
                                    </div>
                                    <div class="dc-suc-trigger-right">
                                        <i class="fas fa-chevron-down dc-suc-chevron" id="dcInsumoChevron"></i>
                                    </div>
                                </div>
                                <div class="dc-suc-dropdown" id="dcInsumoDropdown">
                                    <div class="dc-suc-list" id="dcInsumoList"></div>
                                </div>
                                <select id="chartInsumoSel" style="display:none"><option value=""></option></select>
                            </div>

                            <!-- Semana de Corte -->
                            <div class="col-6 col-md-2 col-lg-2">
                                <label class="dc-label" for="kardexSemanaCorte">
                                    <i class="fas fa-cut me-1" style="color:#e67e22"></i>Sem. Corte
                                </label>
                                <input type="number" id="kardexSemanaCorte"
                                    class="form-control form-control-sm dc-input-semana"
                                    min="1" max="9999" placeholder="Auto"
                                    title="Semana de referencia del inventario — punto de partida del Kardex. Vacío = usa la semana inicial del rango.">
                            </div>

                            <!-- Fecha Pronóstico -->
                            <div class="col-6 col-md-2 col-lg-2">
                                <label class="dc-label" for="kardexFechaPronostico">
                                    <i class="fas fa-magic me-1" style="color:#8e44ad"></i>Fecha Pronóstico
                                </label>
                                <input type="date" id="kardexFechaPronostico"
                                    class="form-control form-control-sm"
                                    title="Fecha objetivo para proyectar existencia (solo consume — sin ingresos ni mermas). Opcional.">
                            </div>

                            <!-- Botón Refresh Kardex -->
                            <div class="col-auto d-flex align-items-end">
                                <button class="btn btn-sm dc-btn-primary" id="btnRefreshKardex"
                                    title="Recargar el Kardex con la semana de corte indicada">
                                    <i class="fas fa-sync-alt me-1"></i>Actualizar Kardex
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                        <!-- ═══════════════════════════════════════════════════════ -->
                        <!-- PANEL INSUMO: KPIs + Gráfico de Tendencia              -->
                        <!-- ═══════════════════════════════════════════════════════ -->
                        <div class="dc-insumo-panel mb-3 d-none" id="panelAnalisisInsumo">
                            <div class="dc-insumo-panel-header" id="analisisInsumoHeader">
                                <div class="dc-insumo-panel-title">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Análisis de Insumo
                                    <span class="dc-insumo-panel-hint" id="insumoNombreHint">— selecciona un insumo arriba para ver KPIs y tendencia</span>
                                </div>
                                <button class="dc-alertas-toggle" id="analisisInsumoToggle" title="Expandir / Contraer">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>

                            <div class="dc-alertas-body" id="analisisInsumoBody">
                                <!-- KPI Cards (controladas por el selector) -->
                                <div class="dc-insumo-panel-body">

                                    <div class="row g-3 mb-3" id="kpiRow">
                                        <div class="col-6 col-lg-4">
                                            <div class="dc-kpi-card" id="kpiTotal">
                                                <div class="dc-kpi-icon" style="color:#51B8AC"><i class="fas fa-boxes"></i></div>
                                                <div class="dc-kpi-label">Consumo Total (período)</div>
                                                <div class="dc-kpi-valor" id="kpiTotalVal">—</div>
                                                <div class="dc-kpi-sub" id="kpiTotalSub"></div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-lg-4">
                                            <div class="dc-kpi-card" id="kpiPico">
                                                <div class="dc-kpi-icon" style="color:#e67e22"><i class="fas fa-fire"></i></div>
                                                <div class="dc-kpi-label">Semana de Mayor Consumo</div>
                                                <div class="dc-kpi-valor" id="kpiPicoVal">—</div>
                                                <div class="dc-kpi-sub" id="kpiPicoSub"></div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-lg-4">
                                            <div class="dc-kpi-card" id="kpiProy">
                                                <div class="dc-kpi-icon" style="color:#27ae60"><i class="fas fa-chart-line"></i>
                                                </div>
                                                <div class="dc-kpi-label">Proyección · Próx. 3 Semanas</div>
                                                <div class="dc-kpi-valor" id="kpiProyVal">—</div>
                                                <div class="dc-kpi-sub" id="kpiProySub"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Gráfico de Tendencia -->
                                    <div class="card border-0" id="cardTendencia" style="background:transparent">
                                        <div class="card-body px-0 pb-0 pt-2">
                                            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                                                <h6 class="dc-seccion-titulo mb-0" id="tituloTendencia">
                                                    <i class="fas fa-chart-line me-2"></i>Tendencia
                                                </h6>
                                                <div class="d-flex gap-1 align-items-center flex-wrap">
                                                    <button class="btn btn-xs dc-chip active" id="chartModoBarras"
                                                        data-modo="barras">
                                                        <i class="fas fa-chart-bar me-1"></i>Barras
                                                    </button>
                                                    <button class="btn btn-xs dc-chip" id="chartModoLineaTotal"
                                                        data-modo="linea_total">
                                                        <i class="fas fa-chart-line me-1"></i>Línea Total
                                                    </button>
                                                    <button class="btn btn-xs dc-chip" id="chartModoLineaSuc" data-modo="linea_suc">
                                                        <i class="fas fa-store me-1"></i>Línea x Tienda
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="chartPlaceholder" class="text-center py-5" style="color:#b0c8c5">
                                                <i class="fas fa-hand-point-up fa-2x mb-2 d-block"></i>
                                                <span class="text-muted" style="font-size:.85rem">Selecciona un insumo para ver su
                                                    tendencia de consumo por semana.</span>
                                            </div>
                                            <div class="dc-chart-wrap d-none" id="chartWrap">
                                                <canvas id="chartTendencia"></canvas>
                                                <!-- Botón reset posicionado en la zona de leyenda inferior del canvas -->
                                                <button class="dc-legend-reset-btn" id="chartLegendReset" style="display:none"
                                                    title="Mostrar todas las series">
                                                    <i class="fas fa-eye me-1"></i>Mostrar todas
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div><!-- /dc-insumo-panel-body -->
                            </div><!-- /analisisInsumoBody -->
                        </div><!-- /dc-insumo-panel -->

                        <!-- ═══════════════════════════════════════════════════════ -->
                        <!-- PANEL KARDEX (Traído de balance_inventario_detalle)   -->
                        <!-- ═══════════════════════════════════════════════════════ -->
                        <div id="panelKardex" class="dc-insumo-panel mb-1 d-none">
                            <div class="dc-insumo-panel-header" id="kardexHeader">
                                <div class="dc-insumo-panel-title">
                                    <i class="fas fa-exchange-alt me-2"></i>
                                    Movimiento de Existencia (Kardex)
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span id="bdKardexCorteTag" style="font-size:.7rem;background:rgba(255,255,255,.18);color:#fff;padding:3px 10px;border-radius:20px;border:1px solid rgba(255,255,255,.35);"></span>
                                    <button class="dc-alertas-toggle" id="kardexToggle" title="Expandir / Contraer">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="dc-alertas-body" id="kardexBody">
                                <div class="dc-insumo-panel-body">
                                    <!-- Loader -->
                                    <div id="bdLoaderKardex" class="text-center py-4">
                                        <div class="spinner-border text-success mb-3" role="status"></div>
                                        <div class="fw-bold">Consultando registros históricos…</div>
                                    </div>

                                    <!-- Resumen Neumorphic -->
                                    <div id="bdResumen" class="bd-resumen-card d-none"></div>

                                    <!-- Gráfico de Existencia -->
                                    <div id="bdChartWrap" class="bd-chart-card d-none">
                                        <div class="bd-section-title" style="box-shadow:none; padding:0; margin-bottom:1.5rem;">
                                            <i class="fas fa-chart-line" style="color:#51B8AC"></i>
                                            Movimiento de Existencia (Kardex)
                                            <span id="bdChartStockBadge"
                                                style="display:none;margin-left:auto;font-size:.7rem;font-weight:600;color:#51B8AC"></span>
                                            <!-- Toggle: Pronóstico de Abastecimiento -->
                                            <div class="d-flex align-items-center gap-3 ms-auto" id="kardexToggles">
                                                <label class="dc-pron-toggle" id="labelToggleDespCurso" title="Considerar ingresos que se reciben hoy (pre-ingresos)">
                                                    <input type="checkbox" id="toggleDespCurso" checked>
                                                    <span class="dc-pron-toggle-slider"></span>
                                                    <span class="dc-pron-toggle-label"><i class="fas fa-box-open me-1" style="font-size:.65rem"></i>Despacho en Curso</span>
                                                </label>
                                                <label class="dc-pron-toggle" id="labelTogglePronAbast" title="Activa para incorporar eventos de despacho (Pronóstico de Abastecimiento) en la proyección morada">
                                                    <input type="checkbox" id="togglePronAbast">
                                                    <span class="dc-pron-toggle-slider"></span>
                                                    <span class="dc-pron-toggle-label"><i class="fas fa-truck me-1" style="font-size:.65rem"></i>Pron. Abastecimiento</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div style="height:320px; position:relative;">
                                            <canvas id="existenciaChart"></canvas>
                                        </div>
                                        <!-- Nota semanas retrocedidas -->
                                        <div id="bdChartNota"
                                            style="display:none;margin-top:.8rem;padding:.55rem 1rem;border-radius:10px;background:#fffbf0;border-left:4px solid #f9a825;font-size:.74rem;color:#795548;">
                                            <i class="fas fa-info-circle me-1" style="color:#f9a825"></i>
                                            <span id="bdChartNotaText"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>



                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- HEADER: ANALISIS GLOBAL                                 -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <h5 class="fw-bold mb-3 mt-4" style="color:#0E544C">
                    <i class="fas fa-globe me-2" style="color:#27ae60"></i>Análisis Global y Planificación
                </h5>

                <!-- Contenedor flex para la barra de pestañas y el input global -->
                <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="position-relative">
                            <input type="text" class="form-control form-control-sm dc-search-input" id="buscarHistorial"
                                placeholder="Buscar insumo…" style="width: 200px;">
                        </div>
                    </div>
                    
                    <!-- Tab Bar a la derecha -->
                    <ul class="nav dc-tabs m-0" id="dashTabs" role="tablist" style="padding-top: 0;">
                        <li class="nav-item" role="presentation">
                            <button class="dc-tab-btn active" id="tabHistorialBtn" data-bs-toggle="tab"
                                data-bs-target="#tabHistorial" role="tab">
                                <i class="fas fa-history me-1"></i>Historial de Consumo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="dc-tab-btn" id="tabProyeccionBtn" data-bs-toggle="tab"
                                data-bs-target="#tabProyeccion" role="tab">
                                <i class="fas fa-chart-line me-1"></i>Proyección y Planificación
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="dashTabContent">

                    <!-- TAB 1: HISTORIAL -->
                    <div class="tab-pane fade show active" id="tabHistorial" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover dc-tabla mb-0" id="tablaHistorial">
                                        <thead>
                                            <tr>
                                                <th>Insumo ERP</th>
                                                <th>Categoría</th>
                                                <th class="text-end">Consumo Total</th>
                                                <th class="text-end">Prom/Semana</th>
                                                <th class="text-end">Semana Pico</th>
                                                <th class="text-center">Semanas</th>
                                                <th class="text-center">Tipo</th>
                                                <th class="text-center">Desglose</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyHistorial">
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">
                                                    Aplica los filtros para ver el historial.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: PROYECCIÓN -->
                    <div class="tab-pane fade" id="tabProyeccion" role="tabpanel">

                        <!-- ── Panel de Pronóstico Global de Stock ── -->
                        <div class="card border-0 shadow-sm mb-2" id="panelStockPron">
                            <div class="card-body py-2 px-3">
                                <div class="row g-2 align-items-end">

                                    <!-- Fecha Pronóstico Global -->
                                    <div class="col-6 col-md-3 col-lg-2">
                                        <label class="dc-label" for="pronFechaGlobal">
                                            <i class="fas fa-magic me-1" style="color:#8e44ad"></i>Fecha Pronóstico
                                        </label>
                                        <input type="date" id="pronFechaGlobal"
                                            class="form-control form-control-sm"
                                            title="Fecha objetivo para proyectar el stock de TODOS los insumos">
                                    </div>

                                    <!-- Semana de Corte Global -->
                                    <div class="col-6 col-md-2 col-lg-2">
                                        <label class="dc-label" for="pronSemCorteGlobal">
                                            <i class="fas fa-cut me-1" style="color:#e67e22"></i>Sem. Corte
                                        </label>
                                        <input type="number" id="pronSemCorteGlobal"
                                            class="form-control form-control-sm dc-input-semana"
                                            min="1" max="9999" placeholder="Auto"
                                            title="Semana de referencia del inventario (igual que el Kardex individual)">
                                    </div>

                                    <!-- Botón Calcular -->
                                    <div class="col-auto d-flex align-items-end gap-2">
                                        <button class="btn btn-sm dc-btn-primary" id="btnCalcStockPron"
                                            title="Calcular stock proyectado para todos los insumos">
                                            <i class="fas fa-calculator me-1"></i>Calcular Stock
                                        </button>
                                        <!-- Spinner / estado -->
                                        <span id="pronStatusTxt" class="text-muted small" style="display:none"></span>
                                    </div>

                                    <!-- Info -->
                                    <div class="col-12 col-md">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1 text-primary"></i>
                                            Stock al llegar a la fecha de pronóstico usando el balance Kardex + consumo teórico proyectado por día de semana.
                                        </small>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="dc-tabla-toolbar px-3 py-2">
                                    <span class="text-muted small">
                                        <i class="fas fa-info-circle me-1 text-primary"></i>
                                        Proyección (WLS) calculando tendencia de ventanas activas · Stock Mín y Stock Máx 
                                        calculados dinámicamente según configuración logística y ciclo de despacho.
                                    </span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover dc-tabla mb-0" id="tablaProyeccion">
                                        <thead>
                                            <tr>
                                                <th>Insumo ERP</th>
                                                <th>Categoría</th>
                                                <th class="text-end">Prom/Semana</th>
                                                <th class="text-end">Proyec. 3 Sem.</th>
                                                <th class="text-end">Stock Mín</th>
                                                <th class="text-end">Stock Máx</th>
                                                <th class="text-end">Semana Pico</th>
                                                <th class="text-end">Semana Baja</th>
                                                <th class="text-center">Tendencia</th>
                                                <th class="text-end" id="thStockPron" style="color:#8e44ad;white-space:nowrap">
                                                    <i class="fas fa-magic me-1"></i>Stock Pronóstico
                                                    <span id="thStockPronFecha" class="d-block" style="font-size:.65rem;font-weight:400;color:#b39ddb"></span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyProyeccion">
                                            <tr>
                                                <td colspan="10" class="text-center text-muted py-4">
                                                    Aplica los filtros para ver la proyección.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>



                </div><!-- /tab-content -->

            </div><!-- /panelDatos -->

        </div><!-- /dc-wrapper -->

        <!-- ══════════════════════════════════════════ -->
        <!--  MODAL DESGLOSE POR SEMANA               -->
        <!-- ══════════════════════════════════════════ -->
        <div class="modal fade" id="modalDesglose" tabindex="-1" aria-labelledby="modalDesgloseLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header" style="background:#0E544C;color:#fff">
                        <h5 class="modal-title" id="modalDesgloseLabel">
                            <i class="fas fa-expand-arrows-alt me-2"></i>Desglose por Semana y Local
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="modalDesgloseContenido" class="p-3"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════ -->
        <!--  MODAL AUDITORÍA VENTA × VENTA           -->
        <!-- ══════════════════════════════════════════ -->
        <div class="modal fade" id="modalAuditoria" tabindex="-1" aria-labelledby="modalAuditoriaLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header" style="background:#e65100;color:#fff">
                        <h5 class="modal-title" id="modalAuditoriaLabel">
                            <i class="fas fa-microscope me-2"></i>Auditoría de Cálculo
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="modalAuditoriaContenido" class="p-3"></div>
                    </div>
                    <div class="modal-footer py-1" style="font-size:.75rem;color:#888">
                        <span>
                            <span
                                style="background:#c8e6c9;padding:2px 6px;border-radius:3px;color:#1b5e20;font-weight:700">P1</span>
                            Porción directa → 0.5 &nbsp;|
                            <span
                                style="background:#bbdefb;padding:2px 6px;border-radius:3px;color:#0d47a1;font-weight:700">P2</span>
                            Cotización base → 4 dec &nbsp;|
                            <span
                                style="background:#ffe0b2;padding:2px 6px;border-radius:3px;color:#bf360c;font-weight:700">P3</span>
                            Fallback → 4 dec &nbsp;|
                            <span style="background:#fff8e1;padding:2px 6px;border-radius:3px">Amarillo</span> = Crudo
                            redondeado
                        </span>
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════ -->
        <!--  MODAL DE AYUDA                           -->
        <!-- ══════════════════════════════════════════ -->
        <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
            data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-xl">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header" style="background:#0E544C;color:#fff">
                        <h5 class="modal-title" id="pageHelpModalLabel">
                            <i class="fas fa-info-circle me-2"></i>Guía — Dashboard de Consumo de Insumos
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="font-size:.85rem">

                        <!-- ROW 1: Filtros + Traducción -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-filter me-2"></i>Cómo Usar los Filtros
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            <strong>Sem. Desde / Hasta:</strong> Ingresa el número de semana
                                            directamente (ej: 10 a 14).<br><br>
                                            El badge <strong>"Semana actual"</strong> en los filtros te indica en qué
                                            semana estás.<br><br>
                                            <strong>Tiendas:</strong> Deja vacío para analizar todas, o selecciona
                                            específicas con el selector de pills.<br><br>
                                            Haz clic en <strong>Analizar</strong> para cargar los datos.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-cogs me-2"></i>Lógica de Traducción Access→ERP
                                        </h6>
                                        <p class="small text-muted mb-1">
                                            Las ventas se toman de <code>VentasGlobalesAccessCSV</code> (solo no anuladas).<br>
                                            Por cada ingrediente se resuelve la cotización vía <strong>P1 / P2 / P3</strong>
                                            y luego se localiza la <strong>Presentación de Consumo</strong>
                                            (<code>presentacion_basica_inventario = 1</code>) en <strong>3 etapas en cascada</strong>:
                                        </p>
                                        <ul class="small text-muted mb-1 ps-3">
                                            <li><strong>Paso A</strong> — Mapeo directo: la cotización en el diccionario ya apunta
                                                a una presentación con <code>basica_inventario = 1</code>.</li>
                                            <li><strong>Paso B</strong> — Rastreo por maestro: si la presentación mapeada es de
                                                despacho/otra, se obtiene su <code>id_producto_maestro</code> y se busca la
                                                presentación básica del mismo maestro.</li>
                                            <li><strong>Paso C</strong> — Rastreo vía <code>CodIngrediente</code> (replica el
                                                AUTO del Visor de Recetas): para productos donde la presentación mapeada no tiene
                                                FK de maestro. Traza <em>CodCotizacion → CodIngrediente → todas sus cotizaciones
                                                → cualquier presentación con maestro → presentación básica</em>.</li>
                                        </ul>
                                        <p class="small text-muted mb-0">
                                            Consumo = <code>(Cantidad_receta × factor_conversión) / pp_cantidad × ventas</code>
                                        </p>
                                        <div class="mt-2 p-2 rounded" style="background:#e8eaf6;border-left:3px solid #283593;font-size:.76rem">
                                            <strong style="color:#283593"><i class="fas fa-info-circle me-1"></i>Nota técnica — Cascada de Despacho:</strong>
                                            Las búsquedas de Presentación Despacho usan <code>LEFT JOIN producto_maestro</code>
                                            y el <strong>Fallback 2</strong> (receta de 1 componente = Presentación Uso)
                                            se ejecuta <strong>siempre</strong>, incluso cuando
                                            <code>id_producto_maestro = NULL</code> en el producto de uso
                                            (ej: <em>Mix de Waffle</em> es una receta sin maestro → su paquete de 10u se detecta correctamente).
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 2: Tabs -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-history me-2"></i>Tab: Historial de Consumo
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            Muestra el consumo real por insumo ERP en el período seleccionado.<br><br>
                                            Haz clic en <strong>Ver</strong> para ver el detalle semana × local, o en
                                            <strong><i class="fas fa-microscope"></i></strong> para auditar cada venta
                                            individual.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-chart-line me-2"></i>Tab: Proyección y Planificación
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            Proyección de consumo para las próximas 3 semanas basada en regresión lineal
                                            ponderada (Mínimos Cuadrados Ponderados - WLS) sobre las semanas con consumo activo.<br><br>
                                            Incluye <strong>Stock Mínimo</strong> y <strong>Stock Máximo</strong> recomendados calculados dinámicamente usando las configuraciones logísticas de la tienda (días de seguridad, ciclo de despacho y factor de ajuste).
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ROW 3: Alertas -->
                        <div class="row g-3 mb-3">

                            <!-- Sobreconsumo -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0"
                                    style="background:#fff5f5;border-left:4px solid #e74c3c !important;">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#c0392b">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Panel: Sobreconsumo
                                            Detectado
                                            <span class="badge ms-2"
                                                style="background:#e74c3c;color:#fff;font-size:.65rem">Acción: reponer
                                                stock</span>
                                        </h6>
                                        <p class="small text-muted mb-1">
                                            Detecta <strong>spikes puntuales</strong> en una tienda específica: semanas
                                            donde el consumo
                                            supera el umbral estadístico de esa tienda para ese insumo.
                                        </p>
                                        <div class="p-2 rounded"
                                            style="background:#fff;border:1px solid #fcc;font-size:.78rem">
                                            <strong>Fórmula:</strong><br>
                                            <code>Umbral = μ_local + k·σ_local</code><br>
                                            <code>Z-score = (consumo − μ) / σ</code><br><br>
                                            <strong>μ</strong> = promedio de semanas con valor · <strong>σ</strong> =
                                            desviación estándar poblacional<br><br>
                                            <strong>Umbral σ ajustable:</strong><br>
                                            &nbsp;· <strong>1σ</strong> — Más sensible (detecta desviaciones leves)<br>
                                            &nbsp;· <strong>1.5σ</strong> — Balance recomendado (default)<br>
                                            &nbsp;· <strong>2σ</strong> — Solo spikes severos<br><br>
                                            <strong>Severidad del Z-score:</strong><br>
                                            &nbsp;· <span
                                                style="background:#fde8e8;color:#c0392b;border-radius:4px;padding:1px 6px">Crítico</span>
                                            Z ≥ 2.5 &nbsp;
                                            <span
                                                style="background:#fdf0e0;color:#d35400;border-radius:4px;padding:1px 6px">Alto</span>
                                            Z ≥ 2 &nbsp;
                                            <span
                                                style="background:#fefae0;color:#b7950b;border-radius:4px;padding:1px 6px">Moderado</span>
                                            Z &lt; 2
                                        </div>
                                        <p class="small text-muted mt-2 mb-0">
                                            <i class="fas fa-mouse-pointer me-1"></i>Al hacer clic en un insumo, el
                                            gráfico de tendencia se abre filtrado a esa tienda.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Crecimiento Sostenido -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0"
                                    style="background:#f0f6ff;border-left:4px solid #2980b9 !important;">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#1a5276">
                                            <i class="fas fa-chart-line me-2"></i>Panel: Crecimiento Sostenido
                                            <span class="badge ms-2"
                                                style="background:#2980b9;color:#fff;font-size:.65rem">Acción: redefinir
                                                abastecimiento</span>
                                        </h6>
                                        <p class="small text-muted mb-2">
                                            Detecta cuando el consumo de una tienda viene <strong>creciendo semana a
                                                semana de forma persistente</strong>,
                                            aunque no haya superado el umbral de sobreconsumo. Señal para revisar las
                                            cantidades base de abastecimiento.<br>
                                            El análisis es por <strong>sucursal × insumo</strong>.
                                        </p>

                                        <!-- Semanas recomendadas -->
                                        <div class="p-2 rounded mb-2"
                                            style="background:#fff8ec;border:1px solid #f0b429;font-size:.78rem">
                                            <strong><i class="fas fa-calendar-alt me-1"
                                                    style="color:#e67e22"></i>Semanas de estudio
                                                recomendadas:</strong><br><br>
                                            &nbsp;· <strong>Mínimo absoluto:</strong> 3 semanas completas (muestra
                                            resultados, baja confianza)<br>
                                            &nbsp;· <strong>Mínimo consistente: 6 semanas completas</strong> —
                                            Mann-Kendall S<sub>max</sub>=15, OLS con 3 grados de libertad<br><br>
                                            <strong>¿Cómo contar las semanas completas?</strong><br>
                                            &nbsp;· Si el filtro <strong>incluye la semana actual</strong> (en curso) →
                                            la semana actual se excluye automáticamente del cálculo (dato parcial).
                                            Selecciona <strong>7 semanas</strong> para obtener 6 completas.<br>
                                            &nbsp;· Si el filtro <strong>termina antes de la semana actual</strong>
                                            (rango 100% histórico) → todas las semanas son completas. Basta con
                                            seleccionar <strong>6 semanas</strong>.<br><br>
                                            El sistema muestra una advertencia naranja dentro del panel cuando las
                                            semanas analizadas son &lt; 6.
                                        </div>

                                        <!-- Indicadores -->
                                        <div class="p-2 rounded mb-2"
                                            style="background:#fff;border:1px solid #b3d4f0;font-size:.78rem">
                                            <strong>Activa cuando ≥ 2 de 3 indicadores superan su
                                                umbral:</strong><br><br>
                                            <strong>① Regresión lineal normalizada (β̂/μ):</strong><br>
                                            &nbsp;Ajusta <code>y = α + β·t</code> sobre las semanas con dato.<br>
                                            &nbsp;Umbral ajustable con el botón <strong>Umbral β/μ</strong> del panel
                                            (3% / 6% / 12%).<br><br>
                                            <strong>② Mann-Kendall τ:</strong><br>
                                            &nbsp;<code>S = Σ<sub>i&lt;j</sub> sgn(y<sub>j</sub> − y<sub>i</sub>)</code>
                                            &nbsp;·&nbsp; <code>τ = S / (n(n−1)/2)</code><br>
                                            &nbsp;Mide monotonía (τ=1 perfecto, τ=−1 decreciente). Umbral fijo:
                                            <code>τ &gt; 0.45</code><br><br>
                                            <strong>③ Run-ratio de incrementos:</strong><br>
                                            &nbsp;Proporción de semanas donde <code>y[t] &gt; y[t−1]</code>. Umbral
                                            fijo: <code>&gt; 65%</code>
                                        </div>

                                        <!-- Controles -->
                                        <div class="p-2 rounded mb-2"
                                            style="background:#fff;border:1px solid #b3d4f0;font-size:.78rem">
                                            <strong>Controles del panel:</strong><br><br>
                                            <strong>Umbral β/μ</strong> — sensibilidad de detección (Indicador ①):<br>
                                            &nbsp;· <span
                                                style="background:#d6eaf8;color:#1a5276;border-radius:4px;padding:1px 6px">3%</span>
                                            Sensible — detecta pendientes leves<br>
                                            &nbsp;· <span
                                                style="background:#d6eaf8;color:#1a5276;border-radius:4px;padding:1px 6px">6%</span>
                                            Balance recomendado (default)<br>
                                            &nbsp;· <span
                                                style="background:#d6eaf8;color:#1a5276;border-radius:4px;padding:1px 6px">12%</span>
                                            Estricto — solo crecimiento claro<br><br>
                                            <strong>Ver</strong> — filtro de severidad sobre los detectados:<br>
                                            &nbsp;· <span
                                                style="background:#eaf4fc;color:#2e86c1;border-radius:4px;padding:1px 6px">Todos</span>
                                            Muestra todos<br>
                                            &nbsp;· <span
                                                style="background:#d6eaf8;color:#1a5276;border-radius:4px;padding:1px 6px">Not+Crít</span>
                                            Notable y Crítico<br>
                                            &nbsp;· <span
                                                style="background:#e8daef;color:#7d3c98;border-radius:4px;padding:1px 6px">Críticos</span>
                                            Solo Críticos (default)<br><br>
                                            El <strong>badge</strong> del encabezado muestra el total detectado.<br>
                                            El <strong>hint</strong> muestra: <em>"N sem analizadas · X detectado(s) [·
                                                mostrando Y]"</em>
                                        </div>

                                        <!-- Severidad -->
                                        <div class="p-2 rounded"
                                            style="background:#fff;border:1px solid #b3d4f0;font-size:.78rem">
                                            <strong>Severidad:</strong><br>
                                            &nbsp;· <span
                                                style="background:#e8daef;color:#7d3c98;border-radius:4px;padding:1px 6px">Crítico</span>
                                            3/3 activos <em>o</em> β/μ &gt; 40%/sem<br>
                                            &nbsp;· <span
                                                style="background:#d6eaf8;color:#1a5276;border-radius:4px;padding:1px 6px">Notable</span>
                                            2/3 activos <em>o</em> β/μ &gt; 20%/sem<br>
                                            &nbsp;· <span
                                                style="background:#eaf4fc;color:#2e86c1;border-radius:4px;padding:1px 6px">Moderado</span>
                                            reservado para umbrales bajos
                                        </div>
                                        <p class="small text-muted mt-2 mb-0">
                                            <i class="fas fa-mouse-pointer me-1"></i>Al hacer clic en un insumo, el
                                            gráfico se abre filtrado a esa tienda.
                                        </p>
                                    </div>
                                </div>
                            </div>


                        </div>

                        <!-- Nota recetas globales -->
                        <div class="alert alert-info py-2 px-3 small mb-0">
                            <strong><i class="fas fa-lightbulb me-1"></i>Nota — Recetas Globales:</strong>
                            Los insumos marcados como <strong>Global</strong> (compuestos) se calculan como
                            <code>SubReceta.Cantidad × N_ventas</code> sin conversión de unidades.
                            Su consumo refiere al número de veces que se usa esa receta compuesta, no a una unidad
                            física.
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div><!-- /sub-container -->
    </div><!-- /main-container -->

    <style>
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }

        /* ── Estilos traídos de balance_inventario_detalle para Kardex ── */
        :root {
            --neu-bg: #f6f6f6;
            --neu-shadow-dark: #d6d6d6;
            --neu-shadow-light: #ffffff;
            --neu-accent: #51B8AC;
            --neu-text-primary: #455a64;
            --neu-text-secondary: #757575;
            --neu-radius: 20px;
            --neu-blur: 14px;
            --neu-distance: 8px;

            /* Specific component colors */
            --bd-pos: #51B8AC;
            --bd-neg: #e76f51;
            --bd-neutral: #457b9d;
            --bd-final: #6d597a;
        }

        .bd-resumen-card {
            background: var(--neu-bg);
            border-radius: var(--neu-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1.2rem;
            box-shadow: var(--neu-distance) var(--neu-distance) var(--neu-blur) var(--neu-shadow-dark),
                calc(-1 * var(--neu-distance)) calc(-1 * var(--neu-distance)) var(--neu-blur) var(--neu-shadow-light);
        }

        .bd-resumen-item {
            background: var(--neu-bg);
            padding: 15px;
            border-radius: 16px;
            box-shadow: inset 4px 4px 8px var(--neu-shadow-dark),
                inset -4px -4px 8px var(--neu-shadow-light);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .bd-resumen-label {
            font-size: .65rem;
            color: var(--neu-text-secondary);
            text-transform: uppercase;
            letter-spacing: .1em;
            margin-bottom: .4rem;
            font-weight: 700;
        }

        .bd-resumen-val {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--neu-text-primary);
        }

        .bd-chart-card {
            background: var(--neu-bg);
            border-radius: var(--neu-radius);
            padding: 1.8rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--neu-distance) var(--neu-distance) var(--neu-blur) var(--neu-shadow-dark),
                calc(-1 * var(--neu-distance)) calc(-1 * var(--neu-distance)) var(--neu-blur) var(--neu-shadow-light);
        }

        .bd-section-title {
            display: flex;
            align-items: center;
            gap: .7rem;
            font-size: .9rem;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-bottom: 1.2rem;
            padding: .9rem 1.4rem;
            border-radius: 16px;
            background: var(--neu-bg);
            box-shadow: 6px 6px 12px var(--neu-shadow-dark),
                -6px -6px 12px var(--neu-shadow-light);
        }

        /* ── Toggle: Pronóstico de Abastecimiento ── */
        .dc-pron-toggle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            margin-left: 8px;
            user-select: none;
        }
        .dc-pron-toggle input[type="checkbox"] { display: none; }
        .dc-pron-toggle-slider {
            position: relative;
            width: 36px;
            height: 20px;
            background: #ccc;
            border-radius: 20px;
            transition: background .25s;
            flex-shrink: 0;
        }
        .dc-pron-toggle-slider::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #fff;
            transition: transform .25s;
            box-shadow: 0 1px 3px rgba(0,0,0,.25);
        }
        .dc-pron-toggle input:checked + .dc-pron-toggle-slider { background: #8e44ad; }
        .dc-pron-toggle input:checked + .dc-pron-toggle-slider::after { transform: translateX(16px); }
        .dc-pron-toggle-label {
            font-size: .68rem;
            font-weight: 700;
            color: #8e44ad;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>const PUEDE_EXPORTAR = <?php echo $puedeExportar ? 'true' : 'false'; ?>;</script>
    <script src="js/dashboard_consumo.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>