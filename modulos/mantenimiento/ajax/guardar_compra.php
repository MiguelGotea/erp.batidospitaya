<?php
// ajax/guardar_compra.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$ticketModel = new Ticket();
$visita_id = $_POST['visita_id'] ?? null;
$monto = $_POST['monto'] ?? 0;
$detalle = $_POST['detalle'] ?? '';

if (!$visita_id) {
    echo json_encode(['success' => false, 'message' => 'ID de visita no proporcionado']);
    exit;
}

$foto_nombre = null;
if (isset($_FILES['foto_factura']) && $_FILES['foto_factura']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['foto_factura']['name'], PATHINFO_EXTENSION);
    $foto_nombre = 'factura_' . $visita_id . '_' . time() . '.' . $ext;
    $target = '../uploads/compras/' . $foto_nombre;
    
    if (!is_dir('../uploads/compras')) {
        mkdir('../uploads/compras', 0777, true);
    }
    
    move_uploaded_file($_FILES['foto_factura']['tmp_name'], $target);
}

// O desde cámara
if (isset($_POST['foto_factura_cam']) && !empty($_POST['foto_factura_cam'])) {
    $data = $_POST['foto_factura_cam'];
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
        $data = substr($data, strpos($data, ',') + 1);
        $type = strtolower($type[1]);
        $data = base64_decode($data);
        $foto_nombre = 'factura_' . $visita_id . '_' . time() . '.' . $type;
        $target = '../uploads/compras/' . $foto_nombre;
        
        if (!is_dir('../uploads/compras')) {
            mkdir('../uploads/compras', 0777, true);
        }
        
        file_put_contents($target, $data);
    }
}

try {
    $ticketModel->agregarCompra($visita_id, [
        'foto_factura' => $foto_nombre,
        'monto' => $monto,
        'detalle' => $detalle
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
