<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');


$usuario = obtenerUsuarioActual();
$cargoUsuario = $usuario['CodNivelesCargos'];
false = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] == 'admin';

$action = $_POST['action'] ?? '';

try {
    if ($action === 'guardar') {
        $id = $_POST['id'] ?? null;
        $codOperario = $_POST['cod_operario'];
        $fecha       = $_POST['fecha'];
        $horas       = $_POST['horas'];
        $motivo      = $_POST['motivo_solicitud'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        $estado      = $_POST['estado'] ?? 'Pendiente';

        // Restricción de fecha según permiso de ver todo (si no puede ver todo, se asume que es líder/restringido)
        $puedeVerTodo = tienePermiso('horas_extras_manual', 'ver_todo', $cargoUsuario);
        if (!$puedeVerTodo) {
            $today = new DateTime();
            $d = (int)$today->format('j');
            $y = (int)$today->format('Y');
            $m = (int)$today->format('n');

            if ($d >= 13 && $d <= 26) {
                $min = sprintf('%04d-%02d-13', $y, $m);
                $max = sprintf('%04d-%02d-26', $y, $m);
            } else {
                if ($d >= 27) {
                    $min = sprintf('%04d-%02d-27', $y, $m);
                    $next = clone $today;
                    $next->modify('first day of next month');
                    $max = sprintf('%s-%s-12', $next->format('Y'), $next->format('m'));
                } else {
                    $prev = clone $today;
                    $prev->modify('first day of last month');
                    $min = sprintf('%s-%s-27', $prev->format('Y'), $prev->format('m'));
                    $max = sprintf('%04d-%02d-12', $y, $m);
                }
            }

            if ($fecha < $min || $fecha > $max) {
                throw new Exception("La fecha seleccionada ($fecha) está fuera del rango permitido para su quincena ($min - $max).");
            }
        }
        
        // Detectar sucursal automáticamente según la asignación vigente en esa fecha
        $codSucursal = $_POST['cod_sucursal'] ?? '';
        
        if (empty($codSucursal)) {
            $stmtSuc = $conn->prepare("
                SELECT anc.Sucursal
                FROM AsignacionNivelesCargos anc
                WHERE anc.CodOperario = ?
                  AND anc.Fecha <= ?
                  AND (anc.Fin IS NULL OR anc.Fin >= ?)
                  AND anc.CodNivelesCargos != 27
                ORDER BY anc.Fecha DESC
                LIMIT 1
            ");
            $stmtSuc->execute([$codOperario, $fecha, $fecha]);
            $codSucursal = $stmtSuc->fetchColumn() ?: null;
        }
        
        if (empty($codSucursal)) {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo determinar la sucursal del colaborador para la fecha indicada.'
            ]);
            exit();
        }
        
        // Permisos de creación/edición unificados
        if ($id) {
            if (!tienePermiso('horas_extras_manual', 'gestionar', $cargoUsuario)) {
                throw new Exception("No tiene permisos para modificar este registro.");
            }
        } else {
            $puedeSolicitar = tienePermiso('horas_extras_manual', 'solicitar', $cargoUsuario);
            $puedeGestionar = tienePermiso('horas_extras_manual', 'gestionar', $cargoUsuario);
            if (!$puedeSolicitar && !$puedeGestionar) {
                throw new Exception("No tiene permisos para crear una nueva solicitud.");
            }
        }

        // Lógica de auto-aprobación
        if (tienePermiso('horas_extras_manual', 'gestionar', $cargoUsuario)) {
            $estado = 'Aprobado';
        }

        if ($id) {
            // Al actualizar, también podríamos validar si se cambia la fecha a una que ya existe (opcional)
            // Pero por ahora nos enfocamos en el insert según lo solicitado
        } else {
            // Validar si ya existe una solicitud para este operario y fecha
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM horas_extras_manual WHERE cod_operario = ? AND fecha = ?");
            $stmtCheck->execute([$codOperario, $fecha]);
            if ($stmtCheck->fetchColumn() > 0) {
                throw new Exception("Ya existe una solicitud de horas extras para este colaborador en la fecha seleccionada ($fecha).");
            }
        }

        if ($id) {
            // Actualizar
            $sql = "
                UPDATE horas_extras_manual 
                SET horas_extras = ?, observaciones = ?, motivo_solicitud = ?, estado = ?, actualizado_por = ?, fecha_actualizacion = NOW()
                WHERE id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$horas, $observaciones, $motivo, $estado, $_SESSION['usuario_id'], $id]);
        } else {
            // Insertar
            // Obtener el último código de contrato del operario
            $stmtContrato = $conn->prepare("SELECT CodContrato FROM Contratos WHERE cod_operario = ? ORDER BY CodContrato DESC LIMIT 1");
            $stmtContrato->execute([$codOperario]);
            $codContrato = $stmtContrato->fetchColumn();

            $sql = "
                INSERT INTO horas_extras_manual (
                    cod_operario, fecha, horas_extras, cod_sucursal, 
                    motivo_solicitud, observaciones, registrado_por, estado, cod_contrato
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $codOperario,
                $fecha,
                $horas,
                $codSucursal,
                $motivo,
                $observaciones,
                $_SESSION['usuario_id'],
                $estado,
                $codContrato
            ]);
        }
        echo json_encode(['success' => true]);
    } elseif ($action === 'cambiar_estado') {
        $id = $_POST['id'];
        $estado = $_POST['estado'];
        $observaciones = $_POST['observaciones'] ?? '';

        // Validar permisos según el estado que se desea poner unificado en "gestionar"
        $puedeGestionar = tienePermiso('horas_extras_manual', 'gestionar', $cargoUsuario);

        if (!$puedeGestionar) {
            throw new Exception("No tiene permisos para aprobar o rechazar esta solicitud.");
        }

        $sql = "
            UPDATE horas_extras_manual 
            SET estado = ?, observaciones = ?, actualizado_por = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$estado, $observaciones, $_SESSION['usuario_id'], $id]);

        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Acción no válida.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
