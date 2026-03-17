<?php
/**
 * ia_config_api_get_datos.php
 * Carga de datos de proveedores de IA con soporte para filtros, orden y paginación.
 */
require_once '../../../core/auth/auth.php'; // Incluye conexion.php y funciones.php
header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso (mismo que la página principal)
if (!verificarPermiso('ia_config_api')) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit();
}

$pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 1;
$registrosPorPagina = isset($_POST['registros_por_pagina']) ? (int)$_POST['registros_por_pagina'] : 25;
$filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
$orden = isset($_POST['orden']) ? json_decode($_POST['orden'], true) : ['columna' => 'id', 'direccion' => 'asc'];

$offset = ($pagina - 1) * $registrosPorPagina;

$where = ["1=1"];
$params = [];

// Aplicar filtros
if (!empty($filtros)) {
    // Filtro por Proveedor (Texto)
    if (!empty($filtros['proveedor'])) {
        $where[] = "proveedor LIKE :proveedor";
        $params[':proveedor'] = '%' . $filtros['proveedor'] . '%';
    }

    // Filtro por Correo (Texto)
    if (!empty($filtros['cuenta_correo'])) {
        $where[] = "cuenta_correo LIKE :cuenta_correo";
        $params[':cuenta_correo'] = '%' . $filtros['cuenta_correo'] . '%';
    }

    // Filtro por Activa (Lista)
    if (!empty($filtros['activa'])) {
        $valores = [];
        foreach ($filtros['activa'] as $idx => $val) {
            $key = ":activa_" . $idx;
            $valores[] = $key;
            $params[$key] = ($val === 'SI' ? 1 : 0);
        }
        $where[] = "activa IN (" . implode(',', $valores) . ")";
    }

    // Filtro por Estado Límite (Lista)
    if (!empty($filtros['estado'])) {
        $valores = [];
        foreach ($filtros['estado'] as $idx => $val) {
            $key = ":estado_" . $idx;
            $valores[] = $key;
            $params[$key] = ($val === 'AGOTADA' ? 1 : 0);
        }
        $where[] = "limite_alcanzado_hoy IN (" . implode(',', $valores) . ")";
    }

    // Filtro por Fecha (Daterange)
    if (!empty($filtros['ultimo_uso'])) {
        if (!empty($filtros['ultimo_uso']['desde'])) {
            $where[] = "ultimo_uso >= :uso_desde";
            $params[':uso_desde'] = $filtros['ultimo_uso']['desde'] . ' 00:00:00';
        }
        if (!empty($filtros['ultimo_uso']['hasta'])) {
            $where[] = "ultimo_uso <= :uso_hasta";
            $params[':uso_hasta'] = $filtros['ultimo_uso']['hasta'] . ' 23:59:59';
        }
    }
}

$whereSql = implode(" AND ", $where);

// Ordenamiento
$columnaOrden = 'id';
$permitidas = ['id', 'proveedor', 'cuenta_correo', 'activa', 'limite_alcanzado_hoy', 'ultimo_uso'];
if (in_array($orden['columna'], $permitidas)) {
    $columnaOrden = $orden['columna'];
}
$dirOrden = ($orden['direccion'] === 'desc') ? 'DESC' : 'ASC';

try {
    // Contar total
    $sqlTotal = "SELECT COUNT(*) FROM ia_proveedores_api WHERE $whereSql";
    $stmtTotal = $conn->prepare($sqlTotal);
    $stmtTotal->execute($params);
    $totalRegistros = $stmtTotal->fetchColumn();

    // Obtener datos
    $sql = "SELECT * FROM ia_proveedores_api 
            WHERE $whereSql 
            ORDER BY $columnaOrden $dirOrden 
            LIMIT $offset, $registrosPorPagina";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'datos' => $datos,
        'total_registros' => (int)$totalRegistros,
        'pagina_actual' => $pagina,
        'total_paginas' => ceil($totalRegistros / $registrosPorPagina)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
