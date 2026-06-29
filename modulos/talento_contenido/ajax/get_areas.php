<?php
// get_areas.php — Obtener áreas de la empresa para el portal de talento
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
    $sql = "SELECT a.id, a.icono, a.titulo, a.descripcion, a.orden, a.activo,
                   a.fecha_creacion, a.fecha_modificacion,
                   a.usuario_creador, a.usuario_modifica,
                   CONCAT_WS(' ', o_c.Nombre, NULLIF(o_c.Nombre2, ''), o_c.Apellido, NULLIF(o_c.Apellido2, '')) AS creador_nombre,
                   CONCAT_WS(' ', o_m.Nombre, NULLIF(o_m.Nombre2, ''), o_m.Apellido, NULLIF(o_m.Apellido2, '')) AS modificador_nombre
            FROM talento_areas_equipo a
            LEFT JOIN Operarios o_c ON a.usuario_creador = o_c.CodOperario
            LEFT JOIN Operarios o_m ON a.usuario_modifica = o_m.CodOperario
            ORDER BY a.orden ASC, a.id ASC";

    $stmt = $conn->query($sql);
    $areas = $stmt->fetchAll();
    echo json_encode($areas);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
