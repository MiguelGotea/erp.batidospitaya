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
    
    $sql = "SELECT c.*, o.Nombre as registrado_por_nombre, o.Apellido as registrado_por_apellido
            FROM cuenta_proveedor c
            LEFT JOIN Operarios o ON c.registrado_por = o.CodOperario
            WHERE c.id_proveedor = ?
            ORDER BY c.principal DESC, c.fecha_registro DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id_proveedor]);
    $cuentas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'cuentas' => $cuentas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>