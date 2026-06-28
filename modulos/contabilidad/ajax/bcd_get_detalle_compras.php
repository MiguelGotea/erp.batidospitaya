<?php
// ajax/bcd_get_detalle_compras.php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $fecha        = isset($_POST['fecha'])        ? $_POST['fecha'] : null;
    $sucursal     = isset($_POST['sucursal'])     ? $_POST['sucursal'] : null;
    $cod_operario = isset($_POST['cod_operario']) ? (int)$_POST['cod_operario'] : null;
    $cod_cierre   = isset($_POST['cod_cierre'])   ? (int)$_POST['cod_cierre'] : null;
    $hora_final   = isset($_POST['hora_final'])   ? $_POST['hora_final'] : null;

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

    // Obtener la hora inicial del primer cierre del día
    $stmtMin = $conn->prepare("SELECT MIN(HoraInicial) FROM msaccess_masivo_CierreDiario WHERE Fecha = :fecha AND Sucursal = :sucursal");
    $stmtMin->execute(['fecha' => $fecha, 'sucursal' => $sucursal]);
    $minHoraInicial = $stmtMin->fetchColumn();

    $sql = "SELECT
                c.CodIngresoAlmacen,
                c.NumeroFactura,
                c.CodProveedor,
                c.Destino,
                c.Cantidad,
                c.CostoTotal,
                c.Observaciones,
                c.Tipo,
                c.Fecha,
                c.Hora,
                c.CodOperario,
                TRIM(REGEXP_REPLACE(CONCAT_WS(' ',
                    COALESCE(o.Nombre,''),
                    COALESCE(o.Nombre2,''),
                    COALESCE(o.Apellido,''),
                    COALESCE(o.Apellido2,'')), '[ ]+', ' ')) AS cajero
            FROM msaccess_masivo_Compras c
            LEFT JOIN Operarios o ON o.CodOperario = c.CodOperario
            WHERE c.Fecha    = :fecha
              AND c.Sucursal = :sucursal
              AND c.Tipo     = 'CAJA'";

    if (!$esUltimoCierre && $hora_final) {
        $sql .= " AND c.Hora >= :min_hora AND c.Hora <= :hora_final";
    }

    $sql .= " ORDER BY c.CodIngresoAlmacen ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fecha',    $fecha);
    $stmt->bindValue(':sucursal', $sucursal);
    if (!$esUltimoCierre && $hora_final) {
        $stmt->bindValue(':min_hora', $minHoraInicial);
        $stmt->bindValue(':hora_final', $hora_final);
    }
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'datos' => $datos]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
