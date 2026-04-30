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
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="css/inventario_semanal.css?v=<?php echo $version; ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargo); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Inventario Semanal'); ?>

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
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Semana de Inventario</label>
                                <input type="number" class="form-control form-control-sm" id="filtroSemanaInv" placeholder="Ej: 538">
                            </div>
                            <div class="col-md-6 d-flex gap-2">
                                <button class="btn btn-sm btn-primary-pitaya flex-grow-1" id="btnCalcular">
                                    <i class="fas fa-calculator me-1"></i> Cargar y Calcular
                                </button>
                                <?php if ($puedeEditar): ?>
                                <button class="btn btn-sm btn-success flex-grow-1" id="btnGuardarInventario" style="display:none;">
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
                    <div class="alert alert-info py-2 px-3 small mb-2 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-info-circle me-1"></i> Mostrando inventario de la semana seleccionada.</span>
                        <span id="labelRangoFechas" class="fw-bold"></span>
                    </div>
                    <div class="table-responsive bg-white rounded shadow-sm">
                        <table class="table table-hover tabla-inventario mb-0">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:20%;">Producto</th>
                                    <th colspan="2">Inventario Semanal</th>
                                    <th colspan="3">Presentación de Envío</th>
                                    <th rowspan="2">Pedido Sugerido<br>(+B - A)</th>
                                    <th colspan="3">Pedido Sugerido en Cada Día de Despacho</th>
                                </tr>
                                <tr>
                                    <th>En Unidades</th>
                                    <th>(A) En Presentación Envío</th>
                                    <th>≡ En Pres. Despacho</th>
                                    <th>Stock Mínimo</th>
                                    <th>Stock Máximo (B)</th>
                                    <th>PEDIDO 1</th>
                                    <th>PEDIDO 2</th>
                                    <th>PEDIDO 3</th>
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
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-info-circle me-2"></i> Guía de Uso: Inventario y Pedido Sugerido</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>1. Registro de Inventario</h6>
                            <p>El objetivo es contar lo que hay físicamente en la sucursal al final de la semana.</p>
                            <ul>
                                <li><strong>En Unidades:</strong> Solo se habilita si el producto se cuenta por unidad básica.</li>
                                <li><strong>(A) En Presentación:</strong> Es el valor principal para el cálculo del pedido. Si ingresas unidades, este se calcula automáticamente dividiendo por el factor de presentación.</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>2. Lógica del Pedido Sugerido</h6>
                            <p>El sistema calcula cuánto necesitas basándose en tu historial:</p>
                            <ul>
                                <li><strong>Historial:</strong> Calcula el Promedio y la Desviación Estándar de las semanas seleccionadas.</li>
                                <li><strong>Consumo Semanal:</strong> <code>Promedio + Desviación</code>.</li>
                                <li><strong>Pedido Sugerido:</strong> <code>Stock Máximo (B) - Inventario Actual (A)</code>.</li>
                            </ul>
                        </div>
                    </div>
                    <hr>
                    <h6>3. Desglose de Despachos (Pedidos 1, 2 y 3)</h6>
                    <p>El pedido total se divide según la categoría del producto y los porcentajes configurados:</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Categorías</th>
                                    <th>Lógica de Distribución</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>B, D, F</strong></td>
                                    <td>Pedido 1 usa % Congelados. Pedido 2 usa el resto.</td>
                                </tr>
                                <tr>
                                    <td><strong>A, C</strong></td>
                                    <td>Pedido 1 usa % Frescos. Pedido 2 usa el resto.</td>
                                </tr>
                                <tr>
                                    <td><strong>E, G</strong></td>
                                    <td>100% se envía en el Pedido 1.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
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
