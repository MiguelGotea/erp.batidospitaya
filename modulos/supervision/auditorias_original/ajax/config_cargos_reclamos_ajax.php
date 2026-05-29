<?php
// ajax/config_cargos_reclamos_ajax.php
// Endpoint AJAX para gestión de reclamos_cargos_responsables
require_once '../../../../core/database/conexion.php';
require_once '../../../../core/auth/auth.php';
require_once '../../../../core/permissions/permissions.php';

header('Content-Type: application/json');

try {
    $usuario = obtenerUsuarioActual();
    $cargoOperario = $usuario['CodNivelesCargos'];
    $esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // --- Acción: obtener tipos filtrados por grupo_id ---
    if ($action === 'get_tipos') {
        // Solo necesita 'vista' para cargar el select
        if (!$esAdmin && !tienePermiso('config_cargos_reclamos', 'vista', $cargoOperario)) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit;
        }
        $grupo_id = isset($_GET['grupo_id']) ? intval($_GET['grupo_id']) : null;
        if ($grupo_id) {
            $stmt = $conn->prepare("SELECT id, nombre FROM reclamos_tipos WHERE grupo_id = :gid ORDER BY nombre");
            $stmt->execute([':gid' => $grupo_id]);
        } else {
            $stmt = $conn->prepare("SELECT id, nombre FROM reclamos_tipos ORDER BY nombre");
            $stmt->execute();
        }
        echo json_encode(['success' => true, 'tipos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- Acción: eliminar un registro ---
    if ($action === 'eliminar') {
        if (!$esAdmin && !tienePermiso('config_cargos_reclamos', 'eliminar', $cargoOperario)) {
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para eliminar registros.']);
            exit;
        }
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM reclamos_cargos_responsables WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
