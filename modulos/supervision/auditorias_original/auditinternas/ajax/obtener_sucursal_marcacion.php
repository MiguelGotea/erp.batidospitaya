<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';

header('Content-Type: application/json');

// Verificar autenticación básica del usuario actual
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$operario_id = isset($_GET['operario_id']) ? intval($_GET['operario_id']) : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';

if ($operario_id <= 0 || empty($fecha)) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit();
}

try {
    // Usar la conexión PDO global de /core/database/conexion.php ($conn)
    $stmt = $conn->prepare("
        SELECT sucursal_codigo 
        FROM marcaciones 
        WHERE CodOperario = ? 
          AND fecha = ? 
        ORDER BY hora_ingreso DESC 
        LIMIT 1
    ");
    $stmt->execute([$operario_id, $fecha]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode([
            'success' => true,
            'sucursal_codigo' => intval($row['sucursal_codigo'])
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró marcación para esta fecha'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
