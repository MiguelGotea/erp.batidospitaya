<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

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
