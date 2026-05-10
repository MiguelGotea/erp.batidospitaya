<?php
// ajax/promo_get_productos.php — Devuelve productos para Select2 (búsqueda en tiempo real)
// Retorna formato Select2: { success, data: [{id, text}] }
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $term = isset($_POST['term']) ? trim($_POST['term']) : '';

    $sql = "SELECT
                pp.id,
                CONCAT(pm.Nombre, ' — ', pp.Nombre) AS text
            FROM producto_presentacion pp
            INNER JOIN producto_maestro pm ON pp.id_producto_maestro = pm.id
            WHERE pp.Activo = 'SI'
              AND (pm.Nombre LIKE :term OR pp.Nombre LIKE :term2 OR pp.SKU LIKE :term3)
            ORDER BY pm.Nombre, pp.Nombre
            LIMIT 40";

    $stmt = $conn->prepare($sql);
    $like = '%' . $term . '%';
    $stmt->bindValue(':term',  $like);
    $stmt->bindValue(':term2', $like);
    $stmt->bindValue(':term3', $like);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
}
?>
