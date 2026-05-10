<?php
// compra_local_configuracion_despacho_get_sucursales.php
// Obtiene las sucursales activas físicas

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    // Obtener sucursales activas y físicas
    $sql = "SELECT codigo, nombre 
            FROM sucursales 
            WHERE activa = 1 AND sucursal = 1
            ORDER BY nombre";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'sucursales' => $sucursales
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener sucursales: ' . $e->getMessage()
    ]);
}
