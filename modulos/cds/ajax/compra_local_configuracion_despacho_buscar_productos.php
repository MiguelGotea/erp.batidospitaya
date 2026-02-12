<?php
// compra_local_configuracion_despacho_buscar_productos.php
// Búsqueda de productos con autocomplete

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $search_term = $_GET['search'] ?? '';
    $codigo_sucursal = $_GET['codigo_sucursal'] ?? '';

    if (empty($codigo_sucursal)) {
        throw new Exception('Código de sucursal requerido');
    }

    if (strlen($search_term) < 2) {
        echo json_encode([
            'success' => true,
            'productos' => []
        ]);
        exit;
    }

    // Buscar productos activos que no estén ya configurados para esta sucursal
    $sql = "SELECT pp.id, pp.Nombre, pp.SKU 
            FROM producto_presentacion pp
            WHERE pp.Activo = 'SI'
            AND (pp.Nombre LIKE ? OR pp.SKU LIKE ?)
            AND pp.id NOT IN (
                SELECT DISTINCT id_producto_presentacion 
                FROM compra_local_configuracion_despacho 
                WHERE codigo_sucursal = ? AND status = 'activo'
            )
            ORDER BY pp.Nombre
            LIMIT 20";

    $search_param = "%$search_term%";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$search_param, $search_param, $codigo_sucursal]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'productos' => $productos
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
