<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/helpers/funciones.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';

// Solo admin o personal de sistemas
// verificarAccesoModulo('sistemas'); // Descomentar si se integra oficialmente en el menú

$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['autorizar'])) {
    $codSucursal = $_POST['sucursal_codigo'];

    if (empty($codSucursal)) {
        $mensaje = "Debe seleccionar una sucursal.";
        $tipo_mensaje = "error";
    } else {
        // Generar token único
        $token = bin2hex(random_bytes(32));

        try {
            // 1. Guardar en base de datos
            $stmt = $conn->prepare("UPDATE sucursales SET cookie_token = ? WHERE codigo = ?");
            $stmt->execute([$token, $codSucursal]);

            // 2. Establecer Cookie persistente (10 años)
            // HttpOnly = true para seguridad, Secure = true si es HTTPS
            $expiracion = time() + (10 * 365 * 24 * 60 * 60);
            $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');

            setcookie('erp_device_token', $token, [
                'expires' => $expiracion,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            $mensaje = "¡Dispositivo autorizado con éxito para la sucursal " . $codSucursal . "! La cookie ha sido establecida.";
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $mensaje = "Error al autorizar: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

$sucursales = obtenerTodasSucursales();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Autorizar Dispositivo - ERP Pitaya</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            background: #f4f4f4;
        }

        .container {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #0E544C;
            font-size: 20px;
        }

        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        select,
        button {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        button {
            background: #51B8AC;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            background: #0E544C;
        }

        .info {
            font-size: 12px;
            color: #666;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Autorizar este Dispositivo</h1>

        <?php if ($mensaje): ?>
            <div class="alert <?= $tipo_mensaje ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label for="sucursal_codigo">Seleccionar Sucursal:</label>
            <select name="sucursal_codigo" id="sucursal_codigo" required>
                <option value="">-- Seleccione Sucursal --</option>
                <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['codigo'] ?>">
                        <?= htmlspecialchars($s['nombre']) ?> (
                        <?= $s['codigo'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="autorizar">Autorizar esta PC</button>
        </form>

        <div class="info">
            <p><strong>Nota:</strong> Al presionar el botón, se guardará un token secreto en este navegador que
                permitirá el acceso a marcaciones para la sucursal seleccionada. No borre las cookies del sitio después
                de realizar este proceso.</p>
        </div>
    </div>
</body>

</html>