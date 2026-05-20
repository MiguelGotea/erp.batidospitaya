<?php
// reclamos_get_opciones_filtro.php
require_once '../../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    $opciones = [];
    
    if ($columna === 'sucursal') {
        $stmt = $conn->prepare("
            SELECT DISTINCT s.nombre as valor, s.nombre as texto 
            FROM reclamos r
            JOIN sucursales s ON r.sucursal_codigo = s.codigo
            ORDER BY s.nombre
        ");
        $stmt->execute();
        $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    elseif ($columna === 'medio_compra') {
        $stmt = $conn->prepare("
            SELECT DISTINCT COALESCE(r.medio_compra, '--') as valor, COALESCE(r.medio_compra, '--') as texto 
            FROM reclamos r
            ORDER BY r.medio_compra
        ");
        $stmt->execute();
        $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($columna === 'estado') {
        $opciones = [
            ['valor' => 'Abierto', 'texto' => 'Abierto'],
            ['valor' => 'Cerrado', 'texto' => 'Cerrado']
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
