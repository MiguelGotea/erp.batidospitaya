<?php
// get_valores.php
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
    $sql = "SELECT v.*, 
                   CONCAT_WS(' ', o_c.Nombre, NULLIF(o_c.Nombre2, ''), o_c.Apellido, NULLIF(o_c.Apellido2, '')) AS creador_nombre,
                   CONCAT_WS(' ', o_m.Nombre, NULLIF(o_m.Nombre2, ''), o_m.Apellido, NULLIF(o_m.Apellido2, '')) AS modificador_nombre
            FROM talento_valores v
            LEFT JOIN Operarios o_c ON v.usuario_creador = o_c.CodOperario
            LEFT JOIN Operarios o_m ON v.usuario_modifica = o_m.CodOperario
            ORDER BY v.orden ASC, v.id ASC";
            
    $stmt = $conn->query($sql);
    $valores = $stmt->fetchAll();
    echo json_encode($valores);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
