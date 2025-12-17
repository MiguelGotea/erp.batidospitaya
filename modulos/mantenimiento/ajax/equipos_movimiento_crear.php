<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

session_start();
$usuario_id = $_SESSION['usuario_id'];

try {
    $db->getConnection()->beginTransaction();
    
    $equipo_id = $_POST['equipo_id'];
    $sucursal_origen_id = $_POST['sucursal_origen_id'];
    $sucursal_destino_id = $_POST['sucursal_destino_id'];
    $fecha_programada = $_POST['fecha_programada'];
    $observaciones = $_POST['observaciones'] ?? '';
    $equipo_cambio_id = $_POST['equipo_cambio_id'] ?? null;
    
    // Validar que origen y destino sean diferentes
    if ($sucursal_origen_id == $sucursal_destino_id) {
        throw new Exception('La sucursal origen y destino no pueden ser iguales');
    }
    
    // Crear movimiento principal (retirar equipo)
    $db->query(
        "INSERT INTO mtto_equipos_movimientos 
         (equipo_id, sucursal_origen_id, sucursal_destino_id, fecha_programada, 
          observaciones, programado_por)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$equipo_id, $sucursal_origen_id, $sucursal_destino_id, $fecha_programada, 
         $observaciones, $usuario_id]
    );
    
    $mensaje = 'Movimiento programado exitosamente';
    
    // Si hay equipo de cambio, crear movimiento adicional
    if ($equipo_cambio_id) {
        // Obtener la central (código 0)
        $central = $db->fetchOne("SELECT id FROM sucursales WHERE codigo = '0'");
        
        if (!$central) {
            throw new Exception('No se encontró la sucursal central');
        }
        
        $db->query(
            "INSERT INTO mtto_equipos_movimientos 
             (equipo_id, sucursal_origen_id, sucursal_destino_id, fecha_programada, 
              observaciones, programado_por)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$equipo_cambio_id, $central['id'], $sucursal_origen_id, $fecha_programada, 
             'Equipo de reemplazo', $usuario_id]
        );
        
        $mensaje = 'Movimientos programados exitosamente (retiro y envío de reemplazo)';
    }
    
    $db->getConnection()->commit();
    
    echo json_encode(['success' => true, 'message' => $mensaje]);
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>