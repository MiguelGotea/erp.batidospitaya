<?php
/**
 * ajax/crm_bot_get_status.php
 * Proxy → status.php?instancia=wsp-crmbot (igual a campanas)
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');
$usuario = obtenerUsuarioActual();
if (!tienePermiso('crm_bot', 'vista', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    exit;
}

$instancia = $_GET['instancia'] ?? 'wsp-crmbot';
$ctx = stream_context_create(['http' => ['timeout' => 8]]);
$raw = @file_get_contents("https://api.batidospitaya.com/api/wsp/status.php?instancia={$instancia}", false, $ctx);
echo $raw ?: json_encode(['estado' => 'desconectado', 'numero' => null]);
