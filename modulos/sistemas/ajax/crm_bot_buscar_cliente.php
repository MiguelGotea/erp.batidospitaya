<?php
/**
 * ajax/crm_bot_buscar_cliente.php
 * Búsqueda de clientes en clientesclub para el modal Nueva Conversación
 * GET ?q=nombre_o_celular
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

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'clientes' => []]);
    exit;
}

try {
    $like = "%{$q}%";
    $stmt = $conn->prepare("
        SELECT id_clienteclub AS id, nombre, apellido, celular
        FROM clientesclub
        WHERE nombre LIKE :q1 OR apellido LIKE :q2 OR celular LIKE :q3
        ORDER BY nombre ASC
        LIMIT 15
    ");
    $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
    $clientes = $stmt->fetchAll();
    echo json_encode(['success' => true, 'clientes' => $clientes]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
