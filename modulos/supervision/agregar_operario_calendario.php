<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

header('Content-Type: application/json');

// Verificar autenticación y permisos
$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar permisos de supervisión
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
if (!$esAdmin && !verificarAccesoCargo([21])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos del POST
$codOperario = $_POST['cod_operario'] ?? null;
$semana = $_POST['semana'] ?? null;
$sucursal = $_POST['sucursal'] ?? null;

if (!$codOperario || !$semana || !$sucursal) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Verificar si el operario existe
    $operario = obtenerOperarioPorCodigo($codOperario);
    if (!$operario) {
        echo json_encode(['success' => false, 'message' => 'El colaborador no existe']);
        exit;
    }

    // Inicializar sesión para operarios adicionales si no existe
    if (!isset($_SESSION['operarios_adicionales'])) {
        $_SESSION['operarios_adicionales'] = [];
    }

    // Agregar operario a la sesión
    $_SESSION['operarios_adicionales'][$codOperario] = $operario;

    echo json_encode(['success' => true, 'message' => 'Colaborador agregado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al agregar: ' . $e->getMessage()]);
}

// Función para obtener operario por código
function obtenerOperarioPorCodigo($codOperario) {
    global $conn;

    $stmt = $conn->prepare("
        SELECT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2 
        FROM Operarios o
        WHERE o.CodOperario = ? 
        AND o.Operativo = 1
        -- AND (o.Fin IS NULL OR o.Fin >= CURDATE())
    ");
    
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}
?>