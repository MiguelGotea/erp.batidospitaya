<?php
require_once '../../core/auth/auth.php';

// Verificar autenticación
verificarAutenticacion();

if (!isset($_GET['cod_operario'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Código de operario no proporcionado']);
    exit();
}

$codOperario = intval($_GET['cod_operario']);

/**
 * Obtiene el último adendum activo de un colaborador
 */
function obtenerUltimoAdendumActivo($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
SELECT anc.*
FROM AsignacionNivelesCargos anc
WHERE anc.CodOperario = ?
AND anc.TipoAdendum IS NOT NULL
AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
ORDER BY anc.Fecha DESC
LIMIT 1
");
    $stmt->execute([$codOperario]);
    return $stmt->fetch();
}

$ultimoAdendum = obtenerUltimoAdendumActivo($codOperario);

if ($ultimoAdendum) {
    echo json_encode([
        'exito' => true,
        'adendum' => [
            'id' => $ultimoAdendum['CodAsignacionNivelesCargos'],
            'TipoAdendum' => $ultimoAdendum['TipoAdendum'],
            'FechaInicio' => $ultimoAdendum['Fecha']
        ]
    ]);
} else {
    echo json_encode([
        'exito' => false,
        'mensaje' => 'No hay adendums activos'
    ]);
}
?>