<?php
// public_html/modulos/mantenimiento/ajax/equipos_calendario_agendados.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $mes = $input['mes'] ?? date('n');
    $anio = $input['anio'] ?? date('Y');
    
    $primerDia = "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01";
    $ultimoDia = date("Y-m-t", strtotime($primerDia));
    
    $mantenimientos = $db->fetchAll("
        SELECT 
            mt.equipo_id,
            e.codigo,
            e.nombre,
            mt.fecha_programada as fecha
        FROM mtto_equipos_mantenimientos mt
        INNER JOIN mtto_equipos e ON mt.equipo_id = e.id
        WHERE mt.fecha_programada BETWEEN :primer_dia AND :ultimo_dia
            AND mt.estado IN ('Programado', 'En Proceso')
        ORDER BY mt.fecha_programada
    ", ['primer_dia' => $primerDia, 'ultimo_dia' => $ultimoDia]);
    
    echo json_encode([
        'success' => true,
        'mantenimientos' => $mantenimientos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
