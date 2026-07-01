<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('plan_despacho_global', 'edicion', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'Sin permiso de edición']);
    exit();
}

$codSucursal = $_POST['cod_sucursal'] ?? '';
$idPP = $_POST['id_producto_presentacion'] ?? '';
$stockG = isset($_POST['stock_minimo_unidades']) ? (float)$_POST['stock_minimo_unidades'] : 0;

if (empty($codSucursal) || empty($idPP)) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit();
}

try {
    $sql = "INSERT INTO configuracion_logistica_stock_producto 
            (cod_sucursal, id_producto_presentacion, stock_minimo_unidades, modificado_por)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            stock_minimo_unidades = VALUES(stock_minimo_unidades),
            modificado_por = VALUES(modificado_por)";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$codSucursal, $idPP, $stockG, $usuario['IdColaborador']]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
