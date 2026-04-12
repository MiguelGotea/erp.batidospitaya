<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    // Verificar autenticación
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No autorizado');
    }
    
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    // Verificar permisos según acción
    if ($accion === 'crear') {
        if (!tienePermiso('producto_maestro', 'nuevo_registro', $cargoOperario)) {
            throw new Exception('No tiene permisos para crear productos');
        }
    } elseif ($accion === 'editar') {
        if (!tienePermiso('producto_maestro', 'editar', $cargoOperario)) {
            throw new Exception('No tiene permisos para editar productos');
        }
    }
    
    $nombre = isset($_POST['Nombre']) ? trim($_POST['Nombre']) : '';
    $sku = isset($_POST['SKU']) ? trim($_POST['SKU']) : '';
    $descripcion = isset($_POST['Descripcion']) ? trim($_POST['Descripcion']) : '';
    $id_categoria = isset($_POST['Id_categoria']) ? (int)$_POST['Id_categoria'] : 0;
    $estado = isset($_POST['Estado']) ? (int)$_POST['Estado'] : 1;
    $usuario_id = $_SESSION['usuario_id'];
    
    // Validaciones
    if (empty($nombre)) {
        throw new Exception('El nombre es requerido');
    }
    
    if (empty($sku)) {
        throw new Exception('El SKU es requerido');
    }
    
    if ($id_categoria <= 0) {
        throw new Exception('Debe seleccionar una categoría');
    }
    
    // Verificar que la categoría existe
    $sqlCheckCat = "SELECT COUNT(*) as total FROM categoria_producto_maestro WHERE id = :id_categoria";
    $stmtCheckCat = $conn->prepare($sqlCheckCat);
    $stmtCheckCat->bindValue(':id_categoria', $id_categoria, PDO::PARAM_INT);
    $stmtCheckCat->execute();
    
    if ($stmtCheckCat->fetch()['total'] == 0) {
        throw new Exception('La categoría seleccionada no existe');
    }
    
    if ($accion === 'crear') {
        // Verificar que no exista un producto con el mismo SKU
        $sqlCheckSKU = "SELECT COUNT(*) as total FROM producto_maestro WHERE SKU = :sku";
        $stmtCheckSKU = $conn->prepare($sqlCheckSKU);
        $stmtCheckSKU->bindValue(':sku', $sku);
        $stmtCheckSKU->execute();
        
        if ($stmtCheckSKU->fetch()['total'] > 0) {
            throw new Exception('Ya existe un producto con ese SKU');
        }
        
        // Insertar nuevo producto
        $sql = "INSERT INTO producto_maestro (Nombre, SKU, Descripcion, Id_categoria, Estado, usuario_creacion) 
                VALUES (:nombre, :sku, :descripcion, :id_categoria, :estado, :usuario_creacion)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':sku', $sku);
        $stmt->bindValue(':descripcion', $descripcion);
        $stmt->bindValue(':id_categoria', $id_categoria, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_creacion', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Producto creado exitosamente'
        ]);
        
    } elseif ($accion === 'editar') {
        if ($id <= 0) {
            throw new Exception('ID inválido');
        }
        
        // Verificar que no exista otro producto con el mismo SKU
        $sqlCheckSKU = "SELECT COUNT(*) as total FROM producto_maestro 
                        WHERE SKU = :sku AND id != :id";
        $stmtCheckSKU = $conn->prepare($sqlCheckSKU);
        $stmtCheckSKU->bindValue(':sku', $sku);
        $stmtCheckSKU->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtCheckSKU->execute();
        
        if ($stmtCheckSKU->fetch()['total'] > 0) {
            throw new Exception('Ya existe un producto con ese SKU');
        }
        
        // Actualizar producto
        $sql = "UPDATE producto_maestro 
                SET Nombre = :nombre,
                    SKU = :sku,
                    Descripcion = :descripcion,
                    Id_categoria = :id_categoria,
                    Estado = :estado,
                    usuario_modificacion = :usuario_modificacion
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':sku', $sku);
        $stmt->bindValue(':descripcion', $descripcion);
        $stmt->bindValue(':id_categoria', $id_categoria, PDO::PARAM_INT);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_INT);
        $stmt->bindValue(':usuario_modificacion', $usuario_id, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Producto actualizado exitosamente'
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
?>