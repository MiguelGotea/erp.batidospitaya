<?php
require_once '../../core/config/database.php';

$db = new Database();

echo "<h3>Cargos del área 'Proyectos':</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>CodNivelesCargos</th><th>Nombre</th></tr>";

$sql = "SELECT CodNivelesCargos, Nombre FROM NivelesCargos WHERE Area = 'Proyectos' ORDER BY Nombre";
$cargos = $db->fetchAll($sql);

foreach ($cargos as $cargo) {
    echo "<tr>";
    echo "<td>" . $cargo['CodNivelesCargos'] . "</td>";
    echo "<td>" . $cargo['Nombre'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<br><h3>Tickets con colaboradores asignados (muestra):</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ticket_id</th><th>CodNivelesCargo</th><th>Nombre Cargo</th></tr>";

$sql2 = "
    SELECT DISTINCT tc.ticket_id, tc.CodNivelesCargo, nc.Nombre
    FROM mtto_tickets_colaboradores tc
    LEFT JOIN NivelesCargos nc ON tc.CodNivelesCargo = nc.CodNivelesCargos
    ORDER BY tc.ticket_id
    LIMIT 20
";
$tickets = $db->fetchAll($sql2);

foreach ($tickets as $ticket) {
    echo "<tr>";
    echo "<td>" . $ticket['ticket_id'] . "</td>";
    echo "<td>" . $ticket['CodNivelesCargo'] . "</td>";
    echo "<td>" . $ticket['Nombre'] . "</td>";
    echo "</tr>";
}

echo "</table>";
?>
