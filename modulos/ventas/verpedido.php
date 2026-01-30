<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';
require_once '../../includes/funciones.php';

verificarAccesoModulo('atencioncliente');

// Verificar que se haya proporcionado un ID de pedido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$pedido_id = $_GET['id'];
$usuario = obtenerUsuarioActual();

// Obtener datos del pedido
$stmt = $conn->prepare("SELECT v.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono, 
                       c.direccion as cliente_direccion, c.codigo as cliente_codigo,
                       s.nombre as sucursal_nombre, e.nombre as empresa_delivery_nombre
                       FROM ventas v
                       LEFT JOIN clientes c ON v.cliente_id = c.id
                       LEFT JOIN sucursales s ON v.sucursal_id = s.id
                       LEFT JOIN servicios_delivery e ON v.servicio_delivery_id = e.id
                       WHERE v.id = ?");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    header('Location: index.php');
    exit();
}

// Obtener detalles del pedido
$stmt = $conn->prepare("SELECT vd.*, p.nombre as producto_nombre
                       FROM ventas_detalle vd
                       JOIN productos_delivery p ON vd.producto_id = p.id
                       WHERE vd.venta_id = ?");
$stmt->execute([$pedido_id]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener extras de cada detalle
foreach ($detalles as &$detalle) {
    $stmt = $conn->prepare("SELECT e.id, e.nombre, e.precio
                   FROM ventas_extras ve
                   JOIN extras e ON ve.extra_id = e.id
                   WHERE ve.venta_detalle_id = ?");
    $stmt->execute([$detalle['id']]);
    $detalle['extras'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($detalle);

// Obtener endulzante usado en cada detalle
foreach ($detalles as &$detalle) {
    $stmt = $conn->prepare("SELECT en.nombre FROM endulzantes en WHERE en.id = ?");
    $stmt->execute([$detalle['endulzante_id']]);
    $detalle['endulzante_nombre'] = $stmt->fetchColumn();
}
unset($detalle);

// Obtener promoción aplicada si existe
foreach ($detalles as &$detalle) {
    if ($detalle['promocion_id']) {
        $stmt = $conn->prepare("SELECT nombre FROM promociones WHERE id = ?");
        $stmt->execute([$detalle['promocion_id']]);
        $detalle['promocion_nombre'] = $stmt->fetchColumn();
    } else {
        $detalle['promocion_nombre'] = null;
    }
}
unset($detalle);

// Calcular totales
$subtotal = 0;
foreach ($detalles as $detalle) {
    $subtotal += $detalle['precio_unitario'] * $detalle['cantidad'];
    
    // Sumar extras
    foreach ($detalle['extras'] as $extra) {
        $subtotal += $extra['precio'] * $detalle['cantidad'];
    }
}

$total = $subtotal + $pedido['cargo_delivery'];
$tipo_cambio = $conn->query("SELECT tasa FROM tipo_cambio ORDER BY fecha DESC LIMIT 1")->fetchColumn();
$total_dolares = $total / $tipo_cambio;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido <?= htmlspecialchars($pedido['codigo']) ?> | Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" type="image/png" href="../../assets/img/icon12.png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            font-size: 16px;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header-ventas {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .header-ventas h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0E544C;
        }
        
        .btn-volver {
            color: #0E544C;
            text-decoration: none;
            font-size: 1.2rem;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .user-info small {
            color: #666;
        }
        
        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .info-pedido {
            display: flex;
            gap: 20px;
        }
        
        .info-pedido span {
            background-color: #eee;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        /* Secciones */
        .seccion {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .seccion-header {
            background-color: #0E544C;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .seccion-header h2 {
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .seccion-body {
            padding: 10px;
        }
        
        /* Formularios en modo lectura */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group .form-control {
            padding: 8px 12px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
        }
        
        .form-group textarea.form-control {
            min-height: 80px;
            resize: none;
        }
        
        /* Tabla de productos */
        .tabla-pedido {
            margin-top: 20px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #0E544C;
            color: white;
            font-weight: normal;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        /* Resumen de pago */
        .resumen-pago {
            display: flex;
            justify-content: space-between;
            gap: 30px;
        }
        
        .totales {
            flex: 1;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
        }
        
        .total-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #ddd;
        }
        
        .total-final {
            font-weight: bold;
            font-size: 1.1rem;
            margin-top: 10px;
            border-bottom: none;
        }
        
        /* Badges de estado */
        .estado-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .estado-pendiente {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .estado-completado {
            background-color: #d4edda;
            color: #155724;
        }
        
        .estado-cancelado {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Botones de acción */
        .acciones-pedido {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 1rem;
            text-decoration: none;
        }
        
        .btn-volver {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-volver:hover {
            background-color: #5a6268;
        }
        
        .btn-imprimir {
            background-color: #ffbb33;
            color: #333;
        }
        
        .btn-imprimir:hover {
            background-color: #ff8800;
        }
        
        /* Estilo para las secciones superiores */
        .secciones-superiores {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .seccion-cliente, .seccion-servicio {
            flex: 1;
            min-width: 0;
        }
        
        /* Indicaciones especiales */
        #indicaciones {
            background-color: <?= !empty($pedido['notas']) ? '#bfd113' : '#f9f9f9' ?>;
            font-weight: <?= !empty($pedido['notas']) ? 'bold' : 'normal' ?>;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-ventas, .header-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .secciones-superiores {
                flex-direction: column;
            }
            
            .seccion-cliente, .seccion-servicio {
                width: 100%;
            }
            
            .resumen-pago {
                flex-direction: column;
            }
            
            .acciones-pedido {
                flex-direction: column;
                gap: 5px;
            }
            
            .acciones-pedido .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header-ventas">
            <h1>
                <a href="index.php" class="btn-volver" style="display:none;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                Pedido <?= htmlspecialchars($pedido['codigo']) ?>
            </h1>
            <div class="header-info">
                <div class="info-pedido">
                    <span><?= date('d-M-y h:i a', strtotime($pedido['fecha_hora'] . ' -6 hours')) ?></span>
                    <span class="estado-badge estado-<?= $pedido['estado'] ?>">
                        <?= $pedido['estado'] === 'pendiente' ? 'Enviado al Cliente' : ucfirst($pedido['estado']) ?>
                    </span>
                </div>
                <div class="user-info">
                    <span><?= htmlspecialchars($usuario['nombre']) ?></span>
                    <small><?= ucfirst($usuario['rol']) ?></small>
                </div>
            </div>
        </header>

        <div class="secciones-superiores" style="margin-bottom:0 !important;">
            <div class="seccion-cliente">
                <div class="seccion">
                    <div class="seccion-header">
                        <h2><i class="fas fa-user"></i> Cliente</h2>
                    </div>
                    <div class="seccion-body">
                        <div class="form-group">
                            <label for="sucursal">Sucursal</label>
                            <div class="form-control"><?= htmlspecialchars($pedido['sucursal_nombre']) ?></div>
                        </div>
                            
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <div class="form-control"><?= htmlspecialchars($pedido['cliente_telefono']) ?></div>
                        </div>
                            
                        <div class="form-group">
                            <label for="codigo-club">Código Club Pitaya</label>
                            <div class="form-control"><?= htmlspecialchars($pedido['cliente_codigo'] ?: '0') ?></div>
                        </div>
                            
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <div class="form-control"><?= htmlspecialchars($pedido['cliente_nombre']) ?></div>
                        </div>
                            
                        <div class="form-group">
                            <label for="direccion">Dirección</label>
                            <div class="form-control"><?= htmlspecialchars($pedido['direccion_pedido']) ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="indicaciones">Indicaciones Especiales</label>
                            <div id="indicaciones" class="form-control"><?= htmlspecialchars($pedido['notas'] ?: 'Ninguna') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="seccion-servicio">
                <div class="seccion">
                    <div class="seccion-header">
                        <h2><i class="fas fa-truck"></i> Servicio</h2>
                    </div>
                    <div class="seccion-body">
                        <div class="form-group">
                            <label for="tipo-servicio">Tipo de Servicio</label>
                            <div class="form-control">
                                <?= $pedido['tipo_servicio'] == 'delivery' ? 'Delivery' : 'Retiro en Local' ?>
                            </div>
                        </div>
                        
                        <?php if ($pedido['tipo_servicio'] == 'delivery'): ?>
                            <div class="form-group">
                                <label for="distancia">Distancia (KM)</label>
                                <div class="form-control">
                                    <?= empty($pedido['distancia']) || (float)$pedido['distancia'] == 0.0 ? 'N/A' : htmlspecialchars($pedido['distancia']) ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="empresa-delivery">Empresa Delivery</label>
                                <div class="form-control">
                                    <?= htmlspecialchars($pedido['empresa_delivery_nombre'] ?: 'No especificada') ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="conductor">Conductor</label>
                                <div class="form-control"><?= htmlspecialchars($pedido['conductor'] ?: 'N/A') ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="cargo-delivery">Cargo Delivery</label>
                                <div class="form-control">C$ <?= number_format($pedido['cargo_delivery'], 0) ?></div>
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label for="hora-retiro">Hora de Retiro</label>
                                <div class="form-control"><?= htmlspecialchars($pedido['hora_retiro'] ?: 'No especificada') ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
            
            <div class="seccion-body">
                <div class="tabla-pedido">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Tamaño</th>
                                <th>Cantidad</th>
                                <th>P.U.</th>
                                <th>Endulzante</th>
                                <th>Extras</th>
                                <th>Promoción</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($detalles) > 0): ?>
                                <?php foreach ($detalles as $detalle): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($detalle['producto_nombre']) ?></td>
                                        <td><?= $detalle['tamano'] == '16oz' ? '16oz' : ($detalle['tamano'] == '20oz' ? '20oz' : '') ?></td>
                                        <td><?= htmlspecialchars($detalle['cantidad']) ?></td>
                                        <td>C$ <?= number_format($detalle['precio_unitario'], 0) ?></td>
                                        <td><?= htmlspecialchars($detalle['endulzante_nombre']) ?></td>
                                        <td>
                                            <?php if (count($detalle['extras']) > 0): ?>
                                                <ul style="list-style-type: none; padding: 0; margin: 0;">
                                                    <?php foreach ($detalle['extras'] as $extra): ?>
                                                        <li><?= htmlspecialchars($extra['nombre']) ?> (C$<?= number_format($extra['precio'], 0) ?>)</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                Ninguno
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $detalle['promocion_nombre'] ? htmlspecialchars($detalle['promocion_nombre']) : 'Ninguna' ?></td>
                                        <td><?= htmlspecialchars($detalle['notas'] ?: 'Ninguna') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">No hay productos en este pedido</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        

        <div class="seccion">
            <div class="seccion-header">
                <h2><i class="fas fa-money-bill-wave"></i> Pago</h2>
            </div>
            <div class="seccion-body">
                <div class="resumen-pago">
                    <div class="totales">
                        <div class="total-item">
                            <span>Subtotal:</span>
                            <span>C$ <?= number_format($subtotal, 0) ?></span>
                        </div>
                        <div class="total-item">
                            <span>Delivery:</span>
                            <span>C$ <?= number_format($pedido['cargo_delivery'], 0) ?></span>
                        </div>
                        <div class="total-item total-final">
                            <span>Total:</span>
                            <span>C$ <?= number_format($total, 0) ?></span>
                        </div>
                        <div class="total-item">
                            <small>Total en dólares: $<?= number_format($total_dolares, 1) ?> (TC: <?= number_format($tipo_cambio, 1) ?>)</small>
                        </div>
                    </div>
                    
                    <div class="metodo-pago">
                        <div class="form-group">
                            <label for="metodo-pago">Método de Pago</label>
                            <div class="form-control">
                                <?= $pedido['tipo_pago'] == 'transferencia' ? 'Transferencia' : 
                                   ($pedido['tipo_pago'] == 'efectivo' ? 'Efectivo' : 'POS') ?>
                            </div>
                        </div>
                        
                        <?php if ($pedido['tipo_pago'] == 'efectivo'): ?>
                            <div class="form-group">
                                <label for="pago-cordobas">Pago Recibido (C$)</label>
                                <div class="form-control">C$ <?= number_format($pedido['pago_recibido_cordobas'], 0) ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="pago-dolares">Pago Recibido ($)</label>
                                <div class="form-control">$<?= number_format($pedido['pago_recibido_dolares'], 2) ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="cambio">Cambio (C$)</label>
                                <div class="form-control">C$ <?= number_format($pedido['cambio_cordobas'], 0) ?></div>
                            </div>
                        <?php elseif ($pedido['tipo_pago'] == 'transferencia'): ?>
                            <div class="form-group">
                                <label>Información Bancaria</label>
                                <img src="../../assets/img/bancos_pagopitaya.png" alt="Datos Bancarios" style="max-width: 50%; height: auto; border: 1px solid #ddd;">
                                <p style="margin-top: 5px; font-size: 0.9em;">
                                    <strong>Nota:</strong> Comprobante enviado al WhatsApp de Batidos Pitaya.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="acciones-pedido">
            <a href="index.php" class="btn btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <button type="button" class="btn btn-imprimir" id="btn-imprimir">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script>
        $(document).ready(function() {
            // Función para imprimir el pedido
            $('#btn-imprimir').click(function() {
                // Crear un div oculto con el formato de impresión
                let printDiv = document.createElement('div');
                printDiv.id = 'print-container';
                printDiv.style.position = 'fixed';
                printDiv.style.left = '-9999px';
                printDiv.style.width = '800px';
                printDiv.style.padding = '20px';
                printDiv.style.backgroundColor = 'white';
                
                // Construir el contenido de impresión
                let contenido = `
                    <style>
                        #print-container {
                            font-family: Arial, sans-serif;
                            color: #333;
                        }
                        #print-container h2, 
                        #print-container h3 {
                            color: #0E544C;
                        }
                        #print-container table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 15px 0;
                            font-size: 14px;
                        }
                        #print-container th {
                            background-color: #0E544C;
                            color: white;
                            padding: 8px;
                            text-align: left;
                        }
                        #print-container td {
                            padding: 8px;
                            border-bottom: 1px solid #ddd;
                        }
                        #print-container .total-item {
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 8px;
                        }
                        #print-container .total-final {
                            font-weight: bold;
                            font-size: 1.1em;
                            margin-top: 10px;
                        }
                        #print-container hr {
                            border: none;
                            border-top: 1px dashed #ccc;
                            margin: 15px 0;
                        }
                        @media print {
                            body * {
                                visibility: hidden;
                            }
                            #print-container, #print-container * {
                                visibility: visible;
                            }
                            #print-container {
                                position: absolute;
                                left: 0;
                                top: 0;
                                width: 100%;
                                padding: 0;
                                margin: 0;
                            }
                        }
                    </style>
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h2 style="margin-bottom: 5px;">Batidos Pitaya</h2>
                        <p style="font-size: 0.9em; color: #666;">
                            Pedido #<?= htmlspecialchars($pedido['codigo']) ?> - <?= date('d-M-y h:i a', strtotime($pedido['fecha_creacion'])) ?>
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="flex: 1; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                            <h3 style="color: #0E544C; margin-bottom: 10px; font-size: 16px;">
                                <i class="fas fa-user"></i> Cliente
                            </h3>
                            <p><strong>Nombre:</strong> <?= htmlspecialchars($pedido['cliente_nombre']) ?></p>
                            <p><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['cliente_telefono']) ?></p>
                            <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion_pedido']) ?></p>
                            <p><strong>Código Club:</strong> <?= htmlspecialchars($pedido['cliente_codigo'] ?: '0') ?></p>
                            <p><strong>Indicaciones:</strong> <?= htmlspecialchars($pedido['notas'] ?: 'Ninguna') ?></p>
                        </div>
                        
                        <div style="flex: 1; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                            <h3 style="color: #0E544C; margin-bottom: 10px; font-size: 16px;">
                                <i class="fas fa-truck"></i> Servicio
                            </h3>
                            <p><strong>Tipo:</strong> <?= $pedido['tipo_servicio'] == 'delivery' ? 'Delivery' : 'Retiro en Local' ?></p>
                            <?php if ($pedido['tipo_servicio'] == 'delivery'): ?>
                                <p><strong>Empresa:</strong> <?= htmlspecialchars($pedido['empresa_delivery_nombre'] ?: 'No especificada') ?></p>
                                <p><strong>Conductor:</strong> <?= htmlspecialchars($pedido['conductor'] ?: 'No especificado') ?></p>
                                <p><strong>Cargo Delivery:</strong> C$ <?= number_format($pedido['cargo_delivery'], 0) ?></p>
                            <?php else: ?>
                                <p><strong>Hora de Retiro:</strong> <?= htmlspecialchars($pedido['hora_retiro'] ?: 'No especificada') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h3 style="margin-bottom: 10px;">Productos</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Tam.</th>
                                <th>Cant.</th>
                                <th>P.U.</th>
                                <th>Endulzante</th>
                                <th>Extras</th>
                                <th>Promoción</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $detalle): ?>
                                <tr>
                                    <td><?= htmlspecialchars($detalle['producto_nombre']) ?></td>
                                    <td><?= $detalle['tamano'] == '16oz' ? '16oz' : ($detalle['tamano'] == '20oz' ? '20oz' : '') ?></td>
                                    <td><?= htmlspecialchars($detalle['cantidad']) ?></td>
                                    <td>C$ <?= number_format($detalle['precio_unitario'], 0) ?></td>
                                    <td><?= htmlspecialchars($detalle['endulzante_nombre']) ?></td>
                                    <td>
                                        <?php if (count($detalle['extras']) > 0): ?>
                                            <?php foreach ($detalle['extras'] as $extra): ?>
                                                <?= htmlspecialchars($extra['nombre']) ?> (C$<?= number_format($extra['precio'], 0) ?>)<br>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            Ninguno
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $detalle['promocion_nombre'] ? htmlspecialchars($detalle['promocion_nombre']) : 'Ninguna' ?></td>
                                    <td><?= htmlspecialchars($detalle['notas'] ?: 'Ninguna') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                        <h3 style="color: #0E544C; margin-bottom: 10px; font-size: 16px;">
                            <i class="fas fa-money-bill-wave"></i> Resumen de Pago
                        </h3>
                        <div style="display: flex; justify-content: space-between;">
                            <div>
                                <p><strong>Método de Pago:</strong> <?= $pedido['tipo_pago'] == 'transferencia' ? 'Transferencia' : 
                                   ($pedido['tipo_pago'] == 'efectivo' ? 'Efectivo' : 'POS') ?></p>
                                <?php if ($pedido['tipo_pago'] == 'efectivo'): ?>
                                    <p><strong>Pago Recibido (C$):</strong> C$ <?= number_format($pedido['pago_recibido_cordobas'], 0) ?></p>
                                    <p><strong>Pago Recibido ($):</strong> $<?= number_format($pedido['pago_recibido_dolares'], 2) ?></p>
                                    <p><strong>Cambio (C$):</strong> C$ <?= number_format($pedido['cambio_cordobas'], 0) ?></p>
                                <?php elseif ($pedido['tipo_pago'] == 'transferencia'): ?>
                                    <div class="form-group">
                                        <label>Información Bancaria</label>
                                        <img src="../../assets/img/bancos_pagopitaya.png" alt="Datos Bancarios" style="max-width: 60%; height: auto; border: 1px solid #ddd;">
                                        <p style="margin-top: 5px; font-size: 0.9em;">
                                            <strong>Nota:</strong> Comprobante enviado al WhatsApp de Batidos Pitaya.
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right;">
                                <p><strong>Subtotal:</strong> C$ <?= number_format($subtotal, 0) ?></p>
                                <p><strong>Delivery:</strong> C$ <?= number_format($pedido['cargo_delivery'], 0) ?></p>
                                <p><strong>Total:</strong> C$ <?= number_format($total, 0) ?></p>
                                <p><small>Total en dólares: $<?= number_format($total_dolares, 1) ?> (TC: <?= number_format($tipo_cambio, 1) ?>)</small></p>
                            </div>
                        </div>
                    </div>
                    
                    <p style="text-align: center; margin-top: 20px; font-size: 0.9em; color: #666;">
                        ¡Gracias por su compra!
                    </p>
                `;
                
                printDiv.innerHTML = contenido;
                document.body.appendChild(printDiv);
                
                // Usar html2canvas para generar una imagen de alta calidad
                html2canvas(printDiv, {
                    scale: 2,
                    logging: false,
                    useCORS: true,
                    backgroundColor: '#fff',
                    allowTaint: true
                }).then(canvas => {
                    // Abrir ventana con la imagen para imprimir
                    let ventana = window.open('', '_blank');
                    ventana.document.write('<img src="' + canvas.toDataURL('image/png', 1.0) + '" style="max-width:100%;"/>');
                    ventana.document.close();
                    
                    // Esperar a que la imagen cargue antes de imprimir
                    ventana.onload = function() {
                        ventana.print();
                    };
                    
                    // Eliminar el div temporal
                    document.body.removeChild(printDiv);
                });
            });
        });
    </script>
</body>
</html>