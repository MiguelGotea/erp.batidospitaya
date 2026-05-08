<?php
// marcaciones_test.php
require_once '../../core/auth/auth.php';

//verificarAccesoModulo('sucursales');

$usuarioActual = obtenerUsuarioActual();
$sucursalUsuario = $usuarioActual['sucursal_codigo'] ?? null;

if (!$sucursalUsuario) {
    die("No tienes una sucursal asignada o no estás autenticado.");
}

$mensaje = '';
$fotoUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_dvr'])) {
    $resultado = capturarFotoDVR($sucursalUsuario);
    if ($resultado['success']) {
        $mensaje = "Foto capturada con éxito!";
        $fotoUrl = $resultado['path'];
    } else {
        $mensaje = "Error: " . $resultado['message'];
    }
}

function capturarFotoDVR($codSucursal)
{
    global $conn;

    // Obtener datos del DVR
    $stmt = $conn->prepare("SELECT * FROM DVR_Sucursales WHERE cod_sucursal = ?");
    $stmt->execute([$codSucursal]);
    $dvr = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dvr) {
        return ['success' => false, 'message' => 'No hay configuración de DVR para la sucursal ' . $codSucursal];
    }

    $ip = $dvr['portal_ip_local'];
    $usuario = $dvr['portal_usuario'];
    $clave = $dvr['portal_clave'];
    $canal = !empty($dvr['canal_caja']) ? $dvr['canal_caja'] : 301;

    if (!$ip || !$usuario || !$clave) {
        return ['success' => false, 'message' => 'Configuración de DVR incompleta (IP, usuario o clave faltante).'];
    }

    // URL formato Hikvision ISAPI
    $url = "http://{$ip}/ISAPI/Streaming/channels/{$canal}/picture";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // Hikvision soporta Digest o Basic Auth
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$usuario:$clave");
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode == 200 && $result) {
        // Directorio de subida
        $rootDir = realpath(__DIR__ . '/../../');
        $uploadDir = $rootDir . '/uploads/sucursales';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = 'dvr_' . $codSucursal . '_' . date('Ymd_His') . '.jpg';
        $filepath = $uploadDir . '/' . $filename;

        file_put_contents($filepath, $result);

        return ['success' => true, 'path' => '/uploads/sucursales/' . $filename];
    } else {
        // Ocultar credenciales en el mensaje de error por seguridad
        return ['success' => false, 'message' => "HTTP Code: $httpCode. Error: $error"];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test DVR Marcaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Calibri', Arial, sans-serif;
            padding: 20px;
            background: #F6F6F6;
            color: #333;
        }

        .container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h2 {
            color: #0E544C;
        }

        .btn {
            padding: 15px 20px;
            background: #51B8AC;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            width: 100%;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #0E544C;
        }

        .msg {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            text-align: left;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        img {
            max-width: 100%;
            margin-top: 20px;
            border: 2px solid #51B8AC;
            border-radius: 8px;
        }

        .info {
            margin-top: 20px;
            font-size: 14px;
            color: #666;
            text-align: left;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2><i class="fas fa-camera"></i> Test Captura DVR</h2>
        <p>Sucursal Actual: <strong><?= htmlspecialchars($sucursalUsuario) ?></strong></p>

        <form method="POST">
            <button type="submit" name="test_dvr" class="btn">
                <i class="fas fa-video"></i> Capturar Foto del DVR
            </button>
        </form>

        <?php if ($mensaje): ?>
            <div class="msg <?= $fotoUrl ? 'success' : 'error' ?>">
                <i class="fas <?= $fotoUrl ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($fotoUrl): ?>
            <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto DVR">
            <div class="info">
                <strong>Ruta guardada:</strong> <code><?= htmlspecialchars($fotoUrl) ?></code>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>