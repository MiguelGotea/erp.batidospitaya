<?php
// ajax/promo_guardar.php — Crear o actualizar una promoción (con transacción)
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    if (!isset($_SESSION['usuario_id'])) throw new Exception('No autorizado');

    $raw = isset($_POST['payload']) ? $_POST['payload'] : null;
    if (!$raw) throw new Exception('Payload vacío');

    $payload = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('JSON inválido: ' . json_last_error_msg());

    $p   = $payload['promo']       ?? [];
    $cs  = $payload['condiciones'] ?? [];
    $uid = $_SESSION['usuario_id'];

    // ── Validaciones ──
    $nombre = trim($p['nombre'] ?? '');
    if (!$nombre) throw new Exception('El nombre es obligatorio');

    $resultadoValor = (float)($p['resultado_valor'] ?? 0);
    if ($resultadoValor <= 0) throw new Exception('El valor del descuento debe ser mayor a 0');

    $id = (int)($p['id'] ?? 0);

    // Validar código único
    $codigo = trim($p['codigo_interno'] ?? '');
    if ($codigo !== '') {
        $sqlChk = 'SELECT COUNT(*) as n FROM promo_promociones WHERE codigo_interno = :cod';
        if ($id > 0) $sqlChk .= ' AND id != :id';
        $stmtChk = $conn->prepare($sqlChk);
        $chkParams = [':cod' => $codigo];
        if ($id > 0) $chkParams[':id'] = $id;
        $stmtChk->execute($chkParams);
        if ($stmtChk->fetch()['n'] > 0) throw new Exception('El código interno ya está en uso');
    }

    $campos = [
        'nombre'                => $nombre,
        'codigo_interno'        => $codigo ?: null,
        'descripcion_interna'   => trim($p['descripcion_interna'] ?? '') ?: null,
        'prioridad'             => (int)($p['prioridad'] ?? 10),
        'estado'                => $p['estado'] ?? 'borrador',
        'ejecucion_automatica'  => (int)(!empty($p['ejecucion_automatica'])),
        'combinable'            => (int)(!empty($p['combinable'])),
        'uso_unico_cliente'     => (int)(!empty($p['uso_unico_cliente'])),
        'requiere_autorizacion' => (int)(!empty($p['requiere_autorizacion'])),
        'usos_maximos'          => $p['usos_maximos'] !== '' && $p['usos_maximos'] !== null ? (int)$p['usos_maximos'] : null,
        'descuento_maximo_cs'   => $p['descuento_maximo_cs'] !== '' && $p['descuento_maximo_cs'] !== null ? (float)$p['descuento_maximo_cs'] : null,
        'fecha_inicio'          => $p['fecha_inicio'] ?: null,
        'fecha_fin'             => $p['fecha_fin']    ?: null,
        'objetivo_descuento'    => $p['objetivo_descuento'] ?? 'todos',
        'objetivo_get_y_prod'   => $p['objetivo_get_y_prod'] ? (int)$p['objetivo_get_y_prod'] : null,
        'objetivo_get_y_cant'   => (int)($p['objetivo_get_y_cant'] ?? 1),
        'objetivo_upgrade_de'   => $p['objetivo_upgrade_de'] ?: null,
        'objetivo_upgrade_a'    => $p['objetivo_upgrade_a']  ?: null,
        'resultado_tipo'        => $p['resultado_tipo'] ?? 'pct_producto',
        'resultado_valor'       => $resultadoValor,
    ];

    $conn->beginTransaction();

    if ($id > 0) {
        // ── UPDATE ──
        $sets = array_map(fn($k) => "$k = :$k", array_keys($campos));
        $sets[] = 'usuario_modificacion = :uid';
        $sql = 'UPDATE promo_promociones SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $conn->prepare($sql);
        foreach ($campos as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $mensaje = 'Promoción actualizada exitosamente';

    } else {
        // ── INSERT ──
        $campos['usuario_creacion'] = $uid;
        $cols = implode(', ', array_keys($campos));
        $phs  = implode(', ', array_map(fn($k) => ":$k", array_keys($campos)));
        $sql  = "INSERT INTO promo_promociones ($cols) VALUES ($phs)";
        $stmt = $conn->prepare($sql);
        foreach ($campos as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->execute();
        $id = $conn->lastInsertId();
        $mensaje = 'Promoción creada exitosamente';
    }

    // ── Condiciones: eliminar anteriores y re-insertar ──
    $stmtDel = $conn->prepare('DELETE FROM promo_condiciones WHERE promo_id = :pid');
    $stmtDel->execute([':pid' => $id]);

    if (!empty($cs)) {
        // Obtenemos los IDs de las opciones de condiciones para el mapeo
        $stmtOpc = $conn->query("SELECT id, codigo FROM promo_condiciones_opciones");
        $mapOpciones = $stmtOpc->fetchAll(PDO::FETCH_KEY_PAIR); // [codigo => id]

        $sqlIns = 'INSERT INTO promo_condiciones (promo_id, tipo_cond, nombre_cond, opcion_id, valor_json, orden)
                   VALUES (:pid, :tipo, :nombre, :opcion_id, :valor, :orden)';
        $stmtIns = $conn->prepare($sqlIns);
        foreach ($cs as $cond) {
            $nombre = $cond['nombre_cond'];
            $opcion_id = $mapOpciones[$nombre] ?? null;

            $stmtIns->execute([
                ':pid'    => $id,
                ':tipo'   => $cond['tipo_cond'],
                ':nombre' => $nombre,
                ':opcion_id' => $opcion_id,
                ':valor'  => json_encode($cond['valor_json']),
                ':orden'  => (int)($cond['orden'] ?? 0)
            ]);
        }
    }

    $conn->commit();

    echo json_encode(['success' => true, 'id' => $id, 'message' => $mensaje]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
