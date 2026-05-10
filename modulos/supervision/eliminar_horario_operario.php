<?php
// require_once '../../core/auth/auth.php';
// require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

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

// Verificar que el usuario tiene permisos (supervisor o admin)
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$esSupervisor = verificarAccesoCargo([21]);

if (!$esAdmin && !$esSupervisor) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para esta acción']);
    exit;
}

// Eliminar de la base de datos
try {
    global $conn;
    
    $stmt = $conn->prepare("DELETE FROM HorariosSemanalesOperaciones 
                           WHERE id_semana_sistema = ? AND cod_operario = ? AND cod_sucursal = ?");
    $stmt->execute([$idSemana, $codOperario, $codSucursal]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Eliminado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró el registro']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
}