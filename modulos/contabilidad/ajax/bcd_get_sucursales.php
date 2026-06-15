<?php
// ajax/bcd_get_sucursales.php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal = 1 ORDER BY nombre ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'datos' => $datos]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
