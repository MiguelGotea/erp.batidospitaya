<?php
// ajax/detalles_actualizar_ticket.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Ticket.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$area_equipo = isset($_POST['area_equipo']) ? trim($_POST['area_equipo']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$nivel_urgencia = isset($_POST['nivel_urgencia']) ? intval($_POST['nivel_urgencia']) : null;
$materiales_otros = isset($_POST['materiales_otros']) ? trim($_POST['materiales_otros']) : '';

if ($ticket_id <= 0 || empty($titulo) || empty($area_equipo) || empty($descripcion)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn = $db->getConnection();
    $conn->beginTransaction();
    
    // Actualizar datos básicos del ticket
    $sql = "UPDATE mtto_tickets 
            SET titulo = ?, 
                area_equipo = ?, 
                descripcion = ?, 
                nivel_urgencia = ?,
                materiales_usados = ?
            WHERE id = ?";
    
    $db->query($sql, [$titulo, $area_equipo, $descripcion, $nivel_urgencia, $materiales_otros, $ticket_id]);
    
    // Procesar nuevas fotos
    $ticket_model = new Ticket();
    $fotos_guardadas = [];
    
    // Fotos desde archivos
    if (isset($_FILES['nuevas_fotos']) && !empty($_FILES['nuevas_fotos']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/tickets/';
        
        foreach ($_FILES['nuevas_fotos']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['nuevas_fotos']['error'][$key] === UPLOAD_ERR_OK) {
                $extension = pathinfo($_FILES['nuevas_fotos']['name'][$key], PATHINFO_EXTENSION);
                $nombreArchivo = 'ticket_' . $ticket_id . '_' . time() . '_' . $key . '.' . $extension;
                $rutaDestino = $uploadDir . $nombreArchivo;
                
                if (move_uploaded_file($tmp_name, $rutaDestino)) {
                    $fotos_guardadas[] = $nombreArchivo;
                }
            }
        }
    }
    
    // Fotos desde cámara
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'foto_camera_') === 0 && !empty($value)) {
            $imageData = str_replace('data:image/jpeg;base64,', '', $value);
            $imageData = str_replace(' ', '+', $imageData);
            $decodedImage = base64_decode($imageData);
            
            $nombreArchivo = 'ticket_' . $ticket_id . '_camera_' . time() . '_' . uniqid() . '.jpg';
            $rutaDestino = __DIR__ . '/../uploads/tickets/' . $nombreArchivo;
            
            if (file_put_contents($rutaDestino, $decodedImage)) {
                $fotos_guardadas[] = $nombreArchivo;
            }
        }
    }
    
    // Guardar fotos en BD
    if (!empty($fotos_guardadas)) {
        $ticket_model->addFotos($ticket_id, $fotos_guardadas);
    }
    
    // Actualizar colaboradores
    if (isset($_POST['colaboradores'])) {
        $colaboradores = json_decode($_POST['colaboradores'], true);
        
        // Eliminar colaboradores actuales
        $sql = "DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?";
        $db->query($sql, [$ticket_id]);
        
        // Insertar nuevos colaboradores
        foreach ($colaboradores as $colab) {
            $sql = "INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, tipo_usuario) 
                    VALUES (?, ?, ?)";
            $db->query($sql, [$ticket_id, $colab['cod_operario'], $colab['tipo_usuario']]);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ticket actualizado correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar ticket: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>