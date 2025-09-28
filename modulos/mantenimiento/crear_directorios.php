<?php
// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Creación de Estructura de Directorios</h1>";
echo "<hr>";

// Directorios necesarios
$directorios = [
    'config',
    'models', 
    'ajax',
    'uploads',
    'uploads/tickets',
    'uploads/chat'
];

echo "<h3>Creando Directorios:</h3>";

foreach ($directorios as $directorio) {
    try {
        if (!is_dir($directorio)) {
            if (mkdir($directorio, 0755, true)) {
                echo "✅ <strong style='color: green;'>$directorio/</strong> - Creado exitosamente<br>";
            } else {
                echo "❌ <strong style='color: red;'>$directorio/</strong> - No se pudo crear<br>";
            }
        } else {
            echo "ℹ️ <strong style='color: blue;'>$directorio/</strong> - Ya existe<br>";
        }
    } catch (Exception $e) {
        echo "❌ <strong style='color: red;'>$directorio/</strong> - Error: " . $e->getMessage() . "<br>";
    }
}

echo "<br><h3>Verificando Permisos:</h3>";

foreach ($directorios as $directorio) {
    if (is_dir($directorio)) {
        $permisos = substr(sprintf('%o', fileperms($directorio)), -4);
        $escribible = is_writable($directorio) ? '✅ Escribible' : '❌ NO Escribible';
        echo "<strong>$directorio/</strong> - Permisos: $permisos - $escribible<br>";
    }
}

echo "<br><h3>Creando Archivos .htaccess de Seguridad:</h3>";

// .htaccess para uploads
$htaccess_uploads = 'uploads/.htaccess';
$contenido_htaccess = "# Permitir solo imágenes
<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">
    Order allow,deny
    Allow from all
</FilesMatch>

# Denegar todo lo demás
<FilesMatch \"^(?!.*\\.(jpg|jpeg|png|gif|webp)$).*$\">
    Order allow,deny
    Deny from all
</FilesMatch>

# Prevenir ejecución de PHP
<Files *.php>
    Order allow,deny
    Deny from all
</Files>
";

if (!file_exists($htaccess_uploads)) {
    if (file_put_contents($htaccess_uploads, $contenido_htaccess)) {
        echo "✅ <strong style='color: green;'>uploads/.htaccess</strong> - Creado para seguridad<br>";
    } else {
        echo "❌ <strong style='color: red;'>uploads/.htaccess</strong> - No se pudo crear<br>";
    }
} else {
    echo "ℹ️ <strong style='color: blue;'>uploads/.htaccess</strong> - Ya existe<br>";
}

echo "<br><h3>Creando Archivo de Prueba:</h3>";

// Crear archivo de prueba de escritura
$archivos_prueba = [
    'uploads/test_write.txt',
    'uploads/tickets/test_write.txt', 
    'uploads/chat/test_write.txt'
];

foreach ($archivos_prueba as $archivo) {
    $contenido = "Test de escritura - " . date('Y-m-d H:i:s') . "\n";
    if (file_put_contents($archivo, $contenido)) {
        echo "✅ <strong style='color: green;'>$archivo</strong> - Escritura exitosa<br>";
        // Limpiar archivo de prueba
        unlink($archivo);
    } else {
        echo "❌ <strong style='color: red;'>$archivo</strong> - No se puede escribir<br>";
    }
}

echo "<br><h3>Información del Sistema:</h3>";
echo "<strong>Directorio actual:</strong> " . __DIR__ . "<br>";
echo "<strong>Usuario del proceso:</strong> " . get_current_user() . "<br>";
echo "<strong>Umask actual:</strong> " . sprintf('%04o', umask()) . "<br>";

// Intentar cambiar permisos si es necesario
echo "<br><h3>Ajustando Permisos (si es necesario):</h3>";
$directorios_permisos = ['uploads', 'uploads/tickets', 'uploads/chat'];

foreach ($directorios_permisos as $dir) {
    if (is_dir($dir)) {
        if (chmod($dir, 0755)) {
            echo "✅ <strong style='color: green;'>$dir/</strong> - Permisos ajustados a 755<br>";
        } else {
            echo "⚠️ <strong style='color: orange;'>$dir/</strong> - No se pudieron ajustar permisos<br>";
        }
    }
}

echo "<br><hr>";
echo "<h3 style='color: green;'>🎉 ¡Estructura de directorios completada!</h3>";
echo "<p><strong>Directorios creados y configurados:</strong></p>";
echo "<ul>";
foreach ($directorios as $dir) {
    if (is_dir($dir)) {
        echo "<li>✅ $dir/</li>";
    } else {
        echo "<li>❌ $dir/</li>";
    }
}
echo "</ul>";

echo "<p><strong>Próximo paso:</strong> Ejecutar <code>crear_tablas.php</code> para crear las tablas de la base de datos.</p>";

echo "<br><em>Fecha de creación: " . date('Y-m-d H:i:s') . "</em>";
?>