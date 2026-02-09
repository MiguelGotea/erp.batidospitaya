<?php
require_once '../../../../includes/auth.php';
require_once '../../../../includes/funciones.php';

require_once '../db_ferias.php';

$cierreId = $_GET['id'] ?? 0;

// 1. Obtener información del cierre
$cierre = $db->prepare("SELECT * FROM cierres WHERE id = ?");
$cierre->execute([$cierreId]);
$cierre = $cierre->fetch(PDO::FETCH_ASSOC);

if (!$cierre) {
    header("Location: index.php");
    exit;
}

// 2. Convertir la fecha UTC del cierre a UTC-6 (hora local)
$fechaCierreUTC = new DateTime($cierre['fecha_hora'], new DateTimeZone('UTC'));
$fechaCierreUTC->sub(new DateInterval('PT6H')); // Restar 6 horas
$fechaCierreLocal = $fechaCierreUTC->format('Y-m-d H:i:s');

// 3. Consulta modificada para usar datos históricos de detalles_venta
$query = "
    SELECT 
        v.id as venta_id,
        v.fecha_hora as fecha_venta,
        v.tipo_pago,
        v.nombre_cliente,
        dv.id as detalle_id,
        dv.producto_id,
        COALESCE(dv.nombre_producto, p.nombre) as producto_nombre,
        dv.cantidad,
        COALESCE(dv.precio_unitario_original, dv.precio_unitario) as precio_unitario,
        dv.notas
    FROM ventas v
    JOIN detalles_venta dv ON v.id = dv.venta_id
    LEFT JOIN productos p ON dv.producto_id = p.id
    WHERE v.fecha_cierre = :fecha_cierre
    ORDER BY v.fecha_hora, v.id, dv.id
";

$stmt = $db->prepare($query);
$stmt->bindParam(':fecha_cierre', $fechaCierreLocal);
$stmt->execute();
$productosVendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Calcular totales (modificado para agrupar por nombre histórico)
$totalProductos = 0;
$resumenProductos = [];
$ventasMostradas = [];

