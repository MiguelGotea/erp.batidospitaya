<?php
// gestion_proyectos_crear.php
// Crea un nuevo proyecto o subproyecto

header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$idOperario = $usuario['CodOperario'];

// Verificar permiso de creación
if (!tienePermiso('gestion_proyectos', 'crear_proyecto', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para crear proyectos']);
    exit();
}

// Obtener datos del request
$data = json_decode(file_get_contents('php://input'), true);

$nombre = $data['nombre'] ?? 'Nuevo Proyecto';
$descripcion = $data['descripcion'] ?? '';
$codNivelesCargos = $data['CodNivelesCargos'] ?? null;
$fechaInicio = $data['fecha_inicio'] ?? null;
$fechaFin = $data['fecha_fin'] ?? null;
$esSubproyecto = $data['es_subproyecto'] ?? 0;
$proyectoPadreId = $data['proyecto_padre_id'] ?? null;

// Validaciones básicas
if (!$codNivelesCargos || !$fechaInicio || !$fechaFin) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit();
}

if (strtotime($fechaFin) < strtotime($fechaInicio)) {
    echo json_encode(['success' => false, 'message' => 'La fecha de fin debe ser posterior a la de inicio']);
    exit();
}

try {
    $conn->beginTransaction();

    // 1. Insertar el proyecto
    $sqlInsert = "INSERT INTO gestion_proyectos_proyectos 
                    (nombre, descripcion, CodNivelesCargos, fecha_inicio, fecha_fin, es_subproyecto, proyecto_padre_id, creado_por) 
                  VALUES (:nombre, :descripcion, :cargo, :inicio, :fin, :es_sub, :padre, :creador)";

    $stmt = $conn->prepare($sqlInsert);
    $stmt->execute([
        ':nombre' => $nombre,
        ':descripcion' => $descripcion,
        ':cargo' => $codNivelesCargos,
        ':inicio' => $fechaInicio,
        ':fin' => $fechaFin,
        ':es_sub' => $esSubproyecto,
        ':padre' => $proyectoPadreId,
        ':creador' => $idOperario
    ]);

    $nuevoId = $conn->lastInsertId();

    // 2. Si es subproyecto, ajustar fechas del padre si es necesario
    if ($esSubproyecto && $proyectoPadreId) {
        $sqlPadre = "SELECT fecha_inicio, fecha_fin FROM gestion_proyectos_proyectos WHERE id = :padre_id";
        $stmtPadre = $conn->prepare($sqlPadre);
        $stmtPadre->execute([':padre_id' => $proyectoPadreId]);
        $padre = $stmtPadre->fetch(PDO::FETCH_ASSOC);

        if ($padre) {
            $nuevoInicioPadre = (strtotime($fechaInicio) < strtotime($padre['fecha_inicio'])) ? $fechaInicio : $padre['fecha_inicio'];
            $nuevoFinPadre = (strtotime($fechaFin) > strtotime($padre['fecha_fin'])) ? $fechaFin : $padre['fecha_fin'];

            if ($nuevoInicioPadre != $padre['fecha_inicio'] || $nuevoFinPadre != $padre['fecha_fin']) {
                $sqlUpdatePadre = "UPDATE gestion_proyectos_proyectos 
                                   SET fecha_inicio = :inicio, fecha_fin = :fin, modificado_por = :usuario 
                                   WHERE id = :id";
                $stmtUpdate = $conn->prepare($sqlUpdatePadre);
                $stmtUpdate->execute([
                    ':inicio' => $nuevoInicioPadre,
                    ':fin' => $nuevoFinPadre,
                    ':usuario' => $idOperario,
                    ':id' => $proyectoPadreId
                ]);
            }
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Proyecto creado exitosamente',
        'id' => $nuevoId
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction())
        $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error al crear proyecto: ' . $e->getMessage()]);
}
?>