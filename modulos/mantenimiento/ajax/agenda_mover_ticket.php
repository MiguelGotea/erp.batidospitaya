<?php
// ajax/agenda_mover_ticket.php - VERSIÓN MEJORADA V2
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$ticketId = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$equipoId = isset($_POST['equipo_id']) ? $_POST['equipo_id'] : '';
$fechaInicio = isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : '';
$fechaFinal = isset($_POST['fecha_final']) ? $_POST['fecha_final'] : '';

if (!$ticketId || !$equipoId || !$fechaInicio || !$fechaFinal) {
    echo json_encode([
        'error' => 'Datos incompletos',
        'received' => [
            'ticket_id' => $ticketId,
            'equipo_id' => $equipoId,
            'fecha_inicio' => $fechaInicio,
            'fecha_final' => $fechaFinal
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar formato de fechas
$dateInicio = DateTime::createFromFormat('Y-m-d', $fechaInicio);
$dateFinal = DateTime::createFromFormat('Y-m-d', $fechaFinal);

if (!$dateInicio || !$dateFinal) {
    echo json_encode(['error' => 'Formato de fecha inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db->getConnection()->beginTransaction();
    
    // 1. Obtener información del ticket
    $ticket = $db->fetchOne("SELECT cod_operario, tipo_formulario FROM mtto_tickets WHERE id = ?", [$ticketId]);
    
    if (!$ticket) {
        throw new Exception('Ticket no encontrado');
    }
    
    // 2. GUARDAR los tipos de usuario actuales ANTES de borrar
    $tiposActualesResult = $db->fetchAll(
        "SELECT DISTINCT tipo_usuario 
         FROM mtto_tickets_colaboradores 
         WHERE ticket_id = ? 
         AND tipo_usuario IS NOT NULL 
         AND tipo_usuario != ''
         ORDER BY tipo_usuario", 
        [$ticketId]
    );
    
    $tiposActuales = array_column($tiposActualesResult, 'tipo_usuario');
    
    // 3. Calcular el MD5 de los tipos actuales
    $md5Actual = '';
    if (!empty($tiposActuales)) {
        $md5Actual = md5(implode('|', $tiposActuales));
    }
    
    // 4. Actualizar solo las fechas del ticket
    $stmt = $db->query("
        UPDATE mtto_tickets 
        SET fecha_inicio = ?, 
            fecha_final = ?
        WHERE id = ?
    ", [$fechaInicio, $fechaFinal, $ticketId]);
    
    // 5. Determinar qué tipos de usuario usar
    $tiposAUsar = [];
    $razonDecision = '';
    
    if ($equipoId === 'cambio_equipos') {
        // Para cambio de equipos, no asignar colaboradores
        $tiposAUsar = [];
        $razonDecision = 'Cambio de equipos - sin colaboradores';
        
    } elseif ($md5Actual === $equipoId) {
        // Si el equipo destino es el mismo que el origen, mantener los tipos actuales
        $tiposAUsar = $tiposActuales;
        $razonDecision = 'Mismo equipo - mantener tipos actuales';
        
    } else {
        // Es un equipo diferente - buscar tipos del equipo destino
        $tiposDestino = obtenerTiposDeEquipoPorMD5($db, $equipoId);
        
        if (!empty($tiposDestino)) {
            $tiposAUsar = $tiposDestino;
            $razonDecision = 'Equipo diferente - usar tipos del destino';
        } else {
            // Si no encuentra tipos del equipo destino, mantener los actuales
            $tiposAUsar = !empty($tiposActuales) ? $tiposActuales : ['Jefe de Manteniento'];
            $razonDecision = 'Equipo no encontrado - mantener tipos actuales como fallback';
        }
    }
    
    // 6. Eliminar colaboradores existentes
    $db->query("DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?", [$ticketId]);
    
    // 7. Insertar los tipos de usuario determinados
    if (!empty($tiposAUsar)) {
        foreach ($tiposAUsar as $tipoUsuario) {
            $db->query("
                INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, tipo_usuario, fecha_asignacion)
                VALUES (?, ?, ?, NOW())
            ", [$ticketId, $ticket['cod_operario'], $tipoUsuario]);
        }
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'ticket_id' => $ticketId,
        'equipo_id' => $equipoId,
        'md5_actual' => $md5Actual,
        'tipos_previos' => $tiposActuales,
        'tipos_aplicados' => $tiposAUsar,
        'mismo_equipo' => ($md5Actual === $equipoId),
        'razon' => $razonDecision
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

function obtenerTiposDeEquipoPorMD5($db, $equipoIdMD5) {
    try {
        // Buscar TODOS los tickets del historial que tengan exactamente esa combinación
        $sql = "
            SELECT 
                ticket_id,
                GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR '|') as tipos_str,
                MD5(GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR '|')) as md5_tipos
            FROM mtto_tickets_colaboradores tc
            INNER JOIN mtto_tickets t ON tc.ticket_id = t.id
            WHERE t.tipo_formulario = 'mantenimiento_general'
            AND tc.tipo_usuario IS NOT NULL
            AND tc.tipo_usuario != ''
            GROUP BY tc.ticket_id
            HAVING md5_tipos = ?
            LIMIT 1
        ";
        
        $result = $db->fetchOne($sql, [$equipoIdMD5]);
        
        if ($result && !empty($result['tipos_str'])) {
            $tipos = explode('|', $result['tipos_str']);
            return array_values(array_filter($tipos));
        }
        
        return [];
        
    } catch (Exception $e) {
        // Log error para debugging
        error_log("Error en obtenerTiposDeEquipoPorMD5: " . $e->getMessage());
        return [];
    }
}
?>