<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('operaciones');

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

// Al inicio del archivo promedio.php, agregar esto para detectar la página actual
$pagina_actual = basename($_SERVER['PHP_SELF']);
$es_pagina_avisos = $pagina_actual == 'index_avisos_publico.php';
$es_pagina_auditorias = $pagina_actual == 'index_auditorias_publico.php';
$es_pagina_promedio = $pagina_actual == 'promedio.php';

// Verificar si se envió el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_edicion'])) {
    $sucursal = $_POST['sucursal'];
    $cod_sucursal = $_POST['cod_sucursal']; // NUEVO - recibir el código de sucursal
    $mes = $_POST['mes'];
    $anio = $_POST['anio'];
    
    // Obtener el ID del usuario que está modificando
    $cod_operario_actualizacion = $_SESSION['usuario_id'];
    $fecha_actualizacion = date('Y-m-d H:i:s');
    
    // Procesar KPI Ventas (MODIFICAR ESTA PARTE)
    if (isset($_POST['kpi_ventas']) && $_POST['kpi_ventas'] !== '') {
        $kpi_ventas = number_format((float)$_POST['kpi_ventas'], 2, '.', '');
        
        // Verificar si ya existe un registro para esta sucursal, mes y año
        $query = "SELECT id FROM kpi_reclamos WHERE cod_sucursal = :cod_sucursal AND mes = :mes AND anio = :anio";
        $stmt = $conn->prepare($query);
        $stmt->execute([':cod_sucursal' => $cod_sucursal, ':mes' => $mes, ':anio' => $anio]);
        $existe = $stmt->fetch();
        
        if ($existe) {
            // Actualizar registro existente CON LAS NUEVAS COLUMNAS
            $query = "UPDATE kpi_reclamos SET kpi_ventas = :kpi_ventas, 
                      fecha_actualizacion = :fecha_actualizacion, 
                      cod_operario_actualizacion = :cod_operario 
                      WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':kpi_ventas' => $kpi_ventas, 
                ':fecha_actualizacion' => $fecha_actualizacion,
                ':cod_operario' => $cod_operario_actualizacion,
                ':id' => $existe['id']
            ]);
        } else {
            // Insertar nuevo registro CON LAS NUEVAS COLUMNAS
            $query = "INSERT INTO kpi_reclamos (sucursal, cod_sucursal, mes, anio, kpi_ventas, 
                      fecha_actualizacion, cod_operario_actualizacion) 
                      VALUES (:sucursal, :cod_sucursal, :mes, :anio, :kpi_ventas, :fecha_actualizacion, :cod_operario)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':sucursal' => $sucursal, 
                ':cod_sucursal' => $cod_sucursal, 
                ':mes' => $mes, 
                ':anio' => $anio, 
                ':kpi_ventas' => $kpi_ventas,
                ':fecha_actualizacion' => $fecha_actualizacion,
                ':cod_operario' => $cod_operario_actualizacion
            ]);
        }
    }
    
    // Procesar Reclamos - VERSIÓN MODIFICADA PARA MANEJAR CANTIDAD Y PORCENTAJE POR SEPARADO
    $reclamos_cantidad = isset($_POST['reclamos_cantidad']) && $_POST['reclamos_cantidad'] !== '' ? (int)$_POST['reclamos_cantidad'] : null;
    $reclamos_porcentaje = isset($_POST['reclamos_porcentaje']) && $_POST['reclamos_porcentaje'] !== '' ? number_format((float)$_POST['reclamos_porcentaje'], 2, '.', '') : null;
    
    // Solo proceder si al menos uno de los valores está presente
    if ($reclamos_cantidad !== null || $reclamos_porcentaje !== null) {
        // Verificar si ya existe un registro
        $query = "SELECT id, reclamos_cantidad, reclamos_porcentaje FROM kpi_reclamos WHERE cod_sucursal = :cod_sucursal AND mes = :mes AND anio = :anio";
        $stmt = $conn->prepare($query);
        $stmt->execute([':cod_sucursal' => $cod_sucursal, ':mes' => $mes, ':anio' => $anio]);
        $existe = $stmt->fetch();
        
        if ($existe) {
            // Construir la consulta dinámicamente según los campos proporcionados
            $actualizaciones = [];
            $parametros = [':id' => $existe['id']];
            
            if ($reclamos_cantidad !== null) {
                $actualizaciones[] = "reclamos_cantidad = :cantidad";
                $parametros[':cantidad'] = $reclamos_cantidad;
            }
            
            if ($reclamos_porcentaje !== null) {
                $actualizaciones[] = "reclamos_porcentaje = :porcentaje";
                $parametros[':porcentaje'] = $reclamos_porcentaje;
            }
            
            $actualizaciones[] = "fecha_actualizacion = :fecha_actualizacion";
            $actualizaciones[] = "cod_operario_actualizacion = :cod_operario";
            $parametros[':fecha_actualizacion'] = $fecha_actualizacion;
            $parametros[':cod_operario'] = $cod_operario_actualizacion;
            
            if (!empty($actualizaciones)) {
                $query = "UPDATE kpi_reclamos SET ".implode(', ', $actualizaciones)." WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->execute($parametros);
            }
        } else {
            // Insertar nuevo registro CON LAS NUEVAS COLUMNAS
            $query = "INSERT INTO kpi_reclamos (sucursal, cod_sucursal, mes, anio, reclamos_cantidad, reclamos_porcentaje,
                      fecha_actualizacion, cod_operario_actualizacion) 
                      VALUES (:sucursal, :cod_sucursal, :mes, :anio, :cantidad, :porcentaje, :fecha_actualizacion, :cod_operario)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':sucursal' => $sucursal, 
                ':cod_sucursal' => $cod_sucursal, 
                ':mes' => $mes, 
                ':anio' => $anio, 
                ':cantidad' => $reclamos_cantidad, 
                ':porcentaje' => $reclamos_porcentaje,
                ':fecha_actualizacion' => $fecha_actualizacion,
                ':cod_operario' => $cod_operario_actualizacion
            ]);
        }
    }
    
    // Redirigir para evitar reenvío del formulario
    header("Location: kpi.php?mes=$mes&anio=$anio&success=1");
    exit();
}

