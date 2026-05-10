<?php
// ajax/promo_get_productos_precios.php — Devuelve productos para la prueba con metadatos completos (RECALIBRADO), de prueba
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $rawTerm = isset($_POST['term']) ? trim($_POST['term']) : '';
    $term = '%' . $rawTerm . '%';

    // Query robusta:
    // 1. Busca en Nombre del maestro, Nombre de la presentación y SKU.
    // 2. Obtiene el id_grupo real desde la jerarquía de la presentación.
    $sql = "SELECT 
                pp.id, 
                pp.Nombre as nombre_presentacion, 
                pm.Nombre as nombre_maestro,
                pm.id as id_maestro_real,
                pp.SKU, 
                sp.id_grupo_presentacion_producto as id_grupo, 
                pp.id_subgrupo_presentacion_producto as id_subgrupo, 
                100.00 as precio,
                pp.Activo
            FROM producto_presentacion pp
            LEFT JOIN producto_maestro pm ON pp.id_producto_maestro = pm.id
            LEFT JOIN subgrupo_presentacion_producto sp ON pp.id_subgrupo_presentacion_producto = sp.id
            WHERE (pp.Nombre LIKE :term OR pm.Nombre LIKE :term2 OR pp.SKU LIKE :term3)
              AND pp.Activo = 'SI'
            ORDER BY COALESCE(pm.Nombre, pp.Nombre) ASC, pp.Nombre ASC
            LIMIT 30";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':term' => $term,
        ':term2' => $term,
        ':term3' => $term
    ]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mapear resultados para Select2
    $data = array_map(function($p) {
        $nombreMaestro = $p['nombre_maestro'] ?? '';
        $nombrePresent = $p['nombre_presentacion'] ?? '';
        
        $fullName = $nombreMaestro;
        if ($nombrePresent && $nombrePresent !== $nombreMaestro) {
            $fullName = $fullName ? ($fullName . ' — ' . $nombrePresent) : $nombrePresent;
        }
        if (!$fullName) $fullName = 'Producto sin nombre id:' . $p['id'];

        return [
            'id' => $p['id'],
            'text' => $fullName . ' (SKU: ' . $p['SKU'] . ')',
            'Nombre' => $fullName,
            'SKU' => $p['SKU'],
            'id_producto_maestro' => $p['id_maestro_real'], 
            'id_grupo' => $p['id_grupo'],
            'id_subgrupo' => $p['id_subgrupo'],
            'precio' => $p['precio']
        ];
    }, $productos);

    echo json_encode([
        'success' => true,
        'count' => count($data),
        'data' => $data
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
}
?>
