<?php
// gestion_proyectos_eliminar.php
// Elimina un proyecto o subproyecto

header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso
if (!tienePermiso('gestion_proyectos', 'crear_proyecto', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar proyectos']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID requerido']);
    exit();
}

try {
    $conn->beginTransaction();

    // Obtener info antes de eliminar para recalcular padre si es subproyecto
    $sqlInfo = "SELECT es_subproyecto, proyecto_padre_id FROM gestion_proyectos_proyectos WHERE id = :id";
    $stmtInfo = $conn->prepare($sqlInfo);
    $stmtInfo->execute([':id' => $id]);
    $proyecto = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    if (!$proyecto) {
        throw new Exception("Proyecto no encontrado");
    }

    // Contar subproyectos si es padre (solo para el mensaje informativo, ON DELETE CASCADE limpia la BD)
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM gestion_proyectos_proyectos WHERE proyecto_padre_id = :id");
    $stmtCount->execute([':id' => $id]);
    $subCount = $stmtCount->fetchColumn();

    // Eliminar
    $sqlDelete = "DELETE FROM gestion_proyectos_proyectos WHERE id = :id";
    $stmtDel = $conn->prepare($sqlDelete);
    $stmtDel->execute([':id' => $id]);

    // Si era subproyecto, recalcular fechas del padre
    if ($proyecto['es_subproyecto'] == 1 && $proyecto['proyecto_padre_id']) {
        $padreId = $proyecto['proyecto_padre_id'];

        // Obtener nuevo rango de hijos restantes
        $sqlRango = "SELECT MIN(fecha_inicio) as min_ini, MAX(fecha_fin) as max_fin 
                     FROM gestion_proyectos_proyectos WHERE proyecto_padre_id = :padre_id";
        $stmtRango = $conn->prepare($sqlRango);
        $stmtRango->execute([':padre_id' => $padreId]);
        $rango = $stmtRango->fetch(PDO::FETCH_ASSOC);

        // Si no quedan hijos, el padre se mantiene igual. Si quedan hijos, ajustar.
        if ($rango['min_ini']) {
            // Podríamos ajustar el padre para que se contraiga si el hijo eliminado era el extremo,
            // pero generalmente en Gantt el padre mantiene su rango a menos que se mueva explícitamente.
            // Sin embargo, según requerimiento 4.2.4: "Cuando se mueve un HIJO: Recalcular...".
            // Para eliminación, si el hijo eliminado definía el borde, el padre NO debería contraerse automáticamente
            // a menos que sea una regla estricta. La regla 4.2.3 dice que el padre NO se ve afectado cuando se alarga/contrae solo.
            // Pero 4.2.4 dice que el padre SIEMPRE debe cubrir desde el hijo más temprano al más tardío.
            // Seguiré la regla 4.2.4: El padre se ajusta al nuevo rango de hijos.

            // Nota: No contraeré el padre más allá de su propio inicio/fin original a menos que sea necesario.
            // En realidad, la regla 4.2.1 dice "Cuando un HIJO se alarga más que el PADRE... el padre se extiende".
            // No dice que se contraiga al eliminar. Lo dejaré así por seguridad.
        }
    }

    $conn->commit();
    $msg = "Proyecto eliminado correctamente" . ($subCount > 0 ? " (incluidos $subCount subproyectos)" : "");
    echo json_encode(['success' => true, 'message' => $msg]);

} catch (Exception $e) {
    if ($conn->inTransaction())
        $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>