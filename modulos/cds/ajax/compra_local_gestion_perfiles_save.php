<?php
// ajax/compra_local_gestion_perfiles_save.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $frecuencia = $_POST['frecuencia_semanas'] ?? 1;
    $semana_ref = !empty($_POST['semana_referencia']) ? $_POST['semana_referencia'] : null;

    $dias = [
        'lunes' => isset($_POST['lunes']) ? 1 : 0,
        'martes' => isset($_POST['martes']) ? 1 : 0,
        'miercoles' => isset($_POST['miercoles']) ? 1 : 0,
        'jueves' => isset($_POST['jueves']) ? 1 : 0,
        'viernes' => isset($_POST['viernes']) ? 1 : 0,
        'sabado' => isset($_POST['sabado']) ? 1 : 0,
        'domingo' => isset($_POST['domingo']) ? 1 : 0
    ];

    if ($id) {
        // Update
        $sql = "UPDATE compra_local_perfiles_despacho SET 
                nombre = ?, frecuencia_semanas = ?, semana_referencia = ?,
                lunes = ?, martes = ?, miercoles = ?, jueves = ?, viernes = ?, sabado = ?, domingo = ?
                WHERE id = ?";
        $params = [$nombre, $frecuencia, $semana_ref, $dias['lunes'], $dias['martes'], $dias['miercoles'], $dias['jueves'], $dias['viernes'], $dias['sabado'], $dias['domingo'], $id];
    } else {
        // Insert
        $sql = "INSERT INTO compra_local_perfiles_despacho 
                (nombre, frecuencia_semanas, semana_referencia, lunes, martes, miercoles, jueves, viernes, sabado, domingo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$nombre, $frecuencia, $semana_ref, $dias['lunes'], $dias['martes'], $dias['miercoles'], $dias['jueves'], $dias['viernes'], $dias['sabado'], $dias['domingo']];
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
