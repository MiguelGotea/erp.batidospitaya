<?php
require_once '../../../core/auth/auth.php';

verificarAutenticacion();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['semana']) && $_POST['semana'] === 'siguiente') {
    // Verificar permisos para editar
    if (!verificarAccesoCargo([13, 16]) && !$esAdmin) {
        echo json_encode(['success' => false, 'error' => 'No tiene permisos suficientes.']);
        exit();
    }

    $hoy = date('Y-m-d');
    $semanaSiguiente = null;
    
    if (function_exists('obtenerSemanasDisponibles')) {
        $semanasDisponibles = obtenerSemanasDisponibles();
        foreach ($semanasDisponibles as $semana) {
            if ($semana['fecha_inicio'] > $hoy) {
                $semanaSiguiente = $semana;
                break;
            }
        }
    }

    if (!$semanaSiguiente) {
        echo json_encode(['success' => false, 'error' => 'No se pudo determinar la semana siguiente.']);
        exit();
    }

    if (isset($_POST['movimientos']) && is_array($_POST['movimientos'])) {
        global $conn;
        foreach ($_POST['movimientos'] as $movimiento) {
            $codOperario = intval($movimiento['cod_operario']);
            $codSucursalDestino = intval($movimiento['cod_sucursal_destino']);
            $codCargo = intval($movimiento['cod_cargo']);

            // Validar datos
            if ($codOperario > 0 && $codSucursalDestino > 0 && in_array($codCargo, [2, 5, 43, 44, 45, 46, 47])) {
                // Obtener el último contrato del operario
                $stmtContrato = $conn->prepare("
                    SELECT 
                        CodContrato,
                        codigo_manual_contrato
                    FROM Contratos 
                    WHERE cod_operario = ? 
                    ORDER BY inicio_contrato DESC, CodContrato DESC 
                    LIMIT 1
                ");
                $stmtContrato->execute([$codOperario]);
                $contrato = $stmtContrato->fetch();

                $codContrato = $contrato['CodContrato'] ?? null;
                $codigoManualContrato = $contrato['codigo_manual_contrato'] ?? null;

                // Obtener la última asignación activa del operario
                $stmtUltima = $conn->prepare("
                    SELECT CodAsignacionNivelesCargos, Sucursal, Fin
                    FROM AsignacionNivelesCargos
                    WHERE CodOperario = ?
                    AND CodNivelesCargos = ?
                    AND (Fin IS NULL OR Fin >= CURDATE())
                    ORDER BY Fecha DESC, CodAsignacionNivelesCargos DESC
                    LIMIT 1
                ");
                $stmtUltima->execute([$codOperario, $codCargo]);
                $ultimaAsignacion = $stmtUltima->fetch();

                // Si hay una asignación activa, cerrarla un día antes del inicio de la semana siguiente
                if ($ultimaAsignacion) {
                    $fechaFin = date('Y-m-d', strtotime($semanaSiguiente['fecha_inicio'] . ' -1 day'));

                    $stmtCerrar = $conn->prepare("
                        UPDATE AsignacionNivelesCargos
                        SET Fin = ?,
                            fecha_ultima_modificacion = NOW(),
                            usuario_ultima_modificacion = ?
                        WHERE CodAsignacionNivelesCargos = ?
                    ");
                    $stmtCerrar->execute([$fechaFin, $_SESSION['usuario_id'], $ultimaAsignacion['CodAsignacionNivelesCargos']]);
                }

                // Crear nueva asignación con los datos del contrato
                $stmtNueva = $conn->prepare("
                    INSERT INTO AsignacionNivelesCargos (
                        CodOperario,
                        CodNivelesCargos,
                        Fecha,
                        Sucursal,
                        Fin,
                        CodContrato,
                        codigo_contrato_asociado,
                        fecha_hora_regsys,
                        fecha_ultima_modificacion,
                        usuario_ultima_modificacion,
                        cod_usuario_creador,
                        es_activo
                    ) VALUES (?, ?, ?, ?, NULL, ?, ?, NOW(), NOW(), ?, ?, 1)
                ");
                $stmtNueva->execute([
                    $codOperario,
                    $codCargo,
                    $semanaSiguiente['fecha_inicio'],
                    $codSucursalDestino,
                    $codContrato,
                    $codigoManualContrato,
                    $_SESSION['usuario_id'],
                    $_SESSION['usuario_id']
                ]);
            }
        }

        $_SESSION['exito'] = "Movimientos guardados exitosamente.";
        echo json_encode(['success' => true]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'No se enviaron movimientos válidos.']);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Petición inválida.']);
exit();
