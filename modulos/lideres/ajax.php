<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../includes/auth.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Para acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    if ($postAction === 'verificar_falta_real') {
        $action = 'verificar_falta_real';
    }
}

try {
    switch ($action) {
        case 'obtener_operarios_sucursal':
            $codSucursal = $_GET['sucursal'] ?? null;
            $fechaFalta = $_GET['fecha_falta'] ?? null;
            
            if (!$codSucursal) {
                throw new Exception('Sucursal no especificada');
            }
            
            // Si NO se proporciona fecha, usar fecha de ayer por defecto
            if (!$fechaFalta) {
                $fechaFalta = date('Y-m-d', strtotime('-1 day'));
            }
            
            // NUEVA LÓGICA: Obtener operarios considerando fecha de liquidación
            // Usar la función que filtra por AsignacionNivelesCargos Y fecha_liquidacion
            $operarios = obtenerOperariosSucursalPorFecha($codSucursal, $fechaFalta);
            
            // Agregar información de contrato para validaciones en frontend
            foreach ($operarios as &$operario) {
                $estadoContrato = obtenerMensajeEstadoContrato($operario['CodOperario']);
                $operario['tiene_contrato'] = operarioTieneContrato($operario['CodOperario']);
                $operario['estado_contrato'] = $estadoContrato['tipo'];
                $operario['mensaje_contrato'] = $estadoContrato['mensaje'];
            }
            
            echo json_encode($operarios);
            break;
            
        case 'consultar_marcacion_falta':
            $codOperario = $_GET['cod_operario'] ?? null;
            $codSucursal = $_GET['cod_sucursal'] ?? null;
            $fecha = $_GET['fecha'] ?? null;
            
            if (!$codOperario || !$codSucursal || !$fecha) {
                throw new Exception('Parámetros incompletos');
            }
            
            // Obtener horario programado
            $horarioProgramado = obtenerHorarioProgramado($codOperario, $codSucursal, $fecha);
            
            // Obtener marcaciones
            $marcaciones = obtenerMarcaciones($codOperario, $codSucursal, $fecha);
            
            echo json_encode([
                'horario_programado' => $horarioProgramado,
                'marcaciones' => $marcaciones
            ]);
            break;
        
        case 'obtener_operarios_sucursal_simple':
            if ($_GET['sucursal'] === 'auto') {
                // Obtener sucursal del usuario actual
                $sucursalesUsuario = obtenerSucursalesUsuario($_SESSION['usuario_id']);
                if (empty($sucursalesUsuario)) {
                    echo json_encode([]);
                    exit;
                }
                $codSucursal = $sucursalesUsuario[0]['codigo'];
            } else {
                $codSucursal = $_GET['sucursal'];
            }
            
            $hoy = date('Y-m-d');
            
            $stmt = $conn->prepare("
                SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2
                FROM Operarios o
                INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                WHERE anc.Sucursal = ?
                AND o.Operativo = 1
                AND (anc.Fin IS NULL OR anc.Fin >= ?)
                AND o.CodOperario NOT IN (
                    SELECT DISTINCT anc2.CodOperario 
                    FROM AsignacionNivelesCargos anc2
                    WHERE anc2.CodNivelesCargos = 27
                    AND (anc2.Fin IS NULL OR anc2.Fin >= ?)
                )
                ORDER BY o.Nombre, o.Apellido
            ");
            $stmt->execute([$codSucursal, $hoy, $hoy]);
            
            $operarios = $stmt->fetchAll();
            echo json_encode($operarios);
            break;
            
        case 'verificar_falta_real':
            // Esta acción viene por POST
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
                break;
            }
            
            // VALIDACIÓN NUEVA: Verificar que el operario tenga contrato
            if (!operarioTieneContrato($codOperario)) {
                echo json_encode([
                    'existe_falta' => false,
                    'error' => 'Este colaborador no tiene registro de contrato. Por favor contactar con el área de RH.'
                ]);
                break;
            }
            
            // EXCEPCIÓN: Para sucursales 6 y 18, RRHH puede registrar sin validación
            $esSucursalEspecial = in_array($codSucursal, ['6', '18']);
            $esRH = verificarAccesoCargo([13,28, 39, 30, 37]);
            
            if ($esSucursalEspecial && $esRH) {
                // Para sucursales especiales y RRHH, siempre retornar true (permite registro)
                echo json_encode(['existe_falta' => true]);
                break;
            }
            
            // Función para verificar si realmente hubo falta
            function verificarFaltaReal($codOperario, $codSucursal, $fechaFalta) {
                global $conn;
                
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
                
                // Si hay marcaciones, no es una falta real
                if ($result && $result['total_marcaciones'] > 0) {
                    return false;
                }
                
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
                
                // Si no hay horario programado o el día no está en estados permitidos, no es una falta real
                if (!$horario || !in_array($horario['estado'], $estadosPermitidos)) {
                    return false;
                }
                
                // 3. Si no hay marcaciones Y tenía horario programado con estado permitido, entonces es una falta real
                return true;
            }
            
            $existeFalta = verificarFaltaReal($codOperario, $codSucursal, $fechaFalta);
            
            echo json_encode(['existe_falta' => $existeFalta]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>