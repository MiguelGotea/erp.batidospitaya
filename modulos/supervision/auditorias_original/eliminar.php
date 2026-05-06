<?php
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditorías, ahora llama al del core;

// Obtener el ID del registro a eliminar
$id = $_GET['id'];

// Eliminar el registro de la base de datos
$sql = "DELETE FROM auditoria WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $id);

// Ejecutar la consulta
if ($stmt->execute()) {
    header("Location: index.php");
    exit();
} else {
    echo "Error al eliminar el registro.";
}
?>
