<?php
// ajax/configuracion_logistica_save_sucursal.php
// Guarda (INSERT o UPDATE) un campo del encabezado de configuración logística de una sucursal.

require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario         = obtenerUsuarioActual();
    $codOperario     = $usuario['CodOperario'];
    $codigo_sucursal = trim($_POST['codigo_sucursal'] ?? '');
    $campo           = trim($_POST['campo']           ?? '');
    $valor           = trim($_POST['valor']           ?? '');

    if (empty($codigo_sucursal) || empty($campo)) {
        throw new Exception('Faltan parámetros requeridos.');
    }

    // Lista blanca de campos permitidos
    $campos_permitidos = ['dias_stock_minimo', 'capacidad_congelados'];
    if (!in_array($campo, $campos_permitidos)) {
        throw new Exception('Campo no permitido.');
    }

    // Sanitizar valor: permitir null si viene vacío
    $valorFinal = ($valor === '' || $valor === null) ? null : $valor;

    // INSERT ... ON DUPLICATE KEY UPDATE
    // La clave única es (codigo_sucursal)
    $sql = "
        INSERT INTO configuracion_logistica_sucursal
            (cod_sucursal, {$campo}, creado_por, fecha_creacion)
        VALUES
            (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            {$campo}            = VALUES({$campo}),
            modificado_por      = ?,
            fecha_actualizacion = NOW()
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $codigo_sucursal,
        $valorFinal,
        $codOperario,
        $codOperario
    ]);

    // Recuperar meta de auditoría para actualizar la UI
    $sqlMeta = "
        SELECT
            cls.fecha_creacion,
            cls.fecha_actualizacion,
            CONCAT(om.Nombre, ' ', om.Apellido) AS modificado_por_nombre
        FROM configuracion_logistica_sucursal cls
        LEFT JOIN Operarios om ON cls.modificado_por = om.CodOperario
        WHERE cls.cod_sucursal = ?
        LIMIT 1
    ";
    $stmtMeta = $conn->prepare($sqlMeta);
    $stmtMeta->execute([$codigo_sucursal]);
    $meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Configuración guardada correctamente.',
        'meta'    => $meta ?: []
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
