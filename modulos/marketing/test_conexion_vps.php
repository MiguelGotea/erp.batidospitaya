<?php
/**
 * Test de conexión al VPS WhatsApp
 */

header('Content-Type: text/html; charset=utf-8');

$vpsUrl = 'http://pitaya-wa.mooo.com';

echo "<h2>Test de Conexión al VPS WhatsApp</h2>";
echo "<p>URL: " . htmlspecialchars($vpsUrl) . "</p>";
echo "<hr>";
// Test cURL
echo "<h3>Test cURL</h3>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $vpsUrl . '/health',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true
]);

$startTime = microtime(true);
$response = curl_exec($ch);
$endTime = microtime(true);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$duration = round(($endTime - $startTime) * 1000, 2);

if ($response && $httpCode === 200) {
    echo "<p style='color:green;'>✅ ÉXITO ({$duration}ms)</p>";
    echo "<p>Respuesta: <code>" . htmlspecialchars($response) . "</code></p>";
} else {
    echo "<p style='color:red;'>❌ ERROR ({$duration}ms)</p>";
    echo "<p>HTTP Code: {$httpCode}</p>";
    echo "<p>Error: " . htmlspecialchars($error) . "</p>";
}

echo "<hr>";
echo "<p>Test completado: " . date('Y-m-d H:i:s') . "</p>";
?>