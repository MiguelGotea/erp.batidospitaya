<?php
// ajax/get_informe_diario.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$ticketModel = new Ticket();
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$cod_operario = $_GET['cod_operario'] ?? $usuario['CodOperario'];

try {
    $informe = $ticketModel->getInformeDiarioPorFecha($cod_operario, $fecha);
    
    if ($informe) {
        $detalle = $ticketModel->getDetalleInformeCompleto($informe['id']);
        echo json_encode(['success' => true, 'informe' => $detalle]);
    } else {
        echo json_encode(['success' => true, 'informe' => null]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
