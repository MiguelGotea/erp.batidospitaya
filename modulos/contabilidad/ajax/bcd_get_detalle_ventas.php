<?php
// ajax/bcd_get_detalle_ventas.php
// Detalle de ventas para el modal — agrupado por pedido, con todos los campos del historial
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

    $modalidades_validas = ['POS', 'TRANSFERENCIA', 'PEDIDOSYA', 'EFECTIVO'];
    if (!in_array($modalidad, $modalidades_validas)) {
        echo json_encode(['success' => false, 'message' => 'Modalidad no válida.']);
        exit;
    }

    // ── Query 1: filas agrupadas por pedido (estilo historial_ventas por_pedido) ─
    $condHora = $hora_final ? "AND v.Hora <= :hora_final" : "";

    $sql = "SELECT
                MAX(v.Sucursal_Nombre)  AS Sucursal_Nombre,
                v.CodPedido,
                v.local,
                MAX(v.Fecha)            AS Fecha,
                MIN(v.Hora)             AS Hora,
                MAX(v.CodCliente)       AS CodCliente,
                CASE
                    WHEN MAX(v.CodCliente) > 0
                    THEN CONCAT(MAX(c.nombre), ' ', MAX(c.apellido))
                    ELSE ''
                END                     AS NombreCliente,
                SUM(v.Puntos)           AS Puntos,
                MAX(v.Caja)             AS Caja,
                MAX(v.MontoFactura)     AS MontoFactura,
                MAX(v.Modalidad)        AS Modalidad,
                MAX(v.Anulado)          AS Anulado
            FROM VentasGlobalesAccessCSV v
            LEFT JOIN clientesclub c ON v.CodCliente = c.membresia
            WHERE v.Fecha     = :fecha
              AND v.local     = :sucursal
              AND v.Modalidad = :modalidad
              $condHora
            GROUP BY v.CodPedido, v.local
            ORDER BY MIN(v.Hora) ASC, v.CodPedido ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fecha',     $fecha);
    $stmt->bindValue(':sucursal',  $sucursal);
    $stmt->bindValue(':modalidad', $modalidad);
    if ($hora_final) {
        $stmt->bindValue(':hora_final', $hora_final);
    }
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Query 2: total correcto usando MontoFactura deduplicado por CodPedido ──
    $sqlTotal = "SELECT SUM(sub.MontoFactura) AS total_factura,
                        COUNT(DISTINCT sub.CodPedido) AS total_pedidos
                 FROM (
                     SELECT DISTINCT v.CodPedido, v.MontoFactura
                     FROM VentasGlobalesAccessCSV v
                     WHERE v.Fecha     = :fecha
                       AND v.local     = :sucursal
                       AND v.Modalidad = :modalidad
                       AND v.Anulado   = 0
                       $condHora
                 ) sub";

    $stmtTotal = $conn->prepare($sqlTotal);
    $stmtTotal->bindValue(':fecha',     $fecha);
    $stmtTotal->bindValue(':sucursal',  $sucursal);
    $stmtTotal->bindValue(':modalidad', $modalidad);
    if ($hora_final) {
        $stmtTotal->bindValue(':hora_final', $hora_final);
    }
    $stmtTotal->execute();
    $rowTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'datos'          => $datos,
        'total_factura'  => (float)($rowTotal['total_factura']  ?? 0),
        'total_pedidos'  => (int)  ($rowTotal['total_pedidos']  ?? 0),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
