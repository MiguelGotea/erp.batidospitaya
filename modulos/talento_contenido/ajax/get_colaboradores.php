<?php
// get_colaboradores.php - Obtener colaboradores
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $colaborador_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($colaborador_id > 0) {
        // Obtener un solo colaborador
        $stmt = $conn->prepare("SELECT id, nombre, cargo, departamento, testimonio, foto, orden, activo FROM colaboradores_talento WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $colaborador_id, PDO::PARAM_INT);
        $stmt->execute();
        $col = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($col) {
            echo json_encode([
                'success' => true,
                'datos' => $col
            ]);
        } else {
            throw new Exception("Colaborador no encontrado.");
        }
    } else {
        // Obtener todos los colaboradores
        $stmt = $conn->prepare("SELECT id, nombre, cargo, departamento, testimonio, foto, orden, activo FROM colaboradores_talento ORDER BY orden ASC, id ASC");
        $stmt->execute();
        $colaboradores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'datos' => $colaboradores
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
