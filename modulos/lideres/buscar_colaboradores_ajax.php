<?php
require_once '../../core/auth/auth.php';
header('Content-Type: application/json');

// Verificar autenticación

if (!isset($_GET['term']) || strlen($_GET['term']) < 2) {
    echo json_encode([]);
    exit;
}

$termino = $_GET['term'];
$sucursal = $_GET['sucursal'] ?? null;

try {
    global $conn;
    
    $sql = "SELECT o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2 
            FROM Operarios o
            WHERE o.Operativo = 1
            AND (o.Nombre LIKE ? OR o.Apellido LIKE ? OR CONCAT(o.Nombre, ' ', o.Apellido) LIKE ?)
            AND o.CodOperario NOT IN (566, 567, 568, 569, 570, 571, 572, 573, 574, 575, 576, 590)
            ORDER BY o.Nombre, o.Apellido
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $likeTerm = "%$termino%";
    $stmt->execute([$likeTerm, $likeTerm, $likeTerm]);
    
    $colaboradores = [];
    while ($row = $stmt->fetch()) {
        $nombreCompleto = trim($row['Nombre'] . ' ' . $row['Nombre2'] . ' ' . $row['Apellido'] . ' ' . ($row['Apellido2'] ?? ''));
        $colaboradores[] = [
            'id' => $row['CodOperario'],
            'text' => $nombreCompleto . ' (' . $row['CodOperario'] . ')',
            'nombre' => $nombreCompleto,
            'codigo' => $row['CodOperario']
        ];
    }
    
    echo json_encode($colaboradores);
    
} catch (Exception $e) {
    echo json_encode([]);
}