<?php
/**
 * Guardar nueva reunión
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/helpers/funciones.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo = $usuario['CodNivelesCargos'];

    // Validar datos
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fechaReunion = $_POST['fecha_reunion'] ?? '';
    $invitadosJson = $_POST['invitados'] ?? '[]';

    if (empty($titulo) || empty($fechaReunion)) {
        throw new Exception('Datos incompletos');
    }

    $invitados = json_decode($invitadosJson, true);

    if (empty($invitados)) {
        throw new Exception('Debe seleccionar al menos un invitado');
    }

    // Insertar reunión
    $sql = "INSERT INTO gestion_tareas_reuniones_items 
            (tipo, titulo, descripcion, cod_cargo_creador, cod_operario_creador, 
             fecha_reunion, estado, fecha_creacion) 
            VALUES 
            ('reunion', :titulo, :descripcion, :cod_cargo_creador, :cod_operario_creador, 
             :fecha_reunion, 'solicitado', NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':titulo' => $titulo,
        ':descripcion' => $descripcion,
        ':cod_cargo_creador' => $codCargo,
        ':cod_operario_creador' => $codOperario,
        ':fecha_reunion' => $fechaReunion
    ]);

    $idItem = $conn->lastInsertId();

    // Insertar participantes (incluyendo al creador)
    $sqlParticipante = "INSERT INTO gestion_tareas_reuniones_participantes 
                        (id_item, cod_cargo, confirmacion) 
                        VALUES (:id_item, :cod_cargo, 'pendiente')";

    $stmtParticipante = $conn->prepare($sqlParticipante);

    // Agregar invitados
    foreach ($invitados as $codCargoInvitado) {
        $stmtParticipante->execute([
            ':id_item' => $idItem,
            ':cod_cargo' => intval($codCargoInvitado)
        ]);
    }

    // Procesar archivos adjuntos
    if (isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0])) {
        $uploadDir = '../uploads/gestion_tareas_reuniones/reuniones/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $totalArchivos = count($_FILES['archivos']['name']);

        for ($i = 0; $i < $totalArchivos; $i++) {
            if ($_FILES['archivos']['error'][$i] === UPLOAD_ERR_OK) {
                $nombreOriginal = $_FILES['archivos']['name'][$i];
                $tmpName = $_FILES['archivos']['tmp_name'][$i];
                $tamano = $_FILES['archivos']['size'][$i];
                $tipo = $_FILES['archivos']['type'][$i];

                if ($tamano > 10 * 1024 * 1024) {
                    continue;
                }

                $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                $nombreArchivo = 'reunion_' . $idItem . '_' . time() . '_' . $i . '.' . $extension;
                $rutaCompleta = $uploadDir . $nombreArchivo;

                if (move_uploaded_file($tmpName, $rutaCompleta)) {
                    $rutaDB = str_replace('../', '', $rutaCompleta);

                    $sqlArchivo = "INSERT INTO gestion_tareas_reuniones_archivos 
                                   (id_item, tipo_vinculo, nombre_archivo, ruta_archivo, 
                                    tipo_archivo, tamano_bytes, cod_operario_subio, fecha_subida) 
                                   VALUES 
                                   (:id_item, 'item', :nombre_archivo, :ruta_archivo, 
                                    :tipo_archivo, :tamano_bytes, :cod_operario, NOW())";

                    $stmtArchivo = $conn->prepare($sqlArchivo);
                    $stmtArchivo->execute([
                        ':id_item' => $idItem,
                        ':nombre_archivo' => $nombreOriginal,
                        ':ruta_archivo' => $rutaDB,
                        ':tipo_archivo' => $tipo,
                        ':tamano_bytes' => $tamano,
                        ':cod_operario' => $codOperario
                    ]);
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reunión solicitada exitosamente',
        'id' => $idItem
    ]);

} catch (Exception $e) {
    error_log("Error en guardar reunión: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>