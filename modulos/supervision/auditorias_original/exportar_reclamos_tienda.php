<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once 'conexion.php';

// Verificar acceso al módulo 'operaciones'
//verificarAccesoModulo('operaciones');

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([11, 16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Configurar zona horaria
date_default_timezone_set('America/Managua');

// Obtener parámetros de filtro
$anio_seleccionado = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');
$sucursal_seleccionada = isset($_GET['sucursal']) ? $_GET['sucursal'] : null;

// Meses fijos a exportar: mayo (5), junio (6), julio (7)
$meses_a_exportar = [5, 6, 7];

// Configurar headers para descarga Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="reclamos_culpabilidad_tienda_MJJ_'.$anio_seleccionado.'.xls"');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Consulta SQL para reclamos con resolución final "Equipo de Tienda"
    $sql = "
        SELECT 
            r.id AS 'ID_Reclamo',
            DATE_FORMAT(r.fecha_hora, '%d-%b-%Y %h:%i %p') AS 'Fecha_Registro',
            r.sucursal AS 'Sucursal',
            r.tipo_reclamo AS 'Tipo_Reclamo_Inicial',
            r.descripcion AS 'Descripcion_Reclamo',
            r.fuente AS 'Fuente_Reclamo',
            r.fecha_evento AS 'Fecha_Evento',
            r.hora_evento AS 'Hora_Evento',
            GROUP_CONCAT(DISTINCT rp.producto SEPARATOR ' | ') AS 'Productos_Involucrados',
            SUM(rp.precio) AS 'Total_Productos',
            r.investigacion_preliminar AS 'Investigacion_Preliminar',
            DATE_FORMAT(ri.fecha_resolucion, '%d-%b-%Y') AS 'Fecha_Resolucion_Final',
            DATEDIFF(ri.fecha_resolucion, r.fecha_registro) AS 'Dias_Investigacion',
            ri.tipo_reclamo_operaciones AS 'Tipo_Reclamo_Final',
            ri.investigacion AS 'Investigacion_Operaciones',
            ri.plan_accion AS 'Plan_Accion_Correctivo',
            GROUP_CONCAT(DISTINCT rc.colaborador SEPARATOR ' | ') AS 'Colaboradores_Responsables',
            GROUP_CONCAT(DISTINCT CONCAT('C$', FORMAT(rc.monto_responsabilidad, 2)) SEPARATOR ' | ') AS 'Montos_Responsabilidad',
            CONCAT('C$', FORMAT(SUM(rc.monto_responsabilidad), 2)) AS 'Total_Responsabilidad'
        FROM 
            reclamos r
        LEFT JOIN 
            reclamos_productos rp ON r.id = rp.reclamo_id
        INNER JOIN 
            reportes_investigacion ri ON r.id = ri.reclamo_id 
        LEFT JOIN 
            reportes_colaboradores rc ON ri.id = rc.reporte_id
        WHERE 
            MONTH(r.fecha_registro) IN (".implode(',', $meses_a_exportar).") 
            AND YEAR(r.fecha_registro) = :anio
            AND ri.resolucion = 'Equipo de Tienda'  -- Solo reclamos donde Operaciones determinó culpabilidad de tienda
    ";
    
    // Filtro por sucursal
    if ($sucursal_seleccionada && $sucursal_seleccionada != 'todas') {
        $sql .= " AND r.sucursal = :sucursal";
    }
    
    $sql .= "
        GROUP BY 
            r.id
        ORDER BY 
            r.sucursal, 
            MONTH(r.fecha_registro), 
            r.fecha_registro DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':anio', $anio_seleccionado, PDO::PARAM_INT);
    
    if ($sucursal_seleccionada && $sucursal_seleccionada != 'todas') {
        $stmt->bindValue(':sucursal', $sucursal_seleccionada, PDO::PARAM_STR);
    }
    
    $stmt->execute();

    // Generar Excel
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reclamos con Culpabilidad de Tienda MJJ '.$anio_seleccionado.'</title></head><body>';
    
    // Títulos
    echo '<h2 style="text-align:center;">RECLAMOS CON DETERMINACIÓN DE CULPABILIDAD</h2>';
    echo '<h3 style="text-align:center;">Equipo de Tienda - Mayo/Junio/Julio '.$anio_seleccionado.'</h3>';
    
    if ($sucursal_seleccionada && $sucursal_seleccionada != 'todas') {
        echo '<h4 style="text-align:center;">Sucursal: '.htmlspecialchars($sucursal_seleccionada).'</h4>';
    }
    
    // Tabla
    echo '<table border="1" style="width:100%; border-collapse:collapse;">';
    
    // Encabezados
    echo '<tr style="background-color: #4CAF50; color: white; font-weight: bold;">';
    echo '<th>ID</th>';
    echo '<th>Fecha Registro</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fuente</th>';
    echo '<th>Tipo Reclamo</th>';
    echo '<th>Descripción</th>';
    echo '<th>Fecha Evento</th>';
    echo '<th>Productos</th>';
    echo '<th>Total Productos</th>';
    echo '<th>Investigación Preliminar</th>';
    echo '<th>Fecha Resolución</th>';
    echo '<th>Días Investigación</th>';
    echo '<th>Tipo Final</th>';
    echo '<th>Investigación Operaciones</th>';
    echo '<th>Plan Acción</th>';
    echo '<th>Colaboradores Responsables</th>';
    echo '<th>Montos Responsabilidad</th>';
    echo '<th>Total Responsabilidad</th>';
    echo '</tr>';

    // Datos
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '<tr>';
        echo '<td>'.htmlspecialchars($row['ID_Reclamo']).'</td>';
        echo '<td>'.htmlspecialchars($row['Fecha_Registro']).'</td>';
        echo '<td>'.htmlspecialchars($row['Sucursal']).'</td>';
        echo '<td>'.htmlspecialchars($row['Fuente_Reclamo']).'</td>';
        echo '<td>'.htmlspecialchars($row['Tipo_Reclamo_Inicial']).'</td>';
        echo '<td style="text-align:left;">'.nl2br(htmlspecialchars($row['Descripcion_Reclamo'])).'</td>';
        echo '<td>'.htmlspecialchars($row['Fecha_Evento']).' '.htmlspecialchars($row['Hora_Evento']).'</td>';
        echo '<td>'.htmlspecialchars($row['Productos_Involucrados']).'</td>';
        echo '<td style="text-align:right;">'.htmlspecialchars($row['Total_Productos']).'</td>';
        echo '<td style="text-align:left;">'.nl2br(htmlspecialchars($row['Investigacion_Preliminar'])).'</td>';
        echo '<td>'.htmlspecialchars($row['Fecha_Resolucion_Final']).'</td>';
        echo '<td>'.htmlspecialchars($row['Dias_Investigacion']).' días</td>';
        echo '<td>'.htmlspecialchars($row['Tipo_Reclamo_Final']).'</td>';
        echo '<td style="text-align:left;">'.nl2br(htmlspecialchars($row['Investigacion_Operaciones'])).'</td>';
        echo '<td style="text-align:left;">'.nl2br(htmlspecialchars($row['Plan_Accion_Correctivo'])).'</td>';
        echo '<td>'.htmlspecialchars($row['Colaboradores_Responsables']).'</td>';
        echo '<td>'.htmlspecialchars($row['Montos_Responsabilidad']).'</td>';
        echo '<td style="text-align:right; font-weight:bold;">'.htmlspecialchars($row['Total_Responsabilidad']).'</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';

} catch (PDOException $e) {
    die("Error al generar reporte: " . $e->getMessage());
}
