<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
if (!tienePermiso('registro_vacaciones', 'vista', $cargoOperario)) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

$esLider = tienePermiso('registro_vacaciones', 'ver_sucursales_lider', $cargoOperario);
$esRH = tienePermiso('registro_vacaciones', 'ver_todas_sucursales', $cargoOperario);
$puedeAprobar = tienePermiso('registro_vacaciones', 'aprobar', $cargoOperario);

/**
 * Obtiene el porcentaje de pago para un tipo de falta específico
 */
function obtenerPorcentajePagoTipoFalta($tipoFalta)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT porcentaje_pago 
        FROM tipos_falta 
        WHERE codigo = ? 
        LIMIT 1
    ");
    $stmt->execute([$tipoFalta]);
    $result = $stmt->fetch();

    return $result ? $result['porcentaje_pago'] : 0;
}

// Obtener sucursales según el cargo del usuario
if ($esRH) {
    // RH puede ver todas las sucursales
    $sucursales = obtenerTodasSucursales();
    // Agregar opción "Todas" al principio
    array_unshift($sucursales, ['codigo' => 'todas', 'nombre' => 'Todas las sucursales']);
} else {
    // Líder solo ve sus sucursales
    $sucursales = obtenerSucursalesLider($_SESSION['usuario_id']);
}

// Si el líder solo tiene una sucursal, seleccionarla automáticamente
if (count($sucursales) === 1 && !isset($_GET['sucursal'])) {
    $sucursalSeleccionada = $sucursales[0]['codigo'];
} else {
    $sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);
}

// Establecer rango del mes actual por defecto
$hoy = new DateTime();
$primerDiaMes = $hoy->format('Y-m-01');
$ultimoDiaMes = $hoy->format('Y-m-t');

// Obtener fechas desde los parámetros GET o usar el mes actual
$fechaDesde = $_GET['desde'] ?? $primerDiaMes;
$fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;

// Validar que las fechas no estén vacías
if (empty($fechaDesde))
    $fechaDesde = $primerDiaMes;
if (empty($fechaHasta))
    $fechaHasta = $ultimoDiaMes;

// Obtener operario seleccionado
$operarioSeleccionado = isset($_GET['operario']) ? intval($_GET['operario']) : 0;

// Filtro por tipo de ausencia
$tipoFiltro = $_GET['tipo_filtro'] ?? 'todos';

// Obtener operarios para el filtro
$operarios = obtenerOperariosFiltro();

// Determinar modo de vista basado en la selección de sucursal
$modoVista = ($sucursalSeleccionada === 'todas') ? 'todas' : 'sucursal';

// Obtener registros si hay sucursal y fechas seleccionadas
$vacaciones = [];
if (($sucursalSeleccionada || $modoVista === 'todas') && $fechaDesde && $fechaHasta) {
    $vacaciones = obtenerVacaciones(
        ($modoVista === 'todas') ? null : $sucursalSeleccionada,
        $fechaDesde,
        $fechaHasta,
        $esRH,
        $modoVista,
        $operarioSeleccionado,
        $tipoFiltro
    );
}

// Función para obtener operarios para el filtro
function obtenerOperariosFiltro()
{
    global $conn;

    $sql = "SELECT o.CodOperario, 
                   CONCAT_WS(' ',
                       NULLIF(TRIM(o.Nombre),   ''),
                       NULLIF(TRIM(o.Nombre2),  ''),
                       NULLIF(TRIM(o.Apellido), ''),
                       NULLIF(TRIM(o.Apellido2),'')
                   ) AS nombre_completo 
            FROM Operarios o
            -- WHERE o.Operativo = 1 AND
            WHERE o.CodOperario NOT IN (
                SELECT DISTINCT anc.CodOperario 
                FROM AsignacionNivelesCargos anc
                WHERE anc.CodNivelesCargos = 27
                AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            )
            GROUP BY o.CodOperario
            ORDER BY nombre_completo";

    $stmt = $conn->prepare($sql);
    $stmt->execute();

    return $stmt->fetchAll();
}

//Recortar texto
function recortarTexto($texto, $longitud = 50)
{
    if (strlen($texto) <= $longitud) {
        return $texto;
    }
    return substr($texto, 0, $longitud) . '...';
}

// Función unificada para obtener todos los tipos de ausencia de faltas_manual
function obtenerVacaciones($codSucursal, $fechaDesde, $fechaHasta, $esRH = false, $modoVista = 'sucursal', $operarioId = 0, $tipoFiltro = 'todos')
{
    global $conn;

    try {
        $sql = "
            SELECT fm.*, 
                o.Nombre AS operario_nombre, 
                o.Nombre2 AS operario_nombre2,
                o.Apellido AS operario_apellido,
                o.Apellido2 AS operario_apellido2,
                s.nombre AS sucursal_nombre,
                r.Nombre AS registrador_nombre,
                r.Apellido AS registrador_apellido,
                fm.observaciones_rrhh,
                fm.cod_contrato,
                fm.fecha_registro,
                fm.porcentaje_pago,
                tf.nombre AS tipo_falta_nombre
            FROM faltas_manual fm
            JOIN Operarios o ON fm.cod_operario = o.CodOperario
            JOIN sucursales s ON fm.cod_sucursal = s.codigo
            JOIN Operarios r ON fm.registrado_por = r.CodOperario
            LEFT JOIN tipos_falta tf ON fm.tipo_falta = tf.codigo
            WHERE fm.fecha_falta BETWEEN ? AND ?
        ";

        $params = [$fechaDesde, $fechaHasta];

        // Filtrar por tipo de ausencia
        if ($tipoFiltro === 'pendiente') {
            $sql .= " AND fm.tipo_falta = 'Pendiente'";
        } elseif ($tipoFiltro === 'vacaciones') {
            $sql .= " AND fm.tipo_falta = 'Vacaciones'";
        } elseif ($tipoFiltro === 'subsidio') {
            $sql .= " AND fm.tipo_falta IN ('Subsidio_3dias','Subsidio_INSS','Subsidio_maternidad','Reposo_hasta_3dias')";
        } elseif ($tipoFiltro === 'faltas_permisos') {
            $sql .= " AND fm.tipo_falta NOT IN ('Vacaciones','Pendiente','Subsidio_3dias','Subsidio_INSS','Subsidio_maternidad','Reposo_hasta_3dias')";
        }
        // 'todos' no agrega filtro

        if ($modoVista !== 'todas' && !empty($codSucursal) && $codSucursal !== 'todas') {
            $sql .= " AND fm.cod_sucursal = ?";
            $params[] = $codSucursal;
        }

        if ($operarioId > 0) {
            $sql .= " AND fm.cod_operario = ?";
            $params[] = $operarioId;
        }

        $sql .= " ORDER BY fm.fecha_falta DESC, o.Nombre, o.Apellido";

        $stmt = $conn->prepare($sql);
        if (!$stmt || !$stmt->execute($params)) {
            return [];
        }

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Excepción al obtener registros de ausencias: " . $e->getMessage());
        return [];
    }
}

// Función para obtener días laborables entre dos fechas (excluye fines de semana)
function obtenerDiasLaborablesEnRango($fechaInicio, $fechaFin)
{
    $dias = [];

    try {
        $fechaActual = new DateTime($fechaInicio);
        $fechaFinObj = new DateTime($fechaFin);

        while ($fechaActual <= $fechaFinObj) {
            $dias[] = $fechaActual->format('Y-m-d');
            $fechaActual->modify('+1 day');
        }
    } catch (Exception $e) {
        error_log("Error obteniendo días en rango: " . $e->getMessage());
    }

    return $dias;
}

// Procesar formulario de registro de vacaciones por rango
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_vacaciones'])) {
    procesarRegistroVacacionesRango();
}

/**
 * Procesa el registro de vacaciones por rango de fechas
 */
