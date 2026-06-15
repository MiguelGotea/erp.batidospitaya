<?php
// postulacion_panel_control_toggle_operativo.php
// Activa o desactiva un cargo en NivelesCargos (campo operativo)

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('postulacion_panel_control', 'editar', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso para realizar esta acción']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $codCargo  = (int) ($input['cod_cargo']  ?? 0);
    $operativo = (int) ($input['operativo']  ?? 1);
    $operativo = ($operativo === 1) ? 1 : 0; // sanitizar a 0 o 1

    if ($codCargo <= 0) {
        echo json_encode(['success' => false, 'message' => 'Cargo inválido']);
        exit();
    }

    $sql  = "UPDATE NivelesCargos SET operativo = :operativo WHERE CodNivelesCargos = :cod_cargo";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':operativo', $operativo, PDO::PARAM_INT);
    $stmt->bindValue(':cod_cargo', $codCargo,  PDO::PARAM_INT);
    $stmt->execute();

    $estado = $operativo ? 'activado' : 'desactivado';
    echo json_encode(['success' => true, 'message' => "Cargo $estado correctamente"]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
