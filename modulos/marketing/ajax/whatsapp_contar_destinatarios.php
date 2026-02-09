<?php
/**
 * AJAX: Contar destinatarios según segmento
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');

try {
    $segmento = $_GET['segmento'] ?? 'todos';
    $sucursal = $_GET['sucursal'] ?? '';

    $sql = "
        SELECT COUNT(*) FROM clientesclub 
        WHERE celular IS NOT NULL 
          AND celular != ''
          AND LENGTH(REPLACE(REPLACE(celular, ' ', ''), '-', '')) >= 8
    ";

    $params = [];

    switch ($segmento) {
        case 'sucursal':
            if (!empty($sucursal)) {
                $sql .= " AND nombre_sucursal = ?";
                $params[] = $sucursal;
            }
            break;
        // Otros segmentos se pueden agregar aquí
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'total' => $total
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'total' => 0
    ]);
}