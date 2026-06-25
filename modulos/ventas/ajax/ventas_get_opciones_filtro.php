<?php
// ventas_get_opciones_filtro.php
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $columna = isset($_POST['columna']) ? trim($_POST['columna']) : '';
    
    if (empty($columna)) {
        throw new Exception('Columna no especificada');
    }
    
    $columnasPermitidas = [
        'Sucursal_Nombre',
        'NombrePromocion',
        'Medida',
        'Modalidad',
        'Anulado'
    ];
    
    if (!in_array($columna, $columnasPermitidas)) {
        throw new Exception('Columna no válida para filtro de lista');
    }
    
    // Manejo especial para Anulado - obtener valores 0 y 1
    if ($columna === 'Anulado') {
        $opciones = [
            ['valor' => '0', 'texto' => 'NO'],
            ['valor' => '1', 'texto' => 'SÍ']
        ];
        
        echo json_encode([
            'success' => true,
            'opciones' => $opciones
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    if ($columna === 'NombrePromocion') {
        $sql = "SELECT DISTINCT Nombre as valor
                FROM promociones_access_csv
                WHERE Nombre IS NOT NULL
                AND Nombre != ''
                ORDER BY Nombre ASC";
    } else {
        $sql = "SELECT DISTINCT v.$columna as valor
                FROM VentasGlobalesAccessCSV v
                LEFT JOIN DBBatidos b ON v.CodProducto = b.CodBatido
                WHERE (b.CodGrupo IS NULL OR (b.CodGrupo != 25 AND b.CodGrupo != 11))
                AND v.$columna IS NOT NULL
                AND v.$columna != ''
                ORDER BY v.$columna ASC";
    }
$stmt = $conn->prepare($sql);
$stmt->execute();
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$opciones = [];
foreach ($resultados as $row) {
    $valor = $row['valor'];
    $texto = $valor;
    
    $opciones[] = [
        'valor' => $valor,
        'texto' => $texto
    ];
}

echo json_encode([
    'success' => true,
    'opciones' => $opciones
], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
echo json_encode([
'success' => false,
'message' => $e->getMessage()
], JSON_UNESCAPED_UNICODE);
}