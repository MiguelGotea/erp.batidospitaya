<?php
header('Content-Type: application/json');
session_start();
require_once '../models/Ticket.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Verificar si es finalización simple o con detalles
    $finalizacion_completa = isset($_POST['detalle_trabajo']) && isset($_POST['materiales_usados']);
    
    if ($finalizacion_completa) {
        // Finalización con detalles, materiales y fotos
        if (!isset($_POST['ticket_id'])) {
            throw new Exception('ID de ticket requerido');
        }
        
        $ticket_id = intval($_POST['ticket_id']);
        $detalle_trabajo = $_POST['detalle_trabajo'];
        $materiales_usados = $_POST['materiales_usados'];
        $finalizado_por = $_SESSION['usuario_id'] ?? null;
        
        // Procesar fotos de finalización
        $fotosFin = [];
        
        // Manejar archivos subidos
        if (isset($_FILES['fotos_fin']) && !empty($_FILES['fotos_fin']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/tickets/finalizacion/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $totalFiles = count($_FILES['fotos_fin']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['fotos_fin']['error'][$i] === UPLOAD_ERR_OK) {
                    $extension = pathinfo($_FILES['fotos_fin']['name'][$i], PATHINFO_EXTENSION);
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array(strtolower($extension), $allowedExtensions)) {
                        continue;
                    }
                    
                    $foto = 'fin_' . $ticket_id . '_' . time() . '_' . $i . '.' . $extension;
                    if (move_uploaded_file($_FILES['fotos_fin']['tmp_name'][$i], $uploadDir . $foto)) {
                        $fotosFin[] = $foto;
                    }
                }
            }
        }
        
        // Manejar fotos de cámara
        if (!empty($_POST['fotos_camera_fin'])) {
            $uploadDir = __DIR__ . '/../uploads/tickets/finalizacion/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fotosCamera = json_decode($_POST['fotos_camera_fin'], true);
            if (is_array($fotosCamera)) {
                foreach ($fotosCamera as $index => $img_data) {
                    $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
                    $img_data = str_replace(' ', '+', $img_data);
                    $data_decoded = base64_decode($img_data);
                    
                    if ($data_decoded !== false) {
                        $foto = 'fin_camera_' . $ticket_id . '_' . time() . '_' . $index . '.jpg';
                        if (file_put_contents($uploadDir . $foto, $data_decoded)) {
                            $fotosFin[] = $foto;
                        }
                    }
                }
            }
        }
        
        $ticket = new Ticket();
        
        // Finalizar ticket con detalles
        $ticket->finalizarTicket($ticket_id, $detalle_trabajo, $materiales_usados, $finalizado_por);
        
        // Guardar fotos de finalización
        if (!empty($fotosFin)) {
            $ticket->addFotosFinalizacion($ticket_id, $fotosFin);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Ticket finalizado correctamente con detalles y fotos',
            'status' => 'finalizado',
            'fotos_agregadas' => count($fotosFin),
            'tipo_finalizacion' => 'completa'
        ]);
        
    } else {
        // Finalización simple (tu código original)
        if (!isset($_POST['id'])) {
            throw new Exception('ID de ticket requerido');
        }
        
        $ticket_id = intval($_POST['id']);
        $status = 'finalizado';
        
        $ticket = new Ticket();
        
        // Actualizar ticket con estado finalizado
        $data = [
            'status' => $status,
        ];
        
        $ticket->update($ticket_id, $data);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Ticket finalizado exitosamente',
            'status' => $status,
            'tipo_finalizacion' => 'simple'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>