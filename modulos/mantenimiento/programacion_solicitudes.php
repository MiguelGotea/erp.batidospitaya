<?php
// programacion_solicitudes.php
require_once 'config/database.php';
require_once 'models/Ticket.php';

$ticket = new Ticket();

// Obtener semana actual (518 por defecto)
$semana_actual = isset($_GET['semana']) ? intval($_GET['semana']) : 518;

// Obtener fechas de la semana desde FechasSistema
$sql_fechas = "SELECT CAST(fecha AS DATE) as fecha, numero_semana 
               FROM FechasSistema 
               WHERE numero_semana = ? 
               AND DAYOFWEEK(fecha) BETWEEN 2 AND 7
               ORDER BY fecha";
$fechas_semana = $db->fetchAll($sql_fechas, [$semana_actual]);

if (empty($fechas_semana)) {
    die("No se encontraron fechas para la semana $semana_actual");
}


// Extraer solo las fechas
$fechas = array_column($fechas_semana, 'fecha');
$fecha_inicio_semana = $fechas[0];
$fecha_fin_semana = end($fechas);

// Función para obtener color de urgencia
function getColorUrgencia($nivel) {
    switch($nivel) {
        case 1: return '#28a745';
        case 2: return '#ffc107';
        case 3: return '#fd7e14';
        case 4: return '#dc3545';
        default: return '#8b8b8bff';
    }
}

// Obtener equipos de trabajo únicos históricos
$sql_equipos = "
    SELECT DISTINCT tipo_usuario
    FROM mtto_tickets_colaboradores
    WHERE tipo_usuario IS NOT NULL
    ORDER BY tipo_usuario
";
$tipos_disponibles = $db->fetchAll($sql_equipos);

// Construir equipos de trabajo (combinaciones únicas)
$sql_combinaciones = "
    SELECT ticket_id, GROUP_CONCAT(DISTINCT tipo_usuario ORDER BY tipo_usuario SEPARATOR ' + ') as equipo
    FROM mtto_tickets_colaboradores
    WHERE tipo_usuario IS NOT NULL
    GROUP BY ticket_id
";
$combinaciones = $db->fetchAll($sql_combinaciones);

$equipos_trabajo = ['Cambio de Equipos']; // Siempre incluir este grupo
$equipos_unicos = [];

foreach ($combinaciones as $comb) {
    if (!empty($comb['equipo']) && !in_array($comb['equipo'], $equipos_unicos)) {
        $equipos_unicos[] = $comb['equipo'];
    }
}



// Agrupar tickets por equipo de trabajo
$tickets_por_equipo = [];
foreach ($equipos_trabajo as $equipo) {
    $tickets_por_equipo[$equipo] = [];
}

foreach ($tickets_programados as $ticket) {
    // Determinar equipo
    if ($ticket['tipo_formulario'] === 'cambio_equipos') {
        $equipo_key = 'Cambio de Equipos';
    } else {
        // Normalizar equipo
        $tipos = !empty($ticket['equipo_trabajo']) ? explode(' + ', $ticket['equipo_trabajo']) : [];
        $tipos_unicos = array_unique($tipos);
        sort($tipos_unicos);
        $equipo_key = implode(' + ', $tipos_unicos);
        
        if (empty($equipo_key)) {
            $equipo_key = 'Sin Equipo';
            if (!in_array($equipo_key, $equipos_trabajo)) {
                $equipos_trabajo[] = $equipo_key;
                $tickets_por_equipo[$equipo_key] = [];
            }
        }
    }
    
    if (!isset($tickets_por_equipo[$equipo_key])) {
        $tickets_por_equipo[$equipo_key] = [];
    }
    
    $tickets_por_equipo[$equipo_key][] = $ticket;
}

// Obtener tickets sin programar
$tickets_pendientes = $ticket->getTicketsWithoutDates();
?>

<!DOCTYPE html>
<html lang="es">


</html>