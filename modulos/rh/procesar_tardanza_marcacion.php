<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

verificarAutenticacion();

// Solo líderes pueden registrar tardanzas desde marcaciones
if (!verificarAccesoCargo([5])) {
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para realizar esta acción']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Validar datos obligatorios
$camposRequeridos = ['cod_operario', 'cod_sucursal', 'fecha_tardanza', 'tipo_justificacion'];
foreach ($camposRequeridos as $campo) {
    if (empty($_POST[$campo])) {
        echo json_encode(['success' => false, 'message' => "Campo requerido: $campo"]);
        exit();
    }
}

// Validar que no se registren tardanzas para fechas futuras
$fechaTardanza = $_POST['fecha_tardanza'];
$fechaMaxima = date('Y-m-d', strtotime('-1 day'));
if ($fechaTardanza > $fechaMaxima) {
    echo json_encode(['success' => false, 'message' => 'No se pueden registrar tardanzas para fechas futuras o el día actual']);
    exit();
}

// Validar foto
if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Debe subir una foto como evidencia']);
    exit();
}

// Verificar si ya existe una tardanza para esta fecha y operario
$codOperario = (int)$_POST['cod_operario'];
$codSucursal = $_POST['cod_sucursal'];

$stmt = $conn->prepare("
    SELECT id FROM TardanzasManuales 
    WHERE cod_operario = ? 
    AND fecha_tardanza = ? 
    AND cod_sucursal = ?
    LIMIT 1
");
$stmt->execute([$codOperario, $fechaTardanza, $codSucursal]);

if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Ya existe una solicitud de tardanza para esta fecha']);
    exit();
}

// Obtener el código de contrato (usar el proporcionado o buscar el último)
$codContrato = $_POST['cod_contrato'] ?? null;
if (empty($codContrato)) {
    $codContrato = obtenerUltimoCodigoContrato($codOperario);
}

try {
    // Procesar la foto
    $fotoPath = null;
    if ($_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/tardanzas/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExt = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $fileName = 'tardanza_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $filePath)) {
            $fotoPath = $fileName;
        }
    }
    
    // Insertar la tardanza manual
    $stmt = $conn->prepare("
        INSERT INTO TardanzasManuales (
            cod_operario, 
            fecha_tardanza, 
            cod_sucursal, 
            tipo_justificacion, 
            observaciones, 
            foto_path, 
            registrado_por,
            cod_contrato,
            estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')
    ");
    
    $params = [
        $codOperario,
        $fechaTardanza,
        $codSucursal,
        $_POST['tipo_justificacion'],
        $_POST['observaciones'] ?? null,
        $fotoPath,
        $_SESSION['usuario_id'],
        $codContrato
    ];
    
    if ($stmt->execute($params)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Solicitud de justificación de tardanza enviada correctamente'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>