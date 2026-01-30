<?php
// historial_productos.php

require_once '../../includes/auth.php';
require_once '../../includes/menu_lateral.php';
require_once '../../includes/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('historial_pedidos_clientes_club', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtener membresía del parámetro GET
$membresia = isset($_GET['membresia']) ? $_GET['membresia'] : '';

// Si no hay membresía, redirigir a historial de clientes
if (empty($membresia)) {
    header('Location: historial_clientes.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Productos Vendidos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/historial_productos.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <div class="main-container">
        <div class="contenedor-principal">
            <?php echo renderHeader($usuario, false, 'Historial de Productos Vendidos'); ?>
            
            <div class="container-fluid p-3">
                <!-- Info del cliente -->
                <div class="mb-3">
                    <div id="infoCliente" class="info-cliente"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover historial-table" id="tablaProductos">
                        <thead>
                            <tr>
                                <th>Sucursal</th>
                                <th>Pedido</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Producto</th>
                                <th>Medida</th>
                                <th>Cantidad</th>
                                <th>Puntos Totales</th>
                                <th>Puntos Acumulados</th>
                            </tr>
                        </thead>
                        <tbody id="tablaProductosBody">
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const membresiaInicial = '<?php echo addslashes($membresia); ?>';
    </script>
    <script src="js/historial_productos.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>