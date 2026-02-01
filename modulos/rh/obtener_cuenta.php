<?php
require_once '../../core/auth/auth.php';

verificarAutenticacion();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID no proporcionado']);
    exit();
}

$idCuenta = intval($_GET['id']);

function obtenerCuentaBancariaPorId($idCuenta)
{
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM CuentaBancaria WHERE id = ?");
    $stmt->execute([$idCuenta]);
    return $stmt->fetch();
}

$cuenta = obtenerCuentaBancariaPorId($idCuenta);

if (!$cuenta) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Cuenta no encontrada']);
    exit();
}

header('Content-Type: application/json');
echo json_encode($cuenta);
?>