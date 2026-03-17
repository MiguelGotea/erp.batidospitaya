<?php
// ajax/validar_caja_chica.php
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
$monto = $_POST['monto'] ?? 0;

if (!$informe_id) {
    echo json_encode(['success' => false, 'message' => 'ID de informe no proporcionado']);
    exit;
}

$foto_nombre = null;
if (isset($_FILES['foto_caja']) && $_FILES['foto_caja']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['foto_caja']['name'], PATHINFO_EXTENSION);
    $foto_nombre = 'caja_' . $informe_id . '_' . time() . '.' . $ext;
    $target = '../uploads/caja/' . $foto_nombre;
    
    if (!is_dir('../uploads/caja')) {
        mkdir('../uploads/caja', 0777, true);
    }
    
    move_uploaded_file($_FILES['foto_caja']['tmp_name'], $target);
}

try {
    $ticketModel->actualizarCajaChica($informe_id, $monto, $foto_nombre);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
