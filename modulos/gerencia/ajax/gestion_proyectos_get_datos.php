<?php
// gestion_proyectos_get_datos.php
// Carga proyectos activos y cargos para el diagrama Gantt

header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso de vista
if (!tienePermiso('gestion_proyectos', 'vista', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para ver esta sección']);
    exit();
}

try {
    // 1. Obtener cargos del equipo de liderazgo
    $sqlCargos = "SELECT CodNivelesCargos, Nombre 
                  FROM NivelesCargos 
                  WHERE EquipoLiderazgo = 1 
                  ORDER BY Peso ASC";
    $stmtCargos = $conn->prepare($sqlCargos);
    $stmtCargos->execute();
    $cargos = $stmtCargos->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener proyectos activos (fecha_fin >= HOY)
    $sqlProyectos = "SELECT 
                        p.id,
                        p.nombre,
                        p.descripcion,
                        p.CodNivelesCargos,
                        p.fecha_inicio,
                        p.fecha_fin,
                        p.orden_visual,
                        p.es_subproyecto,
                        p.proyecto_padre_id,
                        p.esta_expandido,
                        nc.Nombre as cargo_nombre
                    FROM gestion_proyectos_proyectos p
                    INNER JOIN NivelesCargos nc ON p.CodNivelesCargos = nc.CodNivelesCargos
                    WHERE p.fecha_fin >= CURDATE()
                    ORDER BY nc.Peso ASC, p.orden_visual ASC";
    $stmtProyectos = $conn->prepare($sqlProyectos);
    $stmtProyectos->execute();
    $proyectos = $stmtProyectos->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'cargos' => $cargos,
        'proyectos' => $proyectos
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
