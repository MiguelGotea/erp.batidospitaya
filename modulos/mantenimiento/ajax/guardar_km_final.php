<?php
// ajax/guardar_km_final.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$informe_id = intval($_POST['informe_id'] ?? 0);
$km_final   = $_POST['km_final'] ?? null;

if (!$informe_id || $km_final === null || $km_final === '') {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Manejo de foto desde archivo
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

// O foto desde cámara (base64)
if (!$foto_nombre && isset($_POST['km_foto_final_cam']) && !empty($_POST['km_foto_final_cam'])) {
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
    $ticketModel = new Ticket();

    // Verificar que el informe pertenece al usuario o tiene permiso
    $informe = $ticketModel->getDetalleInformeCompleto($informe_id);
    if (!$informe) {
        echo json_encode(['success' => false, 'message' => 'Informe no encontrado']);
        exit;
    }
    if ($informe['cod_operario'] != $usuario['CodOperario'] &&
        !tienePermiso('agenda_mantenimiento', 'todos_colaboradores', $usuario['CodNivelesCargos'])) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso']);
        exit;
    }

    // Actualizar km_final (y foto si aplica)
    $ticketModel->actualizarKmFinal($informe_id, floatval($km_final), $foto_nombre);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
