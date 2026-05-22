<?php
/**
 * AJAX correos_guardar.php
 * Módulo: sistemas
 * Guarda (crea o edita) un registro de correo corporativo
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    $operarioActualId = $usuario['CodOperario'];

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';
    $proveedor = isset($_POST['proveedor']) ? $_POST['proveedor'] : '';
    $nombre_usuario = isset($_POST['nombre_usuario']) ? trim($_POST['nombre_usuario']) : '';
    $password_correo = isset($_POST['password_correo']) ? trim($_POST['password_correo']) : '';
    $cargo_asignado = isset($_POST['cargo_asignado']) ? (int)$_POST['cargo_asignado'] : 0;
    $asignado_a = isset($_POST['asignado_a']) && $_POST['asignado_a'] !== '' ? (int)$_POST['asignado_a'] : null;
    $fecha_asignacion = isset($_POST['fecha_asignacion']) && $_POST['fecha_asignacion'] !== '' ? $_POST['fecha_asignacion'] : null;
    $departamento = isset($_POST['departamento']) ? trim($_POST['departamento']) : '';
    $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;
    $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

    // 1. Validaciones básicas
    if (empty($correo)) {
        echo json_encode(['success' => false, 'error' => 'El correo electrónico es requerido.']);
        exit;
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'El formato del correo electrónico no es válido.']);
        exit;
    }
    if (!in_array($proveedor, ['gmail', 'outlook'])) {
        echo json_encode(['success' => false, 'error' => 'El proveedor seleccionado no es válido.']);
        exit;
    }
    if ($cargo_asignado <= 0) {
        echo json_encode(['success' => false, 'error' => 'Debe seleccionar un cargo asignado válido.']);
        exit;
    }
    if (!in_array($estado, [0, 1, 2])) {
        echo json_encode(['success' => false, 'error' => 'El estado seleccionado no es válido.']);
        exit;
    }

    // 2. Verificar permisos y realizar la operación
    if ($id > 0) {
        // Modo Edición
        if (!tienePermiso('correos_corporativos', 'editar', $cargoOperario)) {
            echo json_encode(['success' => false, 'error' => 'No tiene permisos para modificar correos corporativos.']);
            exit;
        }

        // Verificar si el correo ya existe en otro registro
        $sqlCheck = "SELECT COUNT(*) FROM Correos_Corporativos WHERE correo = ? AND id != ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->execute([$correo, $id]);
        if ($stmtCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'El correo electrónico ya se encuentra registrado.']);
            exit;
        }

        // Actualizar
        $sql = "
            UPDATE Correos_Corporativos
            SET correo = :correo,
                proveedor = :proveedor,
                nombre_usuario = :nombre_usuario,
                password_correo = :password_correo,
                cargo_asignado = :cargo_asignado,
                asignado_a = :asignado_a,
                fecha_asignacion = :fecha_asignacion,
                departamento = :departamento,
                estado = :estado,
                observaciones = :observaciones,
                usuario_modifica = :usuario_modifica
            WHERE id = :id
        ";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':correo' => $correo,
            ':proveedor' => $proveedor,
            ':nombre_usuario' => $nombre_usuario,
            ':password_correo' => $password_correo,
            ':cargo_asignado' => $cargo_asignado,
            ':asignado_a' => $asignado_a,
            ':fecha_asignacion' => $fecha_asignacion,
            ':departamento' => $departamento,
            ':estado' => $estado,
            ':observaciones' => $observaciones,
            ':usuario_modifica' => $operarioActualId,
            ':id' => $id
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Correo corporativo actualizado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo actualizar el registro.']);
        }

    } else {
        // Modo Creación
        if (!tienePermiso('correos_corporativos', 'crear', $cargoOperario)) {
            echo json_encode(['success' => false, 'error' => 'No tiene permisos para crear correos corporativos.']);
            exit;
        }

        // Verificar si el correo ya existe
        $sqlCheck = "SELECT COUNT(*) FROM Correos_Corporativos WHERE correo = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->execute([$correo]);
        if ($stmtCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'El correo electrónico ya se encuentra registrado.']);
            exit;
        }

        // Insertar
        $sql = "
            INSERT INTO Correos_Corporativos (
                correo,
                proveedor,
                nombre_usuario,
                password_correo,
                cargo_asignado,
                asignado_a,
                fecha_asignacion,
                departamento,
                estado,
                observaciones,
                usuario_creador
            ) VALUES (
                :correo,
                :proveedor,
                :nombre_usuario,
                :password_correo,
                :cargo_asignado,
                :asignado_a,
                :fecha_asignacion,
                :departamento,
                :estado,
                :observaciones,
                :usuario_creador
            )
        ";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':correo' => $correo,
            ':proveedor' => $proveedor,
            ':nombre_usuario' => $nombre_usuario,
            ':password_correo' => $password_correo,
            ':cargo_asignado' => $cargo_asignado,
            ':asignado_a' => $asignado_a,
            ':fecha_asignacion' => $fecha_asignacion,
            ':departamento' => $departamento,
            ':estado' => $estado,
            ':observaciones' => $observaciones,
            ':usuario_creador' => $operarioActualId
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Correo corporativo registrado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo crear el registro.']);
        }
    }

} catch (Exception $e) {
    error_log("Error en correos_guardar.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar los datos: ' . $e->getMessage()
    ]);
}
