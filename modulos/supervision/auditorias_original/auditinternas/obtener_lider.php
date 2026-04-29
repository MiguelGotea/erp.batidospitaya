<?php
require_once '../auth.php';
require_once '../funciones.php';
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['sucursal_id'])) {
    echo json_encode(['error' => 'Sucursal no especificada']);
    exit();
}

$sucursalId = $_GET['sucursal_id'];
$conn = conectarDB();

function obtenerLiderSucursal($sucursalId, $conn) {
    $stmt = $conn->prepare("
        SELECT o.CodOperario, o.Nombre, o.Apellido 
        FROM AsignacionNivelesCargos anc
        JOIN Operarios o ON anc.CodOperario = o.CodOperario
        WHERE anc.Sucursal = ?
        AND anc.CodNivelesCargos IN (5, 43, 46) 
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        AND o.Operativo = 1
        LIMIT 1
    ");
    $stmt->execute([$sucursalId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$lider = obtenerLiderSucursal($sucursalId, $conn);

if ($lider) {
    echo json_encode([
        'lider' => $lider['Nombre'] . ' ' . $lider['Apellido'],
        'codigo' => $lider['CodOperario']
    ]);
} else {
    echo json_encode(['lider' => '', 'codigo' => null]);
}
?>