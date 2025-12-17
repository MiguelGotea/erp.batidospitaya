<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

session_start();
$usuario_id = $_SESSION['usuario_id'];

try {
    $equipo_id = $_POST['equipo_id'];
    $descripcion = $_POST['descripcion_problema'];
    
    // Validar que el equipo existe
    $equipo = $db->fetchOne("SELECT id FROM mtto_equipos WHERE id = ? AND activo = 1", [$equipo_id]);
    if (!$equipo) {
        echo json_encode(['success' => false, 'message' => 'Equipo no encontrado']);
        exit;
    }
    
    // Verificar que no tenga solicitud pendiente
    $solicitudExistente = $db->fetchOne(
        "SELECT id FROM mtto_equipos_solicitudes 
         WHERE equipo_id = ? AND estado = 'solicitado'",
        [$equipo_id]
    );
    
    if ($solicitudExistente) {
        echo json_encode(['success' => false, 'message' => 'El equipo ya tiene una solicitud de mantenimiento pendiente']);
        exit;
    }
    
    // Obtener sucursal del equipo (ubicación actual)
    $ubicacion = $db->fetchOne("
        SELECT s.id 
        FROM mtto_equipos_movimientos m
        INNER JOIN sucursales s ON m.sucursal_destino_id = s.id
        WHERE m.equipo_id = ? AND m.estado = 'finalizado'
        ORDER BY m.fecha_realizada DESC 
        LIMIT 1
    ", [$equipo_id]);
    
    if (!$ubicacion) {
        echo json_encode(['success' => false, 'message' => 'No se pudo determinar la ubicación del equipo']);
        exit;
    }
    
    $sucursal_id = $ubicacion['id'];
    
    // Insertar solicitud
    $db->query(
        "INSERT INTO mtto_equipos_solicitudes 
         (equipo_id, sucursal_id, descripcion_problema, solicitado_por)
         VALUES (?, ?, ?, ?)",
        [$equipo_id, $sucursal_id, $descripcion, $usuario_id]
    );
    
    $solicitud_id = $db->lastInsertId();
    
    // Guardar imágenes
    $uploadDir = '../uploads/solicitudes/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $imagenesGuardadas = 0;
    
    if (isset($_FILES['imagenes'])) {
        foreach ($_FILES['imagenes']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['imagenes']['error'][$key] === 0) {
                // Validar que sea imagen
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmp_name);
                finfo_close($finfo);
                
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mimeType, $allowedTypes)) {
                    continue;
                }
                
                $extension = pathinfo($_FILES['imagenes']['name'][$key], PATHINFO_EXTENSION);
                $filename = 'solicitud_' . $solicitud_id . '_' . time() . '_' . $key . '.' . $extension;
                $filepath = $uploadDir . $filename;
                
                if (move_uploaded_file($tmp_name, $filepath)) {
                    $db->query(
                        "INSERT INTO mtto_equipos_solicitudes_fotos (solicitud_id, ruta_archivo)
                         VALUES (?, ?)",
                        [$solicitud_id, $filepath]
                    );
                    $imagenesGuardadas++;
                }
            }
        }
    }
    
    if ($imagenesGuardadas === 0) {
        // Eliminar solicitud si no se guardó ninguna imagen
        $db->query("DELETE FROM mtto_equipos_solicitudes WHERE id = ?", [$solicitud_id]);
        echo json_encode(['success' => false, 'message' => 'Debe adjuntar al menos una evidencia fotográfica válida']);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Solicitud de mantenimiento creada exitosamente',
        'solicitud_id' => $solicitud_id,
        'imagenes_guardadas' => $imagenesGuardadas
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>