<?php
// ajax/configuracion_logistica_get_sucursales.php
// Retorna las sucursales activas (activa=1 AND sucursal=1)

require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT codigo, nombre
            FROM sucursales
            WHERE activa = 1 AND sucursal = 1
            ORDER BY nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'sucursales' => $sucursales
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener sucursales: ' . $e->getMessage()
    ]);
}
