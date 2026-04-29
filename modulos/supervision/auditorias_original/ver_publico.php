<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once 'auth.php';
require_once 'funciones.php';
require_once 'conexion.php';

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('supervision');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([5, 8, 11, 13, 16, 21, 27]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([5, 8, 11, 13, 16, 21, 27]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Verificar si se ha pasado un ID en la URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Obtener el registro de la base de datos
    $sql = "SELECT a.*, a.tipo_auditoria, a.promedio_exterior, a.promedio_interior, a.promedio_equipo, a.promedio_general, a.comentarios,
                   COALESCE(CONCAT(o.Nombre, ' ', o.Apellido), a.persona) AS persona_nombre
            FROM auditoria a
            LEFT JOIN Operarios o ON a.operario_id = o.CodOperario
            WHERE a.id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener las fotos asociadas
    $sqlFotos = "SELECT ruta_foto FROM auditoria_fotos WHERE auditoria_id = :id";
    $stmtFotos = $conn->prepare($sqlFotos);
    $stmtFotos->bindParam(':id', $id, PDO::PARAM_INT);
    $stmtFotos->execute();
    $fotos = $stmtFotos->fetchAll(PDO::FETCH_COLUMN, 0);

    // Verificar si el registro existe
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
        text-align:center;
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

    th, td {
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
        transition: background-color 0.3s ease; /* Transición suave */
    }
    
    .btn-volver:hover {
        background-color: #45a597; /* Color al pasar el mouse */
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
    grid-template-columns: repeat(3, 1fr); /* Siempre 3 columnas */
    gap: 8px; /* Espacio más pequeño entre fotos */
    margin: 20px 0;
    padding: 0 5px;
}

.photo-item {
    position: relative;
    width: 100%;
    height: 0;
    padding-bottom: 100%; /* Relación 1:1 (cuadrada) */
    overflow: hidden;
    border-radius: 6px; /* Bordes ligeramente menos redondeados */
    box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Sombra más sutil */
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.photo-item:hover {
    transform: scale(1.02); /* Efecto hover más sutil */
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
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
        right: 15px;+
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
    grid-column: 1 / -1; /* Ocupa todas las columnas */
}

/* Lightbox mejorado - solución para el problema de visualización */
.lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.95);
    z-index: 9999; /* Valor muy alto para asegurar que esté por encima */
    justify-content: center;
    align-items: center;
    overflow-y: auto; /* Permitir scroll si el contenido es muy grande */
}

.lightbox-content {
    position: relative;
    max-width: 90%;
    max-height: 90vh;
    margin: 20px 0; /* Margen para no pegarse a los bordes */
}

.lightbox-content img {
    max-width: 100%;
    max-height: 80vh; /* Dejar espacio para los controles */
    display: block;
    margin: 0 auto;
    border: 3px solid white;
    border-radius: 5px;
    box-shadow: 0 0 20px rgba(0,0,0,0.5);
}

.close-lightbox {
    position: fixed; /* Cambiado de absolute a fixed */
    top: 20px;
    right: 30px;
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s;
    z-index: 10000; /* Asegurar que esté por encima del lightbox */
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
    z-index: 10000; /* Asegurar que esté por encima del lightbox */
}

.lightbox-nav button {
    background: rgba(0,0,0,0.5);
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
    <title>Detalles de Auditoría</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="icon12.png" type="image/png">
    
    <!-- Incluir html2canvas y jsPDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
    <!-- Header con logo -->
    <header>
        <a href="index_auditorias_publico.php">
            <img src="Logo.svg" alt="Logo de la empresa" class="logo" style="max-width:75px;">
        </a>
    </header>

    <div style="text-align:center;">
        <h2>Auditoría de Limpieza</h2>

        <div id="tabla-auditoria">
            <table>
                <tbody>
                    <br>
                    <p>No. Auditoría: <?php echo $registro['id']; ?></p>
                    <p>Fecha: <?php
                                $meses = [
                                    1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
                                    5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
                                    9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
                                ];
                                
                                $fecha = new DateTime($registro['fecha_hora']);
                                $dia = $fecha->format('d');
                                $mes = $meses[(int)$fecha->format('m')];
                                $anio = $fecha->format('y');
                                
                                $hora = $fecha->format('H:i');
                                $hora_formateada = ($hora == '00:00') ? '12:00 am' :
                                                  (($fecha->format('H') < 12) ? $fecha->format('g:i a') :
                                                  (($fecha->format('H') == 12) ? $fecha->format('g:i') . ' pm' :
                                                  $fecha->format('g:i') . ' pm')); // Se añadió el paréntesis que faltaba
                                
                                echo "$dia-$mes-$anio $hora_formateada";
                            ?>
                    </p>
                    <p>Sucursal: <?php echo $registro['sucursal']; ?></p>
                    <p>Verificador(a): <?php echo $registro['persona_nombre']; ?></p>
                    
                    <!-- Aquí puedes agregar más campos si es necesario -->
                    
                    <tr>
                        <th style="text-align:center;">Limpieza</th>
                        <th style="text-align:center;"><?php echo $registro['promedio_general']; ?></th>
                    </tr>
                    
                    <!-- Limpieza Exterior -->
                    <tr>
                        <td style="text-align:center; background:#b7bfc9;">Limpieza Exterior</td>
                        <th style="text-align:center; background:#b7bfc9;"><?php echo $registro['promedio_exterior']; ?></th>
                    </tr>
                    
                    <tr>
                        <td>Acera y cunetas correctamente barridas</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_1']) && $registro['limpieza_exterior_1_1_1'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_1'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_1'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Hay basura en exteriores (vasos, cartón, papel, etc.)</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_2']) && $registro['limpieza_exterior_1_1_2'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_2'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_2'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Vidrios limpios</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_3']) && $registro['limpieza_exterior_1_1_3'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_3'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_3'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Cortinas metálicas están limpias</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_4']) && $registro['limpieza_exterior_1_1_4'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_4'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_4'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Bolsas de basura en su contenedor</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_5']) && $registro['limpieza_exterior_1_1_5'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_5'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_5'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Contenedor de basura está limpio</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_6']) && $registro['limpieza_exterior_1_1_6'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_6'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_6'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Se ha regado con manguera al exterior de la tienda</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_7']) && $registro['limpieza_exterior_1_1_7'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_7'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_7'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Plantas ornamentales regadas y en buen estado</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_8']) && $registro['limpieza_exterior_1_1_8'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_8'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_8'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Paredes externas limpias y bien pintadas</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_9']) && $registro['limpieza_exterior_1_1_9'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_9'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_9'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Luces externas están limpias y sin polvo</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_10']) && $registro['limpieza_exterior_1_1_10'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_10'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_10'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Cámaras externas limpias y sin polvo</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_11']) && $registro['limpieza_exterior_1_1_11'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_11'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_11'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Rótulos de Pitaya, limpio y sin manchas</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_12']) && $registro['limpieza_exterior_1_1_12'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_12'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_12'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Sillas y mesas externas limpias</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_exterior_1_1_13']) && $registro['limpieza_exterior_1_1_13'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_exterior_1_1_13'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_exterior_1_1_13'];
                                }
                            ?>
                        </td>
                    </tr>
                    <!-- Limpieza Interior -->
                    <tr>
                        <td style="text-align:center; background:#b7bfc9;">Limpieza Interior</td>
                        <th style="text-align:center; background:#b7bfc9;"><?php echo $registro['promedio_interior']; ?></th>
                    </tr>
                    
                    <tr>
                        <td>Paredes interiores del edificio están limpias</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_1']) && $registro['limpieza_interior_1_2_1'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_1'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_1'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Vidrios internos limpios y pulidos</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_2']) && $registro['limpieza_interior_1_2_2'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_2'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_2'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Piso limpio y con buen aroma (lampaseado)</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_3']) && $registro['limpieza_interior_1_2_3'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_3'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_3'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Sillas y mesas limpias y sin chicles por debajo</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_4']) && $registro['limpieza_interior_1_2_4'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_4'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_4'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Hay música en la Tienda</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_5']) && $registro['limpieza_interior_1_2_5'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_5'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_5'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Vitrinas limpias por dentro y por fuera (no chorreadas)</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_6']) && $registro['limpieza_interior_1_2_6'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_6'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_6'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Área de facturación limpia y en orden</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_7']) && $registro['limpieza_interior_1_2_7'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_7'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_7'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Mesas de acero y pantry, limpias y sin sarro</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_8']) && $registro['limpieza_interior_1_2_8'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_8'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_8'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Productos Pitaya en orden y con su respetiva etiqueta</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_9']) && $registro['limpieza_interior_1_2_9'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_9'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_9'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Paredes y techo sin telarañas</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_10']) && $registro['limpieza_interior_1_2_10'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_10'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_10'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Abanicos de salón limpios y sin polvo acumulado</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_11']) && $registro['limpieza_interior_1_2_11'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_11'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_11'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Bodega limpia y ordenada</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_12']) && $registro['limpieza_interior_1_2_12'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_12'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_12'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Productos de bodega clasificados y etiquetados</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_13']) && $registro['limpieza_interior_1_2_13'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_13'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_13'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Baños limpios y sin mal olor</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_14']) && $registro['limpieza_interior_1_2_14'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_14'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_14'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Productos de mostrador no vencidos</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_15']) && $registro['limpieza_interior_1_2_15'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_15'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_15'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Buena rotación de productos e insumos. Primeros en entrar, primeros en salir</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_16']) && $registro['limpieza_interior_1_2_16'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_16'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_16'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Productos procesados rotulados con fecha de elaboración (naranja, limón)</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_17']) && $registro['limpieza_interior_1_2_17'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_17'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_17'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Frutas sin dañar o deterioradas en cajillas</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_18']) && $registro['limpieza_interior_1_2_18'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_18'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_18'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Miel y azúcar con fecha de recepción</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_interior_1_2_19']) && $registro['limpieza_interior_1_2_19'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_interior_1_2_19'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_interior_1_2_19'];
                                }
                            ?>
                        </td>
                    </tr>
                    <!-- Limpieza de Equipos y Utensilios -->
                    <tr>
                        <td style="text-align:center; background:#b7bfc9;">Limpieza de Equipos y Utensilios </td>
                        <th style="text-align:center; background:#b7bfc9;"><?php echo $registro['promedio_equipo']; ?></th>
                    </tr>
                    
                    <tr>
                        <td>Vasos de licuadoras están limpios y en buen estado</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_1']) && $registro['limpieza_equipo_1_3_1'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_1'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_1'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Tapa de licuadora limpia, sin moho ni curtida</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_2']) && $registro['limpieza_equipo_1_3_2'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_2'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_2'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Empaques de hule de licuadora limpios y sin residuos</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_3']) && $registro['limpieza_equipo_1_3_3'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_3'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_3'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Motor de licuadora, limpio y en buen estado (botones, patas, cable, domos)</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_4']) && $registro['limpieza_equipo_1_3_4'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_4'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_4'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Refrigeradora limpia y presentable exteriormente</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_5']) && $registro['limpieza_equipo_1_3_5'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_5'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_5'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Refrigeradora limpia internamente (empaques, rejilla, costados)</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_6']) && $registro['limpieza_equipo_1_3_6'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_6'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_6'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Frízer limpios y presentables externamente</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_7']) && $registro['limpieza_equipo_1_3_7'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_7'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_7'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Frízeres limpios internamente (empaque, rejilla sin hielo)</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_8']) && $registro['limpieza_equipo_1_3_8'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_8'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_8'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Waflera luce limpia y presentable externamente</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_9']) && $registro['limpieza_equipo_1_3_9'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_9'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_9'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Waflera sin costras por malas prácticas de limpieza</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_10']) && $registro['limpieza_equipo_1_3_10'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_10'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_10'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Extractor de frutas limpio (canales, cable, superficie)</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_11']) && $registro['limpieza_equipo_1_3_11'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_11'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_11'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Piezas plásticas de extractor limpias y no están curtidas</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_12']) && $registro['limpieza_equipo_1_3_12'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_12'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_12'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Menaje en buen estado y limpio</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_equipo_1_3_13']) && $registro['limpieza_equipo_1_3_13'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_equipo_1_3_13'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_equipo_1_3_13'];
                                }
                            ?>
                        </td>
                    </tr>
                    <!-- Manejo de Insumos -->
                    <tr>
                        <td style="text-align:center; background:#b7bfc9;">Manejo de Insumos </td>
                        <th style="text-align:center; background:#b7bfc9;"><?php echo $registro['promedio_insumos']; ?></th>
                    </tr>
                    
                    <tr>
                        <td>Disponibilidad de insumos</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_insumos_1_4_1']) && $registro['limpieza_insumos_1_4_1'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_insumos_1_4_1'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_insumos_1_4_1'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Productos de mostrador no vencidos</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_insumos_1_4_2']) && $registro['limpieza_insumos_1_4_2'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_insumos_1_4_2'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_insumos_1_4_2'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Buena rotación de productos e insumos. Primeros en entrar, primeros en salir</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_insumos_1_4_3']) && $registro['limpieza_insumos_1_4_3'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_insumos_1_4_3'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_insumos_1_4_3'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Productos procesados rotulados con fecha de elaboración (naranja, limón)</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_insumos_1_4_4']) && $registro['limpieza_insumos_1_4_4'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_insumos_1_4_4'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_insumos_1_4_4'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Frutas sin dañar o deterioradas en cajillas</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_insumos_1_4_5']) && $registro['limpieza_insumos_1_4_5'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_insumos_1_4_5'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_insumos_1_4_5'];
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Miel y azúcar con fecha de recepción</td>
                        <td style="text-align:center;">
                            <?php
                                if (empty($registro['limpieza_insumos_1_4_6']) && $registro['limpieza_insumos_1_4_6'] !== '0') {
                                    echo 'N/A';
                                } elseif ($registro['limpieza_insumos_1_4_6'] == 0) {
                                    echo 'N/A';
                                } else {
                                    echo $registro['limpieza_insumos_1_4_6'];
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
        </div>

        <div style="padding:30px;">
            <!-- Botón para generar el PDF -->
            <a class="btn-volver" href="index_auditorias_publico.php">Volver a la lista</a>
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
