<?php
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    // Obtener estructura
    $input = json_decode(file_get_contents('php://input'), true);
    $estructura = isset($input['estructura']) ? $input['estructura'] : null;
    
    if (!$estructura) {
        throw new Exception('Estructura no proporcionada');
    }
    
    // Construir SQL
    $sql = construirSQL($estructura);
    
    // Validar SQL
    if (!validarSQL($sql)) {
        throw new Exception('Consulta SQL inválida o insegura');
    }
    
    // Ejecutar con límite de tiempo
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    
    $datos = $stmt->fetchAll();
    
    // Calcular estadísticas adicionales
    $estadisticas = calcularEstadisticas($datos, $estructura);
    
    echo json_encode([
        'success' => true,
        'data' => $datos,
        'estadisticas' => $estadisticas,
        'total_registros' => count($datos)
    ]);
    
} catch (Exception $e) {
    error_log('Error ia_graficos_ejecutar_query: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Construir SQL seguro - FUNCIÓN CORREGIDA
 */
function construirSQL($estructura) {
    // SELECT
    $metricaColumna = escaparColumna($estructura['metrica_columna']);
    $metricaFuncion = strtoupper($estructura['metrica_funcion']);
    $dimensionColumna = escaparColumna($estructura['dimension_columna']);
    
    // Funciones SQL permitidas
    $funcionesPermitidas = ['SUM', 'AVG', 'COUNT', 'MIN', 'MAX', 'COUNT DISTINCT'];
    if (!in_array($metricaFuncion, $funcionesPermitidas)) {
        throw new Exception('Función SQL no permitida');
    }
    
    // Construir SELECT
    if ($metricaFuncion === 'COUNT DISTINCT') {
        $selectMetrica = "COUNT(DISTINCT $metricaColumna)";
    } else {
        $selectMetrica = "$metricaFuncion($metricaColumna)";
    }
    
    $sql = "SELECT \n";
    $sql .= "  $dimensionColumna as label,\n";
    $sql .= "  $selectMetrica as value\n";
    $sql .= "FROM VentasGlobalesAccessCSV\n";
    
    // WHERE
    $condiciones = [];
    
    // Rango temporal
    if (isset($estructura['rango_temporal'])) {
        $rango = $estructura['rango_temporal'];
        $dias = intval($rango['cantidad']);
        $condiciones[] = "Fecha >= DATE_SUB(CURDATE(), INTERVAL $dias DAY)";
    }
    
    // Filtros
    if (!empty($estructura['filtros'])) {
        foreach ($estructura['filtros'] as $filtro) {
            $columna = escaparColumna($filtro['columna']);
            $operador = escaparOperador($filtro['operador']);
            $valor = escaparValor($filtro['valor']);
            
            $condiciones[] = "$columna $operador $valor";
        }
    }
    
    if (!empty($condiciones)) {
        $sql .= "WHERE " . implode("\n  AND ", $condiciones) . "\n";
    }
    
    // GROUP BY
    $sql .= "GROUP BY $dimensionColumna\n";
    
    // ORDER BY - CORREGIDO PARA ORDENAR FECHAS
    // Si es dimensión temporal, ordenar por la columna de fecha/hora
    if (isset($estructura['dimension_tipo']) && $estructura['dimension_tipo'] === 'temporal') {
        $sql .= "ORDER BY $dimensionColumna ASC\n";
    } else {
        // Si no es temporal, ordenar por valor descendente
        $sql .= "ORDER BY value DESC\n";
    }
    
    // LIMIT
    $sql .= "LIMIT 100";
    
    return $sql;
}

/**
 * Escapar nombre de columna
 */
function escaparColumna($columna) {
    // Whitelist de columnas permitidas
    $columnasPermitidas = [
        'Fecha', 'Hora', 'Semana', 'CodPedido', 'CodCliente', 'CodProducto',
        'Precio', 'Cantidad', 'Propina', 'Puntos', 'MontoFactura',
        'Tipo', 'NombreGrupo', 'DBBatidos_Nombre', 'Medida',
        'Modalidad', 'Sucursal_Nombre', 'Caja', 'Anulado', 'MotivoAnulado',
        'Motorizado', 'Observaciones'
    ];
    
    if (!in_array($columna, $columnasPermitidas)) {
        throw new Exception('Columna no permitida: ' . $columna);
    }
    
    return "`$columna`";
}

/**
 * Escapar operador
 */
function escaparOperador($operador) {
    $operadoresPermitidos = ['=', '>', '<', '>=', '<=', '!=', 'LIKE', 'IN', 'NOT IN'];
    
    if (!in_array($operador, $operadoresPermitidos)) {
        throw new Exception('Operador no permitido');
    }
    
    return $operador;
}

/**
 * Escapar valor
 */
function escaparValor($valor) {
    global $conn;
    
    if (is_numeric($valor)) {
        return $valor;
    }
    
    return $conn->quote($valor);
}

/**
 * Validar SQL
 */
function validarSQL($sql) {
    // Palabras prohibidas
    $prohibidas = [
        'DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER', 'CREATE', 
        'TRUNCATE', 'EXEC', 'EXECUTE', '--', '/*', '*/', 'xp_'
    ];
    
    $sqlUpper = strtoupper($sql);
    
    foreach ($prohibidas as $palabra) {
        if (strpos($sqlUpper, $palabra) !== false) {
            return false;
        }
    }
    
    return true;
}

/**
 * Calcular estadísticas adicionales
 */
function calcularEstadisticas($datos, $estructura) {
    if (empty($datos)) {
        return null;
    }
    
    $valores = array_column($datos, 'value');
    $total = array_sum($valores);
    $promedio = count($valores) > 0 ? $total / count($valores) : 0;
    $maximo = max($valores);
    $minimo = min($valores);
    
    $formato = $estructura['formato_metrica'];
    
    return [
        'total' => [
            'valor' => $total,
            'label' => 'Total',
            'formato' => $formato
        ],
        'promedio' => [
            'valor' => $promedio,
            'label' => 'Promedio',
            'formato' => $formato
        ],
        'maximo' => [
            'valor' => $maximo,
            'label' => 'Máximo',
            'formato' => $formato
        ],
        'minimo' => [
            'valor' => $minimo,
            'label' => 'Mínimo',
            'formato' => $formato
        ],
        'registros' => [
            'valor' => count($datos),
            'label' => 'Registros',
            'formato' => 'numero'
        ]
    ];
}
?>