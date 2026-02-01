<?php
require_once '../../core/auth/auth.php';

// Verificar autenticación
verificarAutenticacion();

header('Content-Type: application/json');

if (!isset($_GET['codigo']) || empty($_GET['codigo'])) {
    echo json_encode(['existe' => false]);
    exit();
}

$codigo = $_GET['codigo'];
$excluir = isset($_GET['excluir']) ? intval($_GET['excluir']) : 0;
global $conn;

if ($excluir > 0) {
    $stmt = $conn->prepare("
        SELECT CodContrato FROM Contratos 
        WHERE codigo_manual_contrato = ? AND CodContrato != ?
    ");
    $stmt->execute([$codigo, $excluir]);
} else {
    $stmt = $conn->prepare("
        SELECT CodContrato FROM Contratos 
        WHERE codigo_manual_contrato = ?
    ");
    $stmt->execute([$codigo]);
}

echo json_encode(['existe' => $stmt->rowCount() > 0]);
?>