function procesarRegistroVacacionesRango()
{
    global $conn, $esLider, $esRH;

    // Permitir tanto a líderes como a RH registrar vacaciones
    if (!$esLider && !$esRH) {
        $_SESSION['error'] = 'Solo los líderes y RH pueden registrar vacaciones';
        header('Location: vacaciones.php');
        exit();
    }

    try {
        $codOperario = (int) $_POST['cod_operario'];
        $fechaInicio = $_POST['fecha_inicio'];
        $fechaFin = $_POST['fecha_fin'];
        $codSucursal = $_POST['cod_sucursal'];
        $observaciones = $_POST['observaciones'] ?? '';
        $tipoFalta = $_POST['tipo_falta'] ?? 'Vacaciones';

        // Validar fechas
        if (empty($fechaInicio) || empty($fechaFin)) {
            throw new Exception('Debe seleccionar ambas fechas');
        }

        if ($fechaInicio > $fechaFin) {
            throw new Exception('La fecha de inicio no puede ser mayor que la fecha fin');
        }

        // Validar que se haya subido una foto
        if (!isset($_FILES['foto_falta']) || $_FILES['foto_falta']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Debe subir una foto como evidencia');
        }

        $foto = $_FILES['foto_falta'];

        // Validar tamaño (máximo 5MB)
        if ($foto['size'] > 5 * 1024 * 1024) {
            throw new Exception('La foto no debe exceder los 5MB');
        }

        // Validar tipo de archivo
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($foto['type'], $allowedTypes)) {
            throw new Exception('Solo se permiten imágenes JPEG, PNG o GIF');
        }

        // Obtener el porcentaje de pago para el tipo de falta
        $porcentajePago = obtenerPorcentajePagoTipoFalta($tipoFalta);

        // Obtener el código de contrato
        $codContrato = null;
        $stmt_contrato = $conn->prepare("
            SELECT CodContrato 
            FROM Contratos 
            WHERE cod_operario = ? 
            ORDER BY inicio_contrato DESC, CodContrato DESC 
            LIMIT 1
        ");
        $stmt_contrato->execute([$codOperario]);
        $contrato = $stmt_contrato->fetch();
        if ($contrato) {
            $codContrato = $contrato['CodContrato'];
        }

        // Obtener todos los días laborables en el rango (excluye sábados y domingos)
        $diasLaborables = obtenerDiasLaborablesEnRango($fechaInicio, $fechaFin);

        if (empty($diasLaborables)) {
            throw new Exception('No hay días en el rango seleccionado');
        }

        // Crear nombre único para el archivo
        $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
        $nombreFoto = 'vacacion_' . $codOperario . '_' . date('YmdHis') . '.' . $extension;

        // Ruta relativa para la base de datos
        $rutaRelativa = '/uploads/faltas_manual/' . $nombreFoto;

        // Ruta absoluta para guardar el archivo
        $uploadDir = __DIR__ . '/../../uploads/faltas_manual/';

        // Crear directorio si no existe
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('No se pudo crear el directorio de uploads');
            }
        }

        // Verificar que el directorio es escribible
        if (!is_writable($uploadDir)) {
            throw new Exception('El directorio de uploads no tiene permisos de escritura');
        }

        $rutaCompleta = $uploadDir . $nombreFoto;

        // Mover el archivo subido
        if (!move_uploaded_file($foto['tmp_name'], $rutaCompleta)) {
            throw new Exception('Error al guardar la foto en el servidor. Verifique permisos.');
        }

        $registrosExitosos = 0;
        $errores = [];

        // Procesar cada día laborable
        foreach ($diasLaborables as $dia) {
            // Validar si ya existe una falta/vacación para este operario en esta fecha
            $stmt = $conn->prepare("
                SELECT id FROM faltas_manual 
                WHERE cod_operario = ? AND fecha_falta = ?
                LIMIT 1
            ");
            $stmt->execute([$codOperario, $dia]);

            if ($stmt->fetch()) {
                $errores[] = "Ya existe un registro para el día " . formatoFechaCorta($dia);
                continue; // Saltar este día
            }

            // Validar si el operario trabajó ese día (tuvo marcaciones)
            //$stmt = $conn->prepare("
            //SELECT COUNT(*) as total_marcaciones 
            //FROM marcaciones 
            //WHERE CodOperario = ? 
            //AND sucursal_codigo = ?
            //AND fecha = ?
            //AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL)
            //");
            //$stmt->execute([$codOperario, $codSucursal, $dia]);
            //$result = $stmt->fetch();

            //if ($result && $result['total_marcaciones'] > 0) {
            //$errores[] = "El colaborador trabajó el día " . formatoFechaCorta($dia) . " (tiene marcaciones)";
            //continue; // Saltar este día
            //}

            // Insertar registro de vacaciones para este día
            $stmt = $conn->prepare("
                INSERT INTO faltas_manual (
                    cod_operario, fecha_falta, cod_sucursal, 
                    tipo_falta, observaciones, observaciones_rrhh, foto_path, registrado_por, cod_contrato, porcentaje_pago
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (
                $stmt->execute([
                    $codOperario,
                    $dia,
                    $codSucursal,
                    $tipoFalta,
                    $observaciones,
                    ($esRH ? $observaciones : null), // Si es RH, guardamos lo mismo en observaciones_rrhh
                    $rutaRelativa, // Usamos la ruta relativa para la BD
                    $_SESSION['usuario_id'],
                    $codContrato,
                    $porcentajePago
                ])
            ) {
                $registrosExitosos++;
            } else {
                $errores[] = "Error al registrar vacaciones para " . formatoFechaCorta($dia);
            }
        }

        // Preparar mensaje de resultado
        if ($registrosExitosos > 0) {
            $mensaje = "Se registraron $registrosExitosos días de vacaciones correctamente";
            if (!empty($errores)) {
                $mensaje .= ". Hubo " . count($errores) . " errores: " . implode(', ', array_slice($errores, 0, 3));
                if (count($errores) > 3) {
                    $mensaje .= "... (y " . (count($errores) - 3) . " más)";
                }
            }
            $_SESSION['exito'] = $mensaje;
        } else {
            // Eliminar la foto si no se registró ningún día
            if (file_exists($rutaCompleta)) {
                @unlink($rutaCompleta);
            }
            throw new Exception("No se pudo registrar ningún día de vacaciones. Errores: " . implode(', ', $errores));
        }
    } catch (Exception $e) {
        // Eliminar la foto si hubo un error
        if (isset($rutaCompleta) && file_exists($rutaCompleta)) {
            @unlink($rutaCompleta);
        }
        $_SESSION['error'] = 'Error al registrar las vacaciones: ' . $e->getMessage();
        error_log('Error en procesarRegistroVacacionesRango: ' . $e->getMessage());
    }

    // Redirigir manteniendo los filtros
    echo '<script>window.location.href = "vacaciones.php?' .
        (isset($_GET['sucursal']) ? 'sucursal=' . urlencode($_GET['sucursal']) . '&' : '') .
        (isset($_GET['desde']) ? 'desde=' . urlencode($_GET['desde']) . '&' : '') .
        (isset($_GET['hasta']) ? 'hasta=' . urlencode($_GET['hasta']) . '&' : '') .
        (isset($_GET['operario']) && $_GET['operario'] != 0 ? 'operario=' . $_GET['operario'] : '') .
        '";</script>';
    exit();
}

// Verificar si se solicitó exportación de vacaciones
if (isset($_GET['exportar_excel'])) {
    $nombreArchivo = "vacaciones_{$fechaDesde}_a_{$fechaHasta}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
    echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
    echo '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código Contrato</th>';
    echo '<th>Colaborador</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha Vacación</th>';
    echo '<th>Observaciones</th>';
    echo '<th>Registrado por</th>';
    echo '<th>Fecha Registro</th>';
    echo '</tr>';

    foreach ($vacaciones as $vacacion) {
        echo '<tr>';
        $nombreCompleto = obtenerNombreCompletoOperario([
            'Nombre' => $vacacion['operario_nombre'],
            'Nombre2' => $vacacion['operario_nombre2'] ?? '',
            'Apellido' => $vacacion['operario_apellido'],
            'Apellido2' => $vacacion['operario_apellido2'] ?? ''
        ]);

        echo '<td>' . ($vacacion['cod_contrato'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($nombreCompleto) . '</td>';
        echo '<td>' . htmlspecialchars($vacacion['sucursal_nombre']) . '</td>';
        echo '<td>' . formatoFechaCorta($vacacion['fecha_falta']) . '</td>';
        $obsDisplay = !empty($vacacion['observaciones_rrhh']) ? $vacacion['observaciones_rrhh'] : $vacacion['observaciones'];
        echo '<td>' . ($obsDisplay ? htmlspecialchars($obsDisplay) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($vacacion['registrador_nombre'] . ' ' . $vacacion['registrador_apellido']) . '</td>';
        echo '<td>' . formatoFechaCorta($vacacion['fecha_registro']) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';
    exit;
}

/**
 * Obtiene los tipos de falta con sus porcentajes
 */
function obtenerTiposFaltaConPorcentajes()
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT codigo, nombre, porcentaje_pago, descripcion 
        FROM tipos_falta 
        WHERE activo = 1 
        ORDER BY nombre
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Vacaciones/Faltas/Subsidios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/vacaciones.css?v=<?php echo mt_rand(1, 10000); ?>">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Cámara encima de otros modales Bootstrap */
        #vacModalCamara { z-index: 1080 !important; }
    </style>

    <style>
        /* ── Cámara Premium (idéntica a reembolsos_ia_nuevo) ── */
        #vac-camera-viewport {
            position: relative;
            background: #000;
            min-height: 300px;
            cursor: crosshair;
            overflow: hidden;
        }
        #vac-video {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        #vac-focus-ring {
            position: absolute;
            width: 70px;
            height: 70px;
            border: 2px solid #FFD700;
            border-radius: 50%;
            transform: translate(-50%, -50%) scale(1.6);
            opacity: 0;
            pointer-events: none;
            transition: transform 0.25s ease, opacity 0.25s ease;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.4);
        }
        #vac-focus-ring.active  { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        #vac-focus-ring.locked  { border-color: #00FF88; opacity: 0.7; }
        #vac-focus-ring::before, #vac-focus-ring::after {
            content: ''; position: absolute;
            width: 10px; height: 10px;
            border-color: inherit; border-style: solid;
        }
        #vac-focus-ring::before { top:-1px; left:-1px; border-width:2px 0 0 2px; }
        #vac-focus-ring::after  { bottom:-1px; right:-1px; border-width:0 2px 2px 0; }
        #vac-cam-grid {
            position: absolute; inset: 0; pointer-events: none; opacity: 0.15;
            background-image: linear-gradient(to right,#fff 1px,transparent 1px), linear-gradient(to bottom,#fff 1px,transparent 1px);
            background-size: 33.33% 33.33%;
        }
        #vac-focus-toast {
            position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%);
            background: rgba(0,0,0,0.65); color: #fff; font-size: 0.75rem;
            padding: 4px 12px; border-radius: 20px; opacity: 0;
            transition: opacity 0.3s; pointer-events: none; white-space: nowrap;
        }
        .vac-cam-controls { background: #111; padding: 10px 16px 14px; }
        .vac-btn-torch {
            background: transparent; border: 1.5px solid #555; color: #aaa;
            border-radius: 50%; width: 42px; height: 42px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; transition: all 0.2s; cursor: pointer;
        }
        .vac-btn-torch.on { border-color: #FFD700; color: #FFD700; box-shadow: 0 0 8px rgba(255,215,0,0.5); }
        .vac-btn-capture {
            width: 64px; height: 64px; border-radius: 50%;
            background: #fff; border: 4px solid rgba(255,255,255,0.4);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: #333;
            transition: transform 0.1s, background 0.1s; cursor: pointer;
        }
        .vac-btn-capture:active { transform: scale(0.92); background: #ddd; }
        /* Preview de foto capturada por cámara */
        .vac-foto-preview {
            display: none;
            margin-top: 8px;
            position: relative;
        }
        .vac-foto-preview img {
            width: 100%; max-height: 160px;
            object-fit: cover; border-radius: 8px;
            border: 2px solid #0E544C;
        }
        .vac-foto-preview .vac-preview-remove {
            position: absolute; top: 4px; right: 4px;
            background: rgba(220,53,69,0.85); color:#fff;
            border: none; border-radius: 50%;
            width: 24px; height: 24px; font-size: 0.75rem;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Registro de Vacaciones/Faltas/Subsidios'); ?>

            <div class="container-fluid p-3">
                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= $_SESSION['exito'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['exito']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= $_SESSION['error'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Filtros -->
                <div class="filtros-container">
                    <form method="get" action="vacaciones.php" class="filtros-form">
                        <?php if (tienePermiso('registro_vacaciones', 'ver_filtro_sucursal', $cargoOperario)): ?>
                            <div class="filtro-group">
                                <label for="sucursal">Sucursal</label>
                                <select id="sucursal" name="sucursal">
                                    <?php foreach ($sucursales as $sucursal): ?>
                                        <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sucursal['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="filtro-group">
                            <label for="operario">Colaborador</label>
                            <input type="text" id="operario" name="operario" placeholder="Escriba para buscar..." value="<?php
                                                                                                                            if ($operarioSeleccionado > 0) {
                                                                                                                                foreach ($operarios as $op) {
                                                                                                                                    if ($op['CodOperario'] == $operarioSeleccionado) {
                                                                                                                                        echo htmlspecialchars($op['nombre_completo']);
                                                                                                                                        break;
                                                                                                                                    }
                                                                                                                                }
                                                                                                                            } else {
                                                                                                                                echo 'Todos los colaboradores';
                                                                                                                            }
                                                                                                                            ?>">
                            <input type="hidden" id="operario_id" name="operario"
                                value="<?php echo $operarioSeleccionado; ?>">
                            <div id="operarios-sugerencias" style="display: none;"></div>
                        </div>

                        <div class="filtro-group">
                            <label for="desde">Desde</label>
                            <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($fechaDesde) ?>">
                        </div>

                        <div class="filtro-group">
                            <label for="hasta">Hasta</label>
                            <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
                        </div>

                        <div class="filtro-group">
                            <label for="tipo_filtro">Tipo de Ausencia</label>
                            <select id="tipo_filtro" name="tipo_filtro">
                                <option value="todos" <?= $tipoFiltro === 'todos' ? 'selected' : '' ?>>Todos</option>
                                <option value="pendiente" <?= $tipoFiltro === 'pendiente' ? 'selected' : '' ?>>Pendientes</option>
                                <option value="vacaciones" <?= $tipoFiltro === 'vacaciones' ? 'selected' : '' ?>>Vacaciones</option>
                                <option value="subsidio" <?= $tipoFiltro === 'subsidio' ? 'selected' : '' ?>>Subsidios</option>
                                <option value="faltas_permisos" <?= $tipoFiltro === 'faltas_permisos' ? 'selected' : '' ?>>Faltas y Permisos</option>
                            </select>
                        </div>

                        <div class="filtro-buttons">
                            <button type="submit" class="btn-aplicar">
                                <i class="fas fa-search"></i> Buscar
                            </button>

                            <button type="button" class="btn-aplicar" data-bs-toggle="modal" data-bs-target="#modalTipoBoleta">
                                <i class="fas fa-print"></i> Imprimir Boleta
                            </button>

                            <?php if (tienePermiso('registro_vacaciones', 'exportar_excel', $cargoOperario)): ?>
                                <a href="vacaciones.php?<?= http_build_query([
                                                            'sucursal' => $sucursalSeleccionada ?? '',
                                                            'desde' => $fechaDesde,
                                                            'hasta' => $fechaHasta,
                                                            'operario' => $operarioSeleccionado,
                                                            'tipo_filtro' => $tipoFiltro,
                                                            'exportar_excel' => 1
                                                        ]) ?>" class="btn-agregar">
                                    <i class="fas fa-file-excel"></i> Exportar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <?php
                // Helper: badge CSS class based on tipo_falta
                function getBadgeClass($tipo)
                {
                    if ($tipo === 'Pendiente') return 'badge-status badge-pendiente';
                    if ($tipo === 'Vacaciones') return 'badge-status badge-vacaciones';
                    if (str_starts_with($tipo, 'Subsidio') || str_starts_with($tipo, 'Reposo')) return 'badge-status badge-subsidio';
                    if (str_starts_with($tipo, 'No_Pagado')) return 'badge-status badge-nopagado';
                    if (str_starts_with($tipo, 'Compensacion') || str_starts_with($tipo, 'Dia_mas')) return 'badge-status badge-compensacion';
                    return 'badge-status badge-permiso';
                }
                ?>
                <div class="table-container">
                    <?php if (!empty($vacaciones)): ?>
                        <table id="listaVacaciones">
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Sucursal</th>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>% Pago</th>
                                    <th>Días</th>
                                    <th>Observaciones</th>
                                    <?php if ($puedeAprobar): ?><th>Obs. RRHH</th><?php endif; ?>
                                    <th>Registrado por</th>
                                    <th>Foto</th>
                                    <?php if ($puedeAprobar): ?><th>Acciones</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vacaciones as $vacacion): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(trim($vacacion['operario_nombre'] . ' ' . ($vacacion['operario_nombre2'] ?? '') . ' ' . $vacacion['operario_apellido'] . ' ' . ($vacacion['operario_apellido2'] ?? ''))) ?></td>
                                        <td><?= htmlspecialchars($vacacion['sucursal_nombre']) ?></td>
                                        <td><?= formatoFechaCorta($vacacion['fecha_falta']) ?></td>
                                        <td>
                                            <span class="<?= getBadgeClass($vacacion['tipo_falta']) ?>">
                                                <?= htmlspecialchars($vacacion['tipo_falta_nombre'] ?? str_replace('_', ' ', $vacacion['tipo_falta'])) ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;"><?= number_format($vacacion['porcentaje_pago'] ?? 0, 0) ?>%</td>
                                        <td style="text-align:center;">
                                            <?php
                                            $cantDias = isset($vacacion['cantidad_dias']) ? (float)$vacacion['cantidad_dias'] : 1.0;
                                            echo number_format($cantDias, 2);
                                            ?>
                                        </td>
                                        <td title="<?= htmlspecialchars($vacacion['observaciones'] ?: '-') ?>">
                                            <?= $vacacion['observaciones'] ? htmlspecialchars(recortarTexto($vacacion['observaciones'], 25)) : '-' ?>
                                        </td>
                                        <?php if ($puedeAprobar): ?>
                                            <td title="<?= htmlspecialchars($vacacion['observaciones_rrhh'] ?: '-') ?>">
                                                <?= $vacacion['observaciones_rrhh'] ? htmlspecialchars(recortarTexto($vacacion['observaciones_rrhh'], 25)) : '<span class="text-muted small">-</span>' ?>
                                            </td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($vacacion['registrador_nombre'] . ' ' . $vacacion['registrador_apellido']) ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($vacacion['foto_path']): ?>
                                                <button type="button" onclick="mostrarFoto('<?= htmlspecialchars($vacacion['foto_path']) ?>')" class="btn btn-sm btn-foto">
                                                    <i class="fas fa-camera" style="color: #51B8AC; font-size: 18px;"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($puedeAprobar): ?>
                                            <td>
                                                <div class="action-buttons-cell">
                                                    <button type="button" class="btn-action-table btn-action-edit"
                                                        onclick="mostrarModalEditarAprobar(
                                                        <?= $vacacion['id'] ?>,
                                                        '<?= htmlspecialchars(addslashes(trim($vacacion['operario_nombre'] . ' ' . $vacacion['operario_apellido']))) ?>',
                                                        '<?= htmlspecialchars(addslashes($vacacion['sucursal_nombre'])) ?>',
                                                        '<?= $vacacion['fecha_falta'] ?>',
                                                        '<?= $vacacion['tipo_falta'] ?>',
                                                        '<?= htmlspecialchars(addslashes($vacacion['observaciones'] ?? '')) ?>',
                                                        '<?= htmlspecialchars(addslashes($vacacion['observaciones_rrhh'] ?? '')) ?>',
                                                        '<?= htmlspecialchars($vacacion['foto_path'] ?? '') ?>',
                                                        <?= number_format(isset($vacacion['cantidad_dias']) ? (float)$vacacion['cantidad_dias'] : 1.0, 2) ?>
                                                    )">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button style="display: none;" type="button" class="btn-action-table btn-action-delete"
                                                        onclick="eliminarSolicitud(<?= $vacacion['id'] ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <?php if (($sucursalSeleccionada || $modoVista === 'todas') && $fechaDesde && $fechaHasta): ?>
                                No se encontraron registros para los filtros seleccionados.
                            <?php else: ?>
                                Seleccione una sucursal y rango de fechas para buscar registros.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Barra de paginación (idéntica a cupones) -->
                    <?php if (!empty($vacaciones)): ?>
                        <div class="paginacion-toolbar">
                            <div class="d-flex align-items-center gap-2">
                                <label class="mb-0 small">Mostrar:</label>
                                <select class="form-select form-select-sm" id="registrosPorPaginaVac" style="width: auto;" onchange="vacPaginaActual=1; vacRenderizar();">
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span class="mb-0 small text-muted" id="vacInfoRegistros"></span>
                            </div>
                            <div id="paginacion"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para nuevo subsidio por rango -->
    <div class="modal fade" id="modalNuevoSubsidio" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow" style="border-radius: 8px;">
                <div class="modal-header border-0 py-3 px-3" style="background: #0E544C; color: #fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <i class="fas fa-notes-medical fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Registrar Subsidio</h5>
                            <p class="small mb-0 opacity-75">Registro de subsidio por rango de fechas</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 bg-light">
                    <form id="formNuevoSubsidio" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="registrar_vacaciones" value="1">

                        <div class="mb-3">
                            <label for="subsidio_sucursal" class="form-label small fw-bold text-muted text-uppercase">Sucursal:</label>
                            <select id="subsidio_sucursal" name="cod_sucursal" class="form-select" required>
                                <?php if ($esRH): ?>
                                    <?php foreach (obtenerTodasSucursales() as $sucursal): ?>
                                        <option value="<?= $sucursal['codigo'] ?>">
                                            <?= htmlspecialchars($sucursal['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach (obtenerSucursalesLider($_SESSION['usuario_id']) as $sucursal): ?>
                                        <option value="<?= $sucursal['codigo'] ?>">
                                            <?= htmlspecialchars($sucursal['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="subsidio_fecha_inicio" class="form-label small fw-bold text-muted text-uppercase">Fecha Inicio:</label>
                                <input type="date" id="subsidio_fecha_inicio" name="fecha_inicio" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="subsidio_fecha_fin" class="form-label small fw-bold text-muted text-uppercase">Fecha Fin:</label>
                                <input type="date" id="subsidio_fecha_fin" name="fecha_fin" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="subsidio_operario" class="form-label small fw-bold text-muted text-uppercase">Colaborador:</label>
                            <select id="subsidio_operario" name="cod_operario" class="form-select" required>
                                <option value="">Seleccione un colaborador</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="subsidio_tipo" class="form-label small fw-bold text-muted text-uppercase"<?php if (!$puedeAprobar): ?> style="display:none;"<?php endif; ?>>Tipo de Subsidio:</label>
                            <?php if ($puedeAprobar): ?>
                                <select id="subsidio_tipo" name="tipo_falta" class="form-select" required onchange="actualizarPorcentajeSubsidio(this.value)">
                                    <?php
                                    $tiposSubsidio = ['Subsidio_3dias', 'Subsidio_INSS', 'Subsidio_maternidad', 'Reposo_hasta_3dias', 'Cuido_materno'];
                                    foreach (obtenerTiposFaltaConPorcentajes() as $tipo):
                                        if (in_array($tipo['codigo'], $tiposSubsidio)):
                                            $pct = $tipo['porcentaje_pago'];
                                            $label = $tipo['nombre'] . ' (Paga ' . $pct . '%)';
                                    ?>
                                            <option value="<?= $tipo['codigo'] ?>" data-porcentaje="<?= $pct ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endif;
                                    endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control bg-light" value="Pendiente de Revisión por RRHH" readonly style="display:none;">
                                <input type="hidden" id="subsidio_tipo" name="tipo_falta" value="Pendiente">
                                <small class="form-text text-muted" style="display:none;"><i class="fas fa-info-circle me-1"></i>El tipo será definido por Recursos Humanos al revisar el registro.</small>
                            <?php endif; ?>
                            <small id="info-porcentaje-subsidio" class="form-text text-muted mt-1 d-block"></small>
                        </div>

                        <?php if ($puedeAprobar): ?>
                            <div class="mb-3">
                                <label for="subsidio_cantidad_dias" class="form-label small fw-bold text-muted text-uppercase">Duración de la Ausencia:</label>
                                <select id="subsidio_cantidad_dias" name="cantidad_dias" class="form-select" onchange="manejarCantidadDias(this, 'subsidio_custom_dias')">
                                    <!-- 1.00 = 8 horas -->
                                    <option value="1.00">Día completo (1 día - 8 horas)</option>
                                    <!-- 0.88 = 7 horas -->
                                    <option value="0.88">Casi el día completo (0.88 días - 7 horas)</option>
                                    <!-- 0.75 = 6 horas -->
                                    <option value="0.75">Tres cuartos de jornada (0.75 días - 6 horas)</option>
                                    <!-- 0.63 = 5 horas -->
                                    <option value="0.63">Más de medio día (0.63 días - 5 horas)</option>
                                    <!-- 0.50 = 4 horas -->
                                    <option value="0.50">Medio día (0.50 días - 4 horas)</option>
                                    <!-- 0.38 = 3 horas -->
                                    <option value="0.38">Menos de medio día (0.38 días - 3 horas)</option>
                                    <!-- 0.25 = 2 horas -->
                                    <option value="0.25">Dos horas (0.25 días - 2 horas)</option>
                                    <!-- 0.13 = 1 hora -->
                                    <option value="0.13">Una hora (0.13 días - 1 hora)</option>
                                    <option value="custom">Personalizado...</option>
                                </select>
                                <div id="subsidio_custom_dias" class="mt-2" style="display:none;">
                                    <input type="number" id="subsidio_custom_input" class="form-control" placeholder="Ingrese fracción de día (ej. 0.60)" min="0.01" max="1.00" step="0.01"
                                        oninput="actualizarCantidadPersonalizada(this, 'subsidio_cantidad_dias')">
                                    <small class="form-text text-muted">Ingresa un valor entre 0.01 y 1.00 (equivale a horas ÷ 8)</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="cantidad_dias" value="1.00">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="subsidio_observaciones" class="form-label small fw-bold text-muted text-uppercase">Observaciones:</label>
                            <textarea id="subsidio_observaciones" name="observaciones" class="form-control" rows="2" style="resize: none;"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Foto de Evidencia (Obligatoria):</label>
                            <div class="input-group">
                                <input type="file" id="subsidio_foto" name="foto_falta" class="form-control" accept="image/*" required>
                                <button type="button" class="btn btn-success" onclick="vacAbrirCamara('formNuevoSubsidio')" title="Tomar foto con cámara">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Selecciona una imagen o usa la cámara (máx. 5MB)</small>
                            <div class="vac-foto-preview" id="subsidio_preview">
                                <img id="subsidio_preview_img" src="" alt="Vista previa">
                                <button type="button" class="vac-preview-remove" onclick="vacEliminarPreview('formNuevoSubsidio')" title="Eliminar foto">&times;</button>
                            </div>
                        </div>

                        <div id="info-rango-subsidio" class="alert alert-info py-2" style="display: none;">
                            <p class="mb-1"><strong>Resumen del rango seleccionado:</strong></p>
                            <p class="mb-0 small" id="info-dias-totales-subsidio">Días totales en rango: 0</p>
                            <p class="mb-0 small fw-bold" id="info-dias-subsidio">Días a registrar como subsidio: 0</p>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 bg-white d-flex justify-content-between">
                    <button type="button" class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formNuevoSubsidio" class="btn-modern btn-modern-primary">
                        <i class="fas fa-save me-2"></i>Registrar Subsidio
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para nueva vacación por rango -->
    <div class="modal fade" id="modalNuevaVacacion" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow" style="border-radius: 8px;">
                <div class="modal-header border-0 py-3 px-3" style="background: #0E544C; color: #fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <i class="fas fa-umbrella-beach fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Registrar Vacaciones</h5>
                            <p class="small mb-0 opacity-75">Registro de vacaciones por rango de fechas</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 bg-light">
                    <form id="formNuevaVacacion" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="registrar_vacaciones" value="1">

                        <div class="mb-3">
                            <label for="nueva_sucursal" class="form-label small fw-bold text-muted text-uppercase">Sucursal:</label>
                            <select id="nueva_sucursal" name="cod_sucursal" class="form-select" required>
                                <?php if ($esRH): ?>
                                    <?php foreach (obtenerTodasSucursales() as $sucursal): ?>
                                        <option value="<?= $sucursal['codigo'] ?>">
                                            <?= htmlspecialchars($sucursal['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach (obtenerSucursalesLider($_SESSION['usuario_id']) as $sucursal): ?>
                                        <option value="<?= $sucursal['codigo'] ?>">
                                            <?= htmlspecialchars($sucursal['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nueva_fecha_inicio" class="form-label small fw-bold text-muted text-uppercase">Fecha Inicio:</label>
                                <input type="date" id="nueva_fecha_inicio" name="fecha_inicio" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nueva_fecha_fin" class="form-label small fw-bold text-muted text-uppercase">Fecha Fin:</label>
                                <input type="date" id="nueva_fecha_fin" name="fecha_fin" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nueva_operario" class="form-label small fw-bold text-muted text-uppercase">Colaborador:</label>
                            <select id="nueva_operario" name="cod_operario" class="form-select" required>
                                <option value="">Seleccione un colaborador</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="nueva_tipo" class="form-label small fw-bold text-muted text-uppercase"<?php if (!$puedeAprobar): ?> style="display:none;"<?php endif; ?>>Tipo:</label>
                            <?php if ($puedeAprobar): ?>
                                <select id="nueva_tipo" name="tipo_falta" class="form-select" required onchange="actualizarPorcentajeVacaciones(this.value)">
                                    <?php
                                    $tiposFalta = obtenerTiposFaltaConPorcentajes();
                                    foreach ($tiposFalta as $tipo):
                                        if ($tipo['codigo'] === 'Vacaciones'):
                                            $porcentajeTexto = ($tipo['porcentaje_pago'] == -100) ? 'Deducción 100%' : 'Paga ' . $tipo['porcentaje_pago'] . '%';
                                    ?>
                                            <option value="<?= $tipo['codigo'] ?>" data-porcentaje="<?= $tipo['porcentaje_pago'] ?>" selected>
                                                <?= htmlspecialchars($tipo['nombre']) ?> (<?= $porcentajeTexto ?>)
                                            </option>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </select>
                                <small id="info-porcentaje-vacaciones" class="form-text text-muted mt-1 d-block" style="display: none;"></small>
                            <?php else: ?>
                                <input type="text" class="form-control bg-light" value="Pendiente de Revisión por RRHH" readonly style="display:none;">
                                <input type="hidden" id="nueva_tipo" name="tipo_falta" value="Pendiente">
                                <small class="form-text text-muted" style="display:none;"><i class="fas fa-info-circle me-1"></i>El tipo será definido por Recursos Humanos al revisar el registro.</small>
                            <?php endif; ?>
                        </div>

                        <?php if ($puedeAprobar): ?>
                            <div class="mb-3">
                                <label for="nueva_cantidad_dias" class="form-label small fw-bold text-muted text-uppercase">Duración de la Ausencia:</label>
                                <select id="nueva_cantidad_dias" name="cantidad_dias" class="form-select" onchange="manejarCantidadDias(this, 'nueva_custom_dias')">
                                    <!-- 1.00 = 8 horas -->
                                    <option value="1.00">Día completo (1 día - 8 horas)</option>
                                    <!-- 0.88 = 7 horas -->
                                    <option value="0.88">Casi el día completo (0.88 días - 7 horas)</option>
                                    <!-- 0.75 = 6 horas -->
                                    <option value="0.75">Tres cuartos de jornada (0.75 días - 6 horas)</option>
                                    <!-- 0.63 = 5 horas -->
                                    <option value="0.63">Más de medio día (0.63 días - 5 horas)</option>
                                    <!-- 0.50 = 4 horas -->
                                    <option value="0.50">Medio día (0.50 días - 4 horas)</option>
                                    <!-- 0.38 = 3 horas -->
                                    <option value="0.38">Menos de medio día (0.38 días - 3 horas)</option>
                                    <!-- 0.25 = 2 horas -->
                                    <option value="0.25">Dos horas (0.25 días - 2 horas)</option>
                                    <!-- 0.13 = 1 hora -->
                                    <option value="0.13">Una hora (0.13 días - 1 hora)</option>
                                    <option value="custom">Personalizado...</option>
                                </select>
                                <div id="nueva_custom_dias" class="mt-2" style="display:none;">
                                    <input type="number" id="nueva_custom_input" class="form-control" placeholder="Ingrese fracción de día (ej. 0.60)" min="0.01" max="1.00" step="0.01"
                                        oninput="actualizarCantidadPersonalizada(this, 'nueva_cantidad_dias')">
                                    <small class="form-text text-muted">Ingresa un valor entre 0.01 y 1.00 (equivale a horas ÷ 8)</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="cantidad_dias" value="1.00">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="nueva_observaciones" class="form-label small fw-bold text-muted text-uppercase">Observaciones:</label>
                            <textarea id="nueva_observaciones" name="observaciones" class="form-control" rows="2" style="resize: none;"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Foto de Evidencia (Obligatoria):</label>
                            <div class="input-group">
                                <input type="file" id="nueva_foto" name="foto_falta" class="form-control" accept="image/*" required>
                                <button type="button" class="btn btn-success" onclick="vacAbrirCamara('formNuevaVacacion')" title="Tomar foto con cámara">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Selecciona una imagen o usa la cámara (máx. 5MB)</small>
                            <div class="vac-foto-preview" id="vacacion_preview">
                                <img id="vacacion_preview_img" src="" alt="Vista previa">
                                <button type="button" class="vac-preview-remove" onclick="vacEliminarPreview('formNuevaVacacion')" title="Eliminar foto">&times;</button>
                            </div>
                        </div>

                        <div id="info-rango" class="alert alert-info py-2" style="display: none;">
                            <p class="mb-1"><strong>Resumen del rango seleccionado:</strong></p>
                            <p class="mb-0 small" id="info-dias-totales">Días totales en rango: 0</p>
                            <p id="info-dias-laborables" style="display:none;">Días laborables (L-V): 0</p>
                            <p class="mb-0 small fw-bold" id="info-vacaciones">Días a registrar como vacaciones: 0</p>
                            <p style="display:none;"><small><i>Nota: Se excluyen sábados y domingos</i></small></p>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 bg-white d-flex justify-content-between flex-wrap gap-2">
                    <button type="button" class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn-modern" style="background:#17a2b8;color:#fff;" onclick="imprimirBoletaVacacionesV2()">
                        <i class="fas fa-print me-2"></i>Imprimir Boleta
                    </button>
                    <button type="submit" form="formNuevaVacacion" class="btn-modern btn-modern-primary">
                        <i class="fas fa-save me-2"></i>Registrar Vacaciones
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // CONFIG de datos para vacaciones.js
        window.CONFIG_VACACIONES = {
            operariosData: [{
                    id: 0,
                    nombre: 'Todos los colaboradores'
                },
                <?php foreach ($operarios as $op): ?> {
                        id: <?php echo $op['CodOperario']; ?>,
                        nombre: '<?php echo addslashes($op['nombre_completo']); ?>'
                    },
                <?php endforeach; ?>
            ],
            puedeAprobar: <?= $puedeAprobar ? 'true' : 'false' ?>,
            esRH: <?= $esRH ? 'true' : 'false' ?>,
            jefeNombre: '<?= addslashes(trim(($usuario['Nombre'] ?? '') . ' ' . ($usuario['Apellido'] ?? ''))) ?>'
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/vacaciones.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

    <!-- ===================== MODAL CÁMARA PREMIUM (vacaciones) ===================== -->
    <div class="modal fade" id="vacModalCamara" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px !important; overflow: hidden;">

                <!-- Header -->
                <div class="modal-header bg-dark text-white border-0 py-2 px-3">
                    <h6 class="modal-title mb-0"><i class="fas fa-camera me-2"></i> Tomar Evidencia</h6>
                    <div class="d-flex align-items-center gap-2">
                        <span id="vac-cam-focus-status" class="badge bg-secondary" style="font-size:0.65rem;">AUTO</span>
                        <button type="button" class="btn-close btn-close-white" onclick="vacCerrarCamara()"></button>
                    </div>
                </div>

                <!-- Viewport -->
                <div id="vac-camera-viewport" style="min-height: 320px;">
                    <video id="vac-video" autoplay playsinline muted></video>
                    <div id="vac-cam-grid"></div>
                    <div id="vac-focus-ring"></div>
                    <div id="vac-focus-toast">Toca para enfocar</div>
                    <canvas id="vac-canvas" style="display:none;"></canvas>
                </div>

                <!-- Controles -->
                <div class="vac-cam-controls">
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <!-- Cancelar -->
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3 text-white border-secondary" onclick="vacCerrarCamara()">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <!-- Capturar -->
                        <button type="button" class="vac-btn-capture" onclick="vacCapturarFoto()" title="Tomar foto">
                            <i class="fas fa-circle" style="color:#e74c3c;"></i>
                        </button>
                        <!-- Linterna -->
                        <button type="button" id="vac-btnTorch" class="vac-btn-torch" onclick="vacToggleLinterna()" title="Linterna" style="display:none;">
                            <i class="fas fa-bolt"></i>
                        </button>
                        <div id="vac-btnTorchPlaceholder" style="width:42px;"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <!-- ===================== MODAL AYUDA ===================== -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow" style="border-radius: 8px;">
                <div class="modal-header border-0 py-3 px-3" style="background: #0E544C; color: #fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-question fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Ayuda — Registro de Ausencias</h5>
                            <p class="small mb-0 opacity-75">Guía rápida de uso del módulo</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 bg-light">
                    <h6 class="fw-bold text-success"><i class="fas fa-info-circle me-2"></i>¿Para qué sirve este módulo?</h6>
                    <p>Este módulo permite registrar, visualizar y exportar las ausencias del personal: <strong>Vacaciones</strong>, <strong>Subsidios</strong> y <strong>Faltas/Permisos</strong>, ya sea para aplicarlos o deducirlos del cálculo de nómina.</p>
                    <h6 class="fw-bold text-success mt-4"><i class="fas fa-umbrella-beach me-2"></i>Vacaciones</h6>
                    <ul class="mb-0">
                        <li>Permite registrar vacaciones seleccionando un rango de fechas.</li>
                        <li>Si eres líder, la solicitud queda como <strong>Pendiente</strong> hasta ser aprobada por RRHH.</li>
                    </ul>
                    <h6 class="fw-bold text-success mt-4"><i class="fas fa-notes-medical me-2"></i>Subsidios</h6>
                    <ul class="mb-0">
                        <li><strong>Subsidio 3 días:</strong> Asume el pago al 100% por cuenta de la empresa.</li>
                        <li><strong>Subsidio INSS:</strong> Asume el 0% por cuenta de la empresa (lo cubre el INSS).</li>
                    </ul>
                    <h6 class="fw-bold text-success mt-4"><i class="fas fa-exclamation-triangle me-2"></i>Faltas y Permisos</h6>
                    <ul class="mb-0">
                        <li>Solo se permiten fechas anteriores al día actual (no futuras).</li>
                        <li>El sistema verifica que no existan marcaciones el día seleccionado.</li>
                    </ul>
                    <h6 class="fw-bold text-success mt-4"><i class="fas fa-camera me-2"></i>Evidencias</h6>
                    <p class="mb-0">Todo registro requiere de manera <strong>obligatoria</strong> adjuntar una foto de evidencia (máx. 5MB).</p>
                </div>
                <div class="modal-footer border-0 p-3 bg-white d-flex justify-content-end">
                    <button type="button" class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== MODAL NUEVA FALTA/PERMISO ===================== -->
    <div class="modal fade" id="modalNuevaFalta" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow" style="border-radius: 8px;">
                <div class="modal-header border-0 py-3 px-3" style="background: #0E544C; color: #fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-exclamation-triangle fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Registrar Falta o Permiso</h5>
                            <p class="small mb-0 opacity-75">Registro por rango de fechas (no futuras)</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 bg-light">
                    <form id="formNuevaFalta" enctype="multipart/form-data">
                        <input type="hidden" name="categoria_falta" value="falta_permiso">

                        <div class="mb-3">
                            <label for="falta_sucursal" class="form-label small fw-bold text-muted text-uppercase">Sucursal:</label>
                            <select id="falta_sucursal" name="cod_sucursal" class="form-select" required
                                onchange="cargarOperariosSucursal(this.value, 'falta_operario')">
                                <?php if ($esRH): ?>
                                    <?php foreach (obtenerTodasSucursales() as $sucursal): ?>
                                        <option value="<?= $sucursal['codigo'] ?>"><?= htmlspecialchars($sucursal['nombre']) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach (obtenerSucursalesLider($_SESSION['usuario_id']) as $sucursal): ?>
                                        <option value="<?= $sucursal['codigo'] ?>"><?= htmlspecialchars($sucursal['nombre']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="falta_fecha_inicio" class="form-label small fw-bold text-muted text-uppercase">Fecha Inicio:</label>
                                <input type="date" id="falta_fecha_inicio" name="fecha_inicio" class="form-control" required
                                    onchange="actualizarInfoRangoFaltaPermiso()">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="falta_fecha_fin" class="form-label small fw-bold text-muted text-uppercase">Fecha Fin:</label>
                                <input type="date" id="falta_fecha_fin" name="fecha_fin" class="form-control" required
                                    onchange="actualizarInfoRangoFaltaPermiso()">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="falta_operario" class="form-label small fw-bold text-muted text-uppercase">Colaborador:</label>
                            <select id="falta_operario" name="cod_operario" class="form-select" required>
                                <option value="">Seleccione un colaborador</option>
                            </select>
                        </div>

                        <?php if ($puedeAprobar): ?>
                            <div class="mb-3">
                                <label for="falta_tipo" class="form-label small fw-bold text-muted text-uppercase">Tipo de Falta/Permiso:</label>
                                <select id="falta_tipo" name="tipo_falta" class="form-select" required onchange="actualizarPorcentajeFaltaPermiso(this.value)">
                                    <?php
                                    $tiposExcluidos = ['Vacaciones', 'Pendiente', 'Subsidio_3dias', 'Subsidio_INSS', 'Subsidio_maternidad', 'Reposo_hasta_3dias', 'Cuido_materno'];
                                    foreach (obtenerTiposFaltaConPorcentajes() as $tipo):
                                        if (!in_array($tipo['codigo'], $tiposExcluidos)):
                                            $pct = $tipo['porcentaje_pago'];
                                    ?>
                                            <option value="<?= $tipo['codigo'] ?>" data-porcentaje="<?= $pct ?>">
                                                <?= htmlspecialchars($tipo['nombre']) ?> (Paga <?= $pct ?>%)
                                            </option>
                                    <?php endif;
                                    endforeach; ?>
                                </select>
                                <small id="info-porcentaje-falta" class="form-text text-muted mt-1 d-block"></small>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase" style="display:none;">Tipo de Falta/Permiso:</label>
                                <input type="text" class="form-control bg-light" value="Pendiente de Revisión por RRHH" readonly style="display:none;">
                                <input type="hidden" id="falta_tipo" name="tipo_falta" value="Pendiente">
                                <small id="info-porcentaje-falta" class="form-text text-muted mt-1 d-block" style="display:none;">
                                    ℹ️ El tipo de ausencia será determinado y clasificado por Recursos Humanos.
                                </small>
                            </div>
                        <?php endif; ?>

                        <?php if ($puedeAprobar): ?>
                            <div class="mb-3">
                                <label for="falta_cantidad_dias" class="form-label small fw-bold text-muted text-uppercase">Duración de la Ausencia:</label>
                                <select id="falta_cantidad_dias" name="cantidad_dias" class="form-select" onchange="manejarCantidadDias(this, 'falta_custom_dias')">
                                    <!-- 1.00 = 8 horas -->
                                    <option value="1.00">Día completo (1 día - 8 horas)</option>
                                    <!-- 0.88 = 7 horas -->
                                    <option value="0.88">Casi el día completo (0.88 días - 7 horas)</option>
                                    <!-- 0.75 = 6 horas -->
                                    <option value="0.75">Tres cuartos de jornada (0.75 días - 6 horas)</option>
                                    <!-- 0.63 = 5 horas -->
                                    <option value="0.63">Más de medio día (0.63 días - 5 horas)</option>
                                    <!-- 0.50 = 4 horas -->
                                    <option value="0.50">Medio día (0.50 días - 4 horas)</option>
                                    <!-- 0.38 = 3 horas -->
                                    <option value="0.38">Menos de medio día (0.38 días - 3 horas)</option>
                                    <!-- 0.25 = 2 horas -->
                                    <option value="0.25">Dos horas (0.25 días - 2 horas)</option>
                                    <!-- 0.13 = 1 hora -->
                                    <option value="0.13">Una hora (0.13 días - 1 hora)</option>
                                    <option value="custom">Personalizado...</option>
                                </select>
                                <div id="falta_custom_dias" class="mt-2" style="display:none;">
                                    <input type="number" id="falta_custom_input" class="form-control" placeholder="Ingrese fracción de día (ej. 0.60)" min="0.01" max="1.00" step="0.01"
                                        oninput="actualizarCantidadPersonalizada(this, 'falta_cantidad_dias')">
                                    <small class="form-text text-muted">Ingresa un valor entre 0.01 y 1.00 (equivale a horas ÷ 8)</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="cantidad_dias" value="1.00">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="falta_observaciones" class="form-label small fw-bold text-muted text-uppercase">Observaciones:</label>
                            <textarea id="falta_observaciones" name="observaciones" class="form-control" rows="2" style="resize: none;"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Foto de Evidencia (Obligatoria):</label>
                            <div class="input-group">
                                <input type="file" id="falta_foto" name="foto_falta" class="form-control" accept="image/*" required>
                                <button type="button" class="btn btn-success" onclick="vacAbrirCamara('formNuevaFalta')" title="Tomar foto con cámara">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Selecciona una imagen o usa la cámara (máx. 5MB)</small>
                            <div class="vac-foto-preview" id="falta_preview">
                                <img id="falta_preview_img" src="" alt="Vista previa">
                                <button type="button" class="vac-preview-remove" onclick="vacEliminarPreview('formNuevaFalta')" title="Eliminar foto">&times;</button>
                            </div>
                        </div>

                        <div id="info-rango-falta" class="alert alert-info py-2" style="display: none;">
                            <p class="mb-1"><strong>Resumen del rango seleccionado:</strong></p>
                            <p class="mb-0 small" id="info-dias-totales-falta">Días totales en rango: 0</p>
                            <p class="mb-0 small fw-bold" id="info-dias-falta">Días a registrar: 0</p>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 p-3 bg-white d-flex justify-content-between flex-nowrap">
                    <button type="button" class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formNuevaFalta" class="btn-modern btn-modern-primary">
                        <i class="fas fa-save me-2"></i>Registrar Falta/Permiso
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($puedeAprobar): ?>
        <!-- ===================== MODAL EDITAR/APROBAR (RRHH) ===================== -->
        <div class="modal fade" id="modalEditarFalta" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow" style="border-radius: 8px;">
                    <div class="modal-header border-0 py-3 px-3" style="background: #0E544C; color: #fff;">
                        <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-check-circle fs-4"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0">Editar / Aprobar Solicitud</h5>
                                <p class="small mb-0 opacity-75">Modificación por parte de RRHH</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3 bg-light">
                        <form id="formEditarFalta">
                            <input type="hidden" id="editar_id" name="id">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Colaborador:</label>
                                <p class="form-control-plaintext fw-bold" id="editar_nombre">-</p>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Sucursal:</label>
                                    <p class="form-control-plaintext" id="editar_sucursal">-</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold text-muted text-uppercase">Fecha:</label>
                                    <p class="form-control-plaintext" id="editar_fecha">-</p>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Observaciones del líder:</label>
                                <p class="form-control-plaintext text-muted small" id="editar_observaciones_lider">-</p>
                            </div>

                            <div id="preview-container" class="mb-3" style="display: none;">
                                <label class="form-label small fw-bold text-muted text-uppercase">Foto de evidencia:</label>
                                <div>
                                    <img id="preview-image" src="" alt="Foto evidencia" style="max-height: 120px; border-radius: 8px; cursor: pointer;"
                                        onclick="ampliarImagen(this.src)">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="editar_tipo" class="form-label small fw-bold text-muted text-uppercase">Tipo de Falta (Aprobación):</label>
                                <select id="editar_tipo" name="tipo_falta" class="form-select" required onchange="actualizarPorcentajeEdicion(this.value)">
                                    <?php foreach (obtenerTiposFaltaConPorcentajes() as $tipo):
                                        if ($tipo['codigo'] === 'Pendiente') continue;
                                        $pct = $tipo['porcentaje_pago'];
                                    ?>
                                        <option value="<?= $tipo['codigo'] ?>" data-porcentaje="<?= $pct ?>">
                                            <?= htmlspecialchars($tipo['nombre']) ?> (<?= $pct ?>%)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="info-porcentaje-edicion" class="form-text mt-1 d-block"></small>
                            </div>

                            <div class="mb-3">
                                <label for="editar_cantidad_dias" class="form-label small fw-bold text-muted text-uppercase">Duración de la Ausencia:</label>
                                <select id="editar_cantidad_dias" name="cantidad_dias" class="form-select" onchange="manejarCantidadDias(this, 'editar_custom_dias')">
                                    <!-- 1.00 = 8 horas -->
                                    <option value="1.00">Día completo (1 día - 8 horas)</option>
                                    <!-- 0.88 = 7 horas -->
                                    <option value="0.88">Casi el día completo (0.88 días - 7 horas)</option>
                                    <!-- 0.75 = 6 horas -->
                                    <option value="0.75">Tres cuartos de jornada (0.75 días - 6 horas)</option>
                                    <!-- 0.63 = 5 horas -->
                                    <option value="0.63">Más de medio día (0.63 días - 5 horas)</option>
                                    <!-- 0.50 = 4 horas -->
                                    <option value="0.50">Medio día (0.50 días - 4 horas)</option>
                                    <!-- 0.38 = 3 horas -->
                                    <option value="0.38">Menos de medio día (0.38 días - 3 horas)</option>
                                    <!-- 0.25 = 2 horas -->
                                    <option value="0.25">Dos horas (0.25 días - 2 horas)</option>
                                    <!-- 0.13 = 1 hora -->
                                    <option value="0.13">Una hora (0.13 días - 1 hora)</option>
                                    <option value="custom">Personalizado...</option>
                                </select>
                                <div id="editar_custom_dias" class="mt-2" style="display:none;">
                                    <input type="number" id="editar_custom_input" class="form-control" placeholder="Ingrese fracción de día (ej. 0.60)" min="0.01" max="1.00" step="0.01"
                                        oninput="actualizarCantidadPersonalizada(this, 'editar_cantidad_dias')">
                                    <small class="form-text text-muted">Ingresa un valor entre 0.01 y 1.00 (equivale a horas ÷ 8)</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="editar_observaciones_rrhh" class="form-label small fw-bold text-muted text-uppercase">Observaciones RRHH (Obligatorio):</label>
                                <textarea id="editar_observaciones_rrhh" name="observaciones_rrhh" class="form-control" rows="3" style="resize: none;" required></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer border-0 p-3 bg-white d-flex justify-content-between">
                        <button type="button" class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" form="formEditarFalta" class="btn-modern btn-modern-primary">
                            <i class="fas fa-check me-2"></i>Aprobar / Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal para seleccionar tipo de boleta a imprimir -->
    <div class="modal fade" id="modalTipoBoleta" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 450px;">
            <div class="modal-content border-0 shadow" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header border-0 py-3 px-3" style="background: #0E544C; color: #fff;">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px;">
                            <i class="fas fa-print fs-4"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0">Imprimir Boleta</h5>
                            <p class="small mb-0 opacity-75">Seleccione el tipo de boleta a generar</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <p class="text-muted text-center mb-4">¿Qué tipo de boleta de acción de personal desea imprimir?</p>
                    <div class="d-flex flex-column gap-3">
                        <a href="imprimir_boleta.php?tipo=vacaciones" target="_blank" class="btn-boleta-select" onclick="cerrarModalBoleta()">
                            <div class="boleta-icon-wrapper bg-vacaciones-light text-vacaciones">
                                <i class="fas fa-umbrella-beach"></i>
                            </div>
                            <div class="boleta-text-wrapper">
                                <div class="boleta-title">Vacaciones</div>
                                <div class="boleta-desc">Acción de personal para días de vacaciones</div>
                            </div>
                            <i class="fas fa-chevron-right arrow-icon"></i>
                        </a>
                        
                        <a href="imprimir_boleta.php?tipo=subsidio" target="_blank" class="btn-boleta-select" onclick="cerrarModalBoleta()">
                            <div class="boleta-icon-wrapper bg-subsidio-light text-subsidio">
                                <i class="fas fa-notes-medical"></i>
                            </div>
                            <div class="boleta-text-wrapper">
                                <div class="boleta-title">Subsidio</div>
                                <div class="boleta-desc">Boleta para subsidios o reposos médicos</div>
                            </div>
                            <i class="fas fa-chevron-right arrow-icon"></i>
                        </a>
                        
                        <a href="imprimir_boleta.php?tipo=permiso" target="_blank" class="btn-boleta-select" onclick="cerrarModalBoleta()">
                            <div class="boleta-icon-wrapper bg-permiso-light text-permiso">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="boleta-text-wrapper">
                                <div class="boleta-title">Permiso</div>
                                <div class="boleta-desc">Boleta para faltas autorizadas o permisos</div>
                            </div>
                            <i class="fas fa-chevron-right arrow-icon"></i>
                        </a>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-white justify-content-center">
                    <button type="button" class="btn-modern btn-modern-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function cerrarModalBoleta() {
            setTimeout(() => {
                const modalEl = document.getElementById('modalTipoBoleta');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
            }, 500);
        }

        /**
         * Imprime la boleta V2 de vacaciones con los datos del formulario modalNuevaVacacion.
         * Abre imprimir_boleta_v2.php en una nueva pestaña con los campos pre-llenados.
         */
        async function imprimirBoletaVacacionesV2() {
            // Tienda (texto del select de sucursal)
            const sucursalSel = document.getElementById('nueva_sucursal');
            const tienda = sucursalSel ? sucursalSel.options[sucursalSel.selectedIndex]?.text?.trim() : '';

            // Jefe: usuario logueado
            const jefe = (window.CONFIG_VACACIONES && window.CONFIG_VACACIONES.jefeNombre)
                ? window.CONFIG_VACACIONES.jefeNombre : '';

            // Fecha de emisión: hoy
            const hoy = new Date();
            const fechaEmision = ('0' + hoy.getDate()).slice(-2) + '/' +
                ('0' + (hoy.getMonth() + 1)).slice(-2) + '/' + hoy.getFullYear();

            // Nombre del colaborador
            const operarioSel = document.getElementById('nueva_operario');
            const nombre = operarioSel ? operarioSel.options[operarioSel.selectedIndex]?.text?.trim() : '';
            const codOperario = operarioSel ? operarioSel.value : '';

            // Fechas de inicio y fin
            const fechaInicio = document.getElementById('nueva_fecha_inicio')?.value || '';
            const fechaFin    = document.getElementById('nueva_fecha_fin')?.value || '';

            // Total de días (diferencia inclusiva)
            let totalDias = '';
            if (fechaInicio && fechaFin) {
                const d1 = new Date(fechaInicio + 'T00:00:00');
                const d2 = new Date(fechaFin    + 'T00:00:00');
                if (d2 >= d1) {
                    const diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
                    totalDias = diff + (diff === 1 ? ' día' : ' días');
                }
            }

            // Puesto: traer vía AJAX
            let puesto = '';
            if (codOperario && parseInt(codOperario) > 0) {
                try {
                    const resp = await fetch(`ajax/vacaciones_ajax.php?action=obtener_cargo_operario&cod_operario=${codOperario}`);
                    const data = await resp.json();
                    puesto = data.cargo || '';
                } catch (e) {
                    puesto = '';
                }
            }

            // Construir URL
            const params = new URLSearchParams({
                tienda, jefe, fecha_emision: fechaEmision,
                nombre, puesto, fecha_inicio: fechaInicio,
                fecha_fin: fechaFin, total_dias: totalDias
            });

            window.open('imprimir_boleta_v2.php?' + params.toString(), '_blank');

            // También accionar el registro de vacaciones
            const submitBtn = document.querySelector('button[type="submit"][form="formNuevaVacacion"]');
            if (submitBtn) submitBtn.click();
        }
    </script>

    <!-- Botón Flotante con opciones -->
    <?php if (tienePermiso('registro_vacaciones', 'nuevo_registro', $cargoOperario)): ?>
        <div class="fab-container" id="fabContainer">
            <div class="fab-options">
                <div class="fab-option" onclick="mostrarModalNuevaVacacion()">
                    <span class="fab-label">Vacaciones</span>
                    <div class="fab-icon-holder"><i class="fas fa-umbrella-beach"></i></div>
                </div>
                <div class="fab-option" onclick="mostrarModalNuevoSubsidio()">
                    <span class="fab-label">Subsidio</span>
                    <div class="fab-icon-holder"><i class="fas fa-notes-medical"></i></div>
                </div>
                <div class="fab-option" onclick="mostrarModalNuevaFaltaPermiso()">
                    <span class="fab-label">Falta / Permiso</span>
                    <div class="fab-icon-holder"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>
            <div class="btn-floating-pitaya" title="Nuevo Registro" onclick="toggleFab(event)">
                <i class="fas fa-plus"></i>
            </div>
        </div>
        <script>
            function toggleFab(event) {
                event.stopPropagation();
                document.getElementById('fabContainer').classList.toggle('active');
            }
            document.addEventListener('click', function(event) {
                const fab = document.getElementById('fabContainer');
                if (fab && fab.classList.contains('active') && !fab.contains(event.target)) {
                    fab.classList.remove('active');
                }
            });
        </script>
    <?php endif; ?>
    <!-- FAB Draggable: permite mover el botón flotante libremente en el viewport -->
    <script src="/core/assets/js/fab_button.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>