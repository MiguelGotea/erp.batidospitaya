<?php
/* ============================================================
   AJAX: Operaciones de Porcentajes de Inventario
   Ruta: modulos/inventario/ajax/porcentajes_operaciones.php
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargo = $usuario['CodNivelesCargos'];
$idOperario = $usuario['CodOperario'];

$accion = $_REQUEST['accion'] ?? '';

try {
    if ($accion === 'get_lista') {
        if (!tienePermiso('porcentajes_inventario', 'vista', $cargo)) {
            echo json_encode(['ok' => false, 'msg' => 'Sin permisos para ver esta sección.']);
            exit();
        }
        // Listar todas las sucursales con sus porcentajes configurados
        $sql = "SELECT s.codigo, s.nombre, 
                       COALESCE(c.porcentaje_congelados, 0) as porcentaje_congelados,
                       COALESCE(c.porcentaje_frescos, 0) as porcentaje_frescos
                FROM sucursales s
                LEFT JOIN inventario_configuracion_sucursal c ON s.codigo = c.cod_sucursal
                WHERE s.activa = 1 AND s.sucursal = 1
                ORDER BY s.nombre ASC";
        $data = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'data' => $data]);
        exit();
    }

    if ($accion === 'save') {
        // Permisos de edición: 27, 16, 55
        if (!tienePermiso('porcentajes_inventario', 'edicion', $cargo)) {
            echo json_encode(['ok' => false, 'msg' => 'Sin permisos para editar porcentajes.']);
            exit();
        }

        $codSucursal = $_POST['cod_sucursal'] ?? '';
        $pCongelados = (float)($_POST['porcentaje_congelados'] ?? 0);
        $pFrescos = (float)($_POST['porcentaje_frescos'] ?? 0);

        if (empty($codSucursal)) throw new Exception("Código de sucursal requerido.");

        $sql = "INSERT INTO inventario_configuracion_sucursal (cod_sucursal, porcentaje_congelados, porcentaje_frescos, creado_por)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    porcentaje_congelados = VALUES(porcentaje_congelados),
                    porcentaje_frescos = VALUES(porcentaje_frescos),
                    modificado_por = ?,
                    fecha_actualizacion = CURRENT_TIMESTAMP";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$codSucursal, $pCongelados, $pFrescos, $idOperario, $idOperario]);

        echo json_encode(['ok' => true, 'msg' => 'Configuración guardada correctamente.']);
        exit();
    }

    throw new Exception("Acción no válida.");

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
