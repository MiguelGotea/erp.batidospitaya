<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!verificarAccesoCargo([13, 16, 39, 30, 37, 28]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

$esLider = verificarAccesoCargo([5, 43]);
$esRH = verificarAccesoCargo([13, 8, 39, 30, 37, 28]);

/**
 * Obtiene el porcentaje de pago para un tipo de falta específico
 */
function obtenerPorcentajePagoTipoFalta($tipoFalta) {
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
if (empty($fechaDesde)) $fechaDesde = $primerDiaMes;
if (empty($fechaHasta)) $fechaHasta = $ultimoDiaMes;

// Obtener operario seleccionado
$operarioSeleccionado = isset($_GET['operario']) ? intval($_GET['operario']) : 0;

// Obtener operarios para el filtro
$operarios = obtenerOperariosFiltro();

// Determinar modo de vista basado en la selección de sucursal
$modoVista = ($sucursalSeleccionada === 'todas') ? 'todas' : 'sucursal';

// Obtener vacaciones si hay sucursal y fechas seleccionadas
$vacaciones = [];
if (($sucursalSeleccionada || $modoVista === 'todas') && $fechaDesde && $fechaHasta) {
    $vacaciones = obtenerVacaciones(
        ($modoVista === 'todas') ? null : $sucursalSeleccionada, 
        $fechaDesde, 
        $fechaHasta, 
        $esRH, 
        $modoVista,
        $operarioSeleccionado
    );
}

// Función para obtener operarios para el filtro
function obtenerOperariosFiltro() {
    global $conn;
    
    $sql = "SELECT o.CodOperario, 
                   CONCAT(
                       IFNULL(o.Nombre, ''), ' ', 
                       IFNULL(o.Nombre2, ''), ' ', 
                       IFNULL(o.Apellido, ''), ' ', 
                       IFNULL(o.Apellido2, '')
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
function recortarTexto($texto, $longitud = 50) {
    if (strlen($texto) <= $longitud) {
        return $texto;
    }
    return substr($texto, 0, $longitud) . '...';
}

// Función para obtener vacaciones (filtradas por tipo "Vacaciones")
function obtenerVacaciones($codSucursal, $fechaDesde, $fechaHasta, $esRH = false, $modoVista = 'sucursal', $operarioId = 0) {
    global $conn;
    
    error_log("Intentando obtener vacaciones para sucursal: $codSucursal, desde: $fechaDesde, hasta: $fechaHasta, operario: $operarioId");
    
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
                fm.fecha_registro
            FROM faltas_manual fm
            JOIN Operarios o ON fm.cod_operario = o.CodOperario
            JOIN sucursales s ON fm.cod_sucursal = s.codigo
            JOIN Operarios r ON fm.registrado_por = r.CodOperario
            WHERE fm.tipo_falta = 'Vacaciones'
            AND fm.fecha_falta BETWEEN ? AND ?
        ";
        
        $params = [$fechaDesde, $fechaHasta];
        
        // CORRECCIÓN: Si no es 'todas' y no está vacío, filtrar por sucursal
        if ($modoVista !== 'todas' && !empty($codSucursal) && $codSucursal !== 'todas') {
            $sql .= " AND fm.cod_sucursal = ?";
            $params[] = $codSucursal;
        }
        
        // Filtrar por operario si se seleccionó uno específico
        if ($operarioId > 0) {
            $sql .= " AND fm.cod_operario = ?";
            $params[] = $operarioId;
        }
        
        $sql .= " ORDER BY fm.fecha_falta DESC, o.Nombre, o.Apellido";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Error al preparar la consulta: " . implode(" ", $conn->errorInfo()));
            return [];
        }
        
        if (!$stmt->execute($params)) {
            error_log("Error al ejecutar la consulta: " . implode(" ", $stmt->errorInfo()));
            return [];
        }
        
        $resultados = $stmt->fetchAll();
        
        error_log("Vacaciones encontradas: " . count($resultados));
        return $resultados;
    } catch (PDOException $e) {
        error_log("Excepción al obtener vacaciones: " . $e->getMessage());
        return [];
    }
}

// Función para obtener días laborables entre dos fechas (excluye fines de semana)
function obtenerDiasLaborablesEnRango($fechaInicio, $fechaFin) {
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
function procesarRegistroVacacionesRango() {
    global $conn, $esLider, $esRH;
    
    // Permitir tanto a líderes como a RH registrar vacaciones
    if (!$esLider && !$esRH) {
        $_SESSION['error'] = 'Solo los líderes y RH pueden registrar vacaciones';
        header('Location: vacaciones.php');
        exit();
    }
    
    try {
        $codOperario = (int)$_POST['cod_operario'];
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
                    tipo_falta, observaciones, foto_path, registrado_por, cod_contrato, porcentaje_pago
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $codOperario, 
                $dia, 
                $codSucursal, 
                $tipoFalta, 
                $observaciones,
                $rutaRelativa, // Usamos la ruta relativa para la BD
                $_SESSION['usuario_id'],
                $codContrato,
                $porcentajePago
            ])) {
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
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    
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
        echo '<td>' . ($vacacion['observaciones'] ? htmlspecialchars($vacacion['observaciones']) : '-') . '</td>';
        echo '<td>' . htmlspecialchars($vacacion['registrador_nombre'] . ' ' . $vacacion['registrador_apellido']) . '</td>';
        echo '<td>' . formatoFechaCorta($vacacion['fecha_registro']) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

/**
 * Obtiene los tipos de falta con sus porcentajes
 */
function obtenerTiposFaltaConPorcentajes() {
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
    <title>Registro de Vacaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 5px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 5px;
            box-sizing: border-box;
            margin: 1px auto;
            flex-wrap: wrap;
        }

        .logo {
            height: 50px;
        }

        .logo-container {
            flex-shrink: 0;
            margin-right: auto;
        }

        .buttons-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            flex-grow: 1;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-agregar {
            background-color: transparent;
            color: #51B8AC;
            border: 1px solid #51B8AC;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
            flex-shrink: 0;
        }

        .btn-agregar.activo {
            background-color: #51B8AC;
            color: white;
            font-weight: normal;
        }

        .btn-agregar:hover {
            background-color: #0E544C;
            color: white;
            border-color: #0E544C;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: #51B8AC;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .btn-logout {
            background: #51B8AC;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #0E544C;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
        }
        
        .filtros-container {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filtros-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filtro-group {
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .filtro-group label {
            margin-bottom: 5px;
            text-align: left;
            font-weight: bold;
        }

        .filtro-group select,
        .filtro-group input {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
            width: 100%;
        }

        .filtro-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .filtro-buttons button {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-aplicar {
            background-color: #51B8AC;
            color: white;
        }

        .btn-aplicar:hover {
            background-color: #0E544C;
        }

        .btn-limpiar {
            background-color: #f1f1f1;
            color: #333;
        }

        .btn-limpiar:hover {
            background-color: #ddd;
        }

        .btn {
            padding: 8px 15px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #0E544C;
        }
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            vertical-align: middle;
        }

        th {
            background-color: #0E544C !important;
            color: white;
            text-align: center;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 90%;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            margin: 20px auto;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-radius: 8px 8px 0 0;
        }
        
        .modal-title {
            color: #0E544C;
            font-size: 1.2rem !important;
            font-weight: bold;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            margin-bottom: 15px;
            max-height: calc(90vh - 150px);
            overflow-y: auto;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 10;
            border-radius: 0 0 8px 8px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }
        
        .form-select, .form-textarea, .form-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .info-resumen {
            background-color: #e8f4f8;
            border-left: 4px solid #51B8AC;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .info-resumen p {
            margin: 5px 0;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }
            
            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }
            
            .logo-container {
                order: 1;
                margin-right: 0;
            }
            
            .user-info {
                order: 2;
                margin-left: auto;
            }
            
            .filtros-form {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                max-width: 95%;
                padding: 15px;
            }
        }
        
        /* Estilos para el botón de foto en la tabla */
        .btn-foto {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .btn-foto:hover {
            background-color: #f0f0f0;
        }
        
        .btn-foto i {
            transition: color 0.3s;
        }
        
        .btn-foto:hover i {
            color: #0E544C !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../../assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <?php if ($esAdmin || verificarAccesoCargo([8, 5, 43, 13, 16, 39, 30, 37, 28])): ?>
                        <a href="faltas_manual.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'faltas_manual.php' ? '' : '' ?>">
                            <i class="fas fa-user-times"></i> <span class="btn-text">Faltas/Ausencias</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin): ?>
                        <a href="../rh/tf_operarios.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'tf_operarios.php' ? '' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Totales</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([5, 43, 11, 16, 27, 8, 28, 39, 30, 37, 28, 13])): ?>
                        <a href="../operaciones/tardanzas_manual.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == '../operaciones/tardanzas_manual.php' ? '' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Tardanzas</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([11, 8, 16])): ?>
                        <a href="../operaciones/horas_extras_manual.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'horas_extras_manual.php' ? '' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Horas Extras</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([8, 11, 16])): ?>
                        <a href="../operaciones/feriados.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'feriados.php' ? '' : '' ?>">
                            <i class="fas fa-calendar-day"></i> <span class="btn-text">Feriados</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([8, 16])): ?>
                        <a href="../operaciones/viaticos.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'viaticos.php' ? '' : '' ?>">
                            <i class="fas fa-money-check-alt"></i> <span class="btn-text">Viáticos</span>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([5, 43, 16])): ?>
                        <a href="programar_horarios_lider2.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'programar_horarios_lider2.php' ? '' : '' ?>">
                            <i class="fas fa-user-clock"></i> <span class="btn-text">Generar Horarios</span>
                        </a>
                    <?php endif; ?>
                    
                    <a href="vacaciones.php" class="btn-agregar activo">
                        <i class="fas fa-umbrella-beach"></i> <span class="btn-text">Vacaciones</span>
                    </a>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?= $esAdmin ? 
                            strtoupper(substr($usuario['nombre'], 0, 1)) : 
                            strtoupper(substr($usuario['Nombre'], 0, 1)) ?>
                    </div>
                    <div>
                        <div>
                            <?= $esAdmin ? 
                                htmlspecialchars($usuario['nombre']) : 
                                htmlspecialchars($usuario['Nombre'].' '.$usuario['Apellido']) ?>
                        </div>
                        <small>
                            <?= htmlspecialchars($cargoUsuario) ?>
                        </small>
                    </div>
                    <a href="../../../index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <h1 class="title" style="display:none;">Registro de Vacaciones</h1>
        
        <?php if (isset($_SESSION['exito'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['exito'] ?>
                <?php unset($_SESSION['exito']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filtros-container">
            <form method="get" action="vacaciones.php" class="filtros-form">
                <?php if ($esAdmin || !verificarAccesoCargo([2, 5])): ?>
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
                    <input type="text" id="operario" name="operario" 
                           placeholder="Escriba para buscar..." 
                           value="<?php 
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
                    <input type="hidden" id="operario_id" name="operario" value="<?php echo $operarioSeleccionado; ?>">
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
                
                <div class="filtro-buttons">
                    <button type="submit" class="btn-aplicar">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([5, 43, 13, 16, 39, 30, 37, 28])): ?>
                        <button type="button" onclick="mostrarModalNuevaVacacion()" class="btn btn-success">
                            <i class="fas fa-plus"></i> Nueva
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($esAdmin || verificarAccesoCargo([8, 16])): ?>
                        <a href="vacaciones.php?<?= http_build_query([
                            'sucursal' => $sucursalSeleccionada ?? '',
                            'desde' => $fechaDesde,
                            'hasta' => $fechaHasta,
                            'operario' => $operarioSeleccionado,
                            'exportar_excel' => 1
                        ]) ?>" class="btn-agregar" style="background-color: #28a745; border-color: #28a745; color: white;">
                            <i class="fas fa-file-excel"></i> Exportar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="table-container">
        <?php if (!empty($vacaciones)): ?>
            <table id="listaVacaciones">
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>Sucursal</th>
                        <th>Fecha Vacación</th>
                        <th>Observaciones</th>
                        <th>Registrado por</th>
                        <th>Fecha Registro</th>
                        <th>Foto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vacaciones as $vacacion): ?>
                        <tr>
                            <td><?= htmlspecialchars($vacacion['operario_nombre'] . ' ' . $vacacion['operario_apellido'] . ' ' . $vacacion['operario_apellido2']) ?></td>
                            <td><?= htmlspecialchars($vacacion['sucursal_nombre']) ?></td>
                            <td><?= formatoFechaCorta($vacacion['fecha_falta']) ?></td>
                            <td title="<?= htmlspecialchars($vacacion['observaciones'] ?: '-') ?>">
                                <?= $vacacion['observaciones'] ? htmlspecialchars(recortarTexto($vacacion['observaciones'], 20)) : '-' ?>
                            </td>
                            <td><?= htmlspecialchars($vacacion['registrador_nombre'] . ' ' . $vacacion['registrador_apellido']) ?></td>
                            <td><?= formatoFechaCorta($vacacion['fecha_registro']) ?></td>
                            <td style="text-align:center;"> <!-- NUEVA CELDA -->
                                <?php if ($vacacion['foto_path']): ?>
                                    <button type="button" onclick="mostrarFoto('<?= htmlspecialchars($vacacion['foto_path']) ?>')" class="btn btn-sm btn-foto">
                                        <i class="fas fa-camera" style="color: #51B8AC; font-size: 18px;"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
                <div class="alert alert-info">
                    <?php if (($sucursalSeleccionada || $modoVista === 'todas') && $fechaDesde && $fechaHasta): ?>
                        No se encontraron registros de vacaciones 
                        <?php if ($modoVista === 'todas'): ?>
                            en todas las sucursales
                        <?php else: ?>
                            para <?= htmlspecialchars(obtenerNombreSucursal($sucursalSeleccionada)) ?>
                        <?php endif; ?>
                        entre <?= formatoFechaCorta($fechaDesde) ?> y <?= formatoFechaCorta($fechaHasta) ?>.
                    <?php else: ?>
                        Seleccione una sucursal y rango de fechas para buscar vacaciones.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal para nueva vacación por rango -->
    <div class="modal" id="modalNuevaVacacion">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Registrar Vacaciones por Rango</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formNuevaVacacion" method="post" enctype="multipart/form-data">
                <input type="hidden" name="registrar_vacaciones" value="1">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="nueva_sucursal" class="form-label">Sucursal:</label>
                        <select id="nueva_sucursal" name="cod_sucursal" class="form-select" required>
                            <?php if ($esRH): ?>
                                <!-- Para RH, mostrar todas las sucursales -->
                                <?php foreach (obtenerTodasSucursales() as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>">
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Para líderes, mostrar solo sus sucursales -->
                                <?php foreach (obtenerSucursalesLider($_SESSION['usuario_id']) as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>">
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nueva_operario" class="form-label">Colaborador:</label>
                        <select id="nueva_operario" name="cod_operario" class="form-select" required>
                            <option value="">Seleccione un colaborador</option>
                            <!-- Se llenará dinámicamente con JavaScript -->
                        </select>
                    </div>
                    
                    <!-- NUEVO: Tipo de falta con porcentaje (aunque sea solo para vacaciones, se aplica la lógica) -->
                    <div class="form-group">
                        <label for="nueva_tipo" class="form-label">Tipo:</label>
                        <select id="nueva_tipo" name="tipo_falta" class="form-select" required onchange="actualizarPorcentajeVacaciones(this.value)">
                            <?php 
                            // Obtener solo el tipo "Vacaciones" de la base de datos
                            $tiposFalta = obtenerTiposFaltaConPorcentajes();
                            foreach ($tiposFalta as $tipo): 
                                if ($tipo['codigo'] === 'Vacaciones'): // Solo mostrar Vacaciones
                                    $porcentajeTexto = ($tipo['porcentaje_pago'] == -100) ? 
                                        'Deducción 100%' : 
                                        'Paga ' . $tipo['porcentaje_pago'] . '%';
                            ?>
                                <option value="<?= $tipo['codigo'] ?>" data-porcentaje="<?= $tipo['porcentaje_pago'] ?>" selected>
                                    <?= htmlspecialchars($tipo['nombre']) ?> (<?= $porcentajeTexto ?>)
                                </option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                        <small id="info-porcentaje-vacaciones" class="form-text text-muted" style="display: none;"></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="nueva_fecha_inicio" class="form-label">Fecha Inicio:</label>
                        <input type="date" id="nueva_fecha_inicio" name="fecha_inicio" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nueva_fecha_fin" class="form-label">Fecha Fin:</label>
                        <input type="date" id="nueva_fecha_fin" name="fecha_fin" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="nueva_observaciones" class="form-label">Observaciones:</label>
                        <textarea id="nueva_observaciones" name="observaciones" class="form-textarea"></textarea>
                    </div>
                    
                    <!-- NUEVO: Campo para foto obligatoria -->
                    <div class="form-group">
                        <label for="nueva_foto" class="form-label">Foto de Evidencia (Obligatoria):</label>
                        <input type="file" id="nueva_foto" name="foto_falta" class="form-input" accept="image/*" capture="environment" required>
                        <small class="form-text text-muted">Toma una foto o selecciona una del dispositivo (máx. 5MB)</small>
                    </div>
                    
                    <!-- Información del rango seleccionado -->
                    <div id="info-rango" class="info-resumen" style="display: none;">
                        <p><strong>Resumen del rango seleccionado:</strong></p>
                        <p id="info-dias-totales">Días totales en rango: 0</p>
                        <p id="info-dias-laborables" style="display:none;">Días laborables (L-V): 0</p>
                        <p id="info-vacaciones">Días a registrar como vacaciones: 0</p>
                        <p style="display:none;"><small><i>Nota: Se excluyen sábados y domingos</i></small></p>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar Vacaciones</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Datos de operarios para el autocompletado
        const operariosData = [
            {id: 0, nombre: 'Todos los colaboradores'},
            <?php foreach ($operarios as $op): ?>
            {id: <?php echo $op['CodOperario']; ?>, nombre: '<?php echo addslashes($op['nombre_completo']); ?>'},
            <?php endforeach; ?>
        ];
        
        // Función para buscar operarios
        function buscarOperarios(texto) {
            if (!texto) {
                return operariosData;
            }
            return operariosData.filter(op => 
                op.nombre.toLowerCase().includes(texto.toLowerCase())
            );
        }
        
        // Manejar el input de operario
        const operarioInput = document.getElementById('operario');
        const operarioIdInput = document.getElementById('operario_id');
        const sugerenciasDiv = document.getElementById('operarios-sugerencias');
        
        operarioInput.addEventListener('input', function() {
            const texto = this.value.trim();
            
            if (texto === '') {
                operarioIdInput.value = '0';
                sugerenciasDiv.style.display = 'none';
                return;
            }
            
            const resultados = buscarOperarios(texto);
            
            sugerenciasDiv.innerHTML = '';
            
            if (resultados.length > 0) {
                resultados.forEach(op => {
                    const div = document.createElement('div');
                    div.textContent = op.nombre;
                    div.style.padding = '8px';
                    div.style.cursor = 'pointer';
                    div.addEventListener('click', function() {
                        operarioInput.value = op.nombre;
                        operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                    });
                    div.addEventListener('mouseover', function() {
                        this.style.backgroundColor = '#f5f5f5';
                    });
                    div.addEventListener('mouseout', function() {
                        this.style.backgroundColor = 'white';
                    });
                    sugerenciasDiv.appendChild(div);
                });
                sugerenciasDiv.style.display = 'block';
            } else {
                sugerenciasDiv.style.display = 'none';
            }
        });
        
        document.addEventListener('click', function(e) {
            if (e.target !== operarioInput) {
                sugerenciasDiv.style.display = 'none';
            }
        });
        
        operarioInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const texto = this.value.trim();
                const resultados = buscarOperarios(texto);
                if (resultados.length > 0) {
                    this.value = resultados[0].nombre;
                    operarioIdInput.value = resultados[0].id;
                }
                sugerenciasDiv.style.display = 'none';
            }
        });
        
        // Función para mostrar modal de nueva vacación
        function mostrarModalNuevaVacacion() {
            // Establecer fechas predeterminadas (hoy por defecto)
            const hoy = new Date();
            const fechaInicioInput = document.getElementById('nueva_fecha_inicio');
            const fechaFinInput = document.getElementById('nueva_fecha_fin');
            
            fechaInicioInput.valueAsDate = hoy;
            fechaFinInput.valueAsDate = hoy;
            
            // Limpiar selección de operario
            const selectOperario = document.getElementById('nueva_operario');
            selectOperario.innerHTML = '<option value="">Seleccione un colaborador</option>';
            
            // Ocultar información del rango inicialmente
            document.getElementById('info-rango').style.display = 'none';
            
            document.getElementById('modalNuevaVacacion').style.display = 'flex';
            
            // NUEVO: Cargar los colaboradores inmediatamente después de mostrar el modal
            setTimeout(() => {
                const selectSucursal = document.getElementById('nueva_sucursal');
                if (selectSucursal.value) {
                    cargarOperariosSucursal(selectSucursal.value);
                }
            }, 100);
        }
        
        // Función para calcular días laborables en un rango
        function calcularDiasLaborables(fechaInicio, fechaFin) {
            if (!fechaInicio || !fechaFin) return 0;
            
            const inicio = new Date(fechaInicio);
            const fin = new Date(fechaFin);
            
            if (inicio > fin) return 0;
            
            let diasLaborables = 0;
            const fechaActual = new Date(inicio);
            
            while (fechaActual <= fin) {
                const diaSemana = fechaActual.getDay(); // 0=domingo, 1=lunes, ..., 6=sábado
                
                // Si no es domingo (0) ni sábado (6), es laborable, pero ahora es cualquier día, pero lo dejamos comentado
                //if (diaSemana !== 0 && diaSemana !== 6) {
                    diasLaborables++;
                //}
                
                fechaActual.setDate(fechaActual.getDate() + 1);
            }
            
            return diasLaborables;
        }
        
        // Función para actualizar información del rango
        function actualizarInfoRango() {
            const fechaInicio = document.getElementById('nueva_fecha_inicio').value;
            const fechaFin = document.getElementById('nueva_fecha_fin').value;
            const infoRango = document.getElementById('info-rango');
            
            if (!fechaInicio || !fechaFin) {
                infoRango.style.display = 'none';
                return;
            }
            
            const inicio = new Date(fechaInicio);
            const fin = new Date(fechaFin);
            
            if (inicio > fin) {
                infoRango.innerHTML = '<p style="color: #dc3545;"><strong>Error:</strong> La fecha de inicio no puede ser mayor que la fecha fin</p>';
                infoRango.style.display = 'block';
                return;
            }
            
            // Calcular días totales
            const diffTime = Math.abs(fin - inicio);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            // Calcular días laborables (excluye sábados y domingos)
            const diasLaborables = calcularDiasLaborables(fechaInicio, fechaFin);
            
            // Actualizar información
            document.getElementById('info-dias-totales').textContent = `Días totales en rango: ${diffDays}`;
            //document.getElementById('info-dias-laborables').textContent = `Días laborables (L-V): ${diasLaborables}`;
            document.getElementById('info-vacaciones').textContent = `Días a registrar como vacaciones: ${diasLaborables}`;
            
            infoRango.style.display = 'block';
        }
        
        // Escuchar cambios en las fechas
        document.getElementById('nueva_fecha_inicio').addEventListener('change', function() {
            actualizarInfoRango();
        });
        
        document.getElementById('nueva_fecha_fin').addEventListener('change', function() {
            actualizarInfoRango();
        });
        
        // Función para cargar operarios de una sucursal
        function cargarOperariosSucursal(codSucursal) {
            const selectOperario = document.getElementById('nueva_operario');
            
            if (!codSucursal) {
                selectOperario.innerHTML = '<option value="">Seleccione un colaborador</option>';
                return;
            }
            
            selectOperario.innerHTML = '<option value="">Cargando colaboradores...</option>';
            
            // Hacer petición AJAX para obtener operarios de la sucursal
            fetch('ajax.php?action=obtener_operarios_sucursal_simple&sucursal=' + codSucursal)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    let options = '<option value="">Seleccione un colaborador</option>';
                    
                    if (data.length > 0) {
                        data.forEach(operario => {
                            const nombreCompleto = operario.Nombre + ' ' + 
                                                  (operario.Apellido || '') + ' ' + 
                                                  (operario.Apellido2 || '');
                            options += `<option value="${operario.CodOperario}">${nombreCompleto.trim()}</option>`;
                        });
                    } else {
                        options = '<option value="">No hay colaboradores disponibles</option>';
                    }
                    
                    selectOperario.innerHTML = options;
                })
                .catch(error => {
                    console.error('Error al cargar colaboradores:', error);
                    selectOperario.innerHTML = '<option value="">Error al cargar colaboradores</option>';
                });
        }
        
        // Función para actualizar la información del porcentaje de vacaciones
        function actualizarPorcentajeVacaciones(tipoFalta) {
            const select = document.getElementById('nueva_tipo');
            const option = select.querySelector(`option[value="${tipoFalta}"]`);
            const infoElement = document.getElementById('info-porcentaje-vacaciones');
            
            if (option && option.dataset.porcentaje) {
                const porcentaje = parseFloat(option.dataset.porcentaje);
                let texto = '';
                
                if (porcentaje === -100) {
                    texto = '⚠️ La empresa NO paga este día - se DEDUCE del salario';
                    infoElement.style.color = '#dc3545';
                } else if (porcentaje === 0) {
                    texto = 'ℹ️ La empresa NO paga este día';
                    infoElement.style.color = '#ffc107';
                } else if (porcentaje === 100) {
                    texto = '✅ La empresa paga el 100% de este día';
                    infoElement.style.color = '#28a745';
                } else {
                    texto = `📊 La empresa paga el ${porcentaje}% de este día`;
                    infoElement.style.color = '#17a2b8';
                }
                
                infoElement.textContent = texto;
                infoElement.style.display = 'block';
            } else {
                infoElement.style.display = 'none';
            }
        }
        
        // Mostrar el porcentaje cuando se carga el modal
        document.addEventListener('DOMContentLoaded', function() {
            const tipoSelect = document.getElementById('nueva_tipo');
            if (tipoSelect) {
                actualizarPorcentajeVacaciones(tipoSelect.value);
            }
        });
        
        // Cargar operarios cuando cambia la sucursal
        document.getElementById('nueva_sucursal').addEventListener('change', function() {
            cargarOperariosSucursal(this.value);
        });
        
        // Validar formulario antes de enviar
        document.getElementById('formNuevaVacacion').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fechaInicio = document.getElementById('nueva_fecha_inicio').value;
            const fechaFin = document.getElementById('nueva_fecha_fin').value;
            const codOperario = document.getElementById('nueva_operario').value;
            const fotoInput = document.getElementById('nueva_foto');
            
            // Validaciones básicas
            if (!fechaInicio || !fechaFin) {
                alert('Debe seleccionar ambas fechas');
                return false;
            }
            
            if (fechaInicio > fechaFin) {
                alert('La fecha de inicio no puede ser mayor que la fecha fin');
                return false;
            }
            
            if (!codOperario) {
                alert('Debe seleccionar un colaborador');
                return false;
            }
            
            // Validar que se haya seleccionado una foto
            if (!fotoInput.files || fotoInput.files.length === 0) {
                alert('Debe subir una foto como evidencia');
                return false;
            }
            
            // Validar tamaño de foto (máximo 5MB)
            const foto = fotoInput.files[0];
            if (foto.size > 5 * 1024 * 1024) {
                alert('La foto no debe exceder los 5MB');
                return false;
            }
            
            // Validar tipo de archivo
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(foto.type)) {
                alert('Solo se permiten imágenes JPEG, PNG o GIF');
                return false;
            }
            
            // Calcular días laborables para confirmación
            const diasLaborables = calcularDiasLaborables(fechaInicio, fechaFin);
            
            if (diasLaborables === 0) {
                alert('No hay días laborables en el rango seleccionado');
                return false;
            }
            
            // Mostrar confirmación
            let mensaje = `¿Está seguro de registrar ${diasLaborables} días de vacaciones para el colaborador seleccionado?\n\n`;
            mensaje += `Rango: ${fechaInicio} al ${fechaFin}\n`;
            mensaje += `Días laborables: ${diasLaborables}\n\n`;
            //mensaje += `NOTA: Se permiten fechas futuras para programar vacaciones con anticipación.\n\n`;
            mensaje += `IMPORTANTE: Se requiere foto de evidencia.`;
            
            if (confirm(mensaje)) {
                this.submit();
            }
            
            return false;
        });
        
        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modalNuevaVacacion').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('modalNuevaVacacion');
            if (event.target === modal) {
                cerrarModal();
            }
        });
        
        // Inicializar DataTable
        $(document).ready(function() {
            $('#listaVacaciones').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                dom: '<"top"l>rt<"bottom"ip>',
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                pageLength: 25,
                order: [], 
                ordering: true,
                orderMulti: true,
                columnDefs: [{
                    orderable: true,
                    targets: '_all'
                }]
            });
        });
        
        // Función para mostrar foto en un modal
        function mostrarFoto(rutaFoto) {
            ampliarImagen(rutaFoto);
        }
        
        // Función para ampliar imagen (funciona sobre modales existentes)
        function ampliarImagen(src) {
            const modalAmpliar = document.createElement('div');
            modalAmpliar.id = 'modalAmpliarImagen';
            modalAmpliar.style.position = 'fixed';
            modalAmpliar.style.top = '0';
            modalAmpliar.style.left = '0';
            modalAmpliar.style.width = '100%';
            modalAmpliar.style.height = '100%';
            modalAmpliar.style.backgroundColor = 'rgba(0,0,0,0.9)';
            modalAmpliar.style.display = 'flex';
            modalAmpliar.style.justifyContent = 'center';
            modalAmpliar.style.alignItems = 'center';
            modalAmpliar.style.zIndex = '3000'; // Mayor z-index para que esté sobre otros modales
            
            const img = document.createElement('img');
            img.src = src;
            img.style.maxWidth = '90%';
            img.style.maxHeight = '90%';
            img.style.objectFit = 'contain';
            img.style.boxShadow = '0 0 20px rgba(255,255,255,0.2)';
            
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.position = 'absolute';
            closeBtn.style.top = '20px';
            closeBtn.style.right = '20px';
            closeBtn.style.fontSize = '2.5rem';
            closeBtn.style.color = 'white';
            closeBtn.style.background = 'none';
            closeBtn.style.border = 'none';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.zIndex = '3001';
            
            closeBtn.onclick = function() {
                document.body.removeChild(modalAmpliar);
            };
            
            modalAmpliar.appendChild(img);
            modalAmpliar.appendChild(closeBtn);
            document.body.appendChild(modalAmpliar);
            
            // Cerrar al hacer clic fuera de la imagen
            modalAmpliar.onclick = function(e) {
                if (e.target === modalAmpliar) {
                    document.body.removeChild(modalAmpliar);
                }
            };
            
            // Cerrar con tecla ESC
            const closeOnEsc = function(e) {
                if (e.key === 'Escape') {
                    document.body.removeChild(modalAmpliar);
                    document.removeEventListener('keydown', closeOnEsc);
                }
            };
            
            document.addEventListener('keydown', closeOnEsc);
        }
    </script>
</body>
</html>