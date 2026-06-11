<?php
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $codOperario = (int)$_POST['cod_operario'];
    $codSucursal = $_POST['cod_sucursal'];
    $fechaFalta = $_POST['fecha_falta'];
    
    if (!$codOperario || !$codSucursal || !$fechaFalta) {
        throw new Exception('Parámetros incompletos para verificar falta');
    }
    
    // VALIDACIÓN NUEVA: Verificar que la fecha no sea posterior a liquidación
    if (fechaPosteriorLiquidacion($codOperario, $fechaFalta)) {
        echo json_encode([
            'existe_falta' => false,
            'error' => 'No se puede registrar falta: El colaborador fue liquidado antes de esta fecha'
        ]);
        exit;
    }
    
    // VALIDACIÓN NUEVA: Verificar que el operario tenga contrato
    if (!operarioTieneContrato($codOperario)) {
        echo json_encode([
            'existe_falta' => false,
            'error' => 'Este colaborador no tiene registro de contrato. Por favor contactar con el área de RH.'
        ]);
        exit;
    }
    
    // EXCEPCIÓN: Quienes tienen permiso de aprobación pueden registrar sin validar marcaciones, en cualquier sucursal.
    require_once '../../../core/permissions/permissions.php';
    $cargoOperario = $_SESSION['cargo_cod'] ?? 0;
    $puedeAprobar = tienePermiso('registro_vacaciones', 'aprobar', $cargoOperario);
    
    // 1. Verificar si hay marcaciones para ese día
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_marcaciones 
        FROM marcaciones 
        WHERE CodOperario = ? 
        AND sucursal_codigo = ?
        AND fecha = ?
        AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL)
    ");
    $stmt->execute([$codOperario, $codSucursal, $fechaFalta]);
    $result = $stmt->fetch();
    $tieneMarcaciones = ($result && $result['total_marcaciones'] > 0);
    
    // 2. Verificar si el operario tenía horario programado para ese día
    $diaSemana = date('N', strtotime($fechaFalta)); // 1=lunes, 7=domingo
    
    // Mapear a los nombres de columna
    $dias = [
        1 => 'lunes',
        2 => 'martes', 
        3 => 'miercoles',
        4 => 'jueves',
        5 => 'viernes',
        6 => 'sabado',
        7 => 'domingo'
    ];
    $diaColumna = $dias[$diaSemana];
    
    // Obtener el horario programado para ese día
    $stmt = $conn->prepare("
        SELECT 
            {$diaColumna}_estado as estado,
            {$diaColumna}_entrada as hora_entrada,
            {$diaColumna}_salida as hora_salida
        FROM HorariosSemanalesOperaciones hso
        JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
        WHERE hso.cod_operario = ?
        AND hso.cod_sucursal = ?
        AND ? BETWEEN ss.fecha_inicio AND ss.fecha_fin
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $codSucursal, $fechaFalta]);
    $horario = $stmt->fetch();
    
    // MODIFICADO: Permitir registro si el estado es Activo, Otra.Tienda, Subsidio o Vacaciones
    $estadosPermitidos = ['Activo', 'Otra.Tienda', 'Subsidio', 'Vacaciones'];
    
    // Si el usuario puede aprobar, se le permite registrar aunque haya marcaciones, en cualquier sucursal.
    if ($puedeAprobar) {
        echo json_encode([
            'existe_falta' => true,
            'tiene_marcaciones' => $tieneMarcaciones,
            'tiene_horario_permitido' => true
        ]);
        exit;
    }

    $tieneHorarioPermitido = ($horario && in_array($horario['estado'], $estadosPermitidos));

    // Es una falta real estándar si NO tiene marcaciones Y tiene un horario permitido
    $existeFalta = (!$tieneMarcaciones && $tieneHorarioPermitido);
    
    echo json_encode([
        'existe_falta' => $existeFalta,
        'tiene_marcaciones' => $tieneMarcaciones,
        'tiene_horario_permitido' => $tieneHorarioPermitido
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
