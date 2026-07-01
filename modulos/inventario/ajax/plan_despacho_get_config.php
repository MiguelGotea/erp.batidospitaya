<?php
/* ============================================================
   AJAX: plan_despacho_get_config.php
   Ruta: modulos/inventario/ajax/plan_despacho_get_config.php
   Método: POST
   Body:   cod_sucursal
   ============================================================ */
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

$cod_sucursal = trim($_POST['cod_sucursal'] ?? '');

if (empty($cod_sucursal)) {
    echo json_encode(['success' => false, 'message' => 'cod_sucursal requerido.']);
    exit();
}

$categorias = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

try {
    /* ── Plan de despacho (LEFT JOIN para retornar defaults si no existe fila) ── */
    $placeholders = implode(',', array_fill(0, count($categorias), '?'));
    $stmt = $conn->prepare("
        SELECT
            cats.cat,
            pds.id,
            pds.categoria_insumo,
            pds.tipo_frecuencia,
            pds.intervalo_semanas,
            pds.dia_despacho,
            pds.semana_ancla,
            pds.dias_semana,
            pds.dias_preparacion,
            pds.activo,
            pds.dias_stock_minimo,
            pds.capacidad_congelados_paquetes,
            pds.modificado_por,
            CONCAT(o.Nombre, ' ', o.Apellido) AS modificado_por_nombre,
            pds.fecha_actualizacion
        FROM (SELECT ? AS cat UNION SELECT ? UNION SELECT ? UNION SELECT ?
              UNION SELECT ? UNION SELECT ? UNION SELECT ?) cats
        LEFT JOIN plan_despacho_sucursal pds
            ON pds.cod_sucursal = ? AND pds.categoria_insumo = cats.cat
        LEFT JOIN Operarios o ON o.CodOperario = pds.modificado_por
        ORDER BY cats.cat ASC
    ");

    $params = array_merge($categorias, [$cod_sucursal]);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $plan = [];
    foreach ($rows as $row) {
        $cat = $row['cat'];
        $plan[$cat] = [
            'id'                   => $row['id'],
            'tipo_frecuencia'      => $row['tipo_frecuencia'] ?? 'n_semanas',
            'intervalo_semanas'    => $row['intervalo_semanas'] !== null ? (int)$row['intervalo_semanas'] : 1,
            'dia_despacho'         => $row['dia_despacho'] !== null ? (int)$row['dia_despacho'] : 1,
            'semana_ancla'         => $row['semana_ancla'] !== null ? (int)$row['semana_ancla'] : null,
            'dias_semana'          => $row['dias_semana'] ? json_decode($row['dias_semana'], true) : [],
            'dias_preparacion'     => $row['dias_preparacion'] !== null ? (int)$row['dias_preparacion'] : 1,
            'activo'               => $row['activo'] !== null ? (int)$row['activo'] : 1,
            'dias_stock_minimo'    => $row['dias_stock_minimo'] !== null ? (float)$row['dias_stock_minimo'] : null,
            'capacidad_congelados_paquetes' => $row['capacidad_congelados_paquetes'] !== null ? (int)$row['capacidad_congelados_paquetes'] : null,
            'modificado_por_nombre'=> $row['modificado_por_nombre'],
            'fecha_actualizacion'  => $row['fecha_actualizacion'],
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => [
            'plan' => $plan,
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
