<?php
/* ============================================================
   AJAX: plan_despacho_save.php
   Ruta: modulos/inventario/ajax/plan_despacho_save.php
   Método: POST
   ============================================================ */
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

/* ── Leer y sanitizar inputs ── */
$cod_sucursal      = trim($_POST['cod_sucursal']      ?? '');
$categoria_insumo  = trim($_POST['categoria_insumo']  ?? '');
$tipo_frecuencia   = trim($_POST['tipo_frecuencia']   ?? 'n_semanas');
$intervalo_semanas = isset($_POST['intervalo_semanas']) && $_POST['intervalo_semanas'] !== '' ? (int)$_POST['intervalo_semanas'] : null;
$dia_despacho      = isset($_POST['dia_despacho'])     && $_POST['dia_despacho']      !== '' ? (int)$_POST['dia_despacho']      : null;
$semana_ancla      = isset($_POST['semana_ancla'])     && $_POST['semana_ancla']      !== '' ? (int)$_POST['semana_ancla']      : null;
$dias_semana_raw   = trim($_POST['dias_semana']       ?? '');
$dias_preparacion  = isset($_POST['dias_preparacion']) ? max(0, (int)$_POST['dias_preparacion']) : 1;
$activo            = isset($_POST['activo'])            ? (int)(bool)$_POST['activo'] : 1;

// Campos Cat B
$cap_paquetes = isset($_POST['capacidad_congelados_paquetes']) && $_POST['capacidad_congelados_paquetes'] !== ''
    ? (int)$_POST['capacidad_congelados_paquetes'] : null;
$cap_obs      = trim($_POST['capacidad_congelados_obs'] ?? '');

/* ── Validaciones ── */
$errores = [];

if (empty($cod_sucursal)) $errores[] = 'cod_sucursal es requerido.';
if (empty($categoria_insumo)) $errores[] = 'categoria_insumo es requerida.';
if (!in_array($categoria_insumo, ['A','B','C','D','E','F','G'])) $errores[] = 'categoria_insumo inválida.';
if (!in_array($tipo_frecuencia, ['n_semanas','dias_semana'])) $errores[] = 'tipo_frecuencia inválido.';

if ($tipo_frecuencia === 'n_semanas') {
    if ($intervalo_semanas === null || $intervalo_semanas < 1 || $intervalo_semanas > 3) {
        $errores[] = 'intervalo_semanas debe ser 1, 2 o 3.';
    }
    if ($dia_despacho === null || $dia_despacho < 0 || $dia_despacho > 6) {
        $errores[] = 'dia_despacho debe estar entre 0 (Lun) y 6 (Dom).';
    }
}

$dias_semana_json = null;
if ($tipo_frecuencia === 'dias_semana') {
    $decoded = json_decode($dias_semana_raw, true);
    if (!is_array($decoded)) {
        $errores[] = 'dias_semana debe ser un JSON array válido.';
    } else {
        foreach ($decoded as $d) {
            if (!is_int($d) || $d < 0 || $d > 6) {
                $errores[] = 'dias_semana contiene valores fuera de rango (0-6).';
                break;
            }
        }
        $dias_semana_json = json_encode(array_values(array_unique($decoded)));
    }
}

if (!empty($errores)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errores)]);
    exit();
}

/* ── Usuario actual ── */
$usuario    = obtenerUsuarioActual();
$codOperario = $usuario['CodOperario'];
$nombreOp   = trim($usuario['Nombre'] . ' ' . $usuario['Apellido']);

