<?php
// balance_cierre_diario.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('balance_cierre_diario', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Parámetros recibidos desde el Historial de Cierres
$paramFecha = isset($_GET['fecha']) ? htmlspecialchars($_GET['fecha']) : date('Y-m-d');
$paramSucursal = isset($_GET['sucursal']) ? htmlspecialchars($_GET['sucursal']) : '';
$paramCierre = isset($_GET['cierre']) ? (int) $_GET['cierre'] : 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Cierre Diario - Batidos Pitaya</title>
    <meta name="description" content="Informe de balance de cierre de caja diario por sucursal">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/balance_cierre_diario.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Balance Cierre Diario'); ?>

            <div class="container-fluid p-3">

                <!-- Inputs ocultos: cargados desde parámetros GET del Historial -->
                <input type="hidden" id="filtroFecha" value="<?php echo $paramFecha; ?>">
                <input type="hidden" id="filtroSucursal" value="<?php echo $paramSucursal; ?>">

                <!-- Layout principal: menú lateral + detalle -->
                <div class="bcd-layout" id="bcdLayout" style="display:none;">

                    <!-- Sidebar de cierres del día -->
                    <div class="bcd-sidebar" id="bcdSidebar">
                        <div class="bcd-sidebar-header">
                            <i class="bi bi-clock-history me-2"></i>Cierres del Día
                            <span class="bcd-sidebar-count" id="sidebarCount">0</span>
                        </div>
                        <div class="bcd-sidebar-list" id="listaCierres">
                            <!-- Ítems generados por JS -->
                        </div>
                    </div>

                    <!-- Panel de detalle del cierre -->
                    <div class="bcd-detail" id="bcdDetail">
                        <div class="bcd-detail-placeholder" id="placeholderDetalle">
                            <i class="bi bi-arrow-left-circle"></i>
                            <p>Seleccioná un cierre del panel lateral para ver el balance</p>
                        </div>

                        <div class="bcd-detail-content" id="contenidoDetalle" style="display:none;">

                            <!-- Encabezado del cierre -->
                            <div class="bcd-report-header">
                                <div class="bcd-report-title">
                                    <i class="bi bi-cash-stack me-2"></i>
                                    <span>BALANCE CIERRE DIARIO</span>
                                </div>
                                <div class="bcd-report-meta">
                                    <div class="bcd-meta-item">
                                        <span class="bcd-meta-label">Código de Cierre</span>
                                        <span class="bcd-meta-value" id="detCodigoCierre">—</span>
                                    </div>
                                    <div class="bcd-meta-item">
                                        <span class="bcd-meta-label">Cajero</span>
                                        <span class="bcd-meta-value" id="detCajero">—</span>
                                    </div>
                                    <div class="bcd-meta-item">
                                        <span class="bcd-meta-label">Fecha</span>
                                        <span class="bcd-meta-value" id="detFecha">—</span>
                                    </div>
                                    <div class="bcd-meta-item">
                                        <span class="bcd-meta-label">Turno</span>
                                        <span class="bcd-meta-value" id="detTurno">—</span>
                                    </div>
                                </div>
                            </div>

                            <!-- BALANCE DE VENTAS -->
                            <div class="bcd-section-title">
                                <i class="bi bi-bar-chart-line me-2"></i>Balance de Ventas
                            </div>

                            <div class="bcd-table-wrapper">
                                <table class="bcd-table" id="tablaVentas">
                                    <thead>
                                        <tr>
                                            <th>Forma de Pago</th>
                                            <th class="text-end">Boleta Físico</th>
                                            <th class="text-end">Sistema Pitaya</th>
                                            <th class="text-center">Diferencia</th>
                                            <th class="text-center">Detalle</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><i class="bi bi-credit-card me-2 text-muted"></i>POS Banco</td>
                                            <td class="text-end fw-semibold" id="vfPosFisico">—</td>
                                            <td class="text-end fw-semibold" id="vfPosSistema">—</td>
                                            <td class="text-center" id="vfPosDif">—</td>
                                            <td class="text-center">
                                                <button class="bcd-btn-detail" onclick="abrirDetalleVentas('POS')"
                                                    title="Ver detalle POS">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><i class="bi bi-arrow-left-right me-2 text-muted"></i>Transferencias
                                            </td>
                                            <td class="text-end fw-semibold" id="vfTransFisico">—</td>
                                            <td class="text-end fw-semibold" id="vfTransSistema">—</td>
                                            <td class="text-center" id="vfTransDif">—</td>
                                            <td class="text-center">
                                                <button class="bcd-btn-detail"
                                                    onclick="abrirDetalleVentas('TRANSFERENCIA')"
                                                    title="Ver detalle Transferencias">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><i class="bi bi-scooter me-2 text-muted"></i>Pedidos Ya</td>
                                            <td class="text-end fw-semibold" id="vfPYFisico">—</td>
                                            <td class="text-end fw-semibold" id="vfPYSistema">—</td>
                                            <td class="text-center" id="vfPYDif">—</td>
                                            <td class="text-center">
                                                <button class="bcd-btn-detail" onclick="abrirDetalleVentas('PEDIDOSYA')"
                                                    title="Ver detalle Pedidos Ya">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><i class="bi bi-cash me-2 text-muted"></i>Efectivo</td>
                                            <td class="text-end fw-semibold" id="vfEfecFisico">—</td>
                                            <td class="text-end fw-semibold" id="vfEfecSistema">—</td>
                                            <td class="text-center" id="vfEfecDif">—</td>
                                            <td class="text-center">
                                                <button class="bcd-btn-detail" onclick="abrirDetalleVentas('EFECTIVO')"
                                                    title="Ver detalle Efectivo">
                                                    <i class="bi bi-search"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="bcd-row-total">
                                            <td><strong>TOTAL VENTAS</strong></td>
                                            <td class="text-end" id="vfTotalFisico">—</td>
                                            <td class="text-end" id="vfTotalSistema">—</td>
                                            <td class="text-center" id="vfTotalDif">—</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- BALANCE DE EFECTIVO -->
                            <div class="bcd-section-title mt-4">
                                <i class="bi bi-wallet2 me-2"></i>Balance de Efectivo
                            </div>

                            <div class="bcd-efectivo-grid">
                                <!-- A Entregar -->
                                <div class="bcd-efectivo-block">
                                    <div class="bcd-efectivo-block-title">A Entregar</div>
                                    <div class="bcd-efectivo-row">
                                        <span class="bcd-efectivo-label">
                                            <span class="bcd-sign bcd-sign-plus">+</span>
                                            Caja Inicial
                                        </span>
                                        <span class="bcd-efectivo-value" id="efCajaInicial">—</span>
                                    </div>
                                    <div class="bcd-efectivo-row">
                                        <span class="bcd-efectivo-label">
                                            <span class="bcd-sign bcd-sign-plus">+</span>
                                            Ventas Efectivo
                                            <button class="bcd-btn-detail ms-1" onclick="abrirDetalleVentas('EFECTIVO')"
                                                title="Ver detalle ventas efectivo">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </span>
                                        <span class="bcd-efectivo-value" id="efVentasEfectivo">—</span>
                                    </div>
                                    <div class="bcd-efectivo-row">
                                        <span class="bcd-efectivo-label">
                                            <span class="bcd-sign bcd-sign-minus">−</span>
                                            Aligeramientos
                                        </span>
                                        <span class="bcd-efectivo-value" id="efAligeramientos">—</span>
                                    </div>
                                    <div class="bcd-efectivo-row">
                                        <span class="bcd-efectivo-label">
                                            <span class="bcd-sign bcd-sign-minus">−</span>
                                            Compras de Caja
                                            <button class="bcd-btn-detail ms-1" onclick="abrirDetalleCompras()"
                                                title="Ver detalle compras">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </span>
                                        <span class="bcd-efectivo-value" id="efCompras">—</span>
                                    </div>
                                    <div class="bcd-efectivo-row bcd-efectivo-subtotal">
                                        <span class="bcd-efectivo-label"><strong>EFECTIVO A ENTREGAR</strong></span>
                                        <span class="bcd-efectivo-value" id="efAEntregar">—</span>
                                    </div>
                                </div>

                                <!-- Entregado -->
                                <div class="bcd-efectivo-block">
                                    <div class="bcd-efectivo-block-title">Entregado</div>
                                    <div class="bcd-efectivo-row">
                                        <span class="bcd-efectivo-label">
                                            <span class="bcd-sign bcd-sign-plus">+</span>
                                            Conteo de Caja
                                        </span>
                                        <span class="bcd-efectivo-value" id="efConteoCaja">—</span>
                                    </div>
                                    <div class="bcd-efectivo-row bcd-efectivo-subtotal">
                                        <span class="bcd-efectivo-label"><strong>TOTAL ENTREGADO</strong></span>
                                        <span class="bcd-efectivo-value" id="efTotalEntregado">—</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Resultado final efectivo -->
                            <div class="bcd-result-box" id="bcdResultBox">
                                <div class="bcd-result-label" id="bcdResultLabel">EFECTIVO SOBRANTE</div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bcd-result-value" id="bcdResultValue">—</div>
                                    <div id="bcdFaltanteSync" style="display: none;"></div>
                                </div>
                            </div>


                            <!-- Observaciones -->
                            <div class="bcd-obs-block" id="bcdObsBlock" style="display:none;">
                                <i class="bi bi-chat-square-text me-2"></i>
                                <span id="bcdObsText"></span>
                            </div>

                        </div><!-- /bcd-detail-content -->
                    </div><!-- /bcd-detail -->

                </div><!-- /bcd-layout -->

                <!-- Estado vacío inicial -->
                <div class="bcd-empty-state" id="bcdEmptyState">
                    <i class="bi bi-journal-check"></i>
                    <h5>Balance Cierre Diario</h5>
                    <p>Seleccioná una fecha y sucursal, luego presioná <strong>Buscar</strong> para cargar los cierres
                        del día.</p>
                </div>

                <!-- Estado sin resultados -->
                <div class="bcd-no-results" id="bcdNoResults" style="display:none;">
                    <i class="bi bi-inbox"></i>
                    <h5>Sin cierres registrados</h5>
                    <p>No se encontraron cierres para la fecha y sucursal seleccionada.</p>
                </div>

            </div>
        </div>
    </div>

    <!-- ============================================================
         MODAL: Detalle de Ventas por Modalidad
    ============================================================ -->
    <div class="modal fade" id="modalDetalleVentas" tabindex="-1" aria-labelledby="modalDetalleVentasLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">
                <div class="modal-header border-0 py-3 px-4" style="background:#0E544C; color:#fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                            style="width:40px; height:40px;">
                            <i class="bi bi-search fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="modalDetalleVentasLabel">Detalle de Ventas</h5>
                            <p class="small mb-0 opacity-75" id="modalDetalleVentasSubtitle">Transacciones por modalidad
                            </p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 border-bottom bg-light">
                        <div class="row g-2">
                            <div class="col">
                                <div class="bcd-modal-stat">
                                    <span class="bcd-modal-stat-label">Total Transacciones</span>
                                    <span class="bcd-modal-stat-value" id="modalTotalTx">0</span>
                                </div>
                            </div>
                            <div class="col">
                                <div class="bcd-modal-stat">
                                    <span class="bcd-modal-stat-label">Monto Total</span>
                                    <span class="bcd-modal-stat-value text-pitaya" id="modalTotalMonto">C$ 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="tablaDetalleVentas">
                            <thead class="table-dark">
                                <tr>
                                    <th>Hora</th>
                                    <th>Cod. Pedido</th>
                                    <th>Producto</th>
                                    <th>Grupo</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-center">Anulado</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDetalleVentas">
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Cargando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         MODAL: Detalle de Compras de Caja
    ============================================================ -->
    <div class="modal fade" id="modalDetalleCompras" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">
                <div class="modal-header border-0 py-3 px-4" style="background:#0E544C; color:#fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                            style="width:40px; height:40px;">
                            <i class="bi bi-cart-check fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Detalle de Compras de Caja</h5>
                            <p class="small mb-0 opacity-75" id="modalComprasSubtitle">Compras registradas en caja</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="p-3 border-bottom bg-light">
                        <div class="row g-2">
                            <div class="col">
                                <div class="bcd-modal-stat">
                                    <span class="bcd-modal-stat-label">Total Compras</span>
                                    <span class="bcd-modal-stat-value" id="modalTotalCompras">0</span>
                                </div>
                            </div>
                            <div class="col">
                                <div class="bcd-modal-stat">
                                    <span class="bcd-modal-stat-label">Costo Total</span>
                                    <span class="bcd-modal-stat-value text-pitaya" id="modalTotalCostoCompras">C$
                                        0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nº Factura</th>
                                    <th>Proveedor</th>
                                    <th>Destino</th>
                                    <th>Cantidad</th>
                                    <th class="text-end">Costo Total</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyDetalleCompras">
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Cargando...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/balance_cierre_diario.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>