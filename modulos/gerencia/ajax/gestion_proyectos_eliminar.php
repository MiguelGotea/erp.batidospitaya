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

    // 1. Verificar si tiene subproyectos (Ahora como restricción fuerte)
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM gestion_proyectos_proyectos WHERE proyecto_padre_id = :id");
    $stmtCount->execute([':id' => $id]);
    $subCount = $stmtCount->fetchColumn();

    if ($subCount > 0) {
        if ($conn->inTransaction())
            $conn->rollBack();
        echo json_encode(['success' => false, 'message' => "No se puede eliminar un proyecto padre que tiene subproyectos. Primero debes eliminar los subproyectos."]);
        exit();
    }

    // 2. Eliminar
    $sqlDelete = "DELETE FROM gestion_proyectos_proyectos WHERE id = :id";
    $stmtDel = $conn->prepare($sqlDelete);
    $stmtDel->execute([':id' => $id]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Proyecto eliminado correctamente"]);

} catch (Throwable $e) {
    if ($conn->inTransaction())
        $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>