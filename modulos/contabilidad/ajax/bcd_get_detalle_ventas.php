<?php
// ajax/bcd_get_detalle_ventas.php
// Detalle de ventas para el modal — filas individuales + total correcto por MontoFactura
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

    // ── Query 1: filas de detalle (productos individuales) ──────────────────
    // Mostramos todos los ítems para referencia visual, incluyendo anulados
    $sql = "SELECT
                v.Hora,
                v.CodPedido,
                v.DBBatidos_Nombre,
                v.NombreGrupo,
                v.Precio,
                v.MontoFactura,
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

    // ── Query 2: total correcto usando MontoFactura deduplicado por CodPedido ─
    // Cada CodPedido comparte el mismo MontoFactura en todas sus filas de detalle.
    // DISTINCT CodPedido evita contar el mismo monto varias veces por pedido.
    // Solo se cuentan pedidos no anulados (Anulado = 0).
    $sqlTotal = "SELECT SUM(sub.MontoFactura) AS total_factura,
                        COUNT(DISTINCT sub.CodPedido) AS total_pedidos
                 FROM (
                     SELECT DISTINCT v.CodPedido, v.MontoFactura
                     FROM VentasGlobalesAccessCSV v
                     WHERE v.Fecha     = :fecha
                       AND v.local     = :sucursal
                       AND v.Modalidad = :modalidad
                       AND v.Anulado   = 0";
    if ($hora_final) {
        $sqlTotal .= "   AND v.Hora <= :hora_final";
    }
    $sqlTotal .= " ) sub";

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
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
