<?php
/**
 * Obtener cargos del equipo de liderazgo
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();

    // Obtener cargos con EquipoLiderazgo = 1
    $sql = "SELECT CodNivelesCargos, Nombre 
            FROM NivelesCargos 
            WHERE EquipoLiderazgo = 1 
            ORDER BY Nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $cargos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'cargos' => $cargos
    ]);

} catch (PDOException $e) {
    error_log("Error en get_cargos_liderazgo: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener cargos'
    ]);
}
?>