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
        $stmt = $conn->query("SELECT DISTINCT Sucursal FROM AnulacionPedidosHost ORDER BY Sucursal ASC");
        while ($row = $stmt->fetch()) {
            $opciones[] = ['valor' => $row['Sucursal'], 'texto' => 'Sucursal ' . $row['Sucursal']];
        }
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
