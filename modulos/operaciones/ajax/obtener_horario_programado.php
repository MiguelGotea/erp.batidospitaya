<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

if (!$conn) {
    die("Error de conexión a la base de datos");
}

verificarAutenticacion();

$codOperario = $_GET['cod_operario'] ?? null;
$fecha = $_GET['fecha'] ?? null;

if (!$codOperario || !$fecha) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Parámetros faltantes']);
    exit();
}

// Obtener la semana a la que pertenece la fecha
$semana = obtenerSemanaPorFecha($fecha);
if (!$semana) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Semana no encontrada']);
    exit();
}

// Obtener el día de la semana (1=lunes, 2=martes, etc.)
$diaSemana = date('N', strtotime($fecha));

// Mapear a nombres de columna
$diasColumnas = [
    1 => 'lunes',
    2 => 'martes',
    3 => 'miercoles',
    4 => 'jueves',
    5 => 'viernes',
    6 => 'sabado',
    7 => 'domingo'
];
$diaColumna = $diasColumnas[$diaSemana];

// Obtener el horario programado
$stmt = $conn->prepare("
    SELECT 
        {$diaColumna}_entrada as hora_entrada,
        {$diaColumna}_salida as hora_salida
    FROM HorariosSemanalesOperaciones
    WHERE cod_operario = ?
    AND id_semana_sistema = ?
    LIMIT 1
");
$stmt->execute([$codOperario, $semana['id']]);
$horario = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode($horario ?: []);