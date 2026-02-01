<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar autenticación
verificarAutenticacion();

// Verificar que sea líder (cargo 5)
if (!verificarAccesoCargo([5])) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para registrar faltas']);
    exit();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['registrar_falta_marcacion'])) {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
    exit();
}

try {
    // Obtener datos del formulario
    $codOperario = (int)$_POST['cod_operario'];
    $codSucursal = (int)$_POST['cod_sucursal'];
    $fechaFalta = $_POST['fecha_falta'];
    $observaciones = trim($_POST['observaciones']);
    $registradoPor = $_SESSION['usuario_id'];
    
    // Validaciones básicas
    if (empty($codOperario) || empty($codSucursal) || empty($fechaFalta)) {
        throw new Exception('Datos incompletos');
    }
    
    // Validar que no sea fecha futura ni hoy
    $fechaHoy = date('Y-m-d');
    if ($fechaFalta >= $fechaHoy) {
        throw new Exception('No se pueden registrar faltas para fechas futuras o el día actual');
    }
    
    // Verificar si ya existe una falta para esta fecha
    $stmt = $conn->prepare("
        SELECT id FROM faltas_manual 
        WHERE cod_operario = ? 
        AND cod_sucursal = ? 
        AND fecha_falta = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $codSucursal, $fechaFalta]);
    
    if ($stmt->fetch()) {
        throw new Exception('Ya existe una falta registrada para este colaborador en esta fecha');
    }
    
    // Obtener el último contrato del operario
    $codContrato = obtenerUltimoCodigoContrato($codOperario);
    
    // Validar foto
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Debe subir una foto como evidencia');
    }
    
    $foto = $_FILES['foto'];
    
    // Validar tamaño (máximo 5MB)
    if ($foto['size'] > 5 * 1024 * 1024) {
        throw new Exception('La foto no debe exceder los 5MB');
    }
    
    // Validar tipo de archivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($foto['type'], $allowedTypes)) {
        throw new Exception('Solo se permiten imágenes JPEG, PNG o GIF');
    }
    
    // Crear nombre único para el archivo
    $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
    $nombreFoto = 'falta_' . $codOperario . '_' . date('YmdHis') . '.' . $extension;
    
    // Ruta relativa para la base de datos
    $rutaRelativa = '/uploads/faltas_marcaciones/' . $nombreFoto;
    
    // Ruta absoluta para guardar el archivo
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/faltas_marcaciones/';
    
    // Crear directorio si no existe
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de uploads');
        }
    }
    
    // Verificar que el directorio es escribible
    if (!is_writable($uploadDir)) {
        throw new Exception('El directorio de uploads no tiene permisos de escritura');
    }
    
    $rutaCompleta = $uploadDir . $nombreFoto;
    
    // Mover el archivo subido
    if (!move_uploaded_file($foto['tmp_name'], $rutaCompleta)) {
        throw new Exception('Error al guardar la foto en el servidor');
    }
    
    // Insertar nueva falta manual
    $stmt = $conn->prepare("
        INSERT INTO faltas_manual (
            cod_operario, 
            fecha_falta, 
            cod_sucursal, 
            tipo_falta, 
            observaciones, 
            foto_path, 
            registrado_por,
            cod_contrato,
            porcentaje_pago
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $tipoFalta = 'Pendiente'; // Siempre Pendiente para líderes
    $porcentajePago = 0; // Por defecto 0% para Pendiente
    
    $stmt->execute([
        $codOperario,
        $fechaFalta,
        $codSucursal,
        $tipoFalta,
        $observaciones,
        $rutaRelativa,
        $registradoPor,
        $codContrato,
        $porcentajePago
    ]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Falta registrada correctamente'
    ]);
    
} catch (Exception $e) {
    // Eliminar la foto si hubo un error después de subirla
    if (isset($rutaCompleta) && file_exists($rutaCompleta)) {
        @unlink($rutaCompleta);
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>