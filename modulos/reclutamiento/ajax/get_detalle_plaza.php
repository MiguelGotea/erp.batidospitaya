<?php
// get_detalle_plaza.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('postulacion_panel_control', 'vista', $cargoOperario)) {
        throw new Exception("Sin permiso");
    }

    $idConfig = isset($_GET['id_config']) ? (int)$_GET['id_config'] : 0;

    if ($idConfig <= 0) {
        echo json_encode([
            'success'           => true,
            'descripcion'       => null,
            'responsabilidades' => null,
            'requisitos'        => null
        ]);
        exit();
    }

    $sql  = "SELECT descripcion, responsabilidades, requisitos FROM plazas_cargos WHERE id = :id LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $idConfig, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode([
            'success'           => true,
            'descripcion'       => null,
            'responsabilidades' => null,
            'requisitos'        => null
        ]);
        exit();
    }

    echo json_encode([
        'success'           => true,
        'descripcion'       => $row['descripcion'],
        'responsabilidades' => $row['responsabilidades'],
        'requisitos'        => $row['requisitos']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
