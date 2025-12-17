<?php
// models/Equipo.php
require_once __DIR__ . '/../config/database.php';

class Equipo {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    // Obtener todos los equipos con información calculada
    public function obtenerTodos($filtros = []) {
        $sql = "SELECT 
                    e.*,
                    t.nombre as tipo_nombre,
                    p.nombre as proveedor_nombre,
                    (SELECT s.nombre 
                     FROM mtto_equipos_movimientos m 
                     INNER JOIN sucursales s ON m.sucursal_destino_id = s.codigo 
                     WHERE m.equipo_id = e.id AND m.estado = 'finalizado' 
                     ORDER BY m.fecha_realizada DESC LIMIT 1) as ubicacion_actual,
                    (SELECT s.codigo 
                     FROM mtto_equipos_movimientos m 
                     INNER JOIN sucursales s ON m.sucursal_destino_id = s.codigo 
                     WHERE m.equipo_id = e.id AND m.estado = 'finalizado' 
                     ORDER BY m.fecha_realizada DESC LIMIT 1) as codigo_sucursal_actual,
                    CASE 
                        WHEN (SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id) IS NOT NULL
                        THEN DATE_ADD((SELECT MAX(mm.fecha_finalizacion) FROM mtto_equipos_mantenimientos mm WHERE mm.equipo_id = e.id), INTERVAL e.frecuencia_mantenimiento_meses MONTH)
                        ELSE DATE_ADD(e.fecha_compra, INTERVAL e.frecuencia_mantenimiento_meses MONTH)
                    END as proxima_fecha_preventivo,
                    (SELECT COUNT(*) FROM mtto_equipos_solicitudes sol 
                     WHERE sol.equipo_id = e.id AND sol.estado = 'solicitado') as tiene_solicitud_pendiente,
                    (SELECT sol.id FROM mtto_equipos_solicitudes sol 
                     WHERE sol.equipo_id = e.id AND sol.estado = 'solicitado' 
                     ORDER BY sol.fecha_solicitud DESC LIMIT 1) as solicitud_id,
                    (SELECT mov.fecha_programada 
                     FROM mtto_equipos_movimientos mov 
                     WHERE mov.equipo_id = e.id AND mov.estado = 'agendado' 
                     ORDER BY mov.fecha_programada ASC LIMIT 1) as fecha_movimiento_programado
                FROM mtto_equipos e
                LEFT JOIN mtto_equipos_tipos t ON e.tipo_equipo_id = t.id
                LEFT JOIN proveedores_compras_servicios p ON e.proveedor_compra_id = p.id
                WHERE e.activo = 1";
        
        return $this->db->fetchAll($sql);
    }
    
    // Obtener equipos por sucursal
    public function obtenerPorSucursal($codigoSucursal) {
        $sql = "SELECT 
                    e.*,
                    t.nombre as tipo_nombre,
                    (SELECT s.nombre 
                     FROM mtto_equipos_movimientos m 
                     INNER JOIN sucursales s ON m.sucursal_destino_id = s.id 
                     WHERE m.equipo_id = e.id AND m.estado = 'finalizado' 
                     ORDER BY m.fecha_realizada DESC LIMIT 1) as ubicacion_actual
                FROM mtto_equipos e
                LEFT JOIN mtto_equipos_tipos t ON e.tipo_equipo_id = t.id
                WHERE e.activo = 1
                AND e.id IN (
                    SELECT mov.equipo_id 
                    FROM mtto_equipos_movimientos mov
                    INNER JOIN sucursales s ON mov.sucursal_destino_id = s.id
                    WHERE s.codigo = ? AND mov.estado = 'finalizado'
                    AND mov.id = (
                        SELECT MAX(m2.id) 
                        FROM mtto_equipos_movimientos m2 
                        WHERE m2.equipo_id = mov.equipo_id AND m2.estado = 'finalizado'
                    )
                )";
        
        return $this->db->fetchAll($sql, [$codigoSucursal]);
    }
    
    // Obtener equipo por ID con toda la información
    public function obtenerPorId($id) {
        $sql = "SELECT 
                    e.*,
                    t.nombre as tipo_nombre,
                    p.nombre as proveedor_nombre,
                    (SELECT s.nombre 
                     FROM mtto_equipos_movimientos m 
                     INNER JOIN sucursales s ON m.sucursal_destino_id = s.id 
                     WHERE m.equipo_id = e.id AND m.estado = 'finalizado' 
                     ORDER BY m.fecha_realizada DESC LIMIT 1) as ubicacion_actual,
                    (SELECT s.codigo 
                     FROM mtto_equipos_movimientos m 
                     INNER JOIN sucursales s ON m.sucursal_destino_id = s.id 
                     WHERE m.equipo_id = e.id AND m.estado = 'finalizado' 
                     ORDER BY m.fecha_realizada DESC LIMIT 1) as codigo_sucursal_actual
                FROM mtto_equipos e
                LEFT JOIN mtto_equipos_tipos t ON e.tipo_equipo_id = t.id
                LEFT JOIN proveedores_compras_servicios p ON e.proveedor_compra_id = p.id
                WHERE e.id = ?";
        
        return $this->db->fetchOne($sql, [$id]);
    }
    
