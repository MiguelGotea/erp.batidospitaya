<?php
require_once '../../includes/auth.php';
require_once '../../includes/conexion.php';

header('Content-Type: application/json');

if (!isset($_POST['empresa_id'])) {
    echo json_encode(['success' => false, 'error' => 'Empresa no proporcionada']);
    exit();
}

$empresa_id = intval($_POST['empresa_id']);
$distancia = isset($_POST['distancia']) ? floatval($_POST['distancia']) : 0;

// Primero obtenemos los datos completos de la empresa
$stmt = $conn->prepare("SELECT 
    costo_primer_envio, 
    costo_regular, 
    costo_mayor_distancia,
    distancia_limite_km
    FROM servicios_delivery WHERE id = ?");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    echo json_encode(['success' => false, 'error' => 'Empresa no encontrada']);
    exit();
}

$cargos = [];

// Determinar qué cargo aplicar basado en la distancia
if ($empresa['distancia_limite_km'] && $distancia > $empresa['distancia_limite_km'] && $empresa['costo_mayor_distancia'] !== null) {
    // Aplicar cargo por mayor distancia
    $cargos[] = [
        'valor' => $empresa['costo_mayor_distancia'],
        'nombre' => 'Mayor distancia',
        'descripcion' => 'Más de '.$empresa['distancia_limite_km'].' km'
    ];
} elseif ($empresa['costo_regular'] !== null) {
    // Aplicar cargo regular
    $cargos[] = [
        'valor' => $empresa['costo_regular'],
        'nombre' => 'Costo regular',
        'descripcion' => $empresa['distancia_limite_km'] ? 'Hasta '.$empresa['distancia_limite_km'].' km' : null
    ];
}

// Siempre incluir el primer envío como opción si está definido
if ($empresa['costo_primer_envio'] !== null) {
    $cargos[] = [
        'valor' => $empresa['costo_primer_envio'],
        'nombre' => 'Primer envío',
        'descripcion' => null
    ];
}

echo json_encode(['success' => true, 'cargos' => $cargos]);
?>