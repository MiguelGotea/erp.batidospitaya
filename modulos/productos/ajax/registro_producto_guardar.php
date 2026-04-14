<?php
// registro_producto_guardar.php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $sku = isset($_POST['sku']) ? trim($_POST['sku']) : '';
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $idProductoMaestro = isset($_POST['id_producto_maestro']) ? (int) $_POST['id_producto_maestro'] : 0;
    $idUnidad = isset($_POST['id_unidad_producto']) ? (int) $_POST['id_unidad_producto'] : 0;
    $cantidad = isset($_POST['cantidad']) ? (float) $_POST['cantidad'] : 0.00;

    // CORREGIDO: JavaScript envía 'SI' o 'NO', no 'on'
    $esVendible = isset($_POST['es_vendible']) && $_POST['es_vendible'] === 'SI' ? 'SI' : 'NO';
    $esComprable = isset($_POST['es_comprable']) && $_POST['es_comprable'] === 'SI' ? 'SI' : 'NO';
    $esFabricable = isset($_POST['es_fabricable']) && $_POST['es_fabricable'] === 'SI' ? 'SI' : 'NO';
    $compraTienda = isset($_POST['compra_tienda']) && intval($_POST['compra_tienda']) === 1 ? 1 : 0;

    $idSubgrupo = isset($_POST['id_subgrupo_presentacion_producto']) && $_POST['id_subgrupo_presentacion_producto'] !== ''
        ? (int) $_POST['id_subgrupo_presentacion_producto']
        : null;

    $categoriaInsumo = isset($_POST['categoria_insumo']) ? trim($_POST['categoria_insumo']) : null;

    // Activo siempre es 'SI' por defecto (no hay checkbox en el formulario)
    $activo = 'SI';

    // Receta
    $tieneReceta = isset($_POST['tiene_receta']) && $_POST['tiene_receta'] === '1';
    $nombreReceta = isset($_POST['nombre_receta']) ? trim($_POST['nombre_receta']) : '';
    $idTipoReceta = isset($_POST['id_tipo_receta']) && $_POST['id_tipo_receta'] !== ''
        ? (int) $_POST['id_tipo_receta']
        : null;
    $descripcionReceta = isset($_POST['descripcion_receta']) ? trim($_POST['descripcion_receta']) : null;

    $usuarioId = $_SESSION['usuario_id'];

    // Validaciones
    if (empty($sku)) {
        throw new Exception('El SKU es obligatorio');
    }

    if (empty($nombre)) {
        throw new Exception('El nombre es obligatorio');
    }

    // MODIFICADO: Producto maestro y unidad son obligatorios solo si NO tiene receta
    if (!$tieneReceta) {
        if ($idProductoMaestro <= 0) {
            throw new Exception('Debe seleccionar un producto maestro');
        }

        if ($idUnidad <= 0) {
            throw new Exception('Debe seleccionar una unidad');
        }
    }

    // Validar SKU único
    $sqlCheck = "SELECT COUNT(*) as total FROM producto_presentacion WHERE SKU = :sku";
    if ($id > 0) {
        $sqlCheck .= " AND id != :id";
    }
    $stmtCheck = $conn->prepare($sqlCheck);
    $params = [':sku' => $sku];
    if ($id > 0) {
        $params[':id'] = $id;
    }
    $stmtCheck->execute($params);

    if ($stmtCheck->fetch()['total'] > 0) {
        throw new Exception('Ya existe un producto con ese SKU');
    }

    // Validar receta si aplica
    if ($tieneReceta) {
        if (empty($nombreReceta)) {
            throw new Exception('El nombre de la receta es obligatorio');
        }
        if (!$idTipoReceta) {
            throw new Exception('Debe seleccionar un tipo de receta');
        }
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Guardar/Actualizar producto PRIMERO (antes de la receta)
    if ($id > 0) {
        // ACTUALIZAR producto existente (sin receta por ahora)
        $sql = "UPDATE producto_presentacion SET 
                SKU = :sku,
                Nombre = :nombre,
                id_producto_maestro = :id_producto_maestro,
                id_unidad_producto = :id_unidad_producto,
                es_vendible = :es_vendible,
                es_comprable = :es_comprable,
                es_fabricable = :es_fabricable,
                id_subgrupo_presentacion_producto = :id_subgrupo,
                Activo = :activo,
                cantidad = :cantidad,
                compra_tienda = :compra_tienda,
                categoria_insumo = :categoria_insumo,
                usuario_modificacion = :usuario_mod,
                fecha_modificacion = NOW()
                WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':sku' => $sku,
            ':nombre' => $nombre,
            ':id_producto_maestro' => ($idProductoMaestro > 0) ? $idProductoMaestro : null,
            ':id_unidad_producto' => ($idUnidad > 0) ? $idUnidad : null,
            ':es_vendible' => $esVendible,
            ':es_comprable' => $esComprable,
            ':es_fabricable' => $esFabricable,
            ':id_subgrupo' => $idSubgrupo,
            ':activo' => $activo,
            ':cantidad' => ($tieneReceta && $cantidad == 0) ? null : $cantidad,
            ':compra_tienda' => $compraTienda,
            ':categoria_insumo' => $categoriaInsumo,
            ':usuario_mod' => $usuarioId,
            ':id' => $id
        ]);

        $idProducto = $id;

    } else {
        // CREAR NUEVO producto (sin receta por ahora)
        $sql = "INSERT INTO producto_presentacion 
                (SKU, Nombre, id_producto_maestro, id_unidad_producto, 
                 es_vendible, es_comprable, es_fabricable, 
                 id_subgrupo_presentacion_producto, 
                 Activo, cantidad, compra_tienda, categoria_insumo, usuario_creacion)
                VALUES 
                (:sku, :nombre, :id_producto_maestro, :id_unidad_producto,
                 :es_vendible, :es_comprable, :es_fabricable,
                 :id_subgrupo,
                 :activo, :cantidad, :compra_tienda, :categoria_insumo, :usuario_creacion)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':sku' => $sku,
            ':nombre' => $nombre,
            ':id_producto_maestro' => ($idProductoMaestro > 0) ? $idProductoMaestro : null,
            ':id_unidad_producto' => ($idUnidad > 0) ? $idUnidad : null,
            ':es_vendible' => $esVendible,
            ':es_comprable' => $esComprable,
            ':es_fabricable' => $esFabricable,
            ':id_subgrupo' => $idSubgrupo,
            ':activo' => $activo,
            ':cantidad' => ($tieneReceta && $cantidad == 0) ? null : $cantidad,
            ':compra_tienda' => $compraTienda,
            ':categoria_insumo' => $categoriaInsumo,
            ':usuario_creacion' => $usuarioId
        ]);

        $idProducto = $conn->lastInsertId();
    }

    // Ahora gestionar receta (después de que el producto existe)
    $idRecetaProducto = null;

    if ($tieneReceta) {
        // CORREGIDO: Buscar si ya existe una receta para este producto directamente en la tabla de recetas
        // Esto evita el error de "Duplicate entry" si el producto no tiene el ID de receta vinculado pero la receta ya existe
        $sqlCheckReceta = "SELECT id FROM receta_producto_global WHERE id_presentacion_producto = :id_p";
        $stmtCheckReceta = $conn->prepare($sqlCheckReceta);
        $stmtCheckReceta->execute([':id_p' => $idProducto]);
        $idRecetaExistente = $stmtCheckReceta->fetchColumn();

        if ($idRecetaExistente) {
            // Actualizar receta existente
            $sqlUpdateReceta = "UPDATE receta_producto_global SET
                               nombre = :nombre,
                               id_tipo_receta = :id_tipo,
                               descripcion = :descripcion,
                               usuario_modificacion = :usuario_mod,
                               fecha_modificacion = NOW()
                               WHERE id = :id_receta";

            $stmtUpdateReceta = $conn->prepare($sqlUpdateReceta);
            $stmtUpdateReceta->execute([
                ':nombre' => $nombreReceta,
                ':id_tipo' => $idTipoReceta,
                ':descripcion' => $descripcionReceta,
                ':usuario_mod' => $usuarioId,
                ':id_receta' => $idRecetaExistente
            ]);

            $idRecetaProducto = $idRecetaExistente;
        } else {
            // Crear nueva receta
            $sqlInsertReceta = "INSERT INTO receta_producto_global 
                               (nombre, id_tipo_receta, descripcion, id_presentacion_producto, usuario_creacion)
                               VALUES (:nombre, :id_tipo, :descripcion, :id_presentacion, :usuario_creacion)";

            $stmtInsertReceta = $conn->prepare($sqlInsertReceta);
            $stmtInsertReceta->execute([
                ':nombre' => $nombreReceta,
                ':id_tipo' => $idTipoReceta,
                ':descripcion' => $descripcionReceta,
                ':id_presentacion' => $idProducto,
                ':usuario_creacion' => $usuarioId
            ]);

            $idRecetaProducto = $conn->lastInsertId();
        }

        // Actualizar el producto con el ID de la receta (por si no estaba vinculado)
        $sqlUpdateProductoReceta = "UPDATE producto_presentacion SET Id_receta_producto = :id_receta WHERE id = :id";
        $stmtUpdateProductoReceta = $conn->prepare($sqlUpdateProductoReceta);
        $stmtUpdateProductoReceta->execute([
            ':id_receta' => $idRecetaProducto,
            ':id' => $idProducto
        ]);
    } else {
        // CORREGIDO: Si NO tiene receta, asegurarse de limpiar el campo en el producto
        $sqlUpdateProductoReceta = "UPDATE producto_presentacion SET Id_receta_producto = NULL WHERE id = :id";
        $stmtUpdateProductoReceta = $conn->prepare($sqlUpdateProductoReceta);
        $stmtUpdateProductoReceta->execute([':id' => $idProducto]);
    }

    // Confirmar transacción de producto y receta básica
    // (Mantendremos la transacción abierta para los componentes, variaciones y fichas)

    // 1. GESTIONAR COMPONENTES (Si hay receta)
    if ($idRecetaProducto) {
        $componentes = isset($_POST['componentes']) ? json_decode($_POST['componentes'], true) : [];
        if (json_last_error() === JSON_ERROR_NONE) {
            // Eliminar componentes anteriores
            $sqlDeleteComp = "DELETE FROM componentes_receta_producto WHERE id_receta_producto_global = :id_receta";
            $stmtDeleteComp = $conn->prepare($sqlDeleteComp);
            $stmtDeleteComp->execute([':id_receta' => $idRecetaProducto]);

            // Insertar nuevos componentes
            if (!empty($componentes)) {
                $sqlInsertComp = "INSERT INTO componentes_receta_producto 
                                 (id_receta_producto_global, id_presentacion_producto, nombre, cantidad, notas, orden, usuario_creacion)
                                 VALUES (:id_receta, :id_pp, :nombre, :cant, :notas, :orden, :user)";
                $stmtInsertComp = $conn->prepare($sqlInsertComp);
                foreach ($componentes as $index => $comp) {
                    $stmtInsertComp->execute([
                        ':id_receta' => $idRecetaProducto,
                        ':id_pp' => $comp['id_presentacion_producto'],
                        ':nombre' => $comp['nombre_producto'], // Agregado campo nombre obligatorio
                        ':cant' => $comp['cantidad'],
                        ':notas' => $comp['notas'] ?? '',
                        ':orden' => $index + 1,
                        ':user' => $usuarioId
                    ]);
                }
            }
        }
    }

    // 2. GESTIONAR VARIACIONES
    $variaciones = isset($_POST['variaciones']) ? json_decode($_POST['variaciones'], true) : [];
    if (json_last_error() === JSON_ERROR_NONE) {
        // Eliminar variaciones anteriores
        $sqlDeleteVar = "DELETE FROM variedad_producto_presentacion WHERE id_presentacion_producto = :id_p";
        $stmtDeleteVar = $conn->prepare($sqlDeleteVar);
        $stmtDeleteVar->execute([':id_p' => $idProducto]);

        // Insertar nuevas variaciones
        if (!empty($variaciones)) {
            // Validar que exactamente uno sea principal
            $conteoPrincipal = 0;
            foreach ($variaciones as $v) {
                if (isset($v['es_principal']) && $v['es_principal'] == 1) {
                    $conteoPrincipal++;
                }
            }

            if ($conteoPrincipal !== 1) {
                throw new Exception("Debe haber exactamente una variación marcada como Principal.");
            }

            $sqlInsertVar = "INSERT INTO variedad_producto_presentacion 
                            (id_presentacion_producto, nombre, descripcion, es_principal, usuario_creacion)
                            VALUES (:id_p, :nombre, :desc, :es_p, :user)";
            $stmtInsertVar = $conn->prepare($sqlInsertVar);
            foreach ($variaciones as $var) {
                $stmtInsertVar->execute([
                    ':id_p' => $idProducto,
                    ':nombre' => $var['nombre'],
                    ':desc' => $var['descripcion'] ?? '',
                    ':es_p' => isset($var['es_principal']) ? (int) $var['es_principal'] : 0,
                    ':user' => $usuarioId
                ]);
            }
        }
    }

    // 3. GESTIONAR FICHA TÉCNICA
    $fichas = isset($_POST['fichas']) ? json_decode($_POST['fichas'], true) : [];
    if (json_last_error() === JSON_ERROR_NONE) {
        // Eliminar ficha anterior
        $sqlDeleteFicha = "DELETE FROM fichatecnica_presentacion_producto WHERE id_presentacion_producto = :id_p";
        $stmtDeleteFicha = $conn->prepare($sqlDeleteFicha);
        $stmtDeleteFicha->execute([':id_p' => $idProducto]);

        // Insertar nueva ficha
        if (!empty($fichas)) {
            $sqlInsertFicha = "INSERT INTO fichatecnica_presentacion_producto 
                              (id_presentacion_producto, campo, descripcion, usuario_creacion)
                              VALUES (:id_p, :campo, :desc, :user)";
            $stmtInsertFicha = $conn->prepare($sqlInsertFicha);
            foreach ($fichas as $f) {
                $stmtInsertFicha->execute([
                    ':id_p' => $idProducto,
                    ':campo' => $f['campo'],
                    ':desc' => $f['descripcion'],
                    ':user' => $usuarioId
                ]);
            }
        }
    }

    // Confirmar transacción total
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $id > 0 ? 'Producto actualizado exitosamente' : 'Producto creado exitosamente',
        'id_producto' => $idProducto
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>