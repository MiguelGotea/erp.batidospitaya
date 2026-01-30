<?php
// historial_ventas.php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('historial_pedidos_globales', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Verificar si tiene permiso para ver montos
$puedeVerMontos = tienePermiso('historial_pedidos_globales', 'detalle_montos', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Ventas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/historial_ventas.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Historial de Ventas'); ?>
            
            <div class="container-fluid p-3">
                <!-- Resumen de totales -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card-totales">
                            <?php if ($puedeVerMontos): ?>
                            <div class="total-item">
                                <span class="total-label">Total Monto:</span>
                                <span class="total-value" id="totalMonto">0.0</span>
                            </div>
                            <?php endif; ?>
                            <div class="total-item">
                                <span class="total-label">Total Productos:</span>
                                <span class="total-value" id="totalProductos">0</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover historial-table" id="tablaVentas">
                        <thead>
                            <tr>
                                <th data-column="Sucursal_Nombre" data-type="list">
                                    Sucursal 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="CodPedido" data-type="text">
                                    Pedido 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Fecha" data-type="daterange">
                                    Fecha 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th>Hora</th>
                                <th data-column="CodCliente" data-type="text">
                                    Membresía 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="NombreCliente" data-type="text">
                                    Cliente 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="DBBatidos_Nombre" data-type="text">
                                    Producto 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Medida" data-type="list">
                                    Medida 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Cantidad" data-type="text">
                                    Cantidad 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Puntos" data-type="text">
                                    Puntos 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Caja" data-type="text">
                                    Cajero 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <?php if ($puedeVerMontos): ?>
                                <th data-column="Precio" data-type="text">
                                    Monto 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <?php endif; ?>
                                <th data-column="Modalidad" data-type="list">
                                    Modalidad 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                                <th data-column="Anulado" data-type="list">
                                    Anulado 
                                    <i class="bi bi-funnel filter-icon" onclick="toggleFilter(this)"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="tablaVentasBody">
                            <!-- Datos cargados vía AJAX -->
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Mostrar:</label>
                        <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;" onchange="cambiarRegistrosPorPagina()">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span class="mb-0">registros</span>
                    </div>
                    <div id="paginacion"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Pasar permiso de montos a JavaScript
        const puedeVerMontos = <?php echo $puedeVerMontos ? 'true' : 'false'; ?>;
    </script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/historial_ventas.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>