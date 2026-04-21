<?php
/**
 * Pitaya OPS Lab — Ingeniería de Operaciones
 * modulos/gerencia/pitaya_ops_lab.php
 */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitaya_ops_lab', 'vista', $cargo)) {
  header('Location: /login.php');
  exit();
}

$iniDefault = date('Y-m-01', strtotime('-1 month'));
$finDefault = date('Y-m-t', strtotime('-1 month'));
?>
<!DOCTYPE html>
<html lang="es">


<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pitaya OPS Lab · Ingeniería de Operaciones</title>
  <meta name="description"
    content="Análisis de tiempos de ciclo, tasas de llegada y eficiencia operativa por estación — Batidos Pitaya.">
  <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 9999); ?>">
  <link rel="stylesheet" href="css/pitaya_ops_lab.css?v=<?php echo mt_rand(1, 9999); ?>">
</head>

<body id="opsLabBody">
  <?php echo renderMenuLateral($cargo); ?>
  <div class="main-container">
    <div class="sub-container">
      <?php echo renderHeader($usuario, false, 'Pitaya OPS Lab'); ?>
      <div id="opsLabWrapper">

        <!-- HERO -->
        <div class="ops-hero">
          <div>
            <div class="ops-hero-badge"><i class="fas fa-flask"></i> Operations Engineering Lab</div>
            <h1 class="ops-hero-title">Pitaya <span>OPS Lab</span></h1>
            <p class="ops-hero-sub">Análisis de cycle times · tasas de llegada · eficiencia por estación</p>
          </div>
          <div class="ops-hero-controls">
            <select id="opsSucursal" class="ops-select">
              <option value="">Todas las tiendas</option>
            </select>
            <input type="date" id="opsIni" class="ops-input" value="<?php echo $iniDefault; ?>">
            <input type="date" id="opsFin" class="ops-input" value="<?php echo $finDefault; ?>">
            <select id="opsTipoDia" class="ops-select">
              <option value="todos">Todos los días</option>
              <option value="entre_semana">Entre semana</option>
              <option value="fin_semana">Fin de semana</option>
            </select>
            <select id="opsTurno" class="ops-select">
              <option value="todos">Ambos turnos</option>
              <option value="manana">Mañana</option>
              <option value="tarde">Tarde</option>
            </select>
            <button class="ops-btn ops-btn-primary" id="opsBtnCargar">
              <i class="fas fa-play"></i> Analizar
            </button>
          </div>
        </div>

        <!-- TABS -->
        <div class="ops-tabs" id="opsTabs">
          <button class="ops-tab active" data-tab="resumen"><i class="fas fa-chart-pie"></i> Resumen</button>
          <button class="ops-tab" data-tab="llegadas"><i class="fas fa-wave-square"></i> Llegadas &amp; λ</button>
          <button class="ops-tab" data-tab="cycle"><i class="fas fa-stopwatch"></i> Cycle Times</button>
          <button class="ops-tab" data-tab="estaciones"><i class="fas fa-layer-group"></i> Mix Estaciones</button>
          <button class="ops-tab" data-tab="multi"><i class="fas fa-project-diagram"></i> Multi-Estación</button>
          <button class="ops-tab" data-tab="config"><i class="fas fa-sliders-h"></i> Configuración</button>
          <button class="ops-tab" data-tab="planificador"><i class="fas fa-users-cog"></i> Planificador</button>
          <button class="ops-tab" data-tab="simulador"><i class="fas fa-dice-d20"></i> Simulador DES</button>
          <button class="ops-tab" data-tab="lean"><i class="fas fa-leaf"></i> Lean 6σ</button>
        </div>

        <!-- PANEL: RESUMEN -->
        <div class="ops-tab-panel active" id="panelResumen">
          <div id="resumenLoader" class="ops-loader">
            <div class="ops-loader-ring"></div><span>Cargando resumen…</span>
          </div>
          <div id="resumenContent" style="display:none">
            <div class="ops-section-title"><i class="fas fa-tachometer-alt"></i> KPIs del Período <span
                class="ops-badge" id="badgeResumen">—</span></div>
            <div class="ops-kpi-grid" id="kpiGridResumen"></div>
            <div class="ops-grid-2">
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-chart-donut"></i> Mix Global por Estación</h3>
                </div>
                <div class="ops-card-body">
                  <div class="ops-chart-wrap"><canvas id="chartMixGlobal" height="260"></canvas></div>
                </div>
              </div>
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-clock"></i> Horas Pico</h3>
                </div>
                <div class="ops-card-body">
                  <div id="horasPicoList"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PANEL: LLEGADAS -->
        <div class="ops-tab-panel" id="panelLlegadas">
          <div id="llegadasLoader" class="ops-loader">
            <div class="ops-loader-ring"></div><span>Calculando λ…</span>
          </div>
          <div id="llegadasContent" style="display:none">
            <div class="ops-section-title"><i class="fas fa-wave-square"></i> Tasa de Llegada (λ) por Hora</div>
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-chart-bar"></i> Pedidos Promedio por Hora</h3>
                <span class="ops-badge" id="badgeLlegadas">—</span>
              </div>
              <div class="ops-card-body">
                <div class="ops-chart-wrap"><canvas id="chartLlegadas" height="220"></canvas></div>
              </div>
            </div>
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-table"></i> Detalle por Hora</h3>
              </div>
              <div class="ops-card-body p-0">
                <div class="ops-table-wrap">
                  <table class="ops-table">
                    <thead>
                      <tr>
                        <th>Hora</th>
                        <th>λ Pedidos/h</th>
                        <th>Pedidos Totales</th>
                        <th>Días Obs.</th>
                        <th>Unidades Prom</th>
                        <th>Intensidad</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyLlegadas"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PANEL: CYCLE TIMES -->
        <div class="ops-tab-panel" id="panelCycle">
          <div id="cycleLoader" class="ops-loader">
            <div class="ops-loader-ring"></div><span>Calculando tiempos…</span>
          </div>
          <div id="cycleContent" style="display:none">
            <div class="ops-section-title"><i class="fas fa-stopwatch"></i> Tiempos de Proceso — Análisis de Caja</div>
            <div class="ops-kpi-grid" id="kpiGridCycle"></div>
            <div class="ops-grid-2">
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-chart-bar"></i> Tiempo de Caja vs Tiempo Ingreso Productos (min)</h3>
                </div>
                <div class="ops-card-body"><canvas id="chartCycleTimes" height="250"></canvas></div>
              </div>
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-hourglass-half"></i> Diferencia: Caja vs Ingreso Productos</h3>
                </div>
                <div class="ops-card-body"><canvas id="chartQueueTime" height="250"></canvas></div>
              </div>
            </div>
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-table"></i> Detalle de Tiempos</h3>
              </div>
              <div class="ops-card-body p-0">
                <div class="ops-table-wrap">
                  <table class="ops-table">
                    <thead>
                      <tr>
                        <th>Estación</th>
                        <th>Registros</th>
                        <th>Tiempo de Caja Prom<br><small style="font-weight:400;opacity:.7">HoraCreado→HoraImpreso</small></th>
                        <th>Ingreso Productos Prom<br><small style="font-weight:400;opacity:.7">HoraIngresoProducto→HoraImpreso</small></th>
                        <th>Diferencia Prom</th>
                        <th>Caja Mín</th>
                        <th>Caja Máx</th>
                        <th>Std Dev</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyCycle"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PANEL: MIX ESTACIONES -->
        <div class="ops-tab-panel" id="panelEstaciones">
          <div id="estacionesLoader" class="ops-loader">
            <div class="ops-loader-ring"></div><span>Analizando mix…</span>
          </div>
          <div id="estacionesContent" style="display:none">
            <div class="ops-section-title"><i class="fas fa-layer-group"></i> Mix de Estaciones por Hora</div>
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-chart-area"></i> Distribución Horaria por Estación</h3>
              </div>
              <div class="ops-card-body"><canvas id="chartMixEstaciones" height="240"></canvas></div>
            </div>
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-percent"></i> % por Estación por Hora</h3>
              </div>
              <div class="ops-card-body p-0">
                <div class="ops-table-wrap">
                  <table class="ops-table">
                    <thead>
                      <tr>
                        <th>Hora</th>
                        <th>Batido</th>
                        <th>Waffle</th>
                        <th>Bowl</th>
                        <th>Total</th>
                        <th>% Batido</th>
                        <th>% Waffle</th>
                        <th>% Bowl</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyMix"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PANEL: MULTI-ESTACION -->
        <div class="ops-tab-panel" id="panelMulti">
          <div id="multiLoader" class="ops-loader">
            <div class="ops-loader-ring"></div><span>Analizando pedidos…</span>
          </div>
          <div id="multiContent" style="display:none">
            <div class="ops-section-title"><i class="fas fa-project-diagram"></i> Pedidos Multi-Estación</div>
            <div class="ops-kpi-grid" id="kpiGridMulti"></div>
            <div class="ops-grid-2">
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-chart-pie"></i> Distribución por # de Estaciones</h3>
                </div>
                <div class="ops-card-body"><canvas id="chartMultiDist" height="260"></canvas></div>
              </div>
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-table"></i> Combinaciones Frecuentes</h3>
                </div>
                <div class="ops-card-body p-0">
                  <div class="ops-table-wrap">
                    <table class="ops-table">
                      <thead>
                        <tr>
                          <th>Combinación</th>
                          <th># Estaciones</th>
                          <th>Pedidos</th>
                          <th>Items Prom</th>
                          <th>% del Total</th>
                        </tr>
                      </thead>
                      <tbody id="tbodyMulti"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PANEL: CONFIG -->
        <div class="ops-tab-panel" id="panelConfig">
          <div id="configLoader" class="ops-loader">
            <div class="ops-loader-ring"></div><span>Cargando configuración…</span>
          </div>
          <div id="configContent" style="display:none">
            <div class="ops-section-title"><i class="fas fa-sliders-h"></i> Parámetros Operativos</div>
            <p style="color:var(--ops-text-muted);font-size:.86rem;margin-bottom:20px;">
              <i class="fas fa-info-circle me-1"></i>Edita los valores y presiona <kbd>Enter</kbd> o el botón guardar.
              Los cambios se reflejan en los cálculos de simulación.
            </p>
            <div id="configPanels"></div>
          </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- PANEL: SIMULADOR DES                                      -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="ops-tab-panel" id="panelSimulador">
          <div id="simLoader" class="ops-loader" style="display:none">
            <div class="ops-loader-ring"></div><span>Ejecutando simulación…</span>
          </div>

          <div class="ops-section-title"><i class="fas fa-dice-d20"></i> Simulador de Eventos Discretos (DES)</div>

          <!-- Controles del simulador -->
          <div class="ops-sim-controls ops-card">
            <div class="ops-card-header">
              <h3><i class="fas fa-sliders-h"></i> Parámetros de Escenario</h3>
            </div>
            <div class="ops-card-body">
              <div class="ops-sim-grid">

                <!-- Columna filtros -->
                <div class="ops-sim-col">
                  <div class="ops-sim-section-label">Contexto</div>
                  <div class="ops-sim-field">
                    <label>Turno simulado</label>
                    <select id="simTurno" class="ops-select">
                      <option value="manana">Mañana (6am–2pm)</option>
                      <option value="tarde">Tarde (2pm–10pm)</option>
                      <option value="completo" selected>Día completo</option>
                    </select>
                  </div>
                  <div class="ops-sim-field">
                    <label>Tipo de día</label>
                    <select id="simTipoDia" class="ops-select">
                      <option value="todos" selected>Todos los días</option>
                      <option value="entre_semana">Entre semana</option>
                      <option value="fin_semana">Fin de semana</option>
                    </select>
                  </div>
                  <div class="ops-sim-field">
                    <label>Personas disponibles: <strong id="simPersonasVal">3</strong></label>
                    <input type="range" id="simPersonas" min="2" max="7" value="3" class="ops-slider">
                    <div class="ops-slider-ticks">
                      <span>2</span><span>3</span><span>4</span><span>5</span><span>6</span><span>7</span></div>
                  </div>
                </div>

                <!-- Estación Batidos -->
                <div class="ops-sim-col">
                  <div class="ops-sim-section-label" style="color:var(--ops-blue)"><i class="fas fa-blender"></i>
                    Batidos</div>
                  <div class="ops-sim-field">
                    <label>Licuado (min): <strong id="sBatLicVal">2.0</strong></label>
                    <input type="range" id="sBatLic" min="1" max="5" step="0.25" value="2"
                      class="ops-slider ops-slider-blue">
                  </div>
                  <div class="ops-sim-field">
                    <label>Servido+sellado (min): <strong id="sBatSerVal">0.5</strong></label>
                    <input type="range" id="sBatSer" min="0.25" max="2" step="0.25" value="0.5"
                      class="ops-slider ops-slider-blue">
                  </div>
                  <div class="ops-sim-field">
                    <label>Limpieza (min): <strong id="sBatLimVal">1.0</strong></label>
                    <input type="range" id="sBatLim" min="0.5" max="3" step="0.25" value="1"
                      class="ops-slider ops-slider-blue">
                  </div>
                  <div class="ops-sim-field">
                    <label>Nº Licuadoras</label>
                    <select id="sBatMaq" class="ops-select">
                      <option value="1">1</option>
                      <option value="2" selected>2</option>
                      <option value="3">3</option>
                      <option value="4">4</option>
                    </select>
                  </div>
                  <div class="ops-sim-field">
                    <label>Max batch</label>
                    <select id="sBatBatch" class="ops-select">
                      <option value="1">1</option>
                      <option value="2">2</option>
                      <option value="3" selected>3</option>
                    </select>
                  </div>
                </div>

                <!-- Estación Waffles -->
                <div class="ops-sim-col">
                  <div class="ops-sim-section-label" style="color:var(--ops-gold)"><i class="fas fa-bread-slice"></i>
                    Waffles</div>
                  <div class="ops-sim-field">
                    <label>Mezcla (min): <strong id="sWafMezVal">2.0</strong></label>
                    <input type="range" id="sWafMez" min="1" max="5" step="0.25" value="2"
                      class="ops-slider ops-slider-gold">
                  </div>
                  <div class="ops-sim-field">
                    <label>Cocción (min): <strong id="sWafCocVal">5.0</strong></label>
                    <input type="range" id="sWafCoc" min="3" max="8" step="0.25" value="5"
                      class="ops-slider ops-slider-gold">
                  </div>
                  <div class="ops-sim-field">
                    <label>Emplato (min): <strong id="sWafEmpVal">1.0</strong></label>
                    <input type="range" id="sWafEmp" min="0.5" max="3" step="0.25" value="1"
                      class="ops-slider ops-slider-gold">
                  </div>
                  <div class="ops-sim-field">
                    <label>Limpieza (min): <strong id="sWafLimVal">1.0</strong></label>
                    <input type="range" id="sWafLim" min="0.5" max="3" step="0.25" value="1"
                      class="ops-slider ops-slider-gold">
                  </div>
                  <div class="ops-sim-field">
                    <label>Nº Waffleras</label>
                    <select id="sWafMaq" class="ops-select">
                      <option value="1">1</option>
                      <option value="2" selected>2</option>
                      <option value="3">3</option>
                      <option value="4">4</option>
                    </select>
                  </div>
                </div>

                <!-- Estación Bowl -->
                <div class="ops-sim-col">
                  <div class="ops-sim-section-label" style="color:var(--ops-purple)"><i class="fas fa-bowl-food"></i>
                    Bowl</div>
                  <div class="ops-sim-field">
                    <label>Licuado (min): <strong id="sBowLicVal">3.0</strong></label>
                    <input type="range" id="sBowLic" min="1" max="6" step="0.25" value="3"
                      class="ops-slider ops-slider-purple">
                  </div>
                  <div class="ops-sim-field">
                    <label>Servido+decorado (min): <strong id="sBowSerVal">2.0</strong></label>
                    <input type="range" id="sBowSer" min="1" max="4" step="0.25" value="2"
                      class="ops-slider ops-slider-purple">
                  </div>
                  <div class="ops-sim-field">
                    <label>Limpieza (min): <strong id="sBowLimVal">1.0</strong></label>
                    <input type="range" id="sBowLim" min="0.5" max="3" step="0.25" value="1"
                      class="ops-slider ops-slider-purple">
                  </div>
                  <div class="ops-sim-field">
                    <label>Nº Motores</label>
                    <select id="sBowMaq" class="ops-select">
                      <option value="1" selected>1</option>
                      <option value="2">2</option>
                      <option value="3">3</option>
                    </select>
                  </div>
                  <div class="ops-sim-field">
                    <label>Max batch</label>
                    <select id="sBowBatch" class="ops-select">
                      <option value="1">1</option>
                      <option value="2" selected>2</option>
                    </select>
                  </div>
                </div>

              </div><!-- /ops-sim-grid -->

              <div class="ops-sim-actions">
                <button class="ops-btn ops-btn-primary ops-btn-run" id="btnEjecutarSim">
                  <i class="fas fa-play-circle"></i> Ejecutar Simulación
                </button>
                <button class="ops-btn ops-btn-ghost" id="btnCompararSim">
                  <i class="fas fa-chart-bar"></i> Comparar con escenario base
                </button>
              </div>
            </div>
          </div><!-- /ops-sim-controls -->

          <!-- Resultados -->
          <div id="simResultados" style="display:none">

            <!-- Gauge de cuellos de botella -->
            <div class="ops-section-title"><i class="fas fa-exclamation-triangle"></i> Análisis de Cuellos de Botella
            </div>
            <div class="ops-sim-gauges" id="simGaugesWrap"></div>

            <!-- Throughput por hora -->
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-chart-bar"></i> Throughput por Hora (pedidos completados)</h3>
                <span class="ops-badge" id="badgeSimThroughput">—</span>
              </div>
              <div class="ops-card-body">
                <div class="ops-chart-wrap"><canvas id="chartSimThroughput" height="220"></canvas></div>
              </div>
            </div>

            <!-- Timeline Gantt -->
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-stream"></i> Timeline de Equipos (vista Gantt simplificada)</h3>
              </div>
              <div class="ops-card-body p-0">
                <div id="simGanttWrap" class="ops-gantt-wrap"></div>
              </div>
            </div>

            <!-- Tabla resumen -->
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-table"></i> Métricas por Estación</h3>
              </div>
              <div class="ops-card-body p-0">
                <div class="ops-table-wrap">
                  <table class="ops-table">
                    <thead>
                      <tr>
                        <th>Estación</th>
                        <th>Utilización %</th>
                        <th>Cola Prom (min)</th>
                        <th>Wq Prom (min)</th>
                        <th>WIP Máx</th>
                        <th>Throughput</th>
                      </tr>
                    </thead>
                    <tbody id="tbodySimMetricas"></tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Panel de recomendaciones -->
            <div class="ops-card" id="simRecomendaciones">
              <div class="ops-card-header">
                <h3><i class="fas fa-lightbulb"></i> Recomendaciones Automáticas</h3>
              </div>
              <div class="ops-card-body" id="simRecomBody"></div>
            </div>

            <!-- Comparativa (aparece solo al comparar) -->
            <div id="simComparativaWrap" style="display:none">
              <div class="ops-section-title"><i class="fas fa-balance-scale"></i> Comparativa: Base vs Modificado</div>
              <div class="ops-card">
                <div class="ops-card-body p-0">
                  <div class="ops-table-wrap">
                    <table class="ops-table" id="tbodySimComparativa"></table>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /simResultados -->
        </div><!-- /panelSimulador -->

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- PANEL: LEAN SIX SIGMA                                    -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="ops-tab-panel" id="panelLean">
          <div id="leanLoader" class="ops-loader">
            <div class="ops-loader-ring"></div><span>Calculando métricas Lean…</span>
          </div>
          <div id="leanContent" style="display:none">

            <!-- Panel 1: OEE -->
            <div class="ops-section-title"><i class="fas fa-gauge-high"></i> Disponibilidad OEE</div>
            <div class="ops-grid-2" style="margin-bottom:28px">
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-circle-notch"></i> OEE — Overall Equipment Effectiveness</h3>
                </div>
                <div class="ops-card-body" style="display:flex;align-items:center;gap:36px;flex-wrap:wrap">
                  <div id="oeeGaugeWrap"></div>
                  <div id="oeeDesglose" style="flex:1;min-width:220px"></div>
                </div>
              </div>
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-clock"></i> Desglose de Tiempo Disponible</h3>
                </div>
                <div class="ops-card-body">
                  <div class="ops-chart-wrap"><canvas id="chartOeeDesglose" height="220"></canvas></div>
                </div>
              </div>
            </div>

            <!-- Panel 2: Takt Time -->
            <div class="ops-section-title"><i class="fas fa-tachometer-alt"></i> Takt Time vs Cycle Time</div>
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-chart-bar"></i> Comparativa Takt Time por Estación</h3>
                <span class="ops-badge" id="badgeTaktTime">— min/pedido</span>
              </div>
              <div class="ops-card-body">
                <div class="ops-chart-wrap"><canvas id="chartTaktTime" height="220"></canvas></div>
                <div id="taktAlerta" style="margin-top:16px"></div>
              </div>
            </div>

            <!-- Panel 3: DPMO -->
            <div class="ops-section-title"><i class="fas fa-sigma"></i> DPMO y Nivel Sigma</div>
            <div class="ops-grid-2" style="margin-bottom:28px">
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-circle-notch"></i> Nivel Sigma del Proceso</h3>
                </div>
                <div class="ops-card-body" style="display:flex;align-items:center;gap:36px;flex-wrap:wrap">
                  <div id="sigmaGaugeWrap"></div>
                  <div id="sigmaDesglose" style="flex:1;min-width:180px"></div>
                </div>
              </div>
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-table"></i> Detalle Anulaciones</h3>
                </div>
                <div class="ops-card-body p-0">
                  <div class="ops-table-wrap">
                    <table class="ops-table">
                      <thead>
                        <tr>
                          <th>Motivo</th>
                          <th>Cantidad</th>
                          <th>%</th>
                        </tr>
                      </thead>
                      <tbody id="tbodyAnulaciones"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Panel 4: 7 Desperdicios -->
            <div class="ops-section-title"><i class="fas fa-trash-alt"></i> 7 Desperdicios (Muda)</div>
            <div class="ops-muda-grid" id="mudaGrid"></div>

            <!-- Panel 5: Control Chart -->
            <div class="ops-section-title"><i class="fas fa-chart-line"></i> Control Chart — Lead Time Diario (X-bar)
            </div>
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-chart-line"></i> Gráfica de Control de Lead Time</h3>
                <span class="ops-badge" id="badgeControlChart">—</span>
              </div>
              <div class="ops-card-body">
                <div class="ops-chart-wrap"><canvas id="chartControlChart" height="260"></canvas></div>
                <div id="controlChartAlerta" style="margin-top:14px"></div>
              </div>
            </div>

          </div><!-- /leanContent -->
        </div><!-- /panelLean -->

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- PANEL: PLANIFICADOR DE CAPACIDAD                        -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="ops-tab-panel" id="panelPlanificador">

          <div class="ops-section-title"><i class="fas fa-users-cog"></i> Planificador de Capacidad por Hora</div>

          <!-- Controles del planificador -->
          <div class="ops-card" style="margin-bottom:20px;">
            <div class="ops-card-header">
              <h3><i class="fas fa-sliders-h"></i> Parámetros del Plan</h3>
            </div>
            <div class="ops-card-body">
              <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-end;">
                <div class="ops-sim-field" style="min-width:180px;">
                  <label>Objetivo utilización máx: <strong id="planUtilVal">80</strong>%</label>
                  <input type="range" id="planUtil" min="60" max="95" step="5" value="80" class="ops-slider">
                  <div class="ops-slider-ticks"><span>60%</span><span>75%</span><span>90%</span></div>
                </div>
                <div class="ops-sim-field" style="min-width:160px;">
                  <label>Tipo de día</label>
                  <select id="planTipoDia" class="ops-select">
                    <option value="todos">Todos los días</option>
                    <option value="entre_semana">Entre semana</option>
                    <option value="fin_semana">Fin de semana</option>
                  </select>
                </div>
                <div class="ops-sim-field">
                  <label>&nbsp;</label>
                  <button class="ops-btn ops-btn-primary" id="btnCalcularPlan" style="height:38px;">
                    <i class="fas fa-calculator"></i> Calcular Plan
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Loader -->
          <div id="planLoader" class="ops-loader" style="display:none;">
            <div class="ops-loader-ring"></div><span>Calculando plan de capacidad…</span>
          </div>

          <!-- Resultados -->
          <div id="planResultados" style="display:none;">

            <!-- Alerta de sucursal -->
            <div id="planAlertaSucursal" style="margin-bottom:16px;"></div>

            <!-- Heatmap de demanda -->
            <div class="ops-card" style="margin-bottom:20px;">
              <div class="ops-card-header">
                <h3><i class="fas fa-fire"></i> Heatmap de Demanda por Hora</h3>
                <span class="ops-badge" id="badgePlanHoraPico">—</span>
              </div>
              <div class="ops-card-body">
                <div id="planHeatmap" style="display:flex;gap:4px;flex-wrap:wrap;align-items:flex-end;"></div>
                <div style="display:flex;gap:16px;margin-top:12px;font-size:.78rem;color:var(--ops-text-muted);flex-wrap:wrap;">
                  <span><span style="display:inline-block;width:14px;height:14px;background:#e0f0ee;border-radius:3px;vertical-align:middle;"></span> Baja demanda</span>
                  <span><span style="display:inline-block;width:14px;height:14px;background:#51B8AC;border-radius:3px;vertical-align:middle;"></span> Media</span>
                  <span><span style="display:inline-block;width:14px;height:14px;background:#e67e22;border-radius:3px;vertical-align:middle;"></span> Alta</span>
                  <span><span style="display:inline-block;width:14px;height:14px;background:#e74c3c;border-radius:3px;vertical-align:middle;"></span> Pico crítico</span>
                </div>
              </div>
            </div>

            <!-- Tabla de plan por hora -->
            <div class="ops-card" style="margin-bottom:20px;">
              <div class="ops-card-header">
                <h3><i class="fas fa-table"></i> Plan de Dotación por Hora</h3>
                <span class="ops-badge" id="badgePlanTotal">—</span>
              </div>
              <div class="ops-card-body p-0">
                <div class="ops-table-wrap">
                  <table class="ops-table" id="tablaPlanDotacion">
                    <thead>
                      <tr>
                        <th>Hora</th>
                        <th>Demanda<br><small style="font-weight:400;opacity:.7">λ pedidos/h</small></th>
                        <th style="color:var(--ops-blue);"><i class="fas fa-blender"></i> Batidos<br><small style="font-weight:400;opacity:.7">λ parcial</small></th>
                        <th style="color:var(--ops-blue);">Licuadoras<br><small style="font-weight:400;opacity:.7">mín</small></th>
                        <th style="color:var(--ops-gold);"><i class="fas fa-bread-slice"></i> Waffles<br><small style="font-weight:400;opacity:.7">λ parcial</small></th>
                        <th style="color:var(--ops-gold);">Waffleras<br><small style="font-weight:400;opacity:.7">mín</small></th>
                        <th style="color:var(--ops-purple);"><i class="fas fa-bowl-food"></i> Bowls<br><small style="font-weight:400;opacity:.7">λ parcial</small></th>
                        <th style="color:var(--ops-purple);">Motores<br><small style="font-weight:400;opacity:.7">mín</small></th>
                        <th style="background:#f0f9ff;"><i class="fas fa-users"></i> Operarios<br><small style="font-weight:400;opacity:.7">recomendados</small></th>
                        <th>Nivel de Carga</th>
                      </tr>
                    </thead>
                    <tbody id="tbodyPlan"></tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- KPIs resumen del plan -->
            <div class="ops-kpi-grid" id="kpiGridPlan"></div>

            <!-- Gráfica de operarios por hora -->
            <div class="ops-card">
              <div class="ops-card-header">
                <h3><i class="fas fa-chart-bar"></i> Dotación Recomendada por Hora</h3>
              </div>
              <div class="ops-card-body">
                <div class="ops-chart-wrap"><canvas id="chartPlanDotacion" height="220"></canvas></div>
              </div>
            </div>

          </div><!-- /planResultados -->
        </div><!-- /panelPlanificador -->

        <div style="height:40px"></div>
      </div><!-- /opsLabWrapper -->
    </div>
  </div>

  <!-- Toast -->
  <div id="opsToast" class="ops-toast" style="display:none"></div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- MODAL DE AYUDA — pageHelpModal (requerido por header_universal) -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
      <div class="modal-content" style="border-radius:16px;border:none;font-family:'Inter',sans-serif;">

        <!-- Header -->
        <div class="modal-header" style="background:linear-gradient(135deg,#0E544C,#51B8AC);color:white;border-radius:16px 16px 0 0;padding:20px 28px;">
          <div>
            <h5 class="modal-title fw-800" id="pageHelpModalLabel" style="font-size:1.15rem;font-weight:800;margin:0;">
              <i class="fas fa-flask me-2"></i>Pitaya OPS Lab — Guía de Uso
            </h5>
            <p style="margin:4px 0 0;font-size:.82rem;opacity:.85;">Ingeniería de Operaciones · Análisis de Capacidad · Simulación DES · Lean Six Sigma</p>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <!-- Body -->
        <div class="modal-body" style="padding:28px;max-height:72vh;overflow-y:auto;background:#fafafa;">

          <!-- ── ¿QUÉ ES? ─────────────────────────────────────── -->
          <div style="background:#e8f5f3;border-left:4px solid #51B8AC;border-radius:8px;padding:14px 18px;margin-bottom:22px;">
            <strong style="color:#0E544C;"><i class="fas fa-info-circle me-1"></i>¿Qué es Pitaya OPS Lab?</strong>
            <p style="margin:6px 0 0;font-size:.875rem;color:#2d6a63;line-height:1.6;">
              Módulo de <strong>Investigación de Operaciones</strong> que analiza datos reales de ventas para modelar la capacidad de producción, detectar cuellos de botella y simular escenarios de mejora. Usa datos de BD sin necesidad de ingresar información manual.
            </p>
          </div>

          <!-- ── FILTROS GLOBALES ───────────────────────────────── -->
          <h6 style="color:#0E544C;font-weight:700;border-bottom:2px solid #e0f0ee;padding-bottom:6px;margin-bottom:14px;">
            <i class="fas fa-filter me-1"></i>Filtros Globales (barra superior)
          </h6>
          <table style="width:100%;font-size:.84rem;border-collapse:collapse;margin-bottom:22px;">
            <thead><tr style="background:#e8f5f3;"><th style="padding:8px 12px;text-align:left;">Filtro</th><th style="padding:8px 12px;text-align:left;">Descripción</th></tr></thead>
            <tbody>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Tienda</td><td style="padding:8px 12px;">Filtra por sucursal. "Todas" muestra datos consolidados de las 14 tiendas.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Fecha Inicio / Fin</td><td style="padding:8px 12px;">Rango de análisis. Por defecto el mes anterior completo.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Tipo de Día</td><td style="padding:8px 12px;"><strong>Todos</strong>: lunes-domingo · <strong>Entre semana</strong>: lun-jue · <strong>Fin de semana</strong>: vie-dom.</td></tr>
              <tr><td style="padding:8px 12px;font-weight:600;">Turno</td><td style="padding:8px 12px;"><strong>Mañana</strong>: pedidos hasta las 14:00 · <strong>Tarde</strong>: pedidos desde las 14:01 · <strong>Ambos</strong>: día completo.</td></tr>
            </tbody>
          </table>

          <!-- ── TABS ──────────────────────────────────────────── -->
          <h6 style="color:#0E544C;font-weight:700;border-bottom:2px solid #e0f0ee;padding-bottom:6px;margin-bottom:14px;">
            <i class="fas fa-layer-group me-1"></i>Pestañas del Módulo
          </h6>

          <!-- Resumen -->
          <div style="background:white;border:1px solid #e0f0ee;border-radius:10px;padding:14px 18px;margin-bottom:12px;">
            <div style="font-weight:700;color:#0E544C;margin-bottom:6px;"><i class="fas fa-chart-pie me-1" style="color:#51B8AC;"></i>1. Resumen</div>
            <p style="font-size:.84rem;margin:0;line-height:1.6;color:#444;">Vista general del período. Muestra KPIs principales (total pedidos, unidades, ticket promedio, pedidos/día, ventas totales), mix de ventas por estación en donut chart, y las 3 horas de mayor demanda con barra de intensidad.</p>
          </div>

          <!-- Llegadas -->
          <div style="background:white;border:1px solid #e0f0ee;border-radius:10px;padding:14px 18px;margin-bottom:12px;">
            <div style="font-weight:700;color:#0E544C;margin-bottom:6px;"><i class="fas fa-wave-square me-1" style="color:#51B8AC;"></i>2. Llegadas &amp; λ (Lambda)</div>
            <p style="font-size:.84rem;margin:0;line-height:1.6;color:#444;">Calcula la <strong>tasa de llegada Poisson (λ)</strong> promedio de pedidos por hora del día. La intensidad de color de cada barra representa la carga relativa. La tabla detalla pedidos totales, días observados y unidades promedio por franja horaria. Útil para planificar turnos.</p>
          </div>

          <!-- Cycle Times -->
          <div style="background:white;border:1px solid #e0f0ee;border-radius:10px;padding:14px 18px;margin-bottom:12px;">
            <div style="font-weight:700;color:#0E544C;margin-bottom:6px;"><i class="fas fa-stopwatch me-1" style="color:#51B8AC;"></i>3. Tiempos de Proceso — Análisis de Caja</div>
            <p style="font-size:.84rem;margin:0;line-height:1.6;color:#444;">
              <strong>⚠️ Importante:</strong> El sistema <strong>no registra la hora de entrega del producto</strong> al cliente. Los tiempos medidos desde BD son del proceso de caja:<br>
              &bull; <strong>Tiempo de Caja</strong> (<code>HoraCreado → HoraImpreso</code>): desde que el cajero inicia la factura hasta que se imprime y manda la comanda a las estaciones. Refleja la eficiencia del proceso de facturación.<br>
              &bull; <strong>Tiempo Ingreso Productos</strong> (<code>HoraIngresoProducto → HoraImpreso</code>): desde que se empiezan a ingresar ítems hasta que se imprime. Subconjunto del anterior.<br>
              &bull; <strong>Tiempo de preparación</strong> (post-HoraImpreso): NO medible desde BD — se estima con los parámetros configurados en la pestaña Configuración.<br>
              Outliers mayores a 2 horas se excluyen automáticamente.
            </p>
          </div>

          <!-- Mix Estaciones -->
          <div style="background:white;border:1px solid #e0f0ee;border-radius:10px;padding:14px 18px;margin-bottom:12px;">
            <div style="font-weight:700;color:#0E544C;margin-bottom:6px;"><i class="fas fa-layer-group me-1" style="color:#51B8AC;"></i>4. Mix de Estaciones</div>
            <p style="font-size:.84rem;margin:0;line-height:1.6;color:#444;">Muestra cuántos pedidos por hora van a cada estación (Batidos, Waffles, Bowls) en gráfica de barras apiladas y tabla de porcentajes. Permite ver en qué horas se concentra la presión sobre cada estación de trabajo.</p>
          </div>

          <!-- Multi-Estación -->
          <div style="background:white;border:1px solid #e0f0ee;border-radius:10px;padding:14px 18px;margin-bottom:12px;">
            <div style="font-weight:700;color:#0E544C;margin-bottom:6px;"><i class="fas fa-project-diagram me-1" style="color:#51B8AC;"></i>5. Multi-Estación</div>
            <p style="font-size:.84rem;margin:0;line-height:1.6;color:#444;">Analiza pedidos que involucran más de una estación simultáneamente (ej. un pedido con batido + waffle). Muestra el porcentaje de pedidos simples (1 estación), dobles y triples, y las combinaciones más frecuentes. Impacta directamente en el tiempo total de entrega.</p>
          </div>

          <!-- Config -->
          <div style="background:white;border:1px solid #e0f0ee;border-radius:10px;padding:14px 18px;margin-bottom:12px;">
            <div style="font-weight:700;color:#0E544C;margin-bottom:6px;"><i class="fas fa-sliders-h me-1" style="color:#51B8AC;"></i>6. Configuración Operativa</div>
            <p style="font-size:.84rem;margin:0;line-height:1.6;color:#444;">Parámetros editables almacenados en BD (<code>ops_config_estaciones</code>). Edita un valor y presiona <kbd>Enter</kbd> o el botón ✓. Los cambios se usan en los cálculos del Simulador DES. Incluye tiempos de proceso, número de equipos, tamaños de batch y parámetros generales de turno.</p>
          </div>

          <!-- Simulador -->
          <div style="background:white;border:1px solid #e0f0ee;border-radius:10px;padding:14px 18px;margin-bottom:12px;">
            <div style="font-weight:700;color:#0E544C;margin-bottom:6px;"><i class="fas fa-dice-d20 me-1" style="color:#51B8AC;"></i>7. Simulador DES</div>
            <p style="font-size:.84rem;margin:0;line-height:1.6;color:#444;">
              <strong>Motor de Simulación de Eventos Discretos</strong> que usa la distribución real de llegadas (λ por hora) y el mix de estaciones de la BD. Pasos:<br>
              1. Ajusta los sliders de parámetros (tiempos, máquinas, batch).<br>
              2. Selecciona turno y tipo de día.<br>
              3. Pulsa <strong>Ejecutar Simulación</strong>.<br>
              4. Compara escenarios con <strong>Comparar con escenario base</strong>.<br><br>
              Los gauges muestran la <strong>utilización de cada estación</strong>. El marcado en rojo como "CUELLO DE BOTELLA" indica la estación con mayor utilización. El Gantt muestra la carga por equipo y hora.
            </p>
          </div>

          <!-- Lean -->
          <div style="background:white;border:1px solid #e0f0ee;border-radius:10px;padding:14px 18px;margin-bottom:22px;">
            <div style="font-weight:700;color:#0E544C;margin-bottom:6px;"><i class="fas fa-leaf me-1" style="color:#51B8AC;"></i>8. Lean Six Sigma</div>
            <p style="font-size:.84rem;margin:0;line-height:1.6;color:#444;">
              • <strong>OEE</strong>: Disponibilidad del turno descontando setup (30 min) y limpiezas (6×15 min = 90 min) → 360 min netos de 480.<br>
              • <strong>Takt Time</strong>: Tiempo disponible ÷ demanda diaria. Si el Cycle Time de una estación supera el Takt Time, no puede seguir el ritmo.<br>
              • <strong>DPMO / Nivel Sigma</strong>: Defectos (pedidos anulados) por millón de oportunidades → nivel de calidad 1σ–6σ.<br>
              • <strong>7 Desperdicios (Muda)</strong>: Diagnóstico visual con semáforo.<br>
              • <strong>Control Chart X-bar</strong>: Lead time diario con límites UCL/LCL. Puntos fuera = proceso inestable.
            </p>
          </div>

          <!-- ── GLOSARIO ────────────────────────────────────────── -->
          <h6 style="color:#0E544C;font-weight:700;border-bottom:2px solid #e0f0ee;padding-bottom:6px;margin-bottom:14px;">
            <i class="fas fa-book me-1"></i>Diccionario de Términos
          </h6>
          <table style="width:100%;font-size:.83rem;border-collapse:collapse;margin-bottom:22px;">
            <thead><tr style="background:#e8f5f3;"><th style="padding:8px 12px;text-align:left;width:28%;">Término</th><th style="padding:8px 12px;text-align:left;">Definición</th></tr></thead>
            <tbody>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">λ (Lambda)</td><td style="padding:8px 12px;">Tasa de llegada Poisson: pedidos promedio que llegan por hora. Derivada de datos reales ÷ días observados.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Lead Time</td><td style="padding:8px 12px;"><strong>No medible directamente en BD</strong>. Sería HoraCreado → entrega al cliente, pero no se registra la hora de entrega. El Lead Time real = Tiempo de Caja + Tiempo de Preparación (estimado).</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Tiempo de Caja</td><td style="padding:8px 12px;"><code>HoraCreado → HoraImpreso</code>. Tiempo que tarda el cajero en facturar e imprimir la comanda. Inicio del proceso de preparación.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Tiempo Ingreso Productos</td><td style="padding:8px 12px;"><code>HoraIngresoProducto → HoraImpreso</code>. Subconjunto del tiempo de caja: desde que se empiezan a ingresar ítems hasta imprimir.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Wq (Diferencia)</td><td style="padding:8px 12px;">Tiempo de Caja − Tiempo Ingreso Productos. Tiempo previo al ingreso de productos en la misma facturación.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">WIP</td><td style="padding:8px 12px;">Work In Process: máxima cantidad de pedidos simultáneos en cola de una estación durante la simulación.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Throughput</td><td style="padding:8px 12px;">Pedidos completados por hora en cada estación. Capacidad real de producción.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Utilización %</td><td style="padding:8px 12px;">% del tiempo que el equipo está ocupado procesando. &gt;85% = cuello de botella. Ideal: 70-80%.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">DES</td><td style="padding:8px 12px;">Discrete Event Simulation. Simula cada llegada de pedido como un evento individual con tiempos variables.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Cuello de Botella</td><td style="padding:8px 12px;">Estación con mayor utilización que limita el throughput del sistema completo.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Takt Time</td><td style="padding:8px 12px;">Tiempo disponible ÷ demanda diaria. Ritmo máximo al que el sistema debe producir para satisfacer la demanda.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">OEE</td><td style="padding:8px 12px;">Overall Equipment Effectiveness. Aquí = Disponibilidad: tiempo neto operativo ÷ tiempo total del turno.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">DPMO</td><td style="padding:8px 12px;">Defects Per Million Opportunities. (Anulaciones ÷ Pedidos totales) × 1,000,000. Base para calcular Nivel Sigma.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Nivel Sigma (σ)</td><td style="padding:8px 12px;">Escala de calidad: 3σ = estándar industria alimentos (66,807 DPMO), 4σ = objetivo Pitaya, 6σ = casi perfección (3.4 DPMO).</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Muda (7 Desperdicios)</td><td style="padding:8px 12px;">Concepto Lean: Sobreproducción, Espera, Transporte, Sobreproceso, Inventario, Movimiento, Defectos.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Control Chart (X-bar)</td><td style="padding:8px 12px;">Gráfica de control estadístico con UCL (límite superior), LCL (límite inferior) y media. Puntos fuera = causa especial.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">UCL / LCL</td><td style="padding:8px 12px;">Upper/Lower Control Limit = Media ± 3σ. Define el rango de variación "normal" del proceso.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Pool de Operarios</td><td style="padding:8px 12px;">Modelo donde todos los operarios son polivalentes y se reasignan dinámicamente a la estación con mayor cola.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Batch</td><td style="padding:8px 12px;">Lote de procesamiento: máx. pedidos del mismo producto procesados en una sola corrida de máquina (ej. 3 batidos en 1 licuada).</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">HoraCreado</td><td style="padding:8px 12px;">Momento en que el cajero inicia la factura del pedido. Inicio del Lead Time.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">HoraImpreso</td><td style="padding:8px 12px;">Momento en que se imprime la comanda y pasa a las estaciones de trabajo. Inicio del Cycle Time.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">HoraIngresoProducto</td><td style="padding:8px 12px;">Hora en que se comienza a facturar/registrar productos en el pedido. Corresponde al inicio real de preparación.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Estación Batido</td><td style="padding:8px 12px;">Incluye <code>GrupoProductosVenta.Tipo = 'Batido'</code> y <code>'Limonada'</code>. Proceso: insumos → licuado (2 min) → servido (0.5 min). 2 licuadoras, batch de 3.</td></tr>
              <tr style="border-bottom:1px solid #eee;"><td style="padding:8px 12px;font-weight:600;">Estación Waffles</td><td style="padding:8px 12px;"><code>GrupoProductosVenta.Tipo = 'Waffles'</code>. Proceso: mezcla (2 min) → cocción (5 min) → emplato (1 min) → limpieza (1 min). 2 waffleras. Operarios rotan según carga, hasta 3 simultáneos.</td></tr>
              <tr><td style="padding:8px 12px;font-weight:600;">Estación Bowl</td><td style="padding:8px 12px;"><code>GrupoProductosVenta.Tipo = 'Bowl'</code>. Proceso: insumos → licuado pesado (3 min) → decorado (2 min) → limpieza (1 min). 1 motor, batch de 2.</td></tr>
            </tbody>
          </table>

          <!-- ── NOTA BD ─────────────────────────────────────────── -->
          <div style="background:#fff8e1;border-left:4px solid #ffc107;border-radius:8px;padding:12px 16px;font-size:.82rem;color:#6d5102;">
            <strong><i class="fas fa-database me-1"></i>Nota técnica — Joins de BD:</strong><br>
            Para clasificar productos por estación se usa: <code>VentasGlobalesAccessCSV.CodProducto → DBBatidos.CodBatido → DBBatidos.CodGrupo → GrupoProductosVenta.CodGrupo → GrupoProductosVenta.Tipo</code>.<br>
            Para filtrar por sucursal: <code>VentasGlobalesAccessCSV.local = sucursales.codigo</code> (ambos VARCHAR).
          </div>

        </div><!-- /modal-body -->

        <div class="modal-footer" style="background:#f8fffe;border-radius:0 0 16px 16px;border-top:1px solid #e0f0ee;">
          <small style="color:#888;flex:1;">Pitaya OPS Lab v2.1 · Ingeniería de Operaciones</small>
          <button type="button" class="btn btn-sm" data-bs-dismiss="modal"
            style="background:#51B8AC;color:white;border:none;border-radius:8px;padding:6px 20px;font-weight:600;">
            Entendido
          </button>
        </div>

      </div>
    </div>
  </div>
  <!-- /pageHelpModal -->

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="js/pitaya_ops_lab.js?v=<?php echo mt_rand(1, 9999); ?>"></script>
</body>

</html>