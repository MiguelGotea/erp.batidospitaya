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
    $presBasica = isset($_POST['presentacion_basica_inventario']) && intval($_POST['presentacion_basica_inventario']) === 1 ? 1 : 0;
    $presDespacho = isset($_POST['presentacion_despacho']) && intval($_POST['presentacion_despacho']) === 1 ? 1 : 0;
    $presentacion = isset($_POST['presentacion']) ? trim($_POST['presentacion']) : null;

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
                presentacion_basica_inventario = :pres_basica,
                presentacion_despacho = :pres_despacho,
                presentacion = :presentacion,
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
            ':pres_basica' => $presBasica,
            ':pres_despacho' => $presDespacho,
            ':presentacion' => $presentacion,
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
                 Activo, cantidad, compra_tienda, categoria_insumo, 
                 presentacion_basica_inventario, presentacion_despacho, presentacion, usuario_creacion)
                VALUES 
                (:sku, :nombre, :id_producto_maestro, :id_unidad_producto,
                 :es_vendible, :es_comprable, :es_fabricable,
                 :id_subgrupo,
                 :activo, :cantidad, :compra_tienda, :categoria_insumo, 
                 :pres_basica, :pres_despacho, :presentacion, :usuario_creacion)";

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
            ':pres_basica' => $presBasica,
            ':pres_despacho' => $presDespacho,
            ':presentacion' => $presentacion,
            ':usuario_creacion' => $usuarioId
        ]);

        $idProducto = $conn->lastInsertId();
    }

    // Ahora gestionar receta (después de que el producto existe)
    $idRecetaProducto = null;

    if ($tieneReceta) {
        // 1. PRIORIDAD: Buscar si el producto ya tiene un Id_receta_producto vinculado
        $sqlRecetaActual = "SELECT Id_receta_producto FROM producto_presentacion WHERE id = :id";
        $stmtRecetaActual = $conn->prepare($sqlRecetaActual);
        $stmtRecetaActual->execute([':id' => $idProducto]);
        $idRecetaProducto = $stmtRecetaActual->fetchColumn();

        // 2. RESPALDO: Si no tiene el ID vinculado, buscar si existe una receta con este id_presentacion_producto
        if (!$idRecetaProducto) {
            $sqlCheckReceta = "SELECT id FROM receta_producto_global WHERE id_presentacion_producto = :id_p LIMIT 1";
            $stmtCheckReceta = $conn->prepare($sqlCheckReceta);
            $stmtCheckReceta->execute([':id_p' => $idProducto]);
            $idRecetaProducto = $stmtCheckReceta->fetchColumn();
        }

        if ($idRecetaProducto) {
            // Actualizar receta existente
            $sqlUpdateReceta = "UPDATE receta_producto_global SET
                               nombre = :nombre,
                               id_tipo_receta = :id_tipo,
                               descripcion = :descripcion,
                               id_presentacion_producto = :id_p,
                               usuario_modificacion = :usuario_mod,
                               fecha_modificacion = NOW()
                               WHERE id = :id_receta";

            $stmtUpdateReceta = $conn->prepare($sqlUpdateReceta);
            $stmtUpdateReceta->execute([
                ':nombre' => $nombreReceta,
                ':id_tipo' => $idTipoReceta,
                ':descripcion' => $descripcionReceta,
                ':id_p' => $idProducto,
                ':usuario_mod' => $usuarioId,
                ':id_receta' => $idRecetaProducto
            ]);
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

        // Asegurarse de que el producto esté vinculado a ESTA receta (por si acaso)
        $sqlUpdateProductoReceta = "UPDATE producto_presentacion SET Id_receta_producto = :id_receta WHERE id = :id";
        $stmtUpdateProductoReceta = $conn->prepare($sqlUpdateProductoReceta);
        $stmtUpdateProductoReceta->execute([
            ':id_receta' => $idRecetaProducto,
            ':id' => $idProducto
        ]);

    } else {
        // Si NO tiene receta, asegurarse de limpiar el campo en el producto
        $sqlUpdateProductoReceta = "UPDATE producto_presentacion SET Id_receta_producto = NULL WHERE id = :id";
        $stmtUpdateProductoReceta = $conn->prepare($sqlUpdateProductoReceta);
        $stmtUpdateProductoReceta->execute([':id' => $idProducto]);
    }

    // GESTIONAR COMPONENTES (Si hay receta identificada o creada)
    $debugInfo = [
        'id_receta' => $idRecetaProducto,
        'num_componentes_recibidos' => 0
    ];

    if ($idRecetaProducto && $idRecetaProducto > 0) {
        $componentesRaw = isset($_POST['componentes']) ? $_POST['componentes'] : '[]';
        $componentes = json_decode($componentesRaw, true);
        
        // VALIDACIÓN ESTRICTA: Si no es un JSON válido, lanzamos error para que no sea una falla silenciosa
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error de formato en el listado de componentes: ' . json_last_error_msg());
        }

        if (!is_array($componentes)) {
            throw new Exception('El listado de componentes debe ser un arreglo.');
        }

        $debugInfo['num_componentes_recibidos'] = count($componentes);

        // 1. Eliminar TODOS los componentes anteriores vinculados a esta receta específica
        $sqlDeleteComp = "DELETE FROM componentes_receta_producto WHERE id_receta_producto_global = :id_receta";
        $stmtDeleteComp = $conn->prepare($sqlDeleteComp);
        $stmtDeleteComp->execute([':id_receta' => $idRecetaProducto]);

        // 2. Insertar los nuevos componentes del arreglo (si los hay)
        if (!empty($componentes)) {
            $sqlInsertComp = "INSERT INTO componentes_receta_producto 
                             (id_receta_producto_global, id_presentacion_producto, nombre, cantidad, notas, orden, usuario_creacion)
                             VALUES (:id_receta, :id_pp, :nombre, :cant, :notas, :orden, :user)";
            $stmtInsertComp = $conn->prepare($sqlInsertComp);
            
            foreach ($componentes as $index => $comp) {
                // Validar datos mínimos del componente
                if (!isset($comp['id_presentacion_producto'])) {
                    throw new Exception("Error en el componente #" . ($index + 1) . ": ID de producto no definido.");
                }

                $stmtInsertComp->execute([
                    ':id_receta' => $idRecetaProducto,
                    ':id_pp' => $comp['id_presentacion_producto'],
                    ':nombre' => isset($comp['nombre_producto']) ? $comp['nombre_producto'] : 'Componente',
                    ':cant' => $comp['cantidad'],
                    ':notas' => isset($comp['notas']) ? $comp['notas'] : '',
                    ':orden' => $index + 1,
                    ':user' => $usuarioId
                ]);
            }
        }
    } else if ($tieneReceta) {
        throw new Exception('Se marcó que tiene receta pero no se pudo identificar o crear el registro de receta global.');
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