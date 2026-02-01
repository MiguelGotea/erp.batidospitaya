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

$idSalario = intval($_GET['id']);

// Obtener el salario desde Contratos
function obtenerSalarioPorId($idSalario) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            c.salario_inicial as monto,
            c.frecuencia_pago,
            c.inicio_contrato as inicio,
            c.fin_contrato as fin,
            c.salario_inicial as monto_salario
        FROM Contratos c
        WHERE c.CodContrato = ?
    ");
    $stmt->execute([$idSalario]);
    return $stmt->fetch();
}

$salario = obtenerSalarioPorId($idSalario);

if (!$salario) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Salario no encontrado']);
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

// Devolver datos
header('Content-Type: application/json');
echo json_encode([
    'monto' => $salario['monto_salario'],
    'inicio' => $salario['inicio'],
    'fin' => $salario['fin'],
    'frecuencia_pago' => $salario['frecuencia_pago']
]);
?>