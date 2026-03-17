<?php
// ajax/guardar_informe_apertura.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$ticketModel = new Ticket();
$cod_operario = $usuario['CodOperario'];
$fecha = date('Y-m-d');
$km_inicial = $_POST['km_inicial'] ?? null;

// Manejo de foto
$foto_nombre = null;
if (isset($_FILES['km_foto_inicial']) && $_FILES['km_foto_inicial']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['km_foto_inicial']['name'], PATHINFO_EXTENSION);
    $foto_nombre = 'km_ini_' . $cod_operario . '_' . time() . '.' . $ext;
    $target = '../uploads/informes/' . $foto_nombre;
    
    if (!is_dir('../uploads/informes')) {
        mkdir('../uploads/informes', 0777, true);
    }
    
    move_uploaded_file($_FILES['km_foto_inicial']['tmp_name'], $target);
}

// O manejo de foto desde cámara (base64)
if (isset($_POST['km_foto_inicial_cam']) && !empty($_POST['km_foto_inicial_cam'])) {
    $data = $_POST['km_foto_inicial_cam'];
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
        $data = substr($data, strpos($data, ',') + 1);
        $type = strtolower($type[1]);
        $data = base64_decode($data);
        $foto_nombre = 'km_ini_' . $cod_operario . '_' . time() . '.' . $type;
        $target = '../uploads/informes/' . $foto_nombre;
        
        if (!is_dir('../uploads/informes')) {
            mkdir('../uploads/informes', 0777, true);
        }
        
        file_put_contents($target, $data);
    }
}

try {
    // Verificar que no exista ya un informe para hoy
    $existente = $ticketModel->getInformeDiarioPorFecha($cod_operario, $fecha);
    if ($existente) {
        echo json_encode(['success' => false, 'message' => 'Ya existe un informe iniciado para hoy']);
        exit;
    }

    $id = $ticketModel->crearInformeDiario([
        'cod_operario' => $cod_operario,
        'fecha' => $fecha,
        'km_inicial' => $km_inicial,
        'km_foto_inicial' => $foto_nombre
    ]);

    echo json_encode(['success' => true, 'id' => $id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
