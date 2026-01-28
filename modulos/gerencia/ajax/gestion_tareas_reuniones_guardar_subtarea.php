<?php
/**
 * Guardar nueva subtarea
 */

require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $codOperario = $usuario['CodOperario'];
    $codCargo = $usuario['CodNivelesCargos'];

    $idPadre = intval($_POST['id_padre'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fechaMeta = $_POST['fecha_meta'] ?? '';

    if ($idPadre <= 0 || empty($titulo) || empty($fechaMeta)) {
        throw new Exception('Datos incompletos');
    }

    // Verificar que la tarea padre existe y el usuario es el asignado
    $sqlPadre = "SELECT * FROM gestion_tareas_reuniones_items WHERE id = :id";
    $stmtPadre = $conn->prepare($sqlPadre);
    $stmtPadre->execute([':id' => $idPadre]);
    $padre = $stmtPadre->fetch(PDO::FETCH_ASSOC);

    if (!$padre) {
        throw new Exception('Tarea no encontrada');
    }

    if ($padre['cod_cargo_asignado'] != $codCargo) {
        throw new Exception('No tiene permisos para agregar subtareas');
    }

    // Insertar subtarea
    $sql = "INSERT INTO gestion_tareas_reuniones_items 
            (tipo, id_padre, titulo, descripcion, cod_cargo_asignado, 
             cod_cargo_creador, cod_operario_creador, fecha_meta, estado) 
            VALUES 
            ('subtarea', :id_padre, :titulo, :descripcion, :cod_cargo_asignado, 
             :cod_cargo_creador, :cod_operario_creador, :fecha_meta, 'en_progreso')";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_padre' => $idPadre,
        ':titulo' => $titulo,
        ':descripcion' => $descripcion,
        ':cod_cargo_asignado' => $codCargo,
        ':cod_cargo_creador' => $codCargo,
        ':cod_operario_creador' => $codOperario,
        ':fecha_meta' => $fechaMeta
    ]);

    $idSubtarea = $conn->lastInsertId();

    // Si la fecha meta de la subtarea es mayor que la del padre, actualizar el padre
    if ($fechaMeta > $padre['fecha_meta']) {
        $sqlUpdatePadre = "UPDATE gestion_tareas_reuniones_items 
                           SET fecha_meta = :fecha_meta,
                               fecha_ultima_modificacion = NOW(),
                               cod_operario_ultima_modificacion = :cod_operario
                           WHERE id = :id";
        $stmtUpdate = $conn->prepare($sqlUpdatePadre);
        $stmtUpdate->execute([
            ':fecha_meta' => $fechaMeta,
            ':cod_operario' => $codOperario,
            ':id' => $idPadre
        ]);
    }

    // Procesar archivos
    if (isset($_FILES['archivos']) && !empty($_FILES['archivos']['name'][0])) {
        $uploadDir = '../uploads/gestion_tareas_reuniones/tareas/';

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
                $nombreArchivo = 'subtarea_' . $idSubtarea . '_' . time() . '_' . $i . '.' . $extension;
                $rutaCompleta = $uploadDir . $nombreArchivo;

                if (move_uploaded_file($tmpName, $rutaCompleta)) {
                    $rutaDB = str_replace('../', '', $rutaCompleta);

                    $sqlArchivo = "INSERT INTO gestion_tareas_reuniones_archivos 
                                   (id_item, tipo_vinculo, nombre_archivo, ruta_archivo, 
                                    tipo_archivo, tamano_bytes, cod_operario_subio) 
                                   VALUES 
                                   (:id_item, 'item', :nombre_archivo, :ruta_archivo, 
                                    :tipo_archivo, :tamano_bytes, :cod_operario)";

                    $stmtArchivo = $conn->prepare($sqlArchivo);
                    $stmtArchivo->execute([
                        ':id_item' => $idSubtarea,
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

    // Actualizar progreso de la tarea padre
    actualizarProgresoTarea($conn, $idPadre);

    echo json_encode([
        'success' => true,
        'message' => 'Subtarea creada exitosamente',
        'id' => $idSubtarea
    ]);

} catch (Exception $e) {
    error_log("Error en guardar_subtarea: " . $e->getMessage());
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

    $total = intval($resultado['total']);
    $finalizadas = intval($resultado['finalizadas']);

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
    } else {
        // Si no hay subtareas, resetear progreso
        $sqlUpdate = "UPDATE gestion_tareas_reuniones_items 
                      SET progreso = 0,
                          fecha_ultima_modificacion = NOW()
                      WHERE id = :id";

        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->execute([':id' => $idTarea]);
    }
}
?>