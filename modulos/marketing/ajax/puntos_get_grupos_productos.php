<?php
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    // 1. Obtener grupos activos (asumiendo que no hay columna activo en grupos, traemos los que tienen productos activos)
    $sqlGrupos = "SELECT id, nombre FROM grupo_presentacion_producto ORDER BY nombre ASC";
    $stmtGrupos = $conn->query($sqlGrupos);
    $grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener productos vendibles o activos
    // Usamos Activo = 'SI' y cruzamos con su grupo
    $sqlProductos = "SELECT p.id, p.SKU as sku, p.Nombre as nombre, p.id_subgrupo_presentacion_producto as id_grupo 
                     FROM producto_presentacion p
                     WHERE p.Activo = 'SI' AND p.es_vendible = 'SI'
                     ORDER BY p.Nombre ASC";
                     // Nota: Si el id_grupo está ligado a subgrupo en tu BD, ajusta esto. En tu SQL es id_subgrupo_presentacion_producto.
                     
    $stmtProductos = $conn->query($sqlProductos);
    $productos = $stmtProductos->fetchAll(PDO::FETCH_ASSOC);

    // Mapeamos
    $mapa = [];
    foreach ($grupos as $g) {
        $mapa[$g['id']] = [
            'id' => $g['id'],
            'nombre' => $g['nombre'],
            'productos' => []
        ];
    }

    foreach ($productos as $p) {
        $idGrupo = $p['id_grupo'];
        if ($idGrupo && isset($mapa[$idGrupo])) {
            $mapa[$idGrupo]['productos'][] = [
                'id' => $p['id'],
                'sku' => $p['sku'],
                'nombre' => $p['nombre']
            ];
        }
    }

    // Filtrar grupos que no tienen productos
    $mapaLimpio = array_filter($mapa, function($g) {
        return count($g['productos']) > 0;
    });

    echo json_encode(['success' => true, 'data' => $mapaLimpio]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error BD: ' . $e->getMessage()]);
}
?>