try {
    $conn->beginTransaction();

    /* ── INSERT … ON DUPLICATE KEY UPDATE ── */
    if ($tipo_frecuencia === 'n_semanas') {
        $sql = "
            INSERT INTO plan_despacho_sucursal
                (cod_sucursal, categoria_insumo, tipo_frecuencia,
                 intervalo_semanas, dia_despacho, semana_ancla,
                 dias_semana, dias_preparacion, activo,
                 creado_por, modificado_por)
            VALUES
                (:cod_sucursal, :categoria_insumo, 'n_semanas',
                 :intervalo_semanas, :dia_despacho, :semana_ancla,
                 NULL, :dias_preparacion, :activo,
                 :cod_operario, :cod_operario)
            ON DUPLICATE KEY UPDATE
                tipo_frecuencia   = 'n_semanas',
                intervalo_semanas = VALUES(intervalo_semanas),
                dia_despacho      = VALUES(dia_despacho),
                semana_ancla      = VALUES(semana_ancla),
                dias_semana       = NULL,
                dias_preparacion  = VALUES(dias_preparacion),
                activo            = VALUES(activo),
                modificado_por    = VALUES(modificado_por)
        ";
        $params = [
            ':cod_sucursal'      => $cod_sucursal,
            ':categoria_insumo'  => $categoria_insumo,
            ':intervalo_semanas' => $intervalo_semanas,
            ':dia_despacho'      => $dia_despacho,
            ':semana_ancla'      => $semana_ancla,
            ':dias_preparacion'  => $dias_preparacion,
            ':activo'            => $activo,
            ':cod_operario'      => $codOperario,
        ];
    } else {
        $sql = "
            INSERT INTO plan_despacho_sucursal
                (cod_sucursal, categoria_insumo, tipo_frecuencia,
                 intervalo_semanas, dia_despacho, semana_ancla,
                 dias_semana, dias_preparacion, activo,
                 creado_por, modificado_por)
            VALUES
                (:cod_sucursal, :categoria_insumo, 'dias_semana',
                 NULL, NULL, NULL,
                 :dias_semana, :dias_preparacion, :activo,
                 :cod_operario, :cod_operario)
            ON DUPLICATE KEY UPDATE
                tipo_frecuencia   = 'dias_semana',
                intervalo_semanas = NULL,
                dia_despacho      = NULL,
                semana_ancla      = NULL,
                dias_semana       = VALUES(dias_semana),
                dias_preparacion  = VALUES(dias_preparacion),
                activo            = VALUES(activo),
                modificado_por    = VALUES(modificado_por)
        ";
        $params = [
            ':cod_sucursal'     => $cod_sucursal,
            ':categoria_insumo' => $categoria_insumo,
            ':dias_semana'      => $dias_semana_json,
            ':dias_preparacion' => $dias_preparacion,
            ':activo'           => $activo,
            ':cod_operario'     => $codOperario,
        ];
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    /* ── Cat B: actualizar capacidad congelador ── */
    if ($categoria_insumo === 'B') {
        // Intentar UPDATE primero; si no existe fila, no la creamos (la tabla puede no tener fila de logística aún)
        $stmtB = $conn->prepare("
            UPDATE configuracion_logistica_sucursal
            SET capacidad_congelados_paquetes = :paquetes,
                capacidad_congelados_obs      = :obs
            WHERE cod_sucursal = :cod
        ");
        $stmtB->execute([
            ':paquetes' => $cap_paquetes,
            ':obs'      => $cap_obs !== '' ? $cap_obs : null,
            ':cod'      => $cod_sucursal,
        ]);
    }

    /* ── Obtener fecha y nombre actualizados ── */
    $stmtMeta = $conn->prepare("
        SELECT pds.fecha_actualizacion,
               CONCAT(o.Nombre, ' ', o.Apellido) AS nombre_operario
        FROM plan_despacho_sucursal pds
        LEFT JOIN Operarios o ON o.CodOperario = pds.modificado_por
        WHERE pds.cod_sucursal = ? AND pds.categoria_insumo = ?
        LIMIT 1
    ");
    $stmtMeta->execute([$cod_sucursal, $categoria_insumo]);
    $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Plan guardado correctamente.',
        'meta'    => [
            'modificado_por_nombre' => $meta['nombre_operario'] ?? $nombreOp,
            'fecha_actualizacion'   => $meta['fecha_actualizacion'] ?? date('Y-m-d H:i:s'),
        ]
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
