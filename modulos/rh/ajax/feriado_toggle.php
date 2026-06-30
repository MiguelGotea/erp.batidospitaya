<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!tienePermiso('historial_marcaciones_globales', 'vista', $usuario['CodNivelesCargos'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit();
}

$esRRHH_o_Admin = tienePermiso('feriados_v2', 'aprobar', $usuario['CodNivelesCargos']);
// Si no tiene el permiso explícito, verificamos si es administrador (cargo 1 o 2) como fallback, aunque lo ideal es que use el permiso.
if (!$esRRHH_o_Admin && in_array($usuario['CodNivelesCargos'], [1, 2])) {
    $esRRHH_o_Admin = true;
}

$data = json_decode(file_get_contents('php://input'), true);
$codOperario = $data['cod_operario'] ?? null;
$fechaFeriado = $data['fecha_feriado'] ?? null;
$accion = $data['accion'] ?? null; // 'solicitar_pagado', 'solicitar_descansado', 'aprobar_pagado', 'aprobar_descansado'
$horasTrabajadas = $data['horas_trabajadas'] ?? 0;
$idMarcacion = $data['id_marcacion'] ?? null;

if (!$codOperario || !$fechaFeriado || !$accion) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
    exit();
}

try {
    // Buscar si ya existe un registro
    $stmt = $conn->prepare("SELECT id, estado, observaciones FROM FeriadosStatus WHERE cod_operario = ? AND fecha_feriado = ?");
    $stmt->execute([$codOperario, $fechaFeriado]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    $nuevoEstado = 'Pendiente';
    $observacionAdicional = '';

    if ($accion === 'pagado') {
        if ($esRRHH_o_Admin) {
            $nuevoEstado = 'Pagado';
            $observacionAdicional = 'Aprobado';
        } else {
            $nuevoEstado = 'Pendiente';
            $observacionAdicional = 'Solicita: Pagado';
        }
    } elseif ($accion === 'descansado') {
        if ($esRRHH_o_Admin) {
            $nuevoEstado = 'Descansado';
            $observacionAdicional = 'Aprobado (Compensado)';
        } else {
            $nuevoEstado = 'Pendiente';
            $observacionAdicional = 'Solicita: Descansado';
        }
    } elseif ($accion === 'pendiente') {
        $nuevoEstado = 'Pendiente';
        $observacionAdicional = 'Regresado a Pendiente';
    }

    if ($registro) {
        // Actualizar
        $obs = $registro['observaciones'];
        // Si no es RRHH, actualizamos la observación y forzamos el estado a Pendiente
        if (!$esRRHH_o_Admin && ($accion === 'pagado' || $accion === 'descansado')) {
            $obs = $observacionAdicional;
        } elseif ($esRRHH_o_Admin) {
            $obs = $observacionAdicional;
        }
        $stmtUpd = $conn->prepare("UPDATE FeriadosStatus SET estado = ?, observaciones = ?, actualizado_por = ?, fecha_actualizacion = NOW() WHERE id = ?");
        $stmtUpd->execute([$nuevoEstado, $obs, $_SESSION['usuario_id'], $registro['id']]);
    } else {
        // Insertar
        $codContrato = null;
        $stmt_contrato = $conn->prepare("SELECT CodContrato FROM Contratos WHERE cod_operario = ? ORDER BY inicio_contrato DESC LIMIT 1");
        $stmt_contrato->execute([$codOperario]);
        if ($c = $stmt_contrato->fetch(PDO::FETCH_ASSOC)) {
            $codContrato = $c['CodContrato'];
        }

        $stmtIns = $conn->prepare("INSERT INTO FeriadosStatus (id_marcacion, cod_operario, fecha_feriado, horas_trabajadas, cod_contrato, estado, observaciones, creado_por, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmtIns->execute([$idMarcacion, $codOperario, $fechaFeriado, $horasTrabajadas, $codContrato, $nuevoEstado, $observacionAdicional, $_SESSION['usuario_id']]);
    }

    echo json_encode(['success' => true, 'estado' => $nuevoEstado, 'observacion' => $observacionAdicional]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
