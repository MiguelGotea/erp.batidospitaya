<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    
    // Obtener el ID del usuario de forma segura
    $usuarioId = isset($usuario['CodOperario']) ? $usuario['CodOperario'] : null;
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $id_proveedor = isset($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : null;
    $cargo = isset($_POST['cargo']) ? trim($_POST['cargo']) : null;
    $principal = isset($_POST['principal']) ? 1 : 0;
    
    if (empty($nombre)) {
        throw new Exception('El nombre del contacto es requerido');
    }
    
    if ($id_proveedor <= 0) {
        throw new Exception('ID de proveedor inválido');
    }
    
    $conn->beginTransaction();
    
    // Si se marca como principal, desmarcar otros contactos principales
    if ($principal == 1) {
        $sqlUpdate = "UPDATE contacto_proveedores SET principal = 0 WHERE id_proveedor = ?";
        if ($id > 0) {
            $sqlUpdate .= " AND id != ?";
        }
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if ($id > 0) {
            $stmtUpdate->execute([$id_proveedor, $id]);
        } else {
            $stmtUpdate->execute([$id_proveedor]);
        }
    }
    
    if ($id > 0) {
        // EDITAR
        $sql = "UPDATE contacto_proveedores 
                SET nombre = :nombre,
                    telefono = :telefono,
                    cargo = :cargo,
                    principal = :principal
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':telefono', $telefono);
        $stmt->bindValue(':cargo', $cargo);
        $stmt->bindValue(':principal', $principal, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registrar en historial
        $sqlHistorial = "INSERT INTO historial_proveedores 
                         (id_proveedor, tipo_cambio, descripcion, usuario_cambio) 
                         VALUES (?, 'contacto', ?, ?)";
        
        $stmtHistorial = $conn->prepare($sqlHistorial);
        $stmtHistorial->execute([
            $id_proveedor,
            "Contacto actualizado: $nombre",
            $usuarioId
        ]);
        
        $mensaje = 'Contacto actualizado exitosamente';
        
    } else {
        // CREAR
        $sql = "INSERT INTO contacto_proveedores 
                (id_proveedor, nombre, telefono, cargo, principal, registrado_por) 
                VALUES (:id_proveedor, :nombre, :telefono, :cargo, :principal, :registrado_por)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_proveedor', $id_proveedor, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':telefono', $telefono);
        $stmt->bindValue(':cargo', $cargo);
        $stmt->bindValue(':principal', $principal, PDO::PARAM_INT);
        $stmt->bindValue(':registrado_por', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registrar en historial
        $sqlHistorial = "INSERT INTO historial_proveedores 
                         (id_proveedor, tipo_cambio, descripcion, usuario_cambio) 
                         VALUES (?, 'contacto', ?, ?)";
        
        $stmtHistorial = $conn->prepare($sqlHistorial);
        $stmtHistorial->execute([
            $id_proveedor,
            "Nuevo contacto agregado: $nombre",
            $usuarioId
        ]);
        
        $mensaje = 'Contacto agregado exitosamente';
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>