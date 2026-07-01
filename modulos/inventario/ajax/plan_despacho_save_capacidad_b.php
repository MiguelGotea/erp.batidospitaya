<?php
/* ============================================================
   AJAX: plan_despacho_save_capacidad_b.php
   Ruta: modulos/inventario/ajax/plan_despacho_save_capacidad_b.php
   Método: POST
   ============================================================ */
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

$cod_sucursal = trim($_POST['cod_sucursal'] ?? '');
$capacidad_congelados_paquetes = isset($_POST['capacidad_congelados_paquetes']) && $_POST['capacidad_congelados_paquetes'] !== '' ? (int)$_POST['capacidad_congelados_paquetes'] : null;

if (empty($cod_sucursal)) {
    echo json_encode(['success' => false, 'message' => 'cod_sucursal es requerido.']);
    exit();
}

$usuario = obtenerUsuarioActual();
$codOperario = $usuario['CodOperario'];

try {
    $conn->beginTransaction();

    // Actualizamos o insertamos el plan de despacho para la categoría B
    $sql = "
        INSERT INTO plan_despacho_sucursal
            (cod_sucursal, categoria_insumo, tipo_frecuencia, activo, capacidad_congelados_paquetes, creado_por, modificado_por)
        VALUES
            (:cod_sucursal, 'B', 'n_semanas', 1, :capacidad, :creado, :modificado)
        ON DUPLICATE KEY UPDATE
            capacidad_congelados_paquetes = VALUES(capacidad_congelados_paquetes),
            modificado_por = VALUES(modificado_por)
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':cod_sucursal' => $cod_sucursal,
        ':capacidad' => $capacidad_congelados_paquetes,
        ':creado' => $codOperario,
        ':modificado' => $codOperario
    ]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Capacidad actualizada.']);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
