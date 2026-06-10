<?php
// ajax/finalizar_informe_diario.php
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

if (!$informe_id) {
    echo json_encode(['success' => false, 'message' => 'Falta el ID del informe']);
    exit;
}

// Validar que todas las visitas tengan hora_salida y materiales_stock
$informeData = $ticketModel->getDetalleInformeCompleto($informe_id);
if ($informeData) {
    foreach ($informeData['visitas'] as $v) {
        if (!$v['hora_salida'] || !$v['materiales_stock']) {
            echo json_encode(['success' => false, 'message' => 'Todas las visitas deben tener registrada la hora de salida y los materiales usados antes de finalizar el informe.']);
            exit;
        }
    }
}

try {
    $ticketModel->finalizarInformeDiario($informe_id, []);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
