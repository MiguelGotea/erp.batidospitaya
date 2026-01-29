<?php
/**
 * AJAX endpoint to fetch monthly sales KPIs per branch.
 * Displays Sales Meta, Real Sales, and Variation for each month of the current year.
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

// Verify session
$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

$añoActual = date('Y');
$mesActual = (int) date('m');
$hoy = date('Y-m-d');

try {
    // 1. Fetch active branches grouped by VMTAP
    $stmtSucursales = $conn->prepare("
        SELECT codigo, nombre, VMTAP 
        FROM sucursales 
        WHERE activa = 1 AND sucursal = 1
        ORDER BY VMTAP DESC, nombre ASC
    ");
    $stmtSucursales->execute();
    $sucursales = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);

    $mesesData = [];
    $mesesNombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    // 2. Prepare result structure
    $resultado = [
        'sucursales' => $sucursales,
        'meses' => [],
        'actual' => [
            'año' => $añoActual,
            'mes' => $mesActual
        ]
    ];

    // 3. For each month of the year
    for ($mes = 1; $mes <= 12; $mes++) {
        $mesStr = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $primerDiaMes = "$añoActual-$mesStr-01";
        $ultimoDiaMes = date('Y-m-t', strtotime($primerDiaMes));

        // If current month, exclude today
        if ($mes == $mesActual) {
            $ultimoDiaCalculo = date('Y-m-d', strtotime('-1 day'));
            if ($ultimoDiaCalculo < $primerDiaMes) {
                $ultimoDiaCalculo = null; // No data yet for current month if it's the 1st
            }
        } elseif ($mes > $mesActual) {
            $ultimoDiaCalculo = null; // Future month
        } else {
            $ultimoDiaCalculo = $ultimoDiaMes; // Past month
        }

        $datosMes = [
            'nombre' => $mesesNombres[$mes - 1],
            'codigo' => $mesStr . '-' . substr($añoActual, 2),
            'valores' => []
        ];

        foreach ($sucursales as $sucursal) {
            $codSucursal = $sucursal['codigo'];

            // Get Meta
            // Meta table uses cod_sucursal (int) which relates to sucursales.id
            // Need to get sucursales.id first or join.
            // Looking at previous research: fk_ventas_meta_sucursal FOREIGN KEY (cod_sucursal) REFERENCES sucursales (id)
            // But VentasGlobalesAccessCSV uses 'local' which is likely sucursales.codigo

            // Join meta with sucursal to use codigo
            $stmtMeta = $conn->prepare("
                SELECT SUM(vm.meta) as total_meta
                FROM ventas_meta vm
                JOIN sucursales s ON vm.cod_sucursal = s.id
                WHERE s.codigo = ? AND vm.fecha >= ? AND vm.fecha <= ?
            ");
            $stmtMeta->execute([$codSucursal, $primerDiaMes, $ultimoDiaMes]);
            $metaRow = $stmtMeta->fetch();
            $metaVal = $metaRow['total_meta'] ? round($metaRow['total_meta'] / 1000, 1) : 0;

            // Get Real Sales
            $realVal = 0;
            if ($ultimoDiaCalculo) {
                $stmtReal = $conn->prepare("
                    SELECT SUM(Precio) as total_real
                    FROM VentasGlobalesAccessCSV
                    WHERE local = ? AND Fecha >= ? AND Fecha <= ? AND Anulado = 0
                ");
                $stmtReal->execute([$codSucursal, $primerDiaMes, $ultimoDiaCalculo]);
                $realRow = $stmtReal->fetch();
                $realVal = $realRow['total_real'] ? round($realRow['total_real'] / 1000, 1) : 0;
            }

            // Calculate Variation
            $varPct = 0;
            if ($metaVal > 0) {
                $varPct = round((($realVal - $metaVal) / $metaVal) * 100, 1);
            }

            $datosMes['valores'][$codSucursal] = [
                'meta' => $metaVal,
                'real' => $realVal,
                'var' => $varPct
            ];
        }

        $resultado['meses'][$mes] = $datosMes;
    }

    echo json_encode(['success' => true, 'data' => $resultado]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>