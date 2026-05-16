<?php
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Requiere autenticación básica
$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode([]);
    exit();
}

global $conn;

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode([]);
    exit();
}

// Buscar en toda la tabla Operarios sin filtro adicional — el usuario tecleará lo que quiere
$buscar = '%' . $q . '%';

$stmt = $conn->prepare("
    SELECT CodOperario, Nombre, Apellido, Apellido2
    FROM Operarios
    WHERE CONCAT(Nombre, ' ', Apellido, ' ', COALESCE(Apellido2, '')) LIKE ?
    ORDER BY Nombre, Apellido
    LIMIT 30
");
$stmt->execute([$buscar]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
foreach ($rows as $r) {
    $result[] = [
        'id'     => (int) $r['CodOperario'],
        'nombre' => trim($r['Nombre'] . ' ' . $r['Apellido'] . ' ' . ($r['Apellido2'] ?? ''))
    ];
}

echo json_encode($result);
