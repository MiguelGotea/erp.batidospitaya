<?php
require_once '../../../core/auth/auth.php';
// require_once '../../../core/helpers/funciones.php'; // Ya se incluye en auth.php

require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

// Verificar que sea petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;
// Verificar permisos (Aprobación de feriados)
if (!tienePermiso('gestion_feriados', 'aprobar', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    $estado = $_POST['estado'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    
    if (!$id) {
        throw new Exception('ID del feriado no especificado');
    }
    
    if (!$estado) {
        throw new Exception('Estado no especificado');
    }
    
    // Validar estado
    $estadosPermitidos = ['Pagado', 'Descansado', 'Compensado', 'Pendiente'];
    if (!in_array($estado, $estadosPermitidos)) {
        throw new Exception('Estado no válido');
    }
    
    // Actualizar en la base de datos
    $sql = "UPDATE FeriadosStatus 
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
            'ACTUALIZAR_FERIADO',
            "Estado de feriado actualizado a {$estado}",
            [
                'feriado_id' => $id,
                'nuevo_estado' => $estado,
                'usuario_id' => $_SESSION['usuario_id']
            ]
        );
        
        echo json_encode([
            'success' => true, 
            'message' => 'Estado del feriado actualizado correctamente',
            'estado' => $estado
        ]);
    } else {
        throw new Exception('Error al actualizar el estado del feriado');
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