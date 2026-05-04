<?php
/**
 * suc_dvr_guardar_campo.php
 * POST: Auto-save de un campo individual de DVR_Sucursales
 * Permiso requerido: configuracion_sucursales > edicion
 *
 * Recibe (POST):
 *   cod_sucursal  int
 *   campo         string  (whitelist estricta)
 *   valor         mixed
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

// ── Whitelist estricta de campos DVR editables ──────────────
$CAMPOS_DVR_PERMITIDOS = [
    'nombre_sucursal'  => 'string',
    'modelo'           => 'string',
    'marca'            => 'string',
    'serial'           => 'string',
    'clave_dispositivo'=> 'string',
    'portal_ip_local'  => 'string',
    'portal_usuario'   => 'string',
    'portal_clave'     => 'string',
    'url_imagen'       => 'string',
    'capacidad'        => 'string',
    'canal_caja'       => 'int',
    'puerto_rtsp_vps'  => 'int_nullable',
    'tunel_activo'     => 'bool',
];

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('configuracion_sucursales', 'edicion', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso de edición']);
        exit;
    }

    $codSucursal = isset($_POST['cod_sucursal']) ? (int)$_POST['cod_sucursal'] : 0;
    $campo       = isset($_POST['campo'])         ? trim($_POST['campo'])       : '';
    $valor       = isset($_POST['valor'])         ? $_POST['valor']             : null;

    if ($codSucursal <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de sucursal inválido']);
        exit;
    }

    if (!array_key_exists($campo, $CAMPOS_DVR_PERMITIDOS)) {
        echo json_encode(['success' => false, 'message' => "Campo DVR '$campo' no permitido"]);
        exit;
    }

    // Castear valor según tipo
    $tipo = $CAMPOS_DVR_PERMITIDOS[$campo];
    switch ($tipo) {
        case 'int':
            $valor = $valor !== '' && $valor !== null ? (int)$valor : 0;
            break;
        case 'int_nullable':
            $valor = $valor !== '' && $valor !== null ? (int)$valor : null;
            break;
        case 'bool':
            $valor = in_array($valor, [1, '1', true, 'true', 'on'], true) ? 1 : 0;
            break;
        case 'string':
        default:
            $valor = $valor !== null ? trim((string)$valor) : null;
            if ($valor === '') $valor = null;
            break;
    }

    // Verificar que existe el registro DVR
    $stmtCheck = $conn->prepare("SELECT cod_sucursal FROM DVR_Sucursales WHERE cod_sucursal = :id LIMIT 1");
    $stmtCheck->execute([':id' => $codSucursal]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'No existe configuración DVR para esta sucursal']);
        exit;
    }

    // UPDATE seguro (campo whitelisted)
    $sql = "UPDATE DVR_Sucursales SET `$campo` = :valor WHERE cod_sucursal = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':valor' => $valor, ':id' => $codSucursal]);

    echo json_encode([
        'success' => true,
        'message' => 'DVR actualizado correctamente',
        'campo'   => $campo,
        'valor'   => $valor
    ]);

} catch (Exception $e) {
    error_log("Error en suc_dvr_guardar_campo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar DVR: ' . $e->getMessage()]);
}
?>
