<?php
/* ============================================================
   PEDIDO SUGERIDO — Cálculo de orden de compra por sucursal
   modulos/productos/pedido_sugerido.php
   ============================================================ */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pedido_sugerido', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeEditar = tienePermiso('pedido_sugerido', 'edicion', $cargoOperario);
$version     = mt_rand(1, 10000);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Sugerido · Pitaya ERP</title>
    <meta name="description" content="Calcula el pedido sugerido de insumos por sucursal en base a consumo histórico y configuración logística.">

    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="css/pedido_sugerido.css?v=<?php echo $version; ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Pedido Sugerido'); ?>

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
                                    id="filtroSemanaDesde" min="1" max="9999" placeholder="Ej: 535">
                            </div>

                            <!-- Semana Hasta -->
                            <div class="col-6 col-md-2">
                                <label class="ps-label" for="filtroSemanaHasta">
                                    <i class="fas fa-hashtag me-1"></i>Semana Hasta
                                </label>
                                <input type="number" class="form-control form-control-sm ps-input-semana"
                                    id="filtroSemanaHasta" min="1" max="9999" placeholder="Ej: 538">
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

                            <!-- Semana actial + Botón -->
                            <div class="col-12 col-md-5 d-flex flex-wrap gap-2 justify-content-end align-items-center">
                                <div id="badgeSemanaActual" class="ps-badge-semana d-none">
                                    <i class="fas fa-calendar-check"></i>
                                    Sem. actual: <strong id="semanaActualNum">—</strong>
                                </div>
                                <button class="btn btn-sm ps-btn-primary" id="btnCalcular">
                                    <i class="fas fa-calculator me-1"></i>Calcular
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
                    <p class="text-muted">Selecciona el rango de semanas y la sucursal, luego haz clic en <strong>Calcular</strong>.</p>
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
                            <div class="ps-tabla-toolbar px-3 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span id="labelResultados" class="ps-result-count"></span>
                                    <!-- Leyenda semáforo -->
                                    <div class="d-flex gap-2 align-items-center ms-2">
                                        <span class="ps-pill ps-pill-ok">No ordenar</span>
                                        <span class="ps-pill ps-pill-warn">Ordenar</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="text" class="form-control form-control-sm"
                                        id="buscarProducto" placeholder="Buscar producto…"
                                        style="max-width:200px">
                                    <?php if ($puedeEditar): ?>
                                    <button class="btn btn-sm ps-btn-save" id="btnGuardarInventario">
                                        <i class="fas fa-save me-1"></i>Guardar Inventario
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="table-responsive" id="tablaWrapper">
                                <table class="table table-hover ps-tabla mb-0" id="tablaProductos">
                                    <thead>
                                        <tr>
                                            <th class="col-producto">Producto</th>
                                            <th class="text-center">Cat.</th>
                                            <th>Unidad</th>
                                            <th class="text-end" title="Promedio de consumo semanal">Prom./Sem.</th>
                                            <th class="text-end" title="Desviación estándar de muestra">Desv. Std</th>
                                            <th class="text-end" title="Consumo semanal = promedio + desv. estándar">Cons. Semanal</th>
                                            <th class="text-end" title="Consumo diario = (cons_semanal × (1 + ajuste)) / 7">Cons. Diario</th>
                                            <th class="text-end" title="Stock mínimo = cons_diario × dias_stock_minimo">Stock Mín</th>
                                            <th class="text-end" title="Stock máximo = cons_diario × (dias_ciclo + dias_desfase + dias_stock_min)">Stock Máx</th>
                                            <th class="text-end" title="Stock máximo final (ajustado para congelados)">Stock Máx Final</th>
                                            <th class="text-center col-inventario" title="Inventario actual en tienda">Inventario Actual</th>
                                            <th class="text-center col-pedido" title="Pedido sugerido = Stock Máx Final − Inventario Actual">Pedido Sugerido</th>
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
    <script src="js/pedido_sugerido.js?v=<?php echo $version; ?>"></script>
</body>
</html>
