<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

session_start();
$usuario_id = $_SESSION['usuario_id'];

$input = json_decode(file_get_contents('php://input'), true);
$accion = $input['accion'] ?? '';

try {
    switch ($accion) {
        case 'programar':
            // Programar nuevo mantenimiento
            $equipo_id = $input['equipo_id'];
            $fecha = $input['fecha'];
            $tipo = $input['tipo'];
            $solicitud_id = $input['solicitud_id'] ?? null;
            
            // Verificar si ya existe uno programado ese día para ese equipo
            $existente = $db->fetchOne(
                "SELECT id FROM mtto_equipos_mantenimientos_programados 
                 WHERE equipo_id = ? AND fecha_programada = ? AND estado = 'agendado'",
                [$equipo_id, $fecha]
            );
            
            if ($existente) {
                echo json_encode(['success' => false, 'message' => 'El equipo ya tiene un mantenimiento programado para esa fecha']);
                exit;
            }
            
            $db->query(
                "INSERT INTO mtto_equipos_mantenimientos_programados 
                 (equipo_id, fecha_programada, tipo, programado_por)
                 VALUES (?, ?, ?, ?)",
                [$equipo_id, $fecha, $tipo, $usuario_id]
            );
            
            echo json_encode(['success' => true, 'message' => 'Mantenimiento programado']);
            break;
            
        case 'mover':
            // Mover mantenimiento existente
            $programado_id = $input['programado_id'];
            $nueva_fecha = $input['nueva_fecha'];
            
            // Verificar que el mantenimiento no esté finalizado
            $mant = $db->fetchOne(
                "SELECT mp.*, m.id as mantenimiento_id 
                 FROM mtto_equipos_mantenimientos_programados mp
                 LEFT JOIN mtto_equipos_mantenimientos m ON mp.id = m.mantenimiento_programado_id
                 WHERE mp.id = ?",
                [$programado_id]
            );
            
            if (!$mant) {
                echo json_encode(['success' => false, 'message' => 'Mantenimiento no encontrado']);
                exit;
            }
            
            if ($mant['mantenimiento_id']) {
                echo json_encode(['success' => false, 'message' => 'No se puede mover un mantenimiento finalizado']);
                exit;
            }
            
            $db->query(
                "UPDATE mtto_equipos_mantenimientos_programados 
                 SET fecha_programada = ? 
                 WHERE id = ?",
                [$nueva_fecha, $programado_id]
            );
            
            echo json_encode(['success' => true, 'message' => 'Mantenimiento movido']);
            break;
            
        case 'desprogramar':
            // Eliminar mantenimiento programado
            $programado_id = $input['programado_id'];
            
            // Verificar que no esté finalizado
            $mant = $db->fetchOne(
                "SELECT mp.*, m.id as mantenimiento_id 
                 FROM mtto_equipos_mantenimientos_programados mp
                 LEFT JOIN mtto_equipos_mantenimientos m ON mp.id = m.mantenimiento_programado_id
                 WHERE mp.id = ?",
                [$programado_id]
            );
            
            if ($mant['mantenimiento_id']) {
                echo json_encode(['success' => false, 'message' => 'No se puede desprogramar un mantenimiento finalizado']);
                exit;
            }
            
            $db->query(
                "DELETE FROM mtto_equipos_mantenimientos_programados WHERE id = ?",
                [$programado_id]
            );
            
            echo json_encode(['success' => true, 'message' => 'Mantenimiento desprogramado']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>