<?php
// guardar_noticia.php - Guardar/Editar una noticia y subir su imagen de portada
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';
require_once '../../../core/database/conexion.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];

    $noticia_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // Verificar permisos específicos
    if ($noticia_id > 0) {
        if (!tienePermiso('talento_contenido', 'editar', $cargoOperario)) {
            throw new Exception("No tienes privilegios para editar noticias.");
        }
    } else {
        if (!tienePermiso('talento_contenido', 'crear', $cargoOperario)) {
            throw new Exception("No tienes privilegios para crear noticias.");
        }
    }

    // Validar entradas básicas
    $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
    $categoria = isset($_POST['categoria']) ? trim($_POST['categoria']) : 'General';
    $autor = isset($_POST['autor']) ? trim($_POST['autor']) : '';
    $fecha_publicacion = isset($_POST['fecha_publicacion']) ? trim($_POST['fecha_publicacion']) : null;
    $estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'borrador';
    $resumen = isset($_POST['resumen']) ? trim($_POST['resumen']) : '';
    $contenido = isset($_POST['contenido']) ? trim($_POST['contenido']) : '';

    if (empty($titulo) || empty($autor) || empty($fecha_publicacion) || empty($resumen) || empty($contenido)) {
        throw new Exception("Por favor rellena todos los campos obligatorios (*).");
    }

    // Manejo de la subida de foto de portada
    $portada_subida = null;
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['portada'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("El tipo de archivo de imagen no está permitido (solo JPG, PNG o WebP).");
        }

        if ($file['size'] > 50 * 1024 * 1024) {
            throw new Exception("La imagen excede el tamaño máximo permitido de 50MB.");
        }

        // Crear carpeta destino si no existe
        $target_dir = "../../../../talento.batidospitaya/uploads/noticias/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        // Generar nombre de archivo único
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = ($file['type'] === 'image/png') ? 'png' : (($file['type'] === 'image/webp') ? 'webp' : 'jpg');
        }
        $filename = "news_" . time() . "_" . uniqid() . "." . strtolower($ext);
        $target_file = $target_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $portada_subida = $filename;
        } else {
            throw new Exception("Error al guardar la imagen de portada en el servidor.");
        }
    }

    if ($noticia_id > 0) {
        // --- MODO EDICIÓN ---
        // Obtener portada anterior si subió una nueva
        $stmtFoto = $conn->prepare("SELECT imagen_principal FROM noticias_talento WHERE id = :id LIMIT 1");
        $stmtFoto->bindValue(':id', $noticia_id, PDO::PARAM_INT);
        $stmtFoto->execute();
        $foto_anterior = $stmtFoto->fetchColumn();

        if ($portada_subida) {
            // Eliminar portada anterior física si existe
            if ($foto_anterior && file_exists("../../../../talento.batidospitaya/uploads/noticias/" . $foto_anterior)) {
                @unlink("../../../../talento.batidospitaya/uploads/noticias/" . $foto_anterior);
            }
            // Actualizar con nueva portada
            $stmt = $conn->prepare("UPDATE noticias_talento SET titulo = :titulo, categoria = :categoria, autor = :autor, fecha_publicacion = :fecha_publicacion, estado = :estado, resumen = :resumen, contenido = :contenido, imagen_principal = :imagen_principal WHERE id = :id");
            $stmt->bindValue(':imagen_principal', $portada_subida, PDO::PARAM_STR);
        } else {
            // Actualizar sin cambiar portada
            $stmt = $conn->prepare("UPDATE noticias_talento SET titulo = :titulo, categoria = :categoria, autor = :autor, fecha_publicacion = :fecha_publicacion, estado = :estado, resumen = :resumen, contenido = :contenido WHERE id = :id");
        }
        $stmt->bindValue(':id', $noticia_id, PDO::PARAM_INT);
    } else {
        // --- MODO CREACIÓN ---
        $stmt = $conn->prepare("INSERT INTO noticias_talento (titulo, categoria, autor, fecha_publicacion, estado, resumen, contenido, imagen_principal) VALUES (:titulo, :categoria, :autor, :fecha_publicacion, :estado, :resumen, :contenido, :imagen_principal)");
        $stmt->bindValue(':imagen_principal', $portada_subida, PDO::PARAM_STR); // puede ser null
    }

    $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
    $stmt->bindValue(':categoria', $categoria, PDO::PARAM_STR);
    $stmt->bindValue(':autor', $autor, PDO::PARAM_STR);
    $stmt->bindValue(':fecha_publicacion', $fecha_publicacion, PDO::PARAM_STR);
    $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
    $stmt->bindValue(':resumen', $resumen, PDO::PARAM_STR);
    $stmt->bindValue(':contenido', $contenido, PDO::PARAM_STR);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => $noticia_id > 0 ? "Noticia actualizada correctamente." : "Noticia creada correctamente."
        ]);
    } else {
        throw new Exception("Error al guardar en la base de datos.");
    }

} catch (Exception $e) {
    // Si falló el registro pero se subió la foto nueva, borrarla para no dejar basura
    if (isset($portada_subida) && $portada_subida && file_exists("../../../../talento.batidospitaya/uploads/noticias/" . $portada_subida)) {
        @unlink("../../../../talento.batidospitaya/uploads/noticias/" . $portada_subida);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
