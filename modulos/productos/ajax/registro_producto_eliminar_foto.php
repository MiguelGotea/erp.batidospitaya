<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)$data['id'];
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Obtener ruta del archivo
    $sqlRuta = "SELECT ruta FROM fotos_presentacion_producto WHERE id = :id";
    $stmtRuta = $conn->prepare($sqlRuta);
    $stmtRuta->execute([':id' => $id]);
    $ruta = $stmtRuta->fetchColumn();
    
    if ($ruta) {
        $rutaCompleta = '../../../' . $ruta;
        if (file_exists($rutaCompleta)) {
            unlink($rutaCompleta);
        }
    }
    
    // Eliminar registro
    $sql = "DELETE FROM fotos_presentacion_producto WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Foto eliminada exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>