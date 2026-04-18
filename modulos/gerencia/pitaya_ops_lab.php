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
            <div class="ops-section-title"><i class="fas fa-stopwatch"></i> Tiempos de Ciclo por Estación</div>
            <div class="ops-kpi-grid" id="kpiGridCycle"></div>
            <div class="ops-grid-2">
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-chart-bar"></i> Lead Time vs Cycle Time (min)</h3>
                </div>
                <div class="ops-card-body"><canvas id="chartCycleTimes" height="250"></canvas></div>
              </div>
              <div class="ops-card">
                <div class="ops-card-header">
                  <h3><i class="fas fa-hourglass-half"></i> Tiempo en Cola Promedio</h3>
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
                        <th>Lead Time Prom</th>
                        <th>Cycle Time Prom</th>
                        <th>Cola Prom</th>
                        <th>Lead Mín</th>
                        <th>Lead Máx</th>
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

        <div style="height:40px"></div>
      </div><!-- /opsLabWrapper -->
    </div>
  </div>

  <!-- Toast -->
  <div id="opsToast" class="ops-toast" style="display:none"></div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="js/pitaya_ops_lab.js?v=<?php echo mt_rand(1, 9999); ?>"></script>
</body>

</html>