foreach ($productosVendidos as $producto) {
    $totalProductos += $producto['cantidad'];
    
    // Resumen por producto (usando nombre histórico como clave)
    $nombreProducto = $producto['producto_nombre'];
    if (!isset($resumenProductos[$nombreProducto])) {
        $resumenProductos[$nombreProducto] = [
            'nombre' => $nombreProducto,
            'cantidad' => 0,
            'total' => 0
        ];
    }
    $resumenProductos[$nombreProducto]['cantidad'] += $producto['cantidad'];
    $resumenProductos[$nombreProducto]['total'] += ($producto['precio_unitario'] * $producto['cantidad']);
    
    // Control de ventas mostradas
    $ventasMostradas[$producto['venta_id']] = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Cierre #<?= $cierreId ?> - Batidos Pitaya</title>
    <link rel="icon" href="../../icon12.png">
    <style>
        :root {
            --color-primario: #51B8AC;
            --color-secundario: #0E544C;
            --color-fondo: #F6F6F6;
            --font-size-base: clamp(14px, 2vw, 16px);
        }
        
        body {
            font-family: 'Calibri', Arial, sans-serif;
            background-color: var(--color-fondo);
            margin: 0;
            padding: 0;
            color: #333;
            font-size: var(--font-size-base); /* Aplicar tamaño base */
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            height: 45px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: var(--color-primario);
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-primary:hover, .btn-secondary:hover {
            background-color: var(--color-secundario);
        }
        
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: clamp(10px, 2vw, 15px);
            margin-bottom: clamp(20px, 3vw, 30px);
        }
        
        .resumen-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        
        .resumen-item h3 {
            margin-top: 0;
            color: var(--color-secundario);
            font-size: clamp(14px, 2vw, 16px);
        }
        
        .resumen-item p {
            font-size: clamp(20px, 3vw, 24px);
            margin: 10px 0 0;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            font-size: clamp(12px, 1.8vw, 15px); /* Tamaño responsive */
        }
        
        th, td {
            padding: clamp(8px, 1.2vw, 12px) clamp(6px, 1vw, 10px);
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--color-secundario);
            color: white;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .badge {
            padding: 3px 6px;
            border-radius: 4px;
            font-size: clamp(10px, 1.5vw, 12px);
        }
        
        .badge-efectivo {
            background-color: #e3f2fd;
            color: #0d47a1;
        }
        
        .badge-pos {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .empty-message {
            text-align: center;
            padding: 30px;
            color: #666;
            font-style: italic;
        }
        
        h1 {
            font-size: clamp(20px, 4vw, 28px);
        }
        
        h2 {
            font-size: clamp(18px, 3vw, 24px);
        }
        
        h3 {
            font-size: clamp(16px, 2.5vw, 20px);
        }
        
        .venta-group {
            margin-bottom: clamp(15px, 2vw, 20px);
            border-bottom: 1px solid #eee;
            padding-bottom: clamp(10px, 1.5vw, 15px);
        }
        
        @media (max-width: 768px) {
            th, td {
                padding: 8px 10px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 768px) {
            .resumen-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
            }
            
            .container {
                padding: 15px;
            }
            
            header {
                flex-direction: column;
                gap: 10px;
                padding: 10px 15px;
            }
            
            .logo {
                height: 35px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: clamp(12px, 1.8vw, 14px);
            }
        }
        
        @media (max-width: 480px) {
            .resumen-grid {
                grid-template-columns: 1fr;
            }
            
            /* Ocultar columnas menos importantes */
            td:nth-child(1), th:nth-child(1) { /* Columna # */
                display: none;
            }
        }
        
        @media (max-width: 360px) {
            /* Ajustar padding general */
            .container {
                padding: 10px;
            }
            
            /* Reducir tamaño de fuente en tablas */
            table {
                font-size: 11px;
            }
            
            /* Ajustar botones */
            .btn {
                padding: 6px 8px;
            }
        }
    </style>
</head>
<body>
    <header>
        <img src="/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
        <div>
            <a href="index.php" class="btn btn-secondary">Volver a Cierres</a>
            <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
        </div>
    </header>
    
    <main class="container">
        <h1>Detalle de Cierre #<?= $cierre['id'] ?></h1>
        <p>Fecha: <?= formatearFecha($cierre['fecha_hora']) ?></p>
        
        <div class="resumen-grid">
            <div class="resumen-item">
                <h3>Total Ventas</h3>
                <p>C$ <?= number_format($cierre['total_ventas'], 2) ?></p>
            </div>
            <div class="resumen-item">
                <h3>Total POS</h3>
                <p>C$ <?= number_format($cierre['total_pos'], 2) ?></p>
            </div>
            <div class="resumen-item">
                <h3>Total Efectivo</h3>
                <p>C$ <?= number_format($cierre['total_efectivo'], 2) ?></p>
            </div>
            <div class="resumen-item">
                <h3>Ventas en este cierre</h3>
                <p><?= count($ventasMostradas) ?></p>
            </div>
            <div class="resumen-item">
                <h3>Productos Vendidos</h3>
                <p><?= $totalProductos ?></p>
            </div>
        </div>
        
        <div class="card">
            <h2>Resumen por Producto</h2>
            
            <?php if (empty($resumenProductos)): ?>
                <div class="empty-message">
                    <p>No hay productos para resumir</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad Total</th>
                            <th>Total Vendido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumenProductos as $producto): ?>
                            <tr>
                                <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                <td><?= $producto['cantidad'] ?></td>
                                <td>C$ <?= number_format($producto['total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Detalle de Productos Vendidos</h2>
            
            <?php if (empty($productosVendidos)): ?>
                <div class="empty-message">
                    <p>No hay productos registrados para este cierre</p>
                </div>
            <?php else: ?>
                <?php
                // Agrupar productos por venta
                $ventasAgrupadas = [];
                foreach ($productosVendidos as $producto) {
                    $ventaId = $producto['venta_id'];
                    if (!isset($ventasAgrupadas[$ventaId])) {
                        $ventasAgrupadas[$ventaId] = [
                            'fecha_venta' => $producto['fecha_venta'],
                            'tipo_pago' => $producto['tipo_pago'],
                            'nombre_cliente' => $producto['nombre_cliente'],  // <-- Añade esta línea
                            'productos' => []
                        ];
                    }
                    $ventasAgrupadas[$ventaId]['productos'][] = $producto;
                }
                ?>
                
                <?php foreach ($ventasAgrupadas as $ventaId => $venta): ?>
                    <div class="venta-group" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                        <h3>Venta #<?= $ventaId ?> - <?= formatearFecha($venta['fecha_venta']) ?>
                            <span class="badge badge-<?= strtolower($venta['tipo_pago']) ?>" style="margin-left: 10px;">
                                <?= $venta['tipo_pago'] ?>
                            </span>
                        </h3>
                        
                        <?php if (!empty($venta['nombre_cliente'])): ?>
                            <p style="margin: 5px 0 10px; color: #0E544C; font-weight: bold; font-size: 15px;">
                                <strong>Cliente:</strong> <?= htmlspecialchars($venta['nombre_cliente']) ?>
                            </p>
                        <?php endif; ?>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>P. Unitario</th>
                                    <th>Total</th>
                                    <th>Notas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($venta['productos'] as $i => $producto): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($producto['producto_nombre']) ?></td>
                                        <td><?= $producto['cantidad'] ?></td>
                                        <td>C$ <?= number_format($producto['precio_unitario'], 2) ?></td>
                                        <td>C$ <?= number_format($producto['precio_unitario'] * $producto['cantidad'], 2) ?></td>
                                        <td><?= htmlspecialchars($producto['notas'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        // Reemplazar el script existente por este:
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body {
                    font-size: 12px;
                    background: white;
                    padding: 0;
                }
                
                header, .btn {
                    display: none !important;
                }
                
                .container {
                    padding: 5mm;
                    max-width: 100%;
                }
                
                .card {
                    page-break-inside: avoid;
                    box-shadow: none;
                    border: 1px solid #ddd;
                    margin-bottom: 15px;
                }
                
                h1, h2, h3 {
                    page-break-after: avoid;
                }
                
                table {
                    font-size: 10px;
                }
                
                th, td {
                    padding: 4px 6px;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>