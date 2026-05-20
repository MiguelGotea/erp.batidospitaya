<?php
require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido");
    }

    $usuario = obtenerUsuarioActual();
    $idOperario = $usuario['CodOperario'] ?? null;

    $id = $_POST['id'] ?? null;
    $nombre = trim($_POST['nombre'] ?? '');
    $id_producto_canjeable = !empty($_POST['id_producto_canjeable']) ? $_POST['id_producto_canjeable'] : null;
    $puntos_requeridos = $_POST['puntos_requeridos'] ?? null;
    $orden = isset($_POST['orden']) ? (int)$_POST['orden'] : 0;
    $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;

    if (empty($nombre) || $puntos_requeridos === null) {
        throw new Exception("El nombre y los puntos son obligatorios.");
    }

    if ($id) {
        $sql = "UPDATE pos_ventas_puntos_catalogo_canje 
                SET nombre = :nombre, 
                    id_producto_canjeable = :id_producto,
                    puntos_requeridos = :puntos, 
                    activo = :activo, 
                    orden = :orden 
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id);
    } else {
        $sql = "INSERT INTO pos_ventas_puntos_catalogo_canje 
                (nombre, id_producto_canjeable, puntos_requeridos, activo, orden, registrado_por, fecha_desde) 
                VALUES (:nombre, :id_producto, :puntos, :activo, :orden, :registrado_por, CURDATE())";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':registrado_por', $idOperario);
    }

    $stmt->bindValue(':nombre', $nombre);
    $stmt->bindValue(':id_producto', $id_producto_canjeable);
    $stmt->bindValue(':puntos', $puntos_requeridos);
    $stmt->bindValue(':activo', $activo);
    $stmt->bindValue(':orden', $orden);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Catálogo guardado exitosamente.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
