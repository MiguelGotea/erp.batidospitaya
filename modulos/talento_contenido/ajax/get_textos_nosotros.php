<?php
// get_textos_nosotros.php
header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
if (!tienePermiso('talento_contenido', 'vista', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit();
}

try {
    $stmt = $conn->query("SELECT clave, valor FROM talento_textos_nosotros");
    $textos = [];
    while ($row = $stmt->fetch()) {
        $textos[$row['clave']] = $row['valor'];
    }
    echo json_encode($textos);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
