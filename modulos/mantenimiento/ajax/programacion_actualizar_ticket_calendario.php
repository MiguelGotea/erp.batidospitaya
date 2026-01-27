<?php
require_once '../../../core/auth/auth.php';

$ticket_id = $_POST['ticket_id'] ?? null;
$fecha_inicio = $_POST['fecha_inicio'] ?? null;
$fecha_final = $_POST['fecha_final'] ?? null;
$cargos = json_decode($_POST['cargos'] ?? '[]', true);

if (!$ticket_id || !$fecha_inicio || !$fecha_final) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Actualizar fechas del ticket
    $sql_update = "UPDATE mtto_tickets SET fecha_inicio = ?, fecha_final = ?, status = 'agendado' WHERE id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->execute([$fecha_inicio, $fecha_final, $ticket_id]);

    // Eliminar colaboradores existentes
    $sql_delete = "DELETE FROM mtto_tickets_colaboradores WHERE ticket_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->execute([$ticket_id]);

    // Crear nuevos registros de colaboradores
    $errores = [];
    foreach ($cargos as $cod_cargo) {
        $operarios = obtenerOperariosPorCargoVigente($cod_cargo);
        $cod_operario = !empty($operarios) ? $operarios[0] : null;

        if (!$cod_operario) {
            $errores[] = "No hay operario vigente para cargo $cod_cargo";
            continue;
        }

        $sql_insert = "
            INSERT INTO mtto_tickets_colaboradores (ticket_id, CodNivelesCargo, cod_operario, asignado_por, fecha_asignacion)
            VALUES (?, ?, ?, ?, NOW())
        ";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->execute([$ticket_id, $cod_cargo, $cod_operario, $_SESSION['usuario_id']]);
    }

    if (!empty($errores)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errores)]);
    } else {
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) {
    error_log("Error actualizando ticket: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar ticket']);
}
?>