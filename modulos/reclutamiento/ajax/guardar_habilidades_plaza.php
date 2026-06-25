<?php
// guardar_habilidades_plaza.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    $codOperario = $usuario['CodOperario'];

    // Verificar acceso de edición
    if (!tienePermiso('postulacion_panel_control', 'editar', $cargoOperario)) {
        throw new Exception("No tiene permisos para modificar esta información");
    }

    // Recibir datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    $idConfig = isset($input['id_config']) ? (int)$input['id_config'] : 0;
    $cargo = isset($input['cargo']) ? (int)$input['cargo'] : 0;
    $sucursal = isset($input['sucursal']) ? (int)$input['sucursal'] : 0;
    $area = isset($input['area']) ? $input['area'] : '';
    
    $habilidades = isset($input['habilidades']) ? trim($input['habilidades']) : '';

    if ($cargo <= 0 || $sucursal <= 0 || empty($area)) {
        throw new Exception("Parámetros obligatorios faltantes");
    }

    // Verificar si existe por ID o por combinación
    $existeId = 0;
    if ($idConfig > 0) {
        $sqlCheck = "SELECT id FROM plazas_cargos WHERE id = :id";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bindValue(':id', $idConfig, PDO::PARAM_INT);
        $stmtCheck->execute();
        $existeId = $stmtCheck->fetchColumn();
    }

    if (!$existeId) {
        $sqlCheck = "SELECT id FROM plazas_cargos WHERE cargo = :cargo AND sucursal = :sucursal AND area = :area LIMIT 1";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bindValue(':cargo', $cargo, PDO::PARAM_INT);
        $stmtCheck->bindValue(':sucursal', $sucursal);
        $stmtCheck->bindValue(':area', $area);
        $stmtCheck->execute();
        $existeId = $stmtCheck->fetchColumn();
    }

    if ($existeId > 0) {
        // Actualizar
        $sql = "UPDATE plazas_cargos 
                SET habilidades = :habilidades,
                    usuario_modifica = :usuario_modifica,
                    fecha_actualizacion = NOW()
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':habilidades', $habilidades);
        $stmt->bindValue(':usuario_modifica', $codOperario, PDO::PARAM_INT);
        $stmt->bindValue(':id', $existeId, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Insertar registro base nuevo
        $sql = "INSERT INTO plazas_cargos 
                (cargo, sucursal, area, cantidad_real, cantidad_adicional, obligatorio, visible_web, salario_propuesto, nivel_urgencia, usuario_registra, fecha_creacion, habilidades)
                VALUES 
                (:cargo, :sucursal, :area, 0, 0, 1, 0, 0, 1, :usuario_registra, NOW(), :habilidades)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':cargo', $cargo, PDO::PARAM_INT);
        $stmt->bindValue(':sucursal', $sucursal);
        $stmt->bindValue(':area', $area);
        $stmt->bindValue(':usuario_registra', $codOperario, PDO::PARAM_INT);
        $stmt->bindValue(':habilidades', $habilidades);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Habilidades guardadas correctamente']);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
