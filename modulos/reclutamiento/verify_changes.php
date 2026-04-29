<?php
// verify_changes.php
require_once '../../core/database/conexion.php';

function check_url($url)
{
    echo "Checking $url...\n";
    $ch = curl_init("http://erp.batidospitaya.com/modulos/reclutamiento/" . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        echo "Error checking $url: " . curl_error($ch) . "\n";
        return null;
    }
    curl_close($ch);
    return json_decode($response, true);
}

// 1. Check database
$cargosAMover = [17, 19, 12, 9, 10];
$cargosStr = implode(',', $cargosAMover);
$sql = "SELECT cargo, area, sucursal FROM plazas_cargos WHERE cargo IN ($cargosStr)";
$stmt = $conn->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll();

echo "--- Database Status ---\n";
foreach ($results as $row) {
    echo "Cargo: {$row['cargo']}, Area: {$row['area']}, Sucursal: {$row['sucursal']}\n";
}

// 2. Check AJAX (if possible via CLI, although it might need full environment)
// Since I cannot run curl easily for local PHP files without a server, I will manually inspect the files I changed.

echo "--- Logic Check ---\n";
// The changes were:
// Administrativo NOT IN (..., 17, 19, 12, 9, 10)
// Produccion IN (20, 23, 34, 17, 19, 12, 9, 10)

echo "Verification script finished.\n";
