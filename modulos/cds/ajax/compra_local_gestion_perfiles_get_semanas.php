<?php
// ajax/compra_local_gestion_perfiles_get_semanas.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    // Obtener las últimas 2 semanas y las próximas 4 semanas para referencia
    $sql = "SELECT numero_semana, fecha_inicio, anio 
            FROM SemanasSistema 
            WHERE fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            ORDER BY numero_semana ASC 
            LIMIT 10";
    $stmt = $conn->query($sql);
    $semanas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'semanas' => $semanas
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
