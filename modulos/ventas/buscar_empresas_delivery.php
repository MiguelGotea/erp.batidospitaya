<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_POST['sucursal_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sucursal no proporcionada']);
    exit();
}

$sucursal_id = intval($_POST['sucursal_id']);

$stmt = $conn->prepare("SELECT id, nombre FROM servicios_delivery 
                       WHERE (sucursal_id = ? OR sucursal_id IS NULL) AND activo = 1");
$stmt->execute([$sucursal_id]);
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'empresas' => $empresas]);
?>