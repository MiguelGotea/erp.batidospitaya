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
$campo = $data['campo'] ?? null;
$valor = $data['valor'] ?? null;

if (!$id || !$campo) {
    echo json_encode(['success' => false, 'message' => 'ID y campo son requeridos']);
    exit();
}

// Campos permitidos para actualización simple
$camposPermitidos = ['nombre', 'descripcion', 'orden_visual', 'fecha_inicio', 'fecha_fin'];
if (!in_array($campo, $camposPermitidos)) {
    echo json_encode(['success' => false, 'message' => 'Campo no permitido']);
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

    // Lógica especial para fechas
    if ($campo === 'fecha_inicio' || $campo === 'fecha_fin') {
        $nuevaFechaInicio = ($campo === 'fecha_inicio') ? $valor : $proyecto['fecha_inicio'];
        $nuevaFechaFin = ($campo === 'fecha_fin') ? $valor : $proyecto['fecha_fin'];

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

    // Actualizar el campo
    $sqlUpdate = "UPDATE gestion_proyectos_proyectos SET $campo = :valor, modificado_por = :usuario WHERE id = :id";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':valor' => $valor,
        ':usuario' => $idOperario,
        ':id' => $id
    ]);

    // Si es SUBPROYECTO y se movió/estiró, ajustar al PADRE
    if ($proyecto['es_subproyecto'] == 1 && ($campo === 'fecha_inicio' || $campo === 'fecha_fin')) {
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

} catch (Exception $e) {
    if ($conn->inTransaction())
        $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>