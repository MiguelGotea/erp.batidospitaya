<?php
// ajax/guardar_visita.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$ticketModel = new Ticket();
$informe_id = $_POST['informe_id'] ?? null;
$cod_sucursal = $_POST['cod_sucursal'] ?? null;
$hora_llegada = $_POST['hora_llegada'] ?? date('H:i');
$materiales_stock = $_POST['materiales_stock'] ?? '';

if (!$informe_id || !$cod_sucursal) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit;
}

try {
    // Verificar estado del informe
    $informe = $ticketModel->getDetalleInformeCompleto($informe_id);
    if (!$informe || $informe['estado'] === 'finalizado') {
        echo json_encode(['success' => false, 'message' => 'El informe está cerrado o no existe']);
        exit;
    }

    $id = $ticketModel->agregarVisita($informe_id, [
        'cod_sucursal' => $cod_sucursal,
        'hora_llegada' => $hora_llegada,
        'materiales_stock' => $materiales_stock
    ]);

    echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
