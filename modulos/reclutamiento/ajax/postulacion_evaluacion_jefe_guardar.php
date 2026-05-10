<?php
// postulacion_evaluacion_jefe_guardar.php

require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    $idPostulacion = (int) ($_POST['id_postulacion'] ?? 0);
    $veredicto = strtolower($_POST['veredicto'] ?? '');
    $promedioEstrellas = (float) ($_POST['promedio_estrellas'] ?? 0);
    $conclusiones = trim($_POST['conclusiones'] ?? '');

    if ($idPostulacion <= 0)
        throw new Exception('ID de postulación inválido');

    // Procesar carga de archivo
    $rutaEvidencia = null;
    if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/evaluaciones/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        $extension = pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION);
        $fileName = 'jefe_' . $idPostulacion . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $targetPath)) {
            $rutaEvidencia = 'evaluaciones/' . $fileName;
        }
    }

    // Mapear veredicto interno a nombre de BD
    $veredictoBD = ($veredicto === 'aprobado') ? 'aprobado' : 'descartado';

    // Insertar evaluación
    $sql = "INSERT INTO postulacion_evaluacion_jefe 
            (id_postulacion, 
             p1_calificacion, p1_comentario, 
             p2_calificacion, p2_comentario, 
             p3_calificacion, p3_comentario, 
             p4_calificacion, p4_comentario, 
             p5_calificacion, p5_comentario,
             p6_calificacion, p6_comentario,
             promedio_estrellas, evidencia_ruta, conclusiones_finales, veredicto, usuario_evalua) 
            VALUES 
            (:id_postulacion, 
             :p1, :p1_c, 
             :p2, :p2_c, 
             :p3, :p3_c, 
             :p4, :p4_c, 
             :p5, :p5_c,
             :p6, :p6_c,
             :promedio, :evidencia, :conclusiones, :veredicto, :usuario)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_postulacion' => $idPostulacion,
        ':p1' => (int) ($_POST['p1'] ?? 0),
        ':p1_c' => $_POST['p1_comentario'] ?? '',
        ':p2' => (int) ($_POST['p2'] ?? 0),
        ':p2_c' => $_POST['p2_comentario'] ?? '',
        ':p3' => (int) ($_POST['p3'] ?? 0),
        ':p3_c' => $_POST['p3_comentario'] ?? '',
        ':p4' => (int) ($_POST['p4'] ?? 0),
        ':p4_c' => $_POST['p4_comentario'] ?? '',
        ':p5' => (int) ($_POST['p5'] ?? 0),
        ':p5_c' => $_POST['p5_comentario'] ?? '',
        ':p6' => (int) ($_POST['p6'] ?? 0),
        ':p6_c' => $_POST['p6_comentario'] ?? '',
        ':promedio' => $promedioEstrellas,
        ':evidencia' => $rutaEvidencia,
        ':conclusiones' => $conclusiones,
        ':veredicto' => $veredictoBD,
        ':usuario' => $codOperario
    ]);


    // Actualizar el estado de la postulación
    if ($veredicto === 'aprobado') {
        $updateSql = "UPDATE postulacion_plaza SET status = 'seleccionado', fecha_actualizacion = NOW() WHERE id = :id";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([':id' => $idPostulacion]);
    } else {
        $updateSql = "UPDATE postulacion_plaza SET status = 'denegado', fecha_actualizacion = NOW() WHERE id = :id";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([':id' => $idPostulacion]);
    }

    echo json_encode(['success' => true, 'message' => 'Evaluación técnica guardada correctamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
