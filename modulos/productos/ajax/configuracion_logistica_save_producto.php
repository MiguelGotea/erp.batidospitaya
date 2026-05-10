<?php
// ajax/configuracion_logistica_save_producto.php
// Guarda (INSERT o UPDATE) un campo de configuración logística
// por categoría de insumo (codigo_insumo A-G) y sucursal.

require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario         = obtenerUsuarioActual();
    $codOperario     = $usuario['CodOperario'];
    $codigo_sucursal = trim($_POST['codigo_sucursal'] ?? '');
    $codigo_insumo   = strtoupper(trim($_POST['codigo_insumo'] ?? ''));
    $campo           = trim($_POST['campo']           ?? '');
    $valor           = trim($_POST['valor']           ?? '');

    if (empty($codigo_sucursal) || empty($codigo_insumo) || empty($campo)) {
        throw new Exception('Faltan parámetros requeridos.');
    }

    // Validar que la letra sea válida
    $letras_validas = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
    if (!in_array($codigo_insumo, $letras_validas)) {
        throw new Exception('Código de insumo no válido.');
    }

    // Lista blanca de campos permitidos
    $campos_permitidos = [
        'dias_ciclo',
        'dias_desfase',
        'dias_abastecimiento_despacho',
        'ajuste_demanda'
    ];
    if (!in_array($campo, $campos_permitidos)) {
        throw new Exception('Campo no permitido.');
    }

    // Sanitizar valor
    $valorFinal = ($valor === '' || $valor === null) ? null : $valor;

    // INSERT ... ON DUPLICATE KEY UPDATE
    // La clave única es (codigo_sucursal, codigo_insumo)
    $sql = "
        INSERT INTO configuracion_logistica_producto
            (cod_sucursal, codigo_insumo, {$campo}, creado_por, fecha_creacion)
        VALUES
            (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            {$campo}            = VALUES({$campo}),
            modificado_por      = ?,
            fecha_actualizacion = NOW()
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $codigo_sucursal,
        $codigo_insumo,
        $valorFinal,
        $codOperario,
        $codOperario
    ]);

    // Recuperar meta de auditoría para actualizar el indicador en la fila
    $sqlMeta = "
        SELECT
            clp.fecha_creacion,
            clp.fecha_actualizacion,
            CONCAT(om.Nombre, ' ', om.Apellido) AS modificado_por_nombre
        FROM configuracion_logistica_producto clp
        LEFT JOIN Operarios om ON clp.modificado_por = om.CodOperario
        WHERE clp.cod_sucursal = ? AND clp.codigo_insumo = ?
        LIMIT 1
    ";
    $stmtMeta = $conn->prepare($sqlMeta);
    $stmtMeta->execute([$codigo_sucursal, $codigo_insumo]);
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
