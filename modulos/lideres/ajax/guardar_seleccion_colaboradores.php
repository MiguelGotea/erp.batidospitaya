<?php
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$sucursal = $input['sucursal'] ?? null;
$semanaNumero = $input['semana'] ?? null;
$colaboradores = $input['colaboradores'] ?? [];
$accion = $input['accion'] ?? 'actualizar';

if (!$sucursal || !$semanaNumero) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $semana = obtenerSemanaPorNumero($semanaNumero);
    if (!$semana) {
        echo json_encode(['success' => false, 'message' => 'Semana no válida']);
        exit;
    }
    
    // Inicializar sesiones
    if (!isset($_SESSION['operarios_seleccionados'])) {
        $_SESSION['operarios_seleccionados'] = [];
    }
    if (!isset($_SESSION['operarios_seleccionados'][$semana['id']])) {
        $_SESSION['operarios_seleccionados'][$semana['id']] = [];
    }
    
    // Inicializar lista de operarios a ELIMINAR de BD
    if (!isset($_SESSION['operarios_a_eliminar'])) {
        $_SESSION['operarios_a_eliminar'] = [];
    }
    if (!isset($_SESSION['operarios_a_eliminar'][$semana['id']])) {
        $_SESSION['operarios_a_eliminar'][$semana['id']] = [];
    }
    
    $colaboradorActualizado = null;
    
    if ($accion === 'actualizar') {
        // Actualizar toda la selección
        $seleccionados = [];
        foreach ($colaboradores as $colab) {
            $seleccionados[$colab['id']] = [
                'nombre' => $colab['nombre'],
                'es_adicional' => $colab['es_adicional'] ?? false
            ];
        }
        $_SESSION['operarios_seleccionados'][$semana['id']][$sucursal] = $seleccionados;
        
    } elseif ($accion === 'agregar') {
        // Agregar un solo colaborador
        $colab = $colaboradores[0] ?? null;
        if ($colab) {
            $_SESSION['operarios_seleccionados'][$semana['id']][$sucursal][$colab['id']] = [
                'nombre' => $colab['nombre'],
                'es_adicional' => $colab['es_adicional'] ?? true
            ];
            
            // Si estaba marcado para eliminar, quitarlo de la lista
            if (isset($_SESSION['operarios_a_eliminar'][$semana['id']][$sucursal][$colab['id']])) {
                unset($_SESSION['operarios_a_eliminar'][$semana['id']][$sucursal][$colab['id']]);
            }
            
            $colaboradorActualizado = [
                'id' => $colab['id'],
                'nombre' => $colab['nombre'],
                'codigo' => $colab['codigo'],
                'es_adicional' => $colab['es_adicional'] ?? true
            ];
        }
        
    } elseif ($accion === 'quitar') {
        // Quitar un solo colaborador
        $colab = $colaboradores[0] ?? null;
        if ($colab) {
            $colabId = $colab['id'];
            
            // Quitar de la selección
            if (isset($_SESSION['operarios_seleccionados'][$semana['id']][$sucursal][$colabId])) {
                unset($_SESSION['operarios_seleccionados'][$semana['id']][$sucursal][$colabId]);
            }
            
            // Marcar para ELIMINACIÓN de la BD cuando se guarden los horarios
            $_SESSION['operarios_a_eliminar'][$semana['id']][$sucursal][$colabId] = [
                'id' => $colabId,
                'nombre' => $colab['nombre'],
                'fecha_marcado' => date('Y-m-d H:i:s')
            ];
            
            $colaboradorActualizado = [
                'id' => $colabId,
                'nombre' => $colab['nombre'],
                'accion' => 'quitar'
            ];
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Selección actualizada correctamente',
        'total_seleccionados' => count($_SESSION['operarios_seleccionados'][$semana['id']][$sucursal] ?? []),
        'colaborador_actualizado' => $colaboradorActualizado,
        'accion' => $accion
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}