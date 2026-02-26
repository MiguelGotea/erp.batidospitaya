<?php
require_once '../../../core/auth/auth.php';
require_once '../editar_colaborador_componentes/logic/funciones_colaborador.php';

header('Content-Type: application/json');

try {
    verificarAutenticacion();

    $usuario = obtenerUsuarioActual();
    $cargoId = $usuario['CodNivelesCargos'] ?? 0;

    // Verificar permisos
    if (!tienePermiso('editar_colaborador', 'edicion', $cargoId)) {
        throw new Exception('No tiene permisos para realizar esta acción');
    }

    if (!isset($_POST['id_contrato']) || empty($_POST['id_contrato'])) {
        throw new Exception('ID de contrato no especificado');
    }

    $idContrato = $_POST['id_contrato'];

    // Mapear datos del POST para que coincidan con lo que espera terminarContrato
    $datos = [
        'fecha_terminacion' => $_POST['fecha_terminacion'],
        'fecha_liquidacion' => $_POST['fecha_liquidacion'] ?? null,
        'tipo_salida' => $_POST['tipo_salida'],
        'motivo_salida' => $_POST['motivo_salida'],
        'dias_trabajados' => $_POST['dias_trabajados'] ?? 0,
        'monto_indemnizacion' => $_POST['monto_indemnizacion'] ?? 0,
        'devolucion_herramientas' => isset($_POST['devolucion_herramientas']) && $_POST['devolucion_herramientas'] == '1',
        'persona_recibe_herramientas' => $_POST['persona_recibe_herramientas'] ?? ''
    ];

    $resultado = terminarContrato($idContrato, $datos);

    echo json_encode($resultado);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage()
    ]);
}
?>