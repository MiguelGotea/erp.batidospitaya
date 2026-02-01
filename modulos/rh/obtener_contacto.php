<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

verificarAutenticacion();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID no proporcionado']);
    exit();
}

$idContacto = intval($_GET['id']);

function obtenerContactoEmergenciaPorId($idContacto) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM ContactosEmergencia WHERE id = ?");
    $stmt->execute([$idContacto]);
    return $stmt->fetch();
}

$contacto = obtenerContactoEmergenciaPorId($idContacto);

if (!$contacto) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Contacto no encontrado']);
    exit();
}

header('Content-Type: application/json');
echo json_encode($contacto);
?>