<?php
// models/Mantenimiento.php
require_once __DIR__ . '/../config/database.php';

class Mantenimiento {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    // Obtener todos los mantenimientos
    public function obtenerTodos($filtros = []) {
        $sql = "SELECT 
                    m.*,
                    mp.fecha_programada, mp.tipo as tipo_programado,
                    e.codigo, e.marca, e.modelo,
                    p.nombre as proveedor_nombre,
                    o.Nombre as registrado_nombre, o.Apellido as registrado_apellido
                FROM mtto_equipos_mantenimientos m
                INNER JOIN mtto_equipos_mantenimientos_programados mp ON m.mantenimiento_programado_id = mp.id
                INNER JOIN mtto_equipos e ON m.equipo_id = e.id
                LEFT JOIN proveedores_compras_servicios p ON m.proveedor_servicio_id = p.id
                LEFT JOIN Operarios o ON m.registrado_por = o.CodOperario
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filtros['equipo_id'])) {
            $sql .= " AND m.equipo_id = ?";
            $params[] = $filtros['equipo_id'];
        }
        
        if (isset($filtros['tipo'])) {
            $sql .= " AND m.tipo = ?";
            $params[] = $filtros['tipo'];
        }
        
        if (isset($filtros['fecha_inicio'])) {
            $sql .= " AND m.fecha_inicio >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        
        if (isset($filtros['fecha_fin'])) {
            $sql .= " AND m.fecha_inicio <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        
        $sql .= " ORDER BY m.fecha_inicio DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Obtener mantenimiento por ID
    public function obtenerPorId($id) {
        $sql = "SELECT 
                    m.*,
                    mp.fecha_programada,
                    e.codigo, e.marca, e.modelo,
                    p.nombre as proveedor_nombre,
                    s.id as solicitud_id, s.descripcion_problema
                FROM mtto_equipos_mantenimientos m
                INNER JOIN mtto_equipos_mantenimientos_programados mp ON m.mantenimiento_programado_id = mp.id
                INNER JOIN mtto_equipos e ON m.equipo_id = e.id
                LEFT JOIN proveedores_compras_servicios p ON m.proveedor_servicio_id = p.id
                LEFT JOIN mtto_equipos_solicitudes s ON m.solicitud_id = s.id
                WHERE m.id = ?";
        
        return $this->db->fetchOne($sql, [$id]);
    }
    
    // Crear mantenimiento
    public function crear($datos) {
        $sql = "INSERT INTO mtto_equipos_mantenimientos (
                    mantenimiento_programado_id, equipo_id, solicitud_id, tipo,
                    proveedor_servicio_id, fecha_inicio, fecha_finalizacion,
                    problema_encontrado, trabajo_realizado, observaciones,
                    costo_total_repuestos, registrado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $datos['mantenimiento_programado_id'],
            $datos['equipo_id'],
            $datos['solicitud_id'] ?? null,
            $datos['tipo'],
            $datos['proveedor_servicio_id'] ?? null,
            $datos['fecha_inicio'],
            $datos['fecha_finalizacion'] ?? null,
            $datos['problema_encontrado'] ?? '',
            $datos['trabajo_realizado'],
            $datos['observaciones'] ?? '',
            $datos['costo_total_repuestos'] ?? 0,
            $datos['registrado_por']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Obtener repuestos de un mantenimiento
    public function obtenerRepuestos($mantenimiento_id) {
        $sql = "SELECT 
                    mr.*,
                    r.nombre, r.descripcion, r.unidad_medida
                FROM mtto_equipos_mantenimientos_repuestos mr
                INNER JOIN mtto_equipos_repuestos r ON mr.repuesto_id = r.id
                WHERE mr.mantenimiento_id = ?";
        
        return $this->db->fetchAll($sql, [$mantenimiento_id]);
    }
    
    // Agregar repuesto a mantenimiento
    public function agregarRepuesto($mantenimiento_id, $datos) {
        $sql = "INSERT INTO mtto_equipos_mantenimientos_repuestos (
                    mantenimiento_id, repuesto_id, cantidad, precio_unitario, precio_total
                ) VALUES (?, ?, ?, ?, ?)";
        
        return $this->db->query($sql, [
            $mantenimiento_id,
            $datos['repuesto_id'],
            $datos['cantidad'],
            $datos['precio_unitario'],
            $datos['precio_total']
        ]);
    }
    
    // Obtener fotos de un mantenimiento
    public function obtenerFotos($mantenimiento_id) {
        $sql = "SELECT * FROM mtto_equipos_mantenimientos_fotos 
                WHERE mantenimiento_id = ? 
                ORDER BY fecha_subida ASC";
        
        return $this->db->fetchAll($sql, [$mantenimiento_id]);
    }
    
    // Agregar foto a mantenimiento
    public function agregarFoto($mantenimiento_id, $ruta_archivo, $descripcion = '') {
        $sql = "INSERT INTO mtto_equipos_mantenimientos_fotos 
                (mantenimiento_id, ruta_archivo, descripcion)
                VALUES (?, ?, ?)";
        
        return $this->db->query($sql, [$mantenimiento_id, $ruta_archivo, $descripcion]);
    }
    
    // Programar mantenimiento
    public function programar($datos) {
        $sql = "INSERT INTO mtto_equipos_mantenimientos_programados (
                    equipo_id, fecha_programada, tipo, programado_por
                ) VALUES (?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $datos['equipo_id'],
            $datos['fecha_programada'],
            $datos['tipo'],
            $datos['programado_por']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Obtener mantenimientos programados
    public function obtenerProgramados($filtros = []) {
        $sql = "SELECT 
                    mp.*,
                    e.codigo, e.marca, e.modelo,
                    m.id as mantenimiento_realizado_id,
                    (SELECT s.nombre 
                     FROM mtto_equipos_movimientos mov 
                     INNER JOIN sucursales s ON mov.sucursal_destino_id = s.codigo 
                     WHERE mov.equipo_id = mp.equipo_id AND mov.estado = 'finalizado' 
                     ORDER BY mov.fecha_realizada DESC LIMIT 1) as ubicacion_actual
                FROM mtto_equipos_mantenimientos_programados mp
                INNER JOIN mtto_equipos e ON mp.equipo_id = e.id
                LEFT JOIN mtto_equipos_mantenimientos m ON mp.id = m.mantenimiento_programado_id
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filtros['estado'])) {
            $sql .= " AND mp.estado = ?";
            $params[] = $filtros['estado'];
        }
        
        if (isset($filtros['mes'])) {
            $sql .= " AND MONTH(mp.fecha_programada) = ?";
            $params[] = $filtros['mes'];
        }
        
        if (isset($filtros['anio'])) {
            $sql .= " AND YEAR(mp.fecha_programada) = ?";
            $params[] = $filtros['anio'];
        }
        
        $sql .= " ORDER BY mp.fecha_programada ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Actualizar estado de mantenimiento programado
    public function actualizarEstadoProgramado($id, $estado) {
        $sql = "UPDATE mtto_equipos_mantenimientos_programados 
                SET estado = ? 
                WHERE id = ?";
        
        return $this->db->query($sql, [$estado, $id]);
    }
    
    // Mover mantenimiento programado
    public function moverProgramado($id, $nueva_fecha) {
        $sql = "UPDATE mtto_equipos_mantenimientos_programados 
                SET fecha_programada = ? 
                WHERE id = ?";
        
        return $this->db->query($sql, [$nueva_fecha, $id]);
    }
    
    // Desprogramar mantenimiento
    public function desprogramar($id) {
        $sql = "DELETE FROM mtto_equipos_mantenimientos_programados WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
}
?>