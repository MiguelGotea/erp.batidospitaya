<?php
// ajax/actualizar_hora_llegada.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$visita_id = $_POST['visita_id'] ?? null;
$hora_llegada = $_POST['hora_llegada'] ?? null;

if (!$visita_id || !$hora_llegada) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit;
}

try {
    $ticketModel = new Ticket();
    $res = $ticketModel->actualizarHoraLlegada($visita_id, $hora_llegada);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
