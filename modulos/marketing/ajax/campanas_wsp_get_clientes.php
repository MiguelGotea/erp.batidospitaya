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
    $qRaw = trim($_GET['q'] ?? '');
    $sucursal = trim($_GET['sucursal'] ?? '');
    $ultimaCompra = (int) ($_GET['ultima_compra'] ?? 0); // días; -1 = sin compras

    // Detectar si la búsqueda es numérica (membresía)
    $esSoloNumero = ($qRaw !== '' && ctype_digit($qRaw));

    $params = [];

    // --- Condición de búsqueda ---
    if ($qRaw === '') {
        $condBusqueda = '1=1';
    } elseif ($esSoloNumero) {
        $q = '%' . $qRaw . '%';
        $condBusqueda = 'cc.membresia LIKE :membresia';
        $params[':membresia'] = $q;
    } else {
        $q = '%' . $qRaw . '%';
        $condBusqueda = "(CONCAT(COALESCE(cc.nombre,''), ' ', COALESCE(cc.apellido,'')) LIKE :q1 OR cc.celular LIKE :q2)";
        $params[':q1'] = $q;
        $params[':q2'] = $q;
    }

    // --- Condición de sucursal ---
    $condSucursal = '';
    if ($sucursal !== '') {
        $condSucursal = 'AND cc.nombre_sucursal = :sucursal';
        $params[':sucursal'] = $sucursal;
    }

    // --- Condición de última compra ---
    $condUltimaCompra = '';
    $joinVentas = '';

    if ($ultimaCompra > 0) {
        // Sólo clientes cuya última compra esté dentro del rango de días
        $joinVentas = "
            LEFT JOIN (
                SELECT CodCliente, MAX(Fecha) AS ultima_compra
                FROM VentasGlobalesAccessCSV
                GROUP BY CodCliente
            ) v ON v.CodCliente = cc.membresia
        ";
        $condUltimaCompra = "AND v.ultima_compra >= CURDATE() - INTERVAL {$ultimaCompra} DAY";

    } elseif ($ultimaCompra === -1) {
        // Clientes sin ninguna compra registrada
        $joinVentas = "
            LEFT JOIN (
                SELECT CodCliente, MAX(Fecha) AS ultima_compra
                FROM VentasGlobalesAccessCSV
                GROUP BY CodCliente
            ) v ON v.CodCliente = cc.membresia
        ";
        $condUltimaCompra = "AND v.ultima_compra IS NULL";
    }

    $sql = "
        SELECT
            cc.id_clienteclub           AS id,
            TRIM(CONCAT(COALESCE(cc.nombre,''), ' ', COALESCE(cc.apellido,''))) AS nombre,
            cc.celular                  AS telefono_raw,
            cc.nombre_sucursal          AS sucursal,
            cc.membresia                AS membresia
        FROM clientesclub cc
        $joinVentas
        WHERE cc.celular IS NOT NULL
          AND cc.celular <> ''
          AND LENGTH(REGEXP_REPLACE(cc.celular, '[^0-9]','')) >= 8
          AND $condBusqueda
          $condSucursal
          $condUltimaCompra
        ORDER BY cc.nombre ASC
    ";

    $stmt = $conn->prepare($sql);
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
