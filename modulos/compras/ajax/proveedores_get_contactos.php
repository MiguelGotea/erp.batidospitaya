<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id_proveedor = isset($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
    
    if ($id_proveedor <= 0) {
        throw new Exception('ID de proveedor inválido');
    }
    
    $sql = "SELECT c.*, o.Nombre as registrado_por_nombre, o.Apellido as registrado_por_apellido
            FROM contacto_proveedores c
            LEFT JOIN Operarios o ON c.registrado_por = o.CodOperario
            WHERE c.id_proveedor = ?
            ORDER BY c.principal DESC, c.nombre ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_proveedor]);
    $contactos = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'contactos' => $contactos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>