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
    $id_proveedor = isset($_POST['id_proveedor']) ? (int)$_POST['id_proveedor'] : 0;
    $numero_cuenta = isset($_POST['numero_cuenta']) ? trim($_POST['numero_cuenta']) : '';
    $titular = isset($_POST['titular']) ? trim($_POST['titular']) : '';
    $banco = isset($_POST['banco']) ? trim($_POST['banco']) : '';
    $moneda = isset($_POST['moneda']) ? trim($_POST['moneda']) : 'Córdoba';
    $principal = isset($_POST['principal']) ? 1 : 0;
    
    if (empty($numero_cuenta)) {
        throw new Exception('El número de cuenta es requerido');
    }
    
    if (empty($titular)) {
        throw new Exception('El titular es requerido');
    }
    
    if (empty($banco)) {
        throw new Exception('El banco es requerido');
    }
    
    if ($id_proveedor <= 0) {
        throw new Exception('ID de proveedor inválido');
    }
    
    $conn->beginTransaction();
    
    // Si se marca como principal, desmarcar otras cuentas principales
    if ($principal == 1) {
        $sqlUpdate = "UPDATE cuenta_proveedor SET principal = 0 WHERE id_proveedor = ?";
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
        $sql = "UPDATE cuenta_proveedor 
                SET numero_cuenta = :numero_cuenta,
                    titular = :titular,
                    banco = :banco,
                    moneda = :moneda,
                    principal = :principal
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':numero_cuenta', $numero_cuenta);
        $stmt->bindValue(':titular', $titular);
        $stmt->bindValue(':banco', $banco);
        $stmt->bindValue(':moneda', $moneda);
        $stmt->bindValue(':principal', $principal, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registrar en historial
        $sqlHistorial = "INSERT INTO historial_proveedores 
                         (id_proveedor, tipo_cambio, descripcion, usuario_cambio) 
                         VALUES (?, 'cuenta', ?, ?)";
        
        $stmtHistorial = $conn->prepare($sqlHistorial);
        $stmtHistorial->execute([
            $id_proveedor,
            "Cuenta bancaria actualizada: $banco - $numero_cuenta",
            $usuarioId
        ]);
        
        $mensaje = 'Cuenta bancaria actualizada exitosamente';
        
    } else {
        // CREAR
        $sql = "INSERT INTO cuenta_proveedor 
                (id_proveedor, numero_cuenta, titular, banco, moneda, principal, registrado_por) 
                VALUES (:id_proveedor, :numero_cuenta, :titular, :banco, :moneda, :principal, :registrado_por)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_proveedor', $id_proveedor, PDO::PARAM_INT);
        $stmt->bindValue(':numero_cuenta', $numero_cuenta);
        $stmt->bindValue(':titular', $titular);
        $stmt->bindValue(':banco', $banco);
        $stmt->bindValue(':moneda', $moneda);
        $stmt->bindValue(':principal', $principal, PDO::PARAM_INT);
        $stmt->bindValue(':registrado_por', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registrar en historial
        $sqlHistorial = "INSERT INTO historial_proveedores 
                         (id_proveedor, tipo_cambio, descripcion, usuario_cambio) 
                         VALUES (?, 'cuenta', ?, ?)";
        
        $stmtHistorial = $conn->prepare($sqlHistorial);
        $stmtHistorial->execute([
            $id_proveedor,
            "Nueva cuenta bancaria agregada: $banco - $numero_cuenta",
            $usuarioId
        ]);
        
        $mensaje = 'Cuenta bancaria agregada exitosamente';
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