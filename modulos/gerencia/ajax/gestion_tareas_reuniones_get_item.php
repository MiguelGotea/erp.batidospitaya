<?php
/**
 * Obtener detalles de un item específico
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $id = intval($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    $sql = "SELECT 
                i.*,
                nc.Nombre as nombre_cargo_asignado,
                -- Lógica de avatar: Si es reunión, el creador. Si es tarea, el asignado.
                CASE 
                    WHEN i.tipo = 'reunion' THEN o_creador.foto_perfil
                    ELSE o_asignado.foto_perfil
                END as avatar_url,
                CASE 
                    WHEN i.tipo = 'reunion' THEN CONCAT(o_creador.Nombre, ' ', o_creador.Apellido)
                    ELSE COALESCE(CONCAT(o_asignado.Nombre, ' ', o_asignado.Apellido), nc.Nombre)
                END as nombre_responsable,
                -- Info adicional del creador
                o_creador.foto_perfil as avatar_creador,
                CONCAT(o_creador.Nombre, ' ', o_creador.Apellido) as nombre_creador,
                o_creador.Apellido as apellido_creador
            FROM gestion_tareas_reuniones_items i
            LEFT JOIN NivelesCargos nc ON i.cod_cargo_asignado = nc.CodNivelesCargos
            LEFT JOIN Operarios o_creador ON i.cod_operario_creador = o_creador.CodOperario
            LEFT JOIN Operarios o_asignado ON o_asignado.CodOperario = (
                SELECT anc.CodOperario 
                FROM AsignacionNivelesCargos anc 
                WHERE anc.CodNivelesCargos = i.cod_cargo_asignado 
                AND anc.Fecha <= CURDATE() 
                AND (anc.Fin >= CURDATE() OR anc.Fin IS NULL)
                ORDER BY anc.Fecha DESC 
                LIMIT 1
            )
            WHERE i.id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        // Contar subtareas
        $sqlCountSub = "SELECT COUNT(*) FROM gestion_tareas_reuniones_items WHERE id_padre = :id AND tipo = 'subtarea'";
        $stmtSub = $conn->prepare($sqlCountSub);
        $stmtSub->execute([':id' => $id]);
        $item['total_subtareas'] = intval($stmtSub->fetchColumn());

        // Contar comentarios
        $sqlCountCom = "SELECT COUNT(*) FROM gestion_tareas_reuniones_comentarios WHERE id_item = :id";
        $stmtCom = $conn->prepare($sqlCountCom);
        $stmtCom->execute([':id' => $id]);
        $item['total_comentarios'] = intval($stmtCom->fetchColumn());
    }

    if (!$item) {
        throw new Exception('Item no encontrado');
    }

    echo json_encode([
        'success' => true,
        'item' => $item
    ]);

} catch (Exception $e) {
    error_log("Error en get_item: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>