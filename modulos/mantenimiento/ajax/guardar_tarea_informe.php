<?php
// ajax/guardar_tarea_informe.php
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
$ticket_id = $_POST['ticket_id'] ?? null;
$completado_100 = isset($_POST['completado_100']) ? (int)$_POST['completado_100'] : 1;
$trabajo_realizado = $_POST['trabajo_realizado'] ?? '';

if (!$visita_id || !$ticket_id) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos de la tarea']);
    exit;
}

try {
    // 1. Registrar la tarea
    $tarea_id = $ticketModel->registrarTareaInforme($visita_id, [
        'ticket_id' => $ticket_id,
        'completado_100' => $completado_100,
        'trabajo_realizado' => $trabajo_realizado
    ]);

    // 2. Procesar fotos de evidencia (múltiples)
    $fotosGuardadas = [];
    
    // Fotos desde input file
    if (isset($_FILES['fotos_evidencia'])) {
        $files = $_FILES['fotos_evidencia'];
        if (!is_dir('../uploads/evidencias')) {
            mkdir('../uploads/evidencias', 0777, true);
        }
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $nombre = 'evidencia_' . $tarea_id . '_' . $i . '_' . time() . '.' . $ext;
                move_uploaded_file($files['tmp_name'][$i], '../uploads/evidencias/' . $nombre);
                $fotosGuardadas[] = $nombre;
            }
        }
    }

    // Fotos desde cámara (JSON array de base64)
    if (isset($_POST['fotos_camera_json']) && !empty($_POST['fotos_camera_json'])) {
        $cameraFotos = json_decode($_POST['fotos_camera_json'], true);
        if (is_array($cameraFotos)) {
            if (!is_dir('../uploads/evidencias')) {
                mkdir('../uploads/evidencias', 0777, true);
            }
            foreach ($cameraFotos as $idx => $data) {
                if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                    $data = substr($data, strpos($data, ',') + 1);
                    $type = strtolower($type[1]);
                    $data = base64_decode($data);
                    $nombre = 'evidencia_cam_' . $tarea_id . '_' . $idx . '_' . time() . '.' . $type;
                    file_put_contents('../uploads/evidencias/' . $nombre, $data);
                    $fotosGuardadas[] = $nombre;
                }
            }
        }
    }

    if (!empty($fotosGuardadas)) {
        $ticketModel->agregarFotosTareaInforme($tarea_id, $fotosGuardadas);
    } else {
        // En teoría es obligatorio al menos 1
        // (Se validará también en frontend)
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
