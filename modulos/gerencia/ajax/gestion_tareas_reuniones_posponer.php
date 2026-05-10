<?php
/**
 * gestion_tareas_reuniones_posponer.php
 * Reagenda la fecha_meta de una tarea.
 * Solo permite fechas >= hoy.
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario     = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo    = $usuario['CodNivelesCargos'];

    $id         = intval($_POST['id']         ?? 0);
    $nuevaFecha = trim($_POST['nueva_fecha']  ?? '');

    if ($id <= 0 || empty($nuevaFecha)) {
        throw new Exception('Datos incompletos.');
    }

    // Validar formato de fecha
    $dateObj = DateTime::createFromFormat('Y-m-d', $nuevaFecha);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $nuevaFecha) {
        throw new Exception('Formato de fecha inválido.');
    }

    // No permitir fechas pasadas
    $hoy = new DateTime('today');
    if ($dateObj < $hoy) {
        throw new Exception('No puedes posponer a una fecha pasada. Selecciona hoy o un día futuro.');
    }

    // Obtener el item
    $sql  = "SELECT * FROM gestion_tareas_reuniones_items WHERE id = :id AND tipo = 'tarea'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Tarea no encontrada.');
    }

    // Verificar que no esté finalizada ni cancelada
    if (in_array($item['estado'], ['finalizado', 'cancelado'])) {
        throw new Exception('No se puede reagendar una tarea finalizada o cancelada.');
    }

    // Verificar permisos: asignado O creador O tiene permiso de cancelar_tarea_reunion (gerencia)
    $esAsignado = ($item['cod_cargo_asignado'] == $codCargo);
    $esCreador  = ($item['cod_operario_creador'] == $codOperario);

    // Verificar permiso gerencial
    require_once '../../../core/permissions/permissions.php';
    $esGerencia = tienePermiso('gestion_tareas_reuniones', 'cancelar_tarea_reunion', $codCargo);

    if (!$esAsignado && !$esCreador && !$esGerencia) {
        throw new Exception('No tienes permisos para reagendar esta tarea.');
    }

    // Calcular días de diferencia para el mensaje
    // Guard: fecha_meta puede ser NULL para tareas sin fecha asignada
    $diasDiff   = 0;
    $esSinFecha = empty($item['fecha_meta']);
    if (!$esSinFecha) {
        $fechaAnterior = new DateTime($item['fecha_meta']);
        $diff          = $fechaAnterior->diff($dateObj);
        $diasDiff      = (int) $diff->format('%r%a'); // positivo = hacia el futuro
    }

    // Actualizar fecha_meta
    $sqlUpdate = "UPDATE gestion_tareas_reuniones_items
                  SET fecha_meta                       = :fecha_meta,
                      fecha_ultima_modificacion        = NOW(),
                      cod_operario_ultima_modificacion = :cod_operario
                  WHERE id = :id";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':fecha_meta'    => $nuevaFecha,
        ':cod_operario'  => $codOperario,
        ':id'            => $id,
    ]);

    // Si tiene subtareas, extender también las subtareas que vencían antes de la nueva fecha
    if (!empty($item['id'])) {
        $sqlSubtareas = "UPDATE gestion_tareas_reuniones_items
                         SET fecha_meta                       = :fecha_meta,
                             fecha_ultima_modificacion        = NOW()
                         WHERE id_padre = :id_padre
                           AND tipo     = 'subtarea'
                           AND fecha_meta < :fecha_meta2
                           AND estado NOT IN ('finalizado','cancelado')";
        $stmtSub = $conn->prepare($sqlSubtareas);
        $stmtSub->execute([
            ':fecha_meta'  => $nuevaFecha,
            ':fecha_meta2' => $nuevaFecha,
            ':id_padre'    => $id,
        ]);
    }

    // Respuesta
    if ($esSinFecha) {
        $mensajeDias = "Fecha asignada correctamente.";
    } else {
        $mensajeDias = $diasDiff >= 0
            ? "Postergada " . ($diasDiff === 0 ? "al día de hoy" : "{$diasDiff} día(s) hacia adelante")
            : "Adelantada " . abs($diasDiff) . " día(s)";
    }

    echo json_encode([
        'success'       => true,
        'message'       => "Tarea reagendada para el {$nuevaFecha}. {$mensajeDias}.",
        'nueva_fecha'   => $nuevaFecha,
        'dias_diff'     => $diasDiff,
    ]);

} catch (Exception $e) {
    error_log("Error en posponer_tarea: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
?>
