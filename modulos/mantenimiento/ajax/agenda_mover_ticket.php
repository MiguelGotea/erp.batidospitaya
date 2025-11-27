<?php
// ajax/agenda_mover_ticket.php - VERSIÓN MEJORADA
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
    
    // 2. CRÍTICO: Guardar los tipos de usuario actuales ANTES de borrar
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
    
    // 3. Calcular el MD5 de los tipos actuales para comparar
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
    
    if ($equipoId === 'cambio_equipos') {
        // Para cambio de equipos, no asignar colaboradores
        $tiposAUsar = [];
    } elseif ($md5Actual === $equipoId) {
        // Si el equipo destino es el mismo que el origen, mantener los tipos actuales
        $tiposAUsar = $tiposActuales;
    } else {
        // Si es un equipo diferente, obtener los tipos del equipo destino
        $tiposDestino = obtenerTiposUsuarioDeEquipo($db, $equipoId);
        
        if (!empty($tiposDestino)) {
            $tiposAUsar = $tiposDestino;
        } else {
            // Si no se encuentran tipos del equipo destino, mantener los actuales
            $tiposAUsar = !empty($tiposActuales) ? $tiposActuales : ['Jefe de Manteniento'];
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
        'tipos_previos' => $tiposActuales,
        'tipos_aplicados' => $tiposAUsar,
        'mismo_equipo' => ($md5Actual === $equipoId)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

function obtenerTiposUsuarioDeEquipo($db, $equipoId) {
    try {
        // Buscar cualquier ticket que tenga exactamente esa combinación de tipos
        $sql = "
            SELECT tc.tipo_usuario
            FROM mtto_tickets_colaboradores tc
            INNER JOIN mtto_tickets t ON tc.ticket_id = t.id
            WHERE t.tipo_formulario = 'mantenimiento_general'
            AND tc.tipo_usuario IS NOT NULL
            AND tc.tipo_usuario != ''
            AND tc.ticket_id IN (
                SELECT ticket_id
                FROM mtto_tickets_colaboradores tc2
                INNER JOIN mtto_tickets t2 ON tc2.ticket_id = t2.id
                WHERE t2.tipo_formulario = 'mantenimiento_general'
                AND tc2.tipo_usuario IS NOT NULL
                AND tc2.tipo_usuario != ''
                GROUP BY tc2.ticket_id
                HAVING MD5(GROUP_CONCAT(DISTINCT tc2.tipo_usuario ORDER BY tc2.tipo_usuario SEPARATOR '|')) = ?
                LIMIT 1
            )
            GROUP BY tc.tipo_usuario
            ORDER BY tc.tipo_usuario
        ";
        
        $result = $db->fetchAll($sql, [$equipoId]);
        
        if (!empty($result)) {
            return array_column($result, 'tipo_usuario');
        }
        
        return [];
    } catch (Exception $e) {
        return [];
    }
}
?>