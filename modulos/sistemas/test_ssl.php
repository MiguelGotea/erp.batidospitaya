<?php
/**
 * Archivo de prueba para verificar si el servidor recibe el certificado SSL del cliente.
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Prueba de Certificado SSL de Cliente</h1>";

$ssl_vars = [
    'SSL_CLIENT_CERT',
    'SSL_CLIENT_S_DN',
    'SSL_CLIENT_I_DN',
    'SSL_CLIENT_VERIFY',
    'HTTPS',
    'SERVER_PORT',
    'REMOTE_ADDR'
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f2f2f2;'><th>Variable</th><th>Valor</th></tr>";

foreach ($ssl_vars as $var) {
    echo "<tr>";
    echo "<strong><td>$var</td></strong>";
    if (isset($_SERVER[$var])) {
        echo "<td><pre>" . htmlspecialchars($_SERVER[$var]) . "</pre></td>";
    } else {
        echo "<td style='color: red;'><i>No definida</i></td>";
    }
    echo "</tr>";
}

echo "</table>";

echo "<h2>Dumping completo de \$_SERVER (Solo por si acaso):</h2>";
echo "<pre>";
print_r($_SERVER);
echo "</pre>";
?>