// Obtener el mes y año seleccionados (si existen)
$mes_seleccionado = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n');
$anio_seleccionado = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

// Obtener sucursales dinámicamente desde la BD ordenadas por código
$query_sucursales = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 AND sucursal = 1 ORDER BY codigo";
$stmt_sucursales = $conn->prepare($query_sucursales);
$stmt_sucursales->execute();
$sucursales_data = $stmt_sucursales->fetchAll(PDO::FETCH_ASSOC);

// Crear array asociativo con código => nombre para usar en la tabla
$sucursales = [];
foreach ($sucursales_data as $sucursal) {
    $sucursales[$sucursal['codigo']] = $sucursal['nombre'];
}

// Obtener datos de limpieza
$query_limpieza = "SELECT sucursal, AVG(promedio_general) as promedio, COUNT(*) as cantidad 
                   FROM auditoria 
                   WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio 
                   GROUP BY sucursal";
$stmt_limpieza = $conn->prepare($query_limpieza);
$stmt_limpieza->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$limpieza_data = $stmt_limpieza->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Obtener promedio general de limpieza
$query_limpieza_total = "SELECT AVG(promedio_general) as promedio_total, COUNT(*) as cantidad_total 
                         FROM auditoria 
                         WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio";
$stmt_limpieza_total = $conn->prepare($query_limpieza_total);
$stmt_limpieza_total->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$limpieza_total = $stmt_limpieza_total->fetch(PDO::FETCH_ASSOC);

// Obtener datos de servicio
$query_servicio = "SELECT sucursal, AVG(promedio_calificacion) as promedio, COUNT(*) as cantidad 
                   FROM auditoria_servicio 
                   WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio 
                   GROUP BY sucursal";
$stmt_servicio = $conn->prepare($query_servicio);
$stmt_servicio->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$servicio_data = $stmt_servicio->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Obtener promedio general de servicio
$query_servicio_total = "SELECT AVG(promedio_calificacion) as promedio_total, COUNT(*) as cantidad_total 
                         FROM auditoria_servicio 
                         WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio";
$stmt_servicio_total = $conn->prepare($query_servicio_total);
$stmt_servicio_total->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$servicio_total = $stmt_servicio_total->fetch(PDO::FETCH_ASSOC);

// Obtener datos de personal
$query_personal = "SELECT sucursal, AVG(promedio_personal) as promedio, COUNT(*) as cantidad 
                   FROM auditoria_personal 
                   WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio 
                   GROUP BY sucursal";
$stmt_personal = $conn->prepare($query_personal);
$stmt_personal->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$personal_data = $stmt_personal->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Obtener promedio general de personal
$query_personal_total = "SELECT AVG(promedio_personal) as promedio_total, COUNT(*) as cantidad_total 
                         FROM auditoria_personal 
                         WHERE MONTH(fecha) = :mes AND YEAR(fecha) = :anio";
$stmt_personal_total = $conn->prepare($query_personal_total);
$stmt_personal_total->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$personal_total = $stmt_personal_total->fetch(PDO::FETCH_ASSOC);

// Obtener datos de KPI Ventas y Reclamos - CORREGIDO: usar cod_sucursal como clave
$query_kpi_reclamos = "SELECT kr.cod_sucursal,
                      kr.sucursal,
                      FORMAT(kr.kpi_ventas, 2) as kpi_ventas, 
                      kr.reclamos_cantidad, 
                      kr.reclamos_porcentaje,
                      kr.fecha_actualizacion,
                      kr.cod_operario_actualizacion,
                      o.Nombre as operario_nombre,
                      o.Apellido as operario_apellido
                      FROM kpi_reclamos kr
                      LEFT JOIN Operarios o ON kr.cod_operario_actualizacion = o.CodOperario
                      WHERE kr.mes = :mes AND kr.anio = :anio";
$stmt_kpi_reclamos = $conn->prepare($query_kpi_reclamos);
$stmt_kpi_reclamos->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$kpi_reclamos_data_temp = $stmt_kpi_reclamos->fetchAll(PDO::FETCH_ASSOC);

// Reorganizar los datos usando cod_sucursal como clave
$kpi_reclamos_data = [];
foreach ($kpi_reclamos_data_temp as $row) {
    $kpi_reclamos_data[$row['cod_sucursal']] = $row;
}

// Obtener totales de reclamos - MEJORADO
$query_reclamos_total = "SELECT 
                         COALESCE(SUM(reclamos_cantidad), 0) as cantidad_total, 
                         COALESCE(AVG(reclamos_porcentaje), 0) as porcentaje_total 
                         FROM kpi_reclamos 
                         WHERE mes = :mes AND anio = :anio 
                         AND (reclamos_cantidad IS NOT NULL OR reclamos_porcentaje IS NOT NULL)";
$stmt_reclamos_total = $conn->prepare($query_reclamos_total);
$stmt_reclamos_total->execute([':mes' => $mes_seleccionado, ':anio' => $anio_seleccionado]);
$reclamos_total = $stmt_reclamos_total->fetch(PDO::FETCH_ASSOC);

// Asegurarnos de que tenemos valores válidos
if (!$reclamos_total) {
    $reclamos_total = ['cantidad_total' => 0, 'porcentaje_total' => 0];
} else {
    $reclamos_total['cantidad_total'] = $reclamos_total['cantidad_total'] ?? 0;
    $reclamos_total['porcentaje_total'] = $reclamos_total['porcentaje_total'] ?? 0;
}

// Función para determinar el color según el promedio
function getColorClass($promedio) {
    if ($promedio <= 4) return 'rojo';
    if ($promedio <= 4.5) return 'amarillo';
    return 'verde';
}

