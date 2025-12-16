
<?php
// public_html/modulos/mantenimiento/ajax/equipos_central_disponibles.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $equipo_actual_id = $input['equipo_actual_id'] ?? 0;
    
    // Obtener tipo del equipo actual
    $equipoActual = $db->fetchOne("
        SELECT tipo_id FROM mtto_equipos WHERE id = :id
    ", ['id' => $equipo_actual_id]);
    
    // Buscar equipos del mismo tipo en central
    $equipos = $db->fetchAll("
        SELECT e.id, e.codigo, e.nombre
        FROM mtto_equipos e
        LEFT JOIN (
            SELECT m1.*
            FROM mtto_equipos_movimientos m1
            INNER JOIN (
                SELECT equipo_id, MAX(id) as max_id
                FROM mtto_equipos_movimientos
                WHERE estado = 'Completado'
                GROUP BY equipo_id
            ) m2 ON m1.id = m2.max_id
        ) m ON e.id = m.equipo_id
        WHERE e.activo = 1
            AND e.tipo_id = :tipo_id
            AND e.id != :equipo_actual_id
            AND m.destino_tipo = 'Central'
            AND NOT EXISTS (
                SELECT 1 FROM mtto_equipos_mantenimientos mt
                WHERE mt.equipo_id = e.id 
                    AND mt.estado IN ('Programado', 'En Proceso')
            )
        ORDER BY e.codigo
    ", [
        'tipo_id' => $equipoActual['tipo_id'],
        'equipo_actual_id' => $equipo_actual_id
    ]);
    
    echo json_encode([
        'success' => true,
        'equipos' => $equipos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>