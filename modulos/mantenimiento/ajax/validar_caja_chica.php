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
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso
if (!tienePermiso('agenda_mantenimiento', 'caja_chica', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}
$informe_id = $_POST['informe_id'] ?? null;
$monto = $_POST['monto'] ?? 0;

if (!$informe_id) {
    echo json_encode(['success' => false, 'message' => 'ID de informe no proporcionado']);
    exit;
}

if (isset($_FILES['foto_caja']) && $_FILES['foto_caja']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['foto_caja']['name'], PATHINFO_EXTENSION);
    $foto_nombre = 'caja_' . $informe_id . '_' . time() . '.' . $ext;
    $target = '../uploads/caja/' . $foto_nombre;
    if (!is_dir('../uploads/caja')) mkdir('../uploads/caja', 0777, true);
    move_uploaded_file($_FILES['foto_caja']['tmp_name'], $target);
} elseif (!empty($_POST['foto_caja_cam'])) {
    $imgData = $_POST['foto_caja_cam'];
    $imgData = str_replace('data:image/jpeg;base64,', '', $imgData);
    $imgData = str_replace(' ', '+', $imgData);
    $data = base64_decode($imgData);
    $foto_nombre = 'caja_cam_' . $informe_id . '_' . time() . '.jpg';
    if (!is_dir('../uploads/caja')) mkdir('../uploads/caja', 0777, true);
    file_put_contents('../uploads/caja/' . $foto_nombre, $data);
}

try {
    $ticketModel->actualizarCajaChica($informe_id, $monto, $foto_nombre);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
