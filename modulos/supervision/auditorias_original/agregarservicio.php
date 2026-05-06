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
verificarAccesoCargo([16, 21, 49]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([16, 21, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
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

    // EVALUACIÓN DE SERVICIOS
    $evaluacion_servicio_4_1 = $_POST['evaluacion_servicio_4_1'];
    $evaluacion_servicio_4_2 = $_POST['evaluacion_servicio_4_2'];
    $evaluacion_servicio_4_3 = $_POST['evaluacion_servicio_4_3'];
    $evaluacion_servicio_4_4 = $_POST['evaluacion_servicio_4_4'];
    $evaluacion_servicio_4_5 = $_POST['evaluacion_servicio_4_5'];
    $evaluacion_servicio_4_6 = $_POST['evaluacion_servicio_4_6'];
    $evaluacion_servicio_4_7 = $_POST['evaluacion_servicio_4_7'];
    $evaluacion_servicio_4_8 = $_POST['evaluacion_servicio_4_8'];
    $evaluacion_servicio_4_9 = $_POST['evaluacion_servicio_4_9'];
    $evaluacion_servicio_4_10 = $_POST['evaluacion_servicio_4_10'];
    $evaluacion_servicio_4_11 = $_POST['evaluacion_servicio_4_11'];
    $evaluacion_servicio_4_12 = $_POST['evaluacion_servicio_4_12'];
    $evaluacion_servicio_4_13 = $_POST['evaluacion_servicio_4_13'];
    $evaluacion_servicio_4_14 = $_POST['evaluacion_servicio_4_14'];
    $evaluacion_servicio_4_15 = $_POST['evaluacion_servicio_4_15'];
    $promedio_calificacion = $_POST['promedio_calificacion'];

    // Insertar los datos en la base de datos (sin fotos)
    $sql = "INSERT INTO auditoria_servicio (
                fecha, fecha_hora, sucursal, cod_sucursal, persona, operario_id, comentarios,
                evaluacion_servicio_4_1, evaluacion_servicio_4_2, evaluacion_servicio_4_3, 
                evaluacion_servicio_4_4, evaluacion_servicio_4_5, evaluacion_servicio_4_6, 
                evaluacion_servicio_4_7, evaluacion_servicio_4_8, evaluacion_servicio_4_9, 
                evaluacion_servicio_4_10, evaluacion_servicio_4_11, evaluacion_servicio_4_12, 
                evaluacion_servicio_4_13, evaluacion_servicio_4_14, evaluacion_servicio_4_15,
                promedio_calificacion
            ) VALUES (
                :fecha, CONVERT_TZ(NOW(), '+00:00', '-06:00'), :sucursal, :cod_sucursal, :persona, :operario_id, :comentarios,
                :evaluacion_servicio_4_1, :evaluacion_servicio_4_2, :evaluacion_servicio_4_3, 
                :evaluacion_servicio_4_4, :evaluacion_servicio_4_5, :evaluacion_servicio_4_6, 
                :evaluacion_servicio_4_7, :evaluacion_servicio_4_8, :evaluacion_servicio_4_9, 
                :evaluacion_servicio_4_10, :evaluacion_servicio_4_11, :evaluacion_servicio_4_12, 
                :evaluacion_servicio_4_13, :evaluacion_servicio_4_14, :evaluacion_servicio_4_15,
                :promedio_calificacion
            )";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':fecha', $fecha);
    $stmt->bindParam(':sucursal', $sucursal);
    $stmt->bindParam(':cod_sucursal', $cod_sucursal);
    $stmt->bindParam(':persona', $persona);
    $stmt->bindParam(':operario_id', $operario_id);
    $stmt->bindParam(':comentarios', $comentarios); // Nuevo bindParam para comentarios

    // EVALUACIÓN DE SERVICIOS
    $stmt->bindParam(':evaluacion_servicio_4_1', $evaluacion_servicio_4_1);
    $stmt->bindParam(':evaluacion_servicio_4_2', $evaluacion_servicio_4_2);
    $stmt->bindParam(':evaluacion_servicio_4_3', $evaluacion_servicio_4_3);
    $stmt->bindParam(':evaluacion_servicio_4_4', $evaluacion_servicio_4_4);
    $stmt->bindParam(':evaluacion_servicio_4_5', $evaluacion_servicio_4_5);
    $stmt->bindParam(':evaluacion_servicio_4_6', $evaluacion_servicio_4_6);
    $stmt->bindParam(':evaluacion_servicio_4_7', $evaluacion_servicio_4_7);
    $stmt->bindParam(':evaluacion_servicio_4_8', $evaluacion_servicio_4_8);
    $stmt->bindParam(':evaluacion_servicio_4_9', $evaluacion_servicio_4_9);
    $stmt->bindParam(':evaluacion_servicio_4_10', $evaluacion_servicio_4_10);
    $stmt->bindParam(':evaluacion_servicio_4_11', $evaluacion_servicio_4_11);
    $stmt->bindParam(':evaluacion_servicio_4_12', $evaluacion_servicio_4_12);
    $stmt->bindParam(':evaluacion_servicio_4_13', $evaluacion_servicio_4_13);
    $stmt->bindParam(':evaluacion_servicio_4_14', $evaluacion_servicio_4_14);
    $stmt->bindParam(':evaluacion_servicio_4_15', $evaluacion_servicio_4_15);
    $stmt->bindParam(':promedio_calificacion', $promedio_calificacion);

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
                            $sqlFoto = "INSERT INTO auditoria_servicio_fotos (auditoria_id, ruta_foto) VALUES (:auditoria_id, :ruta_foto)";
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
    <title>Auditoría Evaluación de Servicios</title>
    <link rel="stylesheet" href="styleagg.css">
    <!-- Favicon -->
    <link rel="icon" href="icon12.png" type="image/png">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            text-align: center;
            align-content: center;
            align-items: center;
            justify-content: center;
            font-size: clamp(11px, 2vw, 16px) !important; /* Tamaño mínimo de 11px, se adapta al viewport */
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
        }

        header, form {
            margin-top: 10px;
            width: 100%;
            max-width: 800px; /* Ajusta este valor según tus necesidades */
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            background-color: #fff;
            padding: 10px;
            border-radius: 8px;
            border: 2px solid #ddd;
            width: fit-content;
            transition: background-color 0.3s ease;
        }

        .radio-option:hover {
            background-color: #51B8AC;
        }

        select, input {
            padding: 3px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: clamp(11px, 2vw, 16px);
            width: 200px;
        }

        .radio-group {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .radio-option {
            display: inline-block;
            margin-right: 5px;
        }

        .radio-option input[type="radio"] {
            margin: 0;
        }

        .radio-option label {
            margin: 0;
        }
        
        #guardarBtn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        /* Nuevos estilos para la galería de fotos */
        .gallery-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            justify-content: center;
        }
        
        .photo-thumbnail {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .photo-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .remove-photo {
            position: absolute;
            top: 2px;
            right: 2px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <!-- Header con logo -->
    <header>
        <a href="logout.php">
            <img src="Logo.svg" alt="Logo de la empresa" class="logo" style="max-width:75px;">
        </a>
    </header>
    
    <a href="logout.php" style="display:none;">Cerrar Sesión</a>
    
    <h1>AUDITORÍA DE EVALUACIÓN DE SERVICIOS</h1>
        
    <form action="agregarservicio.php" method="POST" onsubmit="return validarFormulario()">
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
        
        <!-- EVALUACIÓN DE SERVICIOS -->
        <div style="background:#51B8AC;">
            <h3>EVALUACIÓN DE SERVICIOS</h3>
        </div>
        <br>
        <?php
        $questions_servicio = [
            "¿Da la bienvenida a los clientes según el protocolo de servicio y entrega menú?",
            "¿Mantiene contacto visual con el cliente al atenderlo?",
            "¿Pregunta al cliente el # de membresía de Club Pitaya?",
            "¿Ofrece ayuda si el cliente está indeciso?",
            "¿Sugiere las promociones y combos vigentes y tarjeta de Club Pitaya?",
            "¿Sugiere el tamaño normal para los batidos?",
            "¿Menciona todas las opciones de endulzante?",
            "¿Pregunta adecuadamente el nombre del cliente?",
            "¿Lo llama por su nombre y repite la orden antes del cobro?",
            "¿Se le invita a esperar o sentarse mientras se prepara el batido?",
            "¿Se llama por el nombre y repite la orden para hacer la entrega?",
            "¿Se despide según protocolo de servicio?",
            "¿Se usa un tono de voz y vocabulario adecuado?",
            "¿Posición y lenguaje corporal es el adecuado (erguido, firme y frente al cliente)?",
            "¿No se usa gestos inadecuados?"
        ];

        foreach ($questions_servicio as $index => $question) {
            echo '<label for="evaluacion_servicio_4_' . ($index + 1) . '">' . $question . ':</label><br>';
            echo '<div class="radio-group">';
            for ($i = 1; $i <= 5; $i++) {
                echo '<div class="radio-option" onclick="highlightSelection(this)">
                        <input type="radio" id="evaluacion_servicio_4_' . ($index + 1) . '_' . $i . '" name="evaluacion_servicio_4_' . ($index + 1) . '" value="' . $i . '" required>
                        <label for="evaluacion_servicio_4_' . ($index + 1) . '_' . $i . '">' . $i . '</label>
                      </div>';
            }
            echo '<div class="radio-option" onclick="highlightSelection(this)">
                    <input type="radio" id="evaluacion_servicio_4_' . ($index + 1) . '_na" name="evaluacion_servicio_4_' . ($index + 1) . '" value="N/A" required>
                    <label for="evaluacion_servicio_4_' . ($index + 1) . '_na">N/A</label>
                  </div>';
            echo '</div>';
        }
        ?>

        <div style="display:none;">
            <div id="promedioContainer" style="background:#F6F6F6; padding:3px; margin-top:3px; margin-bottom:3px;">
                <strong><h3>Promedio de Calificación:</h3></strong>
                <p id="promedio">0.00</p>
            </div>
        </div>
        
        <!-- En la parte del formulario HTML, agrega esto antes de la sección de fotos: -->
        <label for="comentarios">Comentarios (opcional):</label><br>
        <textarea name="comentarios" id="comentarios" rows="4" cols="50" 
                  style="width: 80%; max-width: 500px; text-align: left; text-align-last: left; resize: vertical; padding: 8px;"></textarea><br><br>
        
        <!-- Sección de fotos modificada -->
        <label>Capturar fotos (opcional):</label>
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
    
        <!-- Agrega esto dentro del formulario, antes del botón de Guardar -->
        <input type="hidden" id="promedio_calificacion" name="promedio_calificacion" value="0.00">
    
        <div style="margin-bottom:13px;">
            <div id="estadoAuditoria" style="color: red; font-weight: bold; margin-bottom: 10px; display: none;">Auditoría Incompleta</div>
            <button type="submit" id="guardarBtn" disabled>Guardar</button>
            <a href="index.php">Cancelar</a>
        </div>
    </form>
    
    <script>
        // Función para cambiar el color de fondo de la opción seleccionada
        function highlightSelection(clickedDiv) {
            var radioInput = clickedDiv.querySelector('input[type="radio"]');
            if (radioInput) {
                radioInput.checked = true;
                var groupName = radioInput.name;
                var options = document.querySelectorAll('.radio-option input[name="' + groupName + '"]');
    
                options.forEach(function(option) {
                    option.parentElement.style.backgroundColor = '';
                });
    
                clickedDiv.style.backgroundColor = '#51B8AC';
            }
            calcularPromedio();
            verificarCompletitud();
        }
    
        // Función para calcular el promedio
        function calcularPromedio() {
            var total = 0;
            var count = 0;
        
            var radios = document.querySelectorAll('input[type="radio"][name^="evaluacion_servicio_4_"]:checked');
        
            radios.forEach(function(radio) {
                var value = radio.value;
                if (value !== "N/A") {
                    total += parseInt(value);
                    count++;
                }
            });
        
            var promedio = count > 0 ? (total / count).toFixed(2) : 0.00;
            document.getElementById('promedio').textContent = promedio;
        
            document.getElementById('promedio_calificacion').value = promedio;
        }
    
        // Función para verificar si todas las preguntas están completas
        function verificarCompletitud() {
            var radios = document.querySelectorAll('input[type="radio"][name^="evaluacion_servicio_4_"]');
            var grupos = {};
            var estadoAuditoria = document.getElementById('estadoAuditoria');
        
            // Verificar si todas las preguntas están completas
            radios.forEach(function(radio) {
                if (!grupos[radio.name]) {
                    grupos[radio.name] = false;
                }
                if (radio.checked) {
                    grupos[radio.name] = true;
                }
            });
        
            var todasCompletas = Object.values(grupos).every(function(completo) {
                return completo;
            });
        
            // Habilitar el botón de Guardar solo si todas las preguntas están completas
            if (todasCompletas) {
                document.getElementById('guardarBtn').disabled = false;
                estadoAuditoria.style.display = 'none';
            } else {
                document.getElementById('guardarBtn').disabled = true;
                estadoAuditoria.style.display = 'block';
            }
        }
    
        // Añadir event listeners a los botones de radio para calcular el promedio y verificar la completitud cuando cambien
        document.querySelectorAll('input[type="radio"][name^="evaluacion_servicio_4_"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                calcularPromedio();
                verificarCompletitud();
            });
        });
        
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
        
        // Función para eliminar una foto
        function eliminarFoto(index) {
            fotosArray.splice(index, 1);
            actualizarGaleria();
        }
        
        // Capturar la foto (solo un event listener)
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
    
        // Función para validar el formulario antes de enviarlo
        function validarFormulario() {
            var radios = document.querySelectorAll('input[type="radio"][name^="evaluacion_servicio_4_"]:checked');
            if (radios.length < <?php echo count($questions_servicio); ?>) {
                alert("Por favor, complete todas las preguntas antes de guardar.");
                return false;
            }

            if (!confirm("¿Está seguro que desea guardar los datos? Esta acción no podrá deshacerse...")) {
                return false;
            }

            return true;
        }
    
        // Iniciar la cámara por defecto al cargar la página
        (async function() {
            await listarCamaras();
            await iniciarCamara();
        })();
        
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

        // Al cargar la página, verificar el estado inicial
        document.addEventListener('DOMContentLoaded', function() {
            verificarCompletitud();
            
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
