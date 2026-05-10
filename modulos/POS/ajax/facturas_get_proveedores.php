<?php
// facturas_get_proveedores.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT id, nombre, ruc_nit
            FROM proveedores
            WHERE vigente = 1
            ORDER BY nombre ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $proveedores
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
