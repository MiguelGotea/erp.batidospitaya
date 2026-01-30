<?php
// Configuración de conexión a la base de datos
$servername = "localhost";
$username = "u839374897_erp";
$password = "ERpPitHay2025$";
$dbname = "u839374897_erp";

header('Content-Type: application/json');

try {
    $conn = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4", 
        $username, 
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    
    // Obtener la semana actual basada en la fecha de hoy
    $currentDate = date('Y-m-d');
    $currentWeekQuery = $conn->prepare("
        SELECT numero_semana 
        FROM FechasSistema 
        WHERE fecha = :current_date 
        LIMIT 1
    ");
    
    $currentWeekQuery->execute([':current_date' => $currentDate]);
    $currentWeekResult = $currentWeekQuery->fetch();
    
    if (!$currentWeekResult) {
        // Si no encuentra la fecha actual, usar la última fecha disponible
        $fallbackQuery = $conn->query("
            SELECT numero_semana 
            FROM FechasSistema 
            ORDER BY fecha DESC 
            LIMIT 1
        ");
        $currentWeekResult = $fallbackQuery->fetch();
    }
    
    if (!$currentWeekResult) {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo determinar la semana actual'
        ]);
        exit;
    }
    
    $currentWeek = $currentWeekResult['numero_semana'];
    $startWeek = $currentWeek - 7; // Últimas 8 semanas (incluyendo la actual)
    
    // Consulta para obtener tickets totales y tickets de urgencia 4 por semana
    $ticketsQuery = $conn->prepare("
        SELECT 
            fs.numero_semana as semana,
            COUNT(mt.id) as total_tickets,
            SUM(CASE WHEN mt.nivel_urgencia = 4 THEN 1 ELSE 0 END) as tickets_urgencia_4,
            CASE 
                WHEN COUNT(mt.id) > 0 THEN 
                    ROUND((SUM(CASE WHEN mt.nivel_urgencia = 4 THEN 1 ELSE 0 END) * 100.0 / COUNT(mt.id)), 2)
                ELSE 0 
            END as porcentaje_urgencia_4
        FROM 
            FechasSistema fs
        LEFT JOIN 
            mtto_tickets mt ON fs.fecha = DATE(mt.created_at)
        WHERE 
            fs.numero_semana BETWEEN :start_week AND :end_week
        GROUP BY 
            fs.numero_semana
        ORDER BY 
            fs.numero_semana ASC
    ");
    
    $ticketsQuery->execute([':start_week' => $startWeek, ':end_week' => $currentWeek]);
    $ticketsByWeek = $ticketsQuery->fetchAll();
    
    // Consulta para obtener tiempo promedio de atención de TODOS los tickets por semana
    $attentionTimeQuery = $conn->prepare("
        SELECT 
            fs.numero_semana as semana,
            AVG(TIMESTAMPDIFF(DAY, mt.created_at, mt.fecha_finalizacion)) as tiempo_promedio,
            COUNT(mt.id) as total_tickets_finalizados,
            SUM(CASE WHEN mt.nivel_urgencia = 4 THEN 1 ELSE 0 END) as tickets_urgencia_4
        FROM 
            FechasSistema fs
        JOIN 
            mtto_tickets mt ON fs.fecha = DATE(mt.created_at) 
        WHERE 
            fs.numero_semana BETWEEN :start_week AND :end_week
            AND mt.fecha_finalizacion IS NOT NULL
        GROUP BY 
            fs.numero_semana
        ORDER BY 
            fs.numero_semana ASC
    ");
    
    $attentionTimeQuery->execute([':start_week' => $startWeek, ':end_week' => $currentWeek]);
    $attentionTimeByWeek = $attentionTimeQuery->fetchAll();
    
    // Asegurarnos de que todas las semanas del rango tengan datos (incluso si son 0)
    $completeTicketsData = [];
    $completeAttentionData = [];
    
    for ($week = $startWeek; $week <= $currentWeek; $week++) {
        // Para tickets (totales y urgencia 4)
        $ticketFound = false;
        foreach ($ticketsByWeek as $ticket) {
            if ($ticket['semana'] == $week) {
                $completeTicketsData[] = $ticket;
                $ticketFound = true;
                break;
            }
        }
        if (!$ticketFound) {
            $completeTicketsData[] = [
                'semana' => $week, 
                'total_tickets' => 0,
                'tickets_urgencia_4' => 0,
                'porcentaje_urgencia_4' => 0
            ];
        }
        
        // Para tiempo de atención de todos los tickets
        $attentionFound = false;
        foreach ($attentionTimeByWeek as $attention) {
            if ($attention['semana'] == $week) {
                $completeAttentionData[] = [
                    'semana' => $attention['semana'],
                    'tiempo_promedio' => $attention['tiempo_promedio'] ? round($attention['tiempo_promedio'], 2) : 0,
                    'total_tickets' => $attention['total_tickets_finalizados'],
                    'tickets_urgencia_4' => $attention['tickets_urgencia_4']
                ];
                $attentionFound = true;
                break;
            }
        }
        if (!$attentionFound) {
            $completeAttentionData[] = [
                'semana' => $week,
                'tiempo_promedio' => 0,
                'total_tickets' => 0,
                'tickets_urgencia_4' => 0
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'currentWeek' => $currentWeek,
        'startWeek' => $startWeek,
        'ticketsByWeek' => $completeTicketsData,
        'attentionTimeByWeek' => $completeAttentionData,
        'weeksRange' => [
            'start' => $startWeek, 
            'end' => $currentWeek,
            'total_weeks' => count($completeTicketsData)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error en get_tickets_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener los datos de la base de datos: ' . $e->getMessage()
    ]);
}
?>