<?php
// compra_local_configuracion_despacho_eliminar.php
// Eliminar día de entrega

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        throw new Exception('ID requerido');
    }

    // Eliminar registro
    $sql = "DELETE FROM compra_local_configuracion_despacho WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Día de entrega eliminado correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
