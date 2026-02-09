<?php
// toggle_valido_registro.php - Toggle valido status for a registro
header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/auth/auth.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de edición
if (!tienePermiso('gestion_sorteos', 'edicion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos para modificar registros']);
    exit;
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $valido = isset($input['valido']) ? (int) $input['valido'] : null;

    if ($id <= 0) {
        $response['message'] = 'ID inválido';
        echo json_encode($response);
        exit;
    }

    if ($valido !== 0 && $valido !== 1) {
        $response['message'] = 'Valor de valido inválido';
        echo json_encode($response);
        exit;
    }

    try {
        // Actualizar el estado valido
        $sql = "UPDATE pitaya_love_registros SET valido = ? WHERE id = ?";
        if (ejecutarConsulta($sql, [$valido, $id])) {
            $response['success'] = true;
            $response['message'] = $valido === 1 ? 'Registro marcado como válido' : 'Registro marcado como inválido';
        } else {
            $response['message'] = 'Error al actualizar el registro';
        }

    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Método no permitido';
}

echo json_encode($response);
