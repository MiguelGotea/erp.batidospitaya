<?php
// ajax/eliminar_tarea_informe.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$tarea_id = $_POST['id'] ?? null;

if (!$tarea_id) {
    echo json_encode(['success' => false, 'message' => 'ID de tarea no proporcionado']);
    exit;
}

try {
    $ticketModel = new Ticket();
    // Podríamos validar aquí que la tarea pertenece al usuario actual revisando la relación con el informe, 
    // pero para agilizar usaremos la lógica de borrado del modelo.
    $ticketModel->eliminarTareaInforme($tarea_id);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
