<?php
require_once '../../../core/auth/auth.php';

header('Content-Type: application/json');

// Verificar sesión
$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

// Obtener sucursales del líder
$sucursalesLider = obtenerSucursalesLider($usuario['CodOperario']);

if (empty($sucursalesLider)) {
    echo json_encode(['success' => false, 'message' => 'Sin sucursales asignadas']);
    exit;
}

// Usar la primera sucursal del líder
$sucursalCodigo = $sucursalesLider[0]['codigo'];
$fechaActual = date('Y-m-d');
$primerDiaMes = date('Y-m-01');
$diaActual = (int) date('d');

// Obtener nombre del mes en español
$meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$mesActual = $meses[(int) date('m') - 1];

try {
    // Obtener meta del mes (cualquier día del mes actual tiene la misma meta)
    $stmtMeta = $conn->prepare("
        SELECT meta 
        FROM ventas_meta 
        WHERE cod_sucursal = ? 
        AND fecha >= ? 
        AND fecha <= ?
        LIMIT 1
    ");
    $stmtMeta->execute([$sucursalCodigo, $primerDiaMes, $fechaActual]);
    $metaData = $stmtMeta->fetch();
    $metaMensual = $metaData ? round($metaData['meta'] / 1000, 2) : 0;

    // Obtener ventas por día (del día actual hacia atrás hasta el día 1)
    $datos = [];
    $totalVentas = 0;
    $totalMeta = 0;
    $diasConDatos = 0;

    for ($dia = $diaActual; $dia >= 1; $dia--) {
        $fecha = date('Y-m-' . str_pad($dia, 2, '0', STR_PAD_LEFT));

        // Ventas reales del día
        $stmtVentas = $conn->prepare("
            SELECT COALESCE(SUM(Precio), 0) AS Total_Ventas
            FROM VentasGlobalesAccessCSV
            WHERE local = ? 
            AND Anulado = 0
            AND Fecha = ?
        ");
        $stmtVentas->execute([$sucursalCodigo, $fecha]);
        $ventasData = $stmtVentas->fetch();
        $ventasReales = $ventasData ? ($ventasData['Total_Ventas'] / 1000) : 0;

        // Meta del día
        $stmtMetaDia = $conn->prepare("
            SELECT meta 
            FROM ventas_meta 
            WHERE cod_sucursal = ? 
            AND fecha = ?
        ");
        $stmtMetaDia->execute([$sucursalCodigo, $fecha]);
        $metaDiaData = $stmtMetaDia->fetch();
        $metaDia = $metaDiaData ? ($metaDiaData['meta'] / 1000) : 0;

        // Calcular cumplimiento
        $cumplimiento = $metaDia > 0 ? ($ventasReales / $metaDia) * 100 : 0;

        // Determinar color semáforo
        if ($cumplimiento < 85) {
            $color = 'rojo';
        } elseif ($cumplimiento >= 100) {
            $color = 'verde';
        } else {
            $color = 'amarillo';
        }

        $datos[] = [
            'dia' => $dia,
            'fecha' => $fecha,
            'ventas_reales' => round($ventasReales, 1),
            'meta' => round($metaDia, 1),
            'cumplimiento' => round($cumplimiento, 0),
            'color' => $color
        ];

        // Acumular para promedio del mes
        $totalVentas += $ventasReales;
        $totalMeta += $metaDia;
        $diasConDatos++;
    }

    // Calcular promedio del mes (columna "Ene", "Feb", etc.)
    $promedioVentas = $diasConDatos > 0 ? $totalVentas / $diasConDatos : 0;
    $promedioMeta = $diasConDatos > 0 ? $totalMeta / $diasConDatos : 0;
    $cumplimientoMes = $promedioMeta > 0 ? ($promedioVentas / $promedioMeta) * 100 : 0;

    // Color del semáforo del mes
    if ($cumplimientoMes < 85) {
        $colorMes = 'rojo';
    } elseif ($cumplimientoMes >= 100) {
        $colorMes = 'verde';
    } else {
        $colorMes = 'amarillo';
    }

    echo json_encode([
        'success' => true,
        'meta_mensual' => $metaMensual,
        'mes_actual' => $mesActual,
        'datos' => $datos,
        'promedio_mes' => [
            'ventas_reales' => round($promedioVentas, 1),
            'cumplimiento' => round($cumplimientoMes, 0),
            'color' => $colorMes
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en get_ventas_balance.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener datos de ventas: ' . $e->getMessage()
    ]);
}
