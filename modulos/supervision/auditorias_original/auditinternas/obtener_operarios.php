<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../../core/helpers/funciones.php'; // Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

header('Content-Type: application/json');

if (!isset($_GET['sucursal_id']) || !is_numeric($_GET['sucursal_id'])) {
    echo json_encode([]);
    exit();
}

$sucursal_id = (int)$_GET['sucursal_id'];
// $db = conectarDB(); // Comentado por migración al core
$db = $conn;

try {
    $stmt = $db->prepare("
        SELECT o.CodOperario, CONCAT(o.Nombre, ' ', o.Apellido) AS nombre_completo
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        WHERE anc.Sucursal = ?
        AND o.Operativo = 1
        AND anc.CodNivelesCargos NOT IN (27)
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        -- AND anc.CodNivelesCargos = 2 Código para operarios/cajeros pero también aplican líderes
        ORDER BY nombre_completo
    ");
    $stmt->execute([$sucursal_id]);
    $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($operarios);
} catch (PDOException $e) {
    echo json_encode([]);
}
