<?php
/**
 * guardar_mapeo.php
 * Crea o actualiza un mapeo en diccionario_productos_legado.
 *
 * POST params:
 *   CodIngrediente          => string
 *   CodCotizacion           => int
 *   id_producto_presentacion => int
 *   notas                   => string (opcional)
 *
 * Para eliminar un mapeo sin asignar nuevo producto enviar:
 *   eliminar => 1  (junto con CodCotizacion)
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $usuario = obtenerUsuarioActual();
    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }

    if (!tienePermiso('diccionario_productos', 'edicion', $usuario['CodNivelesCargos'])) {
        echo json_encode(['success' => false, 'message' => 'Sin permiso de edición']);
        exit;
    }

    $codCotizacion = intval($_POST['CodCotizacion'] ?? 0);

    if ($codCotizacion <= 0) {
        echo json_encode(['success' => false, 'message' => 'CodCotizacion inválido']);
        exit;
    }

    // ── Eliminar mapeo ──────────────────────────────────────────────────────
    if (!empty($_POST['eliminar'])) {
        $stmt = $conn->prepare("DELETE FROM diccionario_productos_legado WHERE CodCotizacion = :cod");
        $stmt->execute([':cod' => $codCotizacion]);
        echo json_encode(['success' => true, 'action' => 'eliminado', 'message' => 'Mapeo eliminado']);
        exit;
    }

    // ── Guardar / actualizar mapeo ──────────────────────────────────────────
    $codIngrediente = trim($_POST['CodIngrediente'] ?? '');
    $idProductoPresentacion = intval($_POST['id_producto_presentacion'] ?? 0);
    $notas = trim($_POST['notas'] ?? '');

    if (!$codIngrediente || $idProductoPresentacion <= 0) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }

    // Verificar que el producto_presentacion existe
    $chk = $conn->prepare("SELECT id FROM producto_presentacion WHERE id = :id AND Activo = 'SI'");
    $chk->execute([':id' => $idProductoPresentacion]);
    if (!$chk->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El producto nuevo no existe o está inactivo']);
        exit;
    }

    // INSERT ON DUPLICATE KEY UPDATE (clave única: CodCotizacion)
    $stmt = $conn->prepare("
        INSERT INTO diccionario_productos_legado
            (CodIngrediente, CodCotizacion, id_producto_presentacion, notas, usuario_mapeo)
        VALUES
            (:ingr, :cot, :pp, :notas, :usr)
        ON DUPLICATE KEY UPDATE
            CodIngrediente          = VALUES(CodIngrediente),
            id_producto_presentacion = VALUES(id_producto_presentacion),
            notas                   = VALUES(notas),
            usuario_mapeo           = VALUES(usuario_mapeo)
    ");
    $stmt->execute([
        ':ingr' => $codIngrediente,
        ':cot' => $codCotizacion,
        ':pp' => $idProductoPresentacion,
        ':notas' => $notas ?: null,
        ':usr' => $usuario['CodOperario'],
    ]);

    $action = ($stmt->rowCount() === 1) ? 'creado' : 'actualizado';

    echo json_encode([
        'success' => true,
        'action' => $action,
        'message' => 'Mapeo guardado correctamente',
    ]);

} catch (Exception $e) {
    error_log("Error en guardar_mapeo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar el mapeo']);
}
?>