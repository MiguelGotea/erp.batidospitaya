<?php
// ajax/actualizar_hora_salida.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$visita_id = $_POST['visita_id'] ?? null;
$hora_salida = $_POST['hora_salida'] ?? null;

if (!$visita_id || !$hora_salida) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit;
}

try {
    $ticketModel = new Ticket();
    $res = $ticketModel->actualizarHoraSalida($visita_id, $hora_salida);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
