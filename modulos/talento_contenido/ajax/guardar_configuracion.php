<?php
// guardar_configuracion.php
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

    $claves = [
        'email_reclutamiento',
        'email_reclutamiento_dom',
        'telefono_principal',
        'url_maps_ubicaciones',
        'url_facebook',
        'url_instagram',
        'url_linkedin',
        'hero_beneficios_sub',
        'hero_beneficios_titulo',
        'hero_beneficios_desc',
        'cultura_titulo',
        'cultura_subtitulo',
        'cultura_cita',
        // Personalización visual del portal
        'footer_descripcion',
        'color_marca',
        'color_marca_hover',
        'color_header',
        'color_footer',
        'color_fondo',
        'color_texto',
        'imagen_fondo',
        'imagen_fondo_opacidad',
        'imagen_fondo_repetir',
        'imagen_fondo_size',
    ];

    $stmt = $conn->prepare("UPDATE talento_configuracion SET valor = ?, usuario_modifica = ?, fecha_modificacion = NOW() WHERE clave = ?");

    foreach ($claves as $clave) {
        if (isset($_POST[$clave])) {
            $valor = trim($_POST[$clave]);
            $stmt->execute([$valor, $usuario['CodOperario'], $clave]);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'mensaje' => 'Configuración guardada correctamente']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
