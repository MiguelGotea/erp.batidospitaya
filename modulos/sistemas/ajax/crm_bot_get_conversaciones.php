<?php
/**
 * ajax/crm_bot_get_conversaciones.php
 * Proxy ERP → API para obtener conversaciones del CRM
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('crm_bot', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permiso']);
    exit;
}

// Pasar params de la UI a la API directamente
$instancia = $_GET['instancia'] ?? 'wsp-crmbot';
$status = $_GET['status'] ?? 'all';
$q = $_GET['q'] ?? '';
$page = $_GET['page'] ?? 1;
$per_page = $_GET['per_page'] ?? 25;

$token = 'c5b155ba8f6877a2eefca0183ab18e37fe9a6accde340cf5c88af724822cbf50';
$params = http_build_query(['instancia' => $instancia, 'status' => $status, 'q' => $q, 'page' => $page, 'per_page' => $per_page]);

$ctx = stream_context_create([
    'http' => [
        'header' => "X-WSP-Token: {$token}\r\nAccept: application/json\r\n",
        'timeout' => 10
    ]
]);

$raw = @file_get_contents("https://api.batidospitaya.com/api/crm/get_conversaciones.php?{$params}", false, $ctx);
echo $raw ?: json_encode(['success' => false, 'error' => 'No se pudo conectar a la API']);
