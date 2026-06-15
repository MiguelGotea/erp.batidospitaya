<?php
// ajax/bcd_get_detalle_compras.php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $fecha    = isset($_POST['fecha'])    ? $_POST['fecha']    : null;
    $sucursal = isset($_POST['sucursal']) ? $_POST['sucursal'] : null;

    if (!$fecha || !$sucursal) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos.']);
        exit;
    }

    // msaccess_masivo_Compras.Sucursal usa el mismo valor que sucursales.codigo.
    // Se filtra directo sin pasar por sucursales.id (NUNCA usar sucursales.id).

    $sql = "SELECT
                c.CodIngresoAlmacen,
                c.NumeroFactura,
                c.CodProveedor,
                c.Destino,
                c.Cantidad,
                c.CostoTotal,
                c.Observaciones,
                c.Tipo,
                c.Fecha
            FROM msaccess_masivo_Compras c
            WHERE c.Fecha    = :fecha
              AND c.Sucursal = :sucursal
              AND c.Tipo     = 'CAJA'
            ORDER BY c.CodIngresoAlmacen ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fecha',    $fecha);
    $stmt->bindValue(':sucursal', $sucursal);
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'datos' => $datos]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
