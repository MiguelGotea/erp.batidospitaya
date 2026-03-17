<?php
/**
 * Obtener datos de cuenta y banco de un proveedor
 * Ubicación: /modulos/compras/ajax/reembolsos_get_proveedor_data.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $id_proveedor = isset($_GET['id_proveedor']) ? (int)$_GET['id_proveedor'] : 0;

    if (!$id_proveedor) {
        throw new Exception('ID de proveedor no proporcionado.');
    }

    // Buscar cuenta principal
    $stmt = $conn->prepare("
        SELECT id, banco, numero_cuenta, moneda, titular 
        FROM cuenta_proveedor 
        WHERE id_proveedor = ? 
        ORDER BY principal DESC, id DESC 
        LIMIT 1
    ");
    $stmt->execute([$id_proveedor]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
