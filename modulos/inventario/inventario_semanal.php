<?php
/* ============================================================
   PÁGINA: Inventario Semanal y Pedido Sugerido
   Ruta: modulos/inventario/inventario_semanal.php
   ============================================================ */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];

// Permisos: 27, 16, 49, 55 para vista
if (!tienePermiso('inventario_semanal', 'vista', $cargo)) {
    header('Location: /index.php');
    exit();
}

$puedeEditar = tienePermiso('inventario_semanal', 'edicion', $cargo);
$version = mt_rand(1, 10000);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario Semanal · Pitaya ERP</title>
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="css/inventario_semanal.css?v=<?php echo $version; ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargo); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Inventario Semanal'); ?>

            <div class="p-3">
                <!-- Filtros -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Sucursal</label>
                                <select class="form-select form-select-sm" id="filtroSucursal">
                                    <option value="">-- Seleccione Sucursal --</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold">Semana de Inventario</label>
                                <input type="number" class="form-control form-control-sm" id="filtroSemanaInv"
                                    placeholder="Ej: 538">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-bold d-flex align-items-center gap-1">
                                    <i class="bi bi-scissors text-warning" style="font-size:.8rem"></i>
                                    Corte Para Pronóstico
                                </label>
                                <input type="number" class="form-control form-control-sm"
                                    id="filtroSemanaCortePronostico" placeholder="Auto"
                                    title="Semana cuyo inventario físico se usa como punto de partida del pronóstico. Por defecto: semana anterior.">
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button class="btn btn-sm btn-primary-pitaya flex-grow-1" id="btnCalcular">
                                    <i class="fas fa-calculator me-1"></i> Cargar y Calcular
                                </button>
                                <?php if ($puedeEditar): ?>
                                    <button class="btn btn-sm btn-success flex-grow-1" id="btnGuardarInventario"
                                        style="display:none;">
                                        <i class="fas fa-save me-1"></i> Guardar Inventario
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Loader -->
                <div id="loader">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-2">Cargando datos y calculando consumos...</p>
                </div>

                <!-- Tabla -->
                <div id="tablaInventarioContainer" style="display:none;">
                    <div
                        class="alert alert-info py-2 px-3 small mb-2 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-info-circle me-1"></i> Mostrando inventario de la semana
                            seleccionada.</span>
                        <span id="labelRangoFechas" class="fw-bold"></span>
                    </div>
                    <div class="table-responsive bg-white rounded shadow-sm">
                        <table class="table table-hover tabla-inventario mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:18%;">Producto</th>
                                    <th colspan="4" class="th-inventario">INVENTARIO SEMANAL</th>
                                    <th class="th-pronostico" rowspan="2"
                                        title="Stock estimado al cierre de la semana de inventario, partiendo del inventario físico de la semana de corte">
                                        <i class="bi bi-graph-up-arrow me-1"></i>Stock Final Pronóstico
                                        <span id="labelCortePronostico" class="d-block fw-normal"
                                            style="font-size:.65rem;opacity:.8"></span>
                                    </th>
                                    <th class="th-inventario">Stock Mínimo</th>
                                    <th class="th-inventario">Stock Máximo (B)</th>
                                    <th class="th-pedido-sugerido">PEDIDO SUGERIDO (+B - A)</th>
                                    <th colspan="3" class="th-pedido-dia">PEDIDO SUGERIDO EN CADA DÍA DE DESPACHO</th>
                                </tr>
                                <tr>
                                    <th colspan="2" class="th-inventario">En Unidades</th>
                                    <th colspan="2" class="th-inventario">(A) En Presentación Envío</th>
                                    <th class="th-inventario">Presentación de Envío</th>
                                    <th class="th-inventario">Presentación de Envío</th>
                                    <th class="th-pedido-sugerido">Presentación de Envío</th>
                                    <th class="th-pedido-dia">PEDIDO 1 (Si es quincenal solo 1 pedido)</th>
                                    <th class="th-pedido-dia">PEDIDO 2</th>
                                    <th class="th-pedido-dia">PEDIDO 3</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyInventario">
                                <!-- Se carga por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Información -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow-lg" style="border-radius:15px;">
                <div class="modal-header text-white"
                    style="background:var(--pitaya-dark,#2d3748);border-radius:15px 15px 0 0;">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle-fill"></i> Guía de Uso: Inventario Semanal y Pedido Sugerido
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">

                    <!-- Registro de inventario y lógica del pedido -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <h6 class="fw-bold small mb-2"><i class="bi bi-clipboard-check me-1"></i>1. Registro de
                                    Inventario</h6>
                                <ul class="small text-muted mb-0">
                                    <li class="mb-1"><b>En Unidades:</b> Conteo físico en unidad básica de control
                                        (gramos, oz, unidades).</li>
                                    <li class="mb-1"><b>(A) En Presentación Envío:</b> Valor principal para el pedido.
                                        Si ingresas unidades, se calcula automáticamente ÷ factor de despacho.</li>
                                    <li class="mb-1">Al guardar, el sistema registra ambos valores en la BD para
                                        auditoría.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <h6 class="fw-bold small mb-2"><i class="bi bi-calculator me-1"></i>2. Lógica del Pedido
                                    Sugerido</h6>
                                <ul class="small text-muted mb-0">
                                    <li class="mb-1"><b>Cons. Semanal:</b> <code>Promedio + Desviación Estándar</code>
                                        sobre la Ventana Activa.</li>
                                    <li class="mb-1"><b>Stock Máximo (B):</b>
                                        <code>Consumo Diario × (Ciclo + Desfase + Stock Mín)</code> ÷ Factor Despacho.
                                    </li>
                                    <li class="mb-1"><b>Pedido Sugerido:</b> <code>B − A</code> (en unidades de
                                        despacho).</li>
                                    <li class="mb-1">Los ceros al inicio/fin del periodo se excluyen automáticamente
                                        (Ventana Activa).</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Presentación de Despacho -->
                    <div class="mb-4 p-3 rounded-3 border" style="background:#f0fdf4;border-color:#86efac !important;">
                        <h6 class="fw-bold small d-flex align-items-center gap-2 mb-3" style="color:#15803d;">
                            <i class="bi bi-truck"></i> Presentación de Despacho y Rastreo de Conversiones
                        </h6>
                        <p class="text-secondary small mb-2">
                            Stocks y pedido se expresan en <b>unidades de despacho</b> (Cajilla, Bolsa, Kg…). El sistema
                            las resuelve en <b>tres pasos</b> en orden de prioridad:
                        </p>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <div class="p-2 rounded-2 border h-100" style="background:#dcfce7;">
                                    <div class="fw-bold small mb-1" style="color:#166534;"><i
                                            class="bi bi-1-circle-fill me-1"></i>Paso B — Receta-Paquete Exacta <span
                                            class="badge bg-success ms-1" style="font-size:9px;">Prioridad</span></div>
                                    <p class="small text-muted mb-0">Presentación con
                                        <code>presentacion_despacho=1</code> y receta con <b>exactamente 1
                                            componente</b> = la unidad básica. El factor es la cantidad en esa receta
                                        (ej: 100 × Banano unid → factor 100). No requiere conversión de unidades.
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-2 rounded-2 border h-100" style="background:#f0fdf4;">
                                    <div class="fw-bold small mb-1" style="color:#166534;"><i
                                            class="bi bi-2-circle-fill me-1"></i>Paso A — Por Producto Maestro <span
                                            class="badge bg-secondary ms-1" style="font-size:9px;">Fallback</span></div>
                                    <p class="small text-muted mb-0">Si no hay receta-paquete exacta, busca otra
                                        presentación del mismo <code>producto_maestro</code> con
                                        <code>presentacion_despacho=1</code>. Factor =
                                        <code>cantidad_despacho / (cantidad_básica × conversión_unidades)</code>. Ej:
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
                                        despacho cuyo componente comparta el mismo maestro. Ej: <b>Naranja oz → Cajilla
                                            100u</b> (componente = "Naranja Unidad", mismo maestro; la cajilla no tiene
                                        maestro propio por ser receta).</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-2 rounded-2 mb-2 border"
                            style="background:#fff7ed;border-color:#fdba74 !important;">
                            <div class="fw-bold small mb-1" style="color:#9a3412;"><i
                                    class="bi bi-bezier2 me-1"></i>Conversiones Transitivas — Floyd-Warshall</div>
                            <p class="small text-muted mb-0">El sistema resuelve cadenas multi-salto (oz → gr → kg)
                                automáticamente. No es necesario tener la fila directa <em>oz → kg</em>; basta con las
                                conversiones intermedias y el algoritmo las encadena en memoria.</p>
                        </div>
                        <div class="p-2 rounded-2 mb-2 border"
                            style="background:#fefce8;border-color:#fde68a !important;">
                            <div class="fw-bold small mb-1" style="color:#854d0e;"><i
                                    class="bi bi-exclamation-triangle-fill me-1"></i>Insumos con Rendimiento Variable
                                (ej: Naranja)</div>
                            <p class="small text-muted mb-0">Fijar un <b>yield factor conservador</b> (ej: 1 naranja =
                                2.0 oz, el mínimo del rango real). La variabilidad queda absorbida por la desviación
                                estándar del consumo. Agregar la conversión en <code>conversion_unidad_producto</code>.
                            </p>
                        </div>
                        <div class="p-2 rounded-2 border border-danger border-opacity-25" style="background:#fff5f5;">
                            <div class="fw-bold small mb-1 text-danger"><i class="bi bi-x-octagon me-1"></i>Si la
                                presentación de despacho no aparece para un producto</div>
                            <ol class="small text-muted mb-0">
                                <li>Verificar que exista una presentación con <code>presentacion_despacho=1</code> bajo
                                    el mismo producto maestro.</li>
                                <li>Si la cajilla/paquete es una receta, verificar que algún componente pertenezca al
                                    mismo <code>id_producto_maestro</code> (el Paso C lo detecta automáticamente).</li>
                                <li>Si las unidades son distintas (ej: Unidad ≠ Cajilla), agregar la conversión directa
                                    o transitiva en <code>conversion_unidad_producto</code>.</li>
                                <li>Alternativa sin conversiones: configurar una receta-paquete (Paso B).</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Desglose de despachos -->
                    <div>
                        <h6 class="fw-bold text-dark small border-bottom pb-2">3. Desglose de Despachos (Pedidos 1, 2 y
                            3)</h6>
                        <p class="small text-muted">El pedido total se divide según la categoría y los porcentajes
                            configurados por sucursal:</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Categorías</th>
                                        <th>Lógica</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><b>B, D, F</b></td>
                                        <td>Pedido 1 = % Congelados. Pedido 2 = el resto.</td>
                                    </tr>
                                    <tr>
                                        <td><b>A, C</b></td>
                                        <td>Pedido 1 = % Frescos. Pedido 2 = el resto.</td>
                                    </tr>
                                    <tr>
                                        <td><b>E, G</b></td>
                                        <td>100% en Pedido 1.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/inventario_semanal.js?v=<?php echo $version; ?>"></script>
</body>

</html>