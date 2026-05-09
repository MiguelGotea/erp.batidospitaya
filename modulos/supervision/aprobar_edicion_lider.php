<?php
// require_once '../../includes/auth.php';
// require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones

// Verificar permisos (solo supervisores o admin)
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
if (!$esAdmin && !verificarAccesoCargo([21])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para realizar esta acción']);
    exit();
}

// Validar datos de entrada
$semana = $_POST['semana'] ?? null;
$sucursal = $_POST['sucursal'] ?? null;
$aprobar = isset($_POST['aprobar']) ? (int)$_POST['aprobar'] : 0;

if (!$semana || !$sucursal) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    // Obtener el ID de la semana (sin validar horarios del líder)
    $semanaData = obtenerSemanaPorNumero($semana);
    if (!$semanaData) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Semana no válida']);
        exit();
    }
    
    // Verificar solo si la sucursal existe (sin validar horarios)
    $stmt = $conn->prepare("SELECT codigo FROM sucursales WHERE codigo = ?");
    $stmt->execute([$sucursal]);
    if (!$stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sucursal no válida']);
        exit();
    }
    
    // Verificar si ya existe una autorización
    $stmt = $conn->prepare("SELECT id FROM AutorizacionesEdicion 
                           WHERE id_semana = ? AND cod_sucursal = ?");
    $stmt->execute([$semanaData['id'], $sucursal]);
    $existente = $stmt->fetch();
    
    if ($existente) {
        // Actualizar registro existente
        $stmt = $conn->prepare("UPDATE AutorizacionesEdicion SET 
                               autorizado = ?, 
                               actualizado_por = ?, 
                               fecha_actualizacion = NOW() 
                               WHERE id = ?");
        $stmt->execute([$aprobar, $_SESSION['usuario_id'], $existente['id']]);
    } else {
        // Crear nuevo registro (no requiere horarios previos)
        $stmt = $conn->prepare("INSERT INTO AutorizacionesEdicion 
                               (id_semana, cod_sucursal, autorizado, creado_por) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([$semanaData['id'], $sucursal, $aprobar, $_SESSION['usuario_id']]);
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => $aprobar ? 
            '✅ Edición autorizada correctamente' : '🔒 Edición desautorizada correctamente'
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => '❌ Error en la base de datos: ' . $e->getMessage()
    ]);
}
?>