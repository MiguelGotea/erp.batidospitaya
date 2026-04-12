<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $usuarioId = $usuario['CodOperario'];
    
    $idProducto = (int)$_POST['id_producto'];
    
    if ($idProducto <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo');
    }
    
    $archivo = $_FILES['archivo'];
    
    // Validar tamaño (10MB)
    if ($archivo['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo no debe superar 10MB');
    }
    
    // Crear directorio si no existe
    $dirUpload = '../uploads/productos/' . $idProducto . '/archivos/';
    if (!is_dir($dirUpload)) {
        mkdir($dirUpload, 0755, true);
    }
    
    // Generar nombre único
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
    $rutaDestino = $dirUpload . $nombreArchivo;
    
    // Mover archivo
    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        throw new Exception('Error al guardar el archivo');
    }
    
    // Guardar en BD
    $rutaRelativa = 'modulos/POS/uploads/productos/' . $idProducto . '/archivos/' . $nombreArchivo;
    
    $sql = "INSERT INTO archivos_presentacion_producto 
            (id_presentacion_producto, nombre, ruta, usuario_creacion, fecha_creacion)
            VALUES (:id_producto, :nombre, :ruta, :usuario, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_producto' => $idProducto,
        ':nombre' => $archivo['name'],
        ':ruta' => $rutaRelativa,
        ':usuario' => $usuarioId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Archivo subido exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>