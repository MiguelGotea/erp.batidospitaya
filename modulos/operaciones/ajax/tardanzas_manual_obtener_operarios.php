<?php
require_once '../../../core/auth/auth.php';


header('Content-Type: application/json');

// Limpiar cualquier output previo
ob_clean();

$codSucursal    = $_GET['sucursal']       ?? null;
$fechaTardanza  = $_GET['fecha_tardanza'] ?? null;

if (!$codSucursal) {
    echo json_encode([]);
    exit();
}

// Si NO se proporciona fecha, usar fecha de ayer por defecto
if (!$fechaTardanza) {
    $fechaTardanza = date('Y-m-d', strtotime('-1 day'));
}

// Obtener operarios considerando fecha de liquidación
$operarios = obtenerOperariosSucursalParaTardanzasPorFecha($codSucursal, $fechaTardanza);

// Agregar información de contrato para validaciones en frontend
foreach ($operarios as &$operario) {
    $estadoContrato = obtenerMensajeEstadoContrato($operario['CodOperario']);
    $operario['tiene_contrato']    = operarioTieneContrato($operario['CodOperario']);
    $operario['estado_contrato']   = $estadoContrato['tipo'];
    $operario['mensaje_contrato']  = $estadoContrato['mensaje'];
}

echo json_encode($operarios);
exit();

// ─────────────────────────────────────────────────────────────────────────────
// Función local (movida desde tardanzas_manual.php)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Obtiene operarios de una sucursal para registrar tardanzas manuales.
 * Considera fecha específica y fecha de liquidación.
 *
 * @param string|int $codSucursal
 * @param string     $fechaReferencia  (Y-m-d)
 * @return array
 */
function obtenerOperariosSucursalParaTardanzasPorFecha($codSucursal, $fechaReferencia)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT DISTINCT
            o.CodOperario,
            o.Nombre,
            o.Nombre2,
            o.Apellido,
            o.Apellido2,
            c.fecha_liquidacion,
            c.CodContrato
        FROM Operarios o
        INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        INNER JOIN marcaciones m ON o.CodOperario = m.CodOperario
        LEFT JOIN (
            -- Último contrato de cada operario
            SELECT c1.cod_operario, c1.CodContrato, c1.fecha_liquidacion
            FROM Contratos c1
            INNER JOIN (
                SELECT cod_operario, MAX(CodContrato) as max_contrato
                FROM Contratos
                GROUP BY cod_operario
            ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
        ) c ON o.CodOperario = c.cod_operario
        WHERE anc.Sucursal = ?
        AND m.sucursal_codigo = ?
        -- Operarios con marcaciones en los últimos 30 días
        AND m.fecha BETWEEN DATE_SUB(?, INTERVAL 30 DAY) AND ?
        AND m.hora_ingreso IS NOT NULL
        -- Verificar que estaba asignado a la sucursal en esa fecha
        AND anc.Fecha <= ?
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        -- Excluir cargo 27
        AND o.CodOperario NOT IN (
            SELECT DISTINCT anc2.CodOperario
            FROM AsignacionNivelesCargos anc2
            WHERE anc2.CodNivelesCargos = 27
            AND anc2.Fecha <= ?
            AND (anc2.Fin IS NULL OR anc2.Fin >= ?)
        )
        -- Solo operarios activos según fecha de liquidación
        AND (
            c.fecha_liquidacion IS NULL
            OR c.fecha_liquidacion = '0000-00-00'
            OR c.fecha_liquidacion >= ?
        )
        GROUP BY o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2, o.Sucursal
        ORDER BY o.Nombre, o.Apellido, o.Apellido2
    ");

    $stmt->execute([
        $codSucursal,       // anc.Sucursal
        $codSucursal,       // m.sucursal_codigo
        $fechaReferencia,   // DATE_SUB
        $fechaReferencia,   // rango marcaciones fin
        $fechaReferencia,   // anc.Fecha
        $fechaReferencia,   // anc.Fin
        $fechaReferencia,   // cargo 27 Fecha
        $fechaReferencia,   // cargo 27 Fin
        $fechaReferencia,   // fecha_liquidacion
    ]);

    return $stmt->fetchAll();
}
