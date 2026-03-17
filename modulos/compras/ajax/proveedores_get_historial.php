<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id_proveedor = isset($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
    
    if ($id_proveedor <= 0) {
        throw new Exception('ID de proveedor inválido');
    }
    
    $sql = "SELECT h.*, 
                   o.Nombre as usuario_nombre, 
                   o.Apellido as usuario_apellido
            FROM historial_proveedores h
            LEFT JOIN Operarios o ON h.usuario_cambio = o.CodOperario
            WHERE h.id_proveedor = ?
            ORDER BY h.fecha_cambio DESC
            LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_proveedor]);
    $historial = $stmt->fetchAll();
    
    // Formatear nombres de usuario
    foreach ($historial as &$item) {
        if ($item['usuario_nombre']) {
            $item['usuario_nombre'] = trim($item['usuario_nombre'] . ' ' . $item['usuario_apellido']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'historial' => $historial
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>