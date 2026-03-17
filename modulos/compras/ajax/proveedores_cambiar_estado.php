<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    
    // Obtener el ID del usuario de forma segura
    $usuarioId = isset($usuario['CodOperario']) ? $usuario['CodOperario'] : null;
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $vigente = isset($_POST['vigente']) ? (int)$_POST['vigente'] : 0;
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Obtener datos anteriores para el historial
    $stmtAntes = $conn->prepare("SELECT nombre, vigente FROM proveedores WHERE id = ?");
    $stmtAntes->execute([$id]);
    $datosAnteriores = $stmtAntes->fetch();
    
    if (!$datosAnteriores) {
        throw new Exception('Proveedor no encontrado');
    }
    
    // Actualizar estado
    $sql = "UPDATE proveedores 
            SET vigente = :vigente,
                modificado_por = :modificado_por
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':vigente', $vigente, PDO::PARAM_INT);
    $stmt->bindValue(':modificado_por', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Registrar en historial
    $descripcion = "Estado cambiado de " . ($datosAnteriores['vigente'] ? 'Vigente' : 'No Vigente') . 
                   " a " . ($vigente ? 'Vigente' : 'No Vigente');
    
    $sqlHistorial = "INSERT INTO historial_proveedores 
                     (id_proveedor, tipo_cambio, descripcion, datos_anteriores, datos_nuevos, usuario_cambio) 
                     VALUES (?, 'vigencia', ?, ?, ?, ?)";
    
    $stmtHistorial = $conn->prepare($sqlHistorial);
    $stmtHistorial->execute([
        $id,
        $descripcion,
        json_encode(['vigente' => $datosAnteriores['vigente']]),
        json_encode(['vigente' => $vigente]),
        $usuarioId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>