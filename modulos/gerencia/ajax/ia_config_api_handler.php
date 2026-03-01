<?php
/**
 * modulos/gerencia/ajax/ia_config_api_handler.php
 * Controlador para la gestión de proveedores de IA
 */

require_once '../../../core/database/conexion.php';
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

// Validar sesión y permisos
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;

if (!tienePermiso('configuracion_ia_provedores', 'vista', $cargoOperario)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

$mensaje = '';
$tipoMensaje = 'success';

// Procesar Petición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = $_POST['id'] ?? null;
        $proveedor = $_POST['proveedor'];
        $apiKey = $_POST['api_key'];
        $password = $_POST['password'] ?? null;
        $activa = isset($_POST['activa']) ? 1 : 0;

        try {
            if ($id) {
                $stmt = $conn->prepare("UPDATE ia_proveedores_api SET proveedor = ?, api_key = ?, password = ?, activa = ? WHERE id = ?");
                $stmt->execute([$proveedor, $apiKey, $password, $activa, $id]);
                $mensaje = "Proveedor actualizado correctamente";
            } else {
                $stmt = $conn->prepare("INSERT INTO ia_proveedores_api (proveedor, api_key, password, activa) VALUES (?, ?, ?, ?)");
                $stmt->execute([$proveedor, $apiKey, $password, $activa]);
                $mensaje = "Nuevo proveedor registrado correctamente";
            }

            // Redirigir de vuelta con mensaje (manteniendo compatibilidad con el flujo actual)
            header("Location: ../ia_config_api.php?status=success&msg=" . urlencode($mensaje));
            exit();

        } catch (Exception $e) {
            header("Location: ../ia_config_api.php?status=error&msg=" . urlencode($e->getMessage()));
            exit();
        }
    } elseif ($accion === 'eliminar') {
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM ia_proveedores_api WHERE id = ?");
            $stmt->execute([$id]);
            $mensaje = "Proveedor eliminado";
            header("Location: ../ia_config_api.php?status=success&msg=" . urlencode($mensaje));
            exit();
        } catch (Exception $e) {
            header("Location: ../ia_config_api.php?status=error&msg=" . urlencode($e->getMessage()));
            exit();
        }
    }
}

// Si se accede directamente sin POST
header('Location: ../ia_config_api.php');
exit();
