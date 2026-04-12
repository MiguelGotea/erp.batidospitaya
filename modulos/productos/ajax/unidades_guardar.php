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
    
    $codOperario = $_SESSION['usuario_id'];
    $cargoOperario = $_SESSION['cargo_cod'];
    
    // Verificar permiso de crear
    if (!tienePermiso('unidades_conversion_productos', 'nuevo_registro', $cargoOperario)) {
        throw new Exception('No tiene permiso para crear unidades');
    }
    
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
    
    // Validaciones
    if (empty($nombre)) {
        throw new Exception('El nombre de la unidad es requerido');
    }
    
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    
    if (strlen($observaciones) > 255) {
        throw new Exception('Las observaciones no pueden exceder 255 caracteres');
    }
    
    if ($accion === 'crear') {
        // Verificar que no exista una unidad con el mismo nombre
        $sqlCheck = "SELECT COUNT(*) as total FROM unidad_producto WHERE nombre = :nombre";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bindValue(':nombre', $nombre);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetch()['total'] > 0) {
            throw new Exception('Ya existe una unidad con ese nombre');
        }
        
        // Insertar nueva unidad
        $sql = "INSERT INTO unidad_producto (nombre, observaciones, usuario_creacion) 
                VALUES (:nombre, :observaciones, :usuario_creacion)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':observaciones', $observaciones);
        $stmt->bindValue(':usuario_creacion', $codOperario, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Nueva unidad registrada exitosamente'
        ]);
        
    } elseif ($accion === 'editar') {
        if ($id <= 0) {
            throw new Exception('ID inválido');
        }
        
        // Verificar que no exista otra unidad con el mismo nombre
        $sqlCheck = "SELECT COUNT(*) as total FROM unidad_producto 
                     WHERE nombre = :nombre AND id != :id";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bindValue(':nombre', $nombre);
        $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetch()['total'] > 0) {
            throw new Exception('Ya existe una unidad con ese nombre');
        }
        
        // Actualizar unidad
        $sql = "UPDATE unidad_producto 
                SET nombre = :nombre,
                    observaciones = :observaciones,
                    fecha_modificacion = NOW(),
                    usuario_modificacion = :usuario_modificacion
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':observaciones', $observaciones);
        $stmt->bindValue(':usuario_modificacion', $codOperario, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Unidad actualizada exitosamente'
        ]);
        
    } else {
        throw new Exception('Acción inválida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}