<?php
// /public_html/modulos/compras/ajax/proveedores_guardar_basicos.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    
    // CORRECCIÓN: El campo correcto es CodOperario
    $usuarioId = isset($usuario['CodOperario']) ? $usuario['CodOperario'] : null;
    
    if (!$usuarioId) {
        throw new Exception('Usuario no autenticado. Por favor inicie sesión nuevamente.');
    }
    
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $ruc_nit = isset($_POST['ruc_nit']) ? trim($_POST['ruc_nit']) : null;
    $direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : null;
    $comprasucursal = isset($_POST['comprasucursal']) && $_POST['comprasucursal'] !== '' ? (int)$_POST['comprasucursal'] : null;
    $vigente = isset($_POST['vigente']) ? (int)$_POST['vigente'] : 1;
    $notas_internas = isset($_POST['notas_internas']) ? trim($_POST['notas_internas']) : null;
    $tipos_pago = isset($_POST['tipos_pago']) ? json_decode($_POST['tipos_pago'], true) : [];
    
    // Validaciones
    if (empty($nombre)) {
        throw new Exception('El nombre del proveedor es requerido');
    }
    
    $conn->beginTransaction();
    
    if ($id > 0) {
        // EDITAR
        $stmtAntes = $conn->prepare("SELECT * FROM proveedores WHERE id = ?");
        $stmtAntes->execute([$id]);
        $datosAnteriores = $stmtAntes->fetch();
        
        if (!$datosAnteriores) {
            throw new Exception('Proveedor no encontrado');
        }
        
        $sql = "UPDATE proveedores 
                SET nombre = :nombre,
                    ruc_nit = :ruc_nit,
                    direccion = :direccion,
                    comprasucursal = :comprasucursal,
                    vigente = :vigente,
                    notas_internas = :notas_internas,
                    modificado_por = :modificado_por
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':ruc_nit', $ruc_nit);
        $stmt->bindValue(':direccion', $direccion);
        $stmt->bindValue(':comprasucursal', $comprasucursal, PDO::PARAM_INT);
        $stmt->bindValue(':vigente', $vigente, PDO::PARAM_INT);
        $stmt->bindValue(':notas_internas', $notas_internas);
        $stmt->bindValue(':modificado_por', $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registrar cambios en historial
        $cambios = [];
        if ($datosAnteriores['nombre'] != $nombre) $cambios[] = 'nombre';
        if ($datosAnteriores['ruc_nit'] != $ruc_nit) $cambios[] = 'RUC/NIT';
        if ($datosAnteriores['direccion'] != $direccion) $cambios[] = 'dirección';
        if ($datosAnteriores['comprasucursal'] != $comprasucursal) $cambios[] = 'sucursal';
        if ($datosAnteriores['notas_internas'] != $notas_internas) $cambios[] = 'notas internas';
        
        if (!empty($cambios)) {
            $descripcion = "Datos básicos actualizados: " . implode(', ', $cambios);
            $sqlHistorial = "INSERT INTO historial_proveedores 
                             (id_proveedor, tipo_cambio, descripcion, datos_anteriores, datos_nuevos, usuario_cambio) 
                             VALUES (?, 'datos_basicos', ?, ?, ?, ?)";
            
            $stmtHistorial = $conn->prepare($sqlHistorial);
            $stmtHistorial->execute([
                $id,
                $descripcion,
                json_encode($datosAnteriores),
                json_encode($_POST),
                $usuarioId
            ]);
        }
        
        $idProveedor = $id;
        $mensaje = 'Proveedor actualizado exitosamente';
        
    } else {
        // CREAR
        $sql = "INSERT INTO proveedores 
                (nombre, ruc_nit, direccion, comprasucursal, vigente, notas_internas, registrado_por) 
                VALUES (:nombre, :ruc_nit, :direccion, :comprasucursal, :vigente, :notas_internas, :registrado_por)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':nombre', $nombre);
        $stmt->bindValue(':ruc_nit', $ruc_nit);
        $stmt->bindValue(':direccion', $direccion);
        $stmt->bindValue(':comprasucursal', $comprasucursal, PDO::PARAM_INT);
        $stmt->bindValue(':vigente', $vigente, PDO::PARAM_INT);
        $stmt->bindValue(':notas_internas', $notas_internas);
        $stmt->bindValue(':registrado_por', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
        
        $idProveedor = $conn->lastInsertId();
        
        // Registrar creación en historial
        $sqlHistorial = "INSERT INTO historial_proveedores 
                         (id_proveedor, tipo_cambio, descripcion, datos_nuevos, usuario_cambio) 
                         VALUES (?, 'datos_basicos', 'Proveedor creado', ?, ?)";
        
        $stmtHistorial = $conn->prepare($sqlHistorial);
        $stmtHistorial->execute([
            $idProveedor,
            json_encode($_POST),
            $usuarioId
        ]);
        
        $mensaje = 'Proveedor creado exitosamente';
    }
    
    // Actualizar tipos de pago
    $stmtDelete = $conn->prepare("DELETE FROM proveedor_tipo_pago WHERE id_proveedor = ?");
    $stmtDelete->execute([$idProveedor]);
    
    if (!empty($tipos_pago)) {
        $sqlTipoPago = "INSERT INTO proveedor_tipo_pago (id_proveedor, id_tipo_pago, asignado_por) VALUES (?, ?, ?)";
        $stmtTipoPago = $conn->prepare($sqlTipoPago);
        
        foreach ($tipos_pago as $idTipoPago) {
            $stmtTipoPago->execute([$idProveedor, $idTipoPago, $usuarioId]);
        }
        
        if ($id > 0) {
            $sqlHistorial = "INSERT INTO historial_proveedores 
                             (id_proveedor, tipo_cambio, descripcion, usuario_cambio) 
                             VALUES (?, 'tipo_pago', 'Tipos de pago actualizados', ?)";
            
            $stmtHistorial = $conn->prepare($sqlHistorial);
            $stmtHistorial->execute([$idProveedor, $usuarioId]);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'id_proveedor' => $idProveedor
    ]);
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
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