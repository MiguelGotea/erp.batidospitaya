<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';
session_start();

header('Content-Type: application/json');

try {
    // Verificar sesión
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('Sesión no válida');
    }
    
    $cargoOperario = $_SESSION['cargo_cod'];
    
    // Verificar permiso
    if (!tienePermiso('unidades_conversion_productos', 'vista', $cargoOperario)) {
        throw new Exception('No tiene permiso para ver esta herramienta');
    }
    
    $excluir_id = isset($_POST['excluir_id']) ? (int)$_POST['excluir_id'] : 0;
    
    // Consulta de unidades
    $sql = "SELECT id, nombre 
            FROM unidad_producto";
    
    if ($excluir_id > 0) {
        $sql .= " WHERE id != :excluir_id";
    }
    
    $sql .= " ORDER BY nombre ASC";
    
    $stmt = $conn->prepare($sql);
    
    if ($excluir_id > 0) {
        $stmt->bindValue(':excluir_id', $excluir_id, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $unidades = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'unidades' => $unidades
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}