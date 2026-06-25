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

            // Parámetro para incluir colaboradores de baja (solo para usuarios con puedeAprobar)
            $incluirBaja = ($puedeAprobar && ($_GET['incluir_baja'] ?? '0') === '1');

            if ($incluirBaja) {
                // Query extendido: incluye activos e históricos (de baja) de la sucursal
                // Los de baja aparecen al final con es_baja = 1
                $stmt = $conn->prepare("
                    SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2,
                        CASE 
                            WHEN o.Operativo = 0 THEN 1
                            WHEN c.fecha_liquidacion IS NOT NULL 
                                 AND c.fecha_liquidacion != '0000-00-00'
                                 AND c.fecha_liquidacion <= CURDATE() THEN 1
                            ELSE 0
                        END AS es_baja
                    FROM Operarios o
                    INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                    LEFT JOIN (
                        SELECT c1.cod_operario, c1.fecha_liquidacion
                        FROM Contratos c1
                        INNER JOIN (
                            SELECT cod_operario, MAX(CodContrato) as max_contrato
                            FROM Contratos
                            GROUP BY cod_operario
                        ) c2 ON c1.cod_operario = c2.cod_operario AND c1.CodContrato = c2.max_contrato
                    ) c ON o.CodOperario = c.cod_operario
                    WHERE anc.Sucursal = ?
                    AND o.CodOperario NOT IN (
                        SELECT DISTINCT anc2.CodOperario 
                        FROM AsignacionNivelesCargos anc2
                        WHERE anc2.CodNivelesCargos = 27
                    )
                    ORDER BY es_baja ASC, o.Nombre, o.Apellido
                ");
                $stmt->execute([$codSucursal]);
            } else {
                // Query normal: solo activos con contrato vigente
                $stmt = $conn->prepare("
                    SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2
                    FROM Operarios o
                    INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                    LEFT JOIN (
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
                    AND (
                        c.fecha_liquidacion IS NULL 
                        OR c.fecha_liquidacion = '0000-00-00'
                        OR c.fecha_liquidacion > ?
                    )
                    ORDER BY o.Nombre, o.Apellido
                ");
                $stmt->execute([$codSucursal, $fechaReferencia, $fechaReferencia, $fechaReferencia, $fechaReferencia, $fechaReferencia]);
            }

            $operarios = $stmt->fetchAll();
            echo json_encode($operarios);
            break;

        case 'obtener_cargo_operario':
            $codOperario = (int) ($_GET['cod_operario'] ?? 0);
            if (!$codOperario) {
                throw new Exception('Debe especificar un colaborador');
            }
            $stmtCargo = $conn->prepare("
                SELECT nc.Nombre AS cargo
                FROM AsignacionNivelesCargos anc
                JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                WHERE anc.CodOperario = ?
                AND (anc.Fin IS NULL OR anc.Fin = '0000-00-00' OR anc.Fin >= CURDATE())
                ORDER BY anc.Fecha DESC
                LIMIT 1
            ");
            $stmtCargo->execute([$codOperario]);
            $cargoRow = $stmtCargo->fetch();
            echo json_encode(['cargo' => $cargoRow ? $cargoRow['cargo'] : '']);
            break;

        case 'verificar_falta_real':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $codOperario = (int) $_POST['cod_operario'];
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

            $codOperario = (int) $_POST['cod_operario'];
            $codSucursal = $_POST['cod_sucursal'];
            $fechaInicio = $_POST['fecha_inicio'];
            $fechaFin = $_POST['fecha_fin'];
            $observaciones = $_POST['observaciones'] ?? '';
            $categoriaFalta = $_POST['categoria_falta'] ?? 'vacaciones'; // 'vacaciones', 'subsidio', 'falta_permiso'
            $tipoFaltaOriginal = $_POST['tipo_falta'] ?? 'Vacaciones';

            // Cantidad de días fraccional: solo aprobadores pueden enviar menos de 1
            $cantidadDiasInput = isset($_POST['cantidad_dias']) ? (float) $_POST['cantidad_dias'] : 1.0;
            if (!$puedeAprobar || $cantidadDiasInput <= 0 || $cantidadDiasInput > 1) {
                $cantidadDias = 1.0;
            } else {
                $cantidadDias = round($cantidadDiasInput, 2);
            }

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
            if ($puedeAprobar) {
                // Aprobador: registra directo con tipo real y aprobado=1
                $tipoFaltaFinal = $tipoFaltaOriginal;
                $aprobadoFinal = 1;
                $obsFinal = $observaciones;
                $obsRRHH = $observaciones;
            } elseif ($categoriaFalta === 'vacaciones') {
                // Líder registra vacaciones: guardar como Vacaciones + aprobado=0 (pendiente de RRHH)
                $tipoFaltaFinal = 'Vacaciones';
                $aprobadoFinal = 0;
                $obsFinal = $observaciones;
                $obsRRHH = null;
            } else {
                // Líder registra subsidio o falta: flujo existente, RRHH asigna tipo luego
                $tipoFaltaFinal = 'Pendiente';
                $aprobadoFinal = 1; // default, no necesita doble aprobación
                $prefijo = '';
                if ($categoriaFalta === 'subsidio') {
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
                // Verificar que la suma de ausencias para este día no supere 1.00
                $stmtSum = $conn->prepare("
                    SELECT COALESCE(SUM(cantidad_dias), 0) AS total_dias
                    FROM faltas_manual
                    WHERE cod_operario = ? AND fecha_falta = ?
                ");
                $stmtSum->execute([$codOperario, $dia]);
                $totalExistente = (float) $stmtSum->fetch()['total_dias'];

                if ($totalExistente + $cantidadDias > 1.0) {
                    $errores[] = "El día " . formatoFechaCorta($dia) . " ya tiene " . number_format($totalExistente, 2) . " día(s) registrado(s). Agregar " . number_format($cantidadDias, 2) . " día(s) superaría el límite de 1 día completo";
                    continue;
                }

                // Verificar falta real si es falta manual y el usuario NO tiene permiso de aprobador.
                // Los aprobadores pueden registrar falta aunque haya marcaciones, en cualquier sucursal.
                if ($esFaltaManual && !$puedeAprobar) {
                    if (!verificarFaltaReal($codOperario, $codSucursal, $dia)) {
                        $errores[] = "No aplica falta real para el día " . formatoFechaCorta($dia) . " (no programado o tiene marcas)";
                        continue;
                    }
                }

                // Insertar
                $stmt = $conn->prepare("
                    INSERT INTO faltas_manual (
                        cod_operario, fecha_falta, cod_sucursal, 
                        tipo_falta, aprobado, observaciones, observaciones_rrhh, foto_path, registrado_por, cod_contrato, porcentaje_pago, cantidad_dias
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $ok = $stmt->execute([
                    $codOperario,
                    $dia,
                    $codSucursal,
                    $tipoFaltaFinal,
                    $aprobadoFinal,
                    $obsFinal,
                    $obsRRHH,
                    $rutaRelativa,
                    $_SESSION['usuario_id'],
                    $codContrato,
                    $porcentajePago,
                    $cantidadDias
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

            $ids_raw = $_POST['id'] ?? '';
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
            $tipoFalta = $_POST['tipo_falta'];
            $observaciones_rrhh = $_POST['observaciones_rrhh'] ?? '';

            if (empty($ids)) {
                throw new Exception('ID no válido');
            }
            if (empty($observaciones_rrhh)) {
                throw new Exception('Las observaciones de RRHH son obligatorias al editar/aprobar');
            }

            $porcentajePago = obtenerPorcentajePagoTipoFalta($tipoFalta);

            // Cantidad de días fraccional en edición (solo aprobadores)
            $cantidadDiasEdit = isset($_POST['cantidad_dias']) ? (float) $_POST['cantidad_dias'] : 1.0;
            if ($cantidadDiasEdit <= 0 || $cantidadDiasEdit > 1) {
                $cantidadDiasEdit = 1.0;
            } else {
                $cantidadDiasEdit = round($cantidadDiasEdit, 2);
            }

            foreach ($ids as $id) {
                $stmt = $conn->prepare("SELECT cod_operario, fecha_falta FROM faltas_manual WHERE id = ?");
                $stmt->execute([$id]);
                $falta = $stmt->fetch();

                if (!$falta) {
                    continue;
                }

                // Validar liquidación y contrato (se omite para aprobadores: pueden editar registros de baja)
                if (!$puedeAprobar) {
                    if (fechaPosteriorLiquidacion($falta['cod_operario'], $falta['fecha_falta'])) {
                        throw new Exception('No se puede editar: posterior a la liquidación del colaborador');
                    }
                    if (!operarioTieneContrato($falta['cod_operario'])) {
                        throw new Exception('El colaborador no tiene un contrato registrado.');
                    }
                }

                // Verificar que la suma de otros registros del mismo día no supere 1.00
                $stmtSumEdit = $conn->prepare("
                    SELECT COALESCE(SUM(cantidad_dias), 0) AS total_dias
                    FROM faltas_manual
                    WHERE cod_operario = ? AND fecha_falta = ? AND id != ?
                ");
                $stmtSumEdit->execute([$falta['cod_operario'], $falta['fecha_falta'], $id]);
                $totalOtros = (float) $stmtSumEdit->fetch()['total_dias'];
                if ($totalOtros + $cantidadDiasEdit > 1.0) {
                    throw new Exception('La duración ingresada (' . number_format($cantidadDiasEdit, 2) . ' días) supera el límite: ya hay ' . number_format($totalOtros, 2) . ' día(s) registrado(s) en esa fecha para este colaborador');
                }

                $stmt = $conn->prepare("
                    UPDATE faltas_manual 
                    SET tipo_falta = ?, 
                        observaciones_rrhh = ?,
                        porcentaje_pago = ?,
                        cantidad_dias = ?,
                        actualizado_por = ?,
                        fecha_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $tipoFalta,
                    $observaciones_rrhh,
                    $porcentajePago,
                    $cantidadDiasEdit,
                    $_SESSION['usuario_id'],
                    $id
                ]);

                // Registrar auditoría y ajustar marcación en la marcación si el tipo implica presencia del colaborador
                registrarAuditoriaMarcacionFalta(
                    $falta['cod_operario'],
                    $falta['fecha_falta'],
                    $falta['cod_sucursal'],
                    $tipoFalta,
                    $id
                );
            }

            echo json_encode(['success' => true, 'message' => 'Registros actualizados y aprobados correctamente']);
            break;

        case 'aprobar_vacacion':
            if (!$puedeAprobar) {
                throw new Exception('No tiene permisos para aprobar solicitudes');
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            $ids_raw = $_POST['id'] ?? '';
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
            if (empty($ids))
                throw new Exception('ID no válido');

            $count = 0;
            foreach ($ids as $id) {
                $stmt = $conn->prepare("
                    UPDATE faltas_manual
                    SET aprobado = 1,
                        actualizado_por = ?,
                        fecha_actualizacion = NOW()
                    WHERE id = ?
                      AND tipo_falta = 'Vacaciones'
                      AND aprobado = 0
                ");
                $stmt->execute([$_SESSION['usuario_id'], $id]);
                if ($stmt->rowCount() > 0) $count++;
            }

            if ($count === 0) {
                throw new Exception('Registros no encontrados o ya fueron procesados anteriormente');
            }
            echo json_encode(['success' => true, 'message' => 'Vacación(es) aprobada(s) correctamente']);
            break;

        case 'rechazar_vacacion':
            if (!$puedeAprobar) {
                throw new Exception('No tiene permisos para rechazar solicitudes');
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }
            $ids_raw = $_POST['id'] ?? '';
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
            if (empty($ids))
                throw new Exception('ID no válido');

            $count = 0;
            foreach ($ids as $id) {
                // Obtener datos del operario y fecha para la reversión del ajuste de marcación
                $stmtData = $conn->prepare("SELECT cod_operario, fecha_falta FROM faltas_manual WHERE id = ?");
                $stmtData->execute([$id]);
                $falta = $stmtData->fetch();

                $stmt = $conn->prepare("
                    UPDATE faltas_manual
                    SET tipo_falta = 'No_Pagado',
                        aprobado   = 1,
                        actualizado_por = ?,
                        fecha_actualizacion = NOW()
                    WHERE id = ?
                      AND tipo_falta = 'Vacaciones'
                      AND aprobado = 0
                ");
                $stmt->execute([$_SESSION['usuario_id'], $id]);
                
                if ($stmt->rowCount() > 0) {
                    $count++;
                    if ($falta) {
                        revertirAjusteMarcacionPorFalta($falta['cod_operario'], $falta['fecha_falta'], $id);
                    }
                }
            }

            if ($count === 0) {
                throw new Exception('Registros no encontrados o ya fueron procesados anteriormente');
            }
            echo json_encode(['success' => true, 'message' => 'Solicitud(es) rechazada(s) y registrada(s) como No Pagado']);
            break;

        case 'eliminar_rechazar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $ids_raw = $_POST['id'] ?? '';
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
            if (empty($ids))
                throw new Exception('ID no válido');

            foreach ($ids as $id) {
                // Cargar falta
                $stmt = $conn->prepare("SELECT cod_operario, fecha_falta, registrado_por, foto_path FROM faltas_manual WHERE id = ?");
                $stmt->execute([$id]);
                $falta = $stmt->fetch();

                if (!$falta) {
                    continue;
                }

                // Los líderes solo pueden eliminar sus propias solicitudes si están en 'Pendiente' o vacacion pendiente
                if (!$puedeAprobar) {
                    $stmt_check = $conn->prepare("SELECT tipo_falta, aprobado FROM faltas_manual WHERE id = ? AND registrado_por = ?");
                    $stmt_check->execute([$id, $_SESSION['usuario_id']]);
                    $miFalta = $stmt_check->fetch();
                    $esPendientePropia = $miFalta && (
                        $miFalta['tipo_falta'] === 'Pendiente' ||
                        ($miFalta['tipo_falta'] === 'Vacaciones' && (int) $miFalta['aprobado'] === 0)
                    );
                    if (!$esPendientePropia) {
                        throw new Exception('No tiene permisos para eliminar uno o más registros seleccionados.');
                    }
                }

                // Revertir cualquier ajuste de marcación antes de eliminar el registro de falta
                revertirAjusteMarcacionPorFalta($falta['cod_operario'], $falta['fecha_falta'], $id);

                // Eliminar registro
                $stmt = $conn->prepare("DELETE FROM faltas_manual WHERE id = ?");
                $stmt->execute([$id]);
            }

            echo json_encode(['success' => true, 'message' => 'Registros eliminados/rechazados correctamente']);
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
    if ($tipoFalta === 'Pendiente')
        return 0;

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

/**
 * Registra auditoría y completa marcaciones parciales (entradas/salidas omitidas) 
 * con el horario programado cuando una falta es justificada (presencia confirmada).
 *
 * - Respalda las horas reales (pueden ser NULL) en hora_ingreso_original y hora_salida_original.
 * - Ajusta los campos vacíos (NULL) a las horas programadas del horario semanal.
 * - Si el tipo de falta ya no es justificado, revierte el ajuste.
 */
function registrarAuditoriaMarcacionFalta($codOperario, $fechaFalta, $codSucursal, $tipoFalta, $idFalta)
{
    global $conn;

    // Tipos de falta que implican presencia (omisión de marcación / ajustes)
    $tiposConPresencia = [
        'Omision_marcacion',
        'Atencion_medica',
        'Cita_medica_programada',
        'Ajuste_horario',
        'Compensacion_feria',
        'Compensacion_dia_trabajado',
    ];

    if (!in_array($tipoFalta, $tiposConPresencia)) {
        // Si no es un tipo de presencia justificada, revertir cualquier ajuste hecho por esta falta
        revertirAjusteMarcacionPorFalta($codOperario, $fechaFalta, $idFalta);
        return;
    }

    // Buscar marcación parcial ese día (solo entrada O solo salida, o incluso completa pero para vincular)
    $stmt = $conn->prepare("
        SELECT id, hora_ingreso, hora_salida, ajustado_por_tardanza, id_falta_ajuste
        FROM marcaciones
        WHERE CodOperario = ?
          AND fecha = ?
          AND sucursal_codigo = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fechaFalta, $codSucursal]);
    $marcacion = $stmt->fetch();

    if (!$marcacion) {
        return; // Sin marcación → nada que ajustar
    }

    // Ignorar si ya está ajustada por otra falta o tardanza
    if ($marcacion['ajustado_por_tardanza'] == 1 && $marcacion['id_falta_ajuste'] != $idFalta) {
        error_log("registrarAuditoriaMarcacionFalta: Marcación ID {$marcacion['id']} ya está ajustada por otra causa, se ignora");
        return;
    }

    // Obtener semana y horario programado
    $semana = obtenerSemanaPorFecha($fechaFalta);
    if (!$semana) {
        error_log("registrarAuditoriaMarcacionFalta: No se encontró semana para la fecha $fechaFalta");
        return;
    }

    $horarioProgramado = obtenerHorarioOperacionesPorDia($codOperario, $semana['id'], $codSucursal, $fechaFalta);
    if (!$horarioProgramado) {
        error_log("registrarAuditoriaMarcacionFalta: Sin horario programado para operario $codOperario en $fechaFalta");
        return;
    }

    $horaProgramadaEntrada = $horarioProgramado['hora_entrada'] ?? null;
    $horaProgramadaSalida  = $horarioProgramado['hora_salida'] ?? null;

    $original_ingreso = $marcacion['hora_ingreso'];
    $original_salida  = $marcacion['hora_salida'];

    // Completar los valores nulos con las horas programadas correspondientes
    $nuevo_ingreso = ($original_ingreso === null && !empty($horaProgramadaEntrada)) ? $horaProgramadaEntrada : $original_ingreso;
    $nuevo_salida  = ($original_salida  === null && !empty($horaProgramadaSalida))  ? $horaProgramadaSalida  : $original_salida;

    $stmt = $conn->prepare("
        UPDATE marcaciones
        SET hora_ingreso_original = ?,
            hora_salida_original  = ?,
            hora_ingreso          = ?,
            hora_salida           = ?,
            ajustado_por_tardanza = 1,
            id_falta_ajuste       = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $original_ingreso,
        $original_salida,
        $nuevo_ingreso,
        $nuevo_salida,
        $idFalta,
        $marcacion['id']
    ]);

    error_log("registrarAuditoriaMarcacionFalta: Marcación ID {$marcacion['id']} ajustada/completada por falta ID $idFalta (tipo: $tipoFalta)");
}

/**
 * Revierte el ajuste de marcación realizado por una falta manual
 * restaurando los valores nulos/originales respaldados.
 */
function revertirAjusteMarcacionPorFalta($codOperario, $fechaFalta, $idFalta)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id, hora_ingreso_original, hora_salida_original
        FROM marcaciones
        WHERE CodOperario = ?
          AND fecha = ?
          AND ajustado_por_tardanza = 1
          AND id_falta_ajuste = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fechaFalta, $idFalta]);
    $marcacion = $stmt->fetch();

    if (!$marcacion) {
        return; // No estaba ajustada por esta falta
    }

    $stmt = $conn->prepare("
        UPDATE marcaciones
        SET hora_ingreso          = hora_ingreso_original,
            hora_salida           = hora_salida_original,
            hora_ingreso_original = NULL,
            hora_salida_original  = NULL,
            ajustado_por_tardanza = 0,
            id_falta_ajuste       = NULL
        WHERE id = ?
    ");
    $stmt->execute([$marcacion['id']]);

    error_log("revertirAjusteMarcacionPorFalta: Marcación ID {$marcacion['id']} revertida (restaurados backups de falta ID $idFalta)");
}
?>