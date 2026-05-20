<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id_producto = $_GET['id_producto'] ?? null;
    
    if (!$id_producto) {
        throw new Exception("ID de producto no proporcionado");
    }

    $sql = "SELECT cod_sucursal, precio, fecha_desde, fecha_hasta, fecha_hora_reg 
            FROM pos_ventas_precios_producto 
            WHERE id_producto_presentacion = :id_producto 
            ORDER BY 
                (cod_sucursal IS NULL) DESC, -- Primero globales
                cod_sucursal ASC,            -- Luego por sucursal
                fecha_desde DESC             -- Y finalmente del más reciente al más antiguo
            ";
            
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id_producto', $id_producto);
    $stmt->execute();
    
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $historial]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
