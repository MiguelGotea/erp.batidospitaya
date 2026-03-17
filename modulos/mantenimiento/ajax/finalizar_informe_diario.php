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
$km_final = $_POST['km_final'] ?? null;

if (!$informe_id || !$km_final) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos para el cierre de jornada']);
    exit;
}

$foto_nombre = null;
if (isset($_FILES['km_foto_final']) && $_FILES['km_foto_final']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['km_foto_final']['name'], PATHINFO_EXTENSION);
    $foto_nombre = 'km_fin_' . $informe_id . '_' . time() . '.' . $ext;
    $target = '../uploads/informes/' . $foto_nombre;
    
    if (!is_dir('../uploads/informes')) {
        mkdir('../uploads/informes', 0777, true);
    }
    
    move_uploaded_file($_FILES['km_foto_final']['tmp_name'], $target);
}

// O desde cámara
if (isset($_POST['km_foto_final_cam']) && !empty($_POST['km_foto_final_cam'])) {
    $data = $_POST['km_foto_final_cam'];
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
        $data = substr($data, strpos($data, ',') + 1);
        $type = strtolower($type[1]);
        $data = base64_decode($data);
        $foto_nombre = 'km_fin_' . $informe_id . '_' . time() . '.' . $type;
        $target = '../uploads/informes/' . $foto_nombre;
        
        if (!is_dir('../uploads/informes')) {
            mkdir('../uploads/informes', 0777, true);
        }
        
        file_put_contents($target, $data);
    }
}

try {
    $ticketModel->finalizarInformeDiario($informe_id, [
        'km_final' => $km_final,
        'km_foto_final' => $foto_nombre
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
