<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('dashboard_rfm', 'vista', $cargoOperario)) {
    exit('No tiene permiso');
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-90 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$sucursal = $_GET['sucursal'] ?? null;

try {
    $where = "WHERE Anulado = 0 AND CodCliente > 0 AND Fecha BETWEEN :f_inicio AND :f_fin";
    $params = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];
    if ($sucursal) {
        $where .= " AND Sucursal_Nombre = :sucursal";
        $params[':sucursal'] = $sucursal;
    }

    $sql = "
        WITH ResumenPedidos AS (
            SELECT CodCliente, CodPedido, MAX(Fecha) as Fecha, MAX(MontoFactura) as TotalPedido
            FROM VentasGlobalesAccessCSV $where GROUP BY CodPedido
        ),
        RFM_Raw AS (
            SELECT 
                r.CodCliente,
                DATEDIFF(CURDATE(), MAX(r.Fecha)) as Recency,
                COUNT(r.CodPedido) as Frequency,
                SUM(r.TotalPedido) as Monetary,
                MAX(CONCAT(COALESCE(c.nombre,''), ' ', COALESCE(c.apellido, ''))) as ClienteNombre
            FROM ResumenPedidos r
            LEFT JOIN clientesclub c ON r.CodCliente = c.membresia
            GROUP BY r.CodCliente
        )
        SELECT * FROM RFM_Raw ORDER BY Monetary DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename_date = date('Ymd_His');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=RFM_Export_' . $filename_date . '.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    fputcsv($output, ['ID Cliente', 'Nombre', 'Recencia (Días)', 'Frecuencia (Visitas)', 'Monetario (LTV)']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['CodCliente'],
            $row['ClienteNombre'],
            $row['Recency'],
            $row['Frequency'],
            number_format($row['Monetary'], 2, '.', '')
        ]);
    }

    fclose($output);

} catch (Exception $e) {
    exit($e->getMessage());
}
