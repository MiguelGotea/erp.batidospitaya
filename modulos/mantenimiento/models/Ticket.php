<?php
//Ticket.php
require_once __DIR__ . '/../config/database.php';

class Ticket
{
    private $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    public function getDb()
    {
        return $this->db;
    }

    public function create($data)
    {
        // Generar código único para el ticket
        $codigo = $this->generateTicketCode();

        // Campos opcionales de análisis IA
        $tieneIA = !empty($data['nivel_urgencia']) || !empty($data['resolucion']);

        if ($tieneIA) {
            $sql = "INSERT INTO mtto_tickets (codigo, titulo, descripcion, tipo_formulario, cod_operario, cod_sucursal, area_equipo, nivel_urgencia, tiempo_estimado, resolucion) 
                    VALUES (:codigo, :titulo, :descripcion, :tipo_formulario, :cod_operario, :cod_sucursal, :area_equipo, :nivel_urgencia, :tiempo_estimado, :resolucion)";

            $params = [
                ':codigo' => $codigo,
                ':titulo' => $data['titulo'],
                ':descripcion' => $data['descripcion'],
                ':tipo_formulario' => $data['tipo_formulario'],
                ':cod_operario' => $data['cod_operario'],
                ':cod_sucursal' => $data['cod_sucursal'],
                ':area_equipo' => $data['area_equipo'],
                ':nivel_urgencia' => (int) ($data['nivel_urgencia'] ?? 0),
                ':tiempo_estimado' => (int) ($data['tiempo_estimado'] ?? 0),
                ':resolucion' => $data['resolucion'] ?? ''
            ];
        } else {
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
        }

        $this->db->query($sql, $params);
        return $this->db->lastInsertId();
    }

    // Nueva función para agregar fotos a un ticket
    public function addFotos($ticket_id, $fotos)
    {
        if (empty($fotos))
            return;

        $sql = "INSERT INTO mtto_tickets_fotos (ticket_id, foto, orden) VALUES (?, ?, ?)";

        foreach ($fotos as $index => $foto) {
            $this->db->query($sql, [$ticket_id, $foto, $index]);
        }
    }

    // Nueva función para obtener fotos de un ticket
    public function getFotos($ticket_id)
    {
        $sql = "SELECT * FROM mtto_tickets_fotos WHERE ticket_id = ? ORDER BY orden ASC";
        return $this->db->fetchAll($sql, [$ticket_id]);
    }

    // Nueva función para eliminar una foto
    public function deleteFoto($foto_id)
    {
        $sql = "DELETE FROM mtto_tickets_fotos WHERE id = ?";
        $this->db->query($sql, [$foto_id]);
    }

