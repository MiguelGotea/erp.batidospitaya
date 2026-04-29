<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

header('Content-Type: application/json');

verificarAutenticacion();

$sucursal = $_GET['sucursal'] ?? null;
$semanaNumero = $_GET['semana'] ?? null;

if (!$sucursal) {
    echo json_encode([]);
    exit;
}

try {
    global $conn;
    
    // Obtener operarios asignados a la sucursal (excluyendo los ya seleccionados)
    $sql = "SELECT DISTINCT o.CodOperario as id, 
                   CONCAT(o.Nombre, ' ', o.Apellido, ' ', COALESCE(o.Apellido2, '')) as nombre,
                   o.CodOperario as codigo
            FROM Operarios o
            INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            WHERE anc.Sucursal = ?
            AND o.Operativo = 1
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            AND o.CodOperario NOT IN (
                SELECT DISTINCT anc2.CodOperario 
                FROM AsignacionNivelesCargos anc2
                WHERE anc2.CodNivelesCargos = 27
                AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
            )
            -- AND o.CodOperario NOT IN (566, 567, 568, 569, 570, 571, 572, 573, 574, 575, 576, 590)
            ORDER BY o.Nombre, o.Apellido";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$sucursal]);
    
    $colaboradores = [];
    while ($row = $stmt->fetch()) {
        $colaboradores[] = [
            'id' => $row['id'],
            'nombre' => trim($row['nombre']),
            'codigo' => $row['codigo']
        ];
    }
    
    echo json_encode($colaboradores);
    
} catch (Exception $e) {
    echo json_encode([]);
}