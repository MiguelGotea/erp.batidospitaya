<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No autorizado');
    }

    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    $opciones = [];

    if ($columna === 'estado') {
        $opciones = [
            ['valor' => 'borrador', 'texto' => 'Borrador'],
            ['valor' => 'activa', 'texto' => 'Activa'],
            ['valor' => 'inactiva', 'texto' => 'Inactiva'],
            ['valor' => 'archivada', 'texto' => 'Archivada']
        ];
    } elseif ($columna === 'resultado_tipo') {
        $opciones = [
            ['valor' => 'pct_producto', 'texto' => '% sobre producto'],
            ['valor' => 'pct_factura', 'texto' => '% sobre factura'],
            ['valor' => 'monto_producto', 'texto' => 'Monto fijo producto'],
            ['valor' => 'monto_factura', 'texto' => 'Monto fijo factura']
        ];
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
