<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// Configuración inicial y autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../../core/helpers/funciones.php'; // Antes llamaba a ../funciones.php de auditora
// require_once 'config.php'; // Comentado por migración al core

// Establecer conexión a la base de datos
// $db = conectarDB(); // Comentado por migración al core
$db = $conn;

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([8, 11, 16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 21]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

// Obtener lista de operarios para el filtro
$sql_operarios = "SELECT o.CodOperario, 
                 CONCAT(
                     IFNULL(o.Nombre, ''), ' ', 
                     IFNULL(o.Nombre2, ''), ' ', 
                     IFNULL(o.Apellido, ''), ' ', 
                     IFNULL(o.Apellido2, '')
                 ) AS nombre_completo 
                 FROM Operarios o
                 LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                 WHERE (anc.CodNivelesCargos IS NULL OR anc.CodNivelesCargos != 27)
                 AND o.Operativo = 1
                 GROUP BY o.CodOperario
                 ORDER BY nombre_completo";
$operarios = $db->query($sql_operarios)->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de sucursales para el filtro
$sql_sucursales = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 ORDER BY nombre";
$sucursales = $db->query($sql_sucursales)->fetchAll(PDO::FETCH_ASSOC);

// Obtener parámetros de los filtros
$operario_id = isset($_GET['operario']) ? intval($_GET['operario']) : 0;
$sucursal_id = isset($_GET['sucursal']) ? $_GET['sucursal'] : 'todas';
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01'); // Primer día del mes actual por defecto
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');  // Fecha actual por defecto
$tipo_seleccionado = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';

// Validar las selecciones
$tipos_permitidos = ['todos', 'facturacion', 'caja_chica', 'inventario', 'faltante_inventario', 'faltante_danos', 'faltante_caja'];
if (!in_array($tipo_seleccionado, $tipos_permitidos)) {
    $tipo_seleccionado = 'todos';
}

// Validar que fecha_desde no sea mayor que fecha_hasta
if (!empty($fecha_desde) && !empty($fecha_hasta) && $fecha_desde > $fecha_hasta) {
    $fecha_desde = $fecha_hasta;
}

// Construir URL base para el botón de limpiar filtros
$url_limpiar_filtros = 'auditorias_consolidadas.php';

// Construir la consulta SQL base, corregida y sin duplicados
$sql = "
    SELECT * FROM (
        -- Auditoría de Facturación
        SELECT 
            id, 
            fecha_hora_regsys AS fecha_hora, 
            sucursal, 
            'facturacion' AS tipo_auditoria,
            faltante_sobrante AS monto_faltante,
            'ver_auditorias_facturacion.php' AS url_ver,
            cajero AS operario_id,
            sucursal_id
        FROM auditoria_facturacion
        
        UNION ALL
        
        -- Auditoría de Caja Chica
        SELECT 
            id, 
            fecha_hora_regsys AS fecha_hora, 
            sucursal, 
            'caja_chica' AS tipo_auditoria,
            faltante_sobrante AS monto_faltante,
            'ver_auditorias_caja_chica.php' AS url_ver,
            lider_tienda_codigo AS operario_id,
            sucursal_id
        FROM auditoria_caja_chica
        
        UNION ALL
        
        -- Auditoría de Inventario CORREGIDA (sin JOIN con operarios)
        SELECT 
            ai.id, 
            ai.fecha_hora_regsys AS fecha_hora, 
            ai.sucursal, 
            'inventario' AS tipo_auditoria,
            ai.total_faltante AS monto_faltante,
            'ver_auditorias_inventario.php' AS url_ver,
            NULL AS operario_id, -- No asociamos a un operario específico para evitar duplicados
            ai.sucursal_id
        FROM auditoria_inventario ai
        
        UNION ALL
        
        -- Faltante de Inventario CORREGIDO (sin JOIN con operarios)
        SELECT 
            fi.id, 
            fi.fecha_hora_regsys AS fecha_hora, 
            fi.sucursal, 
            'faltante_inventario' AS tipo_auditoria,
            fi.total_faltante AS monto_faltante,
            'ver_faltante_inventario.php' AS url_ver,
            NULL AS operario_id, -- No asociamos a un operario específico para evitar duplicados
            fi.sucursal_id
        FROM faltante_inventario fi
        
        UNION ALL
        
        -- Faltante de Daños CORREGIDO (sin JOIN con operarios)
        SELECT 
            fd.id, 
            fd.fecha_hora_regsys AS fecha_hora, 
            fd.sucursal_nombre AS sucursal, 
            'faltante_danos' AS tipo_auditoria,
            fd.valor_faltante AS monto_faltante,
            'ver_faltante_danos.php' AS url_ver,
            NULL AS operario_id, -- No asociamos a un operario específico para evitar duplicados
            fd.sucursal_codigo AS sucursal_id
        FROM faltante_danos fd
        
        UNION ALL
        
        -- Faltante de Caja CORREGIDO
        SELECT 
            fc.id, 
            fc.fecha AS fecha_hora, 
            fc.sucursal, 
            'faltante_caja' AS tipo_auditoria,
            fc.monto AS monto_faltante,
            'ver_faltante_caja.php' AS url_ver,
            fc.operario_id,
            fc.sucursal_id
        FROM faltante_caja fc
    ) AS combined_tables
    WHERE 1=1
";

// Preparar parámetros para la consulta
$params = [];

// Aplicar filtros adicionales
if ($tipo_seleccionado != 'todos') {
    $sql .= " AND tipo_auditoria COLLATE utf8mb4_unicode_ci = :tipo";
    $params[':tipo'] = $tipo_seleccionado;
}

if ($sucursal_id != 'todas') {
    // Verificar si es un código numérico o nombre de sucursal
    if (is_numeric($sucursal_id)) {
        $sql .= " AND sucursal_id = :sucursal_id";
        $params[':sucursal_id'] = $sucursal_id;
    } else {
        $sql .= " AND sucursal COLLATE utf8mb4_unicode_ci = :sucursal";
        $params[':sucursal'] = $sucursal_id;
    }
}

if ($operario_id > 0) {
    $sql .= " AND operario_id = :operario_id";
    $params[':operario_id'] = $operario_id;
}

// Aplicar filtros de fecha
if (!empty($fecha_desde)) {
    $sql .= " AND DATE(fecha_hora) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $sql .= " AND DATE(fecha_hora) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

$sql .= " ORDER BY fecha_hora DESC";

// Ejecutar la consulta
try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Verificar si se solicitó la exportación a Excel
if (isset($_GET['exportar_excel'])) {
    // Para Excel, usamos la misma consulta corregida (sin duplicados)
    $sql_excel = $sql;
    
    // Aplicar los mismos filtros
    if ($tipo_seleccionado != 'todos') {
        $sql_excel .= " AND tipo_auditoria = :tipo";
    }

    if ($sucursal_id != 'todas') {
        if (is_numeric($sucursal_id)) {
            $sql_excel .= " AND sucursal_id = :sucursal_id";
        } else {
            $sql_excel .= " AND sucursal = :sucursal";
        }
    }

    if ($operario_id > 0) {
        $sql_excel .= " AND operario_id = :operario_id";
    }

    if (!empty($fecha_desde)) {
        $sql_excel .= " AND DATE(fecha_hora) >= :fecha_desde";
    }

    if (!empty($fecha_hasta)) {
        $sql_excel .= " AND DATE(fecha_hora) <= :fecha_hasta";
    }

    $sql_excel .= " ORDER BY fecha_hora DESC";

    // Ejecutar la consulta para Excel
    try {
        $stmt_excel = $db->prepare($sql_excel);
        $stmt_excel->execute($params);
        $registros_excel = $stmt_excel->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error en la consulta para Excel: " . $e->getMessage());
    }

    // Configurar headers para descarga de archivo Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="auditorias_consolidadas_'.date('Y-m-d').'.xls"');
    
    // Iniciar salida
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Fecha</th>';
    echo '<th>Código</th>'; // NUEVA COLUMNA
    echo '<th>Sucursal</th>';
    echo '<th>Tipo de Auditoria</th>';
    echo '<th>Faltante (C$)</th>';
    echo '</tr>';
    
    foreach ($registros_excel as $registro) {
        // USAR LA FUNCIÓN formatoFecha de funciones.php
        if ($registro['tipo_auditoria'] == 'faltante_caja') {
            // Para faltante_caja, usar la fecha directamente (ya que formatoFecha solo muestra fecha)
            $fecha_formateada = formatoFecha($registro['fecha_hora']);
        } else {
            // Para los demás tipos, usar la función formatoFecha que ya maneja el ajuste
            $fecha_formateada = formatoFecha($registro['fecha_hora']);
        }
        
        // Determinar el tipo de auditoría
        $tipo = $registro['tipo_auditoria'];
        $tipo_text = '';
        
        switch($tipo) {
            case 'facturacion':
                $tipo_text = 'Caja Facturacion';
                break;
            case 'caja_chica':
                $tipo_text = 'Caja Chica';
                break;
            case 'inventario':
                $tipo_text = 'Auditoría Inventario';
                break;
            case 'faltante_inventario':
                $tipo_text = 'Faltante Inventario';
                break;
            case 'faltante_danos':
                $tipo_text = 'Faltante Daños';
                break;
            case 'faltante_caja':
                $tipo_text = 'Faltante de Caja';
                break;
        }
        
        // Formatear monto faltante
        $monto = ($tipo == 'inventario') ? abs($registro['monto_faltante']) : $registro['monto_faltante'];
        $monto_formateado = number_format($monto, 2);
        
        // Obtener código de operario
        $codigo_operario = $registro['operario_id'] ?? '';
        
        echo '<tr>';
        echo '<td>' . $fecha_formateada . '</td>';
        echo '<td>' . $codigo_operario . '</td>'; // NUEVA COLUMNA
        echo '<td>' . $registro['sucursal'] . '</td>';
        echo '<td>' . $tipo_text . '</td>';
        echo '<td>' . $monto_formateado . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

// Generar opciones de meses y años para los selectores
$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

$anios = range(2020, date('Y') + 1); // Desde 2020 hasta el próximo año

// Construir URL base para los filtros
$url_base = 'auditorias_consolidadas.php?';
$params = [];
if(isset($_GET['tipo'])) {
    $params[] = 'tipo=' . urlencode($_GET['tipo']);
}
if(isset($_GET['sucursal'])) {
    $params[] = 'sucursal=' . urlencode($_GET['sucursal']);
}
$url_filtros = $url_base . implode('&', $params);

// Verificar si se solicitó la exportación de deducciones por operario
if (isset($_GET['exportar_deducciones'])) {
    try {
        // Consulta para obtener todas las deducciones de los diferentes tipos para el rango de fechas seleccionado
        $sql_deducciones = "
            (SELECT 
                'facturacion' AS tipo,
                af.id,
                af.fecha_hora_regsys AS fecha_evento,
                af.fecha_deduccion,
                af.sucursal_id,
                s.nombre AS sucursal_nombre,
                af.cajero AS operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                af.comentarios,
                af.faltante_sobrante AS monto,
                'ver_auditorias_facturacion.php' AS url_ver
            FROM auditoria_facturacion af
            JOIN Operarios o ON af.cajero = o.CodOperario
            JOIN sucursales s ON af.sucursal_id = s.codigo
            WHERE DATE(af.fecha_hora_regsys) BETWEEN :fecha_desde1 AND :fecha_hasta1 AND af.faltante_sobrante != 0)
            
            UNION ALL
            
            (SELECT 
                'caja_chica' AS tipo,
                acc.id,
                acc.fecha_hora_regsys AS fecha_evento,
                acc.fecha_deduccion,
                acc.sucursal_id,
                s.nombre AS sucursal_nombre,
                acc.lider_tienda_codigo AS operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                acc.comentarios,
                acc.faltante_sobrante AS monto,
                'ver_auditorias_caja_chica.php' AS url_ver
            FROM auditoria_caja_chica acc
            JOIN Operarios o ON acc.lider_tienda_codigo = o.CodOperario
            JOIN sucursales s ON acc.sucursal_id = s.codigo
            WHERE DATE(acc.fecha_hora_regsys) BETWEEN :fecha_desde2 AND :fecha_hasta2 AND acc.faltante_sobrante != 0)
            
            UNION ALL
            
            (SELECT 
                'inventario' AS tipo,
                ai.id,
                ai.fecha_hora_regsys AS fecha_evento,
                aio.fecha_deduccion,
                ai.sucursal_id,
                s.nombre AS sucursal_nombre,
                aio.operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                ai.comentarios,
                aio.monto,
                'ver_auditorias_inventario.php' AS url_ver
            FROM auditoria_inventario ai
            JOIN auditoria_inventario_operarios aio ON ai.id = aio.auditoria_id
            JOIN Operarios o ON aio.operario_id = o.CodOperario
            JOIN sucursales s ON ai.sucursal_id = s.codigo
            WHERE DATE(ai.fecha_hora_regsys) BETWEEN :fecha_desde3 AND :fecha_hasta3 AND aio.monto != 0)
            
            UNION ALL
            
            (SELECT 
                'faltante_inventario' AS tipo,
                fi.id,
                fi.fecha_hora_regsys AS fecha_evento,
                fio.fecha_deduccion,
                fi.sucursal_id,
                s.nombre AS sucursal_nombre,
                fio.operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                fi.comentarios,
                fio.monto,
                'ver_faltante_inventario.php' AS url_ver
            FROM faltante_inventario fi
            JOIN faltante_inventario_operarios fio ON fi.id = fio.faltante_id
            JOIN Operarios o ON fio.operario_id = o.CodOperario
            JOIN sucursales s ON fi.sucursal_id = s.codigo
            WHERE DATE(fi.fecha_hora_regsys) BETWEEN :fecha_desde4 AND :fecha_hasta4 AND fio.monto != 0)
            
            UNION ALL
            
            (SELECT 
                'faltante_danos' AS tipo,
                fd.id,
                fd.fecha_hora_regsys AS fecha_evento,
                fdo.fecha_deduccion,
                fd.sucursal_codigo AS sucursal_id,
                s.nombre AS sucursal_nombre,
                fdo.operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                fd.comentarios,
                fdo.monto,
                'ver_faltante_danos.php' AS url_ver
            FROM faltante_danos fd
            JOIN faltante_danos_operarios fdo ON fd.id = fdo.faltante_id
            JOIN Operarios o ON fdo.operario_id = o.CodOperario
            JOIN sucursales s ON fd.sucursal_codigo = s.codigo
            WHERE DATE(fd.fecha_hora_regsys) BETWEEN :fecha_desde5 AND :fecha_hasta5 AND fdo.monto != 0)
            
            ORDER BY fecha_evento DESC
        ";
        
        $stmt_deducciones = $db->prepare($sql_deducciones);
        
        // Bind de parámetros para cada subconsulta
        $stmt_deducciones->bindValue(':fecha_desde1', $fecha_desde);
        $stmt_deducciones->bindValue(':fecha_hasta1', $fecha_hasta);
        $stmt_deducciones->bindValue(':fecha_desde2', $fecha_desde);
        $stmt_deducciones->bindValue(':fecha_hasta2', $fecha_hasta);
        $stmt_deducciones->bindValue(':fecha_desde3', $fecha_desde);
        $stmt_deducciones->bindValue(':fecha_hasta3', $fecha_hasta);
        $stmt_deducciones->bindValue(':fecha_desde4', $fecha_desde);
        $stmt_deducciones->bindValue(':fecha_hasta4', $fecha_hasta);
        $stmt_deducciones->bindValue(':fecha_desde5', $fecha_desde);
        $stmt_deducciones->bindValue(':fecha_hasta5', $fecha_hasta);
        
        $stmt_deducciones->execute();
        $deducciones = $stmt_deducciones->fetchAll(PDO::FETCH_ASSOC);
        
        // Configurar headers para descarga de archivo Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="deducciones_operarios_'.date('Y-m-d').'.xls"');
        
        // Iniciar salida
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Persona</th>';
        echo '<th>Código</th>';
        echo '<th>Sucursal</th>';
        echo '<th>Fecha Incidente</th>';
        echo '<th>Fecha Deducción</th>';
        echo '<th>Monto a Descontar</th>';
        // echo '<th>Tipo CONCEPTO</th>';
        echo '<th>Detalle</th>';
        echo '</tr>';
        
        foreach ($deducciones as $deduccion) {
            // USAR LA FUNCIÓN formatoFecha
            if ($deduccion['tipo'] == 'faltante_caja') {
                // Para faltante_caja, usar la fecha directamente (ya que formatoFecha solo muestra fecha)
                $fecha_evento_formatted = formatoFecha($deduccion['fecha_evento']);
            } else {
                // Para los demás tipos, usar la función formatoFecha que ya maneja el ajuste
                $fecha_evento_formatted = formatoFecha($deduccion['fecha_evento']);
            }
            
            $fecha_deduccion = '';
            if (!empty($deduccion['fecha_deduccion'])) {
                $fecha_deduccion = formatoFecha($deduccion['fecha_deduccion']);
            }
            
            // Determinar el tipo de auditoría
            $tipo = $deduccion['tipo'];
            $tipo_text = '';
            
            switch($tipo) {
                case 'facturacion':
                    $tipo_text = 'Caja Facturación';
                    break;
                case 'caja_chica':
                    $tipo_text = 'Caja Chica';
                    break;
                case 'inventario':
                    $tipo_text = 'Auditoría Inventario';
                    break;
                case 'faltante_inventario':
                    $tipo_text = 'Faltante Inventario';
                    break;
                case 'faltante_danos':
                    $tipo_text = 'Faltante Daños';
                    break;
                case 'faltante_caja':
                    $tipo_text = 'Faltante de Caja';
                    break;
            }
            
            echo '<tr>';
            // Usar la función mejorada para obtener el nombre completo
            $nombre_completo = obtenerNombreCompletoOperario([
                'Nombre' => $deduccion['operario_nombre'] ?? '',
                'Nombre2' => '',
                'Apellido' => '', 
                'Apellido2' => ''
            ]);
            // Si la función anterior no funciona bien, usar esta alternativa más robusta:
            if (empty(trim($nombre_completo)) || $nombre_completo === 'Nombre no disponible') {
                // Intentar obtener los datos desde la base de datos
                $datos_operario = obtenerDatosCompletosOperario($deduccion['operario_id']);
                if ($datos_operario) {
                    $nombre_completo = obtenerNombreCompletoOperario($datos_operario);
                } else {
                    $nombre_completo = 'Código: ' . $deduccion['operario_id'];
                }
            }
            
            echo '<td>' . htmlspecialchars($nombre_completo) . '</td>';
            echo '<td>' . $deduccion['operario_id'] . '</td>';
            echo '<td>' . $deduccion['sucursal_nombre'] . '</td>';
            echo '<td>' . $fecha_evento_formatted . '</td>';
            echo '<td>' . $fecha_deduccion . '</td>';
            echo '<td>' . number_format(abs($deduccion['monto']), 2) . '</td>';
            // echo '<td>' . $tipo_text . '</td>';
            echo '<td>' . htmlspecialchars($deduccion['comentarios'] ?? '') . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        exit;
        
    } catch (PDOException $e) {
        die("Error en la consulta de deducciones: " . $e->getMessage());
    }
}

// Calcular el total de faltantes según los filtros aplicados
$total_faltante = 0;

foreach ($registros as $registro) {
    $tipo = $registro['tipo_auditoria'];
    $monto = $registro['monto_faltante'];
    
    switch($tipo) {
        case 'facturacion':
        case 'caja_chica':
            // Sumar solo si es negativo (faltante)
            if ($monto < 0) {
                $total_faltante += abs($monto);
            }
            break;
            
        case 'inventario':
            // Sumar el valor absoluto (ya que puede ser negativo o positivo)
            $total_faltante += abs($monto);
            break;
        
        case 'faltante_caja':
            // Para faltante de caja: sumar el monto directamente (ya es positivo)
            $total_faltante += $monto;
            break;
        
        case 'faltante_inventario':
        case 'faltante_danos':
            // Sumar solo si es positivo (ya que los negativos se consideran 0)
            if ($monto > 0) {
                $total_faltante += $monto;
            }
            break;
    }
}

// Verificar si se solicitó la exportación para contabilidad
if (isset($_GET['exportar_contabilidad'])) {
    try {
        // Consulta para obtener todas las deducciones agrupadas por operario
        $sql_contabilidad = "
            (SELECT 
                af.cajero AS operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                af.fecha_deduccion,
                SUM(ABS(af.faltante_sobrante)) AS monto_total,
                'facturacion' AS tipo
            FROM auditoria_facturacion af
            JOIN Operarios o ON af.cajero = o.CodOperario
            WHERE DATE(af.fecha_hora_regsys) BETWEEN :fecha_desde1 AND :fecha_hasta1 
            AND af.faltante_sobrante != 0
            GROUP BY operario_id, operario_nombre, af.fecha_deduccion)
            
            UNION ALL
            
            (SELECT 
                acc.lider_tienda_codigo AS operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                acc.fecha_deduccion,
                SUM(ABS(acc.faltante_sobrante)) AS monto_total,
                'caja_chica' AS tipo
            FROM auditoria_caja_chica acc
            JOIN Operarios o ON acc.lider_tienda_codigo = o.CodOperario
            WHERE DATE(acc.fecha_hora_regsys) BETWEEN :fecha_desde2 AND :fecha_hasta2 
            AND acc.faltante_sobrante != 0
            GROUP BY operario_id, operario_nombre, acc.fecha_deduccion)
            
            UNION ALL
            
            (SELECT 
                aio.operario_id AS operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                aio.fecha_deduccion,
                SUM(ABS(aio.monto)) AS monto_total,
                'inventario' AS tipo
            FROM auditoria_inventario ai
            JOIN auditoria_inventario_operarios aio ON ai.id = aio.auditoria_id
            JOIN Operarios o ON aio.operario_id = o.CodOperario
            WHERE DATE(ai.fecha_hora_regsys) BETWEEN :fecha_desde3 AND :fecha_hasta3 
            AND aio.monto != 0
            GROUP BY operario_id, operario_nombre, aio.fecha_deduccion)
            
            UNION ALL
            
            (SELECT 
                fio.operario_id AS operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                fio.fecha_deduccion,
                SUM(ABS(fio.monto)) AS monto_total,
                'faltante_inventario' AS tipo
            FROM faltante_inventario fi
            JOIN faltante_inventario_operarios fio ON fi.id = fio.faltante_id
            JOIN Operarios o ON fio.operario_id = o.CodOperario
            WHERE DATE(fi.fecha_hora_regsys) BETWEEN :fecha_desde4 AND :fecha_hasta4 
            AND fio.monto != 0
            GROUP BY operario_id, operario_nombre, fio.fecha_deduccion)
            
            UNION ALL
            
            (SELECT 
                fdo.operario_id AS operario_id,
                CONCAT(o.Nombre, ' ', o.Nombre2, ' ', o.Apellido, ' ', o.Apellido2) AS operario_nombre,
                fdo.fecha_deduccion,
                SUM(ABS(fdo.monto)) AS monto_total,
                'faltante_danos' AS tipo
            FROM faltante_danos fd
            JOIN faltante_danos_operarios fdo ON fd.id = fdo.faltante_id
            JOIN Operarios o ON fdo.operario_id = o.CodOperario
            WHERE DATE(fd.fecha_hora_regsys) BETWEEN :fecha_desde5 AND :fecha_hasta5 
            AND fdo.monto != 0
            GROUP BY operario_id, operario_nombre, fdo.fecha_deduccion)
            
            ORDER BY operario_nombre, fecha_deduccion
        ";
        
        $stmt_contabilidad = $db->prepare($sql_contabilidad);
        
        // Bind de parámetros para cada subconsulta
        $stmt_contabilidad->bindValue(':fecha_desde1', $fecha_desde);
        $stmt_contabilidad->bindValue(':fecha_hasta1', $fecha_hasta);
        $stmt_contabilidad->bindValue(':fecha_desde2', $fecha_desde);
        $stmt_contabilidad->bindValue(':fecha_hasta2', $fecha_hasta);
        $stmt_contabilidad->bindValue(':fecha_desde3', $fecha_desde);
        $stmt_contabilidad->bindValue(':fecha_hasta3', $fecha_hasta);
        $stmt_contabilidad->bindValue(':fecha_desde4', $fecha_desde);
        $stmt_contabilidad->bindValue(':fecha_hasta4', $fecha_hasta);
        $stmt_contabilidad->bindValue(':fecha_desde5', $fecha_desde);
        $stmt_contabilidad->bindValue(':fecha_hasta5', $fecha_hasta);
        
        $stmt_contabilidad->execute();
        $deducciones_contabilidad = $stmt_contabilidad->fetchAll(PDO::FETCH_ASSOC);
        
        // Configurar headers para descarga de archivo Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="deducciones_contabilidad_'.date('Y-m-d').'.xls"');
        
        // Iniciar salida
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Colaborador</th>';
        echo '<th>Código</th>';
        echo '<th>Fecha a Deducir</th>';
        echo '<th>Monto Total (C$)</th>';
        echo '<th>Tipo de Deducción</th>';
        echo '</tr>';
        
        foreach ($deducciones_contabilidad as $deduccion) {
            // Formatear fecha de deducción usando formatoFecha
            $fecha_deduccion = '';
            if (!empty($deduccion['fecha_deduccion'])) {
                if ($deduccion['tipo'] == 'faltante_caja') {
                    // Para faltante_caja, usar la fecha directamente (ya que formatoFecha solo muestra fecha)
                    $fecha_deduccion = formatoFecha($deduccion['fecha_deduccion']);
                } else {
                    // Para los demás tipos, usar la función formatoFecha que ya maneja el ajuste
                    $fecha_deduccion = formatoFecha($deduccion['fecha_deduccion']);
                }
            }
            
            // Determinar el tipo de deducción
            $tipo = $deduccion['tipo'];
            $tipo_text = '';
            
            switch($tipo) {
                case 'facturacion':
                    $tipo_text = 'Caja Facturación';
                    break;
                case 'caja_chica':
                    $tipo_text = 'Caja Chica';
                    break;
                case 'inventario':
                    $tipo_text = 'Auditoría Inventario';
                    break;
                case 'faltante_inventario':
                    $tipo_text = 'Faltante Inventario';
                    break;
                case 'faltante_danos':
                    $tipo_text = 'Faltante Daños';
                    break;
            }
            
            echo '<tr>';
            echo '<td>' . $deduccion['operario_nombre'] . '</td>';
            echo '<td>' . $deduccion['operario_id'] . '</td>';
            echo '<td>' . $fecha_deduccion . '</td>';
            echo '<td>' . number_format($deduccion['monto_total'], 2) . '</td>';
            echo '<td>' . $tipo_text . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        exit;
        
    } catch (PDOException $e) {
        die("Error en la consulta para contabilidad: " . $e->getMessage());
    }
}

// Verificar si se solicitó la exportación a Excel solo para faltantes de caja
if (isset($_GET['exportar_faltante_caja'])) {
    // Consulta específica para faltantes de caja
    $sql_export = "
        SELECT 
            fc.id,
            fc.fecha AS fecha_hora,
            fc.fecha_deduccion,
            fc.sucursal_id,
            s.nombre AS sucursal_nombre,
            fc.operario_id,
            fc.operario_nombre,
            fc.comentarios,
            fc.monto
        FROM faltante_caja fc
        JOIN sucursales s ON fc.sucursal_id = s.codigo
        WHERE 1=1
    ";
    
    $params_export = [];
    
    // Aplicar mismos filtros
    if ($operario_id > 0) {
        $sql_export .= " AND fc.operario_id = ?";
        $params_export[] = $operario_id;
    }
    
    if ($sucursal_id != 'todas') {
        $sql_export .= " AND fc.sucursal_id = ?";
        $params_export[] = $sucursal_id;
    }
    
    if (!empty($fecha_desde)) {
        $sql_export .= " AND DATE(fc.fecha) >= ?";
        $params_export[] = $fecha_desde;
    }
    
    if (!empty($fecha_hasta)) {
        $sql_export .= " AND DATE(fc.fecha) <= ?";
        $params_export[] = $fecha_hasta;
    }
    
    $sql_export .= " ORDER BY fc.fecha DESC";
    
    $stmt_export = $db->prepare($sql_export);
    $stmt_export->execute($params_export);
    $registros_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);
    
    // Configurar headers para descarga de archivo Excel - INCLUYENDO RANGO DE FECHAS
    $nombre_archivo = "faltantes_caja_" . str_replace('-', '', $fecha_desde) . "_" . str_replace('-', '', $fecha_hasta) . ".xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    
    // Iniciar salida
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Fecha</th>';
    echo '<th>Colaborador</th>';
    echo '<th>Código</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Monto (C$)</th>';
    echo '<th>Comentarios</th>';
    echo '</tr>';
    
    foreach ($registros_export as $registro) {
        // Para faltante_caja, usar la fecha directamente (sin hora)
        $fecha_formateada = formatoFecha($registro['fecha_hora']);
        
        echo '<tr>';
        echo '<td>' . $fecha_formateada . '</td>';
        echo '<td>' . $registro['operario_nombre'] . '</td>';
        echo '<td>' . $registro['operario_id'] . '</td>';
        echo '<td>' . $registro['sucursal_nombre'] . '</td>';
        echo '<td>' . number_format(abs($registro['monto']), 2) . '</td>';
        echo '<td>' . htmlspecialchars($registro['comentarios'] ?? '') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditorías de Efectivo</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="icon" href="../icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        * {
            font-family: 'Calibri', sans-serif;
            text-align: center;
            align-content: center;
            align-items: center;
            justify-content: center;
            font-size: clamp(11px, 2vw, 16px) !important;
        }

        body {
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
        
        .btn-agregar.excel {
            background-color: transparent;
            color: #1d6f42;
            border: 1px solid #1d6f42;
        }
        
        .btn-agregar.excel:hover {
            background-color: #1d6f42;
            color: white;
        }

        .contenedor-principal {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 25px 1px;
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
            min-width: 120px;
            width: auto;
        }
        
        .filtro-opciones.sucursal {
            width: 220px;
            padding: 8px;
        }
        
        .filtro-opciones.sucursal .sucursales-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3px;
        }
        
        .filtro-opciones.sucursal a {
            text-align: left;
            padding: 4px 6px;
            white-space: nowrap;
        }
        
        /* Estilos para el filtro de mes/año como popup */
        .filtro-opciones.mes-anio {
            width: 200px;
            padding: 15px;
            right: 0;
        }
        
        .filtro-opciones.mes-anio form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .filtro-opciones.mes-anio select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
            width: 100%;
        }
        
        .filtro-opciones.mes-anio .botones-filtro {
            display: flex;
            justify-content: space-between;
            gap: 5px;
        }
        
        .filtro-opciones.mes-anio button {
            padding: 8px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            flex: 1;
        }
        
        .filtro-opciones.mes-anio button.cancelar {
            background-color: #f1f1f1;
            color: #333;
        }
        
        .filtro-opciones.mes-anio button:hover {
            background-color: #0E544C;
        }
        
        .filtro-opciones.mes-anio button.cancelar:hover {
            background-color: #ddd;
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

        /* Mostrar el filtro cuando está activo o con hover */
        .filtro-contenedor:hover .filtro-opciones,
        .filtro-contenedor.activo .filtro-opciones {
            display: block;
        }

        .filtro-encabezado {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
        
        /* Estilos para el encabezado del historial */
        .encabezado-historial {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            width: 100%;
        }
        
        .titulo-historial {
            margin: 0;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Estilos para los badges de tipo de auditoría */
        .badge-tipo {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        
        .badge-facturacion {
            background-color: #3498db;
            border: 1px solid #2980b9;
        }
        
        .badge-caja_chica {
            background-color: #9b59b6;
            border: 1px solid #8e44ad;
        }
        
        .badge-inventario {
            background-color: #2ecc71;
            border: 1px solid #27ae60;
        }
        
        .badge-faltante_inventario {
            background-color: #e67e22;
            border: 1px solid #d35400;
        }
        
        .badge-faltante_danos {
            background-color: #e74c3c;
            border: 1px solid #c0392b;
        }
        
        .badge-faltante_caja {
            background-color: #f39c12;
            border: 1px solid #e67e22;
        }
        
        .monto-faltante {
            font-weight: bold;
        }
        
        .monto-positivo {
            color: #27ae60;
        }
        
        .monto-negativo {
            color: #e74c3c;
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
    
    .btn-agregar {
        padding: 6px 10px;
        font-size: 13px;
    }
            
            .filtro-opciones.sucursal {
                width: 160px;
                left: 50%;
                transform: translateX(-50%);
            }
            
            .filtro-opciones.sucursal .sucursales-grid {
                grid-template-columns: 1fr;
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
    
    .user-info {
        flex-direction: column;
        align-items: flex-end;
    }
            
            .btn-agregar i {
                margin-right: 4px;
            }
            
            .filtro-opciones.sucursal {
                width: 130px;
            }
            
            .filtro-opciones.sucursal .sucursales-grid {
                grid-template-columns: 1fr;
            }
            
            .filtro-opciones.mes-anio {
                width: 160px;
                right: -50px;
            }
        }
        
        /* Efecto al pasar el mouse sobre las filas */
        tr:hover {
            background-color: rgba(81, 184, 172, 0.1) !important;
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
    font-weight: bold;
    color: #555;
}

.filtro-select, 
.filtro-group input[type="date"],
.filtro-group input[type="text"] {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 100%;
    background-color: #fff;
}

.filtro-buttons {
    display: flex;
    gap: 10px;
    align-self: flex-end;
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
    border: none;
}

.btn-aplicar:hover {
    background-color: #0E544C;
}

.btn-limpiar {
    background-color: #f1f1f1;
    color: #333;
    border: 1px solid #ddd;
    text-decoration: none;
}

.btn-limpiar:hover {
    background-color: #ddd;
}

/* Estilos para el autocompletado */
#operarios-sugerencias {
    width: calc(100% - 2px); /* Mismo ancho que el input */
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 5px 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    margin-top: -1px; /* Para que se pegue al input */
    position: absolute;
    top: 100%; /* Posiciona el dropdown justo debajo del input */
    left: 0;
    z-index: 1000;
}

#operarios-sugerencias div:hover {
    background-color: #f5f5f5 !important;
}

/* Asegurar que el input tenga un z-index menor */
.filtro-group input[type="text"] {
    position: relative;
    z-index: 1;
}

.encabezado{
    text-align: center;
}
    </style>
</head>
<body>
    <!-- Header con logo -->
    <div class="contenedor-principal">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>
                
                <div class="buttons-container">
                    <a href="auditorias_consolidadas.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditorias_consolidadas.php' ? 'activo' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Historial</span>
                    </a>
                    <?php if ($esAdmin || verificarAccesoCargo([2, 5, 8, 11, 16])): ?>
                        <a href="deducciones_total.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'deducciones_total.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Deducciones</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($esAdmin || verificarAccesoCargo([8, 16])): ?>
                        <a href="faltante_caja.php" class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'faltante_caja.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Faltante de Caja</span>
                        </a>
                    <?php endif; ?>
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
        
        <!-- Filtros -->
        <div class="filtros-container">
            <form method="get" action="auditorias_consolidadas.php" class="filtros-form">
                <!-- Filtro de Tipo -->
                <div style="display:none;" class="filtro-group">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" class="filtro-select">
                        <option value="todos" <?= $tipo_seleccionado == 'todos' ? 'selected' : '' ?>>Todos los tipos</option>
                        <option value="facturacion" <?= $tipo_seleccionado == 'facturacion' ? 'selected' : '' ?>>Caja Facturación</option>
                        <option value="caja_chica" <?= $tipo_seleccionado == 'caja_chica' ? 'selected' : '' ?>>Caja Chica</option>
                        <option value="inventario" <?= $tipo_seleccionado == 'inventario' ? 'selected' : '' ?>>Auditoría Inventario</option>
                        <option value="faltante_inventario" <?= $tipo_seleccionado == 'faltante_inventario' ? 'selected' : '' ?>>Faltante Inventario</option>
                        <option value="faltante_danos" <?= $tipo_seleccionado == 'faltante_danos' ? 'selected' : '' ?>>Faltante Daños</option>
                        <option value="faltante_caja" <?= $tipo_seleccionado == 'faltante_caja' ? 'selected' : '' ?>>Faltante Caja</option>
                    </select>
                </div>
                
                <!-- Filtro de Sucursal -->
                <div class="filtro-group">
                    <label for="sucursal">Sucursal</label>
                    <select id="sucursal" name="sucursal" class="filtro-select">
                        <option value="todas" <?= $sucursal_id == 'todas' ? 'selected' : '' ?>>Todas las sucursales</option>
                        <?php foreach ($sucursales as $sucursal): ?>
                            <option value="<?= $sucursal['codigo'] ?>" 
                                <?= ($sucursal['codigo'] == $sucursal_id || $sucursal['nombre'] == $sucursal_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sucursal['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filtro de Colaborador -->
                <div style="display:none;" class="filtro-group">
                    <label for="operario">Colaborador</label>
                    <input type="text" id="operario" name="operario_nombre" 
                           placeholder="Escriba para buscar..." 
                           value="<?php 
                               if ($operario_id > 0) {
                                   foreach ($operarios as $op) {
                                       if ($op['CodOperario'] == $operario_id) {
                                           echo htmlspecialchars($op['nombre_completo']);
                                           break;
                                       }
                                   }
                               }
                           ?>">
                    <input type="hidden" id="operario_id" name="operario" value="<?= $operario_id ?>">
                    <div id="operarios-sugerencias" style="display: none;"></div>
                </div>
                
                <!-- Filtro de Fechas -->
                <div class="filtro-group">
                    <label for="fecha_desde">Desde</label>
                    <input type="date" id="fecha_desde" name="fecha_desde" 
                           value="<?= htmlspecialchars($fecha_desde) ?>" 
                           max="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="filtro-group">
                    <label for="fecha_hasta">Hasta</label>
                    <input type="date" id="fecha_hasta" name="fecha_hasta" 
                           value="<?= htmlspecialchars($fecha_hasta) ?>" 
                           max="<?= date('Y-m-d') ?>">
                </div>
                
                <!-- Botones del Formulario -->
                <div class="filtro-buttons">
                    <button type="submit" class="btn-aplicar">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a style="display:none;" href="<?= $url_limpiar_filtros ?>" class="btn-limpiar">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
                
                <div class="filtro-buttons">
                    <a href="auditorias_consolidadas.php?<?php 
                        echo http_build_query([
                            'tipo' => $tipo_seleccionado,
                            'sucursal' => $sucursal_id,
                            'operario' => $operario_id,
                            'fecha_desde' => $fecha_desde,
                            'fecha_hasta' => $fecha_hasta,
                            'exportar_deducciones' => 1
                        ]); 
                    ?>" class="btn-agregar excel">
                        <i class="fas fa-file-excel"></i> Exportar
                    </a>
                    
                    <!-- Nuevo botón para exportar solo faltantes de caja -->
                    <a href="auditorias_consolidadas.php?<?php 
                        echo http_build_query([
                            'tipo' => $tipo_seleccionado,
                            'sucursal' => $sucursal_id,
                            'operario' => $operario_id,
                            'fecha_desde' => $fecha_desde,
                            'fecha_hasta' => $fecha_hasta,
                            'exportar_faltante_caja' => 1
                        ]); 
                    ?>" class="btn-agregar excel" style="background-color: #f39c12; border-color: #f39c12; color: white;">
                        <i class="fas fa-file-excel"></i> Faltantes Caja
                    </a>
                </div>
            </form>
        </div>
        
        <?php if (verificarAccesoCargo([21, 16])): ?>
            <!-- Botones para agregar nuevos registros -->
            <div style="background: #fff; padding: 2px;">
                <p>Nueva Auditoría</p>
                <a href="auditoria_caja_facturacion.php" class="btn-agregar"><i class="fas fa-cash-register"></i> AUDITORÍA CAJA FACTURACIÓN</a>
                <a href="auditoria_caja_chica.php" class="btn-agregar"><i class="fas fa-wallet"></i> AUDITORÍA CAJA CHICA</a>
                <a href="auditoria_inventario.php" class="btn-agregar"><i class="fas fa-boxes"></i> AUDITORÍA INVENTARIO</a>
                <a href="faltante_inventario.php" class="btn-agregar"><i class="fas fa-exclamation-triangle"></i> FALTANTE INVENTARIO</a>
                <a href="faltante_danos.php" class="btn-agregar"><i class="fas fa-times-circle"></i> FALTANTE DAÑOS</a>
                <br><br>
            </div>
            <br>
        <?php endif; ?>
        
        <!-- Mostrar registros de la tabla seleccionada -->
        <div class="encabezado-historial">
            <!-- Limpiar campos de Filtros aplicados a la página actual -->
            <a href="<?php echo $url_limpiar_filtros; ?>" class="btn-agregar" style=" display:none; background-color: #f1f1f1; color: #333; border: 1px solid #ccc;">
                <i class="fas fa-times-circle"></i> Limpiar Filtros
            </a>
            
            <h3 style="display:none;" class="titulo-historial">
                <i class="fas fa-history"></i> Historial de Auditorías - 
                <?php 
                    echo date('d/m/Y', strtotime($fecha_desde)) . ' al ' . date('d/m/Y', strtotime($fecha_hasta));
                ?>
            </h3>
            
            <a style="display:none;" href="auditorias_consolidadas.php?<?php 
                echo http_build_query([
                    'tipo' => $tipo_seleccionado,
                    'sucursal' => $sucursal_id,
                    'operario' => $operario_id,
                    'fecha_desde' => $fecha_desde,
                    'fecha_hasta' => $fecha_hasta,
                    'exportar_excel' => 1
                ]); 
            ?>" class="btn-agregar excel" style="margin-left: auto;">
                <i class="fas fa-file-excel"></i> Exportar a Excel
            </a>
            
            <a style="display:none;" href="auditorias_consolidadas.php?<?php 
                echo http_build_query([
                    'tipo' => $tipo_seleccionado,
                    'sucursal' => $sucursal_id,
                    'operario' => $operario_id,
                    'fecha_desde' => $fecha_desde,
                    'fecha_hasta' => $fecha_hasta,
                    'exportar_deducciones' => 1
                ]); 
            ?>" class="btn-agregar" style="background-color: transparent; color: #9b59b6; border: 1px solid #9b59b6;">
                <i class="fas fa-user-check"></i> Exportar Deducciones
            </a>
            
            <a style="display:none;" href="auditorias_consolidadas.php?<?php 
                echo http_build_query([
                    'tipo' => $tipo_seleccionado,
                    'sucursal' => $sucursal_id,
                    'operario' => $operario_id,
                    'fecha_desde' => $fecha_desde,
                    'fecha_hasta' => $fecha_hasta,
                    'exportar_contabilidad' => 1
                ]); 
            ?>" class="btn-agregar" style="background-color: transparent; color: #3498db; border: 1px solid #3498db;">
                <i class="fas fa-file-invoice-dollar"></i> Exportar para Contabilidad
            </a>
            
            <h3 style="display:none; margin: 0; color: #333;">
                Total de Faltantes: 
                <span style="color: <?php echo ($total_faltante > 0) ? '#e74c3c' : '#27ae60'; ?>; font-weight: bold;">
                    C$ <?php echo number_format($total_faltante, 2); ?>
                </span>
            </h3>
        </div>
        
        <!-- Resumen de total de faltantes -->
        <div style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ddd; text-align: center; display:none;">
            <h3 style="margin: 0; color: #333;">
                Total de Faltantes: 
                <span style="color: <?php echo ($total_faltante > 0) ? '#e74c3c' : '#27ae60'; ?>; font-weight: bold;">
                    C$ <?php echo number_format($total_faltante, 2); ?>
                </span>
            </h3>
            <?php if (!empty($registros)): ?>
                <p style="margin: 5px 0 0; color: #666; font-size: 14px; display:none;">
                    Mostrando <?php echo count($registros); ?> registro(s) - 
                    Filtros: <?php echo ucfirst($tipo_seleccionado); ?> / 
                    <?php echo ucfirst($sucursal_seleccionada); ?> / 
                    <?php echo $meses[$mes_seleccionado] . ' ' . $anio_seleccionado; ?>
                </p>
            <?php endif; ?>
            </div>
        
            <table>
                <thead>
                    <tr>
                        <th class="encabezado">Fecha</th>
                        <th class="encabezado">
                            Sucursal
                            <div class="filtro-contenedor">
                                <span class="filtro-encabezado">
                                    <i class="fas fa-caret-down"></i>
                                </span>
                                <div class="filtro-opciones">
                                    <?php
                                    $params_base = [
                                        'mes' => $mes_seleccionado,
                                        'anio' => $anio_seleccionado,
                                        'tipo' => $tipo_seleccionado,
                                        'operario' => $operario_id,
                                        'fecha_desde' => $fecha_desde,
                                        'fecha_hasta' => $fecha_hasta
                                    ];
                                    ?>
                                    <a href="?<?= http_build_query(array_merge($params_base, ['sucursal' => 'todas'])) ?>">
                                        Todas
                                    </a>
                                    <?php foreach ($sucursales as $sucursal): ?>
                                        <a href="?<?= http_build_query(array_merge($params_base, ['sucursal' => $sucursal['codigo']])) ?>">
                                            <?= htmlspecialchars($sucursal['nombre']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </th>
                        <th class="encabezado">
                            Tipo
                            <div class="filtro-contenedor">
                                <span class="filtro-encabezado">
                                    <i class="fas fa-caret-down"></i>
                                </span>
                                <div class="filtro-opciones">
                                    <a href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'todos'])) ?>">
                                        Todos
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'facturacion'])) ?>">
                                        Caja Facturación
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'caja_chica'])) ?>">
                                        Caja Chica
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'inventario'])) ?>">
                                        Auditoría Inventario
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'faltante_inventario'])) ?>">
                                        Faltante Inventario
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'faltante_danos'])) ?>">
                                        Faltante Daños
                                    </a>
                                    <a href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'faltante_caja'])) ?>">
                                        Faltante Caja
                                    </a>
                                </div>
                            </div>
                        </th>
                        <th class="encabezado">Faltante (C$)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center; background-color:#fff;">Sin registros actualmente.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registros as $registro): ?>
                        <tr>
                            <td style="text-align:center;">
                                <?php
                                    $meses_cortos = [
                                        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
                                        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
                                        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
                                    ];
                                    
                                    if ($registro['tipo_auditoria'] == 'faltante_caja') {
                                        // Para faltante_caja, mostrar solo la fecha (sin hora)
                                        $fecha = new DateTime($registro['fecha_hora']);
                                        $dia = $fecha->format('d');
                                        $mes = $meses_cortos[(int)$fecha->format('m')];
                                        $anio = $fecha->format('y');
                                        echo "$dia-$mes-$anio";
                                    } else {
                                        // Para los demás tipos, mostrar fecha y hora (con ajuste de -6 horas)
                                        $fecha = new DateTime($registro['fecha_hora']);
                                        $fecha->sub(new DateInterval('PT6H'));
                                        
                                        $dia = $fecha->format('d');
                                        $mes = $meses_cortos[(int)$fecha->format('m')];
                                        $anio = $fecha->format('y');
                                        
                                        $hora = $fecha->format('H:i');
                                        $hora_formateada = ($hora == '00:00') ? '12:00 am' :
                                                          (($fecha->format('H') < 12) ? $fecha->format('g:i a') :
                                                          (($fecha->format('H') == 12) ? $fecha->format('g:i') . ' pm' :
                                                          (($fecha->format('g:i'))) . ' pm'));
                                        
                                        echo "$dia-$mes-$anio $hora_formateada";
                                    }
                                ?>
                            </td>
                            <td style="text-align:center;"><?php echo $registro['sucursal']; ?></td>
                            <td style="text-align:center;">
                                <?php 
                                    // Mostrar el tipo de auditoría con un badge de color
                                    $tipo = $registro['tipo_auditoria'];
                                    $badge_class = 'badge-' . $tipo;
                                    $tipo_text = '';
                                    
                                    switch($tipo) {
                                        case 'facturacion':
                                            $tipo_text = 'Caja Facturación';
                                            break;
                                        case 'caja_chica':
                                            $tipo_text = 'Caja Chica';
                                            break;
                                        case 'inventario':
                                            $tipo_text = 'Auditoría Inventario';
                                            break;
                                        case 'faltante_inventario':
                                            $tipo_text = 'Faltante Inventario';
                                            break;
                                        case 'faltante_danos':
                                            $tipo_text = 'Faltante Daños';
                                            break;
                                        case 'faltante_caja':
                                            $tipo_text = 'Faltante de Caja';
                                            break;
                                    }
                                    
                                    echo '<span class="badge-tipo ' . $badge_class . '">' . $tipo_text . '</span>';
                                ?>
                            </td>
                            <td style="text-align:center;" class="monto-faltante <?php 
                                    // Determinar la clase CSS basada en el tipo de auditoría y el monto
                                    $monto_mostrar = 0;
                                    
                                    switch($registro['tipo_auditoria']) {
                                        case 'facturacion':
                                        case 'caja_chica':
                                            // Para facturación y caja chica: mostrar 0 si es positivo o cero, mostrar valor absoluto si es negativo
                                            $monto_mostrar = ($registro['monto_faltante'] >= 0) ? 0 : abs($registro['monto_faltante']);
                                            echo ($monto_mostrar > 0) ? 'monto-negativo' : 'monto-positivo';
                                            break;
                                            
                                        case 'inventario':
                                            // Para inventario: mostrar valor absoluto siempre
                                            $monto_mostrar = abs($registro['monto_faltante']);
                                            echo ($registro['monto_faltante'] < 0) ? 'monto-negativo' : 'monto-positivo';
                                            break;
                                            
                                        case 'faltante_inventario':
                                        case 'faltante_danos':
                                            // Para faltantes: mostrar 0 si es negativo, mostrar valor tal cual si es positivo
                                            $monto_mostrar = ($registro['monto_faltante'] < 0) ? 0 : $registro['monto_faltante'];
                                            echo ($monto_mostrar > 0) ? 'monto-negativo' : 'monto-positivo';
                                            break;
                                        case 'faltante_caja':
                                            // Para faltante de caja: mostrar el monto tal cual (ya es positivo)
                                            $monto_mostrar = $registro['monto_faltante'];
                                            echo ($monto_mostrar > 0) ? 'monto-negativo' : 'monto-positivo';
                                            break;
                                    }
                                ?>">
                                <div class="promedio-contenedor">
                                    C$ <?php echo number_format($monto_mostrar, 2); ?>
                                    <a href="<?php echo $registro['url_ver']; ?>?id=<?php echo $registro['id']; ?>" style="color:#51B8AC;">
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

    <script>
        // Función para alternar la visibilidad del filtro de mes/año
        function toggleFiltroMesAnio() {
            const filtro = document.getElementById('filtro-mes-anio');
            filtro.classList.toggle('activo');
        }
        
        // Función para cerrar el filtro de mes/año
        function cerrarFiltroMesAnio() {
            const filtro = document.getElementById('filtro-mes-anio');
            filtro.classList.remove('activo');
        }
        
        // Cerrar el filtro si se hace clic fuera de él
        document.addEventListener('click', function(event) {
            const filtro = document.getElementById('filtro-mes-anio');
            const target = event.target;
            
            // Si el clic no fue dentro del filtro ni en el botón que lo activa
            if (!filtro.contains(target) && !target.closest('.filtro-encabezado')) {
                filtro.classList.remove('activo');
            }
        });
    </script>
    
    <script>
