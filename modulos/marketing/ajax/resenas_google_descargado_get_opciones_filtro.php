<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];


// Verificar permiso de vista
if (!tienePermiso('resenas_google_descargado', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para ver estos datos.']);
    exit;
}

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';

    $opciones = [];

    if ($columna === 'locationId') {
        // Obtener sucursales únicas que tengan reseñas o todas las sucursales si se prefiere
        $sql = "SELECT DISTINCT r.locationId as valor, IFNULL(s.nombre, CONCAT('Desconocida (', r.locationId, ')')) as texto 
                FROM ResenasGoogle r 
                LEFT JOIN sucursales s ON r.locationId = s.cod_googlebusiness
                ORDER BY texto ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'opciones' => $opciones
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>