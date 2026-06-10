<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso al módulo
if (!tienePermiso('auditorias_desempeno', 'crear', $cargoOperario) && !$esAdmin) {
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

    // PRESENTACIÓN DEL PERSONAL
    $presentacion_personal_2_1 = $_POST['presentacion_personal_2_1'];
    $presentacion_personal_2_2 = $_POST['presentacion_personal_2_2'];
    $presentacion_personal_2_3 = $_POST['presentacion_personal_2_3'];
    $presentacion_personal_2_4 = $_POST['presentacion_personal_2_4'];
    $presentacion_personal_2_5 = $_POST['presentacion_personal_2_5'];
    $presentacion_personal_2_6 = $_POST['presentacion_personal_2_6'];
    $presentacion_personal_2_7 = $_POST['presentacion_personal_2_7'];

    $promedio_personal = $_POST['promedio_personal'];

    // Insertar los datos en la base de datos (sin fotos)
    $sql = "INSERT INTO auditoria_personal (
                fecha, fecha_hora, sucursal, cod_sucursal, persona, operario_id, comentarios,
                presentacion_personal_2_1, presentacion_personal_2_2, presentacion_personal_2_3, 
                presentacion_personal_2_4, presentacion_personal_2_5, presentacion_personal_2_6, 
                presentacion_personal_2_7, presentacion_personal_2_8,
                promedio_personal
            ) VALUES (
                :fecha, CONVERT_TZ(NOW(), '+00:00', '-06:00'), :sucursal, :cod_sucursal, :persona, :operario_id, :comentarios,
                :presentacion_personal_2_1, :presentacion_personal_2_2, :presentacion_personal_2_3, 
                :presentacion_personal_2_4, :presentacion_personal_2_5, :presentacion_personal_2_6, 
                :presentacion_personal_2_7, :presentacion_personal_2_8,
                :promedio_personal
            )";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':fecha', $fecha);
    $stmt->bindParam(':sucursal', $sucursal);
    $stmt->bindParam(':cod_sucursal', $cod_sucursal);
    $stmt->bindParam(':persona', $persona);
    $stmt->bindParam(':operario_id', $operario_id);
    $stmt->bindParam(':comentarios', $comentarios); // Nuevo bindParam para comentarios

    // PRESENTACIÓN DEL PERSONAL
    $stmt->bindParam(':presentacion_personal_2_1', $presentacion_personal_2_1);
    $stmt->bindParam(':presentacion_personal_2_2', $presentacion_personal_2_2);
    $stmt->bindParam(':presentacion_personal_2_3', $presentacion_personal_2_3);
    $stmt->bindParam(':presentacion_personal_2_4', $presentacion_personal_2_4);
    $stmt->bindParam(':presentacion_personal_2_5', $presentacion_personal_2_5);
    $stmt->bindParam(':presentacion_personal_2_6', $presentacion_personal_2_6);
    $stmt->bindParam(':presentacion_personal_2_7', $presentacion_personal_2_7);
    $stmt->bindParam(':presentacion_personal_2_8', $_POST['presentacion_personal_2_8']);

    $stmt->bindParam(':promedio_personal', $promedio_personal);

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
                            $sqlFoto = "INSERT INTO auditoria_personal_fotos (auditoria_id, ruta_foto) VALUES (:auditoria_id, :ruta_foto)";
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
    <title>Auditoría Presentación del Personal</title>
    <link rel="stylesheet" href="styleagg.css">
    <!-- Favicon -->
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="stylesauditpers.css">
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
            font-size: clamp(11px, 2vw, 16px) !important;
            /* Tamaño mínimo de 10px, se adapta al viewport */
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
        }

        header,
        form {
            margin-top: 10px;
            width: 100%;
            max-width: 800px;
            /* Ajusta este valor según tus necesidades */
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

        select,
        input {
            padding: 3px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: clamp(10px, 2vw, 16px);
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

        /* Estilo para las opciones deshabilitadas */
        .disabled-option {
            opacity: 0.6;
            /* Hacer que se vean más tenues */
            pointer-events: none;
            /* Deshabilitar clics en el div */
            cursor: not-allowed;
            /* Cambiar el cursor a "no permitido" */
        }

        /* Estilo para las etiquetas de las opciones deshabilitadas */
        .disabled-option label {
            color: #ccc;
            /* Color gris para indicar que está deshabilitado */
        }

        /* Estilos para la sección de la cámara */
        #video {
            border: 2px solid #51B8AC;
            border-radius: 8px;
            margin: 10px 0;
        }

        #capturarBtn {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px 0;
        }

        #capturarBtn:hover {
            background-color: #3a9a8d;
        }

        #selectorCamara {
            margin-bottom: 10px;
        }

        /* Estilo para el botón "Guardar" deshabilitado */
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
            <img src="/core/assets/img/Logo.svg" alt="Logo de la empresa" class="logo" style="max-width:75px;">
        </a>
    </header>

    <a href="logout.php" style="display:none;">Cerrar Sesión</a>

    <h1>AUDITORÍA DE PRESENTACIÓN DEL PERSONAL</h1>

    <form action="agregarpersonal.php" method="POST" onsubmit="return validarFormulario()">
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
            <strong>
                <h3>Rango de Calificación:</h3>
            </strong>
            <p>1 - Deficiente | 5 - Excelente | N/A - No Aplica</p>
        </div>

        <!-- PRESENTACIÓN DEL PERSONAL -->
        <div style="background:#51B8AC;">
            <h3>PRESENTACIÓN DEL PERSONAL</h3>
        </div>
        <br>
        <?php
        $questions_personal = [
            "El personal usa las uñas cortas y limpias (sin pintura)",
            "Todo el personal está usando malla para cabello",
            "Varones bien rasurados cabello, barba y corte de pelo",
            "Mujeres con maquillaje sutil (mínimo requerido labial)",
            "El personal no cuenta con anillos ni pulseras",
            "El personal se presentó a trabajar en condiciones higiénicas",
            "El personal tiene su pin de identificación o training",
            "Porte y Aspecto (camisa por fuera, uniforme desaliñado)"
        ];

        foreach ($questions_personal as $index => $question) {
            echo '<label for="presentacion_personal_2_' . ($index + 1) . '">' . $question . ':</label><br>';
            echo '<div class="radio-group">';
            for ($i = 1; $i <= 5; $i++) {
                // Deshabilitar los valores 2, 3 y 4
                $disabled = ($i == 2 || $i == 3 || $i == 4) ? 'disabled' : '';
                $disabledClass = ($i == 2 || $i == 3 || $i == 4) ? 'disabled-option' : '';
                echo '<div class="radio-option ' . $disabledClass . '" onclick="highlightSelection(this)">
                        <input type="radio" id="presentacion_personal_2_' . ($index + 1) . '_' . $i . '" name="presentacion_personal_2_' . ($index + 1) . '" value="' . $i . '" required ' . $disabled . '>
                        <label for="presentacion_personal_2_' . ($index + 1) . '_' . $i . '">' . $i . '</label>
                      </div>';
            }
            // Deshabilitar la opción "N/A"
            echo '<div class="radio-option disabled-option" onclick="highlightSelection(this)">
                    <input type="radio" id="presentacion_personal_2_' . ($index + 1) . '_na" name="presentacion_personal_2_' . ($index + 1) . '" value="N/A" required disabled>
                    <label for="presentacion_personal_2_' . ($index + 1) . '_na">N/A</label>
                  </div>';
            echo '</div>';
        }
        ?>

        <div style="display:none;">
            <!-- Promedio de Presentación del Personal -->
            <div id="promedioPersonalContainer" style="background:#F6F6F6; padding:10px; margin-top:20px;">
                <strong>
                    <h3>Promedio de Presentación del Personal:</h3>
                </strong>
                <p id="promedioPersonal">0.00</p>
            </div>
        </div>

        <!-- En la parte del formulario HTML, agrega esto antes de la sección de fotos: -->
        <label for="comentarios">Comentarios (opcional):</label><br>
        <textarea name="comentarios" id="comentarios" rows="4" cols="50"
            style="width: 80%; max-width: 500px; text-align: left; text-align-last: left; resize: vertical; padding: 8px;"></textarea><br><br>

        <!-- Sección de fotos modificada -->
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

        <!-- Agrega esto dentro del formulario, antes del botón de Guardar -->
        <input type="hidden" id="promedio_personal" name="promedio_personal" value="0.00">

        <div style="margin-bottom:10px;">
            <div id="estadoAuditoria" style="color: red; font-weight: bold; margin-bottom: 10px; display: none;">Esta
                Auditoría requiere al menos una foto... <br> Auditoría Incompleta </div>
            <button type="submit" id="guardarBtn" disabled>Guardar</button>
            <a href="index.php">Cancelar</a>
        </div>
    </form>

    <script>
        // Elementos del DOM para la cámara y galería
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
                // Obtener todos los dispositivos de medios
                const dispositivos = await navigator.mediaDevices.enumerateDevices();
                // Filtrar solo los dispositivos de tipo "videoinput" (cámaras)
                const camaras = dispositivos.filter(dispositivo => dispositivo.kind === 'videoinput');

                // Limpiar el selector de cámaras
                selectorCamara.innerHTML = '<option value="">Seleccionar cámara...</option>';

                // Agregar cada cámara al selector
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
                // Detener el stream actual si existe
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }

                // Configurar las opciones de la cámara
                const constraints = {
                    video: {
                        deviceId: deviceId ? { exact: deviceId } : undefined, // Usar la cámara seleccionada
                        facingMode: deviceId ? undefined : { ideal: 'environment' },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    }
                };

                // Obtener el stream de la cámara
                stream = await navigator.mediaDevices.getUserMedia(constraints);
                video.srcObject = stream;
                video.onloadedmetadata = () => {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                };
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

        // Iniciar la cámara por defecto al cargar la página
        (async function() {
            await iniciarCamara(); // Iniciar la cámara por defecto
            await listarCamaras(); // Listar cámaras disponibles
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

        // Función para cambiar el color de fondo de la opción seleccionada
        function highlightSelection(clickedDiv) {
            // Verificar si el div está deshabilitado
            if (clickedDiv.classList.contains('disabled-option')) {
                return; // No hacer nada si está deshabilitado
            }

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
            calcularPromedios();
            verificarCompletitud();
        }

        // Función para calcular los promedios
        function calcularPromedios() {
            // Calcular promedio de Presentación del Personal
            var totalPersonal = 0;
            var countPersonal = 0;
            var radiosPersonal = document.querySelectorAll('input[type="radio"][name^="presentacion_personal_2_"]:checked');

            radiosPersonal.forEach(function(radio) {
                var value = radio.value;
                if (value !== "N/A") {
                    totalPersonal += parseInt(value);
                    countPersonal++;
                }
            });

            var promedioPersonal = countPersonal > 0 ? (totalPersonal / countPersonal).toFixed(2) : 0.00;
            document.getElementById('promedioPersonal').textContent = promedioPersonal;
            document.getElementById('promedio_personal').value = promedioPersonal;
        }

        // Función para verificar si todas las preguntas están completas e informando del estado de las fotos
        function verificarCompletitud() {
            var grupos = {};
            var estadoAuditoria = document.getElementById('estadoAuditoria');

            // Verificar preguntas de Presentación del Personal
            var radiosPersonal = document.querySelectorAll('input[type="radio"][name^="presentacion_personal_2_"]');
            radiosPersonal.forEach(function(radio) {
                if (!grupos[radio.name]) {
                    grupos[radio.name] = false;
                }
                if (radio.checked) {
                    grupos[radio.name] = true;
                }
            });

            // Verificar si todos los grupos tienen una respuesta seleccionada
            var todasCompletas = Object.values(grupos).every(function(completo) {
                return completo;
            });

            // Verificar si hay al menos una foto capturada
            var fotosCapturadas = fotosArray.length > 0;

            // Habilitar el botón de Guardar solo si todas las preguntas están completas y hay fotos
            var guardarBtn = document.getElementById('guardarBtn');
            if (todasCompletas && fotosCapturadas) {
                guardarBtn.disabled = false;
                estadoAuditoria.style.display = 'none';
            } else {
                guardarBtn.disabled = true;
                estadoAuditoria.style.display = 'block';
            }
        }

        // Añadir event listeners a los botones de radio para calcular los promedios y verificar la completitud cuando cambien
        document.querySelectorAll('input[type="radio"][name^="presentacion_personal_2_"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                calcularPromedios();
                verificarCompletitud();
            });
        });

        // Función para validar el formulario antes de enviarlo
        function validarFormulario() {
            var radiosPersonal = document.querySelectorAll('input[type="radio"][name^="presentacion_personal_2_"]:checked');

            if (radiosPersonal.length < <?php echo count($questions_personal); ?>) {
                alert("Por favor, complete todas las preguntas antes de guardar.");
                return false;
            }

            if (fotosArray.length === 0) {
                alert("Por favor, capture al menos una foto antes de guardar.");
                return false;
            }

            if (!confirm("¿Está seguro que desea guardar los datos? Esta acción no podrá deshacerse...")) {
                return false;
            }

            return true;
        }

        // Al cargar la página, verificar el estado inicial
        document.addEventListener('DOMContentLoaded', function() {
            verificarCompletitud();

            const codSucursalSelect = document.getElementById('cod_sucursal');
            const sucursalNombreInput = document.getElementById('sucursal_nombre');

            // Mapeo de códigos a nombres de sucursales
            const sucursalesMap = <?php
                                    $map = [];
                                    foreach ($sucursales as $s) {
                                        $map[$s['codigo']] = $s['nombre'];
                                    }
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