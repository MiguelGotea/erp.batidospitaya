<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['autenticado']) || $_SESSION['autenticado'] !== true) {
    http_response_code(403);
    die('Acceso no autorizado');
}

// Configuración de seguridad
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Directorio para subir imágenes
$uploadDir = 'uploads/ckeditor/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Validar archivo
if (isset($_FILES['upload'])) {
    $file = $_FILES['upload'];
    
    // Validaciones de seguridad
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fileInfo->file($file['tmp_name']);
    
    if (!in_array($mime, $allowedTypes) || $file['size'] > $maxSize) {
        http_response_code(400);
        die('Tipo de archivo no permitido o tamaño excedido');
    }
    
    // Generar nombre seguro
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeName = uniqid() . '.' . preg_replace('/[^a-zA-Z0-9]/', '', strtolower($ext));
    $destination = $uploadDir . $safeName;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Retornar la URL de la imagen para CKEditor
        $url = 'https://' . $_SERVER['HTTP_HOST'] . '/' . $destination;
        echo json_encode([
            'uploaded' => 1,
            'fileName' => $safeName,
            'url' => $url
        ]);
    } else {
        http_response_code(500);
        die('Error al subir el archivo');
    }
} else {
    http_response_code(400);
    die('No se recibió ningún archivo');
}
?>
