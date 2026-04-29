<?php
// PHP Script to add 'cobrado' column to audit/deduction tables
// Self-contained to avoid import issues

define('DB_HOST', 'localhost');
define('DB_NAME', 'u839374897_erp');
define('DB_USER', 'u839374897_erp');
define('DB_PASS', 'ERpPitHay2025$');

try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$tables = [
    'auditoria_facturacion',
    'auditoria_caja_chica',
    'auditoria_inventario_operarios',
    'faltante_inventario_operarios',
    'faltante_danos_operarios',
    'faltante_caja'
];

foreach ($tables as $table) {
    echo "Checking table: $table... ";
    try {
        // Check if column exists
        $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE 'cobrado'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `cobrado` TINYINT(1) DEFAULT 0");
            echo "Column 'cobrado' added.\n";
        } else {
            echo "Column 'cobrado' already exists.\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
