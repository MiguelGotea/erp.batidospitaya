<?php
// ajax/agenda_get_tickets_pendientes.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$sucursal = isset($_GET['sucursal']) ? $_GET['sucursal'] : '';

try {
    $sql = "
        SELECT t.*, s.nombre as nombre_sucursal
        FROM mtto_tickets t
        LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
        WHERE (DATE(t.fecha_inicio) IS NULL OR DATE(t.fecha_final) IS NULL)
        AND t.status != 'finalizado'
    ";
    
    $params = [];
    
    if (!empty($sucursal)) {
        $sql .= " AND t.cod_sucursal = ?";
        $params[] = $sucursal;
    }
    
    $sql .= " ORDER BY 
        CASE 
            WHEN t.tipo_formulario = 'cambio_equipos' THEN 1
            WHEN t.tipo_formulario = 'mantenimiento_general' THEN 2
            ELSE 3
        END,
        COALESCE(t.nivel_urgencia, 0) DESC, 
        t.created_at
    ";
    
    $stmt = $db->query($sql, $params);
    $tickets = $stmt->fetchAll();
    
    echo json_encode($tickets, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>