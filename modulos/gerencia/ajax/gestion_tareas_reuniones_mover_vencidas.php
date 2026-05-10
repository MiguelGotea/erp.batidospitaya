<?php
/**
 * gestion_tareas_reuniones_mover_vencidas.php
 * Mueve todas las tareas vencidas (no finalizadas/canceladas) a la fecha de hoy.
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario     = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo    = $usuario['CodNivelesCargos'];

    $hoyStr = date('Y-m-d');

    // Verificar si el usuario tiene permiso de gerencia para cancelar/reagendar cualquier tarea
    $esGerencia = tienePermiso('gestion_tareas_reuniones', 'cancelar_tarea_reunion', $codCargo);

    // Construir la consulta base
    // Solo tareas, no finalizadas ni canceladas, cuya fecha_meta sea menor a hoy
    $sql = "UPDATE gestion_tareas_reuniones_items
            SET fecha_meta = :hoy,
                fecha_ultima_modificacion = NOW(),
                cod_operario_ultima_modificacion = :cod_operario
            WHERE tipo = 'tarea'
              AND estado IN ('solicitado', 'en_progreso')
              AND fecha_meta < :hoy2";

    $params = [
        ':hoy'          => $hoyStr,
        ':hoy2'         => $hoyStr,
        ':cod_operario' => $codOperario
    ];

    // Si NO es gerencia, solo puede mover las suyas (asignadas o creadas)
    if (!$esGerencia) {
        $sql .= " AND (cod_cargo_asignado = :cod_cargo OR cod_operario_creador = :cod_operario2)";
        $params[':cod_cargo']     = $codCargo;
        $params[':cod_operario2'] = $codOperario;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->rowCount();

    // También actualizar subtareas que quedaron huérfanas o vencidas
    // Si una tarea padre se movió a hoy, sus subtareas que vencían antes también deben moverse
    $sqlSub = "UPDATE gestion_tareas_reuniones_items sub
               INNER JOIN gestion_tareas_reuniones_items padre ON sub.id_padre = padre.id
               SET sub.fecha_meta = :hoy,
                   sub.fecha_ultima_modificacion = NOW()
               WHERE sub.tipo = 'subtarea'
                 AND sub.estado NOT IN ('finalizado', 'cancelado')
                 AND sub.fecha_meta < :hoy2
                 AND padre.fecha_meta = :hoy3";
    
    $stmtSub = $conn->prepare($sqlSub);
    $stmtSub->execute([
        ':hoy'  => $hoyStr,
        ':hoy2' => $hoyStr,
        ':hoy3' => $hoyStr
    ]);
    $countSub = $stmtSub->rowCount();

    echo json_encode([
        'success' => true,
        'message' => "Se han movido $count tareas y $countSub subtareas al día de hoy.",
        'moved_count' => $count
    ]);

} catch (Exception $e) {
    error_log("Error en mover_vencidas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
