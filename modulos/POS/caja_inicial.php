<?php
/**
 * caja_inicial.php — Conteo de Caja Inicial
 * Módulo POS / modulos/POS/caja_inicial.php
 */
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/database/conexion.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$hoy           = date('Y-m-d');

// Obtener la sucursal activa según la lógica de asignación (el más reciente sin fecha de fin o con fecha futura)
$sucursalId   = null;
$sucursalNombre = 'Sin Sucursal';

try {
    $stmtS = $conn->prepare("
        SELECT s.codigo, s.nombre 
        FROM sucursales s
        JOIN AsignacionNivelesCargos anc ON s.codigo = anc.Sucursal
        WHERE anc.CodOperario = ? 
        AND (anc.Fin IS NULL OR anc.Fin > NOW())
        ORDER BY anc.Fecha DESC 
        LIMIT 1
    ");
    $stmtS->execute([$_SESSION['usuario_id']]);
    $sRow = $stmtS->fetch();
    if ($sRow) {
        $sucursalId     = $sRow['codigo'];
        $sucursalNombre = $sRow['nombre'];
    }
} catch (Exception $e) {
    // Error en la consulta, se mantiene el valor por defecto
}

// Obtener último tipo de cambio
$tipoCambio = 36.6; // valor por defecto
try {
    $tcRow = $conn->query("SELECT tasa FROM tipo_cambio ORDER BY id DESC LIMIT 1")->fetch();
    if ($tcRow && isset($tcRow['tasa'])) {
        $tipoCambio = (float)$tcRow['tasa'];
    }
} catch (Exception $e) {
    // Usamos el valor por defecto
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja Inicial — POS</title>
    <meta name="description" content="Conteo de caja inicial: denominaciones en córdobas y dólares con cálculo automático de totales">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../core/assets/css/global_tools.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/caja_inicial.css?v=<?php echo time(); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Caja Inicial'); ?>

            <div class="container-fluid p-3">

                <!-- ====== CABECERA: Fecha + Tipo de Cambio ====== -->
                <form id="formCajaInicial">

                    <div class="ci-header-card">
                        <div class="ci-header-field">
                            <label for="inputFecha"><i class="bi bi-calendar3 me-1"></i> Fecha del Conteo</label>
                            <input type="date"
                                   class="form-control"
                                   id="inputFecha"
                                   name="fecha"
                                   value="<?php echo $hoy; ?>"
                                   readonly
                                   style="background-color: #f8fafc; cursor: default;">
                        </div>

                        <div class="ci-header-field">
                            <label><i class="bi bi-currency-exchange me-1"></i> Tipo de Cambio Actual</label>
                            <div class="ci-tc-badge">
                                <i class="bi bi-arrow-left-right"></i>
                                C$ <span id="tcDisplay"><?php echo number_format($tipoCambio, 2); ?></span>
                                / USD
                            </div>
                        </div>

                        <div class="ci-header-field">
                            <label><i class="bi bi-shop me-1"></i> Sucursal</label>
                            <div class="ci-tc-badge" style="background:#fef9c3; border-color:#fef08a; color:#854d0e;">
                                <i class="bi bi-geo-alt-fill"></i>
                                <span id="sucursalDisplay"><?php echo $sucursalNombre; ?></span>
                            </div>
                            <input type="hidden" id="inputSucursalId" name="sucursal_id" value="<?php echo $sucursalId; ?>">
                        </div>

                        <div class="ci-header-field ms-auto">
                            <label>&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="ci-btn-guardar" id="btnGuardar">
                                    <i class="bi bi-save2-fill"></i> Guardar Caja Inicial
                                </button>
                                <button type="button" class="ci-btn-limpiar" id="btnLimpiar">
                                    <i class="bi bi-arrow-counterclockwise"></i> Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Campos ocultos para totales calculados en JS -->
                    <input type="hidden" id="hidTotalNIO" value="0">
                    <input type="hidden" id="hidTotalUSD" value="0">
                    <input type="hidden" id="hidTotalUSDenNIO" value="0">
                    <input type="hidden" id="hidTotalGlobal" value="0">

                    <!-- ====== TABLAS LADO A LADO ====== -->
                    <div class="caja-inicial-layout">

                        <!-- ====== CÓRDOBAS ====== -->
                        <div class="ci-card">
                            <div class="ci-card-header">
                                <i class="bi bi-cash-coin"></i>
                                Córdobas (NIO)
                            </div>
                            <div class="table-responsive">
                                <table class="ci-table">
                                    <thead>
                                        <tr>
                                            <th style="width:40%">Moneda</th>
                                            <th class="text-center" style="width:30%">Cantidad</th>
                                            <th class="text-end"   style="width:30%">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaNIOBody">
                                        <!-- Filas generadas por JS -->
                                    </tbody>
                                    <tfoot>
                                        <tr class="ci-subtotal-row">
                                            <td colspan="2" class="subtotal-label">
                                                <i class="bi bi-sigma me-1"></i>
                                                Total Córdobas
                                            </td>
                                            <td class="subtotal-value" id="subtotalNIO">C$ 0.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- ====== DÓLARES ====== -->
                        <div class="ci-card">
                            <div class="ci-card-header usd">
                                <i class="bi bi-currency-dollar"></i>
                                Dólares (USD)
                            </div>
                            <div class="table-responsive">
                                <table class="ci-table">
                                    <thead>
                                        <tr>
                                            <th style="width:40%">Moneda</th>
                                            <th class="text-center" style="width:30%">Cantidad</th>
                                            <th class="text-end"   style="width:30%">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tablaUSDBody">
                                        <!-- Filas generadas por JS -->
                                    </tbody>
                                    <tfoot>
                                        <tr class="ci-subtotal-row">
                                            <td colspan="2" class="subtotal-label">
                                                <i class="bi bi-sigma me-1"></i>
                                                Total Dólares
                                            </td>
                                            <td class="subtotal-value usd-val" id="subtotalUSD">$ 0.00</td>
                                        </tr>
                                        <tr class="ci-subtotal-row">
                                            <td colspan="2" class="subtotal-label">
                                                <i class="bi bi-arrow-left-right me-1"></i>
                                                Dólares en Córdobas
                                                <small style="opacity:.7;">(TC: <?php echo number_format($tipoCambio, 2); ?>)</small>
                                            </td>
                                            <td class="subtotal-value cordobizado-val" id="subtotalUSDenNIO">C$ 0.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                    </div><!-- /caja-inicial-layout -->

                    <!-- ====== TOTAL EFECTIVO GLOBAL ====== -->
                    <div class="ci-total-global-bar">
                        <div class="global-label">
                            <i class="bi bi-wallet2"></i>
                            Total Efectivo en Caja Inicial
                        </div>
                        <div class="global-amount" id="totalEfectivoGlobal">
                            C$ 0.00
                        </div>
                    </div>

                </form><!-- /formCajaInicial -->

            </div><!-- /container-fluid -->
        </div>
    </div>

    <!-- Tipo de cambio disponible para JS -->
    <script>
        window.TIPO_CAMBIO = <?php echo json_encode($tipoCambio); ?>;
    </script>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/caja_inicial.js?v=<?php echo time(); ?>"></script>
</body>
</html>
