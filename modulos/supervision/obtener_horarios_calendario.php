<?php
// require_once '../../core/auth/auth.php';
// require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

header('Content-Type: application/json');

// Verificar autenticación
verificarAutenticacion();

$semanaNumero = $_GET['semana'] ?? null;
$sucursalCodigo = $_GET['sucursal'] ?? null;

if (!$semanaNumero || !$sucursalCodigo) {
    echo json_encode([]);
    exit;
}

try {
    $semana = obtenerSemanaPorNumero($semanaNumero);
    if (!$semana) {
        echo json_encode([]);
        exit;
    }

    // Obtener todos los horarios de la semana y sucursal
    $stmt = $conn->prepare("
        SELECT h.*, o.Nombre, o.Apellido 
        FROM HorariosSemanalesOperaciones h
        JOIN Operarios o ON h.cod_operario = o.CodOperario
        WHERE h.id_semana_sistema = ? AND h.cod_sucursal = ?
    ");
    $stmt->execute([$semana['id'], $sucursalCodigo]);
    $horarios = $stmt->fetchAll();

    $eventos = [];
    $diasSemana = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

    foreach ($horarios as $horario) {
        foreach ($diasSemana as $dia) {
            $horaEntrada = $horario["{$dia}_entrada"];
            $horaSalida = $horario["{$dia}_salida"];
            $estado = $horario["{$dia}_estado"];

            if ($horaEntrada && $horaSalida) {
                // Calcular la fecha del día específico
                $fechaInicioSemana = new DateTime($semana['fecha_inicio']);
                $indiceDia = array_search($dia, $diasSemana);
                $fechaDia = clone $fechaInicioSemana;
                $fechaDia->modify("+$indiceDia days");

                $eventos[] = [
                    'operario_id' => $horario['cod_operario'],
                    'operario_nombre' => $horario['Nombre'] . ' ' . $horario['Apellido'],
                    'fecha' => $fechaDia->format('Y-m-d'),
                    'hora_entrada' => $horaEntrada,
                    'hora_salida' => $horaSalida,
                    'estado' => $estado,
                    'comentario' => $horario["{$dia}_comentario"] ?? '',
                    'horas_trabajadas' => $horario["{$dia}_horas"] ?? 0
                ];
            }
        }
    }

    echo json_encode($eventos);

} catch (Exception $e) {
    echo json_encode([]);
}
?>