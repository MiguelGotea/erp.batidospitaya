<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT 
                c.id, 
                c.nombre, 
                c.id_producto_canjeable, 
                p.Nombre as producto_nombre,
                c.puntos_requeridos, 
                c.activo, 
                c.orden
            FROM pos_ventas_puntos_catalogo_canje c
            LEFT JOIN producto_presentacion p ON c.id_producto_canjeable = p.id
            ORDER BY c.orden ASC, c.nombre ASC";
                
    $stmt = $conn->query($sql);
    $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $catalogo]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
?>
