<?php
/**
 * Obtener archivos de un item o comentario
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $idItem = intval($_POST['id_item'] ?? 0);
    $tipoVinculo = $_POST['tipo_vinculo'] ?? 'item';

    if ($idItem <= 0) {
        throw new Exception('ID inválido');
    }

    $sql = "SELECT * FROM gestion_tareas_reuniones_archivos 
            WHERE id_item = :id_item AND tipo_vinculo = :tipo_vinculo
            ORDER BY fecha_subida DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_item' => $idItem,
        ':tipo_vinculo' => $tipoVinculo
    ]);
    $archivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'archivos' => $archivos
    ]);

} catch (Exception $e) {
    error_log("Error en get_archivos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>