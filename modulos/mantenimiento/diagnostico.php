<?php
// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Diagnóstico del Sistema</h1>";
echo "<hr>";

// 1. Verificar PHP
echo "<h3>1. Información de PHP</h3>";
echo "Versión de PHP: " . PHP_VERSION . "<br>";
echo "Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Directorio actual: " . __DIR__ . "<br>";
echo "Usuario del proceso: " . get_current_user() . "<br>";
echo "<br>";

// 2. Verificar conexión a base de datos
echo "<h3>2. Prueba de Conexión a Base de Datos</h3>";
try {
    $host = '145.223.105.42';
    $port = '3306';
    $db_name = 'u839374897_erp';
    $username = 'u839374897_erp';
    $password = 'ERpPitHay2025$';
    
    $dsn = "mysql:host={$host};port={$port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "✅ <strong style='color: green;'>Conexión a BD exitosa</strong><br>";
    
    // Verificar tablas existentes
    echo "<br><strong>Tablas existentes en la BD:</strong><br>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll();
    foreach ($tables as $table) {
        $table_name = array_values($table)[0];
        if (strpos($table_name, 'mtto_') === 0) {
            echo "✅ $table_name<br>";
        } else {
            echo "➖ $table_name<br>";
        }
    }
    
    // Verificar si existen las tablas necesarias
    $required_tables = ['mtto_tickets', 'mtto_tipos_casos', 'mtto_equipos', 'mtto_chat_mensajes'];
    echo "<br><strong>Verificación de tablas requeridas:</strong><br>";
    foreach ($required_tables as $table) {
        try {
            $result = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "✅ <span style='color: green;'>$table</span> - $result registros<br>";
        } catch (Exception $e) {
            echo "❌ <span style='color: red;'>$table</span> - NO EXISTE<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ <strong style='color: red;'>Error de conexión a BD:</strong><br>";
    echo $e->getMessage() . "<br>";
}

echo "<br>";

// 3. Verificar estructura de directorios
echo "<h3>3. Estructura de Directorios</h3>";
$directories = ['config', 'models', 'ajax', 'uploads', 'uploads/tickets', 'uploads/chat'];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        $writable = is_writable($dir) ? 'Escribible' : 'NO escribible';
        echo "✅ <span style='color: green;'>$dir/</span> - $writable<br>";
    } else {
        echo "❌ <span style='color: red;'>$dir/</span> - NO EXISTE<br>";
    }
}

// 4. Verificar archivos principales
echo "<br><h3>4. Archivos Principales</h3>";
$files = [
    'config/database.php',
    'models/Ticket.php',
    'models/Chat.php',
    'formulario_mantenimiento.php',
    'formulario_equipos.php',
    'dashboard_mantenimiento.php',
    'dashboard_sucursales.php',
    'chat.php',
    'calendario.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✅ <span style='color: green;'>$file</span> - $size bytes<br>";
    } else {
        echo "❌ <span style='color: red;'>$file</span> - NO EXISTE<br>";
    }
}

// 5. Verificar extensiones PHP
echo "<br><h3>5. Extensiones PHP Requeridas</h3>";
$extensions = ['pdo', 'pdo_mysql', 'gd', 'fileinfo', 'json'];

foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ <span style='color: green;'>$ext</span><br>";
    } else {
        echo "❌ <span style='color: red;'>$ext</span> - NO DISPONIBLE<br>";
    }
}

// 6. Verificar permisos de archivos
echo "<br><h3>6. Permisos de Archivos</h3>";
$current_permissions = substr(sprintf('%o', fileperms(__DIR__)), -4);
echo "Permisos del directorio actual: $current_permissions<br>";

// 7. Información del servidor
echo "<br><h3>7. Variables del Servidor</h3>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'No definido') . "<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido') . "<br>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'No definido') . "<br>";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'No definido') . "<br>";

// 8. Test simple de escritura
echo "<br><h3>8. Test de Escritura</h3>";
$test_file = 'test_write.txt';
if (file_put_contents($test_file, 'Test de escritura: ' . date('Y-m-d H:i:s'))) {
    echo "✅ <span style='color: green;'>Escritura de archivos funciona</span><br>";
    unlink($test_file); // Limpiar archivo de prueba
} else {
    echo "❌ <span style='color: red;'>No se puede escribir archivos</span><br>";
}

// 9. Información de memoria y límites
echo "<br><h3>9. Límites del Sistema</h3>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "Post Max Size: " . ini_get('post_max_size') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . " segundos<br>";

echo "<br><hr>";
echo "<h3>Resumen del Diagnóstico</h3>";
echo "✅ = Correcto | ❌ = Problema | ➖ = Información<br>";
echo "<br><em>Fecha del diagnóstico: " . date('Y-m-d H:i:s') . "</em>";
?>