<?php
// ajax.php (o agregar esta función a tu archivo ajax existente)
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

if (isset($_GET['action']) && $_GET['action'] == 'obtener_operarios_autocompletado') {
    // Obtener todos los operarios activos
    $sql = "SELECT CodOperario as codigo, 
                    CONCAT(
                        IFNULL(Nombre, ''), ' ', 
                        IFNULL(Nombre2, ''), ' ', 
                        IFNULL(Apellido, ''), ' ', 
                        IFNULL(Apellido2, '')
                    ) AS nombre 
            FROM Operarios 
            WHERE Operativo = 1
            ORDER BY Nombre, Apellido";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $operarios = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode($operarios);
    exit;
}