    // Crear nuevo equipo
    public function crear($datos) {
        $sql = "INSERT INTO mtto_equipos (
                    codigo, tipo_equipo_id, marca, modelo, serial, 
                    caracteristicas, fecha_compra, proveedor_compra_id, 
                    garantia_meses, frecuencia_mantenimiento_meses, notas, registrado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $datos['codigo'],
            $datos['tipo_equipo_id'],
            $datos['marca'],
            $datos['modelo'],
            $datos['serial'],
            $datos['caracteristicas'],
            $datos['fecha_compra'],
            $datos['proveedor_compra_id'],
            $datos['garantia_meses'],
            $datos['frecuencia_mantenimiento_meses'],
            $datos['notas'],
            $datos['registrado_por']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Actualizar equipo
    public function actualizar($id, $datos) {
        $sql = "UPDATE mtto_equipos SET
                    codigo = ?, tipo_equipo_id = ?, marca = ?, modelo = ?, 
                    serial = ?, caracteristicas = ?, fecha_compra = ?, 
                    proveedor_compra_id = ?, garantia_meses = ?, 
                    frecuencia_mantenimiento_meses = ?, notas = ?
                WHERE id = ?";
        
        return $this->db->query($sql, [
            $datos['codigo'],
            $datos['tipo_equipo_id'],
            $datos['marca'],
            $datos['modelo'],
            $datos['serial'],
            $datos['caracteristicas'],
            $datos['fecha_compra'],
            $datos['proveedor_compra_id'],
            $datos['garantia_meses'],
            $datos['frecuencia_mantenimiento_meses'],
            $datos['notas'],
            $id
        ]);
    }
    
    // Obtener estadísticas de un equipo
    public function obtenerEstadisticas($equipoId) {
        // Total de mantenimientos
        $sqlTotal = "SELECT COUNT(*) as total FROM mtto_equipos_mantenimientos WHERE equipo_id = ?";
        $total = $this->db->fetchOne($sqlTotal, [$equipoId]);
        
        // Mantenimientos preventivos
        $sqlPreventivos = "SELECT COUNT(*) as total FROM mtto_equipos_mantenimientos 
                          WHERE equipo_id = ? AND tipo = 'preventivo'";
        $preventivos = $this->db->fetchOne($sqlPreventivos, [$equipoId]);
        
        // Mantenimientos correctivos
        $sqlCorrectivos = "SELECT COUNT(*) as total FROM mtto_equipos_mantenimientos 
                          WHERE equipo_id = ? AND tipo = 'correctivo'";
        $correctivos = $this->db->fetchOne($sqlCorrectivos, [$equipoId]);
        
        // Costo total en repuestos
        $sqlCosto = "SELECT IFNULL(SUM(costo_total_repuestos), 0) as total 
                    FROM mtto_equipos_mantenimientos WHERE equipo_id = ?";
        $costo = $this->db->fetchOne($sqlCosto, [$equipoId]);
        
        // Días fuera de servicio (aproximado por mantenimientos correctivos)
        $sqlDias = "SELECT IFNULL(SUM(DATEDIFF(fecha_finalizacion, fecha_inicio)), 0) as dias
                   FROM mtto_equipos_mantenimientos 
                   WHERE equipo_id = ? AND tipo = 'correctivo' AND fecha_finalizacion IS NOT NULL";
        $dias = $this->db->fetchOne($sqlDias, [$equipoId]);
        
        return [
            'total_mantenimientos' => $total['total'],
            'mantenimientos_preventivos' => $preventivos['total'],
            'mantenimientos_correctivos' => $correctivos['total'],
            'costo_total_repuestos' => $costo['total'],
            'dias_fuera_servicio' => $dias['dias']
        ];
    }
    
    // Obtener historial de mantenimientos
    public function obtenerHistorialMantenimientos($equipoId) {
        $sql = "SELECT 
                    m.*,
                    p.nombre as proveedor_nombre,
                    o.Nombre as operario_nombre,
                    o.Apellido as operario_apellido,
                    s.id as solicitud_relacionada_id
                FROM mtto_equipos_mantenimientos m
                LEFT JOIN proveedores_compras_servicios p ON m.proveedor_servicio_id = p.id
                LEFT JOIN Operarios o ON m.registrado_por = o.CodOperario
                LEFT JOIN mtto_equipos_solicitudes s ON m.solicitud_id = s.id
                WHERE m.equipo_id = ?
                ORDER BY m.fecha_inicio DESC";
        
        return $this->db->fetchAll($sql, [$equipoId]);
    }
    
    // Obtener historial de movimientos
    public function obtenerHistorialMovimientos($equipoId) {
        $sql = "SELECT 
                    m.*,
                    so.nombre as sucursal_origen,
                    sd.nombre as sucursal_destino,
                    op.Nombre as programado_nombre,
                    op.Apellido as programado_apellido,
                    of.Nombre as finalizado_nombre,
                    of.Apellido as finalizado_apellido
                FROM mtto_equipos_movimientos m
                INNER JOIN sucursales so ON m.sucursal_origen_id = so.id
                INNER JOIN sucursales sd ON m.sucursal_destino_id = sd.id
                LEFT JOIN Operarios op ON m.programado_por = op.CodOperario
                LEFT JOIN Operarios of ON m.finalizado_por = of.CodOperario
                WHERE m.equipo_id = ?
                ORDER BY m.fecha_programada DESC";
        
        return $this->db->fetchAll($sql, [$equipoId]);
    }
    
    // Obtener plan de mantenimiento del año vigente
    public function obtenerPlanMantenimientoAnual($equipoId) {
        $anio = date('Y');
        $sql = "SELECT * FROM mtto_equipos_mantenimientos_programados
                WHERE equipo_id = ? 
                AND YEAR(fecha_programada) = ?
                ORDER BY fecha_programada ASC";
        
        return $this->db->fetchAll($sql, [$equipoId, $anio]);
    }
}
?>