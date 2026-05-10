<?php
require_once '../../../core/auth/auth.php';
// require_once '../../../core/helpers/funciones.php'; // Ya se incluye en auth.php

require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

// Verificar que sea petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;
// Verificar permisos (Creación/Aprobación de feriados)
if (!tienePermiso('gestion_feriados', 'aprobar', $cargoOperario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

try {
    $codOperario = $_POST['cod_operario'] ?? null;
    $fechaFeriado = $_POST['fecha_feriado'] ?? null;
    $estado = $_POST['estado'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    
    if (!$codOperario || !$fechaFeriado || !$estado) {
        throw new Exception('Datos incompletos');
    }
    
    // Validar estado
    $estadosPermitidos = ['Pagado', 'Descansado', 'Compensado'];
    if (!in_array($estado, $estadosPermitidos)) {
        throw new Exception('Estado no válido');
    }
    
    // Obtener el último código de contrato del operario
    $codContrato = obtenerUltimoCodigoContrato($codOperario);
    
    // Obtener información del feriado
    $feriadoInfo = obtenerInformacionFeriadoParaOperario($codOperario, $fechaFeriado);
    
    if (!$feriadoInfo) {
        throw new Exception('No se encontró información del feriado para esta fecha');
    }
    
    // Obtener horas trabajadas (si hay marcación)
    $horasTrabajadas = 0;
    $idMarcacion = null;
    
    if ($feriadoInfo['tiene_marcacion']) {
        $idMarcacion = $feriadoInfo['id_marcacion'];
        $horasTrabajadas = $feriadoInfo['horas_trabajadas'];
    }
    
    // Insertar nuevo registro
    $sql = "INSERT INTO FeriadosStatus (
                id_marcacion, 
                cod_operario, 
                fecha_feriado, 
                estado, 
                observaciones, 
                horas_trabajadas,
                cod_contrato,
                creado_por, 
                actualizado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $resultado = $stmt->execute([
        $idMarcacion,
        $codOperario,
        $fechaFeriado,
        $estado,
        $observaciones,
        $horasTrabajadas,
        $codContrato,
        $_SESSION['usuario_id'],
        $_SESSION['usuario_id']
    ]);
    
    if ($resultado) {
        $nuevoId = $conn->lastInsertId();
        
        // Registrar en log
        registrarLogSistema(
            'CREAR_FERIADO',
            "Nuevo registro de feriado creado para operario {$codOperario}",
            [
                'feriado_id' => $nuevoId,
                'cod_operario' => $codOperario,
                'fecha' => $fechaFeriado,
                'estado' => $estado,
                'usuario_id' => $_SESSION['usuario_id']
            ]
        );
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registro de feriado creado correctamente',
            'id' => $nuevoId,
            'estado' => $estado
        ]);
    } else {
        throw new Exception('Error al crear el registro del feriado');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

/**
 * Obtiene información del feriado para un operario en una fecha específica
 */
function obtenerInformacionFeriadoParaOperario($codOperario, $fecha) {
    global $conn;
    
    // Obtener las sucursales del operario
    $sqlSucursales = "SELECT Sucursal FROM AsignacionNivelesCargos WHERE CodOperario = ? AND (Fin IS NULL OR Fin >= ?)";
    $stmtSucursales = $conn->prepare($sqlSucursales);
    $stmtSucursales->execute([$codOperario, $fecha]);
    $sucursales = $stmtSucursales->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($sucursales)) {
        return null;
    }
    
    // Buscar marcación para esa fecha
    $placeholders = str_repeat('?,', count($sucursales) - 1) . '?';
    $sqlMarcacion = "SELECT id, hora_ingreso, hora_salida FROM marcaciones 
                     WHERE CodOperario = ? AND fecha = ? AND sucursal_codigo IN ($placeholders) 
                     LIMIT 1";
    
    $params = array_merge([$codOperario, $fecha], $sucursales);
    $stmtMarcacion = $conn->prepare($sqlMarcacion);
    $stmtMarcacion->execute($params);
    $marcacion = $stmtMarcacion->fetch();
    
    // Calcular horas trabajadas si hay marcación
    $horasTrabajadas = 0;
    if ($marcacion && $marcacion['hora_ingreso'] && $marcacion['hora_salida']) {
        $entrada = new DateTime($marcacion['hora_ingreso']);
        $salida = new DateTime($marcacion['hora_salida']);
        $diferencia = $salida->diff($entrada);
        $horasTrabajadas = $diferencia->h + ($diferencia->i / 60);
    }
    
    return [
        'id_marcacion' => $marcacion ? $marcacion['id'] : null,
        'tiene_marcacion' => !empty($marcacion),
        'horas_trabajadas' => $horasTrabajadas
    ];
}

function registrarLogSistema($tipo, $mensaje, $datos = []) {
    global $conn;
    
    try {
        $sql = "INSERT INTO logs_sistema (tipo, mensaje, datos, fecha) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$tipo, $mensaje, json_encode($datos)]);
    } catch (Exception $e) {
        error_log("Error al registrar log: " . $e->getMessage());
    }
}