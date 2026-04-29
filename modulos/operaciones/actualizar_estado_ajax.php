<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

header('Content-Type: application/json');

// Verificar que sea petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar permisos
if (!verificarAccesoCargo([11, 16, 13, 28, 39, 30, 37]) && !$esAdmin) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    $estado = $_POST['estado'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    
    if (!$id || !$estado) {
        throw new Exception('Datos incompletos');
    }
    
    // Validar estado
    if (!in_array($estado, ['Justificado', 'No Válido'])) {
        throw new Exception('Estado no válido');
    }
    
    // Actualizar en la base de datos
    $sql = "UPDATE TardanzasManuales 
            SET estado = ?, 
                observaciones = ?,
                actualizado_por = ?,
                fecha_actualizacion = NOW()
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $resultado = $stmt->execute([
        $estado, 
        $observaciones,
        $_SESSION['usuario_id'],
        $id
    ]);
    
    if ($resultado) {
        // Registrar en log
        registrarLogSistema(
            'ACTUALIZAR_TARDANZA',
            "Estado de tardanza actualizado a {$estado}",
            [
                'tardanza_id' => $id,
                'nuevo_estado' => $estado,
                'usuario_id' => $_SESSION['usuario_id']
            ]
        );
        
        echo json_encode([
            'success' => true, 
            'message' => 'Estado actualizado correctamente',
            'estado' => $estado
        ]);
    } else {
        throw new Exception('Error al actualizar el estado');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

function registrarLogSistema($tipo, $mensaje, $datos = []) {
    global $conn;
    
    try {
        $sql = "INSERT INTO logs_sistema (tipo, mensaje, datos, fecha) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$tipo, $mensaje, json_encode($datos)]);
    } catch (Exception $e) {
        error_log("Error al registrar log: " . $e->getMessage());
    }
}