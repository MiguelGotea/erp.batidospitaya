<?php
// Configuración específica para la base de datos de ferias
$host = 'localhost';
$dbname = 'u839374897_ferias';
$username = 'u839374897_ferias';
$password = 'FerPitHaya2025$';

try {
    $db_ferias = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db_ferias->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión a la base de ferias: " . $e->getMessage());
}

// Funciones específicas para ferias
function obtenerProductos() {
    global $db_ferias;
    $stmt = $db_ferias->query("SELECT * FROM productos WHERE activo = 1");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerVentaActiva() {
    global $db_ferias;
    $stmt = $db_ferias->query("SELECT id FROM ventas WHERE cerrada = 0 ORDER BY id DESC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function procesarVenta($productos, $tipoPago, $nombreCliente = null) {
    global $db_ferias;
    
    try {
        $db_ferias->beginTransaction();
        
        // Crear venta
        $stmtVenta = $db_ferias->prepare("INSERT INTO ventas (tipo_pago, nombre_cliente) VALUES (?, ?)");
        $stmtVenta->execute([$tipoPago, $nombreCliente]);
        $ventaId = $db_ferias->lastInsertId();
        
        // Agregar detalles (capturando nombre y precio actual)
        $stmtDetalle = $db_ferias->prepare("INSERT INTO detalles_venta 
                                   (venta_id, producto_id, cantidad, precio_unitario, notas, nombre_producto, precio_unitario_original) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($productos as $producto) {
            // Obtener datos actuales del producto
            $stmtProducto = $db_ferias->prepare("SELECT nombre, precio FROM productos WHERE id = ?");
            $stmtProducto->execute([$producto['id']]);
            $productoActual = $stmtProducto->fetch(PDO::FETCH_ASSOC);
            
            $stmtDetalle->execute([
                $ventaId,
                $producto['id'],
                $producto['cantidad'],
                $producto['precio'],
                $producto['notas'],
                $productoActual['nombre'],
                $productoActual['precio']
            ]);
        }
        
        $db_ferias->commit();
        return ['success' => true, 'ventaId' => $ventaId];
    } catch (PDOException $e) {
        $db_ferias->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function cerrarEvento() {
    global $db_ferias;
    
    try {
        $db_ferias->beginTransaction();
        
        // Obtener ventas no cerradas
        $stmtVentas = $db_ferias->query("SELECT * FROM ventas WHERE cerrada = 0");
        $ventas = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($ventas)) {
            return ['success' => false, 'message' => 'No hay ventas pendientes de cerrar'];
        }
        
        // Calcular totales
        $totalVentas = 0;
        $totalPos = 0;
        $totalEfectivo = 0;
        $fechaCierre = date('Y-m-d H:i:s');
        
        foreach ($ventas as $venta) {
            $stmtDetalles = $db_ferias->prepare("SELECT SUM(precio_unitario * cantidad) as total 
                                         FROM detalles_venta 
                                         WHERE venta_id = ?");
            $stmtDetalles->execute([$venta['id']]);
            $total = $stmtDetalles->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            $totalVentas += $total;
            
            if ($venta['tipo_pago'] === 'POS') {
                $totalPos += $total;
            } else {
                $totalEfectivo += $total;
            }
            
            // Marcar venta como cerrada
            $stmtCerrar = $db_ferias->prepare("UPDATE ventas SET cerrada = 1, fecha_cierre = ? WHERE id = ?");
            $stmtCerrar->execute([$fechaCierre, $venta['id']]);
        }
        
        // Registrar cierre
        $stmtCierre = $db_ferias->prepare("INSERT INTO cierres 
                                   (total_ventas, total_pos, total_efectivo) 
                                   VALUES (?, ?, ?)");
        $stmtCierre->execute([$totalVentas, $totalPos, $totalEfectivo]);
        $cierreId = $db_ferias->lastInsertId();
        
        $db_ferias->commit();
        return ['success' => true, 'cierreId' => $cierreId];
    } catch (PDOException $e) {
        $db_ferias->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Otras funciones específicas de ferias...
?>