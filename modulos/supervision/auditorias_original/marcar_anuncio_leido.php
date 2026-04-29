<?php
require_once 'auth.php';
require_once 'funciones.php';
require_once 'conexion.php';

verificarAutenticacion();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || !isset($_POST['announcement_id'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $userId = $_SESSION['usuario_id'];
    $announcementId = (int)$_POST['announcement_id'];
    
    // Marcar este anuncio como leído
    $marcado = marcarAnuncioComoLeido($announcementId, $userId);
    
    if ($marcado) {
        // Obtener el nuevo total de no leídos
        $nuevoTotal = obtenerCantidadAnunciosNoLeidos($userId);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Anuncio marcado como leído',
            'nuevo_total' => $nuevoTotal
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo marcar como leído']);
    }
    
} catch (Exception $e) {
    error_log("Error en marcar_anuncio_leido.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>