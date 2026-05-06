<?php
session_start();

// Verificar si el usuario ya está autenticado
if (isset($_SESSION['autenticado'])) {
    // Redirigir según el tipo de usuario
    if ($_SESSION['tipo_usuario'] === 'reclamos') {
        header("Location: nuevoreclamo.php");
    } elseif ($_SESSION['tipo_usuario'] === 'auditor') {
        header("Location: index.php");
    } elseif ($_SESSION['tipo_usuario'] === 'avisos') {
        header("Location: index_avisos.php");
    } elseif ($_SESSION['tipo_usuario'] === 'interno') {
        header("Location: index_avisos.php");
    }
    exit();
}

// Verificar si se envió el formulario de inicio de sesión
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password_ingresada = $_POST['password'];

    // Verificar la contraseña y redirigir según corresponda
    if ($password_ingresada === "reclamos123$") {
        $_SESSION['autenticado'] = true;
        $_SESSION['tipo_usuario'] = 'reclamos';
        header("Location: nuevoreclamo.php");
        exit();
    } elseif ($password_ingresada === "auditor123$") {
        $_SESSION['autenticado'] = true;
        $_SESSION['tipo_usuario'] = 'auditor';
        header("Location: index.php");
        exit();
    } elseif ($password_ingresada === "controlreclamos123$") {
        $_SESSION['autenticado'] = true;
        $_SESSION['tipo_usuario'] = 'auditor';
        header("Location: reclamospend.php");
        exit();
    } elseif ($password_ingresada === "aviso123$") {
        $_SESSION['autenticado'] = true;
        $_SESSION['tipo_usuario'] = 'avisos';
        header("Location: index_avisos.php");
        exit();
    } elseif ($password_ingresada === "interno123$") {
        $_SESSION['autenticado'] = true;
        $_SESSION['tipo_usuario'] = 'interno';
        header("Location: auditinternas/auditorias_consolidadas.php");
        exit();
    } else {
        $error = "Contraseña incorrecta. Inténtalo de nuevo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="icon12.png" type="image/png">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            font-size: clamp(11px, 2vw, 16px) !important;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Calibri', sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #F6F6F6;
            padding: 20px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            max-width: 150px;
            height: auto;
        }
        
        .login-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
            max-width: 400px;
        }
        
        .login-container h2 {
            margin-bottom: 20px;
            color: #51B8AC;
        }
        
        .login-container input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .login-container button {
            padding: 12px 20px;
            background-color: #51B8AC;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .login-container button:hover {
            background-color: #0E544C;
        }
        
        .error {
            color: red;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #ffeeee;
            border-radius: 4px;
        }
        
        .btn-volver {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            padding: 10px 15px;
            background-color: #0E544C;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .btn-volver:hover {
            background-color: #08332d;
        }
        
        @media (max-width: 480px) {
            .logo {
                max-width: 120px;
            }
            
            .login-container {
                padding: 20px;
            }
            
            .login-container input[type="password"] {
                padding: 10px;
            }
            
            .login-container button {
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="logo-container">
        <a href="index_auditorias_publico.php">
            <img src="Logo.svg" alt="Logo de la empresa" class="logo">
        </a>
    </div>
    
    <div class="login-container">
        <h2>Autenticar</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form action="autenticar_login.php" method="POST">
            <input type="password" name="password" placeholder="Ingresa la contraseña" required>
            <button type="submit">
                <i class="fas fa-sign-in-alt"></i> Acceder
            </button>
        </form>
        <a href="index_auditorias_publico.php" class="btn-volver" target="_blank">
            <i class="fas fa-user"></i> Vista Pública
        </a>
    </div>
</body>
</html>
