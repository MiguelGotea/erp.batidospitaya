<?php
/**
 * Agregar comentario a un item
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];

    $idItem = intval($_POST['id_item'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');

    if ($idItem <= 0 || empty($comentario)) {
        throw new Exception('Datos incompletos');
    }

    // Insertar comentario
    $sql = "INSERT INTO gestion_tareas_reuniones_comentarios 
            (id_item, cod_operario, comentario, fecha_creacion) 
            VALUES 
            (:id_item, :cod_operario, :comentario, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_item' => $idItem,
        ':cod_operario' => $codOperario,
        ':comentario' => $comentario
    ]);

    $idComentario = $conn->lastInsertId();

    // Procesar archivos adjuntos
    if (isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0])) {
        $uploadDir = '../uploads/gestion_tareas_reuniones/comentarios/';

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

                if ($tamano > 10 * 1024 * 1024)
                    continue;

                $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                $nombreArchivo = 'comentario_' . $idComentario . '_' . time() . '_' . $i . '.' . $extension;
                $rutaCompleta = $uploadDir . $nombreArchivo;

                if (move_uploaded_file($tmpName, $rutaCompleta)) {
                    $rutaDB = str_replace('../', '', $rutaCompleta);

                    $sqlArchivo = "INSERT INTO gestion_tareas_reuniones_archivos 
                                   (id_item, id_comentario, tipo_vinculo, nombre_archivo, 
                                    ruta_archivo, tipo_archivo, tamano_bytes, cod_operario_subio) 
                                   VALUES 
                                   (:id_item, :id_comentario, 'comentario', :nombre_archivo, 
                                    :ruta_archivo, :tipo_archivo, :tamano_bytes, :cod_operario)";

                    $stmtArchivo = $conn->prepare($sqlArchivo);
                    $stmtArchivo->execute([
                        ':id_item' => $idItem,
                        ':id_comentario' => $idComentario,
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
        'message' => 'Comentario agregado exitosamente',
        'id' => $idComentario
    ]);

} catch (Exception $e) {
    error_log("Error en agregar_comentario: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>