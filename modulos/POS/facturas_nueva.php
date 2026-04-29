<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$hoy           = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Factura — POS</title>
    <meta name="description" content="Crear nuevo registro de factura de compra o abastecimiento de tienda">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../core/assets/css/global_tools.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/facturas.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Nueva Factura'); ?>

            <div class="container-fluid p-3">
                <div class="facturas-layout">

                    <!-- ====== COLUMNA IZQUIERDA ====== -->
                    <div class="d-flex flex-column gap-3">

                        <!-- Cabecera -->
                        <div class="card-factura">
                            <div class="card-header-factura">
                                <i class="bi bi-receipt-cutoff"></i>
                                Datos de la Factura
                            </div>
                            <div class="card-body-factura">
                                <form id="formFactura" class="form-cabecera">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="numeroFactura" class="form-label">N° Factura *</label>
                                            <input type="text" class="form-control" id="numeroFactura"
                                                   name="numero_factura" value="AUTO"
                                                   readonly style="background-color: #f8fafc; font-weight: bold; color: #0E544C;">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="fechaFactura" class="form-label">Fecha *</label>
                                            <input type="date" class="form-control" id="fechaFactura"
                                                   name="fecha" value="<?php echo $hoy; ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="proveedorSearch" class="form-label">Proveedor *</label>
                                            <div class="autocomplete-wrapper">
                                                <input type="text" class="form-control" id="proveedorSearch"
                                                       placeholder="Escriba para buscar proveedor..." 
                                                       autocomplete="off" required>
                                                <input type="hidden" id="proveedorFactura" name="id_proveedor" required>
                                                <div id="proveedorSuggestions" class="autocomplete-list"></div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label for="notasFactura" class="form-label">Notas (opcional)</label>
                                            <textarea class="form-control" id="notasFactura" name="notas"
                                                      rows="2" placeholder="Observaciones sobre esta factura…"></textarea>
                                        </div>
                                    </div>

                                    <!-- Botones -->
                                    <div class="d-flex gap-2 mt-3">
                                        <button type="submit" class="btn-guardar-factura" id="btnGuardar">
                                            <i class="bi bi-save2-fill"></i> Guardar Factura
                                        </button>
                                        <a href="facturas_historial.php" class="btn-cancelar-factura">
                                            <i class="bi bi-x-circle"></i> Cancelar
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Tabla de productos agregados -->
                        <div class="card-factura">
                            <div class="card-header-factura">
                                <i class="bi bi-list-ul"></i>
                                Productos en la Factura
                                <span id="contadorProductos" class="ms-auto badge"
                                      style="background:rgba(255,255,255,.2); font-size:.78rem;">0 ítem(s)</span>
                            </div>
                            <div class="card-body-factura p-0">
                                <div class="table-responsive">
                                    <table class="tabla-detalle-factura">
                                        <thead>
                                            <tr>
                                                <th style="width:35%;">Producto / Servicio</th>
                                                <th style="width:16%;" class="text-end">Cantidad</th>
                                                <th style="width:22%;" class="text-end">Costo Total c/IVA</th>
                                                <th style="width:20%;" class="text-end">Costo Unitario</th>
                                                <th style="width:7%;" class="text-center">Quitar</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tablaDetalleBody">
                                            <tr id="filaVacia">
                                                <td colspan="5" class="empty-detalle">
                                                    <i class="bi bi-box-seam me-2"></i>
                                                    Selecciona productos desde el panel derecho
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Total bar -->
                                <div class="total-factura-bar">
                                    <span class="total-factura-label">Total Factura:</span>
                                    <span class="total-factura-monto" id="totalFacturaMonto">C$ 0.00</span>
                                </div>
                            </div>
                        </div>

                    </div><!-- /columna izquierda -->

                    <!-- ====== COLUMNA DERECHA: productos elegibles ====== -->
                    <div class="panel-productos-elegibles">
                        <div class="card-factura">
                            <div class="card-header-factura">
                                <i class="bi bi-grid"></i>
                                Productos Disponibles
                            </div>
                            <div class="card-body-factura">
                                <div class="buscador-productos">
                                    <i class="bi bi-search icon-search"></i>
                                    <input type="text" id="buscarProducto"
                                           placeholder="Buscar producto…"
                                           autocomplete="off">
                                </div>
                                <div class="lista-productos-scroll" id="listaProductosElegibles">
                                    <p class="no-productos-msg">
                                        <span class="spinner-border spinner-border-sm me-1"></span>
                                        Cargando productos…
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div><!-- /columna derecha -->

                </div><!-- /facturas-layout -->
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/facturas_nueva.js?v=<?php echo time(); ?>"></script>
</body>
</html>
