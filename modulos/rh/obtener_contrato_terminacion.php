<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

verificarAutenticacion();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID de contrato no proporcionado']);
    exit();
}

$idContrato = intval($_GET['id']);

global $conn;

try {
    $stmt = $conn->prepare("
        SELECT 
            CodContrato,
            fin_contrato,
            fecha_salida,
            fecha_liquidacion,
            cod_tipo_salida,
            motivo,
            dias_trabajados,
            monto_indemnizacion,
            devolucion_herramientas_trabajo,
            persona_recibe_herramientas_trabajo
        FROM Contratos 
        WHERE CodContrato = ?
    ");
    $stmt->execute([$idContrato]);
    $contrato = $stmt->fetch();
    
    if ($contrato) {
        echo json_encode($contrato);
    } else {
        echo json_encode(['error' => 'Contrato no encontrado']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al obtener datos: ' . $e->getMessage()]);
}
?>