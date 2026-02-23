<?php
/**
 * ajax/crm_bot_get_conversaciones.php
 * Consulta la BD directamente (mismo servidor que API)
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

$instancia = $_GET['instancia'] ?? '';
$status = $_GET['status'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
$offset = ($page - 1) * $perPage;

try {
    $where = ['1=1'];
    $params = [];
    if ($instancia) {
        $where[] = 'c.instancia = :inst';
        $params[':inst'] = $instancia;
    }
    if ($status !== 'all') {
        $where[] = 'c.status = :status';
        $params[':status'] = $status;
    }
    if ($q) {
        $where[] = 'c.numero_cliente LIKE :q';
        $params[':q'] = "%{$q}%";
    }
    $whereSQL = implode(' AND ', $where);

    $total = (int) $conn->prepare("SELECT COUNT(*) FROM conversations c WHERE {$whereSQL}")
        ->execute($params) ? $conn->prepare("SELECT COUNT(*) FROM conversations c WHERE {$whereSQL}") : 0;

    $stC = $conn->prepare("SELECT COUNT(*) FROM conversations c WHERE {$whereSQL}");
    $stC->execute($params);
    $total = (int) $stC->fetchColumn();

    $stmt = $conn->prepare("
        SELECT
            c.id, c.instancia, c.numero_cliente, c.numero_remitente,
            c.status, c.last_intent,
            DATE_FORMAT(c.last_interaction_at, '%Y-%m-%d %H:%i:%s') AS last_interaction_at,
            DATE_FORMAT(c.created_at, '%Y-%m-%d %H:%i:%s') AS created_at,
            (SELECT message_text FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS ultimo_mensaje,
            (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.direction = 'in'
                AND m.created_at > IFNULL(
                    (SELECT MAX(m2.created_at) FROM messages m2 WHERE m2.conversation_id = c.id AND m2.sender_type IN ('bot','agent')),
                    '2000-01-01'
                )
            ) AS mensajes_sin_leer
        FROM conversations c
        WHERE {$whereSQL}
        ORDER BY c.last_interaction_at DESC
        LIMIT :lim OFFSET :off
    ");
    foreach ($params as $k => $v)
        $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'conversaciones' => $stmt->fetchAll()
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
