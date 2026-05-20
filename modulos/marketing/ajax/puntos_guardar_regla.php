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
    $id_grupo = $_POST['id_grupo'] ?? null;
    $id_producto = !empty($_POST['id_producto']) ? $_POST['id_producto'] : null;
    $puntos = $_POST['puntos'] ?? null;
    $fecha_desde = $_POST['fecha_desde'] ?? null;
    $fecha_hasta = !empty($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : null;

    if (!$id_grupo || $puntos === null || !$fecha_desde) {
        throw new Exception("Faltan datos obligatorios.");
    }

    if ($id) {
        // Actualizar regla existente (ej. cierre manual)
        $sql = "UPDATE pos_ventas_puntos_reglas 
                SET puntos = :puntos, 
                    fecha_desde = :fecha_desde, 
                    fecha_hasta = :fecha_hasta 
                WHERE id = :id AND tipo_regla = 'acumulacion'";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':puntos', $puntos);
        $stmt->bindValue(':fecha_desde', $fecha_desde);
        $stmt->bindValue(':fecha_hasta', $fecha_hasta);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
    } else {
        // Insertar nueva regla
        $sql = "INSERT INTO pos_ventas_puntos_reglas 
                (tipo_regla, id_grupo, id_producto, puntos, fecha_desde, fecha_hasta, registrado_por) 
                VALUES ('acumulacion', :id_grupo, :id_producto, :puntos, :fecha_desde, :fecha_hasta, :registrado_por)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_grupo', $id_grupo);
        $stmt->bindValue(':id_producto', $id_producto);
        $stmt->bindValue(':puntos', $puntos);
        $stmt->bindValue(':fecha_desde', $fecha_desde);
        $stmt->bindValue(':fecha_hasta', $fecha_hasta);
        $stmt->bindValue(':registrado_por', $idOperario);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Regla guardada exitosamente.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
