<?php
// ajax/registro_producto_update_campo_inline.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $campo = isset($_POST['campo']) ? trim($_POST['campo']) : '';
    $valor = isset($_POST['valor']) ? trim($_POST['valor']) : null;
    
    $usuario = obtenerUsuarioActual();
    $usuarioId = $_SESSION['usuario_id'];

    if ($id <= 0) {
        throw new Exception('ID de producto inválido');
    }

    // Lista blanca de campos permitidos para actualización inline por seguridad
    $camposPermitidos = ['categoria_insumo'];
    
    if (!in_array($campo, $camposPermitidos)) {
        throw new Exception('Campo no permitido para actualización inline');
    }

    $sql = "UPDATE producto_presentacion SET 
            $campo = :valor,
            usuario_modificacion = :usuario_mod,
            fecha_modificacion = NOW()
            WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':valor' => ($valor === '') ? null : $valor,
        ':usuario_mod' => $usuarioId,
        ':id' => $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Campo actualizado correctamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
