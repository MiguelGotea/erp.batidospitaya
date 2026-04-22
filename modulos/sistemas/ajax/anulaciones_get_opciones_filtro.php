<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    if (empty($columna)) {
        throw new Exception("Columna no especificada");
    }
    
    $opciones = [];
    
    if ($columna === 'Status') {
        $opciones = [
            ['valor' => 0, 'texto' => 'Pendiente'],
            ['valor' => 1, 'texto' => 'Aprobado'],
            ['valor' => 2, 'texto' => 'Rechazado']
        ];
    } elseif ($columna === 'Sucursal') {
        $stmt = $conn->query("SELECT codigo as valor, nombre as texto FROM sucursales WHERE activa = 1 ORDER BY nombre ASC");
        $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($columna === 'EjecutadoEnTienda') {
        $opciones = [
            ['valor' => 0, 'texto' => 'Pendiente'],
            ['valor' => 1, 'texto' => 'Ejecutado']
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
