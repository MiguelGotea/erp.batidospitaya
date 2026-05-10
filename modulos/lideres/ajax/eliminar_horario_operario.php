<?php
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

// Verificar autenticación y permisos
$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$codOperario = $_POST['cod_operario'] ?? null;
$idSemana = $_POST['id_semana'] ?? null;
$codSucursal = $_POST['cod_sucursal'] ?? null;

// Validar datos
if (!$codOperario || !$idSemana || !$codSucursal) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Verificar que el usuario tiene permisos para esta sucursal
$sucursalesLider = obtenerSucursalesLider($_SESSION['usuario_id']);
$sucursalPermitida = false;

foreach ($sucursalesLider as $sucursal) {
    if ($sucursal['codigo'] == $codSucursal) {
        $sucursalPermitida = true;
        break;
    }
}

if (!$sucursalPermitida) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para esta sucursal']);
    exit;
}

// Primero verificar si el operario está asignado actualmente a la sucursal
$stmt = $conn->prepare("
    SELECT COUNT(*) as esta_asignado 
    FROM AsignacionNivelesCargos 
    WHERE CodOperario = ? 
    AND Sucursal = ?
    AND (Fin IS NULL OR Fin >= CURDATE())
");
$stmt->execute([$codOperario, $codSucursal]);
$resultAsignacion = $stmt->fetch();
$estaAsignado = $resultAsignacion['esta_asignado'] > 0;

// Eliminar de la base de datos
try {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM HorariosSemanales 
                           WHERE id_semana_sistema = ? AND cod_operario = ? AND cod_sucursal = ?");
    $stmt->execute([$idSemana, $codOperario, $codSucursal]);
    
    if ($stmt->rowCount() > 0) {
        $mensaje = $estaAsignado ? 
            'Horario eliminado correctamente (el colaborador sigue asignado a la sucursal)' : 
            'Colaborador adicional eliminado permanentemente';
        
        echo json_encode(['success' => true, 'message' => $mensaje]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró el registro']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}