<?php
/**
 * Obtener comentarios de un item
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $idItem = intval($_POST['id_item'] ?? 0);

    if ($idItem <= 0) {
        throw new Exception('ID inválido');
    }

    // Obtener comentarios
    $sql = "SELECT 
                c.*,
                CONCAT(o.Nombre, ' ', o.Apellido) as nombre_operario
            FROM gestion_tareas_reuniones_comentarios c
            INNER JOIN Operarios o ON c.cod_operario = o.CodOperario
            WHERE c.id_item = :id_item
            ORDER BY c.fecha_creacion ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_item' => $idItem]);
    $comentarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener archivos de cada comentario
    foreach ($comentarios as &$comentario) {
        $sqlArchivos = "SELECT * FROM gestion_tareas_reuniones_archivos 
                        WHERE id_comentario = :id_comentario";
        $stmtArchivos = $conn->prepare($sqlArchivos);
        $stmtArchivos->execute([':id_comentario' => $comentario['id']]);
        $comentario['archivos'] = $stmtArchivos->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'comentarios' => $comentarios
    ]);

} catch (Exception $e) {
    error_log("Error en get_comentarios: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>