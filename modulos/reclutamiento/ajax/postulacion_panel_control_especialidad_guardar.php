<?php
// postulacion_panel_control_especialidad_guardar.php
// Actualiza NivelesCargos.especialidad_area para un cargo dado.

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    $usuario       = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('postulacion_panel_control', 'editar', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permisos para editar']);
        exit();
    }

    $input           = json_decode(file_get_contents('php://input'), true);
    $codCargo        = isset($input['cod_cargo']) ? (int) $input['cod_cargo'] : 0;
    $especialidadArea = isset($input['especialidad_area']) ? trim($input['especialidad_area']) : '';

    if ($codCargo <= 0) {
        echo json_encode(['success' => false, 'message' => 'Cargo inválido']);
        exit();
    }

    // Si viene vacío guardamos NULL para mantener consistencia
    $valorGuardar = $especialidadArea === '' ? null : $especialidadArea;

    $sql  = "UPDATE NivelesCargos SET especialidad_area = :esp WHERE CodNivelesCargos = :cod";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':esp', $valorGuardar);
    $stmt->bindValue(':cod', $codCargo, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Especialidad guardada correctamente'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
