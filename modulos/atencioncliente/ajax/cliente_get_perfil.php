<?php
// cliente_get_perfil.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar acceso básico (vista)
    if (!tienePermiso('clientes_club_pos', 'vista', $cargoOperario)) {
        throw new Exception('No tiene permiso para ver el perfil del cliente.');
    }

    $membresia = isset($_POST['membresia']) ? $_POST['membresia'] : null;

    if (!$membresia) {
        throw new Exception('Membresía no proporcionada.');
    }

    $sql = "SELECT * FROM clientesclub WHERE membresia = :membresia";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':membresia' => $membresia]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        throw new Exception('Cliente no encontrado.');
    }

    echo json_encode([
        'success' => true,
        'datos' => $cliente
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
