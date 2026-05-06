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
        // Los campos de "producto presentación" no aplican cuando el modo es receta.
        // Se ponen en NULL explícito: la semántica correcta es "no tiene unidad/maestro/cantidad".
        // -------------------------------------------------------
        $sql = "UPDATE producto_presentacion SET
                    cantidad             = NULL,
                    id_producto_maestro  = NULL,
                    id_unidad_producto   = NULL,
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
        // Cascada de eliminación:
        //   1. Obtener id de la receta vinculada
        //   2. DELETE componentes_receta_producto  (hijos)
        //   3. DELETE receta_producto_global       (padre)
        //   4. UPDATE producto_presentacion        (limpiar FK)
        // -------------------------------------------------------
        $idReceta = $producto['Id_receta_producto'];

        // Respaldo: buscar por id_presentacion_producto si el FK no está puesto
        if (!$idReceta) {
            $stmtBuscar = $conn->prepare(
                "SELECT id FROM receta_producto_global WHERE id_presentacion_producto = :id LIMIT 1"
            );
            $stmtBuscar->execute([':id' => $id]);
            $idReceta = $stmtBuscar->fetchColumn();
        }

        $componentesEliminados = 0;

        if ($idReceta) {
            // 1. Eliminar componentes (hijos)
            $stmtDelComp = $conn->prepare(
                "DELETE FROM componentes_receta_producto WHERE id_receta_producto_global = :id_receta"
            );
            $stmtDelComp->execute([':id_receta' => $idReceta]);
            $componentesEliminados = $stmtDelComp->rowCount();

            // 2. Eliminar la receta global (padre)
            $stmtDelReceta = $conn->prepare(
                "DELETE FROM receta_producto_global WHERE id = :id_receta"
            );
            $stmtDelReceta->execute([':id_receta' => $idReceta]);
        }

        // 3. Limpiar FK en el producto presentación
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
            'id_receta_eliminada'    => $idReceta,
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
