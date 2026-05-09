<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    if (!$columna) {
        throw new Exception("Columna no especificada");
    }
    
    $opciones = [];
    
    switch ($columna) {
        case 'sucursal_nombre':
            $sql = "SELECT DISTINCT nombre as valor, nombre as texto FROM sucursales WHERE activa = 1 ORDER BY nombre";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $opciones = $stmt->fetchAll();
            break;
            
        case 'feriado_tipo':
            $opciones = [
                ['valor' => 'Nacional', 'texto' => 'Nacional'],
                ['valor' => 'Departamental', 'texto' => 'Departamental']
            ];
            break;
            
        case 'estado':
            $opciones = [
                ['valor' => 'Pendiente', 'texto' => 'Pendiente'],
                ['valor' => 'Pagado', 'texto' => 'Pagado'],
                ['valor' => 'Descansado', 'texto' => 'Descansado'],
                ['valor' => 'Con Marcación', 'texto' => 'Con Marcación'],
                ['valor' => 'Sin marcación', 'texto' => 'Sin marcación']
            ];
            break;
            
        default:
            // Para otras columnas, intentar obtener valores únicos de la base de datos si aplica
            // Pero para este caso, solo necesitamos estos 3
            break;
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
