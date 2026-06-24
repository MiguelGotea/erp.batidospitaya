<?php
// ajax/historial_finalizar_ticket.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$cargoOperario = $usuario['CodNivelesCargos'];
if (!tienePermiso('historial_solicitudes_mantenimiento', 'super_edicion', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para realizar esta acción']);
    exit;
}

$ticketModel = new Ticket();
$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : null;
$trabajo_realizado = isset($_POST['trabajo_realizado']) ? trim($_POST['trabajo_realizado']) : '';

if (!$ticket_id) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket requerido']);
    exit;
}

if (empty($trabajo_realizado)) {
    echo json_encode(['success' => false, 'message' => 'El detalle del trabajo no puede estar vacío']);
    exit;
}

$db = $ticketModel->getDb();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();

    // 1. Actualizar el ticket en mtto_tickets
    $sqlTicket = "UPDATE mtto_tickets SET
                    status = 'finalizado',
                    detalle_trabajo = ?,
                    finalizado_por = ?,
                    fecha_finalizacion = CURRENT_TIMESTAMP,
                    fecha_inicio = COALESCE(fecha_inicio, CURRENT_DATE()),
                    fecha_final = COALESCE(fecha_final, CURRENT_DATE())
                  WHERE id = ?";
    $ticketModel->getDb()->query($sqlTicket, [$trabajo_realizado, $usuario['CodOperario'], $ticket_id]);

    // 2. Registrar la tarea en mtto_informe_tareas (con visita_id = NULL y completado_100 = 1)
    $tarea_id = $ticketModel->registrarTareaInforme(null, [
        'ticket_id' => $ticket_id,
        'completado_100' => 1,
        'trabajo_realizado' => $trabajo_realizado
    ]);

    // 3. Procesar fotos de evidencia (múltiples)
    $fotosGuardadas = [];
    
    // Fotos desde input file (Galería)
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
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
