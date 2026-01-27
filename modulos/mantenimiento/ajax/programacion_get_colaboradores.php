<?php
require_once '../../../core/auth/auth.php';

$ticket_id = $_GET['ticket_id'] ?? null;

if (!$ticket_id) {
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT 
        tc.id,
        tc.CodNivelesCargo,
        tc.cod_operario,
        nc.Nombre as nombre_cargo,
        CONCAT(o.Nombre, ' ', o.Apellido) as nombre_operario
    FROM mtto_tickets_colaboradores tc
    LEFT JOIN NivelesCargos nc ON tc.CodNivelesCargo = nc.CodNivelesCargos
    LEFT JOIN Operarios o ON tc.cod_operario = o.CodOperario
    WHERE tc.ticket_id = ?
    ORDER BY nc.Nombre
";

$stmt = $conn->prepare($sql);
$stmt->execute([$ticket_id]);
$colaboradores = $stmt->fetchAll();

echo json_encode($colaboradores);
?>