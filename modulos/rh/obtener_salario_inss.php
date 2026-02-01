<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once '../../includes/conexion.php';

// Verificar autenticación
verificarAutenticacion();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID no proporcionado']);
    exit();
}

$idSalarioINSS = intval($_GET['id']);

// Obtener el salario INSS
function obtenerSalarioINSSPorId($idSalarioINSS) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM SalarioINSS 
        WHERE id = ?
    ");
    $stmt->execute([$idSalarioINSS]);
    return $stmt->fetch();
}

$salarioINSS = obtenerSalarioINSSPorId($idSalarioINSS);

if (!$salarioINSS) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Salario INSS no encontrado']);
    exit();
}

// Verificar permisos
$usuarioId = $_SESSION['usuario_id'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!$esAdmin && !verificarAccesoCargo([13, 16])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'No tiene permisos para acceder a este recurso']);
    exit();
}

// Devolver los datos en formato JSON
header('Content-Type: application/json');
echo json_encode([
    'monto_salario_inss' => $salarioINSS['monto_salario_inss'],
    'inicio' => $salarioINSS['inicio'],
    'final' => $salarioINSS['final'],
    'observaciones_inss' => $salarioINSS['observaciones_inss']
]);
?>