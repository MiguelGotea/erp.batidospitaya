<?php
// models/Movimiento.php
require_once __DIR__ . '/../config/database.php';

class Movimiento {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    // Obtener todos los movimientos con filtros
    public function obtenerTodos($filtros = []) {
        $sql = "SELECT 
                    m.*,
                    e.codigo, e.marca, e.modelo,
                    so.nombre as sucursal_origen, so.codigo as codigo_origen,
                    sd.nombre as sucursal_destino, sd.codigo as codigo_destino,
                    op.Nombre as programado_nombre, op.Apellido as programado_apellido,
                    of.Nombre as finalizado_nombre, of.Apellido as finalizado_apellido
                FROM mtto_equipos_movimientos m
                INNER JOIN mtto_equipos e ON m.equipo_id = e.id
                INNER JOIN sucursales so ON m.sucursal_origen_id = so.id
                INNER JOIN sucursales sd ON m.sucursal_destino_id = sd.id
                LEFT JOIN Operarios op ON m.programado_por = op.CodOperario
                LEFT JOIN Operarios of ON m.finalizado_por = of.CodOperario
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filtros['sucursal_id'])) {
            $sql .= " AND (m.sucursal_origen_id = ? OR m.sucursal_destino_id = ?)";
            $params[] = $filtros['sucursal_id'];
            $params[] = $filtros['sucursal_id'];
        }
        
        if (isset($filtros['estado'])) {
            $sql .= " AND m.estado = ?";
            $params[] = $filtros['estado'];
        }
        
        if (isset($filtros['equipo_id'])) {
            $sql .= " AND m.equipo_id = ?";
            $params[] = $filtros['equipo_id'];
        }
        
        $sql .= " ORDER BY 
                  CASE m.estado WHEN 'agendado' THEN 1 ELSE 2 END,
                  m.fecha_programada DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Obtener movimiento por ID
    public function obtenerPorId($id) {
        $sql = "SELECT 
                    m.*,
                    e.codigo, e.marca, e.modelo,
                    so.nombre as sucursal_origen,
                    sd.nombre as sucursal_destino
                FROM mtto_equipos_movimientos m
                INNER JOIN mtto_equipos e ON m.equipo_id = e.id
                INNER JOIN sucursales so ON m.sucursal_origen_id = so.id
                INNER JOIN sucursales sd ON m.sucursal_destino_id = sd.id
                WHERE m.id = ?";
        
        return $this->db->fetchOne($sql, [$id]);
    }
    
    // Crear movimiento
    public function crear($datos) {
        $sql = "INSERT INTO mtto_equipos_movimientos (
                    equipo_id, sucursal_origen_id, sucursal_destino_id,
                    fecha_programada, observaciones, programado_por
                ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $datos['equipo_id'],
            $datos['sucursal_origen_id'],
            $datos['sucursal_destino_id'],
            $datos['fecha_programada'],
            $datos['observaciones'] ?? '',
            $datos['programado_por']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Finalizar movimiento
    public function finalizar($id, $usuario_id) {
        $sql = "UPDATE mtto_equipos_movimientos 
                SET estado = 'finalizado',
                    fecha_realizada = NOW(),
                    finalizado_por = ?
                WHERE id = ? AND estado = 'agendado'";
        
        return $this->db->query($sql, [$usuario_id, $id]);
    }
    
    // Obtener ubicación actual de un equipo
    public function obtenerUbicacionActual($equipo_id) {
        $sql = "SELECT 
                    s.id, s.codigo, s.nombre
                FROM mtto_equipos_movimientos m
                INNER JOIN sucursales s ON m.sucursal_destino_id = s.id
                WHERE m.equipo_id = ? AND m.estado = 'finalizado'
                ORDER BY m.fecha_realizada DESC
                LIMIT 1";
        
        return $this->db->fetchOne($sql, [$equipo_id]);
    }
    
    // Verificar si equipo tiene movimiento pendiente
    public function tieneMovimientoPendiente($equipo_id) {
        $sql = "SELECT COUNT(*) as total 
                FROM mtto_equipos_movimientos 
                WHERE equipo_id = ? AND estado = 'agendado'";
        
        $result = $this->db->fetchOne($sql, [$equipo_id]);
        return $result['total'] > 0;
    }
    
    // Obtener movimientos pendientes de una sucursal
    public function obtenerPendientesPorSucursal($sucursal_id) {
        $sql = "SELECT 
                    m.*,
                    e.codigo, e.marca, e.modelo
                FROM mtto_equipos_movimientos m
                INNER JOIN mtto_equipos e ON m.equipo_id = e.id
                WHERE (m.sucursal_origen_id = ? OR m.sucursal_destino_id = ?)
                AND m.estado = 'agendado'
                ORDER BY m.fecha_programada ASC";
        
        return $this->db->fetchAll($sql, [$sucursal_id, $sucursal_id]);
    }
}
?>