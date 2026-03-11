<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

// Sincronizar zona horaria de MySQL para este script
$conn->query("SET time_zone = '-06:00'");

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// El permiso 'descargar' es necesario para esta acción
if (!tienePermiso('dashboard_rfm', 'descargar', $cargoOperario)) {
    exit('No tiene permiso para descargar reportes.');
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-90 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$sucursal = $_GET['sucursal'] ?? 'todas';

try {
    $whereSimple = "WHERE Anulado = 0 AND Fecha BETWEEN :f_inicio AND :f_fin";
    $params = [':f_inicio' => $fecha_inicio, ':f_fin' => $fecha_fin];

    if ($sucursal && $sucursal !== 'todas') {
        $whereSimple .= " AND Sucursal_Nombre = :sucursal";
        $params[':sucursal'] = $sucursal;
    }

    $sqlRFM = "
        WITH ResumenPedidos AS (
            SELECT CodCliente, CodPedido, MAX(Fecha) as Fecha, MAX(MontoFactura) as TotalPedido, MAX(Sucursal_Nombre) as Sucursal
            FROM VentasGlobalesAccessCSV $whereSimple GROUP BY CodPedido
        )
        SELECT 
            r.CodCliente, MAX(r.Sucursal) as Sucursal, DATEDIFF(CURDATE(), MAX(r.Fecha)) as Recency,
            COUNT(r.CodPedido) as Frequency, SUM(r.TotalPedido) as Monetary,
            MAX(CONCAT(COALESCE(c.nombre,''), ' ', COALESCE(c.apellido, ''))) as ClienteNombre
        FROM ResumenPedidos r
        LEFT JOIN clientesclub c ON r.CodCliente = c.membresia
        GROUP BY r.CodCliente
    ";

    $stmt = $conn->prepare($sqlRFM);
    $stmt->execute($params);
    $raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular Quintiles para consistencia
    $recencies = array_column($raw_data, 'Recency');
    $frequencies = array_column($raw_data, 'Frequency');
    $monetaries = array_column($raw_data, 'Monetary');
    sort($recencies); sort($frequencies); sort($monetaries);
    $total_count = count($raw_data);

    $get_q = function($val, $arr, $inv = false) use ($total_count) {
        $pos = array_search($val, $arr); $p = $pos / max(1, $total_count);
        if ($inv) $p = 1 - $p;
        if ($p <= 0.2) return 1; if ($p <= 0.4) return 2;
        if ($p <= 0.6) return 3; if ($p <= 0.8) return 4;
        return 5;
    };

    $filename_date = date('Ymd_His');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=RFM_Maestro_' . $filename_date . '.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

    fputcsv($output, ['ID Cliente', 'Cliente', 'Sucursal', 'Recencia (d)', 'Frecuencia', 'Monetario ($)', 'Score R', 'Score F', 'Score M', 'Score Total', 'Segmento']);

    foreach ($raw_data as $row) {
        $r_score = $get_q($row['Recency'], $recencies, true);
        $f_score = $get_q($row['Frequency'], $frequencies);
        $m_score = $get_q($row['Monetary'], $monetaries);
        $total_score = $r_score + $f_score + $m_score;

        if ($r_score >= 4 && $f_score >= 4) $seg = 'Campeón';
        elseif ($r_score >= 3 && $f_score >= 3) $seg = 'Leal';
        elseif ($r_score <= 2 && $f_score >= 3) $seg = 'En Riesgo';
        elseif ($r_score <= 2 && $f_score <= 2) $seg = 'Perdido';
        elseif ($r_score >= 4 && $f_score <= 2) $seg = 'Nuevo';
        else $seg = 'Hibernando';

        fputcsv($output, [
            $row['CodCliente'],
            $row['ClienteNombre'] ?: 'Anónimo',
            $row['Sucursal'] ?: 'Desconocida',
            $row['Recency'],
            $row['Frequency'],
            number_format($row['Monetary'], 2, '.', ''),
            $r_score,
            $f_score,
            $m_score,
            $total_score,
            $seg
        ]);
    }

    fclose($output);

} catch (Exception $e) {
    exit($e->getMessage());
}
