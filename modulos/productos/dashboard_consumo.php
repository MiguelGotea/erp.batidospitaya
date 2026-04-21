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

    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/dashboard_consumo.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Análisis de Consumo de Insumos'); ?>

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
                                <div class="dc-suc-trigger" id="dcSucTrigger" tabindex="0" role="button" aria-haspopup="listbox" aria-expanded="false">
                                    <div class="dc-suc-trigger-inner">
                                        <span class="dc-suc-placeholder" id="dcSucPlaceholder">
                                            <i class="fas fa-store me-1" style="opacity:.45"></i>Todas las tiendas
                                        </span>
                                        <div class="dc-suc-pills" id="dcSucPills" style="display:none"></div>
                                    </div>
                                    <div class="dc-suc-trigger-right">
                                        <span class="dc-suc-count-badge" id="dcSucCountBadge" style="display:none"></span>
                                        <button class="dc-suc-clear" id="dcSucClear" title="Quitar todas" style="display:none" type="button">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <i class="fas fa-chevron-down dc-suc-chevron" id="dcSucChevron"></i>
                                    </div>
                                </div>
                                <!-- Dropdown panel -->
                                <div class="dc-suc-dropdown" id="dcSucDropdown" role="listbox" aria-multiselectable="true">
                                    <div class="dc-suc-search-wrap">
                                        <i class="fas fa-search dc-suc-search-icon"></i>
                                        <input type="text" class="dc-suc-search" id="dcSucSearch" placeholder="Buscar tienda…" autocomplete="off">
                                    </div>
                                    <div class="dc-suc-actions">
                                        <button type="button" class="dc-suc-action-btn" id="dcSucSelAll"><i class="fas fa-check-double me-1"></i>Todas</button>
                                        <button type="button" class="dc-suc-action-btn" id="dcSucNone"><i class="fas fa-times me-1"></i>Ninguna</button>
                                    </div>
                                    <div class="dc-suc-list" id="dcSucList"></div>
                                </div>
                                <!-- Select oculto para compatibilidad con el JS existente -->
                                <select id="filtroSucursales" multiple style="display:none"></select>
                            </div>



                            <!-- Semana Actual y Botones -->
                            <div class="col-12 col-md-12 col-lg-4 d-flex gap-2 justify-content-end align-items-center flex-nowrap">
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
                            <button class="dc-alertas-toggle" id="alertasToggle" title="Expandir / Contraer">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                    <div class="dc-alertas-body" id="alertasBody">
                        <div id="alertasContenido"></div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════════ -->
                <!-- PANEL INSUMO: Selector + KPIs + Gráfico (todo ligado)  -->
                <!-- ═══════════════════════════════════════════════════════ -->
                <div class="dc-insumo-panel mb-3">

                    <!-- Encabezado con selector de insumo -->
                    <div class="dc-insumo-panel-header">
                        <div class="dc-insumo-panel-title">
                            <i class="fas fa-filter me-2"></i>
                            Insumo Analizado
                            <span class="dc-insumo-panel-hint">— selecciona para ver KPIs, tendencia y métricas</span>
                        </div>
                        <div class="dc-insumo-sel-wrap">
                            <select class="form-select form-select-sm dc-select dc-insumo-sel-main" id="chartInsumoSel">
                                <option value="">— Selecciona un insumo —</option>
                            </select>
                        </div>
                    </div>

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
                                    <div class="dc-kpi-label">Proyección · Próx. 4 Semanas</div>
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
                                        <button class="btn btn-xs dc-chip active" id="chartModoBarras" data-modo="barras">
                                            <i class="fas fa-chart-bar me-1"></i>Barras
                                        </button>
                                        <button class="btn btn-xs dc-chip" id="chartModoLineaTotal" data-modo="linea_total">
                                            <i class="fas fa-chart-line me-1"></i>Línea Total
                                        </button>
                                        <button class="btn btn-xs dc-chip" id="chartModoLineaSuc" data-modo="linea_suc">
                                            <i class="fas fa-store me-1"></i>Línea x Tienda
                                        </button>
                                        <button class="btn btn-xs dc-chip-reset" id="chartLegendReset" style="display:none"
                                            title="Mostrar todas las series">
                                            <i class="fas fa-eye me-1"></i>Mostrar todas
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
                                </div>
                            </div>
                        </div>

                    </div><!-- /dc-insumo-panel-body -->
                </div><!-- /dc-insumo-panel -->

                <!-- Tab Bar -->
                <ul class="nav dc-tabs mb-2" id="dashTabs" role="tablist">
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

                <div class="tab-content" id="dashTabContent">

                    <!-- TAB 1: HISTORIAL -->
                    <div class="tab-pane fade show active" id="tabHistorial" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="dc-tabla-toolbar px-3 py-2">
                                    <div class="dc-tabla-toolbar-left">
                                        <span id="labelResultados" class="dc-result-count"></span>
                                    </div>
                                    <div class="dc-tabla-toolbar-right">
                                        <input type="text" class="form-control form-control-sm" id="buscarHistorial"
                                            placeholder="Buscar insumo…" style="max-width:200px">
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover dc-tabla mb-0" id="tablaHistorial">
                                        <thead>
                                            <tr>
                                                <th>Insumo ERP</th>
                                                <th>Unidad</th>
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
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="dc-tabla-toolbar px-3 py-2">
                                    <span class="text-muted small">
                                        <i class="fas fa-info-circle me-1 text-primary"></i>
                                        Proyección basada en promedio ponderado de las semanas analizadas · Stock Mín =
                                        1 semana · Stock Máx = 2 semanas
                                    </span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover dc-tabla mb-0" id="tablaProyeccion">
                                        <thead>
                                            <tr>
                                                <th>Insumo ERP</th>
                                                <th>Unidad</th>
                                                <th class="text-end">Prom/Semana</th>
                                                <th class="text-end">Proyec. 4 Sem.</th>
                                                <th class="text-end">Stock Mín</th>
                                                <th class="text-end">Stock Máx</th>
                                                <th class="text-end">Semana Pico</th>
                                                <th class="text-end">Semana Baja</th>
                                                <th class="text-center">Tendencia</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyProyeccion">
                                            <tr>
                                                <td colspan="9" class="text-center text-muted py-4">
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
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-filter me-2"></i>Cómo Usar los Filtros
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            <strong>Año + Sem. Desde / Hasta:</strong> Ingresa el número de semana
                                            directamente (ej: 10 a 14). El año se selecciona en el primer campo.<br><br>
                                            El badge <strong>"Semana actual"</strong> debajo de los filtros te indica en
                                            qué semana estás.<br><br>
                                            <strong>Tiendas:</strong> Deja vacío para analizar todas, o selecciona
                                            específicas.<br><br>
                                            <strong>Insumo:</strong> Opcional. Filtra el análisis a un insumo ERP
                                            específico.<br><br>
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
                                        <p class="small text-muted mb-0">
                                            Las ventas se toman de <code>VentasGlobalesAccessCSV</code> (solo no
                                            anuladas).<br><br>
                                            Por cada producto vendido, se consulta <code>SubReceta</code> y se traduce
                                            cada ingrediente al insumo ERP según el algoritmo P1/P2/P3:<br>
                                            <strong>P1</strong> vía <code>codporcion</code> directo ·
                                            <strong>P2</strong> vía Cotización base · <strong>P3</strong>
                                            fallback.<br><br>
                                            El consumo =
                                            <code>(Cantidad_receta × factor_conversión) / presentación × ventas</code>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-history me-2"></i>Tab: Historial
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            Muestra el consumo real por insumo ERP en el período seleccionado.
                                            Haz clic en el botón <strong>Desglose</strong> para ver el detalle semana ×
                                            local.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#0E544C">
                                            <i class="fas fa-chart-line me-2"></i>Tab: Proyección
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            Proyección de consumo para las próximas 4 semanas basada en el promedio del
                                            período analizado.
                                            Incluye <strong>Stock Mínimo</strong> (1 semana) y <strong>Stock
                                                Máximo</strong> (2 semanas) recomendados.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold border-bottom pb-2" style="color:#e74c3c">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Tab: Sin Mapeo
                                        </h6>
                                        <p class="small text-muted mb-0">
                                            Lista los ingredientes de receta que <strong>no tienen mapeo</strong> en el
                                            diccionario ERP.
                                            Estos ingredientes no están siendo contabilizados en el consumo total.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info py-2 px-3 small mt-3 mb-0">
                            <strong><i class="fas fa-lightbulb me-1"></i>Nota:</strong>
                            Los insumos que son <strong>Recetas Globales</strong> (compuestos) se calculan como
                            <code>SubReceta.Cantidad × N_ventas</code> sin conversión de unidades.
                            Su consumo refiere al número de veces que se usa esa receta compuesta.
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