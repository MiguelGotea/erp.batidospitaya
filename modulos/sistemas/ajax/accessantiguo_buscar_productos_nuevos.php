<?php
/**
 * buscar_productos_nuevos.php
 * Autocomplete: busca en producto_presentacion por SKU o Nombre.
 *
 * GET params:
 *   q => texto de búsqueda (mínimo 2 chars)
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $usuario = obtenerUsuarioActual();
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }

    if (!tienePermiso('diccionario_productos', 'vista', $usuario['CodNivelesCargos'])) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso']);
        exit;
    }

    $q = trim($_GET['q'] ?? '');

    if (strlen($q) < 2) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT
            pp.id,
            pp.SKU,
            pp.Nombre,
            pp.cantidad,
            u.Nombre  AS unidad,
            pm.Nombre AS producto_maestro
        FROM producto_presentacion pp
        LEFT JOIN unidad_producto u  ON u.id  = pp.id_unidad_producto
        LEFT JOIN producto_maestro pm ON pm.id = pp.id_producto_maestro
        WHERE pp.Activo = 'SI'
          AND (pp.SKU LIKE :q OR pp.Nombre LIKE :q2 OR pm.Nombre LIKE :q3)
        ORDER BY pp.Nombre ASC
        LIMIT 30
    ");
    $like = "%$q%";
    $stmt->execute([':q' => $like, ':q2' => $like, ':q3' => $like]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $resultados]);

} catch (Exception $e) {
    error_log("Error en buscar_productos_nuevos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en la búsqueda']);
}
?>