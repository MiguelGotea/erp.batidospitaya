<?php
/**
 * hikvision_estado_pedido.php
 * Consulta el estado de análisis de un pedido específico.
 * Devuelve estado de cola + resultado si está completado.
 * Llamado por polling JS cada ~5s.
 * GET ?cod_pedido=X&local=Y
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('historial_pedidos_globales', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$cod_pedido = isset($_GET['cod_pedido']) ? intval($_GET['cod_pedido']) : null;
$local      = isset($_GET['local'])      ? trim($_GET['local'])        : null;

if (!$cod_pedido || !$local) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit;
}

try {
    // Buscar la cola más reciente para este pedido
    $stmt = $conn->prepare("
        SELECT
            c.id           AS id_cola,
            c.estado,
            c.tipo,
            c.intentos,
            c.error_mensaje,
            c.created_at,
            c.updated_at,
            a.id           AS id_analisis,
            a.cal_amabilidad,
            a.cal_saludo,
            a.cal_despedida,
            a.cal_membresia,
            a.promedio,
            a.resumen,
            a.tiene_audio,
            a.duracion_segundos,
            a.modelo_ia,
            a.created_at   AS analizado_en
        FROM hikvision_cola_analisis c
        LEFT JOIN hikvision_analisis_ia_atencion a ON a.id_cola = c.id
        WHERE c.cod_pedido   = :cp
          AND c.local_codigo = :lc
        ORDER BY c.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':cp' => $cod_pedido, ':lc' => $local]);
    $row = $stmt->fetch();

    if (!$row) {
        // No está en cola ni analizado
        echo json_encode([
            'success' => true,
            'estado'  => 'sin_cola',
            'mensaje' => 'Este pedido no ha sido encolado para análisis.',
        ]);
        exit;
    }

    $resultado = [
        'success'    => true,
        'id_cola'    => (int)$row['id_cola'],
        'estado'     => $row['estado'],
        'tipo'       => $row['tipo'],
        'intentos'   => (int)$row['intentos'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];

    if ($row['estado'] === 'fallido') {
        $resultado['error_mensaje'] = $row['error_mensaje'];
    }

    if ($row['estado'] === 'completado' && $row['id_analisis']) {
        $resultado['analisis'] = [
            'id'                => (int)$row['id_analisis'],
            'cal_amabilidad'    => $row['cal_amabilidad'] !== null ? (int)$row['cal_amabilidad'] : null,
            'cal_saludo'        => $row['cal_saludo']     !== null ? (int)$row['cal_saludo']     : null,
            'cal_despedida'     => $row['cal_despedida']  !== null ? (int)$row['cal_despedida']  : null,
            'cal_membresia'     => $row['cal_membresia']  !== null ? (int)$row['cal_membresia']  : null,
            'promedio'          => $row['promedio']        !== null ? (float)$row['promedio']     : null,
            'resumen'           => $row['resumen'],
            'tiene_audio'       => (bool)$row['tiene_audio'],
            'duracion_segundos' => $row['duracion_segundos'] !== null ? (int)$row['duracion_segundos'] : null,
            'modelo_ia'         => $row['modelo_ia'],
            'analizado_en'      => $row['analizado_en'],
        ];
    }

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
