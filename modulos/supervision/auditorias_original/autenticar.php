<?php
session_start();

// Contraseña válida (cámbiala por una contraseña segura)
$password_valida = "alegria123";

// Verificar si el usuario ya está autenticado
if (isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true) {
    header("Location: agregarservicio.php");
    exit();
}

// Verificar si se envió el formulario de inicio de sesión
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password_ingresada = $_POST['password'];

    // Verificar si la contraseña es correcta
    if ($password_ingresada === $password_valida) {
        $_SESSION['autenticado'] = true;
        header("Location: agregarservicio.php");
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
    <title>Iniciar Sesión</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f4f4f4;
        }
        .login-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .login-container h2 {
            margin-bottom: 20px;
        }
        .login-container input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .login-container button {
            padding: 10px 20px;
            background-color: #51B8AC;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .login-container button:hover {
            background-color: #3a9c8f;
        }
        .error {
            color: red;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Autenticar Auditor</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form action="autenticar.php" method="POST">
            <div style="margin-right:10px;">
                <input type="password" name="password" placeholder="Ingresa la contraseña" required>
            </div>
            <button type="submit">Acceder</button><br><br>
            <div style="background:#0E544C; color:white;">
                <a style="color:white;" href="https://auditorias.batidospitaya.com/" class="btn-agregar">Volver a Inicio</a>
            </div>
        </form>
    </div>
</body>
</html>
