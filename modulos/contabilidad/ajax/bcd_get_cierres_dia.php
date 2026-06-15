<?php
// ajax/bcd_get_cierres_dia.php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $fecha    = isset($_POST['fecha'])    ? $_POST['fecha']    : null;
    $sucursal = isset($_POST['sucursal']) ? $_POST['sucursal'] : null;

    if (!$fecha || !$sucursal) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos.']);
        exit;
    }

    // cd.Sucursal almacena el mismo valor numérico que sucursales.codigo
    // Se filtra directo usando el codigo recibido; MySQL hace cast implícito varchar→int
    $sql = "SELECT
                cd.CodigoCierre,
                cd.HoraInicial,
                cd.HoraFinal,
                cd.CodOperario,
                cd.MFCor,
                cd.MFDol,
                cd.TotalPOS,
                cd.TotalTransferencia,
                cd.TotalPedidosYa,
                cd.Faltante,
                cd.TotalHugo,
                cd.Observaciones,
                cd.Fecha
            FROM msaccess_masivo_CierreDiario cd
            WHERE cd.Fecha     = :fecha
              AND cd.Sucursal  = :sucursal
            ORDER BY cd.HoraInicial ASC";

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
