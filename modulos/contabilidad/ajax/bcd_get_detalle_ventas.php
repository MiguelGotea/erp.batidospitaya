<?php
// ajax/bcd_get_detalle_ventas.php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $fecha      = isset($_POST['fecha'])      ? $_POST['fecha']      : null;
    $sucursal   = isset($_POST['sucursal'])   ? $_POST['sucursal']   : null;
    $modalidad  = isset($_POST['modalidad'])  ? $_POST['modalidad']  : null;
    $hora_final = isset($_POST['hora_final']) ? $_POST['hora_final'] : null;

    if (!$fecha || !$sucursal || !$modalidad) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos.']);
        exit;
    }

    // Mapeo de modalidad del JS al valor real en la tabla
    $modalidades_validas = ['POS', 'TRANSFERENCIA', 'PEDIDOSYA', 'EFECTIVO'];
    if (!in_array($modalidad, $modalidades_validas)) {
        echo json_encode(['success' => false, 'message' => 'Modalidad no válida.']);
        exit;
    }

    $sql = "SELECT
                v.Hora,
                v.CodPedido,
                v.DBBatidos_Nombre,
                v.NombreGrupo,
                v.Precio,
                v.Anulado,
                v.Modalidad,
                v.Medida,
                v.Cantidad
            FROM VentasGlobalesAccessCSV v
            WHERE v.Fecha     = :fecha
              AND v.local     = :sucursal
              AND v.Modalidad = :modalidad";

    if ($hora_final) {
        $sql .= " AND v.Hora <= :hora_final";
    }

    $sql .= " ORDER BY v.Hora ASC, v.CodPedido ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fecha',     $fecha);
    $stmt->bindValue(':sucursal',  $sucursal);
    $stmt->bindValue(':modalidad', $modalidad);
    if ($hora_final) {
        $stmt->bindValue(':hora_final', $hora_final);
    }
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'datos' => $datos]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
