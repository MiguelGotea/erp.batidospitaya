<?php
/**
 * suc_get_detalle.php
 * GET ?id=X : Detalle completo de una sucursal + datos DVR
 * Permiso requerido: configuracion_sucursales > vista
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('configuracion_sucursales', 'vista', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso de acceso']);
        exit;
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    // Sucursal principal
    $sqlSuc = "
        SELECT
            id, codigo, nombre, ip_direccion, telefono, whatsapp,
            Fecha_Apertura, Fecha_Cierre, departamento, cod_departamento,
            email, activa, sucursal, viatico_nocturno,
            Latitude, Longitude, cod_googlebusiness,
            VMTAP, cookie_token, pos_cookie_token,
            fecha_hora_regsys
        FROM sucursales
        WHERE id = :id
        LIMIT 1
    ";
    $stmtSuc = $conn->prepare($sqlSuc);
    $stmtSuc->execute([':id' => $id]);
    $sucursal = $stmtSuc->fetch(PDO::FETCH_ASSOC);

    if (!$sucursal) {
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada']);
        exit;
    }

    $sqlDvr = "
        SELECT
            cod_sucursal, nombre_sucursal, modelo, marca, serial,
            clave_dispositivo, portal_ip_local, portal_usuario, portal_clave,
            url_imagen, capacidad, canal_caja, puerto_rtsp_vps, tunel_activo
        FROM DVR_Sucursales
        WHERE cod_sucursal = :codigo
        LIMIT 1
    ";
    $stmtDvr = $conn->prepare($sqlDvr);
    $stmtDvr->execute([':codigo' => $sucursal['codigo']]);
    $dvr = $stmtDvr->fetch(PDO::FETCH_ASSOC);

    // Castear tipos sucursal
    $sucursal['id']              = (int)$sucursal['id'];
    $sucursal['activa']          = (int)$sucursal['activa'];
    $sucursal['sucursal']        = (int)$sucursal['sucursal'];
    $sucursal['VMTAP']           = (int)$sucursal['VMTAP'];
    $sucursal['cod_departamento']= (int)$sucursal['cod_departamento'];
    $sucursal['viatico_nocturno']= (int)$sucursal['viatico_nocturno'];
    $sucursal['Latitude']        = $sucursal['Latitude']  !== null ? (float)$sucursal['Latitude']  : null;
    $sucursal['Longitude']       = $sucursal['Longitude'] !== null ? (float)$sucursal['Longitude'] : null;

    // Castear tipos DVR
    if ($dvr) {
        $dvr['cod_sucursal']   = (int)$dvr['cod_sucursal'];
        $dvr['canal_caja']     = (int)$dvr['canal_caja'];
        $dvr['puerto_rtsp_vps']= $dvr['puerto_rtsp_vps'] !== null ? (int)$dvr['puerto_rtsp_vps'] : null;
        $dvr['tunel_activo']   = (int)$dvr['tunel_activo'];
    }

    echo json_encode([
        'success'  => true,
        'sucursal' => $sucursal,
        'dvr'      => $dvr ?: null,
        'tiene_dvr'=> $dvr ? true : false
    ]);

} catch (Exception $e) {
    error_log("Error en suc_get_detalle.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar detalle']);
}
?>
