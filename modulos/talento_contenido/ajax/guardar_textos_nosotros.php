<?php
// guardar_textos_nosotros.php
header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
if (!tienePermiso('talento_contenido', 'editar', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

try {
    $conn->beginTransaction();

    $claves = ['parrafo_1', 'parrafo_2', 'parrafo_3', 'proposito_titulo', 'proposito_desc'];
    $stmt = $conn->prepare("UPDATE talento_textos_nosotros SET valor = ?, usuario_modifica = ?, fecha_modificacion = NOW() WHERE clave = ?");

    foreach ($claves as $clave) {
        if (isset($_POST[$clave])) {
            $valor = trim($_POST[$clave]);
            $stmt->execute([$valor, $usuario['CodOperario'], $clave]);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'mensaje' => 'Textos actualizados correctamente']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
