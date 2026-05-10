<?php
require_once '../../core/auth/auth.php';
header('Content-Type: application/json');

verificarAutenticacion();

$sucursal = $_GET['sucursal'] ?? null;
$semanaNumero = $_GET['semana'] ?? null;

if (!$sucursal || !$semanaNumero) {
    echo json_encode([]);
    exit;
}

try {
    $semana = obtenerSemanaPorNumero($semanaNumero);
    if (!$semana) {
        echo json_encode([]);
        exit;
    }
    
    global $conn;
    
    // 1. Obtener operarios que YA TIENEN horario guardado para esta semana/sucursal
    $operariosConHorario = obtenerOperariosSucursalConHorario($sucursal, $semana['id']);
    
    // 2. Obtener operarios asignados a la sucursal (todos los activos)
    $operariosAsignados = obtenerOperariosSucursal($sucursal, $semana['fecha_inicio'], $semana['fecha_fin']);
    
    $resultado = [];
    
    // 3. NUEVA LÓGICA: Si NO HAY operarios con horario guardado, usar todos los asignados
    if (empty($operariosConHorario)) {
        // Usar todos los operarios asignados como seleccionados
        foreach ($operariosAsignados as $op) {
            $resultado[] = [
                'id' => $op['CodOperario'],
                'nombre' => trim($op['Nombre'] . ' ' . ($op['Nombre2'] ?? '') . ' ' . $op['Apellido'] . ' ' . ($op['Apellido2'] ?? '')),
                'codigo' => $op['CodOperario'],
                'es_adicional' => false // Son asignados, no adicionales
            ];
        }
    } else {
        // Si ya hay horarios guardados, mostrar solo esos
        foreach ($operariosConHorario as $op) {
            $resultado[] = [
                'id' => $op['CodOperario'],
                'nombre' => trim($op['Nombre'] . ' ' . ($op['Nombre2'] ?? '') . ' ' . $op['Apellido'] . ' ' . ($op['Apellido2'] ?? '')),
                'codigo' => $op['CodOperario'],
                'es_adicional' => false
            ];
        }
    }
    
    // 4. Agregar operarios adicionales de la sesión (si existen)
    if (isset($_SESSION['operarios_seleccionados'][$semana['id']][$sucursal])) {
        $operariosSesion = $_SESSION['operarios_seleccionados'][$semana['id']][$sucursal];
        foreach ($operariosSesion as $opId => $opData) {
            // Verificar que no esté duplicado
            $existe = false;
            foreach ($resultado as $r) {
                if ($r['id'] == $opId) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $resultado[] = [
                    'id' => $opId,
                    'nombre' => $opData['nombre'],
                    'codigo' => $opId,
                    'es_adicional' => $opData['es_adicional'] ?? true
                ];
            }
        }
    }
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    error_log("Error en obtener_seleccionados_actual.php: " . $e->getMessage());
    echo json_encode([]);
}