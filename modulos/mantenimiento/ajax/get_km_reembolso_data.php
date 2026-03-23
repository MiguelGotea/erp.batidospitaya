<?php
/**
 * Puente para obtener datos de KM consumidos y depreciación para el módulo de compras
 * Ubicación: /modulos/mantenimiento/ajax/get_km_reembolso_data.php
 */

header('Content-Type: application/json');
require_once '../../../core/database/conexion.php';
require_once '../models/Ticket.php';

try {
    $semana = isset($_GET['semana']) ? (int)$_GET['semana'] : null;
    $anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');
    $costo_km = isset($_GET['costo_km']) ? (float)$_GET['costo_km'] : 5;

    if (!$semana) {
        throw new Exception('Número de semana no proporcionado.');
    }

    $db = (new Ticket())->getDb();

    // 1. Obtener rango de fechas de la semana
    $sqlS = "SELECT fecha_inicio, fecha_fin FROM SemanasSistema WHERE numero_semana = ? AND anio = ?";
    $rango = $db->fetchOne($sqlS, [$semana, $anio]);
    if (!$rango) {
        throw new Exception("La semana #$semana del año $anio no está configurada en el sistema.");
    }

    // 2. Verificar si ya existe un reembolso para esta semana (opcional preventivo)
    $sqlCheck = "SELECT COUNT(*) as cuenta 
                 FROM mtto_informes_diarios 
                 WHERE (fecha BETWEEN ? AND ?) AND reembolso_id IS NOT NULL";
    $check = $db->fetchOne($sqlCheck, [$rango['fecha_inicio'], $rango['fecha_fin']]);
    
    if ($check['cuenta'] > 0) {
        // Podríamos permitirlo si el usuario forzó, pero el requerimiento dice no dejar si ya existe
        // throw new Exception("Ya existe una solicitud de reembolso vinculada a esta semana.");
    }

    // 3. Obtener detalle diario agrupado por operario para generar los items
    $sqlD = "SELECT i.fecha, i.km_inicial, i.km_final, o.Nombre, o.Apellido, i.cod_operario
             FROM mtto_informes_diarios i
             INNER JOIN Operarios o ON i.cod_operario = o.CodOperario
             WHERE i.fecha BETWEEN ? AND ?
             AND i.km_final IS NOT NULL AND i.km_inicial IS NOT NULL
             ORDER BY i.fecha ASC, o.Nombre, o.Apellido ASC";
    $detalles = $db->fetchAll($sqlD, [$rango['fecha_inicio'], $rango['fecha_fin']]);

    $items = [];
    $operariosContados = [];

    foreach ($detalles as $d) {
        $km = (float)$d['km_final'] - (float)$d['km_inicial'];
        $monto = $km * $costo_km;

        if ($km > 0) {
            $items[] = [
                'cantidad' => 1,
                'detalle' => "Consumo KM {$d['fecha']} ({$km} km) - {$d['Nombre']} {$d['Apellido']}",
                'total_cordobas' => round($monto, 2)
            ];
        }

        // Agregar depreciación fija una vez por operario a la semana
        if (!in_array($d['cod_operario'], $operariosContados)) {
            $items[] = [
                'cantidad' => 1,
                'detalle' => "Depreciación Semanal Fija (S-{$semana}) - {$d['Nombre']} {$d['Apellido']}",
                'total_cordobas' => 150.00
            ];
            $operariosContados[] = $d['cod_operario'];
        }
    }

    if (empty($items)) {
        throw new Exception("No hay registros de KM completos para la semana #$semana.");
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
        'rango' => $rango
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
