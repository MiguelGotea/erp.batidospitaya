<?php
ob_start(); // Capturar toda salida para evitar que warnings rompan el JSON

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../editar_colaborador_componentes/logic/funciones_colaborador.php';

header('Content-Type: application/json');

try {

    $usuario = obtenerUsuarioActual();
    $cargoId = $usuario['CodNivelesCargos'] ?? 0;

    // Verificar permisos
    if (!tienePermiso('gestion_colaboradores', 'finalizar_contrato', $cargoId)) {
        throw new Exception('No tiene permisos para finalizar contratos');
    }

    if (!isset($_POST['id_contrato']) || empty($_POST['id_contrato'])) {
        throw new Exception('ID de contrato no especificado');
    }

    $idContrato = $_POST['id_contrato'];

    // Mapear datos del POST para que coincidan con lo que espera terminarContrato
    $datos = [
        'fecha_terminacion'          => $_POST['fecha_terminacion'],
        'fecha_liquidacion'          => $_POST['fecha_liquidacion'] ?? null,
        'fecha_carta'                => $_POST['fecha_carta'] ?? null,
        'tipo_salida'                => $_POST['tipo_salida'],
        'motivo_salida'              => $_POST['motivo_salida'],
        'dias_trabajados'            => $_POST['dias_trabajados'] ?? 0,
        'monto_indemnizacion'        => $_POST['monto_indemnizacion'] ?? 0,
        'devolucion_herramientas'    => isset($_POST['devolucion_herramientas']) && $_POST['devolucion_herramientas'] == '1',
        'persona_recibe_herramientas'=> $_POST['persona_recibe_herramientas'] ?? ''
    ];

    $resultado = terminarContrato($idContrato, $datos);

    // Limpiar cualquier salida acumulada antes de enviar el JSON
    ob_end_clean();

    echo json_encode([
        'success' => $resultado['exito'] ?? $resultado['success'] ?? false,
        'mensaje' => $resultado['mensaje'] ?? ($resultado['exito'] || $resultado['success'] ? 'Operación exitosa' : 'Error desconocido')
    ]);

    // ── Notificación Email (post-éxito, en bloque independiente) ──────────
    if ($resultado['exito'] ?? $resultado['success'] ?? false) {
        try {
            require_once __DIR__ . '/../../../core/email/EmailService.php';
            $emailService = new EmailService($conn);

            // 1. Obtener cargo/sucursal ACTUAL del colaborador (no del contrato antiguo)
            $stmtColaborador = $conn->prepare("
                SELECT
                    o.Nombre,
                    o.Apellido,
                    o.Cedula,
                    COALESCE(
                        (SELECT nc.Nombre
                         FROM AsignacionNivelesCargos anc
                         JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                         WHERE anc.CodOperario = o.CodOperario
                           AND anc.CodNivelesCargos != 2
                           AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                         ORDER BY anc.CodAsignacionNivelesCargos DESC LIMIT 1),
                        (SELECT nc.Nombre
                         FROM AsignacionNivelesCargos anc
                         JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                         WHERE anc.CodOperario = o.CodOperario
                           AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                         ORDER BY anc.CodAsignacionNivelesCargos DESC LIMIT 1),
                        'Sin cargo'
                    ) AS nombre_cargo,
                    COALESCE(
                        (SELECT s.nombre
                         FROM AsignacionNivelesCargos anc2
                         JOIN sucursales s ON anc2.Sucursal = s.codigo
                         WHERE anc2.CodOperario = o.CodOperario
                           AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
                         ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC LIMIT 1),
                        'Sin tienda'
                    ) AS nombre_sucursal,
                    ts.nombre AS tipo_salida_nombre,
                    c.fecha_carta
                FROM Contratos c
                INNER JOIN Operarios o ON o.CodOperario = c.cod_operario
                LEFT JOIN TipoSalida ts ON ts.CodTipoSalida = c.cod_tipo_salida
                WHERE c.CodContrato = ?
            ");
            $stmtColaborador->execute([$idContrato]);
            $colaborador = $stmtColaborador->fetch(PDO::FETCH_ASSOC);

            if ($colaborador) {
                $nombreCompleto = trim($colaborador['Nombre'] . ' ' . $colaborador['Apellido']);
                $cedula         = $colaborador['Cedula']             ?? 'N/D';
                $cargo          = $colaborador['nombre_cargo']       ?? 'N/D';
                $sucursal       = $colaborador['nombre_sucursal']    ?? 'N/D';
                $tipoSalida     = $colaborador['tipo_salida_nombre'] ?? ($datos['tipo_salida'] ?? 'N/D');

                // Formatear fechas en español
                $meses = ['01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril',
                          '05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto',
                          '09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'];

                $fmtFecha = function ($fecha) use ($meses) {
                    if (empty($fecha) || $fecha === '0000-00-00') return 'N/D';
                    [$y, $m, $d] = explode('-', substr($fecha, 0, 10));
                    return intval($d) . ' de ' . ($meses[$m] ?? $m) . ' del ' . $y;
                };

                // fecha_carta: prioridad → guardada en DB → enviada en POST → fecha_terminacion
                $rawCarta   = $colaborador['fecha_carta'] ?? ($datos['fecha_carta'] ?? $datos['fecha_terminacion']);
                $fechaCarta = $fmtFecha($rawCarta);
                $ultimoDia  = !empty($datos['fecha_liquidacion'])
                                ? $fmtFecha($datos['fecha_liquidacion'])
                                : $fmtFecha($datos['fecha_terminacion']);

                // 2. Construir cuerpo HTML del correo
                $cuerpoHtml = "
                    <div style=\"font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.8;\">
                        <p>Buenos días,</p>
                        <p>Comparto las bajas que tuvimos</p>
                        <br>
                        <p><strong>Nombre:</strong> {$nombreCompleto}</p>
                        <p><strong>Cédula:</strong> {$cedula}</p>
                        <p><strong>Puesto:</strong> {$cargo}</p>
                        <p><strong>Ubicación:</strong> {$sucursal}</p>
                        <p><strong>Tipo de Salida:</strong> {$tipoSalida}</p>
                        <p><strong>Fecha de la carta:</strong> {$fechaCarta}</p>
                        <p><strong>Último día laborado:</strong> {$ultimoDia}</p>
                    </div>
                ";

                // 3. Obtener destinatarios: cargos con permiso 'allow' → acción 'correo' → tool 'salida_colaborador'
                $stmtDestinatarios = $conn->prepare("
                    SELECT DISTINCT o.email_trabajo
                    FROM Operarios o
                    INNER JOIN AsignacionNivelesCargos anc
                        ON anc.CodOperario = o.CodOperario
                        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                        AND anc.Fecha <= CURDATE()
                    INNER JOIN permisos_tools_erp p
                        ON p.CodNivelesCargos = anc.CodNivelesCargos
                        AND p.permiso = 'allow'
                    INNER JOIN acciones_tools_erp ac
                        ON ac.id = p.accion_tool_erp_id
                        AND ac.nombre_accion = 'correo'
                    INNER JOIN tools_erp t
                        ON t.id = ac.tool_erp_id
                        AND t.tipo_componente = 'notificacion_email'
                        AND t.nombre = 'salida_colaborador'
                        AND t.activo = 1
                    WHERE o.email_trabajo IS NOT NULL
                      AND o.email_trabajo != ''
                ");
                $stmtDestinatarios->execute();
                $destinatarios = $stmtDestinatarios->fetchAll(PDO::FETCH_COLUMN);

                // 4. Enviar si hay destinatarios configurados
                if (!empty($destinatarios)) {
                    $asunto = "Baja de Colaborador: {$nombreCompleto}";
                    $emailService->enviarCorreo(
                        $usuario['CodOperario'],
                        $destinatarios,
                        $asunto,
                        $cuerpoHtml
                    );
                }
            }

        } catch (Exception $eEmail) {
            // El correo falló pero la terminación fue exitosa — solo loguear, no interrumpir
            error_log('[salida_colaborador] Error enviando notificación email: ' . $eEmail->getMessage());
        }
    }
    // ── Fin Notificación Email ─────────────────────────────────────────────

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage()
    ]);
}
?>