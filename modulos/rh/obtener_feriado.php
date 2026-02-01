<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar acceso al módulo (solo cargo nivel 13 - RH)
if (!verificarAccesoCargo(13)) {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit();
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM feriadosnic WHERE id = ?");
$stmt->execute([$id]);
$feriado = $stmt->fetch();

if (!$feriado) {
    header('HTTP/1.1 404 Not Found');
    exit();
}

header('Content-Type: application/json');
echo json_encode($feriado);