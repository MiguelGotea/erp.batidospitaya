<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';

$db = $conn;

if (!verificarAccesoCargo([8, 11, 16, 21])) {
    header('Location: /index.php');
    exit();
}

// Obtener parámetros de los filtros
$operario_id = isset($_GET['operario'])    ? intval($_GET['operario'])  : 0;
$sucursal_id = isset($_GET['sucursal'])    ? $_GET['sucursal']          : 'todas';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde']       : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta']       : date('Y-m-d');

if (!empty($fecha_desde) && !empty($fecha_hasta) && $fecha_desde > $fecha_hasta) {
    $fecha_desde = $fecha_hasta;
}

$sql_export = "
    SELECT 
        fc.id,
        fc.fecha AS fecha_hora,
        fc.fecha_deduccion,
        fc.sucursal_id,
        s.nombre AS sucursal_nombre,
        fc.operario_id,
        fc.operario_nombre,
        fc.comentarios,
        fc.monto
    FROM faltante_caja fc
    JOIN sucursales s ON fc.sucursal_id = s.codigo
    WHERE 1=1
";

$params_export = [];

if ($operario_id > 0) {
    $sql_export .= " AND fc.operario_id = ?";
    $params_export[] = $operario_id;
}

if ($sucursal_id != 'todas') {
    $sql_export .= " AND fc.sucursal_id = ?";
    $params_export[] = $sucursal_id;
}

if (!empty($fecha_desde)) {
    $sql_export .= " AND DATE(fc.fecha) >= ?";
    $params_export[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $sql_export .= " AND DATE(fc.fecha) <= ?";
    $params_export[] = $fecha_hasta;
}

$sql_export .= " ORDER BY fc.fecha DESC";

$stmt_export = $db->prepare($sql_export);
$stmt_export->execute($params_export);
$registros_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

// Cabeceras para descarga Excel — incluyendo rango de fechas en nombre de archivo
$nombre_archivo = "faltantes_caja_" . str_replace('-', '', $fecha_desde) . "_" . str_replace('-', '', $fecha_hasta) . ".xls";
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

echo '<table border="1">';
echo '<tr>';
echo '<th>Fecha</th>';
echo '<th>Colaborador</th>';
echo '<th>Código</th>';
echo '<th>Sucursal</th>';
echo '<th>Monto (C$)</th>';
echo '<th>Comentarios</th>';
echo '</tr>';

foreach ($registros_export as $registro) {
    $fecha_formateada = formatoFecha($registro['fecha_hora']);

    echo '<tr>';
    echo '<td>' . $fecha_formateada . '</td>';
    echo '<td>' . $registro['operario_nombre'] . '</td>';
    echo '<td>' . $registro['operario_id'] . '</td>';
    echo '<td>' . $registro['sucursal_nombre'] . '</td>';
    echo '<td>' . number_format(abs($registro['monto']), 2) . '</td>';
    echo '<td>' . htmlspecialchars($registro['comentarios'] ?? '') . '</td>';
    echo '</tr>';
}

echo '</table>';
exit;
