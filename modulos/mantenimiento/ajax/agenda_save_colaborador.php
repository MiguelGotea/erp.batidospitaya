<?php
// ajax/agenda_save_colaborador.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$cod_operario = isset($_POST['cod_operario']) && $_POST['cod_operario'] !== '' ? intval($_POST['cod_operario']) : null;
$tipo_usuario = isset($_POST['tipo_usuario']) ? $_POST['tipo_usuario'] : '';

if ($ticket_id <= 0 || empty($tipo_usuario)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tipos_validos = ['Jefe de Manteniento', 'Lider de Infraestructura', 'Conductor', 'Auxiliar de Mantenimiento'];
if (!in_array($tipo_usuario, $tipos_validos)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de usuario inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Permitir cod_operario NULL
    $sql = "INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, tipo_usuario) 
            VALUES (?, ?, ?)";
    
    $db->query($sql, [$ticket_id, $cod_operario, $tipo_usuario]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Colaborador agregado correctamente'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al agregar colaborador: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>