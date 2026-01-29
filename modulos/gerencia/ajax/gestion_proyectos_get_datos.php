<?php
// gestion_proyectos_get_datos.php
// Carga proyectos activos y cargos para el diagrama Gantt

header('Content-Type: application/json; charset=utf-8');
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';


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

    // 2. Obtener proyectos del año en curso
    // Proyectos padre: se muestran si fecha_inicio O fecha_fin está en el año actual
    // Subproyectos: se incluyen todos (el filtrado se hace en frontend según visibilidad del padre)
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
                        COALESCE(p.color, parent.color) as color,
                        nc.Nombre as cargo_nombre,
                        (SELECT MIN(p2.fecha_inicio) 
                         FROM gestion_proyectos_proyectos p2 
                         LEFT JOIN gestion_proyectos_proyectos parent2 ON p2.proyecto_padre_id = parent2.id
                         WHERE p2.CodNivelesCargos = p.CodNivelesCargos 
                         AND COALESCE(p2.color, parent2.color) = COALESCE(p.color, parent.color)
                        ) as color_group_priority
                    FROM gestion_proyectos_proyectos p
                    INNER JOIN NivelesCargos nc ON p.CodNivelesCargos = nc.CodNivelesCargos
                    LEFT JOIN gestion_proyectos_proyectos parent ON p.proyecto_padre_id = parent.id
                    WHERE (p.es_subproyecto = 1) 
                       OR (p.es_subproyecto = 0 AND (YEAR(p.fecha_inicio) = YEAR(CURDATE()) OR YEAR(p.fecha_fin) = YEAR(CURDATE())))
                    ORDER BY nc.Peso ASC, color_group_priority ASC, color ASC, p.orden_visual ASC";
    $stmtProyectos = $conn->prepare($sqlProyectos);
    $stmtProyectos->execute();
    $proyectos = $stmtProyectos->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'cargos' => $cargos,
        'proyectos' => $proyectos
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>