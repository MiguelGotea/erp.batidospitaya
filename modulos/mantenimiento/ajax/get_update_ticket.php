<?php
header('Content-Type: application/json');
require_once '../models/Ticket.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    if (!isset($_POST['id'])) {
        throw new Exception('ID de ticket requerido');
    }
    
    $ticket = new Ticket();
    
    // Preparar datos para actualizar
    $data = [];
    
    if (isset($_POST['titulo']) && !empty(trim($_POST['titulo']))) {
        $data['titulo'] = trim($_POST['titulo']);
    }
    
    if (isset($_POST['descripcion']) && !empty(trim($_POST['descripcion']))) {
        $data['descripcion'] = trim($_POST['descripcion']);
    }

    if (isset($_POST['area_equipo']) && !empty(trim($_POST['area_equipo']))) {
        $data['area_equipo'] = trim($_POST['area_equipo']);
    }

    if (isset($_POST['nivel_urgencia']) && is_numeric($_POST['nivel_urgencia'])) {
        $urgencia = intval($_POST['nivel_urgencia']);
        if ($urgencia >= 1 && $urgencia <= 4) {
            $data['nivel_urgencia'] = $urgencia;
        }
    }
    
    if (isset($_POST['status']) && in_array($_POST['status'], ['solicitado', 'clasificado', 'agendado', 'finalizado'])) {
        $data['status'] = $_POST['status'];
    }
    
    if (isset($_POST['tipo_caso_id'])) {
        if (empty($_POST['tipo_caso_id'])) {
            $data['tipo_caso_id'] = null;
        } else if (is_numeric($_POST['tipo_caso_id'])) {
            $data['tipo_caso_id'] = intval($_POST['tipo_caso_id']);
        }
    }
    
    if (isset($_POST['fecha_inicio'])) {
        if (empty($_POST['fecha_inicio'])) {
            $data['fecha_inicio'] = null;
        } else if (strtotime($_POST['fecha_inicio'])) {
            $data['fecha_inicio'] = $_POST['fecha_inicio'];
        }
    }
    
    if (isset($_POST['fecha_final'])) {
        if (empty($_POST['fecha_final'])) {
            $data['fecha_final'] = null;
        } else if (strtotime($_POST['fecha_final'])) {
            $data['fecha_final'] = $_POST['fecha_final'];
        }
    }
    
    // Validaciones
    if (isset($data['fecha_inicio']) && isset($data['fecha_final']) && 
        $data['fecha_inicio'] && $data['fecha_final'] && 
        $data['fecha_inicio'] > $data['fecha_final']) {
        throw new Exception('La fecha de inicio no puede ser mayor a la fecha final');
    }
    
    if (empty($data)) {
        throw new Exception('No hay datos para actualizar');
    }
    
    // Actualizar ticket
    $ticket->update($_POST['id'], $data);
    
    // ==================== MANEJO DE NUEVAS FOTOS ====================
    $nuevasFotos = [];
    $totalNuevasFotos = 0;
    
    // Procesar archivos subidos
    if (isset($_FILES['nuevas_fotos']) && !empty($_FILES['nuevas_fotos']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/tickets/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $totalFiles = count($_FILES['nuevas_fotos']['name']);
        for ($i = 0; $i < $totalFiles; $i++) {
            if ($_FILES['nuevas_fotos']['error'][$i] === UPLOAD_ERR_OK) {
                $extension = pathinfo($_FILES['nuevas_fotos']['name'][$i], PATHINFO_EXTENSION);
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array(strtolower($extension), $allowedExtensions)) {
                    continue; // Saltar archivos no permitidos
                }
                
                $foto = 'ticket_' . $_POST['id'] . '_' . time() . '_' . $i . '.' . $extension;
                if (move_uploaded_file($_FILES['nuevas_fotos']['tmp_name'][$i], $uploadDir . $foto)) {
                    $nuevasFotos[] = $foto;
                    $totalNuevasFotos++;
                }
            }
        }
    }
    
    // Procesar fotos de cámara (base64)
    if (!empty($_POST['nuevas_fotos_camera'])) {
        $uploadDir = __DIR__ . '/../uploads/tickets/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fotosCamera = json_decode($_POST['nuevas_fotos_camera'], true);
        if (is_array($fotosCamera)) {
            foreach ($fotosCamera as $index => $img_data) {
                $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
                $img_data = str_replace(' ', '+', $img_data);
                $data_decoded = base64_decode($img_data);
                
                if ($data_decoded !== false) {
                    $foto = 'camera_' . $_POST['id'] . '_' . time() . '_' . $index . '.jpg';
                    if (file_put_contents($uploadDir . $foto, $data_decoded)) {
                        $nuevasFotos[] = $foto;
                        $totalNuevasFotos++;
                    }
                }
            }
        }
    }
    
    // Guardar nuevas fotos en la base de datos
    if (!empty($nuevasFotos)) {
        $ticket->addFotos($_POST['id'], $nuevasFotos);
    }
    // ==================== FIN MANEJO DE NUEVAS FOTOS ====================
    
    echo json_encode([
        'success' => true, 
        'message' => 'Ticket actualizado exitosamente',
        'nuevas_fotos_agregadas' => $totalNuevasFotos
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>