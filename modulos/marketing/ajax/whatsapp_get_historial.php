<?php
/**
 * AJAX: Obtener historial de mensajes
 */

header('Content-Type: application/json');

require_once('../../../core/auth/auth.php');
require_once('../../../core/database/conexion.php');

try {
    $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $estado = $_GET['estado'] ?? '';
    $texto = $_GET['texto'] ?? '';
    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $limite = intval($_GET['limite'] ?? 20);
    $offset = ($pagina - 1) * $limite;

    // Construir consulta
    $sql = "
        SELECT 
            m.*,
            c.nombre as campana_nombre
        FROM whatsapp_mensajes m
        LEFT JOIN whatsapp_campanas c ON m.campana_id = c.id
        WHERE DATE(m.fecha_creacion) BETWEEN ? AND ?
    ";

    $params = [$desde, $hasta];

    if (!empty($estado)) {
        $sql .= " AND m.estado = ?";
        $params[] = $estado;
    }

    if (!empty($texto)) {
        $sql .= " AND (m.nombre_cliente LIKE ? OR m.telefono LIKE ?)";
        $params[] = "%$texto%";
        $params[] = "%$texto%";
    }

    // Contar total
    $sqlCount = str_replace("m.*,\n            c.nombre as campana_nombre", "COUNT(*)", $sql);
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();

    // Obtener registros
    $sql .= " ORDER BY m.fecha_creacion DESC LIMIT $limite OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPaginas = ceil($total / $limite);

    echo json_encode([
        'success' => true,
        'mensajes' => $mensajes,
        'total' => $total,
        'pagina' => $pagina,
        'totalPaginas' => $totalPaginas
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}