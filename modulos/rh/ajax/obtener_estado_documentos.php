<?php
ob_start();
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
if (!isset($_GET['cod_operario']) || !isset($_GET['pestaña'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit();
}

$codOperario = intval($_GET['cod_operario']);
$pestaña = $_GET['pestaña'];

// Verificar que el usuario tenga acceso
verificarAutenticacion();

// Definir variables globales necesarias para funciones_colaborador.php
$usuario = obtenerUsuarioActual();
$cargoId = $usuario['CodNivelesCargos'] ?? 0;

require_once '../editar_colaborador_componentes/logic/compliance_logic.php';

ob_clean();
header('Content-Type: application/json');

$cumplimiento = calcularPorcentajeCumplimiento($codOperario, $pestaña);

echo json_encode([
    'estado' => verificarEstadoDocumentosObligatorios($codOperario, $pestaña),
    'porcentaje' => $cumplimiento['porcentaje'],
    'pestaña' => $pestaña,
    'detalles' => $cumplimiento['detalles'] ?? []
]);
?>