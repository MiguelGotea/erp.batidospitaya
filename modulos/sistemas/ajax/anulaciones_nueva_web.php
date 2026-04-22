<?php
/**
 * ajax/anulaciones_nueva_web.php
 * Crea una solicitud de anulación directamente desde el ERP (sin Access).
 * La inserta directamente en AnulacionPedidosHost con Status=1 (aprobada de inmediato),
 * para que Access la detecte en su próximo ciclo y ejecute la anulación local.
 *
 * POST JSON:
 *   cod_pedido    : Número de pedido a anular (requerido)
 *   sucursal      : Código de sucursal (requerido)
 *   motivo        : Motivo de la anulación (requerido)
 *   aprobado_por  : Nombre del usuario ERP que la genera
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('aprobacion_pedidos_access_host', 'aprobar', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso para crear anulaciones.']);
    exit();
}

$rawBody   = file_get_contents('php://input');
$body      = json_decode($rawBody, true) ?? [];

$codPedido   = (int)($body['cod_pedido']   ?? 0);
$sucursal    = (int)($body['sucursal']     ?? 0);
$motivo      = trim($body['motivo']        ?? '');
$aprobadoPor = trim($body['aprobado_por']  ?? $usuario['Nombre'] . ' ' . $usuario['Apellido']);

if ($codPedido < 1 || $sucursal < 1 || $motivo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Campos requeridos: cod_pedido, sucursal, motivo.']);
    exit();
}

global $conn;
$pdo = $conn;

try {
    // Verificar que el pedido existe en VentasGlobalesAccessCSV
    $stmtVer = $pdo->prepare(
        "SELECT COUNT(*) FROM VentasGlobalesAccessCSV
         WHERE CodPedido = :cod
         LIMIT 1"
    );
    $stmtVer->execute([':cod' => $codPedido]);
    if ((int)$stmtVer->fetchColumn() === 0) {
        echo json_encode(['success' => false, 'error' => "Pedido #$codPedido no encontrado en el historial de ventas."]);
        exit();
    }

    // Verificar que no exista ya una solicitud para este pedido + sucursal
    $stmtCheck = $pdo->prepare(
        "SELECT CodAnulacionHost, Status FROM AnulacionPedidosHost
         WHERE CodPedido = :cod AND Sucursal = :suc
         LIMIT 1"
    );
    $stmtCheck->execute([':cod' => $codPedido, ':suc' => $sucursal]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $st = (int)$existing['Status'];
        $label = $st === 0 ? 'pendiente' : ($st === 1 ? 'aprobada' : 'rechazada');
        echo json_encode(['success' => false, 'error' => "Ya existe una solicitud $label para el pedido #$codPedido (ID #{$existing['CodAnulacionHost']})."]);
        exit();
    }

    // Insertar la solicitud directamente con Status=1 (aprobada desde web)
    // EjecutadoEnTienda=0 → Access la detectará y ejecutará la anulación
    $stmtIns = $pdo->prepare(
        "INSERT INTO AnulacionPedidosHost
         (CodPedido, HoraSolicitada, Status, Modalidad, CodPedidoCambio,
          Motivo, CodMotivoAnulacion, Sucursal, FechaUltimoSync,
          EjecutadoEnTienda, ComentarioAprobacion, AprobadoPor, FechaAprobacion)
         VALUES
         (:cod, NOW(), 1, 2, 0,
          :motivo, NULL, :suc, NOW(),
          0, 'Anulación solicitada desde ERP Web', :aprobpor, NOW())"
    );
    $stmtIns->execute([
        ':cod'      => $codPedido,
        ':motivo'   => $motivo,
        ':suc'      => $sucursal,
        ':aprobpor' => $aprobadoPor,
    ]);

    $nuevoId = $pdo->lastInsertId();

    echo json_encode([
        'success'          => true,
        'message'          => "Solicitud de anulación creada (#$nuevoId). Access la ejecutará en el próximo ciclo.",
        'cod_anulacion'    => $nuevoId,
        'cod_pedido'       => $codPedido,
        'sucursal'         => $sucursal,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
