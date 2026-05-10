<?php
// postulacion_evaluacion_rh_guardar.php

require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    $idPostulacion = (int) ($_POST['id_postulacion'] ?? 0);
    $veredicto = strtolower($_POST['veredicto'] ?? '');
    $puntajeAcumulado = (float) ($_POST['puntaje_acumulado'] ?? 0);
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
        $fileName = 'rh_' . $idPostulacion . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $targetPath)) {
            $rutaEvidencia = 'evaluaciones/' . $fileName;
        }
    }

    // Mapear veredicto interno a nombre de BD
    $veredictoBD = ($veredicto === 'aprobado') ? 'aprobado' : 'rechazado';

    // Insertar evaluación
    $sql = "INSERT INTO postulacion_evaluacion_rh 
            (id_postulacion, 
             hora_inicio, hora_fin,
             p1_calificacion, p1_comentario,
             p2_calificacion, p2_comentario,
             p3_calificacion, p3_comentario,
             p4_calificacion, p4_comentario,
             p5_calificacion, p5_comentario,
             puntaje_acumulado, evidencia_ruta, conclusiones_generales, veredicto, usuario_evalua) 
            VALUES 
            (:id_postulacion, 
             :h_ini, :h_fin,
             :p1, :p1_c,
             :p2, :p2_c,
             :p3, :p3_c,
             :p4, :p4_c,
             :p5, :p5_c,
             :puntaje, :evidencia, :conclusiones, :veredicto, :usuario)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_postulacion' => $idPostulacion,

        ':h_ini' => $_POST['hora_inicio'] ?? null,
        ':h_fin' => $_POST['hora_fin'] ?? null,
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
        ':puntaje' => $puntajeAcumulado,
        ':evidencia' => $rutaEvidencia,
        ':conclusiones' => $conclusiones,
        ':veredicto' => $veredictoBD,
        ':usuario' => $codOperario
    ]);


    // Actualizar el estado de la postulación si es rechazado
    // Si es aprobado, ya tiene el status 'aprobado' desde la fase previa (si se aprobó vía detalle_candidato_aprobar)
    // Pero por si acaso, si llega aquí como aprobado y tiene status solicitado, lo actualizamos.
    if ($veredicto === 'rechazado') {
        $updateSql = "UPDATE postulacion_plaza SET status = 'rechazado', fecha_actualizacion = NOW() WHERE id = :id";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([':id' => $idPostulacion]);
    } else if ($veredicto === 'aprobado') {
        // Asegurar que el status sea aprobado
        $updateSql = "UPDATE postulacion_plaza SET status = 'aprobado', fecha_actualizacion = NOW() WHERE id = :id";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([':id' => $idPostulacion]);
    }

    echo json_encode(['success' => true, 'message' => 'Evaluación guardada correctamente']);

    // --- AGREGADO: Programar entrevista si se aprobó ---
    if ($veredicto === 'aprobado' && !empty($_POST['fecha_entrevista'])) {
        try {
            $fechaEntrevista = $_POST['fecha_entrevista'];
            $horaEntrevista = $_POST['hora_entrevista'];
            $entrevistadorJefe = (int) $_POST['entrevistador_jefe'];
            $modalidad = $_POST['modalidad_entrevista'];
            $notasEntrevista = $_POST['notas_entrevista'] ?? '';

            // 1. Insertar entrevista en la base de datos
            $sqlEntrevista = "INSERT INTO entrevistas_candidatos 
                              (id_postulacion, fecha_entrevista, hora_entrevista, reclutador_entrevista, 
                               modalidad_entrevista, notas_adicionales, usuario_registra, fecha_creacion, resultado_entrevista)
                              VALUES 
                              (:id, :fecha, :hora, :entrevistador, :modalidad, :notas, :usuario, NOW(), 'Pendiente')";
            $stmtEntrevista = $conn->prepare($sqlEntrevista);
            $stmtEntrevista->execute([
                ':id' => $idPostulacion,
                ':fecha' => $fechaEntrevista,
                ':hora' => $horaEntrevista,
                ':entrevistador' => $entrevistadorJefe,
                ':modalidad' => $modalidad,
                ':notas' => $notasEntrevista,
                ':usuario' => $codOperario
            ]);

            // 2. Enviar Invitación de Calendario
            require_once __DIR__ . '/../../../core/email/EmailService.php';
            $emailService = new EmailService($conn);

            // Datos del candidato
            $sqlCandi = "SELECT nombre, correo FROM postulacion_plaza WHERE id = :id";
            $stmtCandi = $conn->prepare($sqlCandi);
            $stmtCandi->execute([':id' => $idPostulacion]);
            $datosCandi = $stmtCandi->fetch();

            // Datos del jefe
            $sqlJefe = "SELECT email_trabajo FROM Operarios WHERE CodOperario = :cod";
            $stmtJefe = $conn->prepare($sqlJefe);
            $stmtJefe->execute([':cod' => $entrevistadorJefe]);
            $emailJefe = $stmtJefe->fetchColumn();

            $asunto = "Entrevista Final: " . $datosCandi['nombre'];

            // Link directo para el jefe
            $linkEvaluacion = "https://erp.batidospitaya.com/modulos/reclutamiento/postulacion_evaluacion_jefe.php?id=" . $idPostulacion;
            $descripcion = "Has sido programado para realizar la entrevista técnica final.\n\n" .
                "Notas RH: " . $notasEntrevista . "\n\n" .
                "POR FAVOR, REALICE LA EVALUACIÓN AQUÍ AL FINALIZAR: " . $linkEvaluacion;

            // Enviar al jefe
            if (!empty($emailJefe)) {
                $emailService->enviarInvitacionCalendario(
                    $codOperario,
                    $emailJefe,
                    "Jefe de Área",
                    $asunto,
                    $descripcion,
                    $fechaEntrevista,
                    $horaEntrevista,
                    45,
                    $modalidad
                );
            }

            // Enviar al candidato
            if (!empty($datosCandi['correo'])) {
                $emailService->enviarInvitacionCalendario(
                    $codOperario,
                    $datosCandi['correo'],
                    $datosCandi['nombre'],
                    $asunto,
                    "Tu entrevista final ha sido programada con el Jefe de Área.\nModalidad: " . $modalidad,
                    $fechaEntrevista,
                    $horaEntrevista,
                    45,
                    $modalidad
                );
            }
        } catch (Exception $e_mail) {
            error_log("Error programando entrevista desde RH: " . $e_mail->getMessage());
        }
    }
    // --- FIN AGREGADO ---

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
