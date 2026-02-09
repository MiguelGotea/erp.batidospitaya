<?php
/**
 * Test de conexión al VPS WhatsApp
 * Acceder desde: https://tu-dominio.com/modulos/marketing/test_conexion_vps.php
 */

header('Content-Type: text/html; charset=utf-8');

$vpsUrl = 'http://192.81.217.146';

echo "<h2>Test de Conexión al VPS WhatsApp</h2>";
echo "<hr>";

// Test 1: Health check con diferentes timeouts
echo "<h3>Test 1: Health Check (cURL)</h3>";

$timeouts = [5, 10, 30];

foreach ($timeouts as $timeout) {
    echo "<p><strong>Timeout: {$timeout} segundos...</strong> ";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $vpsUrl . '/health',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);

    curl_close($ch);

    $duration = round(($endTime - $startTime) * 1000, 2);

    if ($response && $httpCode === 200) {
        echo "<span style='color:green;'>✅ ÉXITO</span> ({$duration}ms)";
        echo "<br>Respuesta: <code>" . htmlspecialchars($response) . "</code>";
    } else {
        echo "<span style='color:red;'>❌ ERROR</span> ({$duration}ms)";
        echo "<br>HTTP Code: {$httpCode}";
        echo "<br>Error #{$errno}: " . htmlspecialchars($error);
    }
    echo "</p>";

    if ($response && $httpCode === 200) {
        break; // Si funciona, no seguir probando
    }
}

// Test 2: file_get_contents
echo "<h3>Test 2: file_get_contents</h3>";
echo "<p>";

$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'ignore_errors' => true
    ]
]);

$startTime = microtime(true);
$response = @file_get_contents($vpsUrl . '/health', false, $context);
$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000, 2);

if ($response) {
    echo "<span style='color:green;'>✅ ÉXITO</span> ({$duration}ms)";
    echo "<br>Respuesta: <code>" . htmlspecialchars($response) . "</code>";
} else {
    echo "<span style='color:red;'>❌ ERROR</span> ({$duration}ms)";
    $error = error_get_last();
    echo "<br>Error: " . htmlspecialchars($error['message'] ?? 'Desconocido');
}
echo "</p>";

// Test 3: Verificar funciones disponibles
echo "<h3>Test 3: Funciones PHP disponibles</h3>";
echo "<ul>";
echo "<li>cURL: " . (function_exists('curl_init') ? '✅ Disponible' : '❌ No disponible') . "</li>";
echo "<li>allow_url_fopen: " . (ini_get('allow_url_fopen') ? '✅ Habilitado' : '❌ Deshabilitado') . "</li>";
echo "<li>fsockopen: " . (function_exists('fsockopen') ? '✅ Disponible' : '❌ No disponible') . "</li>";
echo "</ul>";

// Test 4: Información del servidor
echo "<h3>Test 4: Información del Servidor</h3>";
echo "<ul>";
echo "<li>IP del servidor Hostinger: " . ($_SERVER['SERVER_ADDR'] ?? 'No disponible') . "</li>";
echo "<li>PHP Version: " . phpversion() . "</li>";
echo "<li>cURL Version: " . (function_exists('curl_version') ? curl_version()['version'] : 'N/A') . "</li>";
echo "</ul>";

// Test 5: Socket directo
echo "<h3>Test 5: Conexión Socket Directo</h3>";
echo "<p>";

$startTime = microtime(true);
$socket = @fsockopen('192.81.217.146', 80, $errno, $errstr, 30);
$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000, 2);

if ($socket) {
    echo "<span style='color:green;'>✅ Socket conectado</span> ({$duration}ms)";
    fclose($socket);
} else {
    echo "<span style='color:red;'>❌ Socket falló</span> ({$duration}ms)";
    echo "<br>Error #{$errno}: " . htmlspecialchars($errstr);
}
echo "</p>";

echo "<hr>";
echo "<p><em>Test completado: " . date('Y-m-d H:i:s') . "</em></p>";
?>
```

---

### PASO 3: Acceder al archivo de prueba

Abre en tu navegador:
```
https://tu-dominio-erp.com/modulos/marketing/test_conexion_vps.php