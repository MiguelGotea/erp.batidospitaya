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
verificarAccesoCargo([11, 13, 16, 21, 49, 52]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 13, 16, 21, 49, 52]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "SELECT asv.*, asv.tipo_auditoria, asv.promedio_calificacion, asv.comentarios,
                   COALESCE(CONCAT(o.Nombre, ' ', o.Apellido), asv.persona) AS persona_nombre
            FROM auditoria_servicio asv
            LEFT JOIN Operarios o ON asv.operario_id = o.CodOperario
            WHERE asv.id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    $sqlFotos = "SELECT ruta_foto FROM auditoria_servicio_fotos WHERE auditoria_id = :id";
    $stmtFotos = $conn->prepare($sqlFotos);
    $stmtFotos->bindParam(':id', $id, PDO::PARAM_INT);
    $stmtFotos->execute();
    $fotos = $stmtFotos->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!$registro) {
        echo "No se encontró el registro.";
        exit();
    }
} else {
    echo "ID no proporcionado.";
    exit();
}
?>

<style>
    * {
        font-size: clamp(11px, 2vw, 16px) !important;
        margin: 0;
        padding: 0;
        align-items: center;
        align-content: center;
        text-align: center;
        font-family: 'Calibri', sans-serif;
    }

    body {
        background-color: #F6F6F6;
    }

    table {
        margin: 0;
        padding: 0;
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: center;
    }

    th {
        background-color: #f4f4f4;
    }

    header {
        margin: 20px;
    }

    a {
        color: white;
        text-decoration: none;
        padding: 10px;
    }

    .btn-volver {
        background-color: #51B8AC;
        color: white;
        text-decoration: none;
        padding: 10px;
        transition: background-color 0.3s ease;
    }

    .btn-volver:hover {
        background-color: #45a597;
    }

    #btn-generar-pdf {
        background-color: #0E544C;
        color: white;
        border: none;
        padding: 10px 20px;
        cursor: pointer;
        margin: 10px;
    }

    #btn-generar-pdf:hover {
        background-color: #51B8AC;
    }

    /* Galería responsiva mejorada - 3 columnas en móviles con tamaño reducido */
    .gallery-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        /* Siempre 3 columnas */
        gap: 8px;
        /* Espacio más pequeño entre fotos */
        margin: 20px 0;
        padding: 0 5px;
    }

    .photo-item {
        position: relative;
        width: 100%;
        height: 0;
        padding-bottom: 100%;
        /* Relación 1:1 (cuadrada) */
        overflow: hidden;
        border-radius: 6px;
        /* Bordes ligeramente menos redondeados */
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        /* Sombra más sutil */
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .photo-item:hover {
        transform: scale(1.02);
        /* Efecto hover más sutil */
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
        z-index: 1;
    }

    .photo-item img {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Ajustes para pantallas muy pequeñas */
    @media (max-width: 400px) {
        .gallery-container {
            gap: 5px;
        }
    }

    /* Ajustes para pantallas grandes */
    @media (min-width: 768px) {
        .gallery-container {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }
    }

    /* Lightbox optimizado para móviles */
    @media (max-width: 600px) {
        .lightbox-content {
            max-width: 95%;
            max-height: 70vh;
        }

        .close-lightbox {
            top: 10px;
            right: 15px;
        }

        .lightbox-nav button {
            width: 40px;
            height: 40px;
        }
    }

    .no-photos {
        color: #666;
        font-style: italic;
        margin: 20px 0;
        grid-column: 1 / -1;
        /* Ocupa todas las columnas */
    }

    /* Lightbox mejorado - solución para el problema de visualización */
    .lightbox {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.95);
        z-index: 9999;
        /* Valor muy alto para asegurar que esté por encima */
        justify-content: center;
        align-items: center;
        overflow-y: auto;
        /* Permitir scroll si el contenido es muy grande */
    }

    .lightbox-content {
        position: relative;
        max-width: 90%;
        max-height: 90vh;
        margin: 20px 0;
        /* Margen para no pegarse a los bordes */
    }

    .lightbox-content img {
        max-width: 100%;
        max-height: 80vh;
        /* Dejar espacio para los controles */
        display: block;
        margin: 0 auto;
        border: 3px solid white;
        border-radius: 5px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
    }

    .close-lightbox {
        position: fixed;
        /* Cambiado de absolute a fixed */
        top: 20px;
        right: 30px;
        color: white;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.3s;
        z-index: 10000;
        /* Asegurar que esté por encima del lightbox */
    }

    .close-lightbox:hover {
        color: #51B8AC;
    }

    .lightbox-nav {
        position: fixed;
        width: 100%;
        display: flex;
        justify-content: space-between;
        top: 50%;
        transform: translateY(-50%);
        padding: 0 20px;
        box-sizing: border-box;
        z-index: 10000;
        /* Asegurar que esté por encima del lightbox */
    }

    .lightbox-nav button {
        background: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        cursor: pointer;
        transition: background 0.3s;
    }

    .lightbox-nav button:hover {
        background: #51B8AC;
    }

    /* Asegurar que el body no haga scroll cuando el lightbox está abierto */
    body.lightbox-open {
        overflow: hidden;
        position: fixed;
        width: 100%;
    }
</style>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Auditoría de Servicios</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">

    <!-- Incluir html2canvas y jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body>
    <!-- Header con logo -->
    <header>
        <a href="index.php">
            <img src="/core/assets/img/Logo.svg" alt="Logo de la empresa" class="logo" style="max-width:75px;">
        </a>
    </header>

    <div style="text-align:center;">
        <h2>Detalles de la Auditoría de Servicios</h2>

        <div id="tabla-auditoria">
            <table>
                <tbody>
                    <br>
                    <p>No. Auditoría: <?php echo $registro['id']; ?></p>

                    <p>Fecha: <?php
                                $meses = [
                                    1 => 'ene',
                                    2 => 'feb',
                                    3 => 'mar',
                                    4 => 'abr',
                                    5 => 'may',
                                    6 => 'jun',
                                    7 => 'jul',
                                    8 => 'ago',
                                    9 => 'sep',
                                    10 => 'oct',
                                    11 => 'nov',
                                    12 => 'dic'
                                ];

                                $fecha = new DateTime($registro['fecha_hora']);
                                $dia = $fecha->format('d');
                                $mes = $meses[(int)$fecha->format('m')];
                                $anio = $fecha->format('y');

                                $hora = $fecha->format('H:i');
                                $hora_formateada = ($hora == '00:00') ? '12:00 am' : (($fecha->format('H') < 12) ? $fecha->format('g:i a') : (($fecha->format('H') == 12) ? $fecha->format('g:i') . ' pm' :
                                            $fecha->format('g:i') . ' pm')); // Se añadió el paréntesis que faltaba

                                echo "$dia-$mes-$anio $hora_formateada";
                                ?>
                    </p>

                    <p>Sucursal: <?php echo $registro['sucursal']; ?></p>

                    <p>Verificador(a): <?php echo $registro['persona_nombre']; ?></p>

                    <!-- Preguntas específicas de la tabla auditoria_servicio -->
                    <tr>
                        <th style="text-align:center;">
                            Servicio
                        </th>
                        <th style="text-align:center;">
                            <?php echo $registro['promedio_calificacion']; ?>
                        </th>
                    </tr>
                    <tr>
                        <td>¿Da la bienvenida a los clientes según el protocolo de servicio y entrega menú?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_1']) && $registro['evaluacion_servicio_4_1'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_1'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_1'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Mantiene contacto visual con el cliente al atenderlo?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_2']) && $registro['evaluacion_servicio_4_2'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_2'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_2'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Pregunta al cliente el # de membresía de Club Pitaya?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_3']) && $registro['evaluacion_servicio_4_3'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_3'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_3'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Ofrece ayuda si el cliente está indeciso?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_4']) && $registro['evaluacion_servicio_4_4'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_4'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_4'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Sugiere las promociones y combos vigentes y tarjeta de Club Pitaya?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_5']) && $registro['evaluacion_servicio_4_5'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_5'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_5'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Sugiere el tamaño normal para los batidos?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_6']) && $registro['evaluacion_servicio_4_6'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_6'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_6'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Menciona todas las opciones de endulzante?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_7']) && $registro['evaluacion_servicio_4_7'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_7'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_7'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Pregunta adecuadamente el nombre del cliente?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_8']) && $registro['evaluacion_servicio_4_8'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_8'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_8'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Lo llama por su nombre y repite la orden antes del cobro?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_9']) && $registro['evaluacion_servicio_4_9'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_9'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_9'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Se le invita a esperar o sentarse mientras se prepara el batido?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_10']) && $registro['evaluacion_servicio_4_10'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_10'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_10'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Se llama por el nombre y repite la orden para hacer la entrega?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_11']) && $registro['evaluacion_servicio_4_11'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_11'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_11'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Se despide según protocolo de servicio?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_12']) && $registro['evaluacion_servicio_4_12'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_12'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_12'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Se usa un tono de voz y vocabulario adecuado?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_13']) && $registro['evaluacion_servicio_4_13'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_13'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_13'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿Posición y lenguaje corporal es el adecuado (erguido, firme y frente al cliente)?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_14']) && $registro['evaluacion_servicio_4_14'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_14'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_14'];
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>¿No se usa gestos inadecuados?</td>
                        <td style="text-align:center;">
                            <?php
                            if (empty($registro['evaluacion_servicio_4_15']) && $registro['evaluacion_servicio_4_15'] !== '0') {
                                echo 'N/A';
                            } elseif ($registro['evaluacion_servicio_4_15'] == 0) {
                                echo 'N/A';
                            } else {
                                echo $registro['evaluacion_servicio_4_15'];
                            }
                            ?>
                        </td>
                    </tr>
                    <!--<tr>
                        <td style="text-align:center;">Foto capturada:</td>
                        <td style="text-align:center;">
                            <?php if (!empty($registro['foto'])): ?>
                                <img src="<?php echo $registro['foto']; ?>" alt="Foto de la auditoría" style="max-width: 300px; max-height: 300px;">
                            <?php else: ?>
                                No hay foto disponible
                            <?php endif; ?>
                        </td>
                    </tr>-->
                </tbody>
            </table>

            <!-- Mostrar comentarios antes de las fotos -->
            <?php if (!empty($registro['comentarios'])): ?>
                <div style="margin-top: 30px; text-align: left; padding: 0 10px;">
                    <h3>Comentarios</h3>
                    <p style="background-color: #f8f8f8; padding: 15px; border-radius: 5px; border-left: 4px solid #51B8AC;">
                        <?php echo nl2br(htmlspecialchars($registro['comentarios'])); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Sección de fotos -->
            <div style="margin-top: 30px;">
                <h3>Fotos de la Auditoría</h3>

                <?php if (empty($fotos)): ?>
                    <p class="no-photos">No hay fotos disponibles para esta auditoría</p>
                <?php else: ?>
                    <div class="gallery-container">
                        <?php foreach ($fotos as $index => $foto): ?>
                            <div class="photo-item" onclick="openLightbox(<?php echo $index; ?>)">
                                <img src="<?php echo $foto; ?>" alt="Foto de auditoría <?php echo $index + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lightbox para mostrar fotos en grande -->
        <div id="lightbox" class="lightbox">
            <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
            <div class="lightbox-content">
                <img id="lightbox-image" src="" alt="">
            </div>
            <div class="lightbox-nav">
                <button onclick="changePhoto(-1)">&#10094;</button>
                <button onclick="changePhoto(1)">&#10095;</button>
            </div>
        </div>

        <div style="padding:30px;">
            <a href="generar_pdf.php?id=<?php echo $registro['id']; ?>" id="btn-generar-pdf">Guardar como PDF</a>
            <a class="btn-volver" href="index.php">Volver a la lista</a>
        </div>
    </div>

    <script>
        // Variables para el lightbox
        let currentPhotoIndex = 0;
        const photos = <?php echo json_encode($fotos); ?>;

        // Función para abrir el lightbox
        function openLightbox(index) {
            if (photos.length === 0) return;

            currentPhotoIndex = index;
            document.getElementById('lightbox-image').src = photos[currentPhotoIndex];
            document.getElementById('lightbox').style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Evitar scroll del body
        }

        // Función para cerrar el lightbox
        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
            document.body.style.overflow = 'auto'; // Restaurar scroll del body
        }

        // Función para cambiar de foto
        function changePhoto(step) {
            currentPhotoIndex += step;

            // Circular navigation
            if (currentPhotoIndex >= photos.length) {
                currentPhotoIndex = 0;
            } else if (currentPhotoIndex < 0) {
                currentPhotoIndex = photos.length - 1;
            }

            document.getElementById('lightbox-image').src = photos[currentPhotoIndex];
        }

        // Cerrar con la tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft') {
                changePhoto(-1);
            } else if (e.key === 'ArrowRight') {
                changePhoto(1);
            }
        });

        // Cerrar haciendo clic fuera de la imagen
        document.getElementById('lightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });
    </script>
</body>

</html>