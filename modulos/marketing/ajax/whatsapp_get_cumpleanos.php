<?php
/**
 * AJAX: Obtener lista de cumpleañeros
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');

try {
    $periodo = $_GET['periodo'] ?? 'hoy';
    $sucursal = $_GET['sucursal'] ?? '';
    $estado = $_GET['estado'] ?? '';

    // Construir consulta base
    $sql = "
        SELECT 
            cc.id_clienteclub,
            cc.nombre,
            cc.apellido,
            cc.celular,
            DATE_FORMAT(cc.fecha_nacimiento, '%d/%m/%Y') as fecha_nacimiento,
            cc.nombre_sucursal,
            cc.puntos_iniciales,
            TIMESTAMPDIFF(YEAR, cc.fecha_nacimiento, CURDATE()) as edad,
            (SELECT COUNT(*) FROM whatsapp_mensajes wm 
             WHERE wm.cliente_id = cc.id_clienteclub 
             AND DATE(wm.fecha_creacion) = CURDATE()
             AND wm.estado IN ('enviado', 'entregado', 'en_cola', 'pendiente')) as estado_envio
        FROM clientesclub cc
        WHERE cc.celular IS NOT NULL 
          AND cc.celular != ''
          AND LENGTH(REPLACE(REPLACE(cc.celular, ' ', ''), '-', '')) >= 8
    ";

    $params = [];

    // Filtro por período
    switch ($periodo) {
        case 'hoy':
            $sql .= " AND DAY(cc.fecha_nacimiento) = DAY(CURDATE()) AND MONTH(cc.fecha_nacimiento) = MONTH(CURDATE())";
            break;
        case 'semana':
            $sql .= " AND MONTH(cc.fecha_nacimiento) = MONTH(CURDATE()) 
                      AND DAY(cc.fecha_nacimiento) BETWEEN DAY(CURDATE()) AND DAY(CURDATE()) + 7";
            break;
        case 'mes':
            $sql .= " AND MONTH(cc.fecha_nacimiento) = MONTH(CURDATE())";
            break;
    }

    // Filtro por sucursal
    if (!empty($sucursal)) {
        $sql .= " AND cc.nombre_sucursal = ?";
        $params[] = $sucursal;
    }

    // Ordenar por día del mes
    $sql .= " ORDER BY DAY(cc.fecha_nacimiento), cc.nombre";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $cumpleanos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filtrar por estado de envío si aplica
    if (!empty($estado)) {
        $cumpleanos = array_filter($cumpleanos, function ($c) use ($estado) {
            if ($estado === 'pendiente') {
                return $c['estado_envio'] == 0;
            } else {
                return $c['estado_envio'] > 0;
            }
        });
        $cumpleanos = array_values($cumpleanos);
    }

    echo json_encode([
        'success' => true,
        'cumpleanos' => $cumpleanos,
        'total' => count($cumpleanos)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}