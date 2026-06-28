<?php
// guardar_colaborador.php - Guardar/Editar un colaborador y subir su fotografía
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    $colaborador_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Verificar permisos específicos
    if ($colaborador_id > 0) {
        if (!tienePermiso('talento_contenido', 'editar', $cargoOperario)) {
            throw new Exception("No tienes privilegios para editar colaboradores.");
        }
    } else {
        if (!tienePermiso('talento_contenido', 'crear', $cargoOperario)) {
            throw new Exception("No tienes privilegios para crear colaboradores.");
        }
    }

    // Validar entradas básicas
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $cargo = isset($_POST['cargo']) ? trim($_POST['cargo']) : '';
    $departamento = isset($_POST['departamento']) ? trim($_POST['departamento']) : '';
    $testimonio = isset($_POST['testimonio']) ? trim($_POST['testimonio']) : '';
    $orden = isset($_POST['orden']) ? intval($_POST['orden']) : 0;
    $activo = isset($_POST['activo']) ? intval($_POST['activo']) : 1;

    if (empty($nombre) || empty($cargo) || empty($testimonio)) {
        throw new Exception("Por favor rellena todos los campos obligatorios (*).");
    }

    // Manejo de la subida de foto
    $foto_subida = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['foto'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("El tipo de archivo de imagen no está permitido (solo JPG, PNG o WebP).");
        }

        if ($file['size'] > 50 * 1024 * 1024) {
            throw new Exception("La imagen excede el tamaño máximo permitido de 50MB.");
        }

        // Crear carpeta destino si no existe
        $target_dir = "../../../../talento.batidospitaya/uploads/equipo/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        // Generar nombre de archivo único
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = ($file['type'] === 'image/png') ? 'png' : (($file['type'] === 'image/webp') ? 'webp' : 'jpg');
        }
        $filename = "colab_" . time() . "_" . uniqid() . "." . strtolower($ext);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $foto_subida = $filename;
        } else {
            throw new Exception("Error al guardar la fotografía en el servidor.");
        }
    }

    if ($colaborador_id > 0) {
        // --- MODO EDICIÓN ---
        // Obtener foto anterior si subió una nueva
        $stmtFoto = $conn->prepare("SELECT foto FROM colaboradores_talento WHERE id = :id LIMIT 1");
        $stmtFoto->bindValue(':id', $colaborador_id, PDO::PARAM_INT);
        $stmtFoto->execute();
        $foto_anterior = $stmtFoto->fetchColumn();

        if ($foto_subida) {
            // Eliminar foto anterior física si existe
            if ($foto_anterior && file_exists("../../../../talento.batidospitaya/uploads/equipo/" . $foto_anterior)) {
                @unlink("../../../../talento.batidospitaya/uploads/equipo/" . $foto_anterior);
            }
            // Actualizar con nueva foto
            $stmt = $conn->prepare("UPDATE colaboradores_talento SET nombre = :nombre, cargo = :cargo, departamento = :departamento, testimonio = :testimonio, orden = :orden, activo = :activo, foto = :foto WHERE id = :id");
            $stmt->bindValue(':foto', $foto_subida, PDO::PARAM_STR);
        } else {
            // Actualizar sin cambiar foto
            $stmt = $conn->prepare("UPDATE colaboradores_talento SET nombre = :nombre, cargo = :cargo, departamento = :departamento, testimonio = :testimonio, orden = :orden, activo = :activo WHERE id = :id");
        }
        $stmt->bindValue(':id', $colaborador_id, PDO::PARAM_INT);
    } else {
        // --- MODO CREACIÓN ---
        $stmt = $conn->prepare("INSERT INTO colaboradores_talento (nombre, cargo, departamento, testimonio, orden, activo, foto) VALUES (:nombre, :cargo, :departamento, :testimonio, :orden, :activo, :foto)");
        $stmt->bindValue(':foto', $foto_subida, PDO::PARAM_STR); // puede ser null
    }

    $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
    $stmt->bindValue(':cargo', $cargo, PDO::PARAM_STR);
    $stmt->bindValue(':departamento', $departamento, PDO::PARAM_STR);
    $stmt->bindValue(':testimonio', $testimonio, PDO::PARAM_STR);
    $stmt->bindValue(':orden', $orden, PDO::PARAM_INT);
    $stmt->bindValue(':activo', $activo, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => $colaborador_id > 0 ? "Colaborador actualizado correctamente." : "Colaborador agregado correctamente."
        ]);
    } else {
        throw new Exception("Error al guardar en la base de datos.");
    }

} catch (Exception $e) {
    // Si falló el registro pero se subió la foto nueva, borrarla para no dejar basura
    if (isset($foto_subida) && $foto_subida && file_exists("../../../../talento.batidospitaya/uploads/equipo/" . $foto_subida)) {
        @unlink("../../../../talento.batidospitaya/uploads/equipo/" . $foto_subida);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
