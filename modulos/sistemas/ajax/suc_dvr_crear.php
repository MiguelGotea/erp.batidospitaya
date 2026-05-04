<?php
/**
 * suc_dvr_crear.php
 * POST: Crea un registro vacío en DVR_Sucursales para una sucursal que no tiene
 * Permiso requerido: configuracion_sucursales > edicion
 *
 * Recibe (POST):
 *   cod_sucursal  int
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    if (!tienePermiso('configuracion_sucursales', 'edicion', $cargoOperario)) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso de edición']);
        exit;
    }

    $codSucursal = isset($_POST['cod_sucursal']) ? (int)$_POST['cod_sucursal'] : 0;

    if ($codSucursal <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de sucursal inválido']);
        exit;
    }

    // Verificar que la sucursal existe y obtener su nombre
    $stmtSuc = $conn->prepare("SELECT id, nombre FROM sucursales WHERE id = :id LIMIT 1");
    $stmtSuc->execute([':id' => $codSucursal]);
    $sucursal = $stmtSuc->fetch(PDO::FETCH_ASSOC);

    if (!$sucursal) {
        echo json_encode(['success' => false, 'message' => 'Sucursal no encontrada']);
        exit;
    }

    // Verificar que NO existe ya un registro DVR para esta sucursal
    $stmtCheck = $conn->prepare("SELECT cod_sucursal FROM DVR_Sucursales WHERE cod_sucursal = :id LIMIT 1");
    $stmtCheck->execute([':id' => $codSucursal]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya existe una configuración DVR para esta sucursal']);
        exit;
    }

    // Insertar registro vacío (solo cod y nombre)
    $sqlInsert = "
        INSERT INTO DVR_Sucursales (cod_sucursal, nombre_sucursal, canal_caja, tunel_activo)
        VALUES (:cod, :nombre, 0, 0)
    ";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->execute([
        ':cod'    => $codSucursal,
        ':nombre' => $sucursal['nombre']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Configuración DVR creada correctamente',
        'dvr' => [
            'cod_sucursal'   => $codSucursal,
            'nombre_sucursal'=> $sucursal['nombre'],
            'modelo'         => null,
            'marca'          => null,
            'serial'         => null,
            'clave_dispositivo' => null,
            'portal_ip_local'   => null,
            'portal_usuario'    => null,
            'portal_clave'      => null,
            'url_imagen'        => null,
            'capacidad'         => null,
            'canal_caja'        => 0,
            'puerto_rtsp_vps'   => null,
            'tunel_activo'      => 0
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en suc_dvr_crear.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al crear DVR: ' . $e->getMessage()]);
}
?>
