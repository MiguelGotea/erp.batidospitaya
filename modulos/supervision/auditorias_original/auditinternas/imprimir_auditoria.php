<?php
// Conexión a la base de datos
$host = 'localhost';
$dbname = 'u839374897_avisos';
$username = 'u839374897_avisos';
$password = '8GLVR9*k';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener el ID de la auditoría
$auditoriaId = $_GET['id'] ?? 0;

if (!$auditoriaId) {
    die("ID de auditoría no especificado");
}

// Obtener datos principales
$stmt = $conn->prepare("SELECT a.*, b.name as sucursal_nombre 
                      FROM auditoria_caja_chica a
                      JOIN branches b ON a.sucursal_id = b.id
                      WHERE a.id = ?");
$stmt->execute([$auditoriaId]);
$auditoria = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$auditoria) {
    die("Auditoría no encontrada");
}

// Obtener detalles de denominaciones
$stmtDetalle = $conn->prepare("SELECT * FROM auditoria_caja_chica_detalle 
                             WHERE auditoria_id = ? 
                             ORDER BY denominacion DESC");
$stmtDetalle->execute([$auditoriaId]);
$detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

// Calcular diferencia
$diferencia = $auditoria['faltante_sobrante'];
$claseDiferencia = ($diferencia > 0) ? 'text-danger' : (($diferencia < 0) ? 'text-warning' : 'text-success');
$textoDiferencia = ($diferencia > 0) ? 'Faltante' : (($diferencia < 0) ? 'Sobrante' : 'Correcto');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Auditoría #<?= $auditoriaId ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #0E544C;
            padding-bottom: 10px;
        }
        
        .header h1 {
            color: #0E544C;
            margin: 0;
            font-size: 24px;
        }
        
        .header .subtitle {
            font-size: 16px;
            color: #666;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #0E544C;
            font-size: 14px;
        }
        
        .info-value {
            font-size: 14px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #0E544C;
        }
        
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        .text-warning {
            color: #ffc107;
        }
        
        .text-success {
            color: #28a745;
        }
        
        .comentarios {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .comentarios .info-label {
            margin-bottom: 5px;
        }
        
        .comentarios .info-value {
            white-space: pre-wrap;
            line-height: 1.4;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            table {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Auditoría de Caja Chica</h1>
        <div class="subtitle">Sucursal: <?= htmlspecialchars($auditoria['sucursal_nombre']) ?></div>
    </div>
    
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label">Número de Auditoría</div>
            <div class="info-value">#<?= $auditoriaId ?></div>
        </div>
        
        <div class="info-item">
            <div class="info-label">Fecha y Hora</div>
            <div class="info-value"><?= date('d/m/Y H:i', strtotime($auditoria['fecha_hora'])) ?></div>
        </div>
        
        <div class="info-item">
            <div class="info-label">Líder de Tienda</div>
            <div class="info-value"><?= htmlspecialchars($auditoria['lider_tienda']) ?></div>
        </div>
        
        <div class="info-item">
            <div class="info-label">Auditor</div>
            <div class="info-value"><?= htmlspecialchars($auditoria['auditor']) ?></div>
        </div>
    </div>
    
    <h3>Conteo de Efectivo</h3>
    <table>
        <thead>
            <tr>
                <th>Denominación</th>
                <th>Cantidad</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detalles as $detalle): ?>
                <tr>
                    <td>C$ <?= number_format($detalle['denominacion'], 2) ?></td>
                    <td><?= $detalle['cantidad'] ?></td>
                    <td>C$ <?= number_format($detalle['total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="2">Monto Designado</td>
                <td>C$ <?= number_format($auditoria['monto_designado'], 2) ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="2">Total Conteo</td>
                <td>C$ <?= number_format($auditoria['total_conteo'], 2) ?></td>
            </tr>
            <tr class="total-row">
                <td colspan="2">Diferencia</td>
                <td class="<?= $claseDiferencia ?>">
                    <?= $textoDiferencia ?>: C$ <?= number_format(abs($diferencia), 2) ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <?php if (!empty($auditoria['comentarios'])): ?>
    <div class="comentarios">
        <div class="info-label">Comentarios</div>
        <div class="info-value"><?= nl2br(htmlspecialchars($auditoria['comentarios'])) ?></div>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        Generado el <?= date('d/m/Y H:i') ?> | Sistema de Auditoría de Caja Chica
    </div>
    
    <script>
        // Imprimir automáticamente al cargar la página
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
            
            // Cerrar la ventana después de imprimir (solo si no se cancela)
            window.onafterprint = function() {
                window.close();
            };
        };
    </script>
</body>
</html>
