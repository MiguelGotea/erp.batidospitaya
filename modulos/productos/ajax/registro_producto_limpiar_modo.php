<?php
// registro_producto_limpiar_modo.php
// Limpia residuos en BD cuando se cambia el modo del toggle "¿Este producto tiene receta?"
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $id       = isset($input['id'])   ? (int) $input['id']   : 0;
    $modo     = isset($input['modo']) ? trim($input['modo']) : ''; // 'a_receta' | 'a_producto'

    if ($id <= 0) {
        throw new Exception('ID de producto inválido');
    }

    if (!in_array($modo, ['a_receta', 'a_producto'])) {
        throw new Exception('Modo inválido. Use "a_receta" o "a_producto"');
    }

    // Verificar que el producto existe
    $stmtCheck = $conn->prepare("SELECT id, Id_receta_producto FROM producto_presentacion WHERE id = :id LIMIT 1");
    $stmtCheck->execute([':id' => $id]);
    $producto = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }

    $conn->beginTransaction();

    if ($modo === 'a_receta') {
        // -------------------------------------------------------
        // PRODUCTO → RECETA
        // Limpiar campos de "producto presentación":
        //   - cantidad       → NULL
        //   - id_producto_maestro → NULL
        //   - id_unidad_producto  → 9  (valor por defecto cuando es receta)
        // -------------------------------------------------------
        $sql = "UPDATE producto_presentacion SET
                    cantidad             = NULL,
                    id_producto_maestro  = NULL,
                    id_unidad_producto   = 9,
                    fecha_modificacion   = NOW()
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id]);

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Campos de producto presentación limpiados para modo receta',
            'modo'    => $modo
        ]);

    } else {
        // -------------------------------------------------------
        // RECETA → PRODUCTO PRESENTACIÓN
        // 1. Obtener id_receta_producto vinculado al producto
        // 2. Eliminar todos los componentes_receta_producto de esa receta
        // 3. Limpiar Id_receta_producto = NULL en producto_presentacion
        // (La receta global en receta_producto_global se deja intacta para
        //  poder recuperarla si el usuario vuelve a activar; se limpiará
        //  definitivamente al guardar sin receta)
        // -------------------------------------------------------
        $idReceta = $producto['Id_receta_producto'];

        // Buscar también por id_presentacion_producto como respaldo
        if (!$idReceta) {
            $stmtBuscar = $conn->prepare(
                "SELECT id FROM receta_producto_global WHERE id_presentacion_producto = :id LIMIT 1"
            );
            $stmtBuscar->execute([':id' => $id]);
            $idReceta = $stmtBuscar->fetchColumn();
        }

        $componentesEliminados = 0;

        if ($idReceta) {
            // Eliminar componentes vinculados a esa receta
            $stmtDelComp = $conn->prepare(
                "DELETE FROM componentes_receta_producto WHERE id_receta_producto_global = :id_receta"
            );
            $stmtDelComp->execute([':id_receta' => $idReceta]);
            $componentesEliminados = $stmtDelComp->rowCount();
        }

        // Limpiar el vínculo de receta en el producto
        $stmtLimpiar = $conn->prepare(
            "UPDATE producto_presentacion SET
                Id_receta_producto = NULL,
                fecha_modificacion = NOW()
             WHERE id = :id"
        );
        $stmtLimpiar->execute([':id' => $id]);

        $conn->commit();
        echo json_encode([
            'success'                => true,
            'message'                => 'Receta y componentes eliminados del producto',
            'modo'                   => $modo,
            'id_receta_limpiada'     => $idReceta,
            'componentes_eliminados' => $componentesEliminados
        ]);
    }

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
