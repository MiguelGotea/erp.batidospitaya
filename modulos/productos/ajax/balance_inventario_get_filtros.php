<?php
/* ============================================================
   AJAX: Obtener filtros del balance de inventario
   modulos/productos/ajax/balance_inventario_get_filtros.php
   Devuelve: semana_actual, sucursales activas
   ============================================================ */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('balance_inventario_access_host', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit();
}

try {
    // ── Semana actual ──────────────────────────────────────────────────
    $stmtSem = $conn->prepare("
        SELECT numero_semana, anio, fecha_inicio, fecha_fin
        FROM SemanasSistema
        WHERE CURDATE() BETWEEN fecha_inicio AND fecha_fin
        ORDER BY fecha_inicio DESC
        LIMIT 1
    ");
    $stmtSem->execute();
    $semActual = $stmtSem->fetch(PDO::FETCH_ASSOC);

    // ── Sucursales activas (tiendas) ───────────────────────────────────
    $stmtSuc = $conn->prepare("
        SELECT codigo, nombre
        FROM sucursales
        WHERE activa = 1
          AND sucursal = 1
        ORDER BY nombre ASC
    ");
    $stmtSuc->execute();
    $sucursales = $stmtSuc->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'           => true,
        'semana_actual' => $semActual ? [
            'numero_semana' => (int)$semActual['numero_semana'],
            'anio'          => (int)$semActual['anio'],
            'fecha_inicio'  => $semActual['fecha_inicio'],
            'fecha_fin'     => $semActual['fecha_fin'],
        ] : null,
        'sucursales'   => $sucursales,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
