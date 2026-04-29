<?php
// prueba_promociones.php — Página de pruebas para el motor de promociones, de prueba
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// No estricto en permisos para esta página de prueba, pero requiere login
if (!$usuario) {
    header('Location: /login.php'); exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Promociones — Pitaya ERP</title>
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1,9999); ?>">
    <link rel="stylesheet" href="css/promociones.css?v=<?php echo mt_rand(1,9999); ?>">
    <style>
        .test-panel { background: #f8f9fa; border-radius: 8px; padding: 20px; border: 1px solid #dee2e6; }
        .cart-item { background: #fff; border: 1px solid #eee; border-radius: 6px; padding: 10px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
        .promo-applied { background: #e8f5e9; border-left: 4px solid #2e7d32; padding: 10px; margin-bottom: 10px; border-radius: 0 4px 4px 0; }
        .promo-rejected { background: #ffebee; border-left: 4px solid #c62828; padding: 10px; margin-bottom: 10px; border-radius: 0 4px 4px 0; opacity: 0.7; }
        .price-old { text-decoration: line-through; color: #999; font-size: 0.9em; }
        .price-new { color: #2e7d32; font-weight: bold; }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, '🧪 Demo Aplicación de Promociones'); ?>

            <div class="container-fluid p-3">
                <div class="row g-4">
                    
                    <!-- COLUMNA IZQUIERDA: CONFIGURACIÓN -->
                    <div class="col-lg-4">
                        <div class="test-panel mb-4">
                            <h5 class="mb-3"><i class="bi bi-gear-fill"></i> 1. Contexto de Simulación</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold">Sucursal</label>
                                    <select id="simSucursal" class="form-select form-select-sm">
                                        <option value="">Cargando sucursales...</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Día</label>
                                    <select id="simDia" class="form-select form-select-sm">
                                        <?php
                                        $dias = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
                                        $hoy = date('N'); // 1-7
                                        foreach($dias as $i => $d) {
                                            $sel = ($i+1 == $hoy) ? 'selected' : '';
                                            echo "<option value='$d' $sel>$d</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Hora</label>
                                    <input type="time" id="simHora" class="form-control form-control-sm" value="<?php echo date('H:i'); ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Canal</label>
                                    <select id="simCanal" class="form-select form-select-sm">
                                        <option value="general" selected>General</option>
                                        <option value="página web">Página Web</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Tipo Cliente</label>
                                    <select id="simTipoCliente" class="form-select form-select-sm">
                                        <option value="General" selected>General</option>
                                        <option value="Club">Club</option>
                                        <option value="Colaborador">Colaborador</option>
                                        <option value="Empresa afiliada">Empresa afiliada</option>
                                        <option value="Nuevo: delivery propio">Nuevo: delivery propio</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="test-panel">
                            <h5 class="mb-3"><i class="bi bi-cart-fill"></i> 2. Carrito Mock</h5>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Agregar Producto</label>
                                <select id="searchProducto" class="form-select" style="width:100%"></select>
                            </div>
                            <div id="cartList" class="mb-3">
                                <div class="text-center text-muted py-3">El carrito está vacío</div>
                            </div>
                            <button class="btn btn-success w-100" onclick="procesarPromociones()">
                                <i class="bi bi-lightning-charge-fill"></i> Agregar
                            </button>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: RESULTADOS -->
                    <div class="col-lg-8">
                        <div class="test-panel h-100">
                            <h5 class="mb-3"><i class="bi bi-journal-text"></i> Resultados de Evaluación</h5>
                            
                            <div id="resultLoading" class="text-center py-5 d-none">
                                <div class="spinner-border text-success" role="status"></div>
                                <p class="mt-2 text-muted">Evaluando reglas del motor...</p>
                            </div>

                            <div id="resultEmpty" class="text-center py-5 text-muted">
                                <i class="bi bi-arrow-left-circle fs-1 opacity-25"></i>
                                <p>Configura el carrito y presiona "Calcular"</p>
                            </div>

                            <div id="resultContent" class="d-none">
                                <div class="card mb-4">
                                    <div class="card-header bg-white fw-bold">Resumen de Factura</div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Producto</th>
                                                    <th class="text-center">Cant.</th>
                                                    <th class="text-end">Base</th>
                                                    <th class="text-end">Desc.</th>
                                                    <th class="text-end">Final</th>
                                                </tr>
                                            </thead>
                                            <tbody id="resTableBody"></tbody>
                                            <tfoot>
                                                <tr class="table-light">
                                                    <td colspan="4" class="text-end fw-bold">Total Descuento:</td>
                                                    <td class="text-end fw-bold text-success" id="resTotalDesc">C$0.00</td>
                                                </tr>
                                                <tr class="table-dark">
                                                    <td colspan="4" class="text-end fw-bold">Total a Pagar:</td>
                                                    <td class="text-end fw-bold" id="resTotalFinal">C$0.00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>

                                <div id="appliedPromos" class="mb-4"></div>

                                <h6><i class="bi bi-check-circle text-primary"></i> 3. Promociones Listas para Aprobar</h6>
                                <p class="small text-muted">Se cumplen las condiciones. Presiona "Aprobar" para aplicar el descuento.</p>
                                <div id="suggestedPromos" class="mb-4"></div>

                                <h6><i class="bi bi-info-circle text-warning"></i> 4. Promociones Potenciales</h6>
                                <p class="small text-muted">Se cumple al menos una condición. Completa los requisitos para activarla.</p>
                                <div id="potentialPromos" class="mb-4"></div>

                                <h6><i class="bi bi-x-circle text-danger"></i> 5. Promociones que NO Califican</h6>
                                <div id="rejectedPromos"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/prueba_promociones.js?v=<?php echo mt_rand(1,9999); ?>"></script>
</body>
</html>
