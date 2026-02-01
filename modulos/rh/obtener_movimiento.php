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

$idMovimiento = intval($_GET['id']);

// Obtener el movimiento
function obtenerMovimientoPorId($idMovimiento) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM AsignacionNivelesCargos 
        WHERE CodAsignacionNivelesCargos = ?
    ");
    $stmt->execute([$idMovimiento]);
    return $stmt->fetch();
}

$movimiento = obtenerMovimientoPorId($idMovimiento);

if (!$movimiento) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Movimiento no encontrado']);
    exit();
}

// Verificar permisos
//$usuarioId = $_SESSION['usuario_id'];
//$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

//if (!$esAdmin && !verificarAccesoCargo([11, 13, 16])) {
//    header('HTTP/1.1 403 Forbidden');
//    echo json_encode(['error' => 'No tiene permisos para acceder a este recurso']);
//    exit();
//}

// Devolver los datos en formato JSON
header('Content-Type: application/json');
echo json_encode([
    'CodAsignacionNivelesCargos' => $movimiento['CodAsignacionNivelesCargos'],
    'CodOperario' => $movimiento['CodOperario'],
    'CodNivelesCargos' => $movimiento['CodNivelesCargos'],
    'Fecha' => $movimiento['Fecha'],
    'Sucursal' => $movimiento['Sucursal'],
    'CodTipoContrato' => $movimiento['CodTipoContrato'],
    'Fin' => $movimiento['Fin']
]);
?>