<?php
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    if ($_GET['sucursal'] === 'auto') {
        // Obtener sucursal del usuario actual
        $sucursalesUsuario = obtenerSucursalesUsuario($_SESSION['usuario_id']);
        if (empty($sucursalesUsuario)) {
            echo json_encode([]);
            exit;
        }
        $codSucursal = $sucursalesUsuario[0]['codigo'];
    } else {
        $codSucursal = $_GET['sucursal'];
    }
    
    $hoy = date('Y-m-d');
    
    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2
        FROM Operarios o
        INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        WHERE anc.Sucursal = ?
        AND o.Operativo = 1
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND o.CodOperario NOT IN (
            SELECT DISTINCT anc2.CodOperario 
            FROM AsignacionNivelesCargos anc2
            WHERE anc2.CodNivelesCargos = 27
            AND (anc2.Fin IS NULL OR anc2.Fin >= ?)
        )
        ORDER BY o.Nombre, o.Apellido
    ");
    $stmt->execute([$codSucursal, $hoy, $hoy]);
    
    $operarios = $stmt->fetchAll();
    echo json_encode($operarios);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
