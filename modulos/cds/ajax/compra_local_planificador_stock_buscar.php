<?php
// compra_local_planificador_stock_buscar.php
// Búsqueda de productos para el planificador de stock (sin filtros de sucursal)

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $search_term = $_GET['search'] ?? '';

    if (strlen($search_term) < 2) {
        echo json_encode([
            'success' => true,
            'productos' => []
        ]);
        exit;
    }

    // Buscar productos activos
    $sql = "SELECT pp.id, pp.Nombre, pp.SKU 
            FROM producto_presentacion pp
            WHERE pp.Activo = 'SI'
            AND (pp.Nombre LIKE ? OR pp.SKU LIKE ?)
            ORDER BY pp.Nombre
            LIMIT 20";

    $search_param = "%$search_term%";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$search_param, $search_param]);
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
