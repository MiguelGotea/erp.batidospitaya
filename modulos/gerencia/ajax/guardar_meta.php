<?php
require_once '../../../includes/auth.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Validar datos recibidos
if (!isset($_POST['id_indicador']) || !isset($_POST['semana'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$idIndicador = intval($_POST['id_indicador']);
$semana = intval($_POST['semana']);
$meta = isset($_POST['meta']) && $_POST['meta'] !== '' ? floatval($_POST['meta']) : null;
$usuarioId = $_SESSION['usuario_id'];

// Verificar que el usuario tiene permisos
if (!verificarAccesoCargo([11, 16, 13, 42, 12]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para editar metas']);
    exit();
}

try {
    // Verificar que sea la semana anterior
    $stmtSemanaActual = $conn->prepare("
        SELECT numero_semana FROM SemanasSistema 
        WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()
        LIMIT 1
    ");
    $stmtSemanaActual->execute();
    $semanaActual = $stmtSemanaActual->fetch();
    
    if (!$semanaActual) {
        echo json_encode(['success' => false, 'message' => 'No se encontró la semana actual del sistema']);
        exit();
    }
    
    $numeroSemanaAnterior = $semanaActual['numero_semana'] - 1;
    
    // Verificar que la semana a editar es la anterior
    $stmtSemanaEditar = $conn->prepare("SELECT numero_semana FROM SemanasSistema WHERE id = ?");
    $stmtSemanaEditar->execute([$semana]);
    $semanaEditar = $stmtSemanaEditar->fetch();
    
    if (!$semanaEditar || $semanaEditar['numero_semana'] != $numeroSemanaAnterior) {
        echo json_encode(['success' => false, 'message' => 'Solo se puede editar la meta de la semana anterior']);
        exit();
    }
    
    // Verificar si ya existe registro
    $stmtExiste = $conn->prepare("
        SELECT id FROM IndicadoresSemanalesResultados 
        WHERE id_indicador = ? AND semana = ?
    ");
    $stmtExiste->execute([$idIndicador, $semana]);
    $existe = $stmtExiste->fetch();
    
    if ($existe) {
        // Actualizar meta
        $stmtUpdate = $conn->prepare("
            UPDATE IndicadoresSemanalesResultados 
            SET meta = ?, usuario_modifica = ?, fecha_registro = NOW()
            WHERE id_indicador = ? AND semana = ?
        ");
        $stmtUpdate->execute([$meta, $usuarioId, $idIndicador, $semana]);
    } else {
        // Insertar nuevo registro con meta
        $stmtInsert = $conn->prepare("
            INSERT INTO IndicadoresSemanalesResultados 
            (id_indicador, semana, meta, usuario_registra, fecha_registro)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmtInsert->execute([$idIndicador, $semana, $meta, $usuarioId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Meta guardada correctamente',
        'valor' => $meta
    ]);
    
} catch (PDOException $e) {
    error_log("Error al guardar meta: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar en la base de datos'
    ]);
}
?>