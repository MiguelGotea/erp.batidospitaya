<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

verificarAutenticacion();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit();
}

try {
    $userId = $_SESSION['usuario_id'];
    $marcados = marcarTodosAnunciosComoLeidos($userId);
    
    echo json_encode([
        'success' => true, 
        'message' => "Se marcaron $marcados anuncios como leídos",
        'marcados' => $marcados
    ]);
    
} catch (Exception $e) {
    error_log("Error en marcar_anuncios_leidos.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>
