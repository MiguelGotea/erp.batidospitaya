<?php
/**
 * AJAX correos_eliminar.php
 * Módulo: sistemas
 * Elimina un registro de correo corporativo
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar permiso de eliminación
    if (!tienePermiso('correos_corporativos', 'eliminar', $cargoOperario)) {
        echo json_encode([
            'success' => false,
            'error' => 'No tiene permisos para eliminar correos corporativos.'
        ]);
        exit;
    }

    // Aceptar tanto JSON como POST estándar
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

    if ($id <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'ID de registro no válido.'
        ]);
        exit;
    }

    // Ejecutar eliminación
    $sql = "DELETE FROM Correos_Corporativos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$id]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Correo corporativo eliminado exitosamente.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo eliminar el registro de la base de datos.'
        ]);
    }

} catch (Exception $e) {
    error_log("Error en correos_eliminar.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la eliminación: ' . $e->getMessage()
    ]);
}
