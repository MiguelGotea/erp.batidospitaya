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
function construirSQL($estructura)
{
    // SELECT
    $metricaColumna = escaparColumna($estructura['metrica_columna'] ?? '');
    $metricaFuncion = strtoupper($estructura['metrica_funcion'] ?? 'SUM');
    $dimensionColumna = escaparColumna($estructura['dimension_columna'] ?? '');

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

    // Rango temporal — soporta múltiples tipos de período
    if (isset($estructura['rango_temporal'])) {
        $rango = $estructura['rango_temporal'];
        $tipo = strtolower(trim($rango['tipo'] ?? 'dias'));
        $cantidad = max(1, intval($rango['cantidad'] ?? 7));

        switch ($tipo) {
            case 'dias':
                $condiciones[] = "Fecha >= DATE_SUB(CURDATE(), INTERVAL $cantidad DAY)";
                break;
            case 'semanas':
                $diasTotal = $cantidad * 7;
                $condiciones[] = "Fecha >= DATE_SUB(CURDATE(), INTERVAL $diasTotal DAY)";
                break;
            case 'meses':
                $condiciones[] = "Fecha >= DATE_SUB(CURDATE(), INTERVAL $cantidad MONTH)";
                break;
            case 'semana_actual':
                $condiciones[] = "YEARWEEK(Fecha, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'mes_actual':
                $condiciones[] = "YEAR(Fecha) = YEAR(CURDATE()) AND MONTH(Fecha) = MONTH(CURDATE()) AND Fecha <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'mes_anterior':
                $condiciones[] = "Fecha BETWEEN DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND LAST_DAY(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                break;
            case 'trimestre':
            case 'trimestre_actual':
                $condiciones[] = "QUARTER(Fecha) = QUARTER(CURDATE()) AND YEAR(Fecha) = YEAR(CURDATE()) AND Fecha <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'anio':
            case 'año':
            case 'anio_actual':
                $condiciones[] = "YEAR(Fecha) = YEAR(CURDATE()) AND Fecha <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            default:
                $condiciones[] = "Fecha >= DATE_SUB(CURDATE(), INTERVAL $cantidad DAY)";
        }
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
function escaparColumna($columna)
{
    global $conn;
    static $columnasPermitidas = null;

    if ($columnasPermitidas === null) {
        // Whitelist base de columnas permitidas
        $columnasPermitidas = [
            'Fecha',
            'Hora',
            'Semana',
            'CodPedido',
            'CodCliente',
            'CodProducto',
            'Precio',
            'Cantidad',
            'Propina',
            'Puntos',
            'MontoFactura',
            'Tipo',
            'NombreGrupo',
            'DBBatidos_Nombre',
            'Medida',
            'Modalidad',
            'Sucursal_Nombre',
            'Caja',
            'Anulado',
            'MotivoAnulado',
            'Motorizado',
            'Observaciones'
        ];

        try {
            // Cargar también las columnas del diccionario
            $stmt = $conn->query("SELECT columna_bd FROM ia_graficos_diccionario_columnas WHERE activo = 1");
            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    if (!in_array($row['columna_bd'], $columnasPermitidas)) {
                        $columnasPermitidas[] = $row['columna_bd'];
                    }
                }
            }
        } catch (Exception $e) {
            // Ignorar y seguir con la lista base
        }
    }

    if (!in_array($columna, $columnasPermitidas)) {
        throw new Exception('Columna no permitida: ' . $columna);
    }

    return "`$columna`";
}

/**
 * Escapar operador
 */
function escaparOperador($operador)
{
    $operadoresPermitidos = [
        '=', '>', '<', '>=', '<=', '!=', '<>',
        'LIKE', 'NOT LIKE',
        'IN', 'NOT IN',
        'BETWEEN',
        'IS NULL', 'IS NOT NULL'
    ];

    $operadorUpper = strtoupper(trim($operador));

    if (!in_array($operadorUpper, $operadoresPermitidos)) {
        throw new Exception('Operador no permitido: ' . htmlspecialchars($operador));
    }

    return $operadorUpper;
}

/**
 * Escapar valor
 */
function escaparValor($valor)
{
    global $conn;

    // NULL / IS NULL / IS NOT NULL no llevan valor
    if ($valor === null || strtoupper(trim($valor ?? '')) === 'NULL') {
        return 'NULL';
    }

    if (is_numeric($valor)) {
        return $valor;
    }

    // Detectar expresiones SQL de fecha u otras funciones — NO encapsular entre comillas
    $expresionesSql = [
        'CURDATE()', 'NOW()', 'CURTIME()',
        'DATE_SUB(', 'DATE_ADD(', 'DATE_FORMAT(',
        'YEARWEEK(', 'YEAR(', 'MONTH(', 'DAY(', 'HOUR(', 'MINUTE(',
        'LAST_DAY(', 'INTERVAL ', 'DATE(',
        'STR_TO_DATE(', 'UNIX_TIMESTAMP('
    ];

    $valorUpper = strtoupper(trim($valor));
    foreach ($expresionesSql as $expr) {
        if (strpos($valorUpper, strtoupper($expr)) !== false) {
            // Es una expresión SQL válida — devolver sin comillas
            return $valor;
        }
    }

    // Valor de texto normal — escapar con PDO::quote()
    return $conn->quote($valor);
}

/**
 * Validar SQL
 */
function validarSQL($sql)
{
    // Palabras prohibidas
    $prohibidas = [
        'DROP',
        'DELETE',
        'UPDATE',
        'INSERT',
        'ALTER',
        'CREATE',
        'TRUNCATE',
        'EXEC',
        'EXECUTE',
        '--',
        '/*',
        '*/',
        'xp_'
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
function calcularEstadisticas($datos, $estructura)
{
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