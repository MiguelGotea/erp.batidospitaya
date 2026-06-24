<?php
require_once '../../../core/database/conexion.php';

try {
    $stmt = $conn->query("SELECT MAX(fecha_fin) as max_date, MIN(fecha_inicio) as min_date FROM SemanasSistema");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Date Range in SemanasSistema:\n";
    print_r($res);

    $sql = "SELECT numero_semana, anio, fecha_inicio, fecha_fin FROM SemanasSistema WHERE '2026-06-25' BETWEEN fecha_inicio AND fecha_fin";
    $stmt2 = $conn->query($sql);
    echo "\nWeek for 2026-06-25:\n";
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