    public function getAll($filters = [])
    {
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

    public function getById($id)
    {
        $sql = "SELECT t.*, s.nombre as nombre_sucursal, o.Nombre as nombre_operario, tc.nombre as tipo_caso_nombre 
                FROM mtto_tickets t 
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo 
                LEFT JOIN Operarios o ON t.cod_operario = o.CodOperario 
                LEFT JOIN mtto_tipos_casos tc ON t.tipo_caso_id = tc.id 
                WHERE t.id = :id";

        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    public function update($id, $data)
    {
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

    public function updateBulkDates($ticket_ids, $fecha_inicio, $fecha_final)
    {
        $placeholders = str_repeat('?,', count($ticket_ids) - 1) . '?';
        $sql = "UPDATE mtto_tickets SET fecha_inicio = ?, fecha_final = ?, status = 'agendado' 
                WHERE id IN ({$placeholders})";

        $params = array_merge([$fecha_inicio, $fecha_final], $ticket_ids);
        $this->db->query($sql, $params);
    }

    public function getTicketsForCalendar()
    {
        $sql = "SELECT t.*, s.nombre as nombre_sucursal 
                FROM mtto_tickets t 
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo 
                WHERE t.fecha_inicio IS NOT NULL AND t.fecha_final IS NOT NULL 
                ORDER BY t.fecha_inicio";

        return $this->db->fetchAll($sql);
    }

    public function getTicketsForCalendarBySucursales($sucursales)
    {
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

    public function getTicketsForPlanning()
    {
        $sql = "SELECT t.*, s.nombre as nombre_sucursal, s.departamento as departamento_sucursal,
                s.Latitude, s.Longitude,
                (SELECT foto FROM mtto_tickets_fotos WHERE ticket_id = t.id ORDER BY orden ASC LIMIT 1) as primera_foto,
                FLOOR(DATEDIFF(NOW(), t.created_at) / 7) as semanas_antiguedad,
                COALESCE(t.nivel_urgencia, 0) as urgencia,
                COALESCE(t.tiempo_estimado, 0) as tiempo_exec
                FROM mtto_tickets t 
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo 
                WHERE t.status IN ('solicitado', 'agendado')
                AND t.tipo_formulario = 'mantenimiento_general'";

        return $this->db->fetchAll($sql);
    }

    public function getTicketsWithoutDates()
    {
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

    private function generateTicketCode()
    {
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

    public function getSucursales()
    {
        return $this->db->fetchAll("SELECT codigo as cod_sucursal, nombre as nombre_sucursal FROM sucursales WHERE activa = 1 ORDER BY nombre");
    }

    public function getEquipos()
    {
        return $this->db->fetchAll("SELECT * FROM mtto_equipos WHERE activo = 1 ORDER BY tipo_equipo_id");
    }

    public function getTiposCasos()
    {
        return $this->db->fetchAll("SELECT * FROM mtto_tipos_casos WHERE activo = 1 ORDER BY nombre");
    }

    // Nuevas funciones para colaboradores
    public function asignarColaborador($ticket_id, $cod_operario, $asignado_por = null)
    {
        $sql = "INSERT INTO mtto_tickets_colaboradores (ticket_id, cod_operario, asignado_por) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE fecha_asignacion = CURRENT_TIMESTAMP";
        $this->db->query($sql, [$ticket_id, $cod_operario, $asignado_por]);
    }

    public function removerColaborador($ticket_id, $cod_operario)
    {
        $sql = "DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ? AND cod_operario = ?";
        $this->db->query($sql, [$ticket_id, $cod_operario]);
    }

    public function getColaboradores($ticket_id)
    {
        $sql = "SELECT tc.*, o.Nombre, o.Apellido 
                FROM mtto_tickets_colaboradores tc
                LEFT JOIN Operarios o ON tc.cod_operario = o.CodOperario
                WHERE tc.ticket_id = ?
                ORDER BY tc.fecha_asignacion ASC";
        return $this->db->fetchAll($sql, [$ticket_id]);
    }

    public function getColaboradoresDisponibles()
    {
        $sql = "SELECT CodOperario, Nombre, Apellido 
                FROM Operarios 
                ORDER BY Nombre, Apellido";
        return $this->db->fetchAll($sql);
    }

    public function getColaboradorInfo($cod_operario)
    {
        $sql = "SELECT CodOperario, Nombre, Apellido 
                FROM Operarios 
                WHERE CodOperario = ?";
        return $this->db->fetchOne($sql, [$cod_operario]);
    }

    public function getTicketsPorColaborador($cod_operario, $fecha_inicio = null)
    {
        $sql = "SELECT t.*, s.nombre as nombre_sucursal, tc.fecha_asignacion,
                (SELECT COUNT(*) FROM mtto_tickets_fotos WHERE ticket_id = t.id) as total_fotos
                FROM mtto_tickets t
                INNER JOIN mtto_tickets_colaboradores tc ON t.id = tc.ticket_id
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
                WHERE tc.cod_operario = ?
                AND t.fecha_inicio IS NOT NULL";

        $params = [$cod_operario];

        if ($fecha_inicio) {
            $sql .= " AND t.fecha_inicio >= ?";
            $params[] = $fecha_inicio;
        }

        $sql .= " ORDER BY 
                CASE WHEN t.status = 'finalizado' THEN 1 ELSE 0 END,
                t.fecha_inicio ASC, t.fecha_final ASC, nombre_sucursal ASC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getColaboradoresAsignados()
    {
        $sql = "SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido 
                FROM Operarios o
                INNER JOIN mtto_tickets_colaboradores tc ON o.CodOperario = tc.cod_operario
                INNER JOIN mtto_tickets t ON tc.ticket_id = t.id
                WHERE t.fecha_inicio IS NOT NULL
                AND o.Operativo = 1
                ORDER BY o.Nombre, o.Apellido";
        return $this->db->fetchAll($sql);
    }

    public function finalizarTicket($ticket_id, $detalle_trabajo, $materiales_usados, $finalizado_por)
    {
        $sql = "UPDATE mtto_tickets 
                SET status = 'finalizado', 
                    detalle_trabajo = ?,
                    materiales_usados = ?,
                    fecha_finalizacion = CURRENT_TIMESTAMP,
                    finalizado_por = ?
                WHERE id = ?";
        $this->db->query($sql, [$detalle_trabajo, $materiales_usados, $finalizado_por, $ticket_id]);
    }

    public function addFotosFinalizacion($ticket_id, $fotos)
    {
        if (empty($fotos))
            return;

        $sql = "INSERT INTO mtto_tickets_fotos_finalizacion (ticket_id, foto, orden) VALUES (?, ?, ?)";

        foreach ($fotos as $index => $foto) {
            $this->db->query($sql, [$ticket_id, $foto, $index]);
        }
    }

    public function getFotosFinalizacion($ticket_id)
    {
        $sql = "SELECT * FROM mtto_tickets_fotos_finalizacion WHERE ticket_id = ? ORDER BY orden ASC";
        return $this->db->fetchAll($sql, [$ticket_id]);
    }

    public function getWeeklyReportStats()
    {
        $year = date('Y');
        $sql = "SELECT s.numero_semana, s.fecha_inicio, s.fecha_fin, 
                COUNT(t.id) as total_tickets,
                SUM(CASE WHEN t.nivel_urgencia = 4 THEN 1 ELSE 0 END) as tickets_criticos,
                SUM(CASE WHEN t.nivel_urgencia != 4 OR t.nivel_urgencia IS NULL THEN 1 ELSE 0 END) as tickets_normales
                FROM SemanasSistema s
                LEFT JOIN mtto_tickets t ON t.created_at BETWEEN CONCAT(s.fecha_inicio, ' 00:00:00') AND CONCAT(s.fecha_fin, ' 23:59:59')
                WHERE s.anio = ?
                AND s.fecha_inicio <= CURDATE() 
                GROUP BY s.id
                ORDER BY s.numero_semana DESC
                LIMIT 12";

        return $this->db->fetchAll($sql, [$year]);
    }

    public function getEquipmentChangeStats()
    {
        $year = date('Y');
        $sql = "SELECT s.numero_semana, s.fecha_inicio, s.fecha_fin, 
                COUNT(t.id) as total_cambios
                FROM SemanasSistema s
                LEFT JOIN mtto_tickets t ON t.created_at BETWEEN CONCAT(s.fecha_inicio, ' 00:00:00') AND CONCAT(s.fecha_fin, ' 23:59:59')
                    AND t.tipo_formulario = 'cambio_equipos'
                WHERE s.anio = ?
                AND s.fecha_inicio <= CURDATE() 
                GROUP BY s.id
                ORDER BY s.numero_semana DESC
                LIMIT 8";

        return $this->db->fetchAll($sql, [$year]);
    }

    public function getResponseTimeStats()
    {
        $year = date('Y');
        $sql = "SELECT s.numero_semana, s.fecha_inicio, s.fecha_fin, 
                AVG(DATEDIFF(COALESCE(t.fecha_final, CURDATE()), DATE(t.created_at))) as promedio_dias,
                GROUP_CONCAT(CONCAT(t.titulo, ' ', t.cod_sucursal) SEPARATOR '||') as tickets_info
                FROM SemanasSistema s
                LEFT JOIN mtto_tickets t ON COALESCE(t.fecha_final, CURDATE()) BETWEEN s.fecha_inicio AND s.fecha_fin
                    AND t.tipo_formulario = 'mantenimiento_general'
                    AND t.nivel_urgencia = 4
                WHERE s.anio = ?
                AND s.fecha_inicio <= CURDATE() 
                GROUP BY s.id
                ORDER BY s.numero_semana DESC
                LIMIT 8";

        return $this->db->fetchAll($sql, [$year]);
    }

    // --- NUEVAS FUNCIONES PARA INFORMES DIARIOS v4 ---

    /**
     * Obtener el informe diario actual de un colaborador para una fecha específica
     */
    public function getInformeDiarioPorFecha($cod_operario, $fecha)
    {
        $sql = "SELECT * FROM mtto_informes_diarios 
                WHERE cod_operario = ? AND fecha = ?";
        return $this->db->fetchOne($sql, [$cod_operario, $fecha]);
    }

    /**
     * Crear un nuevo informe diario
     */
    public function crearInformeDiario($data)
    {
        $sql = "INSERT INTO mtto_informes_diarios (cod_operario, fecha, km_inicial, km_foto_inicial, estado) 
                VALUES (?, ?, ?, ?, 'creado')";
        $this->db->query($sql, [
            $data['cod_operario'],
            $data['fecha'],
            $data['km_inicial'],
            $data['km_foto_inicial']
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * Finalizar un informe diario (bloquea ediciones)
     */
    public function finalizarInformeDiario($informe_id, $data)
    {
        // 1. Marcar el informe como finalizado (km opcional)
        $km_final = $data['km_final'] ?? null;
        $km_foto_final = $data['km_foto_final'] ?? null;

        $sql = "UPDATE mtto_informes_diarios SET 
                km_final = COALESCE(?, km_final), 
                km_foto_final = COALESCE(?, km_foto_final), 
                estado = 'finalizado',
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        $this->db->query($sql, [
            $km_final,
            $km_foto_final,
            $informe_id
        ]);

        // 2. Obtener datos del informe (fecha y operario) para propagar a los tickets
        $informe = $this->db->fetchOne(
            "SELECT fecha, cod_operario FROM mtto_informes_diarios WHERE id = ?",
            [$informe_id]
        );
        if (!$informe)
            return;

        $fechaInforme = $informe['fecha'];       // formato Y-m-d
        $codOperario = $informe['cod_operario'];
        $ahora = date('Y-m-d H:i:s');     // Nicaragua (America/Managua) por conexion.php

        // 3. Obtener todas las tareas del informe (a través de sus visitas)
        $tareas = $this->db->fetchAll(
            "SELECT it.ticket_id, it.completado_100
             FROM mtto_informe_tareas it
             INNER JOIN mtto_informe_visitas iv ON it.visita_id = iv.id
             WHERE iv.informe_id = ?",
            [$informe_id]
        );

        foreach ($tareas as $tarea) {
            $ticketId = $tarea['ticket_id'];
            $completado = (int) $tarea['completado_100'];


            // Leer fecha_inicio actual para no sobreescribir si ya existe
            $ticketActual = $this->db->fetchOne(
                "SELECT fecha_inicio FROM mtto_tickets WHERE id = ?",
                [$ticketId]
            );
            if (!$ticketActual)
                continue;

            $fechaInicioActual = $ticketActual['fecha_inicio'];
            // Si ya tiene fecha_inicio registrada no se modifica; si es NULL se asigna la del informe
            $nuevaFechaInicio = $fechaInicioActual ?: $fechaInforme;

            if ($completado === 1) {
                // Tarea 100% completada → finalizar ticket
                $this->db->query(
                    "UPDATE mtto_tickets SET
                        fecha_inicio       = ?,
                        fecha_final        = ?,
                        status             = 'finalizado',
                        updated_at         = ?,
                        fecha_finalizacion = ?,
                        finalizado_por     = ?
                     WHERE id = ?",
                    [$nuevaFechaInicio, $fechaInforme, $ahora, $fechaInforme, $codOperario, $ticketId]
                );
            } else {
                // Avance parcial / pendiente → solo actualizar fecha_inicio (si era NULL) y updated_at
                $this->db->query(
                    "UPDATE mtto_tickets SET
                        fecha_inicio = ?,
                        updated_at   = ?
                     WHERE id = ?",
                    [$nuevaFechaInicio, $ahora, $ticketId]
                );
            }
        }
    }

    /**
     * Agregar una visita a sucursal al informe
     */
    public function agregarVisita($informe_id, $data)
    {
        $sql = "INSERT INTO mtto_informe_visitas (informe_id, cod_sucursal, hora_llegada, hora_salida, materiales_stock) 
                VALUES (?, ?, ?, ?, ?)";
        $this->db->query($sql, [
            $informe_id,
            $data['cod_sucursal'],
            $data['hora_llegada'],
            $data['hora_salida'] ?? null,
            $data['materiales_stock'] ?? ''
        ]);
        return $this->db->lastInsertId();
    }

    /**
     * Agregar una compra/factura a una visita
     */
    public function agregarCompra($visita_id, $data)
    {
        $sql = "INSERT INTO mtto_informe_compras (visita_id, foto_factura, monto, detalle) 
                VALUES (?, ?, ?, ?)";
        return $this->db->query($sql, [
            $visita_id,
            $data['foto_factura'],
            $data['monto'],
            $data['detalle']
        ]);
    }

    /**
     * Registrar una tarea realizada en una visita
     */
    public function registrarTareaInforme($visita_id, $data)
    {
        $sql = "INSERT INTO mtto_informe_tareas (visita_id, ticket_id, completado_100, trabajo_realizado) 
                VALUES (?, ?, ?, ?)";
        $this->db->query($sql, [
            $visita_id,
            $data['ticket_id'],
            $data['completado_100'],
            $data['trabajo_realizado']
        ]);
        return $this->db->lastInsertId();
        // NOTA: mtto_tickets NO se actualiza aquí.
        // La actualización ocurre únicamente al finalizar el informe (finalizarInformeDiario).
    }

    /**
     * Agregar fotos de evidencia a una tarea de informe
     */
    public function agregarFotosTareaInforme($tarea_id, $fotos)
    {
        $sql = "INSERT INTO mtto_informe_tareas_fotos (tarea_id, foto, orden) VALUES (?, ?, ?)";
        foreach ($fotos as $idx => $foto) {
            $this->db->query($sql, [$tarea_id, $foto, $idx]);
        }
    }

    /**
     * Obtener el detalle completo de un informe diario para impresión/visualización
     */
    public function getDetalleInformeCompleto($informe_id)
    {
        // Info básica
        $sql = "SELECT i.*, o.Nombre, o.Apellido 
                FROM mtto_informes_diarios i
                LEFT JOIN Operarios o ON i.cod_operario = o.CodOperario
                WHERE i.id = ?";
        $informe = $this->db->fetchOne($sql, [$informe_id]);

        if (!$informe)
            return null;

        // Visitas
        $sqlV = "SELECT v.*, s.nombre as nombre_sucursal, s.departamento as departamento_sucursal, 
                        (SELECT COUNT(*) FROM mtto_informe_compras WHERE visita_id = v.id) as total_compras
                 FROM mtto_informe_visitas v
                 LEFT JOIN sucursales s ON v.cod_sucursal = s.codigo
                 WHERE v.informe_id = ?
                 ORDER BY v.created_at ASC";
        $visitas = $this->db->fetchAll($sqlV, [$informe_id]);

        foreach ($visitas as &$visita) {
            // Compras por visita
            $sqlC = "SELECT * FROM mtto_informe_compras WHERE visita_id = ?";
            $visita['compras'] = $this->db->fetchAll($sqlC, [$visita['id']]);

            // Tareas por visita
            $sqlT = "SELECT it.*, t.codigo, t.titulo 
                     FROM mtto_informe_tareas it
                     INNER JOIN mtto_tickets t ON it.ticket_id = t.id
                     WHERE it.visita_id = ?";
            $tareas = $this->db->fetchAll($sqlT, [$visita['id']]);

            foreach ($tareas as &$tarea) {
                $sqlF = "SELECT * FROM mtto_informe_tareas_fotos WHERE tarea_id = ? ORDER BY orden ASC";
                $tarea['fotos'] = $this->db->fetchAll($sqlF, [$tarea['id']]);
            }
            $visita['tareas'] = $tareas;
        }

        $informe['visitas'] = $visitas;
        return $informe;
    }

    /**
     * Actualizar datos de caja chica (Admin)
     */
    public function actualizarCajaChica($informe_id, $monto, $foto)
    {
        $sql = "UPDATE mtto_informes_diarios SET monto_caja_chica = ?, foto_caja_chica = ? WHERE id = ?";
        return $this->db->query($sql, [$monto, $foto, $informe_id]);
    }

    /**
     * Obtener tickets pendientes por sucursal
     */
    public function getTicketsPorSucursal($cod_sucursal)
    {
        $sql = "SELECT t.*, s.nombre as nombre_sucursal 
                FROM mtto_tickets t
                LEFT JOIN sucursales s ON t.cod_sucursal = s.codigo
                WHERE t.cod_sucursal = ? AND t.status IN ('solicitado', 'agendado')
                ORDER BY t.created_at DESC";
        return $this->db->fetchAll($sql, [$cod_sucursal]);
    }

    /**
     * Obtener historial de informes para el dashboard
     */
    public function getHistorialInformes($filters = [])
    {
        $sql = "SELECT i.*, o.Nombre, o.Apellido 
                FROM mtto_informes_diarios i
                LEFT JOIN Operarios o ON i.cod_operario = o.CodOperario
                WHERE 1=1";
        $params = [];

        if (!empty($filters['cod_operario'])) {
            $sql .= " AND i.cod_operario = :cod_operario";
            $params[':cod_operario'] = $filters['cod_operario'];
        }

        if (!empty($filters['fecha_desde'])) {
            $sql .= " AND i.fecha >= :fecha_desde";
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }

        $sql .= " ORDER BY i.fecha DESC, i.created_at DESC LIMIT 100";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Actualizar hora de llegada de una visita (inline)
     */
    public function actualizarHoraLlegada($visita_id, $hora_llegada)
    {
        $sql = "UPDATE mtto_informe_visitas SET hora_llegada = ? WHERE id = ?";
        return $this->db->query($sql, [$hora_llegada, $visita_id]);
    }

    /**
     * Actualizar hora de salida de una visita
     */
    public function actualizarHoraSalida($visita_id, $hora_salida)
    {
        $sql = "UPDATE mtto_informe_visitas SET hora_salida = ? WHERE id = ?";
        return $this->db->query($sql, [$hora_salida, $visita_id]);
    }

    /**
     * Actualizar materiales usados de una visita
     */
    public function actualizarMateriales($visita_id, $materiales)
    {
        $sql = "UPDATE mtto_informe_visitas SET materiales_stock = ? WHERE id = ?";
        return $this->db->query($sql, [$materiales, $visita_id]);
    }

    /**
     * Eliminar una tarea de un informe y sus fotos asociadas (físicas y BD)
     */
    public function eliminarTareaInforme($tarea_id)
    {
        // 1. Obtener fotos para borrar archivos físicos
        $sqlFotos = "SELECT foto FROM mtto_informe_tareas_fotos WHERE tarea_id = ?";
        $fotos = $this->db->fetchAll($sqlFotos, [$tarea_id]);

        foreach ($fotos as $f) {
            $path = __DIR__ . '/../uploads/evidencias/' . $f['foto'];
            if (file_exists($path)) {
                unlink($path);
            }
        }

        // 2. Borrar de la BD (cascada manual)
        $this->db->query("DELETE FROM mtto_informe_tareas_fotos WHERE tarea_id = ?", [$tarea_id]);
        return $this->db->query("DELETE FROM mtto_informe_tareas WHERE id = ?", [$tarea_id]);
    }

    /**
     * Eliminar una compra de un informe y su foto de factura
     */
    public function eliminarCompraInforme($compra_id)
    {
        // 1. Obtener foto
        $sql = "SELECT foto_factura FROM mtto_informe_compras WHERE id = ?";
        $compra = $this->db->fetchOne($sql, [$compra_id]);

        if ($compra && $compra['foto_factura']) {
            $path = __DIR__ . '/../uploads/compras/' . $compra['foto_factura'];
            if (file_exists($path)) {
                unlink($path);
            }
        }

        return $this->db->query("DELETE FROM mtto_informe_compras WHERE id = ?", [$compra_id]);
    }

    /**
     * Eliminar una visita y todo su contenido relacionado (cascada completa)
     */
    public function eliminarVisitaInforme($visita_id)
    {
        // 1. Eliminar Tareas (con sus fotos)
        $sqlT = "SELECT id FROM mtto_informe_tareas WHERE visita_id = ?";
        $tareas = $this->db->fetchAll($sqlT, [$visita_id]);
        foreach ($tareas as $t) {
            $this->eliminarTareaInforme($t['id']);
        }

        // 2. Eliminar Compras (con sus facturas)
        $sqlC = "SELECT id FROM mtto_informe_compras WHERE visita_id = ?";
        $compras = $this->db->fetchAll($sqlC, [$visita_id]);
        foreach ($compras as $c) {
            $this->eliminarCompraInforme($c['id']);
        }

        // 3. Eliminar la visita
        return $this->db->query("DELETE FROM mtto_informe_visitas WHERE id = ?", [$visita_id]);
    }

    /**
     * Vincular un reembolso a una visita de informe
     */
    public function vincularReembolsoAVisita($visita_id, $reembolso_id)
    {
        $sql = "UPDATE mtto_informe_visitas SET reembolso_id = ? WHERE id = ?";
        return $this->db->query($sql, [$reembolso_id, $visita_id]);
    }

    /**
     * Registrar el kilometraje inicial en un informe ya creado
     */
    public function actualizarKmInicial($informe_id, $km_inicial, $foto_nombre = null)
    {
        if ($foto_nombre) {
            $sql = "UPDATE mtto_informes_diarios SET km_inicial = ?, km_foto_inicial = ? WHERE id = ?";
            return $this->db->query($sql, [$km_inicial, $foto_nombre, $informe_id]);
        } else {
            $sql = "UPDATE mtto_informes_diarios SET km_inicial = ? WHERE id = ?";
            return $this->db->query($sql, [$km_inicial, $informe_id]);
        }
    }
}
?>