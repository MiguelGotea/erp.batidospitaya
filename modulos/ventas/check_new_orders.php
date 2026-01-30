<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

$ultimoId = $_POST['ultimo_id'] ?? 0;
$sucursalId = $_POST['sucursal_id'] ?? null;
$estado = $_POST['estado'] ?? '';
$fecha = $_POST['fecha'] ?? date('Y-m-d');

try {
    // Construir consulta similar a la del index
    $where = ["v.id > ?"];
    $params = [$ultimoId];
    
    if (!empty($sucursalId)) {
        $where[] = "v.sucursal_id = ?";
        $params[] = $sucursalId;
    }
    
    if (!empty($estado)) {
        $where[] = "v.estado = ?";
        $params[] = $estado;
    }
    
    if (!empty($fecha)) {
        $where[] = "DATE(v.fecha_hora) = ?";
        $params[] = $fecha;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $where);
    
    $query = "SELECT COUNT(*) as nuevos FROM ventas v $whereClause";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'nuevos' => $result['nuevos']]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos']);
}