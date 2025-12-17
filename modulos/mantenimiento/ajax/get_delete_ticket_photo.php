<?php
header('Content-Type: application/json');
session_start();

require_once '../models/Ticket.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/funciones.php';

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificar permisos
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$puedeEditar = $esAdmin || verificarAccesoCargo([14, 16, 35]);

if (!$puedeEditar) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar fotos']);
    exit;
}

if (!isset($_POST['foto_id']) || !isset($_POST['ticket_id'])) {
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos']);
    exit;
}

try {
    $ticket_model = new Ticket();
    
    // Obtener información de la foto antes de eliminarla
    $fotos = $ticket_model->getFotos($_POST['ticket_id']);
    $fotoAEliminar = null;
    
    foreach ($fotos as $foto) {
        if ($foto['id'] == $_POST['foto_id']) {
            $fotoAEliminar = $foto;
            break;
        }
    }
    
    if (!$fotoAEliminar) {
        echo json_encode(['success' => false, 'message' => 'Foto no encontrada']);
        exit;
    }
    
    // Eliminar el archivo físico
    $rutaArchivo = __DIR__ . '/../uploads/tickets/' . $fotoAEliminar['foto'];
    if (file_exists($rutaArchivo)) {
        unlink($rutaArchivo);
    }
    
    // Eliminar el registro de la base de datos
    $ticket_model->deleteFoto($_POST['foto_id']);
    
    echo json_encode(['success' => true, 'message' => 'Foto eliminada correctamente']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar foto: ' . $e->getMessage()]);
}
?>