<?php
// public_html/modulos/mantenimiento/ajax/equipos_registro_guardar.php
header('Content-Type: application/json');
require_once '../../../includes/auth.php';
require_once '../config/database.php';

try {
    // Validar permisos
    $usuario = obtenerUsuarioActual();
    if ($usuario['CodNivelesCargos'] != 35) {
        throw new Exception('No tiene permisos para esta acción');
    }
    
    // Recoger datos
    $codigo = $_POST['codigo'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $tipo_id = $_POST['tipo_id'] ?? 0;
    $marca = $_POST['marca'] ?? null;
    $modelo = $_POST['modelo'] ?? null;
    $serial = $_POST['serial'] ?? null;
    $caracteristicas = $_POST['caracteristicas'] ?? null;
    $fecha_compra = $_POST['fecha_compra'] ?? null;
    $proveedor_compra = $_POST['proveedor_compra'] ?? null;
    $garantia_meses = $_POST['garantia_meses'] ?? null;
    $fecha_vencimiento_garantia = $_POST['fecha_vencimiento_garantia'] ?? null;
    $frecuencia_mantenimiento_meses = $_POST['frecuencia_mantenimiento_meses'] ?? 3;
    $costo_compra = $_POST['costo_compra'] ?? null;
    $observaciones = $_POST['observaciones'] ?? null;
    $registrado_por = $_POST['registrado_por'] ?? 0;
    $ubicacion_inicial = $_POST['ubicacion_inicial'] ?? 'Central';
    $sucursal_inicial_id = $_POST['sucursal_inicial_id'] ?? null;
    
    // Validaciones
    if (empty($codigo) || empty($nombre) || empty($tipo_id)) {
        throw new Exception('Faltan datos requeridos');
    }
    
    // Verificar que el código no exista
    $existe = $db->fetchOne("SELECT COUNT(*) as total FROM mtto_equipos WHERE codigo = :codigo", 
                            ['codigo' => $codigo]);
    
    if ($existe['total'] > 0) {
        throw new Exception('Ya existe un equipo con ese código');
    }
    
    $db->getConnection()->beginTransaction();
    
    // Insertar equipo
    $sqlInsert = "
        INSERT INTO mtto_equipos 
        (codigo, nombre, tipo_id, marca, modelo, serial, caracteristicas, 
         fecha_compra, proveedor_compra, garantia_meses, fecha_vencimiento_garantia,
         frecuencia_mantenimiento_meses, costo_compra, observaciones, registrado_por)
        VALUES 
        (:codigo, :nombre, :tipo_id, :marca, :modelo, :serial, :caracteristicas,
         :fecha_compra, :proveedor_compra, :garantia_meses, :fecha_vencimiento_garantia,
         :frecuencia_mantenimiento_meses, :costo_compra, :observaciones, :registrado_por)
    ";
    
    $stmt = $db->getConnection()->prepare($sqlInsert);
    $stmt->execute([
        'codigo' => $codigo,
        'nombre' => $nombre,
        'tipo_id' => $tipo_id,
        'marca' => $marca,
        'modelo' => $modelo,
        'serial' => $serial,
        'caracteristicas' => $caracteristicas,
        'fecha_compra' => $fecha_compra ?: null,
        'proveedor_compra' => $proveedor_compra,
        'garantia_meses' => $garantia_meses ?: null,
        'fecha_vencimiento_garantia' => $fecha_vencimiento_garantia ?: null,
        'frecuencia_mantenimiento_meses' => $frecuencia_mantenimiento_meses,
        'costo_compra' => $costo_compra ?: null,
        'observaciones' => $observaciones,
        'registrado_por' => $registrado_por
    ]);
    
    $equipo_id = $db->lastInsertId();
    
    // Registrar movimiento inicial
    $sqlMovimiento = "
        INSERT INTO mtto_equipos_movimientos 
        (equipo_id, tipo_movimiento, origen_tipo, destino_tipo, destino_id, 
         fecha_planificada, fecha_ejecutada, estado, observaciones, registrado_por)
        VALUES 
        (:equipo_id, :tipo_movimiento, 'Central', :destino_tipo, :destino_id,
         CURDATE(), CURDATE(), 'Completado', 'Ubicación inicial del equipo', :registrado_por)
    ";
    
    $stmtMovimiento = $db->getConnection()->prepare($sqlMovimiento);
    $stmtMovimiento->execute([
        'equipo_id' => $equipo_id,
        'tipo_movimiento' => ($ubicacion_inicial === 'Sucursal') ? 'Central a Sucursal' : 'Sucursal a Central',
        'destino_tipo' => $ubicacion_inicial,
        'destino_id' => ($ubicacion_inicial === 'Sucursal') ? $sucursal_inicial_id : null,
        'registrado_por' => $registrado_por
    ]);
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Equipo registrado exitosamente',
        'equipo_id' => $equipo_id
    ]);
    
} catch (Exception $e) {
    if ($db->getConnection()->inTransaction()) {
        $db->getConnection()->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}