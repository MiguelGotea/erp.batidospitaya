<?php
require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

header('Content-Type: application/json');

// Verificar autenticación y permisos
$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar permisos de supervisión
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
if (!$esAdmin && !verificarAccesoCargo([21])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos del POST
$codOperario = $_POST['cod_operario'] ?? null;
$idSemana = $_POST['id_semana'] ?? null;
$codSucursal = $_POST['cod_sucursal'] ?? null;
$dia = $_POST['dia'] ?? null;
$horaEntrada = $_POST['hora_entrada'] ?? null;
$horaSalida = $_POST['hora_salida'] ?? null;
$comentario = $_POST['comentario'] ?? '';
$estado = $_POST['estado'] ?? 'Activo';

if (!$codOperario || !$idSemana || !$codSucursal || !$dia) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Calcular horas trabajadas
    $horasTrabajadas = 0;
    if ($horaEntrada && $horaSalida && $estado === 'Activo') {
        $entrada = DateTime::createFromFormat('H:i', $horaEntrada);
        $salida = DateTime::createFromFormat('H:i', $horaSalida);
        if ($entrada && $salida) {
            $diferencia = $salida->diff($entrada);
            $horasTrabajadas = $diferencia->h + ($diferencia->i / 60);
        }
    }

    // Verificar si ya existe un registro
    $stmt = $conn->prepare("
        SELECT * FROM HorariosSemanalesOperaciones 
        WHERE cod_operario = ? AND id_semana_sistema = ? AND cod_sucursal = ?
    ");
    $stmt->execute([$codOperario, $idSemana, $codSucursal]);
    $existente = $stmt->fetch();

    if ($existente) {
        // Actualizar registro existente
        $stmt = $conn->prepare("
            UPDATE HorariosSemanalesOperaciones SET
            {$dia}_estado = ?,
            {$dia}_comentario = ?,
            {$dia}_entrada = ?,
            {$dia}_salida = ?,
            {$dia}_horas = ?,
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
            $existente['id']
        ]);
    } else {
        // Insertar nuevo registro
        $stmt = $conn->prepare("
            INSERT INTO HorariosSemanalesOperaciones (
                cod_operario, id_semana_sistema, cod_sucursal,
                {$dia}_estado, {$dia}_comentario, {$dia}_entrada, {$dia}_salida, {$dia}_horas,
                creado_por, actualizado_por, fecha_creacion, fecha_actualizacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $codOperario,
            $idSemana,
            $codSucursal,
            $estado,
            $comentario,
            $horaEntrada,
            $horaSalida,
            $horasTrabajadas,
            $_SESSION['usuario_id'],
            $_SESSION['usuario_id']
        ]);
    }

    echo json_encode(['success' => true, 'message' => 'Horario guardado correctamente']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}
?>