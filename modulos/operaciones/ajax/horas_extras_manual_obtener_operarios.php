<?php
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

// Clean any previous buffer
ob_clean();

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode([]);
    exit();
}

$codSucursal = $_GET['sucursal'] ?? null;
$fecha = $_GET['fecha'] ?? null;

if (!$codSucursal || !$fecha) {
    echo json_encode([]);
    exit();
}

try {
    global $conn;

    $stmt = $conn->prepare("
        SELECT DISTINCT
            o.CodOperario,
            CONCAT_WS(' ',
                NULLIF(TRIM(o.Nombre),   ''),
                NULLIF(TRIM(o.Nombre2),  ''),
                NULLIF(TRIM(o.Apellido), ''),
                NULLIF(TRIM(o.Apellido2),'')
            ) AS nombre_completo,
            nc.Nombre as cargo_nombre
        FROM Operarios o
        INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        LEFT JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
        LEFT JOIN (
            -- Último contrato de cada operario
            SELECT c1.cod_operario, c1.fecha_liquidacion
            FROM Contratos c1
            INNER JOIN (
                SELECT cod_operario, MAX(CodContrato) as max_contrato
                FROM Contratos
                GROUP BY cod_operario
            ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
        ) c ON o.CodOperario = c.cod_operario
        WHERE anc.Sucursal = ?
          AND anc.Fecha <= ?
          AND (anc.Fin IS NULL OR anc.Fin >= ?)
          -- Excluir cargo 27
          AND (anc.CodNivelesCargos IS NULL OR anc.CodNivelesCargos != 27)
          -- Solo activos según fecha de liquidación
          AND (
              c.fecha_liquidacion IS NULL
              OR c.fecha_liquidacion = '0000-00-00'
              OR c.fecha_liquidacion >= ?
          )
        ORDER BY o.Nombre ASC, o.Apellido ASC
    ");

    $stmt->execute([
        $codSucursal,
        $fecha,
        $fecha,
        $fecha
    ]);

    $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($operarios);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}
