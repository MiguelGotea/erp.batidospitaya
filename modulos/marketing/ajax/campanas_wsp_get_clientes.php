<?php
/**
 * campanas_wsp_get_clientes.php
 * Búsqueda de clientes del Club Pitaya con celular
 * Acciones: 'sucursales' | 'buscar'
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('campanas_wsp', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

$accion = $_GET['accion'] ?? 'buscar';

try {
    if ($accion === 'sucursales') {
        $stmt = $conn->query("
            SELECT DISTINCT nombre_sucursal
            FROM clientesclub
            WHERE celular IS NOT NULL AND celular <> ''
            ORDER BY nombre_sucursal ASC
        ");
        echo json_encode(['success' => true, 'sucursales' => $stmt->fetchAll()]);
        exit;
    }

    // ── Búsqueda de clientes ──
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $sucursal = trim($_GET['sucursal'] ?? '');
    $params = [':q1' => $q, ':q2' => $q];

    $condSucursal = '';
    if ($sucursal !== '') {
        $condSucursal = ' AND nombre_sucursal = :sucursal';
        $params[':sucursal'] = $sucursal;
    }

    $stmt = $conn->prepare("
        SELECT
            id_clienteclub AS id,
            CONCAT(TRIM(COALESCE(nombre,'')), ' ', TRIM(COALESCE(apellido,''))) AS nombre,
            celular        AS telefono_raw,
            nombre_sucursal AS sucursal
        FROM clientesclub
        WHERE celular IS NOT NULL
          AND celular <> ''
          AND LENGTH(REGEXP_REPLACE(celular, '[^0-9]','')) >= 8
          AND (
              CONCAT(COALESCE(nombre,''), ' ', COALESCE(apellido,'')) LIKE :q1
              OR celular LIKE :q2
          )
          $condSucursal
        ORDER BY nombre ASC
        LIMIT 100
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $clientes = array_map(function ($c) {
        $c['telefono'] = formatearTelefonoNi($c['telefono_raw']);
        $c['nombre'] = trim($c['nombre']);
        unset($c['telefono_raw']);
        return $c;
    }, $rows);

    echo json_encode(['success' => true, 'clientes' => $clientes]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function formatearTelefonoNi($cel)
{
    $limpio = preg_replace('/\D/', '', $cel);
    if (strlen($limpio) === 8)
        return '+505' . $limpio;
    if (str_starts_with($limpio, '505') && strlen($limpio) === 11)
        return '+' . $limpio;
    if (strlen($limpio) > 8)
        return '+' . $limpio;
    return $limpio;
}
