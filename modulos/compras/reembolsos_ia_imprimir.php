<?php
/**
 * Vista de Impresión de Reembolso (Modelo Excel)
 * Ubicación: /modulos/compras/reembolsos_ia_imprimir.php
 */

require_once '../../core/auth/auth.php';
require_once '../../core/database/conexion.php';
require_once '../../core/helpers/funciones.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die("ID de solicitud no proporcionado.");
}

// Obtener datos de la solicitud
$stmt = $conn->prepare("
    SELECT s.*, p.nombre as proveedor_nombre, cp.banco, cp.numero_cuenta, cp.titular, o.Nombre as usuario_nombre
    FROM reembolsos_solicitudes s
    LEFT JOIN proveedores p ON s.id_proveedor = p.id
    LEFT JOIN cuenta_proveedor cp ON s.id_cuenta_proveedor = cp.id
    LEFT JOIN Operarios o ON s.usuario_registro = o.CodOperario
    WHERE s.id = ?
");
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    die("Solicitud no encontrada.");
}

// Obtener detalles
$stmtDet = $conn->prepare("SELECT * FROM reembolsos_detalles WHERE id_solicitud = ?");
$stmtDet->execute([$id]);
$detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

// Rellenar con filas vacías hasta 10 para mantener el formato Excel si es necesario
$filas_vacias = max(0, 10 - count($detalles));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Reembolso #<?= $id ?></title>
    <style>
        @page {
            size: letter;
            margin: 1cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            border: 1px solid #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px 8px;
            text-align: left;
        }
        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            background-color: #fff;
            padding: 8px;
        }
        .v-label {
            background-color: #f8f9fa;
            font-weight: bold;
            width: 15%;
        }
        .v-value {
            width: 35%;
            color: #0000FF;
            font-weight: bold;
        }
        .section-header {
            text-align: right;
            font-size: 10px;
            color: #666;
            border-bottom: none;
        }
        .table-main th {
            text-align: center;
            text-transform: uppercase;
            font-size: 10px;
            padding: 6px;
        }
        .table-main td {
            height: 20px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bg-blue { color: #0000FF; }
        .total-row {
            background-color: #fff;
            font-weight: bold;
        }
        .footer-table td {
            border: 1px solid #000;
        }
        .no-border-top { border-top: none; }
        .no-border-bottom { border-bottom: none; }
        
        @media print {
            .no-print { display: none; }
        }
        .btn-print {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #51B8AC;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>
<body onload="/*window.print()*/">

    <button class="btn-print no-print" onclick="window.print()">Imprimir Documento</button>

    <div class="container">
        <table>
            <tr>
                <td colspan="5" class="header-title">SOLICITUD DE REEMBOLSO</td>
                <td class="text-center" style="width: 100px; color: #999;">v2-Nov24</td>
            </tr>
            <tr>
                <td class="v-label">Fecha:</td>
                <td class="v-value"><?= date('d-M-y', strtotime($solicitud['fecha_solicitud'])) ?></td>
                <td class="v-label">Solicita:</td>
                <td class="v-value"><?= htmlspecialchars($solicitud['usuario_nombre']) ?></td>
                <td class="v-label">Autoriza:</td>
                <td class="v-value"></td>
            </tr>
        </table>

        <table class="table-main">
            <thead>
                <tr>
                    <th colspan="3"></th>
                    <th colspan="2" style="background-color: #fff; border-bottom: none;">PARA REGISTRO CONTABLE</th>
                </tr>
                <tr>
                    <th style="width: 8%;">CANT</th>
                    <th style="width: 42%;">DETALLE DEL GASTO</th>
                    <th style="width: 15%;">TOTAL C$</th>
                    <th style="width: 15%;">CONCEPTO (Sistema)</th>
                    <th style="width: 20%;">CECO</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $det): ?>
                <tr>
                    <td class="text-center bg-blue"><?= (float)$det['cantidad'] ?></td>
                    <td class="bg-blue"><?= htmlspecialchars($det['detalle']) ?></td>
                    <td class="text-right bg-blue"><?= number_format($det['monto_cordobas'], 2) ?></td>
                    <td class="bg-blue"><?= htmlspecialchars($solicitud['concepto']) ?></td>
                    <td class="bg-blue"><?= htmlspecialchars($solicitud['ceco']) ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php for($i=0; $i<$filas_vacias; $i++): ?>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endfor; ?>
                
                <tr class="total-row">
                    <td colspan="2" class="text-center">TOTAL C$:</td>
                    <td class="text-right"><?= number_format($solicitud['total_cordobas'], 2) ?></td>
                    <td colspan="2" style="border: none;"></td>
                </tr>
            </tbody>
        </table>

        <table class="footer-table">
            <tr>
                <td style="width: 10%;">Reembolso:</td>
                <td style="width: 25%;" class="bg-blue">TRANSFERENCIA</td>
                <td style="width: 10%;">Cuenta:</td>
                <td style="width: 25%;" class="bg-blue"><?= htmlspecialchars($solicitud['titular'] ?? 'N/A') ?></td>
                <td style="width: 10%;">Banco:</td>
                <td style="width: 20%;" class="bg-blue"><?= htmlspecialchars($solicitud['banco'] ?? 'N/A') ?></td>
            </tr>
        </table>
    </div>

</body>
</html>
