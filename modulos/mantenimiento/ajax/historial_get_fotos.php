<?php
// ajax/historial_get_fotos.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $sql = "SELECT * FROM mtto_tickets_fotos WHERE ticket_id = ? ORDER BY orden ASC";
    $fotos = $db->fetchAll($sql, [$ticket_id]);
    
    // Procesar las fotos
    $fotos_procesadas = [];
    
    foreach ($fotos as $foto) {
        if (!empty($foto['foto'])) {
            // La ruta desde historial_solicitudes.php es: uploads/tickets/nombre_foto.jpg
            // Como estamos en ajax/, necesitamos retroceder un nivel: ../uploads/tickets/
            $url_foto = '../uploads/tickets/' . $foto['foto'];
            
            // Verificar si el archivo existe
            $ruta_fisica = dirname(__DIR__) . '/uploads/tickets/' . $foto['foto'];
            $existe = file_exists($ruta_fisica);
            
            // Si no existe, intentar con ruta alternativa
            if (!$existe) {
                // Intentar ruta desde la raíz del sitio
                $url_foto = '/uploads/tickets/' . $foto['foto'];
            }
            
            $fotos_procesadas[] = [
                'id' => $foto['id'],
                'ticket_id' => $foto['ticket_id'],
                'foto' => $url_foto,        // URL para mostrar
                'foto_url' => $url_foto,     // Alias
                'foto_nombre' => $foto['foto'],
                'orden' => $foto['orden'],
                'created_at' => $foto['created_at']
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'fotos' => $fotos_procesadas
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar fotos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>