<?php
/* ============================================================
   PEDIDO SUGERIDO v2 — Cálculo de orden de compra por sucursal
   (Con Plan de Despacho integrado)
   modulos/productos/pedido_sugerido_v2.php
   ============================================================ */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pedido_sugerido', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeEditar = tienePermiso('pedido_sugerido', 'edicion', $cargoOperario);
$version = mt_rand(1, 10000);

// --- Predeterminar Semanas (Server-Side para evitar delay) ---
$semActual = '';
$semDesdeDefault = '';
$semHastaDefault = '';
try {
    $stmtSem = $conn->query("SELECT numero_semana FROM SemanasSistema WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin LIMIT 1");
    $resSem = $stmtSem->fetch(PDO::FETCH_ASSOC);
    if ($resSem) {
        $semActual = (int) $resSem['numero_semana'];
        $semDesdeDefault = $semActual - 5;
        $semHastaDefault = $semActual - 1;
    }
} catch (Exception $e) { /* Silencioso */
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Sugerido v2 · Pitaya ERP</title>
    <meta name="description"
        content="Calcula el pedido sugerido de insumos por sucursal en base a consumo histórico y configuración logística.">

    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="css/pedido_sugerido_v2.css?v=<?php echo $version; ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Pedido Sugerido'); ?>

            <div class="ps-wrapper p-3">

                <!-- ══════════════════════════════════════════ -->
                <!--  PANEL DE FILTROS                         -->
                <!-- ══════════════════════════════════════════ -->
                <div class="card border-0 shadow-sm mb-3 ps-filtros-card">
                    <div class="card-body py-2 px-3">
                        <div class="row g-2 align-items-end">

                            <!-- Semana Desde -->
                            <div class="col-6 col-md-2">
                                <label class="ps-label" for="filtroSemanaDesde">
                                    <i class="fas fa-hashtag me-1"></i>Semana Desde
                                </label>
                                <input type="number" class="form-control form-control-sm ps-input-semana"
                                    id="filtroSemanaDesde" min="1" max="9999" placeholder="Ej: 535"
                                    value="<?php echo $semDesdeDefault; ?>">
                            </div>

                            <!-- Semana Hasta -->
                            <div class="col-6 col-md-2">
                                <label class="ps-label" for="filtroSemanaHasta">
                                    <i class="fas fa-hashtag me-1"></i>Semana Hasta
                                </label>
                                <input type="number" class="form-control form-control-sm ps-input-semana"
                                    id="filtroSemanaHasta" min="1" max="9999" placeholder="Ej: 538"
                                    value="<?php echo $semHastaDefault; ?>">
                            </div>

                            <!-- Sucursal (obligatorio) -->
                            <div class="col-12 col-md-3">
                                <label class="ps-label" for="filtroSucursal">
                                    <i class="fas fa-store me-1"></i>Sucursal
                                    <span class="text-danger ms-1" title="Requerido">*</span>
                                </label>
                                <select class="form-select form-select-sm ps-select" id="filtroSucursal">
                                    <option value="">— Selecciona una tienda —</option>
                                </select>
                            </div>

                            <!-- Semana actual + Botón Calcular -->
                            <div class="col-12 col-md-5 d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                <div id="badgeSemanaActual"
                                    class="ps-badge-semana <?php echo !empty($semActual) ? '' : 'd-none'; ?>">
                                    <i class="fas fa-calendar-check"></i>
                                    Sem. actual: <strong id="semanaActualNum"><?php echo $semActual; ?></strong>
                                </div>
                                <button class="btn btn-sm ps-btn-primary" id="btnCalcular">
                                    <i class="fas fa-calculator me-1"></i>Calcular
                                </button>
                            </div>

                            <!-- Separador visual -->
                            <div class="col-12">
                                <hr class="my-1 opacity-25">
                            </div>

                            <!-- Sem. Corte (Pronóstico) -->
                            <div class="col-auto">
                                <label class="ps-label" for="semCortePron"
                                    title="Semana cuyo inventario real (domingo) se usa como punto de partida del pronóstico">
                                    <i class="bi bi-graph-up-arrow me-1"></i>Sem. Corte (Pronóst.)
                                </label>
                                <input type="number" id="semCortePron"
                                    class="form-control form-control-sm ps-input-semana"
                                    placeholder="ej: <?php echo $semHastaDefault; ?>" min="1" max="9999"
                                    style="width:120px"
                                    title="Número de semana cuyo inventario real (domingo) se usa como base del pronóstico D-1">
                                <div class="form-text small text-muted" style="font-size:10px">Semana de inventario real
                                </div>
                            </div>

                            <!-- Botón Calcular Pronóstico -->
                            <div class="col-auto d-flex align-items-end">
                                <button id="btnCalcularPronostico" class="btn btn-outline-primary btn-sm" disabled
                                    title="Calcula el stock proyectado al día anterior al próximo despacho">
                                    <i class="bi bi-graph-up-arrow me-1"></i> Calcular Pronóstico D-1
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  ESTADO INICIAL                          -->
                <!-- ══════════════════════════════════════════ -->
                <div id="panelInicial" class="ps-empty-state">
                    <div class="ps-empty-icon"><i class="fas fa-shopping-cart"></i></div>
                    <h5>Configura los filtros</h5>
                    <p class="text-muted">Selecciona el rango de semanas y la sucursal, luego haz clic en
                        <strong>Calcular</strong>.
                    </p>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  LOADER                                   -->
                <!-- ══════════════════════════════════════════ -->
                <div id="panelLoader" class="ps-loader d-none">
                    <div class="ps-loader-inner">
                        <div class="spinner-border ps-spinner" role="status"></div>
                        <div class="ps-loader-text">Calculando pedido sugerido…<br>
                            <small class="text-muted">Procesando consumo y fórmulas logísticas</small>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════ -->
                <!--  PANEL DE DATOS                          -->
                <!-- ══════════════════════════════════════════ -->
                <div id="panelDatos" class="d-none">

                    <!-- KPI Cards -->
                    <div class="row g-2 mb-3" id="kpiRow">
                        <div class="col-6 col-lg-3">
                            <div class="ps-kpi-card">
                                <div class="ps-kpi-icon" style="color:#51B8AC"><i class="fas fa-hashtag"></i></div>
                                <div class="ps-kpi-label">Semanas Analizadas</div>
                                <div class="ps-kpi-valor" id="kpiNSemanas">—</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="ps-kpi-card">
                                <div class="ps-kpi-icon" style="color:#3b82f6"><i class="fas fa-boxes"></i></div>
                                <div class="ps-kpi-label">Productos</div>
                                <div class="ps-kpi-valor" id="kpiNProductos">—</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="ps-kpi-card">
                                <div class="ps-kpi-icon" style="color:#3b82f6"><i class="bi bi-snow2"></i></div>
                                <div class="ps-kpi-label">Capacidad Congelados</div>
                                <div class="ps-kpi-valor" id="kpiCapacidadCongelados">—</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="ps-kpi-card">
                                <div class="ps-kpi-icon" style="color:#8b5cf6"><i class="fas fa-percentage"></i></div>
                                <div class="ps-kpi-label">Factor Congelados</div>
                                <div class="ps-kpi-valor" id="kpiFactorCongelados">—</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla principal -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div
                                class="ps-tabla-toolbar px-3 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span id="labelResultados" class="ps-result-count"></span>`
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="text" class="form-control form-control-sm" id="buscarProducto"
                                        placeholder="Buscar producto…" style="max-width:200px">
                                </div>
                            </div>
                            <div id="tablaWrapper">
                                <table class="table table-hover ps-tabla mb-0" id="tablaProductos">
                                    <thead>
                                        <tr>
                                            <th class="col-producto align-bottom">Producto</th>
                                            <th class="col-presentacion align-bottom">Presentación</th>
                                            <th class="text-end bg-light-gray col-num align-bottom" title="Promedio de consumo semanal">Prom./Sem.</th>
                                            <th class="text-end bg-light-gray col-num align-bottom text-muted" title="Desviación estándar de muestra" style="font-weight: 600;">Desv. Std</th>
                                            <th class="text-end bg-light-gray col-num align-bottom" title="Consumo semanal = promedio + desv. estándar" style="color: var(--pitaya-dark);">Cons. Semanal</th>
                                            <th class="text-end bg-light-gray col-num align-bottom text-muted" title="Consumo diario = (cons_semanal × (1 + ajuste)) / 7" style="font-weight: 600;">Cons. Diario</th>
                                            <th class="text-end bg-mid-gray col-num align-bottom" title="Stock mínimo en unidades de despacho = (cons_diario × dias_stock_minimo) ÷ factor_despacho">Stock Mín</th>
                                            <th class="text-end bg-mid-gray col-num align-bottom" title="Stock máximo en unidades de despacho = (cons_diario × (ciclo + desfase + stock_min)) ÷ factor_despacho">Stock Máx</th>
                                            <th class="text-end bg-mid-gray col-num align-bottom" title="Stock máximo final en unidades de despacho (ajustado para congelados si aplica)" style="color: var(--pitaya-dark);">Stock Máx Final</th>

                                            <th class="text-center col-pronostico align-bottom" title="Próximo despacho de esta categoría según el plan">Próx. Despacho</th>
                                            <th class="text-end col-pronostico col-num align-bottom" title="Stock estimado al cierre del día anterior al despacho">Stock Pronóst. D-1</th>
                                            <th class="text-center col-pronostico col-num align-bottom text-primary" title="ceil(Stock Máx Final − Stock D-1). Requiere calcular pronóstico.">Despacho Pron. (paq)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyProductos">
                                        <tr>
                                            <td colspan="12" class="text-center text-muted py-4">
                                                Aplica los filtros para calcular el pedido sugerido.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div><!-- /panelDatos -->

            </div><!-- /ps-wrapper -->
        </div><!-- /sub-container -->
    </div><!-- /main-container -->

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        const PUEDE_EDITAR = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    </script>
    <!-- ===================================================
         MODAL DE AYUDA TÉCNICA (GUÍA DE USO)
         =================================================== -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-header"
                    style="background: var(--pitaya-dark); color: white; border-radius: 15px 15px 0 0;">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="pageHelpModalLabel">
                        <i class="bi bi-info-circle-fill"></i> Guía Técnica: Pedido Sugerido
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Introducción -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2">¿Qué es el Pedido Sugerido?</h6>
                        <p class="text-secondary small">
                            Es una herramienta inteligente que analiza automáticamente el <b>historial de ventas y
                                movimientos de inventario</b> del periodo que seleccionaste. Su objetivo es calcular la
                            cantidad óptima de producto que debes solicitar para mantener la tienda operando sin
                            interrupciones.
                        </p>
                        <p class="text-secondary small">
                            A diferencia de un pedido manual, este sistema considera no solo cuánto vendes, sino también
                            la <b>variabilidad del consumo</b> y el tiempo que tarda el proveedor en entregarte,
                            asegurando que siempre tengas un "colchón" de seguridad y que el pedido no exceda la
                            capacidad física de tus estantes o congeladores.
                        </p>
                    </div>

                    <!-- Fórmulas -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100 border">
                                <h6 class="fw-bold small mb-2"><i class="bi bi-calculator me-1"></i> Demanda Base</h6>
                                <ul class="small text-muted mb-0">
                                    <li><b>Prom. Consumo:</b> Promedio sobre la <b>Ventana Activa</b> (ver abajo).</li>
                                    <li><b>Cons. Semanal:</b> Promedio + Desv. Estándar (Colchón de seguridad).</li>
                                    <li><b>Ajuste demanda:</b> Afecta directamente al consumo proyectado (+/- %).</li>
                                    <li><b>Consumo Diario:</b> (Semanal × Ajuste) ÷ 7 días.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100 border">
                                <h6 class="fw-bold small mb-2"><i class="bi bi-box-seam me-1"></i> Niveles de Stock</h6>
                                <ul class="small text-muted mb-0">
                                    <li><b>Stock Mín:</b> Nivel crítico en <b>unidades de despacho</b> = (Consumo Diario
                                        × Días Stock Mínimo) ÷ Factor Despacho.</li>
                                    <li><b>Stock Máx:</b> Capacidad teórica en <b>unidades de despacho</b> = (Consumo
                                        Diario × (Ciclo + Desfase + Stock Mín)) ÷ Factor Despacho.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Ventana Activa -->
                    <div class="mb-4 p-3 rounded-3 border" style="background:#fff8f0;border-color:#f0ad4e !important;">
                        <h6 class="fw-bold small d-flex align-items-center gap-2 mb-2" style="color:#c87800;">
                            <i class="bi bi-window-sidebar"></i> Ventana Activa — Promedio Inteligente
                        </h6>
                        <p class="text-secondary small mb-2">
                            Cuando un insumo se incorpora a mitad del periodo o es reemplazado, las semanas sin consumo
                            <b>no reflejan demanda real cero</b> — son ceros estructurales. Incluirlas en el promedio
                            subestimaría el pedido.
                        </p>
                        <p class="text-secondary small mb-2">
                            El sistema detecta la <b>primera y última semana con consumo significativo</b> y promedia
                            solo dentro de ese rango. Para evitar que residuos de redondeo o artefactos de conversión
                            expandan la ventana erróneamente, se aplica un <b>umbral relativo</b>:
                        </p>
                        <div class="p-2 rounded-2 small font-monospace mb-2" style="background:#fff3e0;color:#7a4400;">
                            umbral = max(0.01, media_no_cero × 10%)<br>
                            <span class="text-muted">Semanas con valor &lt; umbral en los extremos → tratadas como cero
                                estructural</span>
                        </div>
                        <div class="p-2 rounded-2 small font-monospace" style="background:#fff3e0;color:#7a4400;">
                            Prom./Sem. = Σ consumo / N<sub>activa</sub><br>
                            <span class="text-muted">donde N<sub>activa</sub> = sem. último consumo significativo − sem.
                                primer consumo significativo + 1</span>
                        </div>
                        <div class="mt-2 d-flex gap-3 flex-wrap small text-muted">
                            <span><i class="bi bi-x-circle text-danger me-1"></i><b>Ceros / ínfimos al inicio/fin</b> →
                                excluidos (cambio de insumo o artefacto)</span>
                            <span><i class="bi bi-check-circle text-success me-1"></i><b>Ceros intermedios</b> →
                                incluidos (semana sin uso real)</span>
                        </div>
                    </div>

                    <!-- Factor de Congelados -->
                    <div class="mb-4 p-3 bg-opacity-10 bg-info rounded-3 border-info border border-opacity-25">
                        <h6 class="fw-bold small text-info-emphasis d-flex align-items-center gap-2">
                            <i class="bi bi-snow2"></i> Capacidad de Congelados (Ajuste B)
                        </h6>
                        <p class="text-secondary small mb-0">
                            Si la suma de los <b>Stock Máximos</b> de la Categoría B excede la capacidad de la sucursal,
                            el sistema aplica un **Factor de Reducción Proporcional** (nunca mayor a 1.0).
                            Esto garantiza que el pedido sugerido no exceda lo que físicamente cabe en los congeladores.
                        </p>
                    </div>

                    <!-- Glosario de Variables -->
                    <div class="mb-4">
                        <h6 class="fw-bold text-dark border-bottom pb-2">Glosario de Columnas y Variables</h6>

                        <div class="row g-3 mt-1">
                            <!-- Tabla Principal -->
                            <div class="col-md-6">
                                <h7 class="fw-bold text-primary small d-block mb-2">Columnas Principales</h7>
                                <ul class="list-unstyled small text-muted">
                                    <li class="mb-1"><b>Prom. Consumo:</b> Promedio semanal sobre la Ventana Activa
                                        (excluye ceros estructurales de inicio/fin).</li>
                                    <li class="mb-1"><b>Desv. Estándar:</b> Qué tanto varía el consumo semana a semana
                                        (mide la incertidumbre).</li>
                                    <li class="mb-1"><b>Cons. Semanal:</b> La demanda base "segura" (Promedio +
                                        Desviación).</li>
                                    <li class="mb-1"><b>Cap. Base (Final):</b> El Stock Máximo ya ajustado a lo que cabe
                                        físicamente en tienda.</li>
                                    <li class="mb-1"><b>Sugerencia:</b> La resta entre el Stock Máximo Final y tu
                                        Inventario Actual.</li>
                                </ul>
                            </div>
                            <!-- Desglose Indicadores -->
                            <div class="col-md-6 border-start ps-3">
                                <h7 class="fw-bold text-primary small d-block mb-2">Indicadores (Encabezado)</h7>
                                <ul class="list-unstyled small text-muted">
                                    <li class="mb-1"><b>Adj:</b> Porcentaje manual de aumento o disminución de la
                                        demanda.</li>
                                    <li class="mb-1"><b>Ciclo:</b> Cuántos días pasan entre un pedido y el siguiente.
                                    </li>
                                    <li class="mb-1"><b>Desfase:</b> Cuántos días tarda el proveedor en entregar el
                                        producto.</li>
                                    <li class="mb-1"><b>S.Mín:</b> Días de reserva que quieres tener siempre "por si
                                        acaso".</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Presentación de Despacho y Conversiones -->
                    <div class="mb-4 p-3 rounded-3 border" style="background:#f0fdf4;border-color:#86efac !important;">
                        <h6 class="fw-bold small d-flex align-items-center gap-2 mb-3" style="color:#15803d;">
                            <i class="bi bi-truck"></i> Presentación de Despacho y Rastreo de Conversiones
                        </h6>

                        <p class="text-secondary small mb-2">
                            Los stocks y el pedido sugerido se expresan en <b>unidades de despacho</b> (ej: Cajilla,
                            Bolsa, Kg) para que coincidan con lo que realmente se ordena al proveedor.
                            El sistema resuelve la presentación de despacho de cada producto en <b>tres pasos</b> en
                            orden de prioridad:
                        </p>

                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <div class="p-2 rounded-2 border h-100" style="background:#dcfce7;">
                                    <div class="fw-bold small mb-1" style="color:#166534;"><i
                                            class="bi bi-1-circle-fill me-1"></i>Paso B — Receta-Paquete Exacta <span
                                            class="badge bg-success ms-1" style="font-size:9px;">Prioridad</span></div>
                                    <p class="small text-muted mb-0">Busca una presentación con
                                        <code>presentacion_despacho=1</code> cuya receta tenga <b>exactamente 1
                                            componente</b> = la presentación básica del insumo. El factor es la cantidad
                                        en la receta. Ej: <em>Banano Cajilla 100u → 100 × Banano unid → factor 100</em>.
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 rounded-2 border h-100" style="background:#f0fdf4;">
                                    <div class="fw-bold small mb-1" style="color:#166534;"><i
                                            class="bi bi-2-circle-fill me-1"></i>Paso A — Por Producto Maestro <span
                                            class="badge bg-secondary ms-1" style="font-size:9px;">Fallback</span></div>
                                    <p class="small text-muted mb-0">Si B falla, busca cualquier presentación del mismo
                                        <code>producto_maestro</code> con <code>presentacion_despacho=1</code>. El
                                        factor = <code>cant_despacho / (cant_básica × conversión_unidades)</code>. Ej:
                                        <em>Fresa 1oz → Bandeja 400gr</em>.
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 rounded-2 border h-100" style="background:#fefce8;">
                                    <div class="fw-bold small mb-1" style="color:#854d0e;"><i
                                            class="bi bi-3-circle-fill me-1"></i>Paso C — Receta-Paquete por Maestro
                                        <span class="badge bg-warning text-dark ms-1" style="font-size:9px;">Último
                                            recurso</span>
                                    </div>
                                    <p class="small text-muted mb-0">Solo si B <b>y</b> A fallan: busca una receta de
                                        despacho cuyo componente comparta el mismo maestro (aunque no sea la
                                        presentación exacta). Ej: <em>Naranja oz → Cajilla 100u</em> (la cajilla no
                                        tiene maestro propio por ser receta, pero su componente "Naranja Unidad" sí
                                        comparte maestro).</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-2 rounded-2 mb-3 border"
                            style="background:#fff7ed;border-color:#fdba74 !important;">
                            <div class="fw-bold small mb-1" style="color:#9a3412;"><i
                                    class="bi bi-bezier2 me-1"></i>Conversiones Transitivas (oz → gr → kg)</div>
                            <p class="small text-muted mb-1">El sistema resuelve cadenas de conversión multi-salto
                                usando el algoritmo <b>Floyd-Warshall</b>. No es necesario tener una fila directa <em>oz
                                    → kg</em> en la tabla de conversiones — basta con tener <em>oz → gr</em> y <em>gr →
                                    kg</em> y el sistema construye el camino automáticamente en memoria.</p>
                            <div class="font-monospace" style="font-size:11px;color:#9a3412;">oz → gr → kg &nbsp;=&nbsp;
                                (1/28.35) × (1/1000) &nbsp;=&nbsp; factor resultante</div>
                        </div>

                        <div class="mt-3 p-2 rounded-2 border border-danger border-opacity-25"
                            style="background:#fff5f5;">
                            <div class="fw-bold small mb-1 text-danger"><i class="bi bi-x-octagon me-1"></i>Si la
                                columna "Presentación" muestra "—" para un producto</div>
                            <ol class="small text-muted mb-0">
                                <li>Verifica que exista una presentación con <code>presentacion_despacho=1</code> bajo
                                    el mismo <code>producto_maestro</code> (Paso A la detecta automáticamente).</li>
                                <li>Si la cajilla es una receta sin maestro propio, verifica que alguno de sus
                                    componentes pertenezca al mismo maestro del insumo (Paso C, último recurso).</li>
                                <li>Si la unidad de despacho difiere de la unidad básica, agrega la conversión directa o
                                    transitiva en <code>conversion_unidad_producto</code> (requerido para el Paso A).
                                </li>
                                <li>Alternativa sin conversiones: configura una receta-paquete con el componente exacto
                                    (Paso B, máxima prioridad).</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Leyenda de Estados -->
                    <div class="mb-2">
                        <h6 class="fw-bold text-dark small border-bottom pb-2">Indicadores de Pedido</h6>
                        <div class="d-flex flex-column gap-2 mt-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success" style="width: 85px;">ÓPTIMO</span>
                                <span class="small text-muted">Stock actual suficiente según días de ciclo y
                                    seguridad.</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark" style="width: 85px;">PEDIR</span>
                                <span class="small text-muted">Es necesario reordenar para no caer por debajo del
                                    mínimo.</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary" style="width: 85px;">N/A</span>
                                <span class="small text-muted">Producto sin parámetros logísticos configurados.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>



    <!-- JavaScript Principal -->
    <script src="js/pedido_sugerido_v2.js?v=<?php echo $version; ?>"></script>
</body>

</html>