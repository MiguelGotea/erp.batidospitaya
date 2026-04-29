<?php
// PHP Script to bulk update 'cobrado' to 1 for records before 2026-03-12
// Self-contained

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

$cutoff_date = '2026-03-12';

// 1. auditoria_facturacion
echo "Updating auditoria_facturacion... ";
$n = $db->exec("UPDATE auditoria_facturacion SET cobrado = 1 WHERE DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) <= '$cutoff_date'");
echo "$n records updated.\n";

// 2. auditoria_caja_chica
echo "Updating auditoria_caja_chica... ";
$n = $db->exec("UPDATE auditoria_caja_chica SET cobrado = 1 WHERE DATE(DATE_SUB(fecha_hora_regsys, INTERVAL 6 HOUR)) <= '$cutoff_date'");
echo "$n records updated.\n";

// 3. auditoria_inventario_operarios (joining parent)
echo "Updating auditoria_inventario_operarios... ";
$n = $db->exec("
    UPDATE auditoria_inventario_operarios aio
    JOIN auditoria_inventario ai ON aio.auditoria_id = ai.id
    SET aio.cobrado = 1 
    WHERE DATE(DATE_SUB(ai.fecha_hora_regsys, INTERVAL 6 HOUR)) <= '$cutoff_date'
");
echo "$n records updated.\n";

// 4. faltante_inventario_operarios (joining parent)
echo "Updating faltante_inventario_operarios... ";
$n = $db->exec("
    UPDATE faltante_inventario_operarios fio
    JOIN faltante_inventario fi ON fio.faltante_id = fi.id
    SET fio.cobrado = 1 
    WHERE DATE(DATE_SUB(fi.fecha_hora_regsys, INTERVAL 6 HOUR)) <= '$cutoff_date'
");
echo "$n records updated.\n";

// 5. faltante_danos_operarios (joining parent)
echo "Updating faltante_danos_operarios... ";
$n = $db->exec("
    UPDATE faltante_danos_operarios fdo
    JOIN faltante_danos fd ON fdo.faltante_id = fd.id
    SET fdo.cobrado = 1 
    WHERE DATE(DATE_SUB(fd.fecha_hora_regsys, INTERVAL 6 HOUR)) <= '$cutoff_date'
");
echo "$n records updated.\n";

// 6. faltante_caja (uses 'fecha' column directly)
echo "Updating faltante_caja... ";
$n = $db->exec("UPDATE faltante_caja SET cobrado = 1 WHERE DATE(fecha) <= '$cutoff_date'");
echo "$n records updated.\n";

echo "Done bulk update.\n";
?>
