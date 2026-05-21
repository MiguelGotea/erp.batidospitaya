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
verificarAccesoCargo([8, 11, 16, 21, 49]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 11, 16, 21, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

    // Obtener la selección del usuario (si existe)
    $tipo_seleccionado = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';

    // Validar la selección para evitar inyecciones SQL
    $tipos_permitidos = ['todos', 'limpieza', 'personal', 'servicio'];
    if (!in_array($tipo_seleccionado, $tipos_permitidos)) {
        $tipo_seleccionado = 'todos'; // Valor por defecto
    }

    try {
        // Subconsulta para combinar las 3 tablas
        $sql = "
            SELECT * FROM (
                SELECT id, fecha_hora, sucursal, persona, promedio_general AS promedio, 'limpieza' AS tipo_auditoria FROM auditoria
                UNION ALL
                SELECT id, fecha_hora, sucursal, persona, promedio_personal AS promedio, 'personal' AS tipo_auditoria FROM auditoria_personal
                UNION ALL
                SELECT id, fecha_hora, sucursal, persona, promedio_calificacion AS promedio, 'servicio' AS tipo_auditoria FROM auditoria_servicio
            ) AS combined_tables
        ";

        // Aplicar filtro si no es "todos"
        if ($tipo_seleccionado != 'todos') {
            $sql .= " WHERE tipo_auditoria = :tipo";
        }

        // Ordenar por fecha de manera descendente
        $sql .= " ORDER BY fecha_hora DESC";

        $stmt = $conn->prepare($sql);
        if ($tipo_seleccionado != 'todos') {
            $stmt->bindParam(':tipo', $tipo_seleccionado);
        }
        $stmt->execute();
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Manejo de errores
        die("Error en la consulta: " . $e->getMessage());
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros de Auditoría</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            text-align: center;
            align-content: center;
            align-items: center;
            justify-content: center;
            font-size: clamp(11px, 2vw, 16px); /* Tamaño mínimo de 11px, se adapta al viewport */
        }

        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 99%; /* Asegura que el body ocupe todo el ancho */
        }

        header {
            margin: 20px;
        }

        .logo {
            max-width: 75px;
        }

        .btn-agregar {
            background-color: #51B8AC;
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            margin: 5px;
            display: inline-block;
        }

        .contenedor-principal {
            width: 100%;
            max-width: 1200px; /* Ajusta este valor según lo que consideres adecuado */
            margin: 0 auto;
            padding: 0 1px; /* Añade un poco de padding a los lados */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
        }

        th {
            background-color: #51B8AC;
            color: white;
        }

        .columna-numero {
            width: 30px;
            display: none;
        }

        .columna-promedio {
            width: 60px;
        }

        .promedio-contenedor {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .filtro-contenedor {
            position: relative;
            display: inline-block;
        }

        .filtro-opciones {
            display: none;
            position: absolute;
            background-color: white;
            border: 1px solid #ccc;
            z-index: 1;
            padding: 5px;
            border-radius: 5px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
        }

        .filtro-opciones a {
            display: block;
            padding: 5px;
            text-decoration: none;
            color: black;
        }

        .filtro-opciones a:hover {
            background-color: #f1f1f1;
        }

        .filtro-contenedor:hover .filtro-opciones {
            display: block;
        }

        .filtro-encabezado {
            cursor: pointer;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-contenido {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 300px;
            text-align: center;
        }

        .modal-contenido h3 {
            margin-bottom: 20px;
        }

        .modal-contenido button {
            padding: 10px 20px;
            margin: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .modal-contenido #confirmar-btn {
            background-color: #FF6F61;
            color: white;
        }

        .modal-contenido #confirmar-btn:hover {
            background-color: #E55C4B;
        }

        .modal-contenido #cancelar-btn {
            background-color: #51B8AC;
            color: white;
        }

        .modal-contenido #cancelar-btn:hover {
            background-color: #0E544C;
        }
        
        /* Nuevos estilos para el header responsive */
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1200px;
            padding: 0 10px;
            box-sizing: border-box;
            margin: 20px auto;
            flex-wrap: wrap;
        }
        
        .logo-container {
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex-grow: 1;
        }
        
        .btn-agregar {
            background-color: #51B8AC;
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
            white-space: nowrap;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .buttons-container {
                width: 100%;
                justify-content: flex-start;
                gap: 8px;
            }
            
            .btn-agregar {
                padding: 6px 10px;
                font-size: 13px;
            }
        }
        
        @media (max-width: 480px) {
            .btn-agregar {
                flex-grow: 1;
                justify-content: center;
                white-space: normal;
                text-align: center;
                padding: 8px 5px;
            }
            
            .btn-agregar i {
                margin-right: 4px;
            }
        }
    </style>
</head>
<body>
    <!-- Header con logo -->
    <div class="header-container">
        <div class="logo-container">
            <a href="logout.php">
                <img src="/core/assets/img/Logo.svg" alt="Logo de la empresa" class="logo">
            </a>
        </div>
    </div>
    
    <div class="contenedor-principal">
        <!-- Mostrar registros de la tabla seleccionada -->
        <strong><p style="margin-top:5px; margin-bottom:0;">Registros de Auditoría</p></strong>
        <table>
            <thead>
                <tr>
                    <th class="columna-numero">No. Auditoría</th>
                    <th style="text-align:center;">Fecha</th>
                    <th style="text-align:center;">Sucursal</th>
                    <th style="text-align:center; display:none;">Persona</th>
                    <th style="text-align:center;">
                        <div class="filtro-contenedor">
                            <span class="filtro-encabezado">
                                Tipo Auditoría <i class="fas fa-caret-down"></i>
                            </span>
                            <div class="filtro-opciones">
                                <a href="index.php?tipo=todos">Todos</a>
                                <a href="index.php?tipo=limpieza">Limpieza</a>
                                <a href="index.php?tipo=personal">Personal</a>
                                <a href="index.php?tipo=servicio">Servicios</a>
                            </div>
                        </div>
                    </th>
                    <th style="text-align:center;" class="columna-promedio">Puntaje</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registros)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; background-color:#fff;">Sin registros actualmente.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($registros as $registro): ?>
                    <tr>
                        <td class="columna-numero"><?php echo $registro['id']; ?></td>
                        <td style="text-align:center;">
                            <?php
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
                        </td>
                        <td style="text-align:center;"><?php echo $registro['sucursal']; ?></td>
                        <td style="display:none; text-align:center;"><?php echo $registro['persona']; ?></td>
                        <td style="text-align:center;"><?php echo ucfirst($registro['tipo_auditoria']); ?></td>
                        <td style="text-align:center;" class="columna-promedio">
                            <div style="text-align:center;" class="promedio-contenedor">
                                <?php echo number_format($registro['promedio'], 2); ?>
                                <a href="<?php echo ($registro['tipo_auditoria'] == 'limpieza') ? 'ver.php' : ($registro['tipo_auditoria'] == 'personal' ? 'verpersonal.php' : 'verservicios.php'); ?>?id=<?php echo $registro['id']; ?>" style="color:#51B8AC;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
