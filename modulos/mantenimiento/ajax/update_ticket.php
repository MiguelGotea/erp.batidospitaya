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
    
    $ticket->update($_POST['id'], $data);
    
    echo json_encode(['success' => true, 'message' => 'Ticket actualizado exitosamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>