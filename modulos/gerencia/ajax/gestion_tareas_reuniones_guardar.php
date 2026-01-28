<?php
/**
 * Guardar nueva tarea (crear o solicitar)
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
    $tipo = $_POST['tipo'] ?? ''; // 'crear' o 'solicitar'
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $codCargoAsignado = intval($_POST['cod_cargo_asignado'] ?? 0);
    $fechaMeta = $_POST['fecha_meta'] ?? '';

    if (empty($titulo) || $codCargoAsignado <= 0 || empty($fechaMeta)) {
        throw new Exception('Datos incompletos');
    }

    // Determinar estado según tipo
    $estado = ($tipo === 'solicitar') ? 'solicitado' : 'en_progreso';

    // Insertar tarea
    $sql = "INSERT INTO gestion_tareas_reuniones_items 
            (tipo, titulo, descripcion, cod_cargo_asignado, cod_cargo_creador, 
             cod_operario_creador, fecha_meta, estado, fecha_creacion) 
            VALUES 
            ('tarea', :titulo, :descripcion, :cod_cargo_asignado, :cod_cargo_creador, 
             :cod_operario_creador, :fecha_meta, :estado, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':titulo' => $titulo,
        ':descripcion' => $descripcion,
        ':cod_cargo_asignado' => $codCargoAsignado,
        ':cod_cargo_creador' => $codCargo,
        ':cod_operario_creador' => $codOperario,
        ':fecha_meta' => $fechaMeta,
        ':estado' => $estado
    ]);

    $idItem = $conn->lastInsertId();

    // Procesar archivos adjuntos
    if (isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0])) {
        $uploadDir = '../uploads/gestion_tareas_reuniones/tareas/';

        // Crear directorio si no existe
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

                // Validar tamaño (10MB)
                if ($tamano > 10 * 1024 * 1024) {
                    continue;
                }

                // Generar nombre único
                $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                $nombreArchivo = 'tarea_' . $idItem . '_' . time() . '_' . $i . '.' . $extension;
                $rutaCompleta = $uploadDir . $nombreArchivo;

                if (move_uploaded_file($tmpName, $rutaCompleta)) {
                    // Guardar en BD (la ruta en la BD debe ser relativa al módulo, sin el ../)
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

    $mensaje = ($tipo === 'solicitar') ? 'Tarea solicitada exitosamente' : 'Tarea creada exitosamente';

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'id' => $idItem
    ]);

} catch (Exception $e) {
    error_log("Error en guardar tarea: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>