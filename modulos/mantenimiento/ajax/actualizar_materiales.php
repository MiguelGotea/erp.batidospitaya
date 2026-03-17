<?php
// ajax/actualizar_materiales.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$visita_id = $_POST['visita_id'] ?? null;
$materiales_stock = $_POST['materiales_stock'] ?? null;

if (!$visita_id || $materiales_stock === null) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit;
}

try {
    $ticketModel = new Ticket();
    $res = $ticketModel->actualizarMateriales($visita_id, $materiales_stock);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
