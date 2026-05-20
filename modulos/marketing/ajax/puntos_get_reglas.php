<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    // Obtenemos las reglas de acumulación
    // Se cruzan con grupo_presentacion_producto para el nombre del grupo
    // y con producto_presentacion para la excepción si la hay
    $sql = "SELECT 
                r.id, 
                r.id_grupo, 
                g.nombre as grupo_nombre, 
                r.id_producto, 
                p.Nombre as producto_nombre,
                p.SKU as sku,
                r.puntos, 
                r.fecha_desde, 
                r.fecha_hasta,
                CASE 
                    WHEN r.fecha_hasta IS NULL THEN 1
                    WHEN r.fecha_hasta >= CURDATE() THEN 1
                    ELSE 0
                END as es_vigente
            FROM pos_ventas_puntos_reglas r
            JOIN grupo_presentacion_producto g ON r.id_grupo = g.id
            LEFT JOIN producto_presentacion p ON r.id_producto = p.id
            WHERE r.tipo_regla = 'acumulacion'
            ORDER BY 
                g.nombre ASC,
                r.id_producto IS NOT NULL DESC,
                r.fecha_desde DESC";
                
    $stmt = $conn->query($sql);
    $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $reglas]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
?>
