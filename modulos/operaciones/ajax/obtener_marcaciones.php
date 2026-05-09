<?php
require_once '../../includes/auth.php';
// Verificar que la petición sea GET y tenga los parámetros necesarios
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['cod_operario']) || !isset($_GET['fecha'])) {
    header('HTTP/1.1 400 Bad Request');
    exit();
}

$codOperario = (int)$_GET['cod_operario'];
$fechaConsulta = $_GET['fecha'];
$debugMode = isset($_GET['debug']);

try {
    // Validar y formatear la fecha recibida
    $fechaObj = new DateTime($fechaConsulta);
    $fechaFormateada = $fechaObj->format('Y-m-d');
    
    // Inicializar array de resultado con información de depuración
    $resultado = [
        'hora_ingreso' => null,
        'hora_salida' => null,
        'hora_entrada_programada' => null,
        'hora_salida_programada' => null,
        'fecha_recibida' => $fechaConsulta,
        'fecha_utilizada' => $fechaFormateada,
        'semana_horario' => null
    ];
    
    if ($debugMode) {
        $resultado['debug'] = [
            'paso_1' => 'Fecha recibida y validada',
            'timestamp_recibido' => $_SERVER['REQUEST_TIME'],
            'timezone' => date_default_timezone_get()
        ];
    }
    
    // 1. Obtener marcaciones del operario para esa fecha
    $sqlMarcaciones = "
        SELECT m.hora_ingreso, m.hora_salida, m.fecha
        FROM marcaciones m
        WHERE m.CodOperario = ?
        AND m.fecha = ?
        LIMIT 1
    ";
    $stmtMarcaciones = $conn->prepare($sqlMarcaciones);
    $stmtMarcaciones->execute([$codOperario, $fechaFormateada]);
    $marcacion = $stmtMarcaciones->fetch();
    
    if ($marcacion) {
        $resultado['hora_ingreso'] = $marcacion['hora_ingreso'];
        $resultado['hora_salida'] = $marcacion['hora_salida'];
        $resultado['fecha_marcacion_real'] = $marcacion['fecha'];
        
        if ($debugMode) {
            $resultado['debug']['paso_2'] = 'Marcaciones encontradas';
            $resultado['debug']['query_marcaciones'] = $sqlMarcaciones;
            $resultado['debug']['params_marcaciones'] = [$codOperario, $fechaFormateada];
        }
    } else {
        if ($debugMode) {
            $resultado['debug']['paso_2'] = 'No se encontraron marcaciones';
        }
    }
    
    // 2. Obtener horario programado para ese día
    $semana = obtenerSemanaPorFecha($fechaFormateada);
    if ($semana) {
        $resultado['semana_horario'] = [
            'id' => $semana['id'],
            'fecha_inicio' => $semana['fecha_inicio'],
            'fecha_fin' => $semana['fecha_fin']
        ];
        
        $diaSemana = date('N', strtotime($fechaFormateada)); // 1=lunes, 7=domingo
        
        $diasColumna = [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo'
        ];
        $diaColumna = $diasColumna[$diaSemana];
        
        $sqlHorario = "
            SELECT 
                {$diaColumna}_entrada as hora_entrada_programada,
                {$diaColumna}_salida as hora_salida_programada
            FROM HorariosSemanalesOperaciones
            WHERE cod_operario = ?
            AND id_semana_sistema = ?
            LIMIT 1
        ";
        $stmtHorario = $conn->prepare($sqlHorario);
        $stmtHorario->execute([$codOperario, $semana['id']]);
        $horario = $stmtHorario->fetch();
        
        if ($horario) {
            $resultado['hora_entrada_programada'] = $horario['hora_entrada_programada'];
            $resultado['hora_salida_programada'] = $horario['hora_salida_programada'];
            
            if ($debugMode) {
                $resultado['debug']['paso_3'] = 'Horario encontrado';
                $resultado['debug']['query_horario'] = $sqlHorario;
                $resultado['debug']['params_horario'] = [$codOperario, $semana['id']];
                $resultado['debug']['dia_semana'] = $diaSemana;
                $resultado['debug']['columna_dia'] = $diaColumna;
            }
        } else {
            if ($debugMode) {
                $resultado['debug']['paso_3'] = 'No se encontró horario programado';
            }
        }
    } else {
        if ($debugMode) {
            $resultado['debug']['paso_3'] = 'No se encontró semana para la fecha';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($resultado);
    
} catch (Exception $e) {
    $errorResponse = [
        'error' => $e->getMessage(),
        'fecha_recibida' => $fechaConsulta,
        'trace' => $debugMode ? $e->getTraceAsString() : null
    ];
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode($errorResponse);
}