<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar autenticación
verificarAutenticacion();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'ID de adendum no proporcionado']);
    exit();
}

$idAdendum = intval($_GET['id']);

// Obtener el adendum
function obtenerAdendumPorId($idAdendum) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            anc.*,
            co.NombreCategoria,
            nc.Nombre as nombre_cargo,
            s.nombre as nombre_sucursal
        FROM AsignacionNivelesCargos anc
        LEFT JOIN CategoriasOperarios co ON anc.CodNivelesCargos = co.idCategoria
        LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
        LEFT JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE anc.CodAsignacionNivelesCargos = ?
    ");
    $stmt->execute([$idAdendum]);
    return $stmt->fetch();
}

$adendum = obtenerAdendumPorId($idAdendum);

if (!$adendum) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Adendum no encontrado']);
    exit();
}

header('Content-Type: application/json');
echo json_encode($adendum);
?>