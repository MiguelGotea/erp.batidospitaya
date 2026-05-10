<?php
// facturas_get_detalle.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) throw new Exception('ID de factura inválido.');

    // Cabecera
    $sqlCab = "SELECT f.*,
                      p.nombre  AS nombre_proveedor,
                      p.ruc_nit AS proveedor_ruc,
                      CONCAT(o.Nombre, ' ', o.Apellido) AS registrado_por_nombre
               FROM pos_facturas f
               LEFT JOIN proveedores p ON f.id_proveedor = p.id
               LEFT JOIN Operarios o ON f.registrado_por = o.CodOperario
               WHERE f.id = :id";
    $stmtCab = $conn->prepare($sqlCab);
    $stmtCab->execute([':id' => $id]);
    $factura = $stmtCab->fetch(PDO::FETCH_ASSOC);

    if (!$factura) throw new Exception('Factura no encontrada.');

    // Detalle
    $sqlDet = "SELECT fd.*,
                      pp.Nombre    AS nombre_presentacion,
                      pm.Nombre    AS nombre_maestro,
                      up.nombre    AS nombre_unidad
               FROM pos_facturas_detalle fd
               INNER JOIN producto_presentacion pp ON fd.id_presentacion = pp.id
               INNER JOIN producto_maestro pm ON pp.id_producto_maestro = pm.id
               LEFT JOIN unidad_producto up ON pp.id_unidad_producto = up.id
               WHERE fd.id_factura = :id
               ORDER BY fd.id ASC";
    $stmtDet = $conn->prepare($sqlDet);
    $stmtDet->execute([':id' => $id]);
    $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'factura' => $factura,
        'detalle' => $detalle
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