// Función para generar el enlace de ver auditorías
function getVerAuditoriasLink($sucursal, $tipo, $mes, $anio) {
    return "ver_auditorias.php?sucursal=" . urlencode($sucursal) . 
           "&tipo=" . $tipo . 
           "&mes=" . $mes . 
           "&anio=" . $anio;
}

// Calcular promedios generales por sucursal y totales - CORREGIDO
$suma_general_sucursales = 0;
$contador_sucursales_con_datos = 0;
$total_auditorias = 0;

// Arrays para almacenar promedios por tipo
$promedios_limpieza = [];
$promedios_personal = [];
$promedios_servicio = [];
$promedios_kpi = [];

// CORRECCIÓN: Calcular promedios KPI por separado
$suma_kpi_total = 0;
$contador_kpi_total = 0;

foreach ($sucursales as $cod_sucursal => $nombre_sucursal) {
    $sucursal = $nombre_sucursal;
    $limpieza = $limpieza_data[$sucursal][0] ?? ['promedio' => 0, 'cantidad' => 0];
    $personal = $personal_data[$sucursal][0] ?? ['promedio' => 0, 'cantidad' => 0];
    $servicio = $servicio_data[$sucursal][0] ?? ['promedio' => 0, 'cantidad' => 0];
    
    // CORRECCIÓN: Usar cod_sucursal en lugar del nombre
    $kpi_reclamos = $kpi_reclamos_data[$cod_sucursal] ?? ['kpi_ventas' => null];
    
    // Solo considerar sucursales que tengan al menos un tipo de auditoría
    if ($limpieza['cantidad'] > 0 || $personal['cantidad'] > 0 || $servicio['cantidad'] > 0 || !empty($kpi_reclamos['kpi_ventas'])) {
        // Calcular promedio general para la sucursal
        $suma = 0;
        $contador = 0;
        
        if ($limpieza['cantidad'] > 0) {
            $suma += $limpieza['promedio'];
            $contador++;
            $promedios_limpieza[] = $limpieza['promedio'];
        }
        if ($personal['cantidad'] > 0) {
            $suma += $personal['promedio'];
            $contador++;
            $promedios_personal[] = $personal['promedio'];
        }
        if ($servicio['cantidad'] > 0) {
            $suma += $servicio['promedio'];
            $contador++;
            $promedios_servicio[] = $servicio['promedio'];
        }
        if (!empty($kpi_reclamos['kpi_ventas'])) {
            $suma += $kpi_reclamos['kpi_ventas'];
            $contador++;
            $promedios_kpi[] = $kpi_reclamos['kpi_ventas'];
            $suma_kpi_total += $kpi_reclamos['kpi_ventas'];
            $contador_kpi_total++;
        }
        
        $general_sucursal = $suma / $contador;
        $suma_general_sucursales += $general_sucursal;
        $contador_sucursales_con_datos++;
    }
    
    $total_auditorias += $limpieza['cantidad'] + $personal['cantidad'] + $servicio['cantidad'];
}

// Calcular promedios totales por tipo (solo si hay datos)
$promedio_limpieza_total = count($promedios_limpieza) > 0 ? array_sum($promedios_limpieza) / count($promedios_limpieza) : 0;
$promedio_personal_total = count($promedios_personal) > 0 ? array_sum($promedios_personal) / count($promedios_personal) : 0;
$promedio_servicio_total = count($promedios_servicio) > 0 ? array_sum($promedios_servicio) / count($promedios_servicio) : 0;
// CORRECCIÓN: Calcular promedio KPI total de forma más robusta
$promedio_kpi_total = $contador_kpi_total > 0 ? $suma_kpi_total / $contador_kpi_total : 0;

// Calcular promedio general total (promedio de los promedios por tipo)
$suma_promedios_tipos = 0;
$contador_tipos_con_datos = 0;

if (count($promedios_limpieza) > 0) {
    $suma_promedios_tipos += $promedio_limpieza_total;
    $contador_tipos_con_datos++;
}
if (count($promedios_personal) > 0) {
    $suma_promedios_tipos += $promedio_personal_total;
    $contador_tipos_con_datos++;
}
if (count($promedios_servicio) > 0) {
    $suma_promedios_tipos += $promedio_servicio_total;
    $contador_tipos_con_datos++;
}
if (count($promedios_kpi) > 0) {
    $suma_promedios_tipos += $promedio_kpi_total;
    $contador_tipos_con_datos++;
}

$general_total = $contador_tipos_con_datos > 0 ? $suma_promedios_tipos / $contador_tipos_con_datos : 0;

// Calcular porcentaje general
$porcentaje_general = $general_total * 20; // (promedio/5)*100

