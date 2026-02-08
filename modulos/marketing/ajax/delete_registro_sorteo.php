<?php
header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/auth/auth.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de edición
if (!tienePermiso('gestion_sorteos', 'edicion', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos para eliminar registros']);
    exit;
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? (int) $input['id'] : 0;

    if ($id <= 0) {
        $response['message'] = 'ID inválido';
        echo json_encode($response);
        exit;
    }

    try {
        // Obtener información del registro antes de eliminar
        $sql = "SELECT foto_factura FROM pitaya_love_registros WHERE id = ?";
        $stmt = ejecutarConsulta($sql, [$id]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            $response['message'] = 'Registro no encontrado';
            echo json_encode($response);
            exit;
        }

        // Eliminar registro de la base de datos
        $deleteSql = "DELETE FROM pitaya_love_registros WHERE id = ?";
        if (ejecutarConsulta($deleteSql, [$id])) {
            // Eliminar foto física del servidor si existe
            if (!empty($registro['foto_factura'])) {
                $fotoPath = '../../PitayaLove/uploads/' . $registro['foto_factura'];
                if (file_exists($fotoPath)) {
                    unlink($fotoPath);
                }
            }

            $response['success'] = true;
            $response['message'] = 'Registro eliminado correctamente';
        } else {
            $response['message'] = 'Error al eliminar el registro';
        }

    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Método no permitido';
}

echo json_encode($response);
