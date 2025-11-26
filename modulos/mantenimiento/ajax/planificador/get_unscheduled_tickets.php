<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Ticket.php';

header('Content-Type: application/json');

try {
    global $db;
    $ticket = new Ticket();
    
    // Obtener tickets sin programar con información completa
    $sql = "SELECT t.id,
            t.codigo,
            t.titulo,
            t.descripcion,
            t.tipo_formulario,
            t.cod_operario,
            t.cod_sucursal,
            t.nivel_urgencia,
            s.nombre as nombre_sucursal
            FROM mtto_tickets t
            LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
            WHERE (t.fecha_inicio IS NULL OR t.fecha_final IS NULL)
            AND t.status != 'finalizado'
            ORDER BY 
                CASE 
                    WHEN t.tipo_formulario = 'cambio_equipos' THEN 1
                    WHEN t.tipo_formulario = 'mantenimiento_general' THEN 2
                    ELSE 3
                END,
                COALESCE(t.nivel_urgencia, 0) DESC, 
                t.created_at";
    
    $tickets = $db->fetchAll($sql);
    
    // Obtener sucursales
    $sucursales = $ticket->getSucursales();
    
    echo json_encode([
        'tickets' => $tickets,
        'sucursales' => $sucursales
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>