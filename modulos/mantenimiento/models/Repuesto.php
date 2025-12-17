<?php
// models/Repuesto.php
require_once __DIR__ . '/../config/database.php';

class Repuesto {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    // Obtener todos los repuestos activos
    public function obtenerTodos() {
        $sql = "SELECT * FROM mtto_equipos_repuestos 
                WHERE activo = 1 
                ORDER BY nombre ASC";
        
        return $this->db->fetchAll($sql);
    }
    
    // Obtener repuesto por ID
    public function obtenerPorId($id) {
        $sql = "SELECT * FROM mtto_equipos_repuestos WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    // Crear repuesto
    public function crear($datos) {
        $sql = "INSERT INTO mtto_equipos_repuestos (
                    nombre, descripcion, costo_base, unidad_medida
                ) VALUES (?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $datos['nombre'],
            $datos['descripcion'] ?? '',
            $datos['costo_base'] ?? 0,
            $datos['unidad_medida'] ?? 'Unidad'
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Actualizar repuesto
    public function actualizar($id, $datos) {
        $sql = "UPDATE mtto_equipos_repuestos 
                SET nombre = ?, 
                    descripcion = ?, 
                    costo_base = ?, 
                    unidad_medida = ?
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $datos['nombre'],
            $datos['descripcion'] ?? '',
            $datos['costo_base'] ?? 0,
            $datos['unidad_medida'] ?? 'Unidad',
            $id
        ]);
    }
    
    // Desactivar repuesto (soft delete)
    public function desactivar($id) {
        $sql = "UPDATE mtto_equipos_repuestos SET activo = 0 WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    // Activar repuesto
    public function activar($id) {
        $sql = "UPDATE mtto_equipos_repuestos SET activo = 1 WHERE id = ?";
        return $this->db->query($sql, [$id]);
    }
    
    // Buscar repuestos
    public function buscar($termino) {
        $sql = "SELECT * FROM mtto_equipos_repuestos 
                WHERE activo = 1 
                AND (nombre LIKE ? OR descripcion LIKE ?)
                ORDER BY nombre ASC";
        
        $busqueda = "%$termino%";
        return $this->db->fetchAll($sql, [$busqueda, $busqueda]);
    }
    
    // Obtener repuestos más utilizados
    public function obtenerMasUtilizados($limite = 10) {
        $sql = "SELECT 
                    r.*,
                    COUNT(mr.id) as veces_usado,
                    SUM(mr.cantidad) as cantidad_total,
                    SUM(mr.precio_total) as costo_total
                FROM mtto_equipos_repuestos r
                INNER JOIN mtto_equipos_mantenimientos_repuestos mr ON r.id = mr.repuesto_id
                WHERE r.activo = 1
                GROUP BY r.id
                ORDER BY veces_usado DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limite]);
    }
    
    // Obtener estadísticas de un repuesto
    public function obtenerEstadisticas($repuesto_id) {
        $sql = "SELECT 
                    COUNT(DISTINCT mr.mantenimiento_id) as veces_usado,
                    SUM(mr.cantidad) as cantidad_total_usada,
                    AVG(mr.precio_unitario) as precio_promedio,
                    MIN(mr.precio_unitario) as precio_minimo,
                    MAX(mr.precio_unitario) as precio_maximo,
                    SUM(mr.precio_total) as costo_total
                FROM mtto_equipos_mantenimientos_repuestos mr
                WHERE mr.repuesto_id = ?";
        
        return $this->db->fetchOne($sql, [$repuesto_id]);
    }
    
    // Obtener historial de uso de un repuesto
    public function obtenerHistorialUso($repuesto_id) {
        $sql = "SELECT 
                    mr.*,
                    m.fecha_inicio, m.tipo,
                    e.codigo as equipo_codigo
                FROM mtto_equipos_mantenimientos_repuestos mr
                INNER JOIN mtto_equipos_mantenimientos m ON mr.mantenimiento_id = m.id
                INNER JOIN mtto_equipos e ON m.equipo_id = e.id
                WHERE mr.repuesto_id = ?
                ORDER BY m.fecha_inicio DESC";
        
        return $this->db->fetchAll($sql, [$repuesto_id]);
    }
}
?>