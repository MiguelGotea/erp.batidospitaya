<?php
// compra_local_consolidado_pedidos_get_sucursales.php
// Obtiene lista de sucursales activas

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    // Obtener sucursales físicas activas
    $sql = "SELECT codigo, nombre 
            FROM sucursales 
            WHERE activa = 1 
            AND sucursal = 1
            ORDER BY nombre";

    $stmt = $conn->query($sql);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'sucursales' => $sucursales
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
