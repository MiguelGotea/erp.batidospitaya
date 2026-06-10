<?php
/**
 * colaboradores_test_email_baja.php
 * 
 * Endpoint TEMPORAL de prueba: envía el correo de notificación de baja
 * usando datos reales del contrato/colaborador, SIN modificar ningún dato en BD.
 * 
 * Solo accesible para usuarios con permiso 'finalizar_contrato'.
 */

ob_start();

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoId = $usuario['CodNivelesCargos'] ?? 0;

    // Misma guardia de permisos que el flujo real
    if (!tienePermiso('gestion_colaboradores', 'finalizar_contrato', $cargoId)) {
        throw new Exception('No tiene permisos para esta acción');
    }

    $idContrato       = (int) ($_POST['id_contrato']       ?? 0);
    $fechaTerminacion = trim($_POST['fecha_terminacion']    ?? '');
    $fechaLiquidacion = trim($_POST['fecha_liquidacion']    ?? '');
    $tipoSalidaPost   = trim($_POST['tipo_salida']          ?? '');

    if ($idContrato <= 0) {
        throw new Exception('ID de contrato inválido');
    }
    if (empty($fechaTerminacion)) {
        throw new Exception('Fecha de terminación requerida');
    }

    require_once __DIR__ . '/../../../core/email/EmailService.php';
    $emailService = new EmailService($conn);

    // 1. Obtener datos del colaborador (solo lectura)
    $stmtColaborador = $conn->prepare("
        SELECT
            o.Nombre,
            o.Apellido,
            o.Cedula,
            nc.Nombre  AS nombre_cargo,
            s.nombre   AS nombre_sucursal
        FROM Contratos c
        INNER JOIN Operarios o
            ON o.CodOperario = c.cod_operario
        LEFT JOIN AsignacionNivelesCargos anc
            ON anc.CodAsignacionNivelesCargos = c.CodAsignacionNivelesCargos
        LEFT JOIN NivelesCargos nc
            ON nc.CodNivelesCargos = anc.CodNivelesCargos
        LEFT JOIN sucursales s
            ON s.id = anc.Sucursal
        WHERE c.CodContrato = ?
    ");
    $stmtColaborador->execute([$idContrato]);
    $colaborador = $stmtColaborador->fetch(PDO::FETCH_ASSOC);

    // Obtener nombre del tipo de salida por separado (evita JOIN con parámetro en ON)
    $tipoSalidaNombre = $tipoSalidaPost;
    if (!empty($tipoSalidaPost) && is_numeric($tipoSalidaPost)) {
        $stmtTipo = $conn->prepare("SELECT nombre FROM TipoSalida WHERE CodTipoSalida = ?");
        $stmtTipo->execute([$tipoSalidaPost]);
        $tipoRow = $stmtTipo->fetch(PDO::FETCH_ASSOC);
        if ($tipoRow) $tipoSalidaNombre = $tipoRow['nombre'];
    }

    if (!$colaborador) {
        throw new Exception('No se encontraron datos del colaborador para el contrato #' . $idContrato);
    }

    $nombreCompleto = trim($colaborador['Nombre'] . ' ' . $colaborador['Apellido']);
    $cedula         = $colaborador['Cedula']             ?? 'N/D';
    $cargo          = $colaborador['nombre_cargo']    ?? 'N/D';
    $sucursal       = $colaborador['nombre_sucursal'] ?? 'N/D';
    $tipoSalida     = !empty($tipoSalidaNombre) ? $tipoSalidaNombre : 'N/D';

    // 2. Formatear fechas en español
    $meses = [
        '01' => 'enero',   '02' => 'febrero', '03' => 'marzo',
        '04' => 'abril',   '05' => 'mayo',     '06' => 'junio',
        '07' => 'julio',   '08' => 'agosto',   '09' => 'septiembre',
        '10' => 'octubre', '11' => 'noviembre','12' => 'diciembre',
    ];
    $fmtFecha = function ($fecha) use ($meses) {
        if (empty($fecha) || $fecha === '0000-00-00') return 'N/D';
        [$y, $m, $d] = explode('-', substr($fecha, 0, 10));
        return intval($d) . ' de ' . ($meses[$m] ?? $m) . ' del ' . $y;
    };

    $fechaCarta = $fmtFecha($fechaTerminacion);
    $ultimoDia  = !empty($fechaLiquidacion)
                    ? $fmtFecha($fechaLiquidacion)
                    : $fmtFecha($fechaTerminacion);

    // 3. Construir HTML del correo (mismo cuerpo que el flujo real)
    $cuerpoHtml = "
        <div style=\"font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.8;\">
            <p style=\"color:#6f42c1; font-size:12px;\">
                ⚗️ <em>Este es un correo de PRUEBA — no se registró ninguna baja en el sistema.</em>
            </p>
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

    // 4. Obtener destinatarios por permisos (misma query que el flujo real)
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

    if (empty($destinatarios)) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'mensaje' => 'No hay destinatarios configurados. Ve a Gestión de Permisos → Notif. Email → salida_colaborador y asigna la acción "correo" a los cargos correspondientes.'
        ]);
        exit;
    }

    // 5. Enviar (remitente = usuario logueado)
    $resultado = $emailService->enviarCorreo(
        $usuario['CodOperario'],
        $destinatarios,
        '[PRUEBA] Baja de Colaborador: ' . $nombreCompleto,
        $cuerpoHtml
    );

    ob_end_clean();

    echo json_encode([
        'success'       => $resultado['success'],
        'mensaje'       => $resultado['success']
                            ? 'Correo de prueba enviado correctamente a ' . count($destinatarios) . ' destinatario(s): ' . implode(', ', $destinatarios)
                            : 'Error al enviar: ' . $resultado['message'],
        'destinatarios' => $destinatarios,
    ]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'mensaje' => $e->getMessage()
    ]);
}
?>
