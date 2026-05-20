<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permiso general
if (!tienePermiso('registro_vacaciones', 'vista', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['error' => 'No tiene permisos para acceder a esta herramienta']);
    exit;
}

$esRH = tienePermiso('registro_vacaciones', 'ver_todas_sucursales', $cargoOperario);
$puedeAprobar = tienePermiso('registro_vacaciones', 'aprobar', $cargoOperario);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'obtener_operarios':
            $codSucursal = $_GET['sucursal'] ?? '';
            if (empty($codSucursal)) {
                throw new Exception('Debe especificar una sucursal');
            }
            
            $fechaReferencia = $_GET['fecha'] ?? date('Y-m-d');
            if (empty($fechaReferencia)) {
                $fechaReferencia = date('Y-m-d');
            }
            
            // Traer colaboradores filtrando por fecha de liquidación activa y rango de asignación
            $stmt = $conn->prepare("
                SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2
                FROM Operarios o
                INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                LEFT JOIN (
                    -- Obtener el último contrato de cada operario
                    SELECT c1.cod_operario, c1.fecha_liquidacion
                    FROM Contratos c1
                    INNER JOIN (
                        SELECT cod_operario, MAX(CodContrato) as max_contrato
                        FROM Contratos
                        GROUP BY cod_operario
                    ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
                ) c ON o.CodOperario = c.cod_operario
                WHERE anc.Sucursal = ?
                AND o.Operativo = 1
                AND anc.Fecha <= ?
                AND (anc.Fin IS NULL OR anc.Fin = '0000-00-00' OR anc.Fin >= ?)
                AND o.CodOperario NOT IN (
                    SELECT DISTINCT anc2.CodOperario 
                    FROM AsignacionNivelesCargos anc2
                    WHERE anc2.CodNivelesCargos = 27
                    AND anc2.Fecha <= ?
                    AND (anc2.Fin IS NULL OR anc2.Fin = '0000-00-00' OR anc2.Fin >= ?)
                )
                -- Filtrar liquidados
                AND (
                    c.fecha_liquidacion IS NULL 
                    OR c.fecha_liquidacion = '0000-00-00'
                    OR c.fecha_liquidacion > ?
                )
                ORDER BY o.Nombre, o.Apellido
            ");
            $stmt->execute([$codSucursal, $fechaReferencia, $fechaReferencia, $fechaReferencia, $fechaReferencia, $fechaReferencia]);
            $operarios = $stmt->fetchAll();
            echo json_encode($operarios);
            break;

        case 'verificar_falta_real':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            $codOperario = (int)$_POST['cod_operario'];
            $codSucursal = $_POST['cod_sucursal'];
            $fechaFalta = $_POST['fecha_falta'];
            
            if (!$codOperario || !$codSucursal || !$fechaFalta) {
                throw new Exception('Parámetros incompletos');
            }
            
            // Verificar liquidación
            if (fechaPosteriorLiquidacion($codOperario, $fechaFalta)) {
                echo json_encode([
                    'existe_falta' => false,
                    'error' => 'El colaborador fue liquidado antes de esta fecha'
                ]);
                exit;
            }
            
            // Verificar contrato
            if (!operarioTieneContrato($codOperario)) {
                echo json_encode([
                    'existe_falta' => false,
                    'error' => 'El colaborador no tiene registro de contrato activo. Contactar con RH.'
                ]);
                exit;
            }
            
            // Excepción para sucursales 6 y 18 con rol de aprobador
            $esSucursalEspecial = in_array($codSucursal, ['6', '18']);
            if ($esSucursalEspecial && $puedeAprobar) {
                echo json_encode(['existe_falta' => true]);
                exit;
            }
            
            $existeFalta = verificarFaltaReal($codOperario, $codSucursal, $fechaFalta);
            echo json_encode(['existe_falta' => $existeFalta]);
            break;

        case 'guardar_registro':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            $codOperario = (int)$_POST['cod_operario'];
            $codSucursal = $_POST['cod_sucursal'];
            $fechaInicio = $_POST['fecha_inicio'];
            $fechaFin = $_POST['fecha_fin'];
            $observaciones = $_POST['observaciones'] ?? '';
            $categoriaFalta = $_POST['categoria_falta'] ?? 'vacaciones'; // 'vacaciones', 'subsidio', 'falta_permiso'
            $tipoFaltaOriginal = $_POST['tipo_falta'] ?? 'Vacaciones';
            
            if (!$codOperario || !$codSucursal || !$fechaInicio || !$fechaFin) {
                throw new Exception('Todos los campos son obligatorios');
            }
            
            if ($fechaInicio > $fechaFin) {
                throw new Exception('La fecha de inicio no puede ser mayor que la fecha fin');
            }
            
            // Determinar si es una falta manual (no vacaciones/subsidio)
            $esFaltaManual = ($categoriaFalta === 'falta_permiso');
            
            // Validaciones específicas para faltas manuales de tipo normal
            if ($esFaltaManual) {
                $esRRHH = ($esRH || $puedeAprobar);
                $fechaMaximaPermitida = $esRRHH ? date('Y-m-d') : date('Y-m-d', strtotime('-1 day'));
                
                if ($fechaInicio > $fechaMaximaPermitida || $fechaFin > $fechaMaximaPermitida) {
                    $mensajeExcepcion = $esRRHH ? 
                        'Para faltas/permisos no se permiten fechas futuras. Solo hasta: ' . formatoFechaCorta($fechaMaximaPermitida) : 
                        'Para faltas/permisos no se permiten fechas futuras ni el día actual. Solo hasta: ' . formatoFechaCorta($fechaMaximaPermitida);
                    throw new Exception($mensajeExcepcion);
                }
            }
            
            // Validar foto obligatoria
            if (!isset($_FILES['foto_falta']) || $_FILES['foto_falta']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Debe subir una foto como evidencia obligatoria');
            }
            
            $foto = $_FILES['foto_falta'];
            if ($foto['size'] > 5 * 1024 * 1024) {
                throw new Exception('La foto de evidencia no debe exceder los 5MB');
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($foto['type'], $allowedTypes)) {
                throw new Exception('Solo se permiten imágenes JPEG, PNG o GIF');
            }
            
            // Obtener el código de contrato
            $codContrato = null;
            $stmt_contrato = $conn->prepare("
                SELECT CodContrato 
                FROM Contratos 
                WHERE cod_operario = ? 
                ORDER BY inicio_contrato DESC, CodContrato DESC 
                LIMIT 1
            ");
            $stmt_contrato->execute([$codOperario]);
            $contrato = $stmt_contrato->fetch();
            if ($contrato) {
                $codContrato = $contrato['CodContrato'];
            }
            
            // Obtener todos los días en el rango
            $diasRango = [];
            $fechaActual = new DateTime($fechaInicio);
            $fechaFinObj = new DateTime($fechaFin);
            while ($fechaActual <= $fechaFinObj) {
                $diasRango[] = $fechaActual->format('Y-m-d');
                $fechaActual->modify('+1 day');
            }
            
            if (empty($diasRango)) {
                throw new Exception('No hay días seleccionados en el rango');
            }
            
            // Determinar tipo de falta final y observaciones según permisos
            // Si es aprobador, se sube directo con su tipo real.
            // Si es líder común, se sube como 'Pendiente' con prefijo en observaciones.
            if ($puedeAprobar) {
                $tipoFaltaFinal = $tipoFaltaOriginal;
                $obsFinal = $observaciones;
                $obsRRHH = $observaciones;
            } else {
                $tipoFaltaFinal = 'Pendiente';
                $prefijo = '';
                if ($categoriaFalta === 'vacaciones') {
                    $prefijo = '[Vacaciones] ';
                } elseif ($categoriaFalta === 'subsidio') {
                    $prefijo = '[Subsidio: ' . str_replace('_', ' ', $tipoFaltaOriginal) . '] ';
                } else {
                    $prefijo = '[Falta/Permiso: ' . str_replace('_', ' ', $tipoFaltaOriginal) . '] ';
                }
                $obsFinal = $prefijo . $observaciones;
                $obsRRHH = null;
            }
            
            $porcentajePago = obtenerPorcentajePagoTipoFalta($tipoFaltaFinal);
            
            // Crear carpeta y subir imagen
            $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $nombreFoto = 'hibrido_' . $codOperario . '_' . date('YmdHis') . '.' . $extension;
            $rutaRelativa = '/uploads/faltas_manual/' . $nombreFoto;
            $uploadDir = __DIR__ . '/../../../uploads/faltas_manual/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $rutaCompleta = $uploadDir . $nombreFoto;
            if (!move_uploaded_file($foto['tmp_name'], $rutaCompleta)) {
                throw new Exception('Error al guardar la foto en el servidor.');
            }
            
            $registrosExitosos = 0;
            $errores = [];
            
            foreach ($diasRango as $dia) {
                // Verificar si ya existe un registro para este día
                $stmt = $conn->prepare("
                    SELECT id FROM faltas_manual 
                    WHERE cod_operario = ? AND fecha_falta = ?
                    LIMIT 1
                ");
                $stmt->execute([$codOperario, $dia]);
                if ($stmt->fetch()) {
                    $errores[] = "Ya existe un registro para el día " . formatoFechaCorta($dia);
                    continue;
                }
                
                // Verificar falta real si es tipo falta manual y NO es sucursal especial o no es aprobador
                if ($esFaltaManual) {
                    $esSucursalEspecial = in_array($codSucursal, ['6', '18']);
                    if (!$esSucursalEspecial || !$puedeAprobar) {
                        if (!verificarFaltaReal($codOperario, $codSucursal, $dia)) {
                            $errores[] = "No aplica falta real para el día " . formatoFechaCorta($dia) . " (no programado o tiene marcas)";
                            continue;
                        }
                    }
                }
                
                // Insertar
                $stmt = $conn->prepare("
                    INSERT INTO faltas_manual (
                        cod_operario, fecha_falta, cod_sucursal, 
                        tipo_falta, observaciones, observaciones_rrhh, foto_path, registrado_por, cod_contrato, porcentaje_pago
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $ok = $stmt->execute([
                    $codOperario,
                    $dia,
                    $codSucursal,
                    $tipoFaltaFinal,
                    $obsFinal,
                    $obsRRHH,
                    $rutaRelativa,
                    $_SESSION['usuario_id'],
                    $codContrato,
                    $porcentajePago
                ]);
                
                if ($ok) {
                    $registrosExitosos++;
                } else {
                    $errores[] = "Error de BD al insertar el día " . formatoFechaCorta($dia);
                }
            }
            
            if ($registrosExitosos > 0) {
                $mensaje = "Se registraron $registrosExitosos días correctamente";
                if (!empty($errores)) {
                    $mensaje .= ". Omitidos: " . implode(', ', array_slice($errores, 0, 2));
                }
                echo json_encode(['success' => true, 'message' => $mensaje]);
            } else {
                if (file_exists($rutaCompleta)) {
                    @unlink($rutaCompleta);
                }
                throw new Exception("No se pudo registrar ningún día. Errores: " . implode('; ', $errores));
            }
            break;

        case 'editar_aprobar':
            if (!$puedeAprobar) {
                throw new Exception('No tiene permisos para editar o aprobar registros');
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            $id = (int)$_POST['id'];
            $tipoFalta = $_POST['tipo_falta'];
            $observaciones_rrhh = $_POST['observaciones_rrhh'] ?? '';
            
            if (empty($observaciones_rrhh)) {
                throw new Exception('Las observaciones de RRHH son obligatorias al editar/aprobar');
            }
            
            $stmt = $conn->prepare("SELECT cod_operario, fecha_falta FROM faltas_manual WHERE id = ?");
            $stmt->execute([$id]);
            $falta = $stmt->fetch();
            
            if (!$falta) {
                throw new Exception('Registro no encontrado');
            }
            
            // Validar liquidación y contrato
            if (fechaPosteriorLiquidacion($falta['cod_operario'], $falta['fecha_falta'])) {
                throw new Exception('No se puede editar: posterior a la liquidación del colaborador');
            }
            if (!operarioTieneContrato($falta['cod_operario'])) {
                throw new Exception('El colaborador no tiene un contrato registrado.');
            }
            
            $porcentajePago = obtenerPorcentajePagoTipoFalta($tipoFalta);
            
            $stmt = $conn->prepare("
                UPDATE faltas_manual 
                SET tipo_falta = ?, 
                    observaciones_rrhh = ?,
                    porcentaje_pago = ?,
                    actualizado_por = ?,
                    fecha_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $tipoFalta,
                $observaciones_rrhh,
                $porcentajePago,
                $_SESSION['usuario_id'],
                $id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Registro actualizado y aprobado correctamente']);
            break;

        case 'eliminar_rechazar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            
            $id = (int)$_POST['id'];
            
            // Cargar falta
            $stmt = $conn->prepare("SELECT registrado_por, foto_path FROM faltas_manual WHERE id = ?");
            $stmt->execute([$id]);
            $falta = $stmt->fetch();
            
            if (!$falta) {
                throw new Exception('Registro no encontrado');
            }
            
            // Los líderes solo pueden eliminar sus propias solicitudes si están en 'Pendiente'
            if (!$puedeAprobar) {
                $stmt_check = $conn->prepare("SELECT tipo_falta FROM faltas_manual WHERE id = ? AND registrado_por = ?");
                $stmt_check->execute([$id, $_SESSION['usuario_id']]);
                $miFalta = $stmt_check->fetch();
                if (!$miFalta || $miFalta['tipo_falta'] !== 'Pendiente') {
                    throw new Exception('No tiene permisos para eliminar este registro.');
                }
            }
            
            // Eliminar registro
            $stmt = $conn->prepare("DELETE FROM faltas_manual WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Registro eliminado/rechazado correctamente']);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Obtiene el porcentaje de pago para un tipo de falta específico
 */
function obtenerPorcentajePagoTipoFalta($tipoFalta)
{
    global $conn;
    if ($tipoFalta === 'Pendiente') return 0;
    
    $stmt = $conn->prepare("
        SELECT porcentaje_pago 
        FROM tipos_falta 
        WHERE codigo = ? 
        LIMIT 1
    ");
    $stmt->execute([$tipoFalta]);
    $result = $stmt->fetch();

    return $result ? $result['porcentaje_pago'] : 0;
}

/**
 * Verifica si realmente hubo falta
 */
function verificarFaltaReal($codOperario, $codSucursal, $fechaFalta)
{
    global $conn;

    // 1. Marcaciones
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

    if ($result && $result['total_marcaciones'] > 0) {
        return false;
    }

    // 2. Horario semanal programado
    $diaSemana = date('N', strtotime($fechaFalta)); // 1=lunes, 7=domingo
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

    $stmt = $conn->prepare("
        SELECT 
            {$diaColumna}_estado as estado
        FROM HorariosSemanalesOperaciones hso
        JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
        WHERE hso.cod_operario = ?
        AND hso.cod_sucursal = ?
        AND ? BETWEEN ss.fecha_inicio AND ss.fecha_fin
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $codSucursal, $fechaFalta]);
    $horario = $stmt->fetch();

    $estadosPermitidos = ['Activo', 'Otra.Tienda', 'Subsidio', 'Vacaciones'];

    if (!$horario || !in_array($horario['estado'], $estadosPermitidos)) {
        return false;
    }

    return true;
}
?>
