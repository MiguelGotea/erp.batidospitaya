<?php
// public_html/modulos/mantenimiento/ajax/equipos_calendario_agendar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $equipo_id = $input['equipo_id'] ?? 0;
    $fecha = $input['fecha'] ?? '';
    $tipo = $input['tipo'] ?? 'Preventivo';
    $registrado_por = $input['registrado_por'] ?? 0;
    
    // Buscar si hay solicitud asociada
    $solicitud_id = null;
    if ($tipo === 'Correctivo') {
        $solicitud = $db->fetchOne("
            SELECT id FROM mtto_equipos_solicitudes
            WHERE equipo_id = :equipo_id 
                AND estado IN ('Solicitado', 'Agendado')
            ORDER BY fecha_solicitud DESC
            LIMIT 1
        ", ['equipo_id' => $equipo_id]);
        
        if ($solicitud) {
            $solicitud_id = $solicitud['id'];
        }
    }
    
    $db->getConnection()->beginTransaction();
    
    // Insertar mantenimiento
    $sql = "
        INSERT INTO mtto_equipos_mantenimientos 
        (equipo_id, solicitud_id, tipo_mantenimiento, fecha_programada, 
         estado, registrado_por)
        VALUES 
        (:equipo_id, :solicitud_id, :tipo_mantenimiento, :fecha_programada,
         'Programado', :registrado_por)
    ";
    
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->execute([
        'equipo_id' => $equipo_id,
        'solicitud_id' => $solicitud_id,
        'tipo_mantenimiento' => $tipo,
        'fecha_programada' => $fecha,
        'registrado_por' => $registrado_por
    ]);
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Mantenimiento agendado exitosamente'
    ]);
    
} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->getConnection()->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>