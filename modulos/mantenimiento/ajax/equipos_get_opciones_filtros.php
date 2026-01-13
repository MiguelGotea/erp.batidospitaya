<?php
//equipos_get_opciones_filtro.php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? $_POST['columna'] : '';
    
    $opciones = [];
    
    // Opciones según columna
    switch ($columna) {
        case 'tipo_nombre':
            $sql = "SELECT DISTINCT t.nombre as valor, t.nombre as texto 
                    FROM mtto_equipos_tipos t 
                    INNER JOIN mtto_equipos e ON t.id = e.tipo_equipo_id
                    WHERE e.activo = 1
                    ORDER BY t.nombre";
            $opciones = $db->fetchAll($sql);
            break;
            
        case 'ubicacion_actual':
            $sql = "SELECT DISTINCT s.nombre as valor, s.nombre as texto
                    FROM sucursales s
                    WHERE s.id IN (
                        SELECT DISTINCT m.sucursal_destino_id
                        FROM mtto_equipos_movimientos m
                        WHERE m.estado = 'finalizado'
                    )
                    ORDER BY s.nombre";
            $opciones = $db->fetchAll($sql);
            break;
            
        case 'estado_solicitud':
            $opciones = [
                ['valor' => 'con_solicitud', 'texto' => 'Con Solicitud Pendiente'],
                ['valor' => 'sin_solicitud', 'texto' => 'Sin Solicitud'],
                ['valor' => 'operativo', 'texto' => 'Operativo']
            ];
            break;
            
        case 'marca':
            $sql = "SELECT DISTINCT marca as valor, marca as texto
                    FROM mtto_equipos
                    WHERE activo = 1 AND marca IS NOT NULL AND marca != ''
                    ORDER BY marca";
            $opciones = $db->fetchAll($sql);
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
?>