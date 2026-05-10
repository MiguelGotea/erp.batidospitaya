<?php
// require_once '../../core/auth/auth.php';
// require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

header('Content-Type: application/json');

// Verificar autenticación y permisos
verificarAutenticacion();
if (!verificarAccesoCargo([16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del POST
$operarioId = $_POST['operario_id'] ?? null;
$semanaNumero = $_POST['semana'] ?? null;
$sucursalCodigo = $_POST['sucursal'] ?? null;
$fecha = $_POST['fecha'] ?? null;
$horaEntrada = $_POST['hora_entrada'] ?? null;
$horaSalida = $_POST['hora_salida'] ?? null;
$estado = $_POST['estado'] ?? 'Activo';
$comentario = $_POST['comentario'] ?? '';
$horasTrabajadas = $_POST['horas_trabajadas'] ?? 0;

// Validar datos
if (!$operarioId || !$semanaNumero || !$sucursalCodigo || !$fecha) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Obtener semana del sistema
    $semana = obtenerSemanaPorNumero($semanaNumero);
    if (!$semana) {
        echo json_encode(['success' => false, 'message' => 'Semana no válida']);
        exit;
    }

    // Determinar el día de la semana (lunes, martes, etc.)
    $fechaObj = new DateTime($fecha);
    $diasSemana = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
    $diaSemana = $diasSemana[(int)$fechaObj->format('w')];
    
    // Verificar si ya existe un horario para este operario en esta semana
    $stmt = $conn->prepare("
        SELECT id FROM HorariosSemanalesOperaciones 
        WHERE cod_operario = ? AND id_semana_sistema = ? AND cod_sucursal = ?
    ");
    $stmt->execute([$operarioId, $semana['id'], $sucursalCodigo]);
    $horarioExistente = $stmt->fetch();

    if ($horarioExistente) {
        // Actualizar horario existente
        $stmt = $conn->prepare("
            UPDATE HorariosSemanalesOperaciones SET
            {$diaSemana}_estado = ?,
            {$diaSemana}_comentario = ?,
            {$diaSemana}_entrada = ?,
            {$diaSemana}_salida = ?,
            {$diaSemana}_horas = ?,
            actualizado_por = ?,
            fecha_actualizacion = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $estado,
            $comentario,
            $horaEntrada,
            $horaSalida,
            $horasTrabajadas,
            $_SESSION['usuario_id'],
            $horarioExistente['id']
        ]);
    } else {
        // Crear nuevo horario
        $stmt = $conn->prepare("
            INSERT INTO HorariosSemanalesOperaciones (
                id_semana_sistema, cod_operario, cod_sucursal,
                {$diaSemana}_estado, {$diaSemana}_comentario, 
                {$diaSemana}_entrada, {$diaSemana}_salida, {$diaSemana}_horas,
                creado_por, fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $semana['id'],
            $operarioId,
            $sucursalCodigo,
            $estado,
            $comentario,
            $horaEntrada,
            $horaSalida,
            $horasTrabajadas,
            $_SESSION['usuario_id']
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Horario guardado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>