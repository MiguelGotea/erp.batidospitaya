<?php
// ajax/detalles_update_ticket.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Ticket.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$area_equipo = isset($_POST['area_equipo']) ? trim($_POST['area_equipo']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$nivel_urgencia = isset($_POST['nivel_urgencia']) && $_POST['nivel_urgencia'] !== '' ? intval($_POST['nivel_urgencia']) : null;

if ($ticket_id <= 0 || empty($titulo) || empty($area_equipo) || empty($descripcion)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $ticket_model = new Ticket();
    
    // Actualizar datos básicos
    $data = [
        'titulo' => $titulo,
        'area_equipo' => $area_equipo,
        'descripcion' => $descripcion,
        'nivel_urgencia' => $nivel_urgencia
    ];
    
    $ticket_model->update($ticket_id, $data);
    
    // Procesar nuevas fotos si existen
    $fotos_guardadas = 0;
    
    // Fotos desde archivos
    if (isset($_FILES['nuevas_fotos']) && !empty($_FILES['nuevas_fotos']['name'][0])) {
        $upload_dir = __DIR__ . '/../../uploads/tickets/';
        
        foreach ($_FILES['nuevas_fotos']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['nuevas_fotos']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = uniqid() . '_' . basename($_FILES['nuevas_fotos']['name'][$key]);
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $ticket_model->addFotos($ticket_id, [$file_name]);
                    $fotos_guardadas++;
                }
            }
        }
    }
    
    // Fotos desde cámara
    if (isset($_POST['fotos_camera']) && !empty($_POST['fotos_camera'])) {
        $fotos_camera = json_decode($_POST['fotos_camera'], true);
        $upload_dir = __DIR__ . '/../../uploads/tickets/';
        
        foreach ($fotos_camera as $foto_data) {
            // Decodificar base64
            $img_data = str_replace('data:image/jpeg;base64,', '', $foto_data);
            $img_data = str_replace(' ', '+', $img_data);
            $data = base64_decode($img_data);
            
            $file_name = uniqid() . '_camera.jpg';
            $file_path = $upload_dir . $file_name;
            
            if (file_put_contents($file_path, $data)) {
                $ticket_model->addFotos($ticket_id, [$file_name]);
                $fotos_guardadas++;
            }
        }
    }
    
    $mensaje = 'Ticket actualizado correctamente';
    if ($fotos_guardadas > 0) {
        $mensaje .= " ($fotos_guardadas fotos agregadas)";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar ticket: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>