<?php
/**
 * hikvision_estados_batch.php
 * Carga los estados IA de múltiples pedidos en una sola consulta.
 * Llamado después de renderizar la tabla para mostrar badges existentes.
 *
 * POST → { pedidos: [ {cod_pedido: X, local: "Y"}, ... ] }
 * Devuelve mapa: { "CodPedido_local": { estado, promedio } }
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('historial_pedidos_globales', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$data    = json_decode(file_get_contents('php://input'), true);
$pedidos = isset($data['pedidos']) && is_array($data['pedidos']) ? $data['pedidos'] : [];

if (empty($pedidos)) {
    echo json_encode(['success' => true, 'estados' => []]);
    exit;
}

// Limitar a 200 por seguridad
$pedidos = array_slice($pedidos, 0, 200);

try {
    // Construir condiciones IN para la consulta
    $conditions = [];
    $params     = [];

    foreach ($pedidos as $i => $p) {
        $cp = isset($p['cod_pedido']) ? intval($p['cod_pedido']) : 0;
        $lc = isset($p['local'])      ? trim($p['local'])        : '';
        if (!$cp || !$lc) continue;

        $conditions[] = "(c.cod_pedido = :cp$i AND c.local_codigo = :lc$i)";
        $params[":cp$i"] = $cp;
        $params[":lc$i"] = $lc;
    }

    if (empty($conditions)) {
        echo json_encode(['success' => true, 'estados' => []]);
        exit;
    }

    $whereOr = implode(' OR ', $conditions);

    // Para cada pedido tomar el registro más reciente de la cola
    $sql = "
        SELECT
            c.cod_pedido,
            c.local_codigo,
            c.estado,
            c.id               AS id_cola,
            a.cal_promedio     AS promedio,
            a.grupo_bienvenida,
            a.grupo_asesoria,
            a.grupo_membresia,
            a.grupo_cobro,
            a.membresia_contexto,
            a.resumen
        FROM hikvision_cola_analisis c
        LEFT JOIN hikvision_analisis_ia_atencion a ON a.id_cola = c.id
        WHERE ($whereOr)
          AND c.id = (
              SELECT MAX(c2.id)
              FROM hikvision_cola_analisis c2
              WHERE c2.cod_pedido   = c.cod_pedido
                AND c2.local_codigo = c.local_codigo
          )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $estados = [];
    foreach ($rows as $row) {
        $clave = $row['cod_pedido'] . '_' . $row['local_codigo'];
        $estados[$clave] = [
            'estado'              => $row['estado'],
            'promedio'            => $row['promedio'] !== null ? (float)$row['promedio'] : null,
            'grupo_bienvenida'    => $row['grupo_bienvenida'] !== null ? (int)$row['grupo_bienvenida'] : null,
            'grupo_asesoria'      => $row['grupo_asesoria']   !== null ? (int)$row['grupo_asesoria']   : null,
            'grupo_membresia'     => $row['grupo_membresia']  !== null ? (int)$row['grupo_membresia']  : null,
            'grupo_cobro'         => $row['grupo_cobro']      !== null ? (int)$row['grupo_cobro']      : null,
            'membresia_contexto'  => $row['membresia_contexto'] ?? 'sin_membresia',
            'resumen'             => $row['resumen'],
        ];
    }


    echo json_encode([
        'success' => true,
        'estados' => $estados,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

