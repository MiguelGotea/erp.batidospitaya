<?php
/**
 * Finalizar tarea o subtarea
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo = $usuario['CodNivelesCargos'];

    $id = intval($_POST['id'] ?? 0);
    $detalles = trim($_POST['detalles'] ?? '');

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // Obtener el item
    $sql = "SELECT * FROM gestion_tareas_reuniones_items WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Item no encontrado');
    }

    // Verificar permisos
    if ($item['cod_cargo_asignado'] != $codCargo) {
        throw new Exception('No tiene permisos para finalizar este item');
    }

    // Actualizar estado
    $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                  SET estado = 'finalizado',
                      fecha_finalizacion = NOW(),
                      detalles_finalizacion = :detalles,
                      fecha_ultima_modificacion = NOW(),
                      cod_operario_ultima_modificacion = :cod_operario
                  WHERE id = :id";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->execute([
        ':detalles' => $detalles,
        ':cod_operario' => $codOperario,
        ':id' => $id
    ]);

    // Procesar archivos de finalización
    if (isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0])) {
        $uploadDir = '../uploads/gestion_tareas_reuniones/finalizaciones/';

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
                $nombreArchivo = 'finalizacion_' . $id . '_' . time() . '_' . $i . '.' . $extension;
                $rutaCompleta = $uploadDir . $nombreArchivo;

                if (move_uploaded_file($tmpName, $rutaCompleta)) {
                    $rutaDB = str_replace('../', '', $rutaCompleta);

                    $sqlArchivo = "INSERT INTO gestion_tareas_reuniones_archivos 
                                   (id_item, tipo_vinculo, nombre_archivo, ruta_archivo, 
                                    tipo_archivo, tamano_bytes, cod_operario_subio) 
                                   VALUES 
                                   (:id_item, 'finalizacion', :nombre_archivo, :ruta_archivo, 
                                    :tipo_archivo, :tamano_bytes, :cod_operario)";

                    $stmtArchivo = $conn->prepare($sqlArchivo);
                    $stmtArchivo->execute([
                        ':id_item' => $id,
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

    // Si es subtarea, actualizar progreso de la tarea padre
    if ($item['tipo'] == 'subtarea' && $item['id_padre']) {
        actualizarProgresoTarea($conn, $item['id_padre']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Finalizado exitosamente'
    ]);

} catch (Exception $e) {
    error_log("Error en finalizar: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Actualizar progreso de tarea basado en subtareas
 */
function actualizarProgresoTarea($conn, $idTarea)
{
    // Contar subtareas totales y finalizadas
    $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) as finalizadas
            FROM gestion_tareas_reuniones_items
            WHERE id_padre = :id_padre AND tipo = 'subtarea'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_padre' => $idTarea]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = $resultado['total'];
    $finalizadas = $resultado['finalizadas'];

    if ($total > 0) {
        $progreso = ($finalizadas / $total) * 100;

        // Si todas las subtareas están finalizadas, finalizar la tarea padre
        if ($finalizadas == $total) {
            $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                          SET estado = 'finalizado',
                              progreso = 100,
                              fecha_finalizacion = NOW(),
                              fecha_ultima_modificacion = NOW()
                          WHERE id = :id";
        } else {
            $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                          SET progreso = :progreso,
                              fecha_ultima_modificacion = NOW()
                          WHERE id = :id";
        }

        $stmtUpdate = $conn->prepare($sqlUpdate);
        $params = [':id' => $idTarea];

        if ($finalizadas != $total) {
            $params[':progreso'] = $progreso;
        }

        $stmtUpdate->execute($params);
    }
}
?>