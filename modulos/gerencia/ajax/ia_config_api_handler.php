<?php
/**
 * modulos/gerencia/ajax/ia_config_api_handler.php
 * Controlador para la gestión de proveedores de IA con soporte de Prueba de Conexión
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

// Procesar Petición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // --- ACCIÓN: GUARDAR / ACTUALIZAR ---
    if ($accion === 'guardar') {
        $id = $_POST['id'] ?? null;
        $proveedor = $_POST['proveedor'];
        $cuentaCorreo = $_POST['cuenta_correo'] ?? null;
        $apiKey = $_POST['api_key'];
        $password = $_POST['password'] ?? null;
        $activa = isset($_POST['activa']) ? 1 : 0;

        try {
            if ($id) {
                $stmt = $conn->prepare("UPDATE ia_proveedores_api SET proveedor = ?, cuenta_correo = ?, api_key = ?, password = ?, activa = ? WHERE id = ?");
                $stmt->execute([$proveedor, $cuentaCorreo, $apiKey, $password, $activa, $id]);
                $mensaje = "Proveedor actualizado correctamente";
            } else {
                $stmt = $conn->prepare("INSERT INTO ia_proveedores_api (proveedor, cuenta_correo, api_key, password, activa) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$proveedor, $cuentaCorreo, $apiKey, $password, $activa]);
                $mensaje = "Nuevo proveedor registrado correctamente";
            }
            header("Location: ../ia_config_api.php?status=success&msg=" . urlencode($mensaje));
            exit();
        } catch (Exception $e) {
            header("Location: ../ia_config_api.php?status=error&msg=" . urlencode($e->getMessage()));
            exit();
        }
    }

    // --- ACCIÓN: ELIMINAR ---
    elseif ($accion === 'eliminar') {
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM ia_proveedores_api WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: ../ia_config_api.php?status=success&msg=" . urlencode("Proveedor eliminado"));
            exit();
        } catch (Exception $e) {
            header("Location: ../ia_config_api.php?status=error&msg=" . urlencode($e->getMessage()));
            exit();
        }
    }

    // --- ACCIÓN: TEST (PING) ---
    elseif ($accion === 'test') {
        header('Content-Type: application/json');
        $id = $_POST['id'];

        try {
            $stmt = $conn->prepare("SELECT * FROM ia_proveedores_api WHERE id = ?");
            $stmt->execute([$id]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$p) {
                echo json_encode(['success' => false, 'message' => 'Proveedor no encontrado']);
                exit();
            }

            $success = false;
            $msg = 'Proveedor no soportado para test directo';

            // Simular o ejecutar pings reales ligeros
            switch ($p['proveedor']) {
                case 'google':
                    // Test para Gemini API
                    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $p['api_key'];
                    $res = @file_get_contents($url);
                    if ($res !== false) {
                        $success = true;
                        $msg = 'Conexión exitosa con Google Gemini';
                    } else {
                        $msg = 'Error de validación: Key inválida o sin permisos';
                    }
                    break;

                case 'openai':
                    // Test para OpenAI (Lista de modelos)
                    $ch = curl_init('https://api.openai.com/v1/models');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $p['api_key']
                    ]);
                    $res = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode === 200) {
                        $success = true;
                        $msg = 'Conexión exitosa con OpenAI';
                    } else {
                        $msg = "Error OpenAI (Code $httpCode)";
                    }
                    break;

                default:
                    // Para otros, validamos formato básico por ahora
                    if (strlen($p['api_key']) > 10) {
                        $success = true;
                        $msg = 'Key con formato válido (Ping parcial)';
                    } else {
                        $msg = 'Key demasiado corta o inválida';
                    }
                    break;
            }

            echo json_encode(['success' => $success, 'message' => $msg]);
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit();
        }
    }
}

header('Location: ../ia_config_api.php');
exit();
