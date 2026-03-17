<?php
// ajax/get_tickets_sucursal.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$cod_sucursal = $_GET['cod_sucursal'] ?? null;

if (!$cod_sucursal) {
    echo json_encode(['success' => false, 'message' => 'Sucursal no proporcionada']);
    exit;
}

try {
    $ticketModel = new Ticket();
    $tickets = $ticketModel->getTicketsPorSucursal($cod_sucursal);
    echo json_encode(['success' => true, 'tickets' => $tickets]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
