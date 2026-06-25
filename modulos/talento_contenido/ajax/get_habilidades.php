<?php
// get_habilidades.php - Obtener habilidades del catálogo
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $habilidad_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($habilidad_id > 0) {
        // Obtener una sola habilidad
        $stmt = $conn->prepare("SELECT id, nombre, categoria, activo FROM habilidades_talento WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $habilidad_id, PDO::PARAM_INT);
        $stmt->execute();
        $hab = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($hab) {
            echo json_encode([
                'success' => true,
                'datos' => $hab
            ]);
        } else {
            throw new Exception("Habilidad no encontrada.");
        }
    } else {
        // Obtener todas las habilidades
        $stmt = $conn->prepare("SELECT id, nombre, categoria, activo FROM habilidades_talento ORDER BY nombre ASC");
        $stmt->execute();
        $habilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'datos' => $habilidades
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
