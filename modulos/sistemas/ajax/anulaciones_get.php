<?php
/**
 * erp/modulos/sistemas/ajax/anulaciones_get.php
 * Obtiene lista paginada de solicitudes de anulación del host.
 *
 * GET params:
 *   sucursal : filtro de sucursal (0 = todas)
 *   status   : -1=todas, 0=pendientes, 1=resueltas
 *   page     : número de página (default 1)
 *   limit    : registros por página (default 50)
 *   buscar   : texto de búsqueda en CodPedido o Motivo
 */

require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$sucursal = isset($_GET['sucursal']) ? (int)$_GET['sucursal'] : 0;
$status   = isset($_GET['status'])   ? (int)$_GET['status']   : -1;
$page     = max(1, (int)($_GET['page']  ?? 1));
$limit    = min(100, max(10, (int)($_GET['limit'] ?? 50)));
$buscar   = trim($_GET['buscar'] ?? '');
$offset   = ($page - 1) * $limit;

/** @var PDO $pdo */
global $conn;
$pdo = $conn;

try {
    $where  = [];
    $params = [];

    if ($sucursal > 0) {
        $where[]  = 'a.Sucursal = :suc';
        $params[':suc'] = $sucursal;
    }
    if ($status >= 0) {
        $where[]  = 'a.Status = :st';
        $params[':st'] = $status;
    }
    if ($buscar !== '') {
        $where[]  = '(a.CodPedido LIKE :b OR a.Motivo LIKE :b2 OR a.ComentarioAprobacion LIKE :b3)';
        $params[':b']  = '%' . $buscar . '%';
        $params[':b2'] = '%' . $buscar . '%';
        $params[':b3'] = '%' . $buscar . '%';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $stmtCount = $pdo->prepare(
        "SELECT COUNT(*) FROM AnulacionPedidosHost a $whereSQL"
    );
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    // Data
    $stmtData = $pdo->prepare(
        "SELECT a.CodAnulacionHost, a.CodPedido, a.Sucursal,
                a.HoraSolicitada, a.HoraAnulada, a.Status,
                a.Modalidad, a.CodPedidoCambio, a.Motivo,
                a.CodMotivoAnulacion, a.ComentarioAprobacion,
                a.AprobadoPor, a.FechaAprobacion,
                a.EjecutadoEnTienda, a.HoraEjecutadaTienda,
                a.FechaUltimoSync
         FROM AnulacionPedidosHost a
         $whereSQL
         ORDER BY a.Status ASC, a.HoraSolicitada DESC
         LIMIT :lim OFFSET :off"
    );
    $params[':lim'] = $limit;
    $params[':off'] = $offset;
    $stmtData->execute($params);
    $registros = $stmtData->fetchAll();

    echo json_encode([
        'success'   => true,
        'total'     => $total,
        'page'      => $page,
        'limit'     => $limit,
        'paginas'   => (int)ceil($total / $limit),
        'registros' => $registros,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
