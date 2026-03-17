<?php
// historial_informes_get_filtros.php
require_once '../models/Ticket.php';
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

$usuario = obtenerUsuarioActual();
if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
    exit;
}

$cargoOperario = $usuario['CodNivelesCargos'];
$puedeVerTodos = tienePermiso('agenda_mantenimiento', 'todos_colaboradores', $cargoOperario);

$ticketModel = new Ticket();
$columna = $_GET['column'] ?? '';

$response = ['success' => true, 'options' => []];

try {
    if ($columna === 'Nombre') {
        if ($puedeVerTodos) {
            // Obtener colaboradores que tienen al menos un informe
            $sql = "SELECT DISTINCT o.CodOperario as value, CONCAT(o.Nombre, ' ', o.Apellido) as label 
                    FROM Operarios o
                    JOIN mtto_informes_diarios i ON o.CodOperario = i.cod_operario
                    ORDER BY label ASC";
            $response['options'] = $ticketModel->db->fetchAll($sql);
        } else {
            // Solo el usuario actual
            $response['options'] = [[
                'value' => $usuario['CodOperario'],
                'label' => $usuario['Nombre'] . ' ' . $usuario['Apellido']
            ]];
        }
    } elseif ($columna === 'estado') {
        $response['options'] = [
            ['value' => 'creado', 'label' => 'ABIERTO'],
            ['value' => 'finalizado', 'label' => 'FINALIZADO']
        ];
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
