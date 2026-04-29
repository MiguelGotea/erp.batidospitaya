<?php
require_once 'auth.php';
require_once 'funciones.php';
require_once 'conexion.php';

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