<?php
require_once 'conexion.php';
$result = $conn->query("SELECT NOW() AS hora_mysql, 
                        CONVERT_TZ(NOW(), '+00:00', '-06:00') AS hora_nicaragua,
                        NOW() - INTERVAL 6 HOUR AS hora_manual");
print_r($result->fetch());
echo "Hora PHP: " . date('Y-m-d H:i:s');
?>
