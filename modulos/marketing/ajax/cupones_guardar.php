<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $numero_cupon = isset($_POST['numero_cupon']) ? trim($_POST['numero_cupon']) : '';
    $monto = isset($_POST['monto']) ? (int)$_POST['monto'] : 0;
    $fecha_caducidad = isset($_POST['fecha_caducidad']) ? $_POST['fecha_caducidad'] : '';
    $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
    
    // Validaciones
    if (empty($numero_cupon)) {
        throw new Exception('El número de cupón es requerido');
    }
    
    if ($monto <= 0) {
        throw new Exception('El monto debe ser mayor a 0');
    }
    
    if (empty($fecha_caducidad)) {
        throw new Exception('La fecha de caducidad es requerida');
    }
    
    if ($accion === 'crear') {
        // Verificar que no exista un cupón con el mismo número
        $sqlCheck = "SELECT COUNT(*) as total FROM cupones_sucursales WHERE numero_cupon = :numero_cupon";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bindValue(':numero_cupon', $numero_cupon);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetch()['total'] > 0) {
            throw new Exception('Ya existe un cupón con ese número');
        }
        
        // Insertar nuevo cupón - siempre con aplicado = 0
        $sql = "INSERT INTO cupones_sucursales (numero_cupon, monto, fecha_caducidad, aplicado, observaciones) 
                VALUES (:numero_cupon, :monto, :fecha_caducidad, 0, :observaciones)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':numero_cupon', $numero_cupon);
        $stmt->bindValue(':monto', $monto, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_caducidad', $fecha_caducidad);
        $stmt->bindValue(':observaciones', $observaciones);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cupón creado exitosamente'
        ]);
        
    } elseif ($accion === 'editar') {
        if ($id <= 0) {
            throw new Exception('ID inválido');
        }
        
        // Verificar que el cupón no esté aplicado
        $sqlCheckAplicado = "SELECT aplicado FROM cupones_sucursales WHERE id = :id";
        $stmtCheckAplicado = $conn->prepare($sqlCheckAplicado);
        $stmtCheckAplicado->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtCheckAplicado->execute();
        $cuponActual = $stmtCheckAplicado->fetch();
        
        if ($cuponActual && $cuponActual['aplicado'] == 1) {
            throw new Exception('No se puede editar un cupón que ya ha sido aplicado');
        }
        
        // Verificar que no exista otro cupón con el mismo número
        $sqlCheck = "SELECT COUNT(*) as total FROM cupones_sucursales 
                     WHERE numero_cupon = :numero_cupon AND id != :id";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bindValue(':numero_cupon', $numero_cupon);
        $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetch()['total'] > 0) {
            throw new Exception('Ya existe un cupón con ese número');
        }
        
        // Actualizar cupón - mantener aplicado = 0
        $sql = "UPDATE cupones_sucursales 
                SET numero_cupon = :numero_cupon,
                    monto = :monto,
                    fecha_caducidad = :fecha_caducidad,
                    observaciones = :observaciones
                WHERE id = :id AND aplicado = 0";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':numero_cupon', $numero_cupon);
        $stmt->bindValue(':monto', $monto, PDO::PARAM_INT);
        $stmt->bindValue(':fecha_caducidad', $fecha_caducidad);
        $stmt->bindValue(':observaciones', $observaciones);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cupón actualizado exitosamente'
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