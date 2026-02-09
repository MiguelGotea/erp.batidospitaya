<?php
/**
 * AJAX: Obtener lista de campañas
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');

try {
    $estado = $_GET['estado'] ?? '';
    $tipo = $_GET['tipo'] ?? '';

    $sql = "
        SELECT 
            c.*,
            p.nombre as plantilla_nombre
        FROM whatsapp_campanas c
        LEFT JOIN whatsapp_plantillas p ON c.plantilla_id = p.id
        WHERE 1=1
    ";

    $params = [];

    if (!empty($estado)) {
        $sql .= " AND c.estado = ?";
        $params[] = $estado;
    }

    if (!empty($tipo)) {
        $sql .= " AND c.tipo = ?";
        $params[] = $tipo;
    }

    $sql .= " ORDER BY c.fecha_creacion DESC LIMIT 100";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $campanas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'campanas' => $campanas
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}