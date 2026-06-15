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

    // Obtener el id interno de la sucursal a partir del codigo
    $stmtSuc = $conn->prepare("SELECT id FROM sucursales WHERE codigo = :codigo LIMIT 1");
    $stmtSuc->bindValue(':codigo', $sucursal);
    $stmtSuc->execute();
    $rowSuc = $stmtSuc->fetch(PDO::FETCH_ASSOC);

    if (!$rowSuc) {
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada.']);
        exit;
    }
    $sucursal_id = $rowSuc['id'];

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
            WHERE c.Fecha     = :fecha
              AND c.Sucursal  = :sucursal_id
              AND c.Tipo      = 'CAJA'
            ORDER BY c.CodIngresoAlmacen ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fecha',       $fecha);
    $stmt->bindValue(':sucursal_id', $sucursal_id, PDO::PARAM_INT);
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'datos' => $datos]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
