<?php
// gestion_proyectos_get_opciones_filtro.php
// Obtiene opciones para los filtros tipo lista

header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('gestion_proyectos', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit();
}

try {
    $columna = $_GET['columna'] ?? '';
    $datos = [];

    if ($columna === 'cargo') {
        $sql = "SELECT CodNivelesCargos as id, Nombre as nombre 
                FROM NivelesCargos 
                WHERE EquipoLiderazgo = 1 
                ORDER BY Peso ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'datos' => $datos]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>