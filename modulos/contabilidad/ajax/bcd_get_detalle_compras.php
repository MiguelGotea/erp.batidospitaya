<?php
// ajax/bcd_get_detalle_compras.php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $fecha        = isset($_POST['fecha'])        ? $_POST['fecha'] : null;
    $sucursal     = isset($_POST['sucursal'])     ? $_POST['sucursal'] : null;
    $cod_operario = isset($_POST['cod_operario']) ? (int)$_POST['cod_operario'] : null;
    $cod_cierre   = isset($_POST['cod_cierre'])   ? (int)$_POST['cod_cierre'] : null;

    if (!$fecha || !$sucursal || !$cod_operario) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos.']);
        exit;
    }

    // msaccess_masivo_Compras.Sucursal usa el mismo valor que sucursales.codigo.
    // Se filtra directo sin pasar por sucursales.id (NUNCA usar sucursales.id).

    // Determinar si este es el último cierre del día para aplicar lógica de facturas "huérfanas"
    $stmtMaxCierre = $conn->prepare("SELECT MAX(CodigoCierre) FROM msaccess_masivo_CierreDiario WHERE Fecha = :fecha AND Sucursal = :sucursal");
    $stmtMaxCierre->execute(['fecha' => $fecha, 'sucursal' => $sucursal]);
    $maxCodigoCierre = $stmtMaxCierre->fetchColumn();
    $esUltimoCierre = ($cod_cierre == $maxCodigoCierre);

    // Obtener los CodOperario de todos los cierres del día HASTA el actual
    $stmtOps = $conn->prepare("SELECT DISTINCT CodOperario FROM msaccess_masivo_CierreDiario WHERE Fecha = :fecha AND Sucursal = :sucursal AND CodigoCierre <= :cod_cierre");
    $stmtOps->execute(['fecha' => $fecha, 'sucursal' => $sucursal, 'cod_cierre' => $cod_cierre]);
    $operarios = $stmtOps->fetchAll(PDO::FETCH_COLUMN);
    $operarios_in = empty($operarios) ? "0" : implode(',', array_map('intval', array_filter($operarios)));

    $condicionOperario = " AND (c.CodOperario IN ($operarios_in)";
    if ($esUltimoCierre) {
        $condicionOperario .= " OR c.CodOperario IS NULL OR c.CodOperario = '' OR c.CodOperario NOT IN (SELECT CodOperario FROM msaccess_masivo_CierreDiario WHERE Fecha = :fecha2 AND Sucursal = :sucursal2)";
    }
    $condicionOperario .= ")";

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
              $condicionOperario
            ORDER BY c.CodIngresoAlmacen ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fecha',    $fecha);
    $stmt->bindValue(':sucursal', $sucursal);
    if ($esUltimoCierre) {
        $stmt->bindValue(':fecha2',    $fecha);
        $stmt->bindValue(':sucursal2', $sucursal);
    }
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'datos' => $datos]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
