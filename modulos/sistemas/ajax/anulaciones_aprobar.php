<?php
/**
 * erp/modulos/sistemas/ajax/anulaciones_aprobar.php
 * Aprueba o rechaza una solicitud de anulación desde el panel web.
 *
 * POST JSON:
 *   cod_anulacion_host : ID del registro en AnulacionPedidosHost
 *   accion             : "aprobar" | "rechazar"
 *   comentario         : Comentario de la aprobación/rechazo
 *   aprobado_por       : Nombre o usuario quien aprueba
 */

require_once __DIR__ . '/../../../../api.batidospitaya.com/core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$rawBody = file_get_contents('php://input');
$body    = json_decode($rawBody, true) ?? [];

// También acepta POST form
$codAnulacion = (int)($body['cod_anulacion_host'] ?? $_POST['cod_anulacion_host'] ?? 0);
$accion       = trim($body['accion']       ?? $_POST['accion']       ?? '');
$comentario   = trim($body['comentario']   ?? $_POST['comentario']   ?? '');
$aprobadoPor  = trim($body['aprobado_por'] ?? $_POST['aprobado_por'] ?? 'Sistema');

if ($codAnulacion < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'cod_anulacion_host inválido.']);
    exit();
}

if (!in_array($accion, ['aprobar', 'rechazar'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción inválida. Use "aprobar" o "rechazar".']);
    exit();
}

/** @var PDO $pdo */
global $conn;
$pdo = $conn;

try {
    // Verificar que exista y esté pendiente
    $stmtCheck = $pdo->prepare(
        "SELECT CodAnulacionHost, CodPedido, Sucursal, Status
         FROM AnulacionPedidosHost
         WHERE CodAnulacionHost = :id
         LIMIT 1"
    );
    $stmtCheck->execute([':id' => $codAnulacion]);
    $row = $stmtCheck->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => "Registro #$codAnulacion no encontrado."]);
        exit();
    }

    if ((int)$row['Status'] === 1) {
        echo json_encode(['success' => false, 'error' => 'Esta solicitud ya fue resuelta.']);
        exit();
    }

    // Determinar nuevo status
    // Para aprobar: Status=1 (Access ejecutará la anulación y luego EjecutadoEnTienda=1)
    // Para rechazar: Status=2 (rechazado, no se ejecuta)
    $nuevoStatus = ($accion === 'aprobar') ? 1 : 2;

    $stmtUpd = $pdo->prepare(
        "UPDATE AnulacionPedidosHost
         SET Status               = :st,
             ComentarioAprobacion = :com,
             AprobadoPor          = :por,
             FechaAprobacion      = NOW()
         WHERE CodAnulacionHost = :id"
    );
    $stmtUpd->execute([
        ':st'  => $nuevoStatus,
        ':com' => $comentario ?: null,
        ':por' => $aprobadoPor,
        ':id'  => $codAnulacion,
    ]);

    $label = $accion === 'aprobar' ? 'Aprobada' : 'Rechazada';
    echo json_encode([
        'success'  => true,
        'message'  => "Solicitud #$codAnulacion $label correctamente.",
        'nuevo_status' => $nuevoStatus,
        'cod_pedido'   => $row['CodPedido'],
        'sucursal'     => $row['Sucursal'],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
