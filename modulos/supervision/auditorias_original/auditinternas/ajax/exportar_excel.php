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
$operario_id     = isset($_GET['operario'])     ? intval($_GET['operario'])    : 0;
$sucursal_id     = isset($_GET['sucursal'])     ? $_GET['sucursal']            : 'todas';
$fecha_desde     = isset($_GET['fecha_desde'])  ? $_GET['fecha_desde']         : date('Y-m-01');
$fecha_hasta     = isset($_GET['fecha_hasta'])  ? $_GET['fecha_hasta']         : date('Y-m-d');
$tipo_seleccionado = isset($_GET['tipo'])       ? $_GET['tipo']                : 'todos';

$tipos_permitidos = ['todos', 'facturacion', 'caja_chica', 'inventario', 'faltante_inventario', 'faltante_danos', 'faltante_caja'];
if (!in_array($tipo_seleccionado, $tipos_permitidos)) {
    $tipo_seleccionado = 'todos';
}

if (!empty($fecha_desde) && !empty($fecha_hasta) && $fecha_desde > $fecha_hasta) {
    $fecha_desde = $fecha_hasta;
}

// Construir consulta base
$sql_excel = "
    SELECT * FROM (
        SELECT id, fecha_hora_regsys AS fecha_hora, sucursal, 'facturacion' AS tipo_auditoria,
               faltante_sobrante AS monto_faltante, 'ver_auditorias_facturacion.php' AS url_ver,
               cajero AS operario_id, sucursal_id
        FROM auditoria_facturacion
        UNION ALL
        SELECT id, fecha_hora_regsys AS fecha_hora, sucursal, 'caja_chica' AS tipo_auditoria,
               faltante_sobrante AS monto_faltante, 'ver_auditorias_caja_chica.php' AS url_ver,
               lider_tienda_codigo AS operario_id, sucursal_id
        FROM auditoria_caja_chica
        UNION ALL
        SELECT ai.id, ai.fecha_hora_regsys AS fecha_hora, ai.sucursal, 'inventario' AS tipo_auditoria,
               ai.total_faltante AS monto_faltante, 'ver_auditorias_inventario.php' AS url_ver,
               NULL AS operario_id, ai.sucursal_id
        FROM auditoria_inventario ai
        UNION ALL
        SELECT fi.id, fi.fecha_hora_regsys AS fecha_hora, fi.sucursal, 'faltante_inventario' AS tipo_auditoria,
               fi.total_faltante AS monto_faltante, 'ver_faltante_inventario.php' AS url_ver,
               NULL AS operario_id, fi.sucursal_id
        FROM faltante_inventario fi
        UNION ALL
        SELECT fd.id, fd.fecha_hora_regsys AS fecha_hora, fd.sucursal_nombre AS sucursal, 'faltante_danos' AS tipo_auditoria,
               fd.valor_faltante AS monto_faltante, 'ver_faltante_danos.php' AS url_ver,
               NULL AS operario_id, fd.sucursal_codigo AS sucursal_id
        FROM faltante_danos fd
        UNION ALL
        SELECT fc.id, fc.fecha AS fecha_hora, fc.sucursal, 'faltante_caja' AS tipo_auditoria,
               fc.monto AS monto_faltante, 'ver_faltante_caja.php' AS url_ver,
               fc.operario_id, fc.sucursal_id
        FROM faltante_caja fc
    ) AS combined_tables
    WHERE 1=1
";

$params = [];

if ($tipo_seleccionado != 'todos') {
    $sql_excel .= " AND tipo_auditoria COLLATE utf8mb4_unicode_ci = :tipo";
    $params[':tipo'] = $tipo_seleccionado;
}

if ($sucursal_id != 'todas') {
    if (is_numeric($sucursal_id)) {
        $sql_excel .= " AND sucursal_id = :sucursal_id";
        $params[':sucursal_id'] = $sucursal_id;
    } else {
        $sql_excel .= " AND sucursal COLLATE utf8mb4_unicode_ci = :sucursal";
        $params[':sucursal'] = $sucursal_id;
    }
}

if ($operario_id > 0) {
    $sql_excel .= " AND operario_id = :operario_id";
    $params[':operario_id'] = $operario_id;
}

if (!empty($fecha_desde)) {
    $sql_excel .= " AND DATE(fecha_hora) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $sql_excel .= " AND DATE(fecha_hora) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$sql_excel .= " ORDER BY fecha_hora DESC";

try {
    $stmt_excel = $db->prepare($sql_excel);
    $stmt_excel->execute($params);
    $registros_excel = $stmt_excel->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la consulta para Excel: " . $e->getMessage());
}

// Cabeceras para descarga Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="auditorias_consolidadas_' . date('Y-m-d') . '.xls"');

echo '<table border="1">';
echo '<tr>';
echo '<th>Fecha</th>';
echo '<th>Código</th>';
echo '<th>Sucursal</th>';
echo '<th>Tipo de Auditoria</th>';
echo '<th>Faltante (C$)</th>';
echo '</tr>';

foreach ($registros_excel as $registro) {
    $fecha_formateada = formatoFecha($registro['fecha_hora']);

    $tipo = $registro['tipo_auditoria'];
    switch ($tipo) {
        case 'facturacion':       $tipo_text = 'Caja Facturacion';      break;
        case 'caja_chica':        $tipo_text = 'Caja Chica';            break;
        case 'inventario':        $tipo_text = 'Auditoría Inventario';  break;
        case 'faltante_inventario': $tipo_text = 'Faltante Inventario'; break;
        case 'faltante_danos':    $tipo_text = 'Faltante Daños';        break;
        case 'faltante_caja':     $tipo_text = 'Faltante de Caja';      break;
        default:                  $tipo_text = $tipo;
    }

    $monto = ($tipo == 'inventario') ? abs($registro['monto_faltante']) : $registro['monto_faltante'];
    $monto_formateado = number_format($monto, 2);
    $codigo_operario  = $registro['operario_id'] ?? '';

    echo '<tr>';
    echo '<td>' . $fecha_formateada . '</td>';
    echo '<td>' . $codigo_operario . '</td>';
    echo '<td>' . $registro['sucursal'] . '</td>';
    echo '<td>' . $tipo_text . '</td>';
    echo '<td>' . $monto_formateado . '</td>';
    echo '</tr>';
}

echo '</table>';
exit;
