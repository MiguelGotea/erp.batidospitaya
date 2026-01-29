<?php
// gestion_proyectos_actualizar.php
// Actualiza campos de un proyecto (fechas, nombre, orden, etc.)

header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$idOperario = $usuario['CodOperario'];

// Verificar permiso
if (!tienePermiso('gestion_proyectos', 'crear_proyecto', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para modificar proyectos']);
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

    // Obtener datos actuales del proyecto
    $sqlActual = "SELECT * FROM gestion_proyectos_proyectos WHERE id = :id";
    $stmtActual = $conn->prepare($sqlActual);
    $stmtActual->execute([':id' => $id]);
    $proyecto = $stmtActual->fetch(PDO::FETCH_ASSOC);

    if (!$proyecto) {
        throw new Exception("Proyecto no encontrado");
    }

    // Determinar qué campos actualizar
    $updates = [];
    $params = [':id' => $id, ':usuario' => $idOperario];

    // Caso 1: Formato antiguo (campo/valor)
    if (isset($data['campo']) && isset($data['valor'])) {
        $campo = $data['campo'];
        $valor = $data['valor'];
        $updates[$campo] = $valor;
    }
    // Caso 2: Formato nuevo/múltiple (campos directos en el body)
    else {
        $camposPermitidos = ['nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'orden_visual', 'CodNivelesCargos', 'esta_expandido'];
        foreach ($camposPermitidos as $cp) {
            if (isset($data[$cp])) {
                $updates[$cp] = $data[$cp];
            }
        }
    }

    if (empty($updates)) {
        throw new Exception("No hay campos para actualizar");
    }

    // Validaciones especiales para fechas
    if (isset($updates['fecha_inicio']) || isset($updates['fecha_fin'])) {
        $nuevaFechaInicio = $updates['fecha_inicio'] ?? $proyecto['fecha_inicio'];
        $nuevaFechaFin = $updates['fecha_fin'] ?? $proyecto['fecha_fin'];

        if (strtotime($nuevaFechaFin) < strtotime($nuevaFechaInicio)) {
            throw new Exception("La fecha de fin debe ser posterior a la de inicio");
        }

        // Si es PADRE, validar que no se contraiga más allá de sus hijos
        if ($proyecto['es_subproyecto'] == 0) {
            $sqlHijos = "SELECT MIN(fecha_inicio) as min_ini, MAX(fecha_fin) as max_fin 
                         FROM gestion_proyectos_proyectos WHERE proyecto_padre_id = :id";
            $stmtHijos = $conn->prepare($sqlHijos);
            $stmtHijos->execute([':id' => $id]);
            $hijosRange = $stmtHijos->fetch(PDO::FETCH_ASSOC);

            if ($hijosRange['min_ini']) { // Tiene hijos
                if (
                    strtotime($nuevaFechaInicio) > strtotime($hijosRange['min_ini']) ||
                    strtotime($nuevaFechaFin) < strtotime($hijosRange['max_fin'])
                ) {
                    throw new Exception("No puedes contraer el proyecto porque tiene subproyectos que quedarían fuera del rango");
                }
            }
        }
    }

    // Caso 3: Movimiento en cascada (desplazamiento de padre e hijos)
    $movimientoCascada = $data['movimiento_cascada'] ?? null;
    if ($movimientoCascada && isset($updates['fecha_inicio']) && isset($updates['fecha_fin'])) {
        // 1. Mover el padre (ya está en $updates)
        // 2. Mover todos los hijos
        $sqlHijosMove = "UPDATE gestion_proyectos_proyectos 
                         SET fecha_inicio = DATE_ADD(fecha_inicio, INTERVAL :offset DAY), 
                             fecha_fin = DATE_ADD(fecha_fin, INTERVAL :offset2 DAY),
                             modificado_por = :usuario
                         WHERE proyecto_padre_id = :padre_id";
        $stmtHijosMove = $conn->prepare($sqlHijosMove);
        $stmtHijosMove->execute([
            ':offset' => (int) $movimientoCascada,
            ':offset2' => (int) $movimientoCascada,
            ':usuario' => $idOperario,
            ':padre_id' => $id
        ]);
    }

    // Construir SQL Dinámico para el proyecto principal
    $setParts = [];
    foreach ($updates as $campo => $valor) {
        $paramName = ":val_$campo";
        $setParts[] = "$campo = $paramName";
        $params[$paramName] = ($valor === "") ? null : $valor;
    }
    $setParts[] = "modificado_por = :usuario";

    $sqlFinal = "UPDATE gestion_proyectos_proyectos SET " . implode(", ", $setParts) . " WHERE id = :id";
    $stmtFinal = $conn->prepare($sqlFinal);
    $stmtFinal->execute($params);

    // Si es SUBPROYECTO y se movieron fechas, ajustar al PADRE (solo si no fue movimiento en cascada)
    if (!$movimientoCascada && $proyecto['es_subproyecto'] == 1 && (isset($updates['fecha_inicio']) || isset($updates['fecha_fin']))) {
        // ... (existing padre date adjustment logic)
        $padreId = $proyecto['proyecto_padre_id'];

        // Obtener rango extremo de todos los hijos del padre
        $sqlRangoHijos = "SELECT MIN(fecha_inicio) as min_ini, MAX(fecha_fin) as max_fin 
                          FROM gestion_proyectos_proyectos WHERE proyecto_padre_id = :padre_id";
        $stmtRango = $conn->prepare($sqlRangoHijos);
        $stmtRango->execute([':padre_id' => $padreId]);
        $rango = $stmtRango->fetch(PDO::FETCH_ASSOC);

        // Obtener fechas del padre
        $sqlPadre = "SELECT fecha_inicio, fecha_fin FROM gestion_proyectos_proyectos WHERE id = :padre_id";
        $stmtPadre = $conn->prepare($sqlPadre);
        $stmtPadre->execute([':padre_id' => $padreId]);
        $padre = $stmtPadre->fetch(PDO::FETCH_ASSOC);

        $nuevoInicioPadre = (strtotime($rango['min_ini']) < strtotime($padre['fecha_inicio'])) ? $rango['min_ini'] : $padre['fecha_inicio'];
        $nuevoFinPadre = (strtotime($rango['max_fin']) > strtotime($padre['fecha_fin'])) ? $rango['max_fin'] : $padre['fecha_fin'];

        if ($nuevoInicioPadre != $padre['fecha_inicio'] || $nuevoFinPadre != $padre['fecha_fin']) {
            $sqlUpdatePadre = "UPDATE gestion_proyectos_proyectos 
                               SET fecha_inicio = :inicio, fecha_fin = :fin, modificado_por = :usuario 
                               WHERE id = :id";
            $stmtUP = $conn->prepare($sqlUpdatePadre);
            $stmtUP->execute([
                ':inicio' => $nuevoInicioPadre,
                ':fin' => $nuevoFinPadre,
                ':usuario' => $idOperario,
                ':id' => $padreId
            ]);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Proyecto actualizado correctamente']);

} catch (Throwable $e) {
    if ($conn->inTransaction())
        $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>