<?php
/**
 * Confirmar asistencia a reunión
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo = $usuario['CodNivelesCargos'];

    $idItem = intval($_POST['id_item'] ?? 0);
    $confirmacion = $_POST['confirmacion'] ?? ''; // 'asistire' o 'no_asistire'

    if ($idItem <= 0 || !in_array($confirmacion, ['asistire', 'no_asistire'])) {
        throw new Exception('Datos inválidos');
    }

    // Verificar que es participante
    $sql = "SELECT * FROM gestion_tareas_reuniones_participantes 
            WHERE id_item = :id_item AND cod_cargo = :cod_cargo";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_item' => $idItem,
        ':cod_cargo' => $codCargo
    ]);
    $participante = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participante) {
        throw new Exception('No es participante de esta reunión');
    }

    // Actualizar confirmación
    $sqlUpdate = "UPDATE gestion_tareas_reuniones_participantes 
                  SET confirmacion = :confirmacion,
                      fecha_confirmacion = NOW(),
                      cod_operario_confirmo = :cod_operario
                  WHERE id_item = :id_item AND cod_cargo = :cod_cargo";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':confirmacion' => $confirmacion,
        ':cod_operario' => $codOperario,
        ':id_item' => $idItem,
        ':cod_cargo' => $codCargo
    ]);

    // Verificar si todos confirmaron para cambiar estado a en_progreso
    $sqlCheck = "SELECT COUNT(*) as pendientes 
                 FROM gestion_tareas_reuniones_participantes 
                 WHERE id_item = :id_item AND confirmacion = 'pendiente'";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->execute([':id_item' => $idItem]);
    $resultado = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($resultado['pendientes'] == 0) {
        // Todos confirmaron, cambiar estado a en_progreso
        $sqlUpdateReunion = "UPDATE gestion_tareas_reuniones_items 
                             SET estado = 'en_progreso',
                                 fecha_ultima_modificacion = NOW()
                             WHERE id = :id";
        $stmtUpdateReunion = $conn->prepare($sqlUpdateReunion);
        $stmtUpdateReunion->execute([':id' => $idItem]);
    }

    // Actualizar progreso de la reunión
    actualizarProgresoReunion($conn, $idItem);

    $mensaje = $confirmacion == 'asistire' ? 'Asistencia confirmada' : 'Inasistencia confirmada';

    echo json_encode([
        'success' => true,
        'message' => $mensaje
    ]);

} catch (Exception $e) {
    error_log("Error en confirmar_asistencia: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Actualizar progreso de reunión
 */
function actualizarProgresoReunion($conn, $idReunion)
{
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN confirmacion != 'pendiente' THEN 1 ELSE 0 END) as confirmados
            FROM gestion_tareas_reuniones_participantes
            WHERE id_item = :id_item";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_item' => $idReunion]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = $resultado['total'];
    $confirmados = $resultado['confirmados'];

    if ($total > 0) {
        $progreso = ($confirmados / $total) * 100;

        $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                      SET progreso = :progreso,
                          fecha_ultima_modificacion = NOW()
                      WHERE id = :id";

        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->execute([
            ':progreso' => $progreso,
            ':id' => $idReunion
        ]);
    }
}
?>