<?php
/**
 * suc_get_lista.php
 * GET: Lista todas las sucursales con join a DVR_Sucursales
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

    $sql = "
        SELECT
            s.id,
            s.codigo,
            s.nombre,
            s.ip_direccion,
            s.ip_impresora,
            s.telefono,
            s.whatsapp,
            s.Fecha_Apertura,
            s.Fecha_Cierre,
            COALESCE(dep.nombre, s.departamento) AS departamento,
            s.cod_departamento,
            s.email,
            s.activa,
            s.sucursal,
            s.viatico_nocturno,
            s.Latitude,
            s.Longitude,
            s.cod_googlebusiness,
            s.VMTAP,
            s.cookie_token,
            s.pos_cookie_token,
            -- DVR
            CASE WHEN d.cod_sucursal IS NOT NULL THEN 1 ELSE 0 END AS tiene_dvr,
            d.modelo       AS dvr_modelo,
            d.marca        AS dvr_marca,
            d.tunel_activo AS dvr_tunel_activo
        FROM sucursales s
        LEFT JOIN DVR_Sucursales d ON s.codigo = d.cod_sucursal
        LEFT JOIN departamentos dep ON s.cod_departamento = dep.codigo
        ORDER BY s.activa DESC, s.nombre ASC
    ";

    $stmt = $conn->query($sql);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Castear tipos
    foreach ($sucursales as &$suc) {
        $suc['id']              = (int)$suc['id'];
        $suc['activa']          = (int)$suc['activa'];
        $suc['sucursal']        = (int)$suc['sucursal'];
        $suc['VMTAP']           = (int)$suc['VMTAP'];
        $suc['tiene_dvr']       = (int)$suc['tiene_dvr'];
        $suc['dvr_tunel_activo']= isset($suc['dvr_tunel_activo']) ? (int)$suc['dvr_tunel_activo'] : 0;
        $suc['cod_departamento']= (int)$suc['cod_departamento'];
        $suc['Latitude']        = $suc['Latitude']  !== null ? (float)$suc['Latitude']  : null;
        $suc['Longitude']       = $suc['Longitude'] !== null ? (float)$suc['Longitude'] : null;
    }
    unset($suc);

    echo json_encode(['success' => true, 'data' => $sucursales]);

} catch (Exception $e) {
    error_log("Error en suc_get_lista.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar sucursales']);
}
?>
