<?php
/**
 * ajax/crm_bot_get_mensajes.php
 * Consulta la BD directamente
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
if (!tienePermiso('crm_bot', 'vista', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$convId = (int) ($_GET['conversation_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(200, max(10, (int) ($_GET['per_page'] ?? 100)));
$offset = ($page - 1) * $perPage;

if (!$convId) {
    echo json_encode(['success' => false, 'error' => 'conversation_id requerido']);
    exit;
}

try {
    // Datos de la conversación
    $stmtC = $conn->prepare("
        SELECT id, instancia, numero_cliente, numero_remitente, status, last_intent,
               DATE_FORMAT(last_interaction_at,'%Y-%m-%d %H:%i:%s') AS last_interaction_at,
               DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s') AS created_at
        FROM conversations WHERE id = :id LIMIT 1
    ");
    $stmtC->execute([':id' => $convId]);
    $conv = $stmtC->fetch();
    if (!$conv) {
        echo json_encode(['success' => false, 'error' => 'Conversación no encontrada']);
        exit;
    }

    // Mensajes
    $stmt = $conn->prepare("
        SELECT id, conversation_id, direction, sender_type, message_text, message_type, enviado_ok,
               DATE_FORMAT(created_at,'%Y-%m-%d %H:%i:%s') AS created_at
        FROM messages
        WHERE conversation_id = :cid
        ORDER BY id ASC
        LIMIT :lim OFFSET :off
    ");
    $stmt->bindValue(':cid', $convId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'conversacion' => $conv,
        'mensajes' => $stmt->fetchAll(),
        'page' => $page,
        'per_page' => $perPage
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
