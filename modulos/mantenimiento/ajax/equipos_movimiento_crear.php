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
    // DEPURACIÓN: Mostrar los valores recibidos
    error_log('=== DEPURACIÓN equipos_movimiento_crear.php ===');
    error_log('POST recibido: ' . print_r($_POST, true));
    error_log('Usuario ID: ' . $usuario_id);
    $db->getConnection()->beginTransaction();
    
    $equipo_id = $_POST['equipo_id'];
    $sucursal_origen_codigo = $_POST['sucursal_origen_id']; // Ya viene como código
    $sucursal_destino_codigo = $_POST['sucursal_destino_id']; // Ya viene como código
    $fecha_programada = $_POST['fecha_programada'];
    $observaciones = $_POST['observaciones'] ?? '';
    $equipo_cambio_id = $_POST['equipo_cambio_id'] ?? null;

    // DEPURACIÓN: Mostrar valores antes de procesar
    error_log('Equipo ID recibido: ' . $equipo_id);
    error_log('Tipo de equipo_id: ' . gettype($equipo_id));
    
    // Validar datos requeridos
    if (empty($equipo_id)) {
        throw new Exception('El ID del equipo es requerido');
    }
    
    //if (empty($sucursal_destino_codigo)) {
    //    throw new Exception('La sucursal destino es requerida');
    //}
    
    if (empty($fecha_programada)) {
        throw new Exception('La fecha programada es requerida');
    }
    
    // Verificar que el equipo existe
    $equipo_existe = $db->fetchOne(
        "SELECT id FROM mtto_equipos WHERE id = ? AND activo = 1", 
        [$equipo_id]
    );
    
    error_log('Resultado verificación equipo: ' . print_r($equipo_existe, true));
    
    if (!$equipo_existe) {
        throw new Exception("El equipo con ID $equipo_id no existe o está inactivo");
    }

    // CONVERTIR LAS MISMAS VARIABLES A INT
    $sucursal_origen_codigo = (int)$sucursal_origen_codigo;     // Ahora es INT
    $sucursal_destino_codigo = (int)$sucursal_destino_codigo;   // Ahora es INT

    // Validar que origen y destino sean diferentes
    if ($sucursal_origen_codigo == $sucursal_destino_codigo) {
        throw new Exception('La sucursal origen y destino no pueden ser iguales');
    }

    // DEPURACIÓN: Mostrar valores que se insertarán
    error_log('Valores a insertar:');
    error_log('- equipo_id: ' . $equipo_id . ' (tipo: ' . gettype($equipo_id) . ')');
    error_log('- sucursal_origen: ' . $sucursal_origen_codigo);
    error_log('- sucursal_destino: ' . $sucursal_destino_codigo);
    error_log('- fecha_programada: ' . $fecha_programada);
    
    // Crear movimiento principal (retirar equipo)
    $db->query(
        "INSERT INTO mtto_equipos_movimientos 
         (equipo_id, sucursal_origen_id, sucursal_destino_id, fecha_programada, 
          observaciones, programado_por)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$equipo_id, $sucursal_origen_codigo, $sucursal_destino_codigo, $fecha_programada, 
         $observaciones, $usuario_id]
    );
    
    $mensaje = 'Movimiento programado exitosamente';
    
    // Si hay equipo de cambio, crear movimiento adicional
    if ($equipo_cambio_id) {
        // La central siempre es código 0
        $codigo_central = '0';
        
        $db->query(
            "INSERT INTO mtto_equipos_movimientos 
             (equipo_id, sucursal_origen_id, sucursal_destino_id, fecha_programada, 
              observaciones, programado_por)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$equipo_cambio_id, $codigo_central, $sucursal_origen_codigo, $fecha_programada, 
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