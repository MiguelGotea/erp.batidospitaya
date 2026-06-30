<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['ticket_id'])) {
    echo json_encode(['success' => false, 'message' => 'Falta el ID del ticket']);
    exit;
}

$ticket_id = intval($_GET['ticket_id']);

try {
    // Obtener la tarea de este ticket
    $sqlTarea = "
        SELECT id, trabajo_realizado, completado_100, created_at
        FROM mtto_informe_tareas 
        WHERE ticket_id = ?
        ORDER BY created_at DESC LIMIT 1
    ";
    
    $tarea = $db->fetchOne($sqlTarea, [$ticket_id]);
    
    if (!$tarea) {
        echo json_encode(['success' => false, 'message' => 'No se encontró trabajo realizado para este ticket']);
        exit;
    }
    
    // Obtener las fotos asociadas a esta tarea
    $sqlFotos = "
        SELECT id, foto 
        FROM mtto_informe_tareas_fotos 
        WHERE tarea_id = ?
        ORDER BY orden ASC
    ";
    
    $fotos = $db->fetchAll($sqlFotos, [$tarea['id']]);
    
    // Agregar ruta completa a cada foto
    foreach ($fotos as &$foto) {
        $foto['foto'] = 'uploads/evidencias/' . $foto['foto'];
    }
    
    echo json_encode([
        'success' => true,
        'tarea' => $tarea,
        'fotos' => $fotos
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
