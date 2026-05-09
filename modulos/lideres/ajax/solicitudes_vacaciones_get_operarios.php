<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// ajax/solicitudes_vacaciones_get_operarios.php
require_once '../../../includes/conexion.php';
require_once '../../../includes/auth.php';
require_once '../../../includes/funciones.php';

header('Content-Type: application/json');

try {
    verificarAutenticacion();
    
    // Obtener la primera sucursal del usuario actual
    $sucursalesUsuario = obtenerSucursalesUsuario($_SESSION['usuario_id']);
    
    if (empty($sucursalesUsuario)) {
        echo json_encode([]);
        exit;
    }
    
    $codSucursal = $sucursalesUsuario[0]['codigo'];
    
    // Obtener operarios activos de la sucursal
    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        WHERE anc.Sucursal = ?
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        AND o.Operativo = 1
        ORDER BY o.Nombre, o.Apellido
    ");
    
    $stmt->execute([$codSucursal]);
    $operarios = $stmt->fetchAll();
    
    echo json_encode($operarios);
    
} catch (Exception $e) {
    echo json_encode([]);
}