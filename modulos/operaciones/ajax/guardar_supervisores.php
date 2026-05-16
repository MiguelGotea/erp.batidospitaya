<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'error' => 'No autenticado.']);
    exit();
}

$cargoOperario = $usuario['CodNivelesCargos'] ?? 0;

// Verificar permiso editar_supervisor
if (!tienePermiso('administracion_colaboradores_lideres', 'editar_supervisor', $cargoOperario)) {
    echo json_encode(['success' => false, 'error' => 'Sin permiso para editar supervisores.']);
    exit();
}

// Leer JSON del body
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!isset($data['cod_sucursal'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos.']);
    exit();
}

$codSucursal = intval($data['cod_sucursal']);
$supervisores = $data['supervisores'] ?? [];

if ($codSucursal <= 0) {
    echo json_encode(['success' => false, 'error' => 'Sucursal inválida.']);
    exit();
}

// Asegurar que sean enteros positivos únicos
$supervisoresLimpios = array_values(array_unique(array_filter(array_map('intval', $supervisores), fn($id) => $id > 0)));

global $conn;

// Verificar que la sucursal existe
$stmtCheck = $conn->prepare("SELECT codigo FROM sucursales WHERE codigo = ? LIMIT 1");
$stmtCheck->execute([$codSucursal]);
if (!$stmtCheck->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Sucursal no encontrada.']);
    exit();
}

// Guardar JSON en supervisor_asignado
$jsonSupervisores = empty($supervisoresLimpios) ? '[]' : json_encode($supervisoresLimpios);

$stmtUpdate = $conn->prepare("UPDATE sucursales SET supervisor_asignado = ? WHERE codigo = ?");
$stmtUpdate->execute([$jsonSupervisores, $codSucursal]);

echo json_encode(['success' => true, 'supervisores' => $supervisoresLimpios]);
