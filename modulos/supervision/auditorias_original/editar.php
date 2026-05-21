<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([11, 16, 49]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Establecer la zona horaria de Managua, Nicaragua
date_default_timezone_set('America/Managua');

// Obtener el ID del registro a editar
$id = $_GET['id'];

// Obtener los datos del registro desde la base de datos
$sql = "SELECT * FROM auditoria WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $id);
$stmt->execute();
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no se encuentra el registro, redirigir a la página principal
if (!$registro) {
    header("Location: index.php");
    exit();
}

// Guardar los cambios cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fecha = $_POST['fecha'];
    $sucursal = $_POST['sucursal'];
    $persona = $_POST['persona'];

    // Limpieza en Exterior
    $limpieza_exterior_1_1_1 = $_POST['limpieza_exterior_1_1_1'];
    $limpieza_exterior_1_1_2 = $_POST['limpieza_exterior_1_1_2'];
    // Añadir los demás campos de limpieza en exterior e interior (igual que en agregar.php)

    // Actualizar los datos en la base de datos
    $sql = "UPDATE auditoria SET 
                fecha = :fecha,
                sucursal = :sucursal,
                persona = :persona,
                limpieza_exterior_1_1_1 = :limpieza_exterior_1_1_1,
                limpieza_exterior_1_1_2 = :limpieza_exterior_1_1_2
                -- Añadir el resto de campos aquí
            WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':fecha', $fecha);
    $stmt->bindParam(':sucursal', $sucursal);
    $stmt->bindParam(':persona', $persona);
    $stmt->bindParam(':limpieza_exterior_1_1_1', $limpieza_exterior_1_1_1);
    $stmt->bindParam(':limpieza_exterior_1_1_2', $limpieza_exterior_1_1_2);
    // Bind para el resto de campos...

    $stmt->bindParam(':id', $id);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error al actualizar los datos.";
    }
}
?>

<style>
    *{
        font-family: 'Calibri', sans-serif;
        font-size: clamp(11px, 2vw, 16px) !important;
    }
</style>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Registro</title>
    <link rel="stylesheet" href="styles.css">
    
    <!-- Favicon -->
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
</head>
<body>
    <!-- Header con logo -->
    <header style="margin-top:20px;">
        <a href="index.php">
            <img src="/core/assets/img/Logo.svg" alt="Logo de la empresa" class="logo"  style="max-width:100px;">
        </a>
    </header>
    
    <div style="padding:10px; margin:10px;">
        <h1>Editar Registro de Auditoría</h1>
        <form action="editar.php?id=<?php echo $id; ?>" method="POST">
            <label for="fecha">Fecha:</label>
            <input type="date" name="fecha" value="<?php echo $registro['fecha']; ?>" required readonly=""><br><br>
    
            <label for="sucursal">Sucursal:</label>
            <input type="text" name="sucursal" value="<?php echo $registro['sucursal']; ?>" required><br><br>
    
            <label for="persona">Persona (Acompañante):</label>
            <input type="text" name="persona" value="<?php echo $registro['persona']; ?>" required><br><br>
    
            <!-- Grupo de Limpieza -->
            <strong><h2>1. Limpieza</h2></strong>
            <!-- Limpieza en Exterior -->
            <h3>1.1. Limpieza en Exterior</h3>
            
            <label for="limpieza_exterior_1_1_1">1.1.1. Acera y cunetas correctamente barridas.</label>
            <select name="limpieza_exterior_1_1_1">
                <option value="1" <?php echo $registro['limpieza_exterior_1_1_1'] == 1 ? 'selected' : ''; ?>>1 - Deficiente</option>
                <option value="2" <?php echo $registro['limpieza_exterior_1_1_1'] == 2 ? 'selected' : ''; ?>>2 - Mala</option>
                <option value="3" <?php echo $registro['limpieza_exterior_1_1_1'] == 3 ? 'selected' : ''; ?>>3 - Regular</option>
                <option value="4" <?php echo $registro['limpieza_exterior_1_1_1'] == 4 ? 'selected' : ''; ?>>4 - Bueno</option>
                <option value="5" <?php echo $registro['limpieza_exterior_1_1_1'] == 5 ? 'selected' : ''; ?>>5 - Excelente</option>
                <option value="N/A" <?php echo $registro['limpieza_exterior_1_1_1'] == 'N/A' ? 'selected' : ''; ?>>N/A</option>
            </select><br><br>
            
            <!-- Repetir para los demás campos de limpieza... -->
            <label for="limpieza_exterior_1_1_2">1.1.2. Hay basura en exteriores (vasos, cartón, papel, etc.)</label>
            <select name="limpieza_exterior_1_1_2">
                <option value="1" <?php echo $registro['limpieza_exterior_1_1_2'] == 1 ? 'selected' : ''; ?>>1 - Deficiente</option>
                <option value="2" <?php echo $registro['limpieza_exterior_1_1_2'] == 2 ? 'selected' : ''; ?>>2 - Mala</option>
                <option value="3" <?php echo $registro['limpieza_exterior_1_1_2'] == 3 ? 'selected' : ''; ?>>3 - Regular</option>
                <option value="4" <?php echo $registro['limpieza_exterior_1_1_2'] == 4 ? 'selected' : ''; ?>>4 - Bueno</option>
                <option value="5" <?php echo $registro['limpieza_exterior_1_1_2'] == 5 ? 'selected' : ''; ?>>5 - Excelente</option>
                <option value="N/A" <?php echo $registro['limpieza_exterior_1_1_2'] == 'N/A' ? 'selected' : ''; ?>>N/A</option>
            </select><br><br>
    
            <button type="submit">Guardar cambios</button>
            <a href="index.php">Cancelar</a>
        </form>
    </div>
</body>
</html>
