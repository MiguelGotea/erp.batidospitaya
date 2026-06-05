<?php
// /public_html/modulos/index.php

require_once '../core/auth/auth.php';

// Verificar que el usuario esté autenticado

// Registro de permiso de vista para el sistema de "tools" si no existe (opcional si se hace manual)
// Esto asegura que al entrar al index del módulo se registre en el sistema de permisos de herramientas

// Obtener todos los cargos del usuario de la sesión (no solo uno)
$cargosUsuario = obtenerCargosUsuario($_SESSION['usuario_id']);

// Obtener el módulo destino (desde la sesión si ya está cacheado, o desde la BD)
if (empty($cargosUsuario)) {
    header("Location: /index.php");
    exit();
}

$moduloDestino = '';

if (isset($_SESSION['modulo_ruta']) && !empty($_SESSION['modulo_ruta'])) {
    $moduloDestino = $_SESSION['modulo_ruta'];
} else {
    global $conn;
    $placeholders = implode(',', array_fill(0, count($cargosUsuario), '?'));

    $stmt = $conn->prepare("
        SELECT nc.CodNivelesCargos, nc.Nombre, nc.modulo_ruta
        FROM NivelesCargos nc
        WHERE nc.CodNivelesCargos IN ($placeholders)
          AND nc.modulo_ruta IS NOT NULL
        ORDER BY
            CASE WHEN nc.CodNivelesCargos = 2 THEN 1 ELSE 0 END ASC,
            nc.Peso DESC
        LIMIT 1
    ");
    $stmt->execute($cargosUsuario);
    $destinoCargo = $stmt->fetch();

    if ($destinoCargo) {
        $moduloDestino = $destinoCargo['modulo_ruta'];
        $_SESSION['modulo_ruta'] = $moduloDestino;
    }
}

$loopDetected = false;
$moduloRechazado = '';

if (!empty($moduloDestino)) {
    // Si acabamos de intentar redirigir a este módulo hace menos de 3 segundos y volvimos aquí, es un bucle
    if (isset($_SESSION['last_redirect_module']) && 
        $_SESSION['last_redirect_module'] === $moduloDestino && 
        isset($_SESSION['last_redirect_time']) && 
        (time() - $_SESSION['last_redirect_time']) < 3) {
        
        $loopDetected = true;
        $moduloRechazado = $moduloDestino;
        
        // Limpiamos para evitar quedar atrapados si el usuario refresca manualmente
        unset($_SESSION['last_redirect_module']);
        unset($_SESSION['last_redirect_time']);
    } else {
        if (file_exists("../modulos/{$moduloDestino}/index.php")) {
            // Registrar el intento de redirección solo si realmente vamos a redirigir
            $_SESSION['last_redirect_module'] = $moduloDestino;
            $_SESSION['last_redirect_time'] = time();
            
            header("Location: /modulos/{$moduloDestino}/index.php");
            exit();
        }
    }
}

// Cargo del usuario no tiene un módulo configurado en el ERP
$cargoNombre = $_SESSION['cargo_nombre'] ?? 'desconocido';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso no configurado - Batidos Pitaya</title>
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Calibri', sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #F6F6F6;
            padding: 20px;
            gap: 24px;
        }
        img.logo { max-width: 140px; height: auto; }
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            padding: 36px 32px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h2 { color: #333; margin-bottom: 10px; font-size: 1.3rem; }
        p { color: #666; line-height: 1.6; margin-bottom: 8px; }
        .cargo-badge {
            display: inline-block;
            background: #f0f0f0;
            color: #444;
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 0.9rem;
            margin: 10px 0 20px;
        }
        a.btn-logout {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 24px;
            background-color: #51B8AC;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.95rem;
            transition: background-color 0.2s;
        }
        a.btn-logout:hover { background-color: #0E544C; }
    </style>
</head>
<body>
    <img src="/core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
    <div class="card">
        <?php if ($loopDetected): ?>
            <div class="icon">🔒</div>
            <h2>Acceso restringido al módulo</h2>
            <p>Tu cargo está asignado al módulo <strong><?php echo htmlspecialchars(ucfirst($moduloRechazado)); ?></strong>, pero tu usuario no cuenta con los permisos de acceso requeridos en el sistema.</p>
            <span class="cargo-badge"><?php echo htmlspecialchars($cargoNombre); ?></span>
            <p>Contacta al equipo de <strong>TI / Sistemas</strong> para que te habiliten los permisos correspondientes en la gestión de accesos.</p>
        <?php else: ?>
            <div class="icon">⚙️</div>
            <h2>Acceso pendiente de configuración</h2>
            <p>Tu cuenta fue verificada correctamente, pero tu cargo aún no tiene un módulo asignado en el ERP o la ruta no existe.</p>
            <span class="cargo-badge"><?php echo htmlspecialchars($cargoNombre); ?></span>
            <p>Contacta al equipo de <strong>TI / Sistemas</strong> para que configuren tu acceso.</p>
        <?php endif; ?>
        <a href="/logout.php" class="btn-logout">Cerrar sesión</a>
    </div>
</body>
</html>

