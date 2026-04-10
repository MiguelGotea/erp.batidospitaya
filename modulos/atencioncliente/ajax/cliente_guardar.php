<?php
// cliente_guardar.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    // Verificar acceso (edicion)
    if (!tienePermiso('clientes_club_pos', 'edicion', $cargoOperario)) {
        throw new Exception('No tiene permiso para editar el perfil del cliente.');
    }

    $id_clienteclub = isset($_POST['id_clienteclub']) ? (int)$_POST['id_clienteclub'] : null;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $apellido = isset($_POST['apellido']) ? trim($_POST['apellido']) : '';
    $cedula = isset($_POST['cedula']) ? trim($_POST['cedula']) : '';
    $celular = isset($_POST['celular']) ? trim($_POST['celular']) : '';
    $correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) && !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;

    if (!$id_clienteclub) {
        throw new Exception('ID de cliente no proporcionado.');
    }

    if (empty($nombre)) {
        throw new Exception('El nombre es obligatorio.');
    }

    $sql = "UPDATE clientesclub SET 
            nombre = :nombre, 
            apellido = :apellido, 
            cedula = :cedula, 
            celular = :celular, 
            correo = :correo, 
            fecha_nacimiento = :fecha_nacimiento 
            WHERE id_clienteclub = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':nombre' => $nombre,
        ':apellido' => $apellido,
        ':cedula' => $cedula,
        ':celular' => $celular,
        ':correo' => $correo,
        ':fecha_nacimiento' => $fecha_nacimiento,
        ':id' => $id_clienteclub
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Cliente actualizado correctamente.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
