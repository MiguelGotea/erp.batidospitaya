<?php
/**
 * AJAX celulares_guardar.php
 * Módulo: sistemas
 * Guarda (crea o edita) un registro de celular asignado
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    $operarioActualId = $usuario['CodOperario'];

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $modelo = isset($_POST['modelo']) ? trim($_POST['modelo']) : '';
    $serie = isset($_POST['serie']) ? trim($_POST['serie']) : '';
    $cod_sucursal = isset($_POST['cod_sucursal']) ? trim($_POST['cod_sucursal']) : '';
    $no_sim = isset($_POST['no_sim']) && $_POST['no_sim'] !== '' ? trim($_POST['no_sim']) : null;
    $departamento = isset($_POST['departamento']) ? trim($_POST['departamento']) : '';
    $IMEI = isset($_POST['IMEI']) && $_POST['IMEI'] !== '' ? trim($_POST['IMEI']) : null;
    $IMSI = isset($_POST['IMSI']) && $_POST['IMSI'] !== '' ? trim($_POST['IMSI']) : null;
    $cargo_asignado = isset($_POST['cargo_asignado']) ? (int)$_POST['cargo_asignado'] : 0;
    $usuario_uso = isset($_POST['usuario_uso']) && $_POST['usuario_uso'] !== '' ? (int)$_POST['usuario_uso'] : null;

    // 1. Validaciones básicas
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'error' => 'El nombre/alias del dispositivo es requerido.']);
        exit;
    }
    if (empty($cod_sucursal)) {
        echo json_encode(['success' => false, 'error' => 'La sucursal es requerida.']);
        exit;
    }
    if (empty($departamento)) {
        echo json_encode(['success' => false, 'error' => 'El departamento/área es requerido.']);
        exit;
    }
    if ($cargo_asignado <= 0) {
        echo json_encode(['success' => false, 'error' => 'Debe seleccionar un cargo asignado válido.']);
        exit;
    }

    // 2. Verificar permisos y realizar la operación
    if ($id > 0) {
        // Modo Edición
        if (!tienePermiso('celulares_asignados', 'editar', $cargoOperario)) {
            echo json_encode(['success' => false, 'error' => 'No tiene permisos para modificar celulares asignados.']);
            exit;
        }

        // Actualizar
        $sql = "
            UPDATE Celulares_Asignados
            SET nombre = :nombre,
                modelo = :modelo,
                serie = :serie,
                cod_sucursal = :cod_sucursal,
                no_sim = :no_sim,
                departamento = :departamento,
                IMEI = :IMEI,
                IMSI = :IMSI,
                cargo_asignado = :cargo_asignado,
                usuario_uso = :usuario_uso,
                usuario_modifica = :usuario_modifica
            WHERE id = :id
        ";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':nombre' => $nombre,
            ':modelo' => $modelo,
            ':serie' => $serie,
            ':cod_sucursal' => $cod_sucursal,
            ':no_sim' => $no_sim,
            ':departamento' => $departamento,
            ':IMEI' => $IMEI,
            ':IMSI' => $IMSI,
            ':cargo_asignado' => $cargo_asignado,
            ':usuario_uso' => $usuario_uso,
            ':usuario_modifica' => $operarioActualId,
            ':id' => $id
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Dispositivo actualizado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo actualizar el registro.']);
        }

    } else {
        // Modo Creación
        if (!tienePermiso('celulares_asignados', 'crear', $cargoOperario)) {
            echo json_encode(['success' => false, 'error' => 'No tiene permisos para registrar celulares.']);
            exit;
        }

        // Insertar
        $sql = "
            INSERT INTO Celulares_Asignados (
                nombre,
                modelo,
                serie,
                cod_sucursal,
                no_sim,
                departamento,
                IMEI,
                IMSI,
                cargo_asignado,
                usuario_uso,
                usuario_creador
            ) VALUES (
                :nombre,
                :modelo,
                :serie,
                :cod_sucursal,
                :no_sim,
                :departamento,
                :IMEI,
                :IMSI,
                :cargo_asignado,
                :usuario_uso,
                :usuario_creador
            )
        ";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':nombre' => $nombre,
            ':modelo' => $modelo,
            ':serie' => $serie,
            ':cod_sucursal' => $cod_sucursal,
            ':no_sim' => $no_sim,
            ':departamento' => $departamento,
            ':IMEI' => $IMEI,
            ':IMSI' => $IMSI,
            ':cargo_asignado' => $cargo_asignado,
            ':usuario_uso' => $usuario_uso,
            ':usuario_creador' => $operarioActualId
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Dispositivo registrado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo crear el registro.']);
        }
    }

} catch (Exception $e) {
    error_log("Error en celulares_guardar.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar los datos: ' . $e->getMessage()
    ]);
}
