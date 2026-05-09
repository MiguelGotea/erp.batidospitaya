<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/helpers/funciones.php';
require_once '../editar_colaborador_componentes/logic/funciones_colaborador.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
$cargoId = $usuario['CodNivelesCargos'] ?? 0;

if (!tienePermiso('editar_colaborador', 'edicion', $cargoId)) {
    echo json_encode(['exito' => false, 'mensaje' => 'No tiene permisos para editar']);
    exit();
}

$id = $_POST['id'] ?? null;
$field = $_POST['field'] ?? null;
$value = $_POST['value'] ?? null;

if (!$id || !$field) {
    echo json_encode(['exito' => false, 'mensaje' => 'Parámetros insuficientes']);
    exit();
}

// Campos permitidos para actualización inline
$allowedFields = ['CodNivelesCargos', 'Fecha', 'Fin'];
if (!in_array($field, $allowedFields)) {
    echo json_encode(['exito' => false, 'mensaje' => 'Campo no permitido']);
    exit();
}

// Obtener datos actuales para no perder información en el update total si usamos actualizarAdendum
// O mejor, hacemos un UPDATE directo del campo específico
global $conn;

try {
    // Si el campo es 'Fin' y el valor está vacío, es NULL
    if ($field === 'Fin' && empty($value)) {
        $value = null;
    }

    $stmt = $conn->prepare("UPDATE AsignacionNivelesCargos SET $field = ?, fecha_ultima_modificacion = NOW(), usuario_ultima_modificacion = ? WHERE CodAsignacionNivelesCargos = ?");
    $stmt->execute([$value, $_SESSION['usuario_id'], $id]);

    if ($stmt->rowCount() >= 0) {
        // Devolver el nuevo valor formateado si es fecha
        $displayValue = $value;
        if (($field === 'Fecha' || $field === 'Fin') && !empty($value)) {
            $displayValue = traducirMes(date('d - M - Y', strtotime($value)));
        } elseif ($field === 'Fin' && empty($value)) {
            $displayValue = '-';
        } elseif ($field === 'CodNivelesCargos') {
            // Si es cargo, devolver el nombre del cargo
            $stmtCargo = $conn->prepare("SELECT Nombre FROM NivelesCargos WHERE CodNivelesCargos = ?");
            $stmtCargo->execute([$value]);
            $resCargo = $stmtCargo->fetch();
            $displayValue = $resCargo['Nombre'] ?? 'No definido';
        }

        echo json_encode(['exito' => true, 'mensaje' => 'Actualizado correctamente', 'displayValue' => $displayValue]);
    } else {
        echo json_encode(['exito' => false, 'mensaje' => 'No se realizaron cambios']);
    }
} catch (Exception $e) {
    echo json_encode(['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
