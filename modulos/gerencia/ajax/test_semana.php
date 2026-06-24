<?php
require_once '../../../core/auth/auth.php'; // Will this work in CLI? Probably not if it checks sessions aggressively.
// Let's just bypass auth for the test if possible, or just mock $conn.
// Actually, let's just include the DB connection directly if we can find it.
// Where is DB connection?
$configPath = '../../../core/config/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // try another path
    require_once '../../../core/database/db_connect.php'; // common name
}
// Try to see if $conn exists
if (!isset($conn)) {
    echo "No conn\n";
    exit;
}

try {
    $sqlSemanaActual = "SELECT numero_semana, anio FROM SemanasSistema WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE() LIMIT 1";
    $stmtActual = $conn->query($sqlSemanaActual);
    $semanaActualData = $stmtActual ? $stmtActual->fetch(PDO::FETCH_ASSOC) : null;
    echo "Actual: "; print_r($semanaActualData);

    $sqlSemana = "SELECT numero_semana, anio, fecha_inicio, fecha_fin FROM SemanasSistema LIMIT 5";
    $stmt = $conn->query($sqlSemana);
    echo "Semanas: "; print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
