<?php
require_once 'conexion.php'; // Si necesitas la conexión
date_default_timezone_set('America/Managua');

function formatFechaEspanol($fecha = 'now') {
    $meses = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
    ];
    
    $date = new DateTime($fecha, new DateTimeZone('UTC'));
    $date->modify('-6 hours');
    
    return $date->format('d').'-'.$meses[$date->format('n')].'-'.$date->format('y').' '.$date->format('h:i a');
}

echo formatFechaEspanol();
?>
