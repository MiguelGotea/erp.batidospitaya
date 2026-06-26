<?php
// guardar_cultura.php
header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$codOperario = $usuario['CodOperario'];
$cargoOperario = $usuario['CodNivelesCargos'];

$id = isset($_POST['id']) ? trim($_POST['id']) : '';
$action = empty($id) ? 'crear' : 'editar';

if (!tienePermiso('talento_contenido', $action, $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

$titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
$orden = isset($_POST['orden']) ? intval($_POST['orden']) : 0;
$activo = isset($_POST['activo']) ? intval($_POST['activo']) : 1;

if (empty($titulo) || empty($descripcion)) {
    http_response_code(400);
    echo json_encode(['error' => 'Todos los campos obligatorios deben estar completos']);
    exit();
}

try {
    if (empty($id)) {
        // Insertar
        $sql = "INSERT INTO talento_cultura (titulo, descripcion, orden, activo, usuario_creador)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$titulo, $descripcion, $orden, $activo, $codOperario]);
        echo json_encode(['success' => true, 'mensaje' => 'Ítem de cultura creado con éxito']);
    } else {
        // Actualizar
        $sql = "UPDATE talento_cultura 
                SET titulo = ?, descripcion = ?, orden = ?, activo = ?, usuario_modifica = ?, fecha_modificacion = NOW()
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$titulo, $descripcion, $orden, $activo, $codOperario, $id]);
        echo json_encode(['success' => true, 'mensaje' => 'Ítem de cultura actualizado con éxito']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
