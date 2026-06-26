<?php
// get_culturas.php
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
    $sql = "SELECT c.*, 
                   CONCAT_WS(' ', o_c.Nombre, NULLIF(o_c.Nombre2, ''), o_c.Apellido, NULLIF(o_c.Apellido2, '')) AS creador_nombre,
                   CONCAT_WS(' ', o_m.Nombre, NULLIF(o_m.Nombre2, ''), o_m.Apellido, NULLIF(o_m.Apellido2, '')) AS modificador_nombre
            FROM talento_cultura c
            LEFT JOIN Operarios o_c ON c.usuario_creador = o_c.CodOperario
            LEFT JOIN Operarios o_m ON c.usuario_modifica = o_m.CodOperario
            ORDER BY c.orden ASC, c.id ASC";
            
    $stmt = $conn->query($sql);
    $culturas = $stmt->fetchAll();
    echo json_encode($culturas);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
