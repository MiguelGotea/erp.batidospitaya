<?php
require_once '../../../../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$resultado = cerrarEvento();
echo json_encode($resultado);