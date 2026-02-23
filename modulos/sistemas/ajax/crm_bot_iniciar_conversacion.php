<?php
/**
 * ajax/crm_bot_iniciar_conversacion.php
 * Inicia manualmente una conversación desde el ERP con un número dado.
 * POST — body: { instancia, numero_cliente }
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
if (!tienePermiso('crm_bot', 'responder', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$instancia = trim($body['instancia'] ?? '');
$numCliente = preg_replace('/\D/', '', $body['numero_cliente'] ?? '');

if (!$instancia || !$numCliente) {
    echo json_encode(['success' => false, 'error' => 'instancia y numero_cliente son requeridos']);
    exit;
}

try {
    // Buscar conversación existente
    $stmt = $conn->prepare("
        SELECT c.*, DATE_FORMAT(c.last_interaction_at, '%Y-%m-%d %H:%i:%s') AS last_interaction_at,
               DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at
        FROM conversations c
        WHERE c.instancia = :inst AND c.numero_cliente = :nc
        LIMIT 1
    ");
    $stmt->execute([':inst' => $instancia, ':nc' => $numCliente]);
    $conv = $stmt->fetch();

    if (!$conv) {
        // Obtener número remitente actual de la instancia
        $stmtSes = $conn->prepare("SELECT numero_telefono FROM wsp_sesion_vps_ WHERE instancia = :i LIMIT 1");
        $stmtSes->execute([':i' => $instancia]);
        $numRem = $stmtSes->fetchColumn() ?: '0';

        $stmtIns = $conn->prepare("
            INSERT INTO conversations
                (instancia, numero_cliente, numero_remitente, status, created_at, updated_at)
            VALUES
                (:inst, :nc, :nr, 'humano',
                 CONVERT_TZ(NOW(),'+00:00','-06:00'),
                 CONVERT_TZ(NOW(),'+00:00','-06:00'))
        ");
        $stmtIns->execute([':inst' => $instancia, ':nc' => $numCliente, ':nr' => $numRem]);
        $convId = $conn->lastInsertId();

        // Re-fetch para devolver completo
        $stmt2 = $conn->prepare("
            SELECT *, DATE_FORMAT(last_interaction_at, '%Y-%m-%d %H:%i:%s') AS last_interaction_at,
                      DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_at
            FROM conversations WHERE id = :id
        ");
        $stmt2->execute([':id' => $convId]);
        $conv = $stmt2->fetch();
        $nueva = true;
    } else {
        $nueva = false;
    }

    echo json_encode([
        'success' => true,
        'conversacion' => $conv,
        'nueva' => $nueva
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
