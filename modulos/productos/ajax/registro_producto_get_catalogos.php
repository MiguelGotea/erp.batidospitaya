<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $accion = isset($_GET['accion']) ? $_GET['accion'] : 'todos';
    
    if ($accion === 'subgrupos') {
        // Obtener subgrupos de un grupo específico
        $idGrupo = isset($_GET['id_grupo']) ? (int)$_GET['id_grupo'] : 0;
        
        $sql = "SELECT id, nombre 
                FROM subgrupo_presentacion_producto 
                WHERE id_grupo_presentacion_producto = :id_grupo 
                ORDER BY nombre";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id_grupo' => $idGrupo]);
        $subgrupos = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'subgrupos' => $subgrupos
        ]);
        exit();
    }
    
    // Obtener todos los catálogos
    
    // Productos Maestros
    $sqlMaestros = "SELECT id, Nombre FROM producto_maestro ORDER BY Nombre";
    $stmtMaestros = $conn->query($sqlMaestros);
    $productosMaestros = $stmtMaestros->fetchAll();
    
    // Unidades
    $sqlUnidades = "SELECT id, nombre FROM unidad_producto ORDER BY nombre";
    $stmtUnidades = $conn->query($sqlUnidades);
    $unidades = $stmtUnidades->fetchAll();
    
    // Grupos
    $sqlGrupos = "SELECT id, nombre FROM grupo_presentacion_producto ORDER BY nombre";
    $stmtGrupos = $conn->query($sqlGrupos);
    $grupos = $stmtGrupos->fetchAll();
    
    // Tipos de Receta
    $sqlTipos = "SELECT id, nombre, descripcion FROM tipo_receta_producto ORDER BY nombre";
    $stmtTipos = $conn->query($sqlTipos);
    $tiposReceta = $stmtTipos->fetchAll();
    
    // Productos Presentación (para componentes)
    $sqlProductos = "SELECT pp.id, pp.Nombre, pp.SKU, up.nombre as unidad
                     FROM producto_presentacion pp
                     LEFT JOIN unidad_producto up ON pp.id_unidad_producto = up.id
                     ORDER BY pp.Nombre";
    $stmtProductos = $conn->query($sqlProductos);
    $productosPresent = $stmtProductos->fetchAll();
    
    echo json_encode([
        'success' => true,
        'productos_maestros' => $productosMaestros,
        'unidades' => $unidades,
        'grupos' => $grupos,
        'tipos_receta' => $tiposReceta,
        'productos_presentacion' => $productosPresent
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar catálogos: ' . $e->getMessage()
    ]);
}
?>