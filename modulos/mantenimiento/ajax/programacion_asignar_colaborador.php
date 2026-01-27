<?php
require_once '../../../core/auth/auth.php';

$ticket_id = $_POST['ticket_id'] ?? null;
$cod_cargo = $_POST['cod_cargo'] ?? null;

if (!$ticket_id || !$cod_cargo) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Obtener operario vigente usando la función de funciones.php
$operarios = obtenerOperariosPorCargoVigente($cod_cargo);
$cod_operario = !empty($operarios) ? $operarios[0] : null;

if (!$cod_operario) {
    echo json_encode(['success' => false, 'message' => 'No hay operario vigente para este cargo']);
    exit;
}

// Insertar colaborador
$sql = "
    INSERT INTO mtto_tickets_colaboradores (ticket_id, CodNivelesCargo, cod_operario, asignado_por, fecha_asignacion)
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE CodNivelesCargo = VALUES(CodNivelesCargo), cod_operario = VALUES(cod_operario)
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$ticket_id, $cod_cargo, $cod_operario, $_SESSION['usuario_id']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error asignando colaborador: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar en base de datos']);
}
?>