<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar acceso
verificarAccesoModulo('sucursales');
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!$esAdmin && !verificarAccesoSucursalCargo([27], [14])) {
    header('Location: ../index.php');
    exit;
}

require_once 'db_ferias.php';

$ventaId = $_GET['id'] ?? 0;

// Obtener datos de la venta
$venta = obtenerVentaPorId($ventaId);
$detalles = obtenerDetallesVenta($ventaId);

// Calcular total
$total = 0;
foreach ($detalles as $detalle) {
    $total += $detalle['precio_unitario'] * $detalle['cantidad'];
}

// Configurar cabeceras para impresión térmica
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Comanda #<?= $ventaId ?></title>
    <style>
        @page {
            size: 80mm auto;
            margin: 0;
        }

        body {
            font-family: 'Arial Narrow', Arial, sans-serif;
            width: 80mm;
            margin: 0;
            padding: 2mm;
            font-size: 12px;
        }

        .logo {
            width: 100%;
            text-align: center;
            margin-bottom: 5px;
        }

        .logo img {
            max-width: 50mm;
            height: auto;
        }

        .text-center {
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }

        th,
        td {
            padding: 3px 2px;
            border-bottom: 1px dashed #ccc;
        }

        .total {
            font-weight: bold;
            font-size: 14px;
        }

        .separator {
            height: 10mm;
            /* Espacio entre comanda y factura */
            position: relative;
        }

        .separator::after {
            content: "";
            display: block;
            border-top: 1px dashed #000;
            margin: 2mm 0;
        }

        .cut {
            display: block;
            text-align: center;
            margin: 5mm 0;
            white-space: pre;
        }

        @media print {

            /* Comando de corte para impresoras térmicas */
            .cut {
                display: block !important;
                visibility: visible !important;
                height: 0 !important;
                overflow: hidden !important;
                margin: 0 !important;
                padding: 0 !important;
                page-break-after: always;
            }

            /* Comando de corte real (usando CSS) */
            .cut::after {
                content: "\00000C";
                /* Carácter de control para corte (form feed) */
                display: block;
                font-size: 0;
                height: 0;
            }

            /* Espacio adicional después de la factura */
            .factura::after {
                content: "";
                display: block;
                height: 50mm;
                /* Espacio para el corte manual */
            }
        }
    </style>
</head>

<body>
    <!-- Factura (primera página) -->
    <div class="factura" style="margin-top:57px; margin-bottom:45px;">
        <div class="logo">
            <img src="../../../core/assets/img/Logo.svg" alt="Batidos Pitaya">
        </div>
        <p class="text-center">¡Los mejores batidos de pura fruta!<br>Más info en batidospitaya.com</p>

        <?php if (!empty($venta['nombre_cliente'])): ?>
            <p style="text-align:center;">Bienvenid@ <?= htmlspecialchars($venta['nombre_cliente']) ?></p>
        <?php endif; ?>

        <p>Fecha: <?= formatearFecha($venta['fecha_hora']) ?> <?= formatearHora($venta['fecha_hora']) ?></p>

        <p>Ticket: #<?= $ventaId ?></p>

        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant</th>
                    <th>P.U.</th>
                    <th>P.T.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td><?= htmlspecialchars($detalle['nombre']) ?></td>
                        <td style="text-align:center;"><?= $detalle['cantidad'] ?></td>
                        <td>C$ <?= number_format($detalle['precio_unitario'], 2) ?></td>
                        <td>C$ <?= number_format($detalle['precio_unitario'] * $detalle['cantidad'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="total">TOTAL: C$ <?= number_format($total, 2) ?></p>
        <p class="text-center">Método de pago: <?= $venta['tipo_pago'] ?></p>
        <p class="text-center">¡Agradecemos su visita!</p>

        <div style="text-align:center; margin-top:45px;">
            <p>
                -
            </p>
        </div>
    </div>

    <!-- Separador con marca de corte -->
    <div class="cut"></div>

    <!-- Comanda (segunda página) -->
    <div class="comanda" style="margin-top:50px; margin-bottom:50px;">
        <h3 class="text-center">#<?= $ventaId ?></h3>

        <?php if (!empty($venta['nombre_cliente'])): ?>
            <p style="text-align:center; font-weight:bold; font-size:16px;">
                <?= strtoupper(htmlspecialchars($venta['nombre_cliente'])) ?></p>
        <?php endif; ?>

        <p>Fecha: <?= formatearFecha($venta['fecha_hora']) ?> <?= formatearHora($venta['fecha_hora']) ?></p>

        <table>
            <thead>
                <tr>
                    <th style="font-weight: normal;">Prod.</th>
                    <th style="font-weight: normal;">Cant</th>
                    <th style="font-weight: normal;">Notas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $detalle): ?>
                    <tr>
                        <td style="font-weight: bold;"><?= htmlspecialchars($detalle['nombre']) ?></td>
                        <td style="font-weight: bold; text-align:center;"><?= $detalle['cantidad'] ?></td>
                        <td style="font-weight: bold;"><?= htmlspecialchars($detalle['notas']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="text-align:center; margin-top:45px;">
            <p>
                -
            </p>
        </div>
    </div>

    <div style="display:none;" class="no-print" style="margin-top: 10px;">
        <button onclick="window.print()">Imprimir</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    <script>
        // Auto-imprimir y cerrar después de imprimir
        window.onload = function () {
            setTimeout(function () {
                window.print();
            }, 300);
        };
    </script>
</body>

</html>