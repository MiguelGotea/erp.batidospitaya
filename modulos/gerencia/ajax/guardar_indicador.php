<?php
require_once '../../../includes/auth.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener datos JSON
$input = file_get_contents('php://input');
$datos = json_decode($input, true);

// Validar datos recibidos
if (!isset($datos['id_indicador']) || !isset($datos['semana']) || !isset($datos['tipo'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$idIndicador = intval($datos['id_indicador']);
$semana = intval($datos['semana']);
$tipo = $datos['tipo'];
$divide = intval($datos['divide']);
$usuarioId = $_SESSION['usuario_id'];

// Verificar que el usuario tiene permisos
if (!verificarAccesoCargo([11, 16, 13, 42, 12, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos']);
    exit();
}

// Verificar que la semana es la anterior (opcional, depende de tu lógica)
// Aquí puedes agregar validación adicional si lo necesitas

// Preparar valores según el tipo
$numerador = null;
$denominador = null;

if ($tipo === 'unico') {
    $numerador = isset($datos['numerador']) && $datos['numerador'] !== '' ? 
        floatval($datos['numerador']) : null;
    // Si EnUso = 1 y no hay valor, guardar como 0
    if ($numerador === null) {
        // Aquí necesitarías obtener el valor de EnUso de la BD
        $stmtEnUso = $conn->prepare("SELECT EnUso FROM IndicadoresSemanales WHERE id = ?");
        $stmtEnUso->execute([$idIndicador]);
        $indicadorInfo = $stmtEnUso->fetch();
        
        if ($indicadorInfo && $indicadorInfo['EnUso'] == 1) {
            $numerador = 0;
        }
    }
    $denominador = null;
} else {
    // Para indicadores con divide=1
    $numerador = isset($datos['numerador']) && $datos['numerador'] !== '' ? floatval($datos['numerador']) : null;
    $denominador = isset($datos['denominador']) && $datos['denominador'] !== '' ? floatval($datos['denominador']) : null;
}

try {
    // Verificar si ya existe un registro
    $query = "SELECT id FROM IndicadoresSemanalesResultados 
              WHERE id_indicador = ? AND semana = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$idIndicador, $semana]);
    $existe = $stmt->fetch();
    
    if ($existe) {
        // Actualizar registro existente
        $query = "UPDATE IndicadoresSemanalesResultados 
                  SET numerador_dato = ?, 
                      denominador_dato = ?, 
                      usuario_modifica = ?, 
                      fecha_registro = NOW()
                  WHERE id_indicador = ? AND semana = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$numerador, $denominador, $usuarioId, $idIndicador, $semana]);
    } else {
        // Insertar nuevo registro
        $query = "INSERT INTO IndicadoresSemanalesResultados 
                  (id_indicador, semana, numerador_dato, denominador_dato, 
                   usuario_registra, fecha_registro)
                  VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->execute([$idIndicador, $semana, $numerador, $denominador, $usuarioId]);
    }
    
    // Calcular el resultado si es necesario (divide=1)
    $resultado = null;
    if ($divide == 1 && $numerador !== null && $denominador !== null && $denominador != 0) {
        // Indicador ID 1 (Rotación de Personal) tiene multiplicador 4.2 en numerador
        $multiplicador = ($idIndicador == 1) ? 4.2 : 1;
        
        // Aplicar multiplicador y calcular
        $resultado = (($numerador * $multiplicador) / $denominador) * 100; // Multiplicar por 100 para porcentaje
    }
    
    // Determinar el valor a devolver
    $valorDevolver = null;
    if ($tipo === 'unico') {
        $valorDevolver = $numerador;
    } else if ($tipo === 'numerador') {
        $valorDevolver = $numerador;
    } else if ($tipo === 'denominador') {
        $valorDevolver = $denominador;
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Datos guardados correctamente',
        'valor' => $valorDevolver,
        'resultado' => $resultado,
        'id_indicador' => $idIndicador,
        'semana' => $semana
    ]);
    
} catch (PDOException $e) {
    // Error en la base de datos
    error_log("Error al guardar indicador: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar en la base de datos'
    ]);
}
?>