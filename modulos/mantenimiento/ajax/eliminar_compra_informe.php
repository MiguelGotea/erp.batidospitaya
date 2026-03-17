<?php
// ajax/eliminar_compra_informe.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$compra_id = $_POST['id'] ?? null;

if (!$compra_id) {
    echo json_encode(['success' => false, 'message' => 'ID de compra no proporcionado']);
    exit;
}

try {
    $ticketModel = new Ticket();
    $ticketModel->eliminarCompraInforme($compra_id);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
