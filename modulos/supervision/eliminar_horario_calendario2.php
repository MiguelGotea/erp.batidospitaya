<?php
// require_once '../../includes/auth.php';
// require_once '../../includes/funciones.php';
require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

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
$idSemana = $_POST['id_semana'] ?? null;
$codSucursal = $_POST['cod_sucursal'] ?? null;
$dia = $_POST['dia'] ?? null;

if (!$codOperario || !$idSemana || !$codSucursal || !$dia) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Obtener el registro existente
    $stmt = $conn->prepare("
        SELECT * FROM HorariosSemanalesOperaciones 
        WHERE cod_operario = ? AND id_semana_sistema = ? AND cod_sucursal = ?
    ");
    $stmt->execute([$codOperario, $idSemana, $codSucursal]);
    $existente = $stmt->fetch();

    if ($existente) {
        // Actualizar el día específico a valores nulos
        $stmt = $conn->prepare("
            UPDATE HorariosSemanalesOperaciones SET
            {$dia}_estado = 'Libre',
            {$dia}_comentario = '',
            {$dia}_entrada = NULL,
            {$dia}_salida = NULL,
            {$dia}_horas = 0,
            actualizado_por = ?, 
            fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['usuario_id'], $existente['id']]);

        echo json_encode(['success' => true, 'message' => 'Horario eliminado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró el horario']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
?>