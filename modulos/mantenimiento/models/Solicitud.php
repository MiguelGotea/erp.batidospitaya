<?php
// models/Solicitud.php
require_once __DIR__ . '/../config/database.php';

class Solicitud {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    // Obtener todas las solicitudes
    public function obtenerTodas($filtros = []) {
        $sql = "SELECT 
                    s.*,
                    e.codigo, e.marca, e.modelo,
                    suc.nombre as sucursal,
                    o.Nombre as solicitante_nombre, o.Apellido as solicitante_apellido,
                    of.Nombre as finalizador_nombre, of.Apellido as finalizador_apellido,
                    (SELECT COUNT(*) FROM mtto_equipos_solicitudes_fotos WHERE solicitud_id = s.id) as num_fotos,
                    (SELECT COUNT(*) FROM mtto_equipos_mantenimientos WHERE solicitud_id = s.id) as tiene_mantenimiento
                FROM mtto_equipos_solicitudes s
                INNER JOIN mtto_equipos e ON s.equipo_id = e.id
                INNER JOIN sucursales suc ON s.sucursal_id = suc.codigo
                INNER JOIN Operarios o ON s.solicitado_por = o.CodOperario
                LEFT JOIN Operarios of ON s.finalizado_por = of.CodOperario
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filtros['estado'])) {
            $sql .= " AND s.estado = ?";
            $params[] = $filtros['estado'];
        }
        
        if (isset($filtros['equipo_id'])) {
            $sql .= " AND s.equipo_id = ?";
            $params[] = $filtros['equipo_id'];
        }
        
        if (isset($filtros['sucursal_id'])) {
            $sql .= " AND s.sucursal_id = ?";
            $params[] = $filtros['sucursal_id'];
        }
        
        $sql .= " ORDER BY 
                  CASE s.estado WHEN 'solicitado' THEN 1 ELSE 2 END,
                  s.fecha_solicitud DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    // Obtener solicitud por ID
    public function obtenerPorId($id) {
        $sql = "SELECT 
                    s.*,
                    e.codigo, e.marca, e.modelo,
                    suc.nombre as sucursal,
                    o.Nombre as solicitante_nombre, o.Apellido as solicitante_apellido,
                    of.Nombre as finalizador_nombre, of.Apellido as finalizador_apellido
                FROM mtto_equipos_solicitudes s
                INNER JOIN mtto_equipos e ON s.equipo_id = e.id
                INNER JOIN sucursales suc ON s.sucursal_id = suc.codigo
                INNER JOIN Operarios o ON s.solicitado_por = o.CodOperario
                LEFT JOIN Operarios of ON s.finalizado_por = of.CodOperario
                WHERE s.id = ?";
        
        return $this->db->fetchOne($sql, [$id]);
    }
    
    // Crear solicitud
    public function crear($datos) {
        $sql = "INSERT INTO mtto_equipos_solicitudes (
                    equipo_id, sucursal_id, descripcion_problema, solicitado_por
                ) VALUES (?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $datos['equipo_id'],
            $datos['sucursal_id'],
            $datos['descripcion_problema'],
            $datos['solicitado_por']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Finalizar solicitud
    public function finalizar($id, $datos) {
        $sql = "UPDATE mtto_equipos_solicitudes 
                SET estado = 'finalizado',
                    finalizado_por = ?,
                    fecha_finalizacion = NOW(),
                    observaciones_finalizacion = ?
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $datos['finalizado_por'],
            $datos['observaciones_finalizacion'] ?? '',
            $id
        ]);
    }
    
    // Obtener fotos de una solicitud
    public function obtenerFotos($solicitud_id) {
        $sql = "SELECT * FROM mtto_equipos_solicitudes_fotos 
                WHERE solicitud_id = ? 
                ORDER BY fecha_subida ASC";
        
        return $this->db->fetchAll($sql, [$solicitud_id]);
    }
    
    // Agregar foto a solicitud
    public function agregarFoto($solicitud_id, $ruta_archivo) {
        $sql = "INSERT INTO mtto_equipos_solicitudes_fotos (solicitud_id, ruta_archivo)
                VALUES (?, ?)";
        
        return $this->db->query($sql, [$solicitud_id, $ruta_archivo]);
    }
    
    // Obtener solicitudes pendientes
    public function obtenerPendientes() {
        return $this->obtenerTodas(['estado' => 'solicitado']);
    }
    
    // Verificar si equipo tiene solicitud pendiente
    public function equipoTieneSolicitudPendiente($equipo_id) {
        $sql = "SELECT COUNT(*) as total 
                FROM mtto_equipos_solicitudes 
                WHERE equipo_id = ? AND estado = 'solicitado'";
        
        $result = $this->db->fetchOne($sql, [$equipo_id]);
        return $result['total'] > 0;
    }
    
    // Obtener solicitud pendiente de un equipo
    public function obtenerSolicitudPendienteEquipo($equipo_id) {
        $sql = "SELECT * FROM mtto_equipos_solicitudes 
                WHERE equipo_id = ? AND estado = 'solicitado' 
                ORDER BY fecha_solicitud DESC 
                LIMIT 1";
        
        return $this->db->fetchOne($sql, [$equipo_id]);
    }
}
?>