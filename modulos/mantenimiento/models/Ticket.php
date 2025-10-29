<?php
require_once __DIR__ . '/../config/database.php';

class Ticket {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    public function create($data) {
        // Generar código único para el ticket
        $codigo = $this->generateTicketCode();
        
        $sql = "INSERT INTO mtto_tickets (codigo, titulo, descripcion, tipo_formulario, cod_operario, cod_sucursal, area_equipo) 
                VALUES (:codigo, :titulo, :descripcion, :tipo_formulario, :cod_operario, :cod_sucursal, :area_equipo)";
        
        $params = [
            ':codigo' => $codigo,
            ':titulo' => $data['titulo'],
            ':descripcion' => $data['descripcion'],
            ':tipo_formulario' => $data['tipo_formulario'],
            ':cod_operario' => $data['cod_operario'],
            ':cod_sucursal' => $data['cod_sucursal'],
            ':area_equipo' => $data['area_equipo']
        ];
        
        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }
    
    // Nueva función para agregar fotos a un ticket
    public function addFotos($ticket_id, $fotos) {
        if (empty($fotos)) return;
        
        $sql = "INSERT INTO mtto_tickets_fotos (ticket_id, foto, orden) VALUES (?, ?, ?)";
        
        foreach ($fotos as $index => $foto) {
            $this->db->query($sql, [$ticket_id, $foto, $index]);
        }
    }
    
    // Nueva función para obtener fotos de un ticket
    public function getFotos($ticket_id) {
        $sql = "SELECT * FROM mtto_tickets_fotos WHERE ticket_id = ? ORDER BY orden ASC";
        return $this->db->fetchAll($sql, [$ticket_id]);
    }
    
    // Nueva función para eliminar una foto
    public function deleteFoto($foto_id) {
        $sql = "DELETE FROM mtto_tickets_fotos WHERE id = ?";
        $this->db->query($sql, [$foto_id]);
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT t.*, s.nombre as nombre_sucursal, o.Nombre as nombre_operario, tc.nombre as tipo_caso_nombre,
                (SELECT COUNT(*) FROM mtto_tickets_fotos WHERE ticket_id = t.id) as total_fotos,
                (SELECT foto FROM mtto_tickets_fotos WHERE ticket_id = t.id ORDER BY orden ASC LIMIT 1) as primera_foto
                FROM mtto_tickets t 
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo 
                LEFT JOIN Operarios o ON t.cod_operario = o.CodOperario 
                LEFT JOIN mtto_tipos_casos tc ON t.tipo_caso_id = tc.id 
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['cod_sucursal'])) {
            $sql .= " AND t.cod_sucursal = :cod_sucursal";
            $params[':cod_sucursal'] = $filters['cod_sucursal'];
        }
        
        if (!empty($filters['cod_operario'])) {
            $sql .= " AND t.cod_operario = :cod_operario";
            $params[':cod_operario'] = $filters['cod_operario'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    public function getById($id) {
        $sql = "SELECT t.*, s.nombre as nombre_sucursal, o.Nombre as nombre_operario, tc.nombre as tipo_caso_nombre 
                FROM mtto_tickets t 
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo 
                LEFT JOIN Operarios o ON t.cod_operario = o.CodOperario 
                LEFT JOIN mtto_tipos_casos tc ON t.tipo_caso_id = tc.id 
                WHERE t.id = :id";
        
        return $this->db->fetchOne($sql, [':id' => $id]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }
        
        if (!empty($fields)) {
            $sql = "UPDATE mtto_tickets SET " . implode(', ', $fields) . " WHERE id = :id";
            $this->db->query($sql, $params);
        }
    }
    
    public function updateBulkDates($ticket_ids, $fecha_inicio, $fecha_final) {
        $placeholders = str_repeat('?,', count($ticket_ids) - 1) . '?';
        $sql = "UPDATE mtto_tickets SET fecha_inicio = ?, fecha_final = ?, status = 'agendado' 
                WHERE id IN ({$placeholders})";
        
        $params = array_merge([$fecha_inicio, $fecha_final], $ticket_ids);
        $this->db->query($sql, $params);
    }
    
    public function getTicketsForCalendar() {
        $sql = "SELECT t.*, s.nombre as nombre_sucursal 
                FROM mtto_tickets t 
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo 
                WHERE t.fecha_inicio IS NOT NULL AND t.fecha_final IS NOT NULL 
                ORDER BY t.fecha_inicio";
        
        return $this->db->fetchAll($sql);
    }
    
    public function getTicketsForCalendarBySucursales($sucursales) {
        if (empty($sucursales)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($sucursales) - 1) . '?';
        
        $sql = "SELECT t.*, s.nombre as nombre_sucursal 
                FROM mtto_tickets t 
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo 
                WHERE t.fecha_inicio IS NOT NULL 
                AND t.fecha_final IS NOT NULL 
                AND t.cod_sucursal IN ({$placeholders})
                ORDER BY t.fecha_inicio";
        
        return $this->db->fetchAll($sql, $sucursales);
    }

    public function getTicketsWithoutDates() {
        $sql = "SELECT t.*, s.nombre as nombre_sucursal
                FROM mtto_tickets t 
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo 
                WHERE (t.fecha_inicio IS NULL OR t.fecha_final IS NULL)
                AND t.status != 'finalizado'
                ORDER BY 
                    CASE 
                        WHEN t.tipo_formulario = 'cambio_equipos' THEN 1
                        WHEN t.tipo_formulario = 'mantenimiento_general' THEN 2
                        ELSE 3
                    END,
                    COALESCE(t.nivel_urgencia, 0) DESC, t.created_at";
        
        return $this->db->fetchAll($sql);
    }
    
    private function generateTicketCode() {
        $year = date('Y');
        $month = date('m');
        
        $sql = "SELECT codigo FROM mtto_tickets 
                WHERE codigo LIKE :pattern 
                ORDER BY codigo DESC LIMIT 1";
        
        $pattern = "TKT{$year}{$month}%";
        $result = $this->db->fetchOne($sql, [':pattern' => $pattern]);
        
        if ($result) {
            $lastNumber = intval(substr($result['codigo'], -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return "TKT{$year}{$month}" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
    
    public function getSucursales() {
        return $this->db->fetchAll("SELECT codigo as cod_sucursal, nombre as nombre_sucursal FROM sucursales ORDER BY nombre");
    }
    
    public function getEquipos() {
        return $this->db->fetchAll("SELECT * FROM mtto_equipos WHERE activo = 1 ORDER BY nombre");
    }
    
    public function getTiposCasos() {
        return $this->db->fetchAll("SELECT * FROM mtto_tipos_casos WHERE activo = 1 ORDER BY nombre");
    }
}
?>