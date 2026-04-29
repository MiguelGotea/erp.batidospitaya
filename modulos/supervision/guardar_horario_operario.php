<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

header('Content-Type: application/json');

// Verificar autenticación y permisos
$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar permisos de supervisión (cargo 21)
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
if (!$esAdmin && !verificarAccesoCargo([21])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener datos básicos
$idSemana = $_POST['semana'] ?? null;
$codSucursal = $_POST['sucursal'] ?? null;
$codOperario = $_POST['cod_operario'] ?? null;

if (!$idSemana || !$codSucursal || !$codOperario) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Verificar si existe el operario (no necesariamente el horario del líder)
$operarioExiste = false;
try {
    $stmt = $conn->prepare("SELECT 1 FROM Operarios WHERE CodOperario = ? LIMIT 1");
    $stmt->execute([$codOperario]);
    $operarioExiste = $stmt->fetch() !== false;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al verificar operario']);
    exit;
}

if (!$operarioExiste) {
    echo json_encode(['success' => false, 'message' => 'El operario no existe']);
    exit;
}

// Obtener horario del líder (si existe)
$horarioLider = obtenerHorarioOperario($codOperario, $idSemana, $codSucursal);
$tieneHorarioLider = $horarioLider !== false;

// Procesar el horario
$dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
$horarioCompleto = [];
$totalHoras = 0;

foreach ($dias as $dia) {
    $horarioCompleto["{$dia}_estado"] = $_POST["horarios[{$codOperario}][{$dia}_estado]"] ?? 'Activo';
    $horarioCompleto["{$dia}_comentario"] = $_POST["horarios[{$codOperario}][{$dia}_comentario]"] ?? '';
    $horarioCompleto["{$dia}_entrada"] = $_POST["horarios[{$codOperario}][{$dia}_entrada]"] ?? null;
    $horarioCompleto["{$dia}_salida"] = $_POST["horarios[{$codOperario}][{$dia}_salida]"] ?? null;
    $horarioCompleto["{$dia}_sucursal_externa"] = ($horarioCompleto["{$dia}_estado"] === 'Otra.Tienda') ? ($_POST["horarios[{$codOperario}][{$dia}_sucursal_externa]"] ?? null) : null;

    // Calcular horas
    if (!empty($horarioCompleto["{$dia}_entrada"]) && !empty($horarioCompleto["{$dia}_salida"])) {
        $entrada = new DateTime($horarioCompleto["{$dia}_entrada"]);
        $salida = new DateTime($horarioCompleto["{$dia}_salida"]);
        $diff = $entrada->diff($salida);
        $horas = $diff->h + ($diff->i / 60);
        $horarioCompleto["{$dia}_horas"] = $horas;
        $totalHoras += $horas;
    } else {
        $horarioCompleto["{$dia}_horas"] = 0;
    }
}

$horarioCompleto['total_horas'] = $totalHoras;

// Verificar si ya existe registro en operaciones
$existente = obtenerHorarioOperaciones($codOperario, $idSemana, $codSucursal);

try {
    if ($existente) {
        // Actualizar registro existente
        actualizarHorarioOperaciones($existente['id'], $horarioCompleto);
        $mensaje = 'Horario actualizado correctamente';
    } else {
        // Crear nuevo registro
        crearHorarioOperaciones($idSemana, $codOperario, $codSucursal, $horarioCompleto);
        $mensaje = 'Horario creado correctamente';
    }

    // Mensaje especial si no hay horario del líder
    if (!$tieneHorarioLider) {
        $mensaje .= ' (Nota: No existe horario del líder para este operario)';
    }

    echo json_encode(['success' => true, 'message' => $mensaje, 'no_lider' => !$tieneHorarioLider]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}