// Determinar colores para los totales
$color_limpieza_total = getColorClass($promedio_limpieza_total);
$color_personal_total = getColorClass($promedio_personal_total);
$color_servicio_total = getColorClass($promedio_servicio_total);
$color_kpi_total = getColorClass($promedio_kpi_total);
$color_general_total = getColorClass($general_total);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desempeño Acumulado</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            text-align: center;
            align-content: center;
            align-items: center;
            justify-content: center;
            font-size: clamp(11px, 2vw, 16px);
        }

        body {
            margin-top: 25px;
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 99%;
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

        .contenedor-principal {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1px;
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
            background-color: #0E544C;
            color: white;
        }

        /* Estilos para los círculos de color */
        .color-circle {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .rojo {
            background-color: #FF6F61;
        }

        .amarillo {
            background-color: #FFD166;
        }

        .verde {
            background-color: #06D6A0;
        }

        /* Estilos para los filtros */
        .filtros-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filtro {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filtro select, .filtro button {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .filtro button {
            background-color: #51B8AC;
            color: white;
            border: none;
            cursor: pointer;
        }

        .filtro button:hover {
            background-color: #0E544C;
        }
        
        @media (max-width: 768px) {
            *{
                font-size: 10px;
            }
            
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
    
    .btn-agregar {
        padding: 6px 10px;
        font-size: 13px;
    }
        }
        
        @media (max-width: 480px) {
            *{
                font-size: 8px;
            }
            
    .btn-agregar {
        flex-grow: 1;
        justify-content: center;
        white-space: normal;
        text-align: center;
        padding: 8px 5px;
    }
    
    .user-info {
        flex-direction: column;
        align-items: flex-end;
    }
        }

        /* Estilo para la fila de totales */
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        
        .cantidad-auditorias {
            font-size: 0.8em;
            opacity: 0.8;
        }
        
        /* Estilo para la columna General */
        td:nth-child(6), td:nth-child(7) {
            background-color: #f0f0f0;
        }
        
        /* Estilo para las columnas de Reclamos */
        td:nth-child(8), td:nth-child(9) {
            background-color: #f8f8f8;
        }
        
        /* Estilos para el formulario de edición */
        .form-edicion {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .form-edicion input {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
            width: 100%;
            box-sizing: border-box;
        }
        
        .form-edicion button {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 8px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 5px;
        }
        
        .form-edicion button:hover {
            background-color: #0E544C;
        }
        
        /* Estilos para mensajes */
        .success-message {
            background-color: #06D6A0;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .error-message {
            background-color: #FF6F61;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        /* Estilos para celdas editables */
        .editable {
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .editable:hover {
            background-color: #e6f7f5;
        }
        
        .editando {
            background-color: #e6f7f5;
            padding: 0;
        }
        
        /* Estilos para botones de acción */
        .btn-accion {
            background-color: transparent;
            border: none;
            cursor: pointer;
            color: #51B8AC;
            margin-left: 5px;
        }
        
        .btn-accion:hover {
            color: #0E544C;
        }
        
        /* Nuevos estilos para edición en tabla */
        .celda-editable {
            cursor: pointer;
            transition: background-color 0.3s;
            position: relative;
        }
        
        .celda-editable:hover {
            background-color: #e6f7f5;
        }
        
        .celda-editando {
            background-color: #e6f7f5;
            padding: 0;
        }
        
        .input-edicion {
            width: 100%;
            height: 100%;
            border: none;
            background: transparent;
            text-align: center;
            font-size: inherit;
            padding: 10px;
            box-sizing: border-box;
        }
        
        .botones-edicion {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: 5px;
        }
        
        .btn-editar, .btn-cancelar-edicion {
            background: #51B8AC;
            color: white;
            border: none;
            border-radius: 3px;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-cancelar-edicion {
            background: #FF6F61;
        }
        
        /* Nuevos estilos para el sistema de edición */
        .editable-kpi, .editable-cantidad, .editable-porcentaje {
            cursor: pointer;
            transition: background-color 0.3s;
            position: relative;
        }
        
        .editable-kpi:hover, .editable-cantidad:hover, .editable-porcentaje:hover {
            background-color: #e6f7f5;
        }
        
        .celda-editando {
            background-color: #e6f7f5;
            padding: 8px !important;
        }
        
        .contenedor-edicion {
            display: flex;
            align-items: center;
            position: relative;
            width: 100%;
        }
        
        .input-edicion {
            width: calc(100% - 50px);
            padding: 5px 40px 5px 5px !important;
            border: 1px solid #51B8AC;
            border-radius: 4px;
            text-align: center;
            font-size: inherit;
            box-sizing: border-box;
        }
        
        .botones-edicion {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            gap: 3px;
        }
        
        .btn-editar, .btn-cancelar-edicion {
            width: 22px;
            height: 22px;
            padding: 0;
            border: none;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 10px;
        }
        
        .btn-editar {
            background-color: #51B8AC;
            color: white;
        }
        
        .btn-cancelar-edicion {
            background-color: #FF6F61;
            color: white;
        }
        
        .no-editable {
            cursor: not-allowed;
            background-color: #f5f5f5;
        }
        
        /* Estilos para tooltip personalizado */
        .tooltip-container {
            position: relative;
            display: inline-block;
        }
        
        .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }
        
        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        
        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        /* Indicador visual de que hay información adicional */
        .has-info::after {
            content: "ⓘ";
            font-size: 10px;
            color: #51B8AC;
            margin-left: 3px;
            cursor: help;
        }
    </style>
</head>
<body>
    <div class="contenedor-principal">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="/core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <a href="index_avisos.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'index_avisos.php' ? 'activo' : '' ?>">
                        <i class="fas fa-bullhorn"></i> <span class="btn-text">Nuevo Aviso</span>
                    </a>
                    <a href="kpi.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'kpi.php' ? 'activo' : '' ?>">
                        <i class="fas fa-chart-line"></i> <span class="btn-text">KPI</span>
                    </a>
                    <a href="reclamospend.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'reclamospend.php' ? 'activo' : '' ?>">
                        <i class="fas fa-search"></i> <span class="btn-text">Reclamos</span>
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
        
        <h2 style="display:none;">Desempeño Acumulado</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: 'Los datos han sido guardados exitosamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            </script>
        <?php endif; ?>
        
        <!-- Filtros de mes y año -->
        <div class="filtros-container">
            <form method="get" action="kpi.php" class="filtro">
                <label for="mes">Mes:</label>
                <select name="mes" id="mes">
                    <?php
                    $meses = [
                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                    ];
                    
                    foreach ($meses as $num => $nombre) {
                        $selected = ($num == $mes_seleccionado) ? 'selected' : '';
                        echo "<option value='$num' $selected>$nombre</option>";
                    }
                    ?>
                </select>
                
                <label for="anio">Año:</label>
                <select name="anio" id="anio">
                    <?php
                    $anio_actual = date('Y');
                    for ($i = $anio_actual; $i >= $anio_actual - 5; $i--) {
                        $selected = ($i == $anio_seleccionado) ? 'selected' : '';
                        echo "<option value='$i' $selected>$i</option>";
                    }
                    ?>
                </select>
                
                <button type="submit">Filtrar</button>
            </form>
        </div>
        
        <!-- Tabla de resultados -->
        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="text-align:center;">Sucursal</th>
                    <th rowspan="2" style="text-align:center;">KPI Ventas</th>
                    <th colspan="2" style="text-align:center; display:none;">Reclamos</th>
                </tr>
                <tr>
                    <th style="text-align:center; display:none;">Cantidad</th>
                    <th style="text-align:center; display:none;">%</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sucursales as $cod_sucursal => $nombre_sucursal): 
                    $sucursal = $nombre_sucursal;
                    // Obtener datos para esta sucursal
                    $limpieza = $limpieza_data[$sucursal][0] ?? ['promedio' => 0, 'cantidad' => 0];
                    $personal = $personal_data[$sucursal][0] ?? ['promedio' => 0, 'cantidad' => 0];
                    $servicio = $servicio_data[$sucursal][0] ?? ['promedio' => 0, 'cantidad' => 0];
                    $kpi_reclamos = $kpi_reclamos_data[$cod_sucursal] ?? ['kpi_ventas' => null, 'reclamos_cantidad' => null, 'reclamos_porcentaje' => null];
                    
                    // Calcular promedio general (solo si hay al menos un tipo de auditoría)
                    $general = 0;
                    $mostrar_general = false;
                    
                    if ($limpieza['cantidad'] > 0 || $personal['cantidad'] > 0 || $servicio['cantidad'] > 0 || !empty($kpi_reclamos['kpi_ventas'])) {
                        $suma = 0;
                        $contador = 0;
                        
                        if ($limpieza['cantidad'] > 0) {
                            $suma += $limpieza['promedio'];
                            $contador++;
                        }
                        if ($personal['cantidad'] > 0) {
                            $suma += $personal['promedio'];
                            $contador++;
                        }
                        if ($servicio['cantidad'] > 0) {
                            $suma += $servicio['promedio'];
                            $contador++;
                        }
                        if (!empty($kpi_reclamos['kpi_ventas'])) {
                            $suma += $kpi_reclamos['kpi_ventas'];
                            $contador++;
                        }
                        
                        $general = $suma / $contador;
                        $mostrar_general = true;
                    }
                    
                    // Determinar colores
                    $color_limpieza = getColorClass($limpieza['promedio']);
                    $color_personal = getColorClass($personal['promedio']);
                    $color_servicio = getColorClass($servicio['promedio']);
                    $color_kpi = getColorClass($kpi_reclamos['kpi_ventas'] ?? 0);
                    $color_general = getColorClass($general);
                ?>
                <tr>
                    <td>
                        <?php echo $sucursal; ?>
                        <small style="color: #666; font-size: 0.8em;"> (<?php echo $cod_sucursal; ?>)</small>
                    </td>
                    
                    <!-- Columna Limpieza -->
                    <td style="text-align:center; display:none;">
                        <?php if ($limpieza['cantidad'] > 0): ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color_limpieza; ?>"></span>
                                <?php echo number_format($limpieza['promedio'], 1); ?>
                                <span class="cantidad-auditorias">(<?php echo $limpieza['cantidad']; ?>)</span>
                                <a href="<?php echo getVerAuditoriasLink($sucursal, 'limpieza', $mes_seleccionado, $anio_seleccionado); ?>" style="color: #51B8AC; margin-left: 5px; display:none;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Columna Personal -->
                    <td style="text-align:center; display:none;">
                        <?php if ($personal['cantidad'] > 0): ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color_personal; ?>"></span>
                                <?php echo number_format($personal['promedio'], 1); ?>
                                <span class="cantidad-auditorias">(<?php echo $personal['cantidad']; ?>)</span>
                                <a href="<?php echo getVerAuditoriasLink($sucursal, 'personal', $mes_seleccionado, $anio_seleccionado); ?>" style="color: #51B8AC; margin-left: 5px; display:none;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Columna Servicio -->
                    <td style="text-align:center; display:none;">
                        <?php if ($servicio['cantidad'] > 0): ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color_servicio; ?>"></span>
                                <?php echo number_format($servicio['promedio'], 1); ?>
                                <span class="cantidad-auditorias">(<?php echo $servicio['cantidad']; ?>)</span>
                                <a href="<?php echo getVerAuditoriasLink($sucursal, 'servicio', $mes_seleccionado, $anio_seleccionado); ?>" style="color: #51B8AC; margin-left: 5px; display:none;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Columna KPI Ventas -->
                    <td style="text-align:center;" 
                        class="editable-kpi" 
                        onclick="iniciarEdicionKPI(this, '<?php echo $nombre_sucursal; ?>', '<?php echo $cod_sucursal; ?>', <?php echo $mes_seleccionado; ?>, <?php echo $anio_seleccionado; ?>)"
                        data-sucursal="<?php echo $sucursal; ?>"
                        data-cod-sucursal="<?php echo $cod_sucursal; ?>"
                        data-mes="<?php echo $mes_seleccionado; ?>"
                        data-anio="<?php echo $anio_seleccionado; ?>"
                    >
                        <?php if (isset($kpi_reclamos['kpi_ventas'])): ?>
                            <div style="display: flex; align-items: center; justify-content: center;" class="<?php echo isset($kpi_reclamos['fecha_actualizacion']) ? 'tooltip-container has-info' : ''; ?>">
                                <span class="color-circle <?php echo $color_kpi; ?>"></span>
                                <?php echo number_format($kpi_reclamos['kpi_ventas'], 2); ?>
                                <?php if (isset($kpi_reclamos['fecha_actualizacion'])): ?>
                                    <span class="tooltip-text">
                                        Última modificación: <?php echo date('d/m/Y H:i', strtotime($kpi_reclamos['fecha_actualizacion'])); ?>
                                        <?php echo $kpi_reclamos['operario_nombre'] ? ' por ' . htmlspecialchars($kpi_reclamos['operario_nombre'] . ' ' . $kpi_reclamos['operario_apellido']) : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle"></span>
                                (Agregar)
                            </div>
                        <?php endif; ?>
                    </td>
                    
                    <!-- Columnas no editables (se mantienen igual) -->
                    <td style="text-align:center; display:none;">
                        <?php if ($mostrar_general): ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color_general; ?>"></span>
                                <?php echo number_format($general, 1); ?>
                                <a href="auditorias_combinadas.php?sucursal=<?php echo urlencode($sucursal); ?>&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>" style="color: #51B8AC; margin-left: 5px;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <td style="text-align:center; display:none;">
                        <?php if ($mostrar_general): ?>
                            <?php echo ($general < 4) ? '0%' : number_format($general * 20, 1) . '%'; ?>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Columna Reclamos Cantidad -->
                    <td style="text-align:center; display:none;" 
                        class="editable-cantidad" 
                        onclick="iniciarEdicionReclamos(this, '<?php echo $sucursal; ?>', '<?php echo $cod_sucursal; ?>', <?php echo $mes_seleccionado; ?>, <?php echo $anio_seleccionado; ?>, 'cantidad')"
                        data-sucursal="<?php echo $sucursal; ?>"
                        data-cod-sucursal="<?php echo $cod_sucursal; ?>" <!-- NUEVO -->
                        data-mes="<?php echo $mes_seleccionado; ?>"
                        data-anio="<?php echo $anio_seleccionado; ?>">
                        <?php echo isset($kpi_reclamos['reclamos_cantidad']) ? $kpi_reclamos['reclamos_cantidad'] : '--'; ?>
                    </td>
                    
                    <!-- Columna Reclamos Porcentaje -->
                    <td style="text-align:center; display:none;" 
                        class="editable-porcentaje" 
                        onclick="iniciarEdicionReclamos(this, '<?php echo $sucursal; ?>', '<?php echo $cod_sucursal; ?>', <?php echo $mes_seleccionado; ?>, <?php echo $anio_seleccionado; ?>, 'porcentaje')"
                        data-sucursal="<?php echo $sucursal; ?>"
                        data-cod-sucursal="<?php echo $cod_sucursal; ?>" <!-- NUEVO -->
                        data-mes="<?php echo $mes_seleccionado; ?>"
                        data-anio="<?php echo $anio_seleccionado; ?>">
                        <?php echo isset($kpi_reclamos['reclamos_porcentaje']) ? number_format($kpi_reclamos['reclamos_porcentaje'], 1) . '%' : '--'; ?>
                    </td>
                    
                    <td style="text-align:center; display:none;">
                        <?php 
                        if ($mostrar_general && isset($kpi_reclamos['reclamos_porcentaje'])) {
                            $porcentaje_general = ($general < 4) ? 0 : $general * 20;
                            $porcentaje_promedio = ($porcentaje_general + $kpi_reclamos['reclamos_porcentaje']) / 2;
                            echo number_format($porcentaje_promedio, 1) . '%';
                        } elseif ($mostrar_general) {
                            $porcentaje_general = ($general < 4) ? 0 : $general * 20;
                            echo number_format($porcentaje_general, 1) . '%';
                        } elseif (isset($kpi_reclamos['reclamos_porcentaje'])) {
                            echo number_format($kpi_reclamos['reclamos_porcentaje'], 1) . '%';
                        } else {
                            echo '--';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Fila de totales -->
                <tr class="total-row">
                    <td style="text-align:center;">Total</td>
                    
                    <!-- Total Limpieza -->
                    <td style="text-align:center; display:none;">
                        <?php if (count($promedios_limpieza) > 0): ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color_limpieza_total; ?>"></span>
                                <?php echo number_format($promedio_limpieza_total, 1); ?>
                                <span style="margin-left: 5px; display:none;">(<?php echo $limpieza_total['cantidad_total']; ?>)</span>
                                <a href="ver_auditorias.php?tipo=limpieza&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>" style="color: #51B8AC; margin-left: 5px; display:none;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Total Personal -->
                    <td style="text-align:center; display:none;">
                        <?php if (count($promedios_personal) > 0): ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color_personal_total; ?>"></span>
                                <?php echo number_format($promedio_personal_total, 1); ?>
                                <span style="margin-left: 5px; display:none;">(<?php echo $personal_total['cantidad_total']; ?>)</span>
                                <a href="ver_auditorias.php?tipo=personal&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>" style="color: #51B8AC; margin-left: 5px; display:none;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Total Servicio -->
                    <td style="text-align:center; display:none;">
                        <?php if (count($promedios_servicio) > 0): ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color_servicio_total; ?>"></span>
                                <?php echo number_format($promedio_servicio_total, 1); ?>
                                <span style="margin-left: 5px; display:none;">(<?php echo $servicio_total['cantidad_total']; ?>)</span>
                                <a href="ver_auditorias.php?tipo=servicio&mes=<?php echo $mes_seleccionado; ?>&anio=<?php echo $anio_seleccionado; ?>" style="color: #51B8AC; margin-left: 5px; display:none;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Total KPI Ventas -->
                    <td style="text-align:center;">
                        <?php if ($contador_kpi_total > 0): ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color_kpi_total; ?>"></span>
                                <?php echo number_format($promedio_kpi_total, 2); ?>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Total General - Promedio -->
                    <td style="text-align:center; display:none;">
                        <?php if ($contador_tipos_con_datos > 0): ?>
                            <div style="display: flex; align-items: center; justify-content: center;">
                                <span class="color-circle <?php echo $color_general_total; ?>"></span>
                                <?php echo number_format($general_total, 1); ?>
                            </div>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Total General - % -->
                    <td style="text-align:center; background:#f8f8f8!important; display:none;">
                        <?php if ($contador_tipos_con_datos > 0): ?>
                            <?php echo number_format($porcentaje_general, 1); ?>%
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                    
                    <!-- Total Reclamos -->
                    <td style="text-align:center; display:none;">
                        <?php echo $reclamos_total['cantidad_total'] > 0 ? $reclamos_total['cantidad_total'] : '--'; ?>
                    </td>
                    <td style="text-align:center; display:none;">
                        <?php echo $reclamos_total['porcentaje_total'] > 0 ? number_format($reclamos_total['porcentaje_total'], 1) . '%' : '--'; ?>
                    </td>
                    
                    <td style="text-align:center; display:none;">
                        <?php
                        if ($contador_tipos_con_datos > 0 && $reclamos_total['porcentaje_total'] > 0) {
                            $porcentaje_general_total = ($general_total < 4) ? 0 : $porcentaje_general;
                            $porcentaje_promedio_total = ($porcentaje_general_total + $reclamos_total['porcentaje_total']) / 2;
                            echo number_format($porcentaje_promedio_total, 1) . '%';
                        } elseif ($contador_tipos_con_datos > 0) {
                            $porcentaje_general_total = ($general_total < 4) ? 0 : $porcentaje_general;
                            echo number_format($porcentaje_general_total, 1) . '%';
                        } elseif ($reclamos_total['porcentaje_total'] > 0) {
                            echo number_format($reclamos_total['porcentaje_total'], 1) . '%';
                        } else {
                            echo '--';
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <script>
        // Variable para controlar la celda en edición
        let celdaEditando = null;
        let valorOriginal = null;
    
        // Función para iniciar edición de KPI Ventas - MODIFICADA
        function iniciarEdicionKPI(celda, sucursal, cod_sucursal, mes, anio) {
            // Cerrar edición actual si existe
            if (celdaEditando && celdaEditando !== celda) {
                cancelarEdicion();
            }
            
            // Guardar referencia y valor original
            celdaEditando = celda;
            valorOriginal = celda.textContent.includes('Agregar') ? '' : celda.querySelector('div').textContent.trim().split(' ')[0];
            
            // Crear campo de edición
            celda.classList.add('celda-editando');
            celda.innerHTML = `
                <div class="contenedor-edicion">
                    <input type="number" class="input-edicion" min="1" max="5" step="0.01" 
                           value="${valorOriginal}" placeholder="KPI (1.00-5.00)" autofocus>
                    <div class="botones-edicion">
                        <button class="btn-editar" onclick="guardarKPI('${sucursal}', '${cod_sucursal}', ${mes}, ${anio})">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn-cancelar-edicion" onclick="cancelarEdicion()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            celda.querySelector('input').focus();
        }
        
        // Función para iniciar edición de Reclamos - MODIFICADA
        function iniciarEdicionReclamos(celda, sucursal, cod_sucursal, mes, anio, tipo) {
            // Cerrar edición actual si existe
            if (celdaEditando && celdaEditando !== celda) {
                cancelarEdicion();
            }
            
            // Guardar referencia y valor original
            celdaEditando = celda;
            valorOriginal = celda.textContent === '--' ? '' : celda.textContent.replace('%', '').trim();
            
            // Crear campo de edición
            celda.classList.add('celda-editando');
            celda.innerHTML = `
                <div class="contenedor-edicion">
                    <input type="number" class="input-edicion" 
                           ${tipo === 'porcentaje' ? 'min="0" max="100" step="0.01"' : 'min="0"'} 
                           value="${valorOriginal}" placeholder="${tipo === 'cantidad' ? 'Cantidad' : 'Porcentaje'}" autofocus>
                    <div class="botones-edicion">
                        <button class="btn-editar" onclick="guardarReclamos('${sucursal}', '${cod_sucursal}', ${mes}, ${anio}, '${tipo}')">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn-cancelar-edicion" onclick="cancelarEdicion()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            celda.querySelector('input').focus();
        }
        
        // Función para guardar KPI Ventas - MODIFICADA
        function guardarKPI(sucursal, cod_sucursal, mes, anio) {
            if (!celdaEditando) return;
            
            const input = celdaEditando.querySelector('input');
            const valor = parseFloat(input.value);
            
            if (isNaN(valor) || valor < 1 || valor > 5) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El KPI debe ser entre 1.00 y 5.00 con dos decimales',
                    confirmButtonColor: '#51B8AC'
                });
                input.focus();
                return;
            }
            
            // Formatear a 2 decimales
            const valorFormateado = valor.toFixed(2);
            
            // Obtener los valores actuales de los filtros para mostrar en el mensaje
            const mesActual = document.getElementById('mes').value;
            const anioActual = document.getElementById('anio').value;
            const nombreMes = document.getElementById('mes').options[document.getElementById('mes').selectedIndex].text;
            
            Swal.fire({
                title: '¿Guardar cambios?',
                text: `KPI Ventas: ${valorFormateado} para ${sucursal} (${nombreMes} ${anioActual})`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#51B8AC',
                cancelButtonColor: '#FF6F61',
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarDatosKPI(sucursal, cod_sucursal, mesActual, anioActual, valorFormateado);
                }
            });
        }
        
        // Función para enviar datos de KPI - MODIFICADA
        function enviarDatosKPI(sucursal, cod_sucursal, mes, anio, valor) {
            // Obtener los valores ACTUALES de los filtros
            const mesActual = document.getElementById('mes').value;
            const anioActual = document.getElementById('anio').value;
            
            const formData = new FormData();
            formData.append('guardar_edicion', true);
            formData.append('sucursal', sucursal);
            formData.append('cod_sucursal', cod_sucursal);
            formData.append('mes', mesActual); // Usar el valor actual del filtro
            formData.append('anio', anioActual); // Usar el valor actual del filtro
            formData.append('kpi_ventas', valor);
            
            fetch('kpi.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    throw new Error('Error en la respuesta');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo guardar',
                    confirmButtonColor: '#51B8AC'
                });
            });
        }
        
        // Función para guardar Reclamos - MODIFICADA
        function guardarReclamos(sucursal, cod_sucursal, mes, anio, tipo) {
            if (!celdaEditando) return;
            
            const input = celdaEditando.querySelector('input');
            const valor = tipo === 'cantidad' ? parseInt(input.value) : parseFloat(input.value);
            
            // Validaciones
            if (isNaN(valor)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ingrese un valor válido',
                    confirmButtonColor: '#51B8AC'
                });
                input.focus();
                return;
            }
            
            if (tipo === 'porcentaje' && (valor < 0 || valor > 100)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'El porcentaje debe ser 0-100',
                    confirmButtonColor: '#51B8AC'
                });
                input.focus();
                return;
            }
            
            if (tipo === 'cantidad' && valor < 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'La cantidad debe ser ≥ 0',
                    confirmButtonColor: '#51B8AC'
                });
                input.focus();
                return;
            }
            
            // Obtener los valores actuales de los filtros para mostrar en el mensaje
            const mesActual = document.getElementById('mes').value;
            const anioActual = document.getElementById('anio').value;
            const nombreMes = document.getElementById('mes').options[document.getElementById('mes').selectedIndex].text;
            
            Swal.fire({
                title: '¿Guardar cambios?',
                text: `${tipo === 'cantidad' ? 'Cantidad' : 'Porcentaje'}: ${valor}${tipo === 'porcentaje' ? '%' : ''} para ${sucursal} (${nombreMes} ${anioActual})`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#51B8AC',
                cancelButtonColor: '#FF6F61',
                confirmButtonText: 'Guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarDatosReclamos(sucursal, cod_sucursal, mesActual, anioActual, tipo, valor);
                }
            });
        }
        
        // Función para enviar datos de Reclamos - MODIFICADA
        function enviarDatosReclamos(sucursal, cod_sucursal, mes, anio, tipo, valor) {
            // Obtener los valores ACTUALES de los filtros
            const mesActual = document.getElementById('mes').value;
            const anioActual = document.getElementById('anio').value;
            
            const formData = new FormData();
            formData.append('guardar_edicion', true);
            formData.append('sucursal', sucursal);
            formData.append('cod_sucursal', cod_sucursal);
            formData.append('mes', mesActual); // Usar el valor actual del filtro
            formData.append('anio', anioActual); // Usar el valor actual del filtro
            
            // Solo enviar el campo que estamos editando
            if (tipo === 'cantidad') {
                formData.append('reclamos_cantidad', valor);
                // No enviar porcentaje para que mantenga su valor actual
            } else {
                formData.append('reclamos_porcentaje', valor);
                // No enviar cantidad para que mantenga su valor actual
            }
            
            fetch('kpi.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    location.reload();
                } else {
                    throw new Error('Error en la respuesta');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo guardar',
                    confirmButtonColor: '#51B8AC'
                });
            });
        }
        
        // Función para cancelar edición - MODIFICADA
        function cancelarEdicion() {
            if (!celdaEditando) return;
            
            const sucursal = celdaEditando.getAttribute('data-sucursal');
            const cod_sucursal = celdaEditando.getAttribute('data-cod-sucursal');
            const mes = celdaEditando.getAttribute('data-mes');
            const anio = celdaEditando.getAttribute('data-anio');
            
            // Restaurar contenido basado en tipo de celda
            if (celdaEditando.classList.contains('editable-kpi')) {
                celdaEditando.classList.remove('celda-editando');
                celdaEditando.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: center;">
                        <span class="color-circle"></span>
                        ${valorOriginal || '(Agregar)'}
                    </div>
                `;
                celdaEditando.onclick = function() { 
                    iniciarEdicionKPI(this, sucursal, cod_sucursal, mes, anio); 
                };
            } 
            else if (celdaEditando.classList.contains('editable-cantidad')) {
                celdaEditando.classList.remove('celda-editando');
                celdaEditando.textContent = valorOriginal || '--';
                celdaEditando.onclick = function() { 
                    iniciarEdicionReclamos(this, sucursal, cod_sucursal, mes, anio, 'cantidad'); 
                };
            }
            else if (celdaEditando.classList.contains('editable-porcentaje')) {
                celdaEditando.classList.remove('celda-editando');
                celdaEditando.textContent = valorOriginal ? valorOriginal + '%' : '--';
                celdaEditando.onclick = function() { 
                    iniciarEdicionReclamos(this, sucursal, cod_sucursal, mes, anio, 'porcentaje'); 
                };
            }
            
            // Limpiar variables
            celdaEditando = null;
            valorOriginal = null;
        }
        
        // Manejo de teclado - MODIFICADO
        document.addEventListener('keydown', function(e) {
            if (!celdaEditando) return;
            
            const input = celdaEditando.querySelector('input');
            if (!input) return;
            
            if (e.key === 'Enter') {
                const botonGuardar = celdaEditando.querySelector('.btn-editar');
                if (botonGuardar) {
                    // Obtener los valores actuales de los filtros
                    const mesActual = document.getElementById('mes').value;
                    const anioActual = document.getElementById('anio').value;
                    
                    if (celdaEditando.classList.contains('editable-kpi')) {
                        const sucursal = celdaEditando.getAttribute('data-sucursal');
                        const cod_sucursal = celdaEditando.getAttribute('data-cod-sucursal');
                        guardarKPI(sucursal, cod_sucursal, mesActual, anioActual);
                    } else {
                        const sucursal = celdaEditando.getAttribute('data-sucursal');
                        const cod_sucursal = celdaEditando.getAttribute('data-cod-sucursal');
                        const tipo = celdaEditando.classList.contains('editable-cantidad') ? 'cantidad' : 'porcentaje';
                        guardarReclamos(sucursal, cod_sucursal, mesActual, anioActual, tipo);
                    }
                }
            } else if (e.key === 'Escape') {
                cancelarEdicion();
            }
        });
    </script>
</body>
</html>
