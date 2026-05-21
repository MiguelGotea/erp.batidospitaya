<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';

$db = $conn;

if (!verificarAccesoCargo([8, 11, 16, 21, 49])) {
    header('Location: /index.php');
    exit();
}

// Obtener parámetros de los filtros
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

if (!empty($fecha_desde) && !empty($fecha_hasta) && $fecha_desde > $fecha_hasta) {
    $fecha_desde = $fecha_hasta;
}

try {
    $sql_contabilidad = "
        (SELECT 
            af.cajero AS operario_id,
            CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
            af.fecha_deduccion,
            SUM(ABS(af.faltante_sobrante)) AS monto_total,
            'facturacion' AS tipo
        FROM auditoria_facturacion af
        JOIN Operarios o ON af.cajero = o.CodOperario
        WHERE DATE(af.fecha_hora_regsys) BETWEEN :fecha_desde1 AND :fecha_hasta1
        AND af.faltante_sobrante != 0
        GROUP BY operario_id, operario_nombre, af.fecha_deduccion)
        
        UNION ALL
        
        (SELECT 
            acc.lider_tienda_codigo AS operario_id,
            CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
            acc.fecha_deduccion,
            SUM(ABS(acc.faltante_sobrante)) AS monto_total,
            'caja_chica' AS tipo
        FROM auditoria_caja_chica acc
        JOIN Operarios o ON acc.lider_tienda_codigo = o.CodOperario
        WHERE DATE(acc.fecha_hora_regsys) BETWEEN :fecha_desde2 AND :fecha_hasta2
        AND acc.faltante_sobrante != 0
        GROUP BY operario_id, operario_nombre, acc.fecha_deduccion)
        
        UNION ALL
        
        (SELECT 
            aio.operario_id AS operario_id,
            CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
            aio.fecha_deduccion,
            SUM(ABS(aio.monto)) AS monto_total,
            'inventario' AS tipo
        FROM auditoria_inventario ai
        JOIN auditoria_inventario_operarios aio ON ai.id = aio.auditoria_id
        JOIN Operarios o ON aio.operario_id = o.CodOperario
        WHERE DATE(ai.fecha_hora_regsys) BETWEEN :fecha_desde3 AND :fecha_hasta3
        AND aio.monto != 0
        GROUP BY operario_id, operario_nombre, aio.fecha_deduccion)
        
        UNION ALL
        
        (SELECT 
            fio.operario_id AS operario_id,
            CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
            fio.fecha_deduccion,
            SUM(ABS(fio.monto)) AS monto_total,
            'faltante_inventario' AS tipo
        FROM faltante_inventario fi
        JOIN faltante_inventario_operarios fio ON fi.id = fio.faltante_id
        JOIN Operarios o ON fio.operario_id = o.CodOperario
        WHERE DATE(fi.fecha_hora_regsys) BETWEEN :fecha_desde4 AND :fecha_hasta4
        AND fio.monto != 0
        GROUP BY operario_id, operario_nombre, fio.fecha_deduccion)
        
        UNION ALL
        
        (SELECT 
            fdo.operario_id AS operario_id,
            CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
            fdo.fecha_deduccion,
            SUM(ABS(fdo.monto)) AS monto_total,
            'faltante_danos' AS tipo
        FROM faltante_danos fd
        JOIN faltante_danos_operarios fdo ON fd.id = fdo.faltante_id
        JOIN Operarios o ON fdo.operario_id = o.CodOperario
        WHERE DATE(fd.fecha_hora_regsys) BETWEEN :fecha_desde5 AND :fecha_hasta5
        AND fdo.monto != 0
        GROUP BY operario_id, operario_nombre, fdo.fecha_deduccion)
        
        ORDER BY operario_nombre, fecha_deduccion
    ";

    $stmt_contabilidad = $db->prepare($sql_contabilidad);
    $stmt_contabilidad->bindValue(':fecha_desde1', $fecha_desde);
    $stmt_contabilidad->bindValue(':fecha_hasta1', $fecha_hasta);
    $stmt_contabilidad->bindValue(':fecha_desde2', $fecha_desde);
    $stmt_contabilidad->bindValue(':fecha_hasta2', $fecha_hasta);
    $stmt_contabilidad->bindValue(':fecha_desde3', $fecha_desde);
    $stmt_contabilidad->bindValue(':fecha_hasta3', $fecha_hasta);
    $stmt_contabilidad->bindValue(':fecha_desde4', $fecha_desde);
    $stmt_contabilidad->bindValue(':fecha_hasta4', $fecha_hasta);
    $stmt_contabilidad->bindValue(':fecha_desde5', $fecha_desde);
    $stmt_contabilidad->bindValue(':fecha_hasta5', $fecha_hasta);
    $stmt_contabilidad->execute();
    $deducciones_contabilidad = $stmt_contabilidad->fetchAll(PDO::FETCH_ASSOC);

    // Cabeceras para descarga Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="deducciones_contabilidad_' . date('Y-m-d') . '.xls"');

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Colaborador</th>';
    echo '<th>Código</th>';
    echo '<th>Fecha a Deducir</th>';
    echo '<th>Monto Total (C$)</th>';
    echo '<th>Tipo de Deducción</th>';
    echo '</tr>';

    foreach ($deducciones_contabilidad as $deduccion) {
        $fecha_deduccion = !empty($deduccion['fecha_deduccion']) ? formatoFecha($deduccion['fecha_deduccion']) : '';

        $tipo = $deduccion['tipo'];
        switch ($tipo) {
            case 'facturacion':       $tipo_text = 'Caja Facturación';    break;
            case 'caja_chica':        $tipo_text = 'Caja Chica';          break;
            case 'inventario':        $tipo_text = 'Auditoría Inventario'; break;
            case 'faltante_inventario': $tipo_text = 'Faltante Inventario'; break;
            case 'faltante_danos':    $tipo_text = 'Faltante Daños';      break;
            default:                  $tipo_text = $tipo;
        }

        echo '<tr>';
        echo '<td>' . $deduccion['operario_nombre'] . '</td>';
        echo '<td>' . $deduccion['operario_id'] . '</td>';
        echo '<td>' . $fecha_deduccion . '</td>';
        echo '<td>' . number_format($deduccion['monto_total'], 2) . '</td>';
        echo '<td>' . $tipo_text . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    exit;

} catch (PDOException $e) {
    die("Error en la consulta para contabilidad: " . $e->getMessage());
}
