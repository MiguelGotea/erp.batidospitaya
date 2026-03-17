<?php
// ajax/eliminar_visita_informe.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$visita_id = $_POST['id'] ?? null;

if (!$visita_id) {
    echo json_encode(['success' => false, 'message' => 'ID de visita no proporcionado']);
    exit;
}

try {
    $ticketModel = new Ticket();
    $ticketModel->eliminarVisitaInforme($visita_id);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
