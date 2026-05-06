<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../../core/helpers/funciones.php'; // Antes llamaba a ../funciones.php de auditora
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
