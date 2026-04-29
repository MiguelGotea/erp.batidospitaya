<?php
require 'conexion.php';
$stmt = $conn->query('SHOW COLUMNS FROM ventas_meta');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $conn->query('SELECT * FROM ventas_meta LIMIT 1');
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
echo "\n====\n";
$stmt3 = $conn->query('SHOW COLUMNS FROM sucursales');
print_r($stmt3->fetchAll(PDO::FETCH_ASSOC));
?>