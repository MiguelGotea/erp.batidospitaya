<?php
// auditorias_get_opciones_filtro.php
require_once '../conexion.php';
header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    $opciones = [];
    
    if ($columna === 'sucursal') {
        // Obtener sucursales activas que son sucursales físicas
        $stmt = $conn->prepare("
            SELECT nombre as valor, nombre as texto 
            FROM sucursales 
            WHERE activa = 1 AND sucursal = 1 
            ORDER BY nombre
        ");
        $stmt->execute();
        $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    else if ($columna === 'tipo_auditoria') {
        // Tipos de auditoría fijos
        $opciones = [
            ['valor' => 'limpieza', 'texto' => 'Limpieza'],
            ['valor' => 'personal', 'texto' => 'Personal'],
            ['valor' => 'servicio', 'texto' => 'Servicio'],
            ['valor' => 'procesos', 'texto' => 'Procesos'],
            ['valor' => 'promociones', 'texto' => 'Promociones']
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