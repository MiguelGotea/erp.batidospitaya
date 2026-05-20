<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    // Primero obtenemos todos los productos vendibles
    $sqlProductos = "SELECT id, SKU as sku, Nombre as nombre_producto 
                     FROM producto_presentacion 
                     WHERE es_vendible = 'SI' 
                     ORDER BY Nombre ASC";
    $stmtProductos = $conn->query($sqlProductos);
    $productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

    // Luego obtenemos los precios vigentes
    $sqlPrecios = "SELECT p.id_producto_presentacion, p.cod_sucursal, p.precio, p.fecha_desde 
                   FROM pos_ventas_precios_producto p
                   WHERE p.fecha_desde <= CURDATE() 
                     AND (p.fecha_hasta IS NULL OR p.fecha_hasta >= CURDATE())
                   ORDER BY p.fecha_desde DESC"; // Para que agarre el más reciente si hay traslape
    $stmtPrecios = $conn->query($sqlPrecios);
    $preciosVigentes = $stmtPrecios->fetchAll(PDO::FETCH_ASSOC);

    // Mapear los precios a los productos
    $mapaPrecios = [];
    foreach ($preciosVigentes as $precio) {
        $idProd = $precio['id_producto_presentacion'];
        if (!isset($mapaPrecios[$idProd])) {
            $mapaPrecios[$idProd] = ['global' => null, 'sucursales' => []];
        }
        
        if (empty($precio['cod_sucursal'])) {
            // Precio global
            if ($mapaPrecios[$idProd]['global'] === null) {
                $mapaPrecios[$idProd]['global'] = $precio;
            }
        } else {
            // Precio por sucursal
            $mapaPrecios[$idProd]['sucursales'][] = $precio;
        }
    }

    $datos = [];
    foreach ($productos as $prod) {
        $id = $prod['id'];
        $preciosData = $mapaPrecios[$id] ?? ['global' => null, 'sucursales' => []];
        
        $datos[] = [
            'id' => $id,
            'sku' => $prod['sku'],
            'nombre_producto' => $prod['nombre_producto'],
            'precio_global' => $preciosData['global'] ? $preciosData['global']['precio'] : null,
            'fecha_desde' => $preciosData['global'] ? $preciosData['global']['fecha_desde'] : null,
            'overrides' => $preciosData['sucursales']
        ];
    }

    echo json_encode(['success' => true, 'data' => $datos]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de Base de Datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