// Datos de operarios para el autocompletado
const operariosData = [
    <?php foreach ($operarios as $op): ?>
    {
        id: <?= $op['CodOperario'] ?>,
        nombre: '<?= addslashes($op['nombre_completo']) ?>'
    },
    <?php endforeach; ?>
];

// Elementos del DOM
const operarioInput = document.getElementById('operario');
const operarioIdInput = document.getElementById('operario_id');
const sugerenciasDiv = document.getElementById('operarios-sugerencias');

// Función para buscar operarios
function buscarOperarios(texto) {
    if (!texto) return [];
    const textoLower = texto.toLowerCase();
    return operariosData.filter(op => 
        op.nombre.toLowerCase().includes(textoLower)
    );
}

// Función para mostrar sugerencias
function mostrarSugerencias(resultados) {
    sugerenciasDiv.innerHTML = '';
    
    if (resultados.length === 0) {
        sugerenciasDiv.style.display = 'none';
        return;
    }
    
    resultados.forEach(op => {
        const div = document.createElement('div');
        div.textContent = op.nombre;
        div.className = 'sugerencia-item';
        div.addEventListener('click', () => {
            operarioInput.value = op.nombre;
            operarioIdInput.value = op.id;
            sugerenciasDiv.style.display = 'none';
        });
        sugerenciasDiv.appendChild(div);
    });
    
    sugerenciasDiv.style.display = 'block';
}

// Event Listeners
operarioInput.addEventListener('input', function() {
    const texto = this.value.trim();
    if (texto.length >= 2) {
        mostrarSugerencias(buscarOperarios(texto));
    } else {
        sugerenciasDiv.style.display = 'none';
    }
});

operarioInput.addEventListener('focus', function() {
    if (this.value.trim() === '') {
        mostrarSugerencias(operariosData.slice(0, 10)); // Muestra los primeros 10 por defecto
    }
});

// Cerrar sugerencias al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!operarioInput.contains(e.target) && !sugerenciasDiv.contains(e.target)) {
        sugerenciasDiv.style.display = 'none';
    }
});

// Validación de fechas
document.getElementById('fecha_desde').addEventListener('change', function() {
    const fechaHasta = document.getElementById('fecha_hasta');
    if (this.value && fechaHasta.value && this.value > fechaHasta.value) {
        fechaHasta.value = this.value;
    }
});

document.getElementById('fecha_hasta').addEventListener('change', function() {
    const fechaDesde = document.getElementById('fecha_desde');
    if (this.value && fechaDesde.value && this.value < fechaDesde.value) {
        fechaDesde.value = this.value;
    }
});
</script>
</body>
</html>
