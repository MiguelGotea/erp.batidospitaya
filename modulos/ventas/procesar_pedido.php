<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';
require_once '../../includes/funciones.php';

header('Content-Type: application/json');

// Verificar que sea una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Verificar conexión a la base de datos
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos']);
    exit();
}

// Validar y obtener datos básicos del pedido
$pedido_id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : null;
$sucursal_id = intval($_POST['sucursal_id']);
$usuario_id = $_SESSION['usuario_id'];
$telefono = trim($_POST['telefono'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$indicaciones = trim($_POST['indicaciones'] ?? '');
$tipo_servicio = $_POST['tipo_servicio'];
$tipo_pago = $_POST['metodo_pago'];
$codigo_club = $_POST['codigo_club'] ?? '0';

// Validar datos mínimos
if (empty($sucursal_id) || empty($tipo_servicio) || empty($tipo_pago)) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

if ($telefono && strlen($telefono) !== 8) {
    echo json_encode(['success' => false, 'error' => 'El teléfono debe tener 8 dígitos']);
    exit();
}

// Manejar datos de delivery
$servicio_delivery_id = null;
$distancia = null;
$conductor = null;
$cargo_delivery = 0;

if ($tipo_servicio === 'delivery') {
    $servicio_delivery_id = isset($_POST['empresa_delivery']) ? intval($_POST['empresa_delivery']) : null;
    $distancia = isset($_POST['distancia']) ? floatval($_POST['distancia']) : 0;
    $conductor = trim($_POST['conductor'] ?? '');
    $cargo_delivery = isset($_POST['cargo_delivery']) ? floatval($_POST['cargo_delivery']) : 0;
}

// Validar productos
if (empty($_POST['producto_id']) || !is_array($_POST['producto_id'])) {
    echo json_encode(['success' => false, 'error' => 'No hay productos en el pedido']);
    exit();
}

// Procesar productos
$productos = [];
$monto_total = 0;

foreach ($_POST['producto_id'] as $index => $producto_id) {
    $producto_id = intval($producto_id);
    $tamano = $_POST['tamano'][$index] ?? 'unico';
    $cantidad = intval($_POST['cantidad'][$index] ?? 1);
    
    // Obtener instanciaId para los endulzantes
    $instanciaId = 'prod_'.$producto_id.'_'.$index; // Crear un ID único para esta instancia
    
    $endulzante_id = isset($_POST['endulzante'][$instanciaId]) ? intval($_POST['endulzante'][$instanciaId]) : 0;
    $notas = trim($_POST['notas_producto'][$index] ?? '');
    
    $promocion_id = isset($_POST['promocion'][$index]) ? intval($_POST['promocion'][$index]) : null;
    
    // Obtener precio del producto según tamaño
    $stmt = $conn->prepare("SELECT 
        CASE ? 
            WHEN '16oz' THEN precio_16oz 
            WHEN '20oz' THEN precio_20oz 
            ELSE precio_fijo 
        END as precio
        FROM productos_delivery WHERE id = ?");
    $stmt->execute([$tamano, $producto_id]);
    $precio = $stmt->fetchColumn();
    
    if (!$precio) {
        echo json_encode(['success' => false, 'error' => 'Precio no encontrado para producto ID: '.$producto_id]);
        exit();
    }
    
    $precio_original = $precio; // Guardar precio original para calcular descuentos
    
    // Aplicar promoción si existe
    if ($promocion_id) {
        $stmt = $conn->prepare("SELECT valor FROM promociones WHERE id = ?");
        $stmt->execute([$promocion_id]);
        $valor_promocion = $stmt->fetchColumn();
        
        if ($valor_promocion) {
            $precio = $precio * $valor_promocion; // Aplicar descuento porcentual
        }
    }
    
    $subtotal = $precio * $cantidad;
    $monto_total += $subtotal;
    
    // Procesar extras
    $extras = [];
    if (isset($_POST['extras'][$instanciaId]) && is_array($_POST['extras'][$instanciaId])) {
        foreach ($_POST['extras'][$instanciaId] as $extra_id) {
            $extra_id = intval($extra_id);
            $stmt = $conn->prepare("SELECT precio FROM extras WHERE id = ?");
            $stmt->execute([$extra_id]);
            $precio_extra = $stmt->fetchColumn();
            
            if ($precio_extra) {
                $monto_total += $precio_extra * $cantidad;
                $extras[] = $extra_id;
            }
        }
    }
    
    $productos[] = [
        'producto_id' => $producto_id,
        'tamano' => $tamano,
        'cantidad' => $cantidad,
        'precio_unitario' => $precio,
        'precio_original' => $precio_original,
        'endulzante_id' => $endulzante_id,
        'extras' => $extras,
        'notas' => $notas,
        'promocion_id' => $promocion_id
    ];
}

// Agregar cargo de delivery al total
$monto_total += $cargo_delivery;

// Calcular montos en dólares
$tipo_cambio = $conn->query("SELECT tasa FROM tipo_cambio ORDER BY fecha DESC LIMIT 1")->fetchColumn();
$monto_dolares = round($monto_total / $tipo_cambio, 1);

$hora_retiro = ($tipo_servicio === 'retiro_local' && !empty($_POST['hora_retiro'])) ? 
               $_POST['hora_retiro'] : null;

// Manejar pago
$pago_recibido_dolares = 0;
$pago_recibido_cordobas = 0;
$cambio_cordobas = 0;

if ($tipo_pago === 'efectivo') {
    $pago_recibido_dolares = floatval($_POST['pago_dolares'] ?? 0);
    $pago_recibido_cordobas = floatval($_POST['pago_cordobas'] ?? 0);
    
    $total_pagado = $pago_recibido_cordobas + ($pago_recibido_dolares * $tipo_cambio);
    $cambio_cordobas = max(0, $total_pagado - $monto_total);
}

// Manejar cliente
$cliente_id = null;
if (!empty($telefono)) {
    // Buscar cliente existente
    $stmt = $conn->prepare("SELECT id, codigo FROM clientes WHERE telefono = ?");
    $stmt->execute([$telefono]);
    $cliente_existente = $stmt->fetch();
    
    if ($cliente_existente) {
        $cliente_id = $cliente_existente['id'];
        
        // Actualizar solo el nombre (no la dirección)
        $stmt = $conn->prepare("UPDATE clientes SET 
                              nombre = ?,
                              codigo = ?
                              WHERE id = ?");
        
        // Solo actualizar código si no tenía uno antes (era 0)
        $nuevo_codigo = ($cliente_existente['codigo'] == '0' || $cliente_existente['codigo'] === null) ? 
                        $codigo_club : $cliente_existente['codigo'];
        
        $stmt->execute([$nombre, $nuevo_codigo, $cliente_id]);
    } else {
        // Crear nuevo cliente con todos los datos
        $stmt = $conn->prepare("INSERT INTO clientes 
                              (codigo, nombre, telefono, direccion, fecha_registro) 
                              VALUES (?, ?, ?, ?, CURDATE())");
        $stmt->execute([$codigo_club, $nombre, $telefono, $direccion]);
        $cliente_id = $conn->lastInsertId();
    }
}

// Iniciar transacción
$conn->beginTransaction();

try {
    if ($pedido_id) {
        // Actualizar pedido existente
        $stmt = $conn->prepare("UPDATE ventas SET
                              sucursal_id = ?,
                              cliente_id = ?,
                              tipo_servicio = ?,
                              hora_retiro = ?,
                              servicio_delivery_id = ?,
                              distancia = ?,
                              conductor = ?,
                              tipo_pago = ?,
                              monto_total = ?,
                              monto_dolares = ?,
                              monto_cordobas = ?,
                              pago_recibido_dolares = ?,
                              pago_recibido_cordobas = ?,
                              cambio_cordobas = ?,
                              cargo_delivery = ?,
                              notas = ?,
                              estado = 'completado'
                              WHERE id = ?");
        
        $stmt->execute([
            $sucursal_id,
            $cliente_id,
            $tipo_servicio,
            $hora_retiro,
            $servicio_delivery_id,
            $distancia,
            $conductor,
            $tipo_pago,
            $monto_total,
            $monto_dolares,
            $monto_total,
            $pago_recibido_dolares,
            $pago_recibido_cordobas,
            $cambio_cordobas,
            $cargo_delivery,
            $indicaciones,
            $pedido_id
        ]);
        
        // Eliminar detalles antiguos
        $conn->prepare("DELETE FROM ventas_detalle WHERE venta_id = ?")->execute([$pedido_id]);
        $conn->prepare("DELETE FROM ventas_extras WHERE venta_detalle_id IN (SELECT id FROM ventas_detalle WHERE venta_id = ?)")->execute([$pedido_id]);
        $conn->prepare("DELETE FROM ventas_detalle_promociones WHERE venta_detalle_id IN (SELECT id FROM ventas_detalle WHERE venta_id = ?)")->execute([$pedido_id]);
    } else {
        // Crear nuevo pedido
        $codigo = $conn->query("SELECT MAX(id) + 1 FROM ventas")->fetchColumn() ?? 1;
        
        $stmt = $conn->prepare("INSERT INTO ventas (
                              codigo, sucursal_id, usuario_id, cliente_id, direccion_pedido, fecha_hora,
                              tipo_servicio, hora_retiro, servicio_delivery_id,
                              distancia, conductor, tipo_pago, monto_total, monto_dolares,
                              monto_cordobas, pago_recibido_dolares, pago_recibido_cordobas,
                              cambio_cordobas, cargo_delivery, notas, estado
                              ) VALUES (
                              ?, ?, ?, ?, ?, NOW(),
                              ?, ?, ?,
                              ?, ?, ?, ?, ?,
                              ?, ?, ?,
                              ?, ?, ?, 'completado'
                              )");
        
        $stmt->execute([
            $codigo,
            $sucursal_id,
            $usuario_id,
            $cliente_id,
            $direccion,
            $tipo_servicio,
            $hora_retiro,
            $servicio_delivery_id,
            $distancia,
            $conductor,
            $tipo_pago,
            $monto_total,
            $monto_dolares,
            $monto_total,
            $pago_recibido_dolares,
            $pago_recibido_cordobas,
            $cambio_cordobas,
            $cargo_delivery,
            $indicaciones
        ]);
        
        $pedido_id = $conn->lastInsertId();
    }
    
    // Agregar detalles del pedido
    foreach ($productos as $producto) {
        $stmt = $conn->prepare("INSERT INTO ventas_detalle (
            venta_id, producto_id, tamano, cantidad, precio_unitario,
            endulzante_id, notas, promocion_id
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?
        )");
        
        $stmt->execute([
            $pedido_id,
            $producto['producto_id'],
            $producto['tamano'],
            $producto['cantidad'],
            $producto['precio_unitario'],
            $producto['endulzante_id'],
            $producto['notas'],
            $producto['promocion_id']
        ]);
        
        $detalle_id = $conn->lastInsertId();
        
        // Agregar extras si existen
        foreach ($producto['extras'] as $extra_id) {
            $stmt = $conn->prepare("INSERT INTO ventas_extras (
                                  venta_detalle_id, extra_id
                                  ) VALUES (?, ?)");
            $stmt->execute([$detalle_id, $extra_id]);
        }
        
        // Guardar promociones aplicadas
        if ($producto['promocion_id']) {
            $stmt = $conn->prepare("INSERT INTO ventas_detalle_promociones (
                venta_detalle_id, promocion_id, monto_descuento
            ) VALUES (?, ?, ?)");
            
            // Calcular monto de descuento (precio original - precio con promoción)
            $monto_descuento = $producto['precio_original'] - $producto['precio_unitario'];
            $stmt->execute([$detalle_id, $producto['promocion_id'], $monto_descuento]);
        }
    }
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'pedido_id' => $pedido_id,
        'redirect' => 'index.php'
    ]);
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Error en transacción: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar el pedido: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error general: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error inesperado: ' . $e->getMessage()
    ]);
}