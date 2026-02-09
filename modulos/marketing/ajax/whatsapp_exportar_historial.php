<?php
/**
 * AJAX: Exportar historial a Excel
 */

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');

// Verificar permiso de exportar
require_once('../../../core/permissions/permissions.php');
$codNivelCargo = $_SESSION['cargo_cod'];

if (!tienePermiso('whatsapp_campanas', 'exportar', $codNivelCargo)) {
    die('No tienes permiso para exportar');
}

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? '';

try {
    $sql = "
        SELECT 
            m.id,
            m.telefono,
            m.nombre_cliente,
            c.nombre as campana,
            m.estado,
            m.mensaje,
            m.fecha_envio,
            m.fecha_creacion,
            m.error_mensaje
        FROM whatsapp_mensajes m
        LEFT JOIN whatsapp_campanas c ON m.campana_id = c.id
        WHERE DATE(m.fecha_creacion) BETWEEN ? AND ?
    ";

    $params = [$desde, $hasta];

    if (!empty($estado)) {
        $sql .= " AND m.estado = ?";
        $params[] = $estado;
    }

    $sql .= " ORDER BY m.fecha_creacion DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generar CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="historial_whatsapp_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // BOM para UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Encabezados
    fputcsv($output, ['ID', 'Teléfono', 'Cliente', 'Campaña', 'Estado', 'Fecha Envío', 'Fecha Creación', 'Error']);

    // Datos
    foreach ($mensajes as $m) {
        fputcsv($output, [
            $m['id'],
            $m['telefono'],
            $m['nombre_cliente'],
            $m['campana'] ?? 'Individual',
            $m['estado'],
            $m['fecha_envio'],
            $m['fecha_creacion'],
            $m['error_mensaje']
        ]);
    }

    fclose($output);

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
