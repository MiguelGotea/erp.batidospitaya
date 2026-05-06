<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once 'conexion.php';

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Establecer la zona horaria de Managua, Nicaragua
date_default_timezone_set('America/Managua');

// Guardar los datos cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtener datos del formulario
    $fecha = $_POST['fecha'];
    $sucursal = $_POST['sucursal'];
    $persona = $_POST['persona'];
    $comentarios = isset($_POST['comentarios']) ? $_POST['comentarios'] : null; // Nuevo campo comentarios
    
    $cod_sucursal = $_POST['cod_sucursal']; // Nuevo campo para el código de sucursal
    $operario_id = isset($_POST['operario_id']) && !empty($_POST['operario_id']) ? (int)$_POST['operario_id'] : null;

    // Limpieza en Exterior
    $limpieza_exterior_1_1_1 = $_POST['limpieza_exterior_1_1_1'];
    $limpieza_exterior_1_1_2 = $_POST['limpieza_exterior_1_1_2'];
    $limpieza_exterior_1_1_3 = $_POST['limpieza_exterior_1_1_3'];
    $limpieza_exterior_1_1_4 = $_POST['limpieza_exterior_1_1_4'];
    $limpieza_exterior_1_1_5 = $_POST['limpieza_exterior_1_1_5'];
    $limpieza_exterior_1_1_6 = $_POST['limpieza_exterior_1_1_6'];
    $limpieza_exterior_1_1_7 = $_POST['limpieza_exterior_1_1_7'];
    $limpieza_exterior_1_1_8 = $_POST['limpieza_exterior_1_1_8'];
    $limpieza_exterior_1_1_9 = $_POST['limpieza_exterior_1_1_9'];
    $limpieza_exterior_1_1_10 = $_POST['limpieza_exterior_1_1_10'];
    $limpieza_exterior_1_1_11 = $_POST['limpieza_exterior_1_1_11'];
    $limpieza_exterior_1_1_12 = $_POST['limpieza_exterior_1_1_12'];
    $limpieza_exterior_1_1_13 = $_POST['limpieza_exterior_1_1_13'];

    // Limpieza de Interiores
    $limpieza_interior_1_2_1 = $_POST['limpieza_interior_1_2_1'];
    $limpieza_interior_1_2_2 = $_POST['limpieza_interior_1_2_2'];
    $limpieza_interior_1_2_3 = $_POST['limpieza_interior_1_2_3'];
    $limpieza_interior_1_2_4 = $_POST['limpieza_interior_1_2_4'];
    $limpieza_interior_1_2_5 = $_POST['limpieza_interior_1_2_5'];
    $limpieza_interior_1_2_6 = $_POST['limpieza_interior_1_2_6'];
    $limpieza_interior_1_2_7 = $_POST['limpieza_interior_1_2_7'];
    $limpieza_interior_1_2_8 = $_POST['limpieza_interior_1_2_8'];
    $limpieza_interior_1_2_9 = $_POST['limpieza_interior_1_2_9'];
    $limpieza_interior_1_2_10 = $_POST['limpieza_interior_1_2_10'];
    $limpieza_interior_1_2_11 = $_POST['limpieza_interior_1_2_11'];
    $limpieza_interior_1_2_12 = $_POST['limpieza_interior_1_2_12'];
    $limpieza_interior_1_2_13 = $_POST['limpieza_interior_1_2_13'];
    $limpieza_interior_1_2_14 = $_POST['limpieza_interior_1_2_14'];
    
    // Limpieza de Equipos y Utensilios
    $limpieza_equipo_1_3_1 = $_POST['limpieza_equipo_1_3_1'];
    $limpieza_equipo_1_3_2 = $_POST['limpieza_equipo_1_3_2'];
    $limpieza_equipo_1_3_3 = $_POST['limpieza_equipo_1_3_3'];
    $limpieza_equipo_1_3_4 = $_POST['limpieza_equipo_1_3_4'];
    $limpieza_equipo_1_3_5 = $_POST['limpieza_equipo_1_3_5'];
    $limpieza_equipo_1_3_6 = $_POST['limpieza_equipo_1_3_6'];
    $limpieza_equipo_1_3_7 = $_POST['limpieza_equipo_1_3_7'];
    $limpieza_equipo_1_3_8 = $_POST['limpieza_equipo_1_3_8'];
    $limpieza_equipo_1_3_9 = $_POST['limpieza_equipo_1_3_9'];
    $limpieza_equipo_1_3_10 = $_POST['limpieza_equipo_1_3_10'];
    $limpieza_equipo_1_3_11 = $_POST['limpieza_equipo_1_3_11'];
    $limpieza_equipo_1_3_12 = $_POST['limpieza_equipo_1_3_12'];
    $limpieza_equipo_1_3_13 = $_POST['limpieza_equipo_1_3_13'];
    
    // Manejo de Insumos
    $limpieza_insumos_1_4_1 = $_POST['limpieza_insumos_1_4_1'];
    $limpieza_insumos_1_4_2 = $_POST['limpieza_insumos_1_4_2'];
    $limpieza_insumos_1_4_3 = $_POST['limpieza_insumos_1_4_3'];
    $limpieza_insumos_1_4_4 = $_POST['limpieza_insumos_1_4_4'];
    $limpieza_insumos_1_4_5 = $_POST['limpieza_insumos_1_4_5'];
    $limpieza_insumos_1_4_6 = $_POST['limpieza_insumos_1_4_6'];
    
    $promedio_exterior = $_POST['promedio_exterior'];
    $promedio_interior = $_POST['promedio_interior'];
    $promedio_equipo = $_POST['promedio_equipo'];
    $promedio_insumos = $_POST['promedio_insumos'];
    $promedio_general = $_POST['promedio_general'];

    // Insertar los datos en la base de datos (sin fotos)
    $sql = "INSERT INTO auditoria (
                fecha, fecha_hora, sucursal, cod_sucursal, persona, operario_id, comentarios,
                limpieza_exterior_1_1_1, limpieza_exterior_1_1_2, limpieza_exterior_1_1_3, limpieza_exterior_1_1_4, 
                limpieza_exterior_1_1_5, limpieza_exterior_1_1_6, limpieza_exterior_1_1_7, limpieza_exterior_1_1_8, 
                limpieza_exterior_1_1_9, limpieza_exterior_1_1_10, limpieza_exterior_1_1_11, limpieza_exterior_1_1_12, 
                limpieza_exterior_1_1_13,
                limpieza_interior_1_2_1, limpieza_interior_1_2_2, limpieza_interior_1_2_3, limpieza_interior_1_2_4, 
                limpieza_interior_1_2_5, limpieza_interior_1_2_6, limpieza_interior_1_2_7, limpieza_interior_1_2_8, 
                limpieza_interior_1_2_9, limpieza_interior_1_2_10, limpieza_interior_1_2_11, limpieza_interior_1_2_12, 
                limpieza_interior_1_2_13, limpieza_interior_1_2_14,
                limpieza_equipo_1_3_1, limpieza_equipo_1_3_2, limpieza_equipo_1_3_3, limpieza_equipo_1_3_4, 
                limpieza_equipo_1_3_5, limpieza_equipo_1_3_6, limpieza_equipo_1_3_7, limpieza_equipo_1_3_8, 
                limpieza_equipo_1_3_9, limpieza_equipo_1_3_10, limpieza_equipo_1_3_11, limpieza_equipo_1_3_12, 
                limpieza_equipo_1_3_13,
                limpieza_insumos_1_4_1, limpieza_insumos_1_4_2, limpieza_insumos_1_4_3, limpieza_insumos_1_4_4, limpieza_insumos_1_4_5, limpieza_insumos_1_4_6,
                promedio_exterior, promedio_interior, promedio_equipo, promedio_insumos, promedio_general
            ) VALUES (
                :fecha, CONVERT_TZ(NOW(), '+00:00', '-06:00'), :sucursal, :cod_sucursal, :persona, :operario_id, :comentarios,
                :limpieza_exterior_1_1_1, :limpieza_exterior_1_1_2, :limpieza_exterior_1_1_3, :limpieza_exterior_1_1_4, 
                :limpieza_exterior_1_1_5, :limpieza_exterior_1_1_6, :limpieza_exterior_1_1_7, :limpieza_exterior_1_1_8, 
                :limpieza_exterior_1_1_9, :limpieza_exterior_1_1_10, :limpieza_exterior_1_1_11, :limpieza_exterior_1_1_12, 
                :limpieza_exterior_1_1_13,
                :limpieza_interior_1_2_1, :limpieza_interior_1_2_2, :limpieza_interior_1_2_3, :limpieza_interior_1_2_4, 
                :limpieza_interior_1_2_5, :limpieza_interior_1_2_6, :limpieza_interior_1_2_7, :limpieza_interior_1_2_8, 
                :limpieza_interior_1_2_9, :limpieza_interior_1_2_10, :limpieza_interior_1_2_11, :limpieza_interior_1_2_12, 
                :limpieza_interior_1_2_13, :limpieza_interior_1_2_14,
                :limpieza_equipo_1_3_1, :limpieza_equipo_1_3_2, :limpieza_equipo_1_3_3, :limpieza_equipo_1_3_4, 
                :limpieza_equipo_1_3_5, :limpieza_equipo_1_3_6, :limpieza_equipo_1_3_7, :limpieza_equipo_1_3_8, 
                :limpieza_equipo_1_3_9, :limpieza_equipo_1_3_10, :limpieza_equipo_1_3_11, :limpieza_equipo_1_3_12, 
                :limpieza_equipo_1_3_13,
                :limpieza_insumos_1_4_1, :limpieza_insumos_1_4_2, :limpieza_insumos_1_4_3, :limpieza_insumos_1_4_4, :limpieza_insumos_1_4_5, :limpieza_insumos_1_4_6,
                :promedio_exterior, :promedio_interior, :promedio_equipo, :promedio_insumos, :promedio_general
            )";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':fecha', $fecha);
    $stmt->bindParam(':sucursal', $sucursal);
    $stmt->bindParam(':cod_sucursal', $cod_sucursal);
    $stmt->bindParam(':persona', $persona);
    $stmt->bindParam(':operario_id', $operario_id);
    $stmt->bindParam(':comentarios', $comentarios); // Nuevo bindParam para comentarios

    // Limpieza en Exterior
    $stmt->bindParam(':limpieza_exterior_1_1_1', $limpieza_exterior_1_1_1);
    $stmt->bindParam(':limpieza_exterior_1_1_2', $limpieza_exterior_1_1_2);
    $stmt->bindParam(':limpieza_exterior_1_1_3', $limpieza_exterior_1_1_3);
    $stmt->bindParam(':limpieza_exterior_1_1_4', $limpieza_exterior_1_1_4);
    $stmt->bindParam(':limpieza_exterior_1_1_5', $limpieza_exterior_1_1_5);
    $stmt->bindParam(':limpieza_exterior_1_1_6', $limpieza_exterior_1_1_6);
    $stmt->bindParam(':limpieza_exterior_1_1_7', $limpieza_exterior_1_1_7);
    $stmt->bindParam(':limpieza_exterior_1_1_8', $limpieza_exterior_1_1_8);
    $stmt->bindParam(':limpieza_exterior_1_1_9', $limpieza_exterior_1_1_9);
    $stmt->bindParam(':limpieza_exterior_1_1_10', $limpieza_exterior_1_1_10);
    $stmt->bindParam(':limpieza_exterior_1_1_11', $limpieza_exterior_1_1_11);
    $stmt->bindParam(':limpieza_exterior_1_1_12', $limpieza_exterior_1_1_12);
    $stmt->bindParam(':limpieza_exterior_1_1_13', $limpieza_exterior_1_1_13);

    // Limpieza de Interiores
    $stmt->bindParam(':limpieza_interior_1_2_1', $limpieza_interior_1_2_1);
    $stmt->bindParam(':limpieza_interior_1_2_2', $limpieza_interior_1_2_2);
    $stmt->bindParam(':limpieza_interior_1_2_3', $limpieza_interior_1_2_3);
    $stmt->bindParam(':limpieza_interior_1_2_4', $limpieza_interior_1_2_4);
    $stmt->bindParam(':limpieza_interior_1_2_5', $limpieza_interior_1_2_5);
    $stmt->bindParam(':limpieza_interior_1_2_6', $limpieza_interior_1_2_6);
    $stmt->bindParam(':limpieza_interior_1_2_7', $limpieza_interior_1_2_7);
    $stmt->bindParam(':limpieza_interior_1_2_8', $limpieza_interior_1_2_8);
    $stmt->bindParam(':limpieza_interior_1_2_9', $limpieza_interior_1_2_9);
    $stmt->bindParam(':limpieza_interior_1_2_10', $limpieza_interior_1_2_10);
    $stmt->bindParam(':limpieza_interior_1_2_11', $limpieza_interior_1_2_11);
    $stmt->bindParam(':limpieza_interior_1_2_12', $limpieza_interior_1_2_12);
    $stmt->bindParam(':limpieza_interior_1_2_13', $limpieza_interior_1_2_13);
    $stmt->bindParam(':limpieza_interior_1_2_14', $limpieza_interior_1_2_14);
    
    // Limpieza de Equipos
    $stmt->bindParam(':limpieza_equipo_1_3_1', $limpieza_equipo_1_3_1);
    $stmt->bindParam(':limpieza_equipo_1_3_2', $limpieza_equipo_1_3_2);
    $stmt->bindParam(':limpieza_equipo_1_3_3', $limpieza_equipo_1_3_3);
    $stmt->bindParam(':limpieza_equipo_1_3_4', $limpieza_equipo_1_3_4);
    $stmt->bindParam(':limpieza_equipo_1_3_5', $limpieza_equipo_1_3_5);
    $stmt->bindParam(':limpieza_equipo_1_3_6', $limpieza_equipo_1_3_6);
    $stmt->bindParam(':limpieza_equipo_1_3_7', $limpieza_equipo_1_3_7);
    $stmt->bindParam(':limpieza_equipo_1_3_8', $limpieza_equipo_1_3_8);
    $stmt->bindParam(':limpieza_equipo_1_3_9', $limpieza_equipo_1_3_9);
    $stmt->bindParam(':limpieza_equipo_1_3_10', $limpieza_equipo_1_3_10);
    $stmt->bindParam(':limpieza_equipo_1_3_11', $limpieza_equipo_1_3_11);
    $stmt->bindParam(':limpieza_equipo_1_3_12', $limpieza_equipo_1_3_12);
    $stmt->bindParam(':limpieza_equipo_1_3_13', $limpieza_equipo_1_3_13);
    
    // Manejo de Insumos
    $stmt->bindParam(':limpieza_insumos_1_4_1', $limpieza_insumos_1_4_1);
    $stmt->bindParam(':limpieza_insumos_1_4_2', $limpieza_insumos_1_4_2);
    $stmt->bindParam(':limpieza_insumos_1_4_3', $limpieza_insumos_1_4_3);
    $stmt->bindParam(':limpieza_insumos_1_4_4', $limpieza_insumos_1_4_4);
    $stmt->bindParam(':limpieza_insumos_1_4_5', $limpieza_insumos_1_4_5);
    $stmt->bindParam(':limpieza_insumos_1_4_6', $limpieza_insumos_1_4_6);
    
    $stmt->bindParam(':promedio_exterior', $promedio_exterior);
    $stmt->bindParam(':promedio_interior', $promedio_interior);
    $stmt->bindParam(':promedio_equipo', $promedio_equipo);
    $stmt->bindParam(':promedio_insumos', $promedio_insumos);
    $stmt->bindParam(':promedio_general', $promedio_general);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        $auditoria_id = $conn->lastInsertId();
        
        // Procesar las fotos si existen
        if (isset($_POST['fotos']) && !empty($_POST['fotos'])) {
            // Decodificar el JSON de fotos
            $fotos = json_decode($_POST['fotos'], true);
            
            if (is_array($fotos)) {
                foreach ($fotos as $fotoData) {
                    if (!empty($fotoData)) {
                        // Eliminar el prefijo "data:image/png;base64," de la cadena base64
                        $fotoData = str_replace('data:image/png;base64,', '', $fotoData);
                        $fotoData = str_replace(' ', '+', $fotoData);
                        
                        // Decodificar la cadena base64
                        $fotoDecodificada = base64_decode($fotoData);
                        
                        // Generar un nombre único para la imagen
                        $nombreArchivo = uniqid() . '.png';
                        $rutaArchivo = 'fotos/' . $nombreArchivo;
                        
                        // Verificar si la carpeta fotos existe, si no, crearla
                        if (!file_exists('fotos')) {
                            mkdir('fotos', 0777, true);
                        }
                        
                        // Guardar la imagen en la carpeta "fotos"
                        if (file_put_contents($rutaArchivo, $fotoDecodificada)) {
                            // Insertar la ruta de la foto en la base de datos
                            $sqlFoto = "INSERT INTO auditoria_fotos (auditoria_id, ruta_foto) VALUES (:auditoria_id, :ruta_foto)";
                            $stmtFoto = $conn->prepare($sqlFoto);
                            $stmtFoto->bindParam(':auditoria_id', $auditoria_id);
                            $stmtFoto->bindParam(':ruta_foto', $rutaArchivo);
                            $stmtFoto->execute();
                        }
                    }
                }
            }
        }
        
        // Redirigir a la página de inicio después de agregar el registro
        header("Location: logout.php");
        exit();
    } else {
        echo "Error al guardar los datos: " . implode(" - ", $stmt->errorInfo());
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría Evaluación de Limpieza</title>
    <link rel="stylesheet" href="styleagg.css">
    <!-- Favicon -->
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="stylesauditlimp.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
</head>
<body>
    <!-- Header con logo -->
    <header>
        <a href="logout.php">
            <img src="Logo.svg" alt="Logo de la empresa" class="logo" style="max-width:75px;">
        </a>
    </header>
    
    <a href="logout.php" style="display:none;">Cerrar Sesión</a>
    
    <form action="agregar.php" method="POST" onsubmit="return validarFormulario()">
        <?php
            // Crear un objeto DateTime a partir de la fecha actual
            $fecha = new DateTime();
            // Formatear la fecha a 'd-M-y' (ejemplo: '17-mar-25')
            $fechaFormateada = $fecha->format('d-M-y');
        ?>
        
        <div style="display:none;">
            <label for="fecha">Fecha:</label>
            <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" readonly><br><br>
        </div>
        
        <p><b>Fecha:</b> <?php echo $fechaFormateada; ?></p>
    
        <label for="cod_sucursal">Sucursal:</label>
        <select name="cod_sucursal" id="cod_sucursal" required>
            <option value="" disabled selected>Seleccione una sucursal</option>
            <?php
            // Obtener sucursales activas desde la base de datos
            $query_sucursales = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal = 1 ORDER BY nombre";
            $stmt_sucursales = $conn->prepare($query_sucursales);
            $stmt_sucursales->execute();
            $sucursales = $stmt_sucursales->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($sucursales as $sucursal) {
                echo '<option value="' . $sucursal['codigo'] . '">' . htmlspecialchars($sucursal['nombre']) . '</option>';
            }
            ?>
        </select>
        <input type="hidden" name="sucursal" id="sucursal_nombre"><br><br>
    
        <label for="persona">Verificador(a):</label>
        <input type="text" name="persona" id="persona_input" placeholder="Buscar por nombre o código..." required>
        <input type="hidden" name="operario_id" id="operario_id">
        <br>
        
        <div style="background:#F6F6F6; text-align:center;">
            <strong><h3>Rango de Calificación:</h3></strong>
            <p>1 - Deficiente | 5 - Excelente | N/A - No Aplica</p>
        </div>
        
        <div style="background:#51B8AC;">
            <strong><h2>AUDITORÍA DE LIMPIEZA</h2></strong>
        </div>
    
        <!-- Limpieza en Exterior -->
        <div style="background:#F6F6F6;">
            <h3>1.1. Limpieza en Exterior</h3>
        </div>
        <br>
        <?php
        $questions_exterior = [
            "Acera y cunetas correctamente barridas",
            "Hay basura en exteriores (vasos, cartón, papel, etc.)",
            "Vidrios limpios",
            "Cortinas metálicas están limpias",
            "Bolsas de basura en su contenedor",
            "Contenedor de basura está limpio",
            "Se ha regado con manguera al exterior de la tienda",
            "Plantas ornamentales regadas y en buen estado",
            "Paredes externas limpias y bien pintadas",
            "Luces externas están limpias y sin polvo",
            "Cámaras externas limpias y sin polvo",
            "Rótulos de Pitaya, limpio y sin manchas",
            "Sillas y mesas externas limpias"
        ];
    
        foreach ($questions_exterior as $index => $question) {
            echo '<label for="limpieza_exterior_1_1_' . ($index + 1) . '">' . $question . ':</label><br>';
            echo '<div class="radio-group">';
            for ($i = 1; $i <= 5; $i++) {
                echo '<div class="radio-option" onclick="highlightSelection(this)">
                        <input type="radio" id="limpieza_exterior_1_1_' . ($index + 1) . '_' . $i . '" name="limpieza_exterior_1_1_' . ($index + 1) . '" value="' . $i . '" required>
                        <label for="limpieza_exterior_1_1_' . ($index + 1) . '_' . $i . '">' . $i . '</label>
                      </div>';
            }
            echo '<div class="radio-option" onclick="highlightSelection(this)">
                    <input type="radio" id="limpieza_exterior_1_1_' . ($index + 1) . '_na" name="limpieza_exterior_1_1_' . ($index + 1) . '" value="N/A" required>
                    <label for="limpieza_exterior_1_1_' . ($index + 1) . '_na">N/A</label>
                  </div>';
            echo '</div><br>';
        }
        ?>
    
        <!-- Limpieza de Interiores -->
        <div style="background:#F6F6F6;">
            <h3>1.2. Limpieza de Interiores</h3>
        </div>
        <br>
        <?php
        $questions_interior = [
            "Paredes interiores del edificio están limpias",
            "Vidrios internos limpios y pulidos",
            "Piso limpio y con buen aroma (lampaseado)",
            "Sillas y mesas limpias y sin chicles por debajo",
            "Hay música en la Tienda",
            "Vitrinas limpias por dentro y por fuera (no chorreadas)",
            "Área de facturación limpia y en orden",
            "Mesas de acero y pantry, limpias y sin sarro",
            "Productos Pitaya en orden y con su respetiva etiqueta",
            "Paredes y techo sin telarañas",
            "Abanicos de salón limpios y sin polvo acumulado",
            "Bodega limpia y ordenada",
            "Productos de bodega clasificados y etiquetados.",
            "Baños limpios y sin mal olor"
        ];
    
        foreach ($questions_interior as $index => $question) {
            echo '<label for="limpieza_interior_1_2_' . ($index + 1) . '">' . $question . ':</label><br>';
            echo '<div class="radio-group">';
            for ($i = 1; $i <= 5; $i++) {
                echo '<div class="radio-option" onclick="highlightSelection(this)">
                        <input type="radio" id="limpieza_interior_1_2_' . ($index + 1) . '_' . $i . '" name="limpieza_interior_1_2_' . ($index + 1) . '" value="' . $i . '" required>
                        <label for="limpieza_interior_1_2_' . ($index + 1) . '_' . $i . '">' . $i . '</label>
                      </div>';
            }
            echo '<div class="radio-option" onclick="highlightSelection(this)">
                    <input type="radio" id="limpieza_interior_1_2_' . ($index + 1) . '_na" name="limpieza_interior_1_2_' . ($index + 1) . '" value="N/A" required>
                    <label for="limpieza_interior_1_2_' . ($index + 1) . '_na">N/A</label>
                  </div>';
            echo '</div><br>';
        }
        ?>
    
        <!-- Limpieza de Equipos y Utensilios -->
        <div style="background:#F6F6F6;">
            <h3>1.3. Limpieza de Equipos y Utensilios</h3>
        </div>
        <br>
        <?php
        $questions_equipment = [
            "Vasos de licuadoras están limpios y en buen estado",
            "Tapa de licuadora limpia, sin moho ni curtida",
            "Empaques de hule de licuadora limpios y sin residuos",
            "Motor de licuadora, limpio y en buen estado (botones, patas, cable, domos)",
            "Refrigeradora limpia y presentable exteriormente",
            "Refrigeradora limpia internamente (empaques, rejilla, costados)",
            "Frízer limpios y presentables externamente",
            "Frízeres limpios internamente (empaque, rejilla sin hielo)",
            "Waflera luce limpia y presentable externamente",
            "Waflera sin costras por malas prácticas de limpieza",
            "Extractor de frutas limpio (canales, cable, superficie)",
            "Piezas plásticas de extractor limpias y no están curtidas",
            "Menaje en buen estado y limpio"
        ];
    
        foreach ($questions_equipment as $index => $question) {
            echo '<label for="limpieza_equipo_1_3_' . ($index + 1) . '">' . $question . ':</label><br>';
            echo '<div class="radio-group">';
            for ($i = 1; $i <= 5; $i++) {
                echo '<div class="radio-option" onclick="highlightSelection(this)">
                        <input type="radio" id="limpieza_equipo_1_3_' . ($index + 1) . '_' . $i . '" name="limpieza_equipo_1_3_' . ($index + 1) . '" value="' . $i . '" required>
                        <label for="limpieza_equipo_1_3_' . ($index + 1) . '_' . $i . '">' . $i . '</label>
                      </div>';
            }
            echo '<div class="radio-option" onclick="highlightSelection(this)">
                    <input type="radio" id="limpieza_equipo_1_3_' . ($index + 1) . '_na" name="limpieza_equipo_1_3_' . ($index + 1) . '" value="N/A" required>
                    <label for="limpieza_equipo_1_3_' . ($index + 1) . '_na">N/A</label>
                  </div>';
            echo '</div>';
        }
        ?>
        
        <!-- Limpieza de Insumos -->
        <div style="background:#F6F6F6;">
            <h3>1.4. Manejo de Insumos</h3>
        </div>
        <br>
        <?php
        $questions_insumos = [
            "Disponibilidad de insumos",
            "Productos de mostrador no vencidos",
            "Buena rotación de productos e insumos. Primeros en entrar, primeros en salir",
            "Productos procesados rotulados con fecha de elaboración (naranja, limón)",
            "Frutas sin dañar o deterioradas en cajillas",
            "Miel y azúcar con fecha de recepción"
        ];
    
        foreach ($questions_insumos as $index => $question) {
            echo '<label for="limpieza_insumos_1_4_' . ($index + 1) . '">' . $question . ':</label><br>';
            echo '<div class="radio-group">';
            for ($i = 1; $i <= 5; $i++) {
                echo '<div class="radio-option" onclick="highlightSelection(this)">
                        <input type="radio" id="limpieza_insumos_1_4_' . ($index + 1) . '_' . $i . '" name="limpieza_insumos_1_4_' . ($index + 1) . '" value="' . $i . '" required>
                        <label for="limpieza_insumos_1_4_' . ($index + 1) . '_' . $i . '">' . $i . '</label>
                      </div>';
            }
            echo '<div class="radio-option" onclick="highlightSelection(this)">
                    <input type="radio" id="limpieza_insumos_1_4_' . ($index + 1) . '_na" name="limpieza_insumos_1_4_' . ($index + 1) . '" value="N/A" required>
                    <label for="limpieza_insumos_1_4_' . ($index + 1) . '_na">N/A</label>
                  </div>';
            echo '</div>';
        }
        ?>
        
        <div style="display:none;">
            <!-- Promedio de Limpieza en Exterior -->
            <div id="promedioExteriorContainer" style="background:#F6F6F6; padding:10px; margin-top:20px;">
                <strong><h3>Promedio de Limpieza en Exterior:</h3></strong>
                <p id="promedioExterior">0.00</p>
            </div>
            
            <!-- Promedio de Limpieza de Interiores -->
            <div id="promedioInteriorContainer" style="background:#F6F6F6; padding:10px; margin-top:20px;">
                <strong><h3>Promedio de Limpieza de Interiores:</h3></strong>
                <p id="promedioInterior">0.00</p>
            </div>
            
            <!-- Promedio de Limpieza de Equipos y Utensilios -->
            <div id="promedioEquipoContainer" style="background:#F6F6F6; padding:10px; margin-top:20px;">
                <strong><h3>Promedio de Limpieza de Equipos y Utensilios:</h3></strong>
                <p id="promedioEquipo">0.00</p>
            </div>
            
            <!-- Promedio de Limpieza de Equipos y Utensilios -->
            <div id="promedioInsumosContainer" style="background:#F6F6F6; padding:10px; margin-top:20px;">
                <strong><h3>Promedio de Limpieza de Insumos:</h3></strong>
                <p id="promedioInsumos">0.00</p>
            </div>
            
            <!-- Promedio General -->
            <div id="promedioGeneralContainer" style="background:#F6F6F6; padding:10px; margin-top:20px;">
                <strong><h3>Promedio General:</h3></strong>
                <p id="promedioGeneral">0.00</p>
            </div>
        </div>
        
        <!-- En la parte del formulario HTML, agrega esto antes de la sección de fotos: -->
        <label for="comentarios">Comentarios (opcional):</label><br>
        <textarea name="comentarios" id="comentarios" rows="4" cols="50" 
                  style="width: 80%; max-width: 500px; text-align: left; text-align-last: left; resize: vertical; padding: 8px;"></textarea><br><br>
        
        <!-- En el formulario, agregar la sección de captura de foto antes del botón Guardar -->
        <label>Capturar fotos (requerido):</label>
        <div>
            <!-- Selector de cámaras -->
            <select id="selectorCamara">
                <option value="">Seleccionar cámara...</option>
            </select>
            <!-- Elemento para mostrar la cámara en vivo -->
            <video id="video" width="320" height="240" autoplay></video>
            <!-- Botón para capturar la foto -->
            <button type="button" id="capturarBtn">Capturar foto</button>
        </div>
        <br>
        <!-- Elemento para mostrar la foto capturada -->
        <canvas id="canvas" width="320" height="240" style="display:none;"></canvas>
        
        <!-- Galería de fotos capturadas -->
        <div id="gallery" class="gallery-container"></div>
        
        <!-- Input oculto para enviar las fotos capturadas como JSON -->
        <input type="hidden" id="fotos" name="fotos" value="[]">
        
        <br>
        
        <div style="margin-bottom:13px;">
            <div id="estadoAuditoria" style="color: red; font-weight: bold; margin-bottom: 10px; display: none;">Esta Auditoría requiere registro fotográfico...<br> Auditoría Incompleta</div>
            <button type="submit" id="guardarBtn" disabled>Guardar</button>
            <a href="index.php">Cancelar</a>
        </div>
        
        <!-- Agrega esto dentro del formulario, antes del botón de Guardar -->
        <input type="hidden" id="promedio_exterior" name="promedio_exterior" value="0.00">
        <input type="hidden" id="promedio_interior" name="promedio_interior" value="0.00">
        <input type="hidden" id="promedio_equipo" name="promedio_equipo" value="0.00">
        <input type="hidden" id="promedio_insumos" name="promedio_insumos" value="0.00">
        <input type="hidden" id="promedio_general" name="promedio_general" value="0.00">
    </form>
    
    <!-- JavaScript para manejar la selección y el color de fondo -->
    <script>
        <?php
        // Obtener operarios para autocompletar
        $operarios_autocomplete = [];
        try {
            $query = "SELECT 
                        o.CodOperario, 
                        CONCAT(
                            COALESCE(o.CodOperario, ''), ' - ',
                            COALESCE(o.Nombre, ''), ' ',
                            COALESCE(o.Nombre2, ''), ' ',
                            COALESCE(o.Apellido, ''), ' ',
                            COALESCE(o.Apellido2, '')
                        ) AS label_completo,
                        CONCAT(
                            COALESCE(o.Nombre, ''), ' ',
                            COALESCE(o.Nombre2, ''), ' ',
                            COALESCE(o.Apellido, ''), ' ',
                            COALESCE(o.Apellido2, '')
                        ) AS nombre_completo
                      FROM Operarios o
                      WHERE o.Operativo = 1
                      ORDER BY label_completo";
            $stmt = $conn->query($query);
            $operarios_autocomplete = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Error silencioso para no romper la app
        }
        $operarios_json = json_encode($operarios_autocomplete);
        ?>
        const operarios = <?php echo $operarios_json; ?>;
        
        // Preparar datos para autocomplete
        const operariosAutocomplete = operarios.map(operario => ({
            label: operario.label_completo,
            value: operario.nombre_completo,
            id: operario.CodOperario
        }));

        $(document).ready(function() {
            $('#persona_input').autocomplete({
                source: function(request, response) {
                    const term = request.term.toLowerCase();
                    const filtered = operariosAutocomplete.filter(op => 
                        op.label.toLowerCase().includes(term)
                    );
                    response(filtered.slice(0, 15)); // Limitar a 15 resultados
                },
                minLength: 2,
                select: function(event, ui) {
                    $(this).val(ui.item.value);
                    $('#operario_id').val(ui.item.id);
                    return false;
                },
                change: function(event, ui) {
                    if (!ui.item) {
                        // Si borra el campo, limpiar el ID
                        if ($(this).val().trim() === '') {
                            $('#operario_id').val('');
                        }
                    }
                }
            });
        });

        // Elementos del DOM para fotos
        // Elementos del DOM para fotos
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const capturarBtn = document.getElementById('capturarBtn');
        const selectorCamara = document.getElementById('selectorCamara');
        const gallery = document.getElementById('gallery');
        const fotosInput = document.getElementById('fotos');
        
        let fotosArray = []; // Array para almacenar las fotos capturadas
        let stream; // Variable para almacenar el stream de la cámara
        
        // Función para actualizar el input oculto con las fotos
        function actualizarFotosInput() {
            fotosInput.value = JSON.stringify(fotosArray);
        }
        
        // Función para mostrar las fotos en la galería
        function actualizarGaleria() {
            gallery.innerHTML = '';
            
            fotosArray.forEach((foto, index) => {
                const photoContainer = document.createElement('div');
                photoContainer.className = 'photo-thumbnail';
                
                const img = document.createElement('img');
                img.src = foto;
                
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-photo';
                removeBtn.innerHTML = '×';
                removeBtn.onclick = (e) => {
                    e.preventDefault();
                    eliminarFoto(index);
                };
                
                photoContainer.appendChild(img);
                photoContainer.appendChild(removeBtn);
                gallery.appendChild(photoContainer);
            });
            
            actualizarFotosInput();
            verificarCompletitud();
        }
        
        // Función para eliminar una foto
        function eliminarFoto(index) {
            fotosArray.splice(index, 1);
            actualizarGaleria();
        }
        
        // Función para listar las cámaras disponibles
        async function listarCamaras() {
            try {
                const dispositivos = await navigator.mediaDevices.enumerateDevices();
                const camaras = dispositivos.filter(dispositivo => dispositivo.kind === 'videoinput');
                
                selectorCamara.innerHTML = '<option value="">Seleccionar cámara...</option>';
                
                camaras.forEach((camara, index) => {
                    const option = document.createElement('option');
                    option.value = camara.deviceId;
                    option.text = camara.label || `Cámara ${index + 1}`;
                    selectorCamara.appendChild(option);
                });
            } catch (error) {
                console.error("Error al listar las cámaras: ", error);
                alert("No se pudieron listar las cámaras disponibles.");
            }
        }
        
        // Función para iniciar la cámara seleccionada
        async function iniciarCamara(deviceId) {
            try {
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
        
                const constraints = {
                    video: {
                        deviceId: deviceId ? { exact: deviceId } : undefined,
                        width: { ideal: 320 },
                        height: { ideal: 240 }
                    }
                };
        
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                video.play();
            } catch (error) {
                console.error("Error al iniciar la cámara: ", error);
                alert("No se pudo acceder a la cámara seleccionada.");
            }
        }
        
        // Evento para cambiar de cámara
        selectorCamara.addEventListener('change', async () => {
            const deviceId = selectorCamara.value;
            if (deviceId) {
                await iniciarCamara(deviceId);
            }
        });
        
        // Capturar la foto
        capturarBtn.addEventListener('click', function() {
            // Dibujar la imagen actual del video en el canvas
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Convertir la imagen del canvas a una URL en formato base64
            const fotoData = canvas.toDataURL('image/png');
            
            // Agregar la foto al array
            fotosArray.push(fotoData);
            
            // Actualizar la galería
            actualizarGaleria();
        });
        
        // Función para verificar si todas las preguntas están completas y la foto ha sido capturada
        function verificarCompletitud() {
            var estadoAuditoria = document.getElementById('estadoAuditoria');
            
            // Verificar preguntas de exterior
            var radiosExterior = document.querySelectorAll('input[type="radio"][name^="limpieza_exterior_1_1_"]:checked');
            var exteriorCompleto = radiosExterior.length >= <?php echo count($questions_exterior); ?>;
            
            // Verificar preguntas de interior
            var radiosInterior = document.querySelectorAll('input[type="radio"][name^="limpieza_interior_1_2_"]:checked');
            var interiorCompleto = radiosInterior.length >= <?php echo count($questions_interior); ?>;
            
            // Verificar preguntas de equipo
            var radiosEquipo = document.querySelectorAll('input[type="radio"][name^="limpieza_equipo_1_3_"]:checked');
            var equipoCompleto = radiosEquipo.length >= <?php echo count($questions_equipment); ?>;
            
            // Verificar preguntas de insumos
            var radiosInsumos = document.querySelectorAll('input[type="radio"][name^="limpieza_insumos_1_4_"]:checked');
            var insumosCompleto = radiosInsumos.length >= <?php echo count($questions_insumos); ?>;
            
            // Verificar que haya al menos una foto capturada
            var fotosCapturadas = fotosArray.length > 0;
            
            // Habilitar el botón solo si todo está completo
            if (exteriorCompleto && interiorCompleto && equipoCompleto && insumosCompleto && fotosCapturadas) {
                guardarBtn.disabled = false;
                estadoAuditoria.style.display = 'none';
            } else {
                guardarBtn.disabled = true;
                estadoAuditoria.style.display = 'block';
                
                if (!fotosCapturadas) {
                    estadoAuditoria.textContent = "Esta Auditoría requiere registro fotográfico...\nAuditoría Incompleta";
                } else {
                    estadoAuditoria.textContent = "Auditoría Incompleta";
                }
            }
        }

        // Función para cambiar el color de fondo de la opción seleccionada
        function highlightSelection(clickedDiv) {
            var radioInput = clickedDiv.querySelector('input[type="radio"]'); // Selecciona el radio button dentro del div
            if (radioInput) {
                radioInput.checked = true; // Marca el radio button como seleccionado
                var groupName = radioInput.name;
                var options = document.querySelectorAll('.radio-option input[name="' + groupName + '"]');
    
                // Reiniciar el fondo de todas las opciones
                options.forEach(function(option) {
                    option.parentElement.style.backgroundColor = ''; // Restablece el fondo de las opciones no seleccionadas
                });
    
                // Cambiar el fondo de la opción seleccionada
                clickedDiv.style.backgroundColor = '#51B8AC'; // El color que desees
            }
            calcularPromedios(); // Llama a la función para calcular los promedios
            verificarCompletitud(); // Agregar esta línea
        }
    
        // Función para calcular los promedios
        function calcularPromedios() {
            // Calcular promedio de Limpieza en Exterior
            var totalExterior = 0;
            var countExterior = 0;
            var radiosExterior = document.querySelectorAll('input[type="radio"][name^="limpieza_exterior_1_1_"]:checked');
        
            radiosExterior.forEach(function(radio) {
                var value = radio.value;
                if (value !== "N/A") {
                    totalExterior += parseInt(value);
                    countExterior++;
                }
            });
        
            var promedioExterior = countExterior > 0 ? (totalExterior / countExterior).toFixed(2) : 0.00;
            document.getElementById('promedioExterior').textContent = promedioExterior;
            document.getElementById('promedio_exterior').value = promedioExterior; // Actualizar campo oculto
        
            // Calcular promedio de Limpieza de Interiores
            var totalInterior = 0;
            var countInterior = 0;
            var radiosInterior = document.querySelectorAll('input[type="radio"][name^="limpieza_interior_1_2_"]:checked');
        
            radiosInterior.forEach(function(radio) {
                var value = radio.value;
                if (value !== "N/A") {
                    totalInterior += parseInt(value);
                    countInterior++;
                }
            });
        
            var promedioInterior = countInterior > 0 ? (totalInterior / countInterior).toFixed(2) : 0.00;
            document.getElementById('promedioInterior').textContent = promedioInterior;
            document.getElementById('promedio_interior').value = promedioInterior; // Actualizar campo oculto
        
            // Calcular promedio de Limpieza de Equipos y Utensilios
            var totalEquipo = 0;
            var countEquipo = 0;
            var radiosEquipo = document.querySelectorAll('input[type="radio"][name^="limpieza_equipo_1_3_"]:checked');
        
            radiosEquipo.forEach(function(radio) {
                var value = radio.value;
                if (value !== "N/A") {
                    totalEquipo += parseInt(value);
                    countEquipo++;
                }
            });
        
            var promedioEquipo = countEquipo > 0 ? (totalEquipo / countEquipo).toFixed(2) : 0.00;
            document.getElementById('promedioEquipo').textContent = promedioEquipo;
            document.getElementById('promedio_equipo').value = promedioEquipo; // Actualizar campo oculto
            
            // Calcular promedio de Limpieza de Insumos
            var totalInsumos = 0;
            var countInsumos = 0;
            var radiosInsumos = document.querySelectorAll('input[type="radio"][name^="limpieza_insumos_1_4_"]:checked');
        
            radiosInsumos.forEach(function(radio) {
                var value = radio.value;
                if (value !== "N/A") {
                    totalInsumos += parseInt(value);
                    countInsumos++;
                }
            });
        
            var promedioInsumos = countInsumos > 0 ? (totalInsumos / countInsumos).toFixed(2) : 0.00;
            document.getElementById('promedioInsumos').textContent = promedioInsumos;
            document.getElementById('promedio_insumos').value = promedioInsumos; // Actualizar campo oculto
        
            // Calcular promedio general
            var totalGeneral = totalExterior + totalInterior + totalEquipo + totalInsumos;
            var countGeneral = countExterior + countInterior + countEquipo + countInsumos;
            var promedioGeneral = countGeneral > 0 ? (totalGeneral / countGeneral).toFixed(2) : 0.00;
            document.getElementById('promedioGeneral').textContent = promedioGeneral;
            document.getElementById('promedio_general').value = promedioGeneral; // Actualizar campo oculto
        }
    
        // Añadir event listeners a los botones de radio para calcular los promedios cuando cambien
        document.querySelectorAll('input[type="radio"][name^="limpieza_exterior_1_1_"], input[type="radio"][name^="limpieza_interior_1_2_"], input[type="radio"][name^="limpieza_equipo_1_3_"], input[type="radio"][name^="limpieza_insumos_1_4_"]').forEach(function(radio) {
            radio.addEventListener('change', calcularPromedios);
        });
        
        // Función para validar el formulario antes de enviarlo
        function validarFormulario() {
            // Verificar que haya al menos una foto
            if (fotosArray.length === 0) {
                alert("Por favor, capture al menos una foto antes de guardar.");
                return false;
            }
            
            // Verificar que todos los grupos de botones de radio tengan al menos una opción seleccionada
            var radiosExterior = document.querySelectorAll('input[type="radio"][name^="limpieza_exterior_1_1_"]:checked');
            var radiosInterior = document.querySelectorAll('input[type="radio"][name^="limpieza_interior_1_2_"]:checked');
            var radiosEquipo = document.querySelectorAll('input[type="radio"][name^="limpieza_equipo_1_3_"]:checked');
            var radiosInsumos = document.querySelectorAll('input[type="radio"][name^="limpieza_insumos_1_4_"]:checked');
        
            if (radiosExterior.length < <?php echo count($questions_exterior); ?> ||
                radiosInterior.length < <?php echo count($questions_interior); ?> ||
                radiosEquipo.length < <?php echo count($questions_equipment); ?> ||
                radiosInsumos.length < <?php echo count($questions_insumos); ?>) {
                alert("Por favor, complete todas las preguntas antes de guardar.");
                return false;
            }
        
            // Mostrar mensaje de confirmación
            if (!confirm("¿Está seguro que desea guardar los datos? Esta acción no podrá deshacerse...")) {
                return false;
            }
        
            return true;
        }
        
        // Iniciar la cámara al cargar la página
        (async function() {
            await listarCamaras();
            await iniciarCamara();
            
            // Verificar completitud cuando cambien los radios
            document.querySelectorAll('input[type="radio"]').forEach(function(radio) {
                radio.addEventListener('change', verificarCompletitud);
            });
            
            // Verificar estado inicial
            verificarCompletitud();
        })();
        
        // Script para manejar el cambio de sucursal y mantener ambos valores (código y nombre)
        document.addEventListener('DOMContentLoaded', function() {
            const codSucursalSelect = document.getElementById('cod_sucursal');
            const sucursalNombreInput = document.getElementById('sucursal_nombre');
            
            // Mapeo de códigos a nombres de sucursales
            const sucursalesMap = <?php 
                $map = [];
                foreach ($sucursales as $s) { $map[$s['codigo']] = $s['nombre']; }
                echo json_encode($map);
            ?>;
            
            // Actualizar el campo oculto con el nombre de la sucursal cuando cambie la selección
            codSucursalSelect.addEventListener('change', function() {
                const selectedCode = this.value;
                if (selectedCode && sucursalesMap[selectedCode]) {
                    sucursalNombreInput.value = sucursalesMap[selectedCode];
                } else {
                    sucursalNombreInput.value = '';
                }
            });
            
            // Inicializar el valor al cargar la página
            if (codSucursalSelect.value) {
                sucursalNombreInput.value = sucursalesMap[codSucursalSelect.value] || '';
            }
        });
    </script>
</body>
</html>
