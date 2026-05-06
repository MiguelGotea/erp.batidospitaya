<?php
// Configuración inicial y autenticación
require_once '../auth.php';
require_once '../../../../core/helpers/funciones.php'; // Antes llamaba a ../funciones.php de auditor�a
require_once 'config.php';

// Establecer conexión a la base de datos
$db = conectarDB();

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
//verificarAccesoCargo([2, 5, 8, 11, 16, 13]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([2, 5, 8, 11, 16, 13, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

$cargoUsuarioCod = obtenerCargoCodigoPrincipalUsuario($_SESSION['usuario_id']);
$esOperarioOLider = in_array($cargoUsuarioCod, [2, 5]);
//******************************Estándar para header, termina******************************

// Procesar asignación de fecha de deducción si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['asignar_fecha'])) {
    $tipo = $_POST['tipo'];
    $id_referencia = $_POST['id_referencia'];
    $fecha_deduccion = $_POST['fecha_deduccion'];
    $usuario_id = $_SESSION['usuario_id'];

    try {
        // Validar fecha
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_deduccion);
        if (!$fecha_obj) {
            throw new Exception("Formato de fecha inválido");
        }

        // Actualizar en la tabla original correspondiente
        switch ($tipo) {
            case 'facturacion':
                $sql = "UPDATE auditoria_facturacion SET fecha_deduccion = ? WHERE id = ?";
                break;
            case 'caja_chica':
                $sql = "UPDATE auditoria_caja_chica SET fecha_deduccion = ? WHERE id = ?";
                break;
            case 'inventario':
                $sql = "UPDATE auditoria_inventario_operarios SET fecha_deduccion = ? WHERE id = ?";
                break;
            case 'faltante_inventario':
                $sql = "UPDATE faltante_inventario_operarios SET fecha_deduccion = ? WHERE id = ?";
                break;
            case 'faltante_danos':
                $sql = "UPDATE faltante_danos_operarios SET fecha_deduccion = ? WHERE id = ?";
                break;
            case 'faltante_caja':
                $sql = "UPDATE faltante_caja SET fecha_deduccion = ? WHERE id = ?"; // NUEVO
                break;
            default:
                throw new Exception("Tipo de deducción inválido");
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$fecha_deduccion, $id_referencia]);

        // Registrar en la tabla de deducciones_operaciones
        $sql_insert = "INSERT INTO deducciones_operaciones (
            tipo_deduccion, id_referencia, operario_id, sucursal_id, monto, 
            fecha_evento, fecha_deduccion, comentarios, usuario_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_insert = $db->prepare($sql_insert);
        $stmt_insert->execute([
            $tipo,
            $id_referencia,
            $_POST['operario_id'],
            $_POST['sucursal_id'],
            $_POST['monto'],
            $_POST['fecha_evento'],
            $fecha_deduccion,
            $_POST['comentarios'],
            $usuario_id
        ]);

        // Redirigir para evitar reenvío del formulario
        header("Location: deducciones_total.php?" . http_build_query($_GET));
        exit();

    } catch (Exception $e) {
        $error_asignacion = "Error al asignar fecha: " . $e->getMessage();
    }
}

// Obtener parámetros de filtro
$operario_id = isset($_GET['operario']) ? intval($_GET['operario']) : 0;
$sucursal_id = isset($_GET['sucursal']) ? $_GET['sucursal'] : 'todas';
$cobrado_filtro = isset($_GET['cobrado']) ? $_GET['cobrado'] : 'todos';

// Establecer fechas por defecto (mes actual) si no se han especificado
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-t');

// Validar fechas
if (!empty($fecha_desde)) {
    $fecha_desde = DateTime::createFromFormat('Y-m-d', $fecha_desde);
    $fecha_desde = $fecha_desde ? $fecha_desde->format('Y-m-d') : '';
}

if (!empty($fecha_hasta)) {
    $fecha_hasta = DateTime::createFromFormat('Y-m-d', $fecha_hasta);
    $fecha_hasta = $fecha_hasta ? $fecha_hasta->format('Y-m-d') : '';
}

try {
    // Si es operario o líder, forzar filtros específicos
    if ($esOperarioOLider) {
        // Obtener el último CodContrato del operario
        $ultimo_cod_contrato = obtenerUltimoCodigoContrato($_SESSION['usuario_id']);

        if ($ultimo_cod_contrato) {
            $operario_id = $ultimo_cod_contrato; // Usar CodContrato en lugar de CodOperario
        } else {
            $operario_id = $_SESSION['usuario_id']; // Fallback al CodOperario
        }

        // MODIFICADO: Usar últimos 20 días en lugar de quincena
        $fecha_hasta = date('Y-m-d');
        $fecha_desde = date('Y-m-d', strtotime('-19 days')); // 20 días incluyendo hoy
    }

    // Consulta para obtener todas las deducciones de los diferentes tipos
    $sql = "
        (SELECT 
            'facturacion' AS tipo,
            af.id,
            -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
            DATE_SUB(af.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
            af.fecha_hora_regsys AS fecha_evento_utc,
            af.fecha_deduccion,
            af.sucursal_id,
            s.nombre AS sucursal_nombre,
            af.cod_contrato AS operario_id,
            CONCAT(
                IFNULL(o.Nombre, ''), ' ', 
                IFNULL(o.Nombre2, ''), ' ', 
                IFNULL(o.Apellido, ''), ' ', 
                IFNULL(o.Apellido2, '')
            ) AS operario_nombre,
            af.comentarios,
            af.faltante_sobrante AS monto_original,
            CASE WHEN af.faltante_sobrante < 0 THEN ABS(af.faltante_sobrante) ELSE 0 END AS monto,
            'ver_auditorias_facturacion.php' AS url_ver,
            af.cod_contrato AS cod_contrato,
            af.fecha_hora_regsys AS fecha_registro,
            -- NUEVA COLUMNA: Estado (usando fecha local)
            CASE 
                WHEN DAY(DATE_SUB(af.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla Primer Quincena'
                WHEN DAY(DATE_SUB(af.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla Segunda Quincena'
                ELSE 'Propina'
            END AS estado_deduccion,
            IFNULL(af.cobrado, 0) AS cobrado
        FROM auditoria_facturacion af
        JOIN Operarios o ON af.cajero = o.CodOperario
        JOIN sucursales s ON af.sucursal_id = s.codigo)
        
        UNION ALL
        
        (SELECT 
            'caja_chica' AS tipo,
            acc.id,
            -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
            DATE_SUB(acc.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
            acc.fecha_hora_regsys AS fecha_evento_utc,
            acc.fecha_deduccion,
            acc.sucursal_id,
            s.nombre AS sucursal_nombre,
            acc.cod_contrato AS operario_id,
            CONCAT(
                IFNULL(o.Nombre, ''), ' ', 
                IFNULL(o.Nombre2, ''), ' ', 
                IFNULL(o.Apellido, ''), ' ', 
                IFNULL(o.Apellido2, '')
            ) AS operario_nombre,
            acc.comentarios,
            acc.faltante_sobrante AS monto_original,
            CASE WHEN acc.faltante_sobrante < 0 THEN ABS(acc.faltante_sobrante) ELSE 0 END AS monto,
            'ver_auditorias_caja_chica.php' AS url_ver,
            acc.cod_contrato AS cod_contrato,
            acc.fecha_hora_regsys AS fecha_registro,
            -- NUEVA COLUMNA: Estado (usando fecha local)
            CASE 
                WHEN DAY(DATE_SUB(acc.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla Primer Quincena'
                WHEN DAY(DATE_SUB(acc.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla Segunda Quincena'
                ELSE 'Propina'
            END AS estado_deduccion,
            IFNULL(acc.cobrado, 0) AS cobrado
        FROM auditoria_caja_chica acc
        JOIN Operarios o ON acc.lider_tienda_codigo = o.CodOperario
        JOIN sucursales s ON acc.sucursal_id = s.codigo)
        
        UNION ALL
        
        (SELECT 
            'inventario' AS tipo,
            ai.id,
            -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
            DATE_SUB(ai.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
            ai.fecha_hora_regsys AS fecha_evento_utc,
            aio.fecha_deduccion,
            ai.sucursal_id,
            s.nombre AS sucursal_nombre,
            aio.cod_contrato AS operario_id,
            CONCAT(
                IFNULL(o.Nombre, ''), ' ', 
                IFNULL(o.Nombre2, ''), ' ', 
                IFNULL(o.Apellido, ''), ' ', 
                IFNULL(o.Apellido2, '')
            ) AS operario_nombre,
            ai.comentarios,
            aio.monto AS monto_original,
            aio.monto AS monto,
            'ver_auditorias_inventario.php' AS url_ver,
            aio.cod_contrato AS cod_contrato,
            ai.fecha_hora_regsys AS fecha_registro,
            -- NUEVA COLUMNA: Estado (usando fecha local)
            CASE 
                WHEN DAY(DATE_SUB(ai.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla Primer Quincena'
                WHEN DAY(DATE_SUB(ai.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla Segunda Quincena'
                ELSE 'Propina'
            END AS estado_deduccion,
            IFNULL(aio.cobrado, 0) AS cobrado
        FROM auditoria_inventario ai
        JOIN auditoria_inventario_operarios aio ON ai.id = aio.auditoria_id
        JOIN Operarios o ON aio.operario_id = o.CodOperario
        JOIN sucursales s ON ai.sucursal_id = s.codigo)
        
        UNION ALL
        
        (SELECT 
            'faltante_inventario' AS tipo,
            fi.id,
            -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
            DATE_SUB(fi.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
            fi.fecha_hora_regsys AS fecha_evento_utc,
            fio.fecha_deduccion,
            fi.sucursal_id,
            s.nombre AS sucursal_nombre,
            fio.cod_contrato AS operario_id,
            CONCAT(
                IFNULL(o.Nombre, ''), ' ', 
                IFNULL(o.Nombre2, ''), ' ', 
                IFNULL(o.Apellido, ''), ' ', 
                IFNULL(o.Apellido2, '')
            ) AS operario_nombre,
            fi.comentarios,
            fio.monto AS monto_original,
            fio.monto AS monto,
            'ver_faltante_inventario.php' AS url_ver,
            fio.cod_contrato AS cod_contrato,
            fi.fecha_hora_regsys AS fecha_registro,
            -- NUEVA COLUMNA: Estado (usando fecha local)
            CASE 
                WHEN DAY(DATE_SUB(fi.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla Primer Quincena'
                WHEN DAY(DATE_SUB(fi.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla Segunda Quincena'
                ELSE 'Propina'
            END AS estado_deduccion,
            IFNULL(fio.cobrado, 0) AS cobrado
        FROM faltante_inventario fi
        JOIN faltante_inventario_operarios fio ON fi.id = fio.faltante_id
        JOIN Operarios o ON fio.operario_id = o.CodOperario
        JOIN sucursales s ON fi.sucursal_id = s.codigo)
        
        UNION ALL
        
        (SELECT 
            'faltante_danos' AS tipo,
            fd.id,
            -- CONVERTIR FECHA A HORA LOCAL ANTES DE FILTRAR
            DATE_SUB(fd.fecha_hora_regsys, INTERVAL 6 HOUR) AS fecha_evento_local,
            fd.fecha_hora_regsys AS fecha_evento_utc,
            fdo.fecha_deduccion,
            fd.sucursal_codigo AS sucursal_id,
            s.nombre AS sucursal_nombre,
            fdo.cod_contrato AS operario_id,
            CONCAT(
                IFNULL(o.Nombre, ''), ' ', 
                IFNULL(o.Nombre2, ''), ' ', 
                IFNULL(o.Apellido, ''), ' ', 
                IFNULL(o.Apellido2, '')
            ) AS operario_nombre,
            fd.comentarios,
            fdo.monto AS monto_original,
            fdo.monto AS monto,
            'ver_faltante_danos.php' AS url_ver,
            fdo.cod_contrato AS cod_contrato,
            fd.fecha_hora_regsys AS fecha_registro,
            -- NUEVA COLUMNA: Estado (usando fecha local)
            CASE 
                WHEN DAY(DATE_SUB(fd.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 5 AND 12 THEN 'Planilla Primer Quincena'
                WHEN DAY(DATE_SUB(fd.fecha_hora_regsys, INTERVAL 6 HOUR)) BETWEEN 13 AND 26 THEN 'Planilla Segunda Quincena'
                ELSE 'Propina'
            END AS estado_deduccion,
            IFNULL(fdo.cobrado, 0) AS cobrado
        FROM faltante_danos fd
        JOIN faltante_danos_operarios fdo ON fd.id = fdo.faltante_id
        JOIN Operarios o ON fdo.operario_id = o.CodOperario
        JOIN sucursales s ON fd.sucursal_codigo = s.codigo)
        
        UNION ALL
        
        (SELECT 
            'faltante_caja' AS tipo,
            fc.id,
            -- PARA FALTANTE_CAJA, USAR FECHA DIRECTAMENTE SIN CONVERSIÓN
            fc.fecha AS fecha_evento_local,
            fc.fecha AS fecha_evento_utc, -- Mismo valor ya que no necesita conversión
            fc.fecha_deduccion,
            fc.sucursal_id,
            s.nombre AS sucursal_nombre,
            fc.cod_contrato AS operario_id,
            fc.operario_nombre AS operario_nombre,
            fc.comentarios,
            fc.monto AS monto_original,
            fc.monto AS monto,
            'ver_faltante_caja.php' AS url_ver,
            fc.cod_contrato AS cod_contrato,
            fc.fecha_hora_regsys AS fecha_registro,
            -- NUEVA COLUMNA: Estado (para faltante_caja usa fecha directamente)
            CASE 
                WHEN DAY(fc.fecha) BETWEEN 5 AND 12 THEN 'Planilla Primer Quincena'
                WHEN DAY(fc.fecha) BETWEEN 13 AND 26 THEN 'Planilla Segunda Quincena'
                ELSE 'Propina'
            END AS estado_deduccion,
            IFNULL(fc.cobrado, 0) AS cobrado
        FROM faltante_caja fc
        JOIN sucursales s ON fc.sucursal_id = s.codigo)
    ";

    // Aplicar filtros SOBRE LA FECHA LOCAL
    $where = [];
    $params = [];

    if ($operario_id > 0) {
        // Siempre buscar por cod_contrato (operario_id ahora representa el cod_contrato)
        $where[] = "cod_contrato = ?";
        $params[] = $operario_id;
    }

    if ($sucursal_id != 'todas') {
        $where[] = "sucursal_id = ?";
        $params[] = $sucursal_id;
    }

    if (!empty($fecha_desde)) {
        $where[] = "DATE(fecha_evento_local) >= ?";  // FILTRAR POR FECHA LOCAL
        $params[] = $fecha_desde;
    }

    if (!empty($fecha_hasta)) {
        $where[] = "DATE(fecha_evento_local) <= ?";  // FILTRAR POR FECHA LOCAL
        $params[] = $fecha_hasta;
    }

    if ($cobrado_filtro !== 'todos') {
        $where[] = "cobrado = ?";
        $params[] = ($cobrado_filtro === 'si' ? 1 : 0);
    }

    // Crear una consulta derivada para aplicar los filtros
    if (!empty($where)) {
        $sql = "SELECT * FROM ($sql) AS subquery WHERE " . implode(" AND ", $where);
    }

    // Ordenar por fecha de evento local descendente
    $sql .= " ORDER BY fecha_evento_local DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Solo obtener lista de operarios y sucursales si NO es operario/líder
    if (!$esOperarioOLider) {
        // Obtener lista de operarios para el filtro (mostrando cod_contrato como identificador)
        $sql_operarios = "SELECT 
                         COALESCE(c.CodContrato, o.CodOperario) as id_display,
                         CONCAT(
                             IFNULL(o.Nombre, ''), ' ', 
                             IFNULL(o.Nombre2, ''), ' ', 
                             IFNULL(o.Apellido, ''), ' ', 
                             IFNULL(o.Apellido2, '')
                             -- ' (', COALESCE(c.CodContrato, o.CodOperario), ')'
                         ) AS nombre_completo 
                         FROM Operarios o
                         LEFT JOIN (
                             SELECT cod_operario, MAX(CodContrato) as CodContrato 
                             FROM Contratos 
                             GROUP BY cod_operario
                         ) c ON o.CodOperario = c.cod_operario
                         LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
                         WHERE (anc.CodNivelesCargos IS NULL OR anc.CodNivelesCargos != 27)
                         AND o.Operativo = 1
                         GROUP BY o.CodOperario
                         ORDER BY nombre_completo";
        $operarios = $db->query($sql_operarios)->fetchAll(PDO::FETCH_ASSOC);

        // Obtener lista de sucursales para el filtro
        $sql_sucursales = "SELECT codigo, nombre FROM sucursales WHERE activa = 1 ORDER BY nombre";
        $sucursales = $db->query($sql_sucursales)->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}

// Verificar si se solicitó la exportación a Excel
if (isset($_GET['exportar_excel'])) {
    // Reconstruir la consulta completa con el filtro de monto
    $sql_export = "
        SELECT * FROM (
            $sql
        ) AS datos_exportacion
        WHERE monto != 0
        ORDER BY fecha_evento_local DESC
    ";

    // Usar los mismos parámetros del filtro principal (ya incluyen las fechas)
    $params_export = $params;

    // Ejecutar la consulta modificada
    $stmt_export = $db->prepare($sql_export);
    $stmt_export->execute($params_export);
    $registros_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

    // Configurar headers para descarga de archivo Excel - CON RANGO DE FECHAS
    $nombre_archivo = "deducciones_" . str_replace('-', '', $fecha_desde) . "_" . str_replace('-', '', $fecha_hasta) . ".xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

    // Función para calcular fecha de aplicación basada en estado y fecha de evento
    function calcularFechaAplicacion($estado, $fecha_evento_local)
    {  // CAMBIAR PARÁMETRO
        $fecha = new DateTime($fecha_evento_local);  // USAR fecha_evento_local DIRECTAMENTE
        $dia_evento = (int) $fecha->format('d');

        switch ($estado) {
            case 'Planilla Primer Quincena':
                // 15 del mismo mes
                $fecha->setDate($fecha->format('Y'), $fecha->format('m'), 15);
                return $fecha->format('d-M-y');

            case 'Planilla Segunda Quincena':
                // Último día del mismo mes
                $fecha->modify('last day of this month');
                return $fecha->format('d-M-y');

            case 'Propina':
                // Si el evento es después del día 7, la propina se aplica el 7 del SIGUIENTE mes
                // Si el evento es antes o igual al día 7, la propina se aplica el 7 del MISMO mes
                if ($dia_evento > 7) {
                    $fecha->modify('first day of next month');
                }
                $fecha->setDate($fecha->format('Y'), $fecha->format('m'), 7);
                return $fecha->format('d-M-y');

            default:
                return 'Fecha no definida';
        }
    }

    // Iniciar salida

    // MARCAR COMO COBRADOS AL EXPORTAR
    foreach ($registros_export as $reg) {
        $tabla_update = '';
        switch ($reg['tipo']) {
            case 'facturacion':
                $tabla_update = 'auditoria_facturacion';
                break;
            case 'caja_chica':
                $tabla_update = 'auditoria_caja_chica';
                break;
            case 'inventario':
                $tabla_update = 'auditoria_inventario_operarios';
                break;
            case 'faltante_inventario':
                $tabla_update = 'faltante_inventario_operarios';
                break;
            case 'faltante_danos':
                $tabla_update = 'faltante_danos_operarios';
                break;
            case 'faltante_caja':
                $tabla_update = 'faltante_caja';
                break;
        }
        if ($tabla_update) {
            $stmt_upd = $db->prepare("UPDATE `$tabla_update` SET cobrado = 1 WHERE id = ?");
            $stmt_upd->execute([$reg['id']]);
        }
    }

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Persona</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha Incidente</th>';
    echo '<th>Fecha Deducción</th>';
    echo '<th>Monto a Descontar</th>';
    // echo '<th>Tipo CONCEPTO</th>';
    echo '<th>Detalle</th>'; // MODIFICADO: Ahora incluirá el concepto entre paréntesis
    echo '<th>Fecha Registro</th>'; // NUEVA COLUMNA
    echo '<th>Aplicarse en</th>'; // MODIFICADO: Ahora mostrará fecha específica
    echo '<th>Cobrado</th>'; // NUEVA COLUMNA
    // ELIMINAR: echo '<th>Código Contrato</th>'; // Eliminamos esta columna
    echo '</tr>';

    foreach ($registros_export as $registro) {
        // USAR fecha_evento_local DIRECTAMENTE (ya convertida)
        $fecha_evento_formatted = formatoFechaCorta($registro['fecha_evento_local']);

        $fecha_deduccion = '';
        if (!empty($registro['fecha_deduccion'])) {
            // Para fecha_deduccion también aplicar conversión si es necesario
            $fecha_ded = new DateTime($registro['fecha_deduccion']);
            if ($registro['tipo'] != 'faltante_caja') {
                $fecha_ded->sub(new DateInterval('PT6H'));
            }
            $fecha_deduccion = formatoFechaCorta($fecha_ded->format('Y-m-d'));
        }

        // Determinar el tipo de auditoría
        $tipo = $registro['tipo'];
        $tipo_text = '';

        switch ($tipo) {
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

        // Obtener el monto como valor absoluto
        $monto_exportar = abs($registro['monto']);

        // Obtener código de contrato
        $cod_contrato = $registro['cod_contrato'] ?? '';

        // Combinar comentarios con tipo de concepto entre paréntesis
        $detalle_combinado = htmlspecialchars($registro['comentarios'] ?? '');
        if (!empty($detalle_combinado)) {
            $detalle_combinado .= " (" . $tipo_text . ")";
        } else {
            $detalle_combinado = "(" . $tipo_text . ")";
        }

        // Formatear fecha de registro (ajustar -6 horas para tipos que no son faltante_caja)
        $fecha_registro = '';
        if (!empty($registro['fecha_registro'])) {
            $fecha_reg = new DateTime($registro['fecha_registro']);
            if ($registro['tipo'] != 'faltante_caja') {
                $fecha_reg->sub(new DateInterval('PT6H'));
            }
            $fecha_registro = $fecha_reg->format('d-m-Y H:i:s');
        }

        // Calcular fecha de aplicación basada en el estado
        $fecha_aplicacion = calcularFechaAplicacion(
            $registro['estado_deduccion'] ?? '',
            $registro['fecha_evento_local']  // Usar fecha local
        );

        // Mostrar código de contrato + nombre en la columna Persona
        $persona_completa = $cod_contrato . ' ' . $registro['operario_nombre'];

        echo '<tr>';
        //echo '<td>' . $registro['operario_id'] . '</td>';
        echo '<td>' . htmlspecialchars($cod_contrato) . '</td>'; // Mostrar código de contrato
        echo '<td>' . $persona_completa . '</td>'; // MODIFICADO
        echo '<td>' . $registro['sucursal_nombre'] . '</td>';
        echo '<td>' . $fecha_evento_formatted . '</td>';
        echo '<td>' . $fecha_deduccion . '</td>';
        echo '<td>' . number_format($monto_exportar, 2) . '</td>';
        // echo '<td>' . $tipo_text . '</td>';
        echo '<td>' . $detalle_combinado . '</td>'; // MODIFICADO de comentarios a combinado comentarios+tipo
        echo '<td>' . $fecha_registro . '</td>'; // NUEVA COLUMNA
        // echo '<td>' . htmlspecialchars($registro['estado_deduccion'] ?? '') . '</td>';
        echo '<td>' . $fecha_aplicacion . '</td>'; // MODIFICADO: Mostrar fecha específica
        echo '<td>' . ($registro['cobrado'] == 1 ? 'Sí' : 'No') . '</td>'; // NUEVA COLUMNA
        // ELIMINAR: echo '<td>' . htmlspecialchars($cod_contrato) . '</td>'; // Eliminamos esta columna
        echo '</tr>';
    }

    echo '</table>';
    exit;
}

// Verificar si se solicitó la exportación a Excel para contabilidad
if (isset($_GET['exportar_contabilidad'])) {
    // Reconstruir la consulta completa con el filtro de monto distinto de cero
    $sql_export = "
        SELECT * FROM (
            $sql
        ) AS datos_exportacion
        WHERE monto != 0
        ORDER BY operario_nombre, fecha_evento DESC
    ";

    // Ejecutar la consulta modificada
    $stmt_export = $db->prepare($sql_export);
    $stmt_export->execute($params);
    $registros_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por operario
    $deducciones_por_operario = [];
    foreach ($registros_export as $registro) {
        $operario_id = $registro['operario_id'];
        if (!isset($deducciones_por_operario[$operario_id])) {
            $deducciones_por_operario[$operario_id] = [
                'cod_operario' => $operario_id,
                'nombre_completo' => $registro['operario_nombre'],
                'cod_contrato' => $registro['cod_contrato'], // AGREGAR
                'sucursal' => $registro['sucursal_nombre'],
                'deducciones' => []
            ];
        }

        // Formatear fechas
        $fecha_evento = new DateTime($registro['fecha_evento']);
        $fecha_evento->sub(new DateInterval('PT6H'));
        $fecha_evento_formatted = $fecha_evento->format('d-m-Y H:i');

        $fecha_deduccion = '';
        if (!empty($registro['fecha_deduccion'])) {
            $fecha_ded = new DateTime($registro['fecha_deduccion']);
            $fecha_ded->sub(new DateInterval('PT6H'));
            $fecha_deduccion = $fecha_ded->format('d-m-Y');
        }

        // Determinar el tipo de auditoría
        $tipo = $registro['tipo'];
        $tipo_text = '';

        switch ($tipo) {
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

        $deducciones_por_operario[$operario_id]['deducciones'][] = [
            'id' => $registro['id'], // AGREGAR
            'fecha_evento' => $fecha_evento_formatted,
            'tipo' => $tipo_text,
            'tipo_raw' => $registro['tipo'], // AGREGAR
            'fecha_deduccion' => $fecha_deduccion,
            'monto' => $registro['monto'],
            'cobrado' => $registro['cobrado'] // AGREGAR
        ];
    }

    // MARCAR COMO COBRADOS AL EXPORTAR CONTABILIDAD
    foreach ($registros_export as $reg) {
        $tabla_update = '';
        switch ($reg['tipo']) {
            case 'facturacion':
                $tabla_update = 'auditoria_facturacion';
                break;
            case 'caja_chica':
                $tabla_update = 'auditoria_caja_chica';
                break;
            case 'inventario':
                $tabla_update = 'auditoria_inventario_operarios';
                break;
            case 'faltante_inventario':
                $tabla_update = 'faltante_inventario_operarios';
                break;
            case 'faltante_danos':
                $tabla_update = 'faltante_danos_operarios';
                break;
            case 'faltante_caja':
                $tabla_update = 'faltante_caja';
                break;
        }
        if ($tabla_update) {
            $stmt_upd = $db->prepare("UPDATE `$tabla_update` SET cobrado = 1 WHERE id = ?");
            $stmt_upd->execute([$reg['id']]);
        }
    }

    // Configurar headers para descarga de archivo Excel - CON RANGO DE FECHAS
    $nombre_archivo = "deducciones_contabilidad_" . str_replace('-', '', $fecha_desde) . "_" . str_replace('-', '', $fecha_hasta) . ".xls";
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

    // Iniciar salida
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código de Operario</th>';
    echo '<th>Nombre completo</th>';
    echo '<th>Fecha evento</th>';
    echo '<th>Tipo</th>';
    echo '<th>Fecha a deducir</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Monto (C$)</th>';
    echo '<th>Detalle</th>'; // NUEVA COLUMNA
    echo '<th>Fecha Registro</th>'; // NUEVA COLUMNA
    echo '<th>Cobrado</th>'; // NUEVA COLUMNA
    // ELIMINAR: echo '<th>Código Contrato</th>'; // Eliminamos esta columna
    echo '</tr>';

    foreach ($deducciones_por_operario as $operario) {
        foreach ($operario['deducciones'] as $deduccion) {
            // Ajustar fecha_evento (UTC a hora local Nicaragua -6 horas), excepto para faltante_caja
            if ($deduccion['tipo'] == 'Faltante de Caja') {
                // Para faltante_caja, usar la fecha directamente sin restar 6 horas
                $fecha_evento_formatted = formatoFechaCorta($deduccion['fecha_evento']);
            } else {
                // Para los demás tipos, restar 6 horas
                $fecha_evento = new DateTime($deduccion['fecha_evento']);
                $fecha_evento->sub(new DateInterval('PT6H'));
                $fecha_evento_formatted = formatoFechaCorta($fecha_evento->format('Y-m-d'));
            }

            // MODIFICADO: Combinar comentarios con tipo de concepto
            $detalle_combinado = htmlspecialchars($deduccion['comentarios'] ?? '');
            if (!empty($detalle_combinado)) {
                $detalle_combinado .= " (" . $deduccion['tipo'] . ")";
            } else {
                $detalle_combinado = "(" . $deduccion['tipo'] . ")";
            }

            // Formatear fecha de registro
            $fecha_registro = '';
            if (!empty($deduccion['fecha_registro'])) {
                $fecha_reg = new DateTime($deduccion['fecha_registro']);
                $fecha_reg->sub(new DateInterval('PT6H'));
                $fecha_registro = $fecha_reg->format('d-m-Y H:i:s');
            }

            // MODIFICADO: Mostrar código de contrato + nombre
            $nombre_completo_con_codigo = $operario['cod_contrato'] . ' ' . $operario['nombre_completo'];

            echo '<tr>';
            // echo '<td>' . $operario['cod_operario'] . '</td>';
            echo '<td>' . htmlspecialchars($operario['cod_contrato'] ?? '') . '</td>'; // MODIFICADO: código de contrato
            echo '<td>' . htmlspecialchars($nombre_completo_con_codigo) . '</td>'; // MODIFICADO
            echo '<td>' . $fecha_evento_formatted . '</td>';
            echo '<td>' . $deduccion['tipo'] . '</td>';
            echo '<td>' . $deduccion['fecha_deduccion'] . '</td>';
            echo '<td>' . htmlspecialchars($operario['sucursal']) . '</td>';
            echo '<td>' . number_format(abs($deduccion['monto']), 2) . '</td>';
            echo '<td>' . $detalle_combinado . '</td>'; // NUEVA COLUMNA
            echo '<td>' . $fecha_registro . '</td>'; // NUEVA COLUMNA
            // ELIMINAR: echo '<td>' . htmlspecialchars($operario['cod_contrato'] ?? '') . '</td>'; // Eliminamos esta columna
            echo '</tr>';
        }
    }

    echo '</table>';
    exit;
}

// Calcular el total de deducciones según los filtros aplicados
$total_deducciones = 0;
$total_registros = 0;

foreach ($registros as $registro) {
    // Verificar que el monto no sea nulo antes de sumar
    if (isset($registro['monto']) && $registro['monto'] !== null) {
        $total_deducciones += abs(floatval($registro['monto']));
        $total_registros++;
    }
}

/**
 * Obtiene las fechas para mostrar (-3 días de cada quincena)
 * Primera quincena: 12 días antes del 15 (del 3 al 12)
 * Segunda quincena: 13 días antes del fin de mes (del 13 al 28)
 */
function obtenerFechasQuincenaActual()
{
    $hoy = new DateTime();
    $diaActual = (int) $hoy->format('d');
    $mesActual = (int) $hoy->format('m');
    $anioActual = (int) $hoy->format('Y');

    // Determinar en qué quincena estamos
    if ($diaActual <= 15) {
        // Primera quincena (1-15): mostrar del 28 del mes anterior al 12 del actual
        $fechaHasta = new DateTime("$anioActual-$mesActual-12");
        $fechaDesde = clone $fechaHasta;
        $fechaDesde->modify('-9 days'); // Del 3 al 12 (10 días)
    } else {
        // Segunda quincena (16-fin de mes): mostrar del 13 al 28 del actual
        $fechaDesde = new DateTime("$anioActual-$mesActual-13");
        $fechaHasta = new DateTime("$anioActual-$mesActual-28");

        // Si el mes tiene menos de 28 días, ajustar al último día
        $ultimoDiaMes = (int) $fechaHasta->format('t');
        if ($ultimoDiaMes < 28) {
            $fechaHasta = new DateTime("$anioActual-$mesActual-$ultimoDiaMes");
        }
    }

    return [
        'desde' => $fechaDesde->format('Y-m-d'),
        'hasta' => $fechaHasta->format('Y-m-d')
    ];
}

// Verificar si se solicitó la exportación a Excel solo para faltantes de caja
if (isset($_GET['exportar_faltante_caja'])) {
    // Consulta específica para faltantes de caja
    $sql_export = "
        SELECT 
            fc.id,
            fc.fecha AS fecha_evento,
            fc.fecha_deduccion,
            fc.sucursal_id,
            s.nombre AS sucursal_nombre,
            fc.cod_contrato AS operario_id, -- USAR cod_contrato directamente
            fc.operario_nombre,
            fc.comentarios,
            fc.monto,
            fc.cod_contrato AS cod_contrato,  -- USAR cod_contrato directamente
            IFNULL(fc.cobrado, 0) AS cobrado
        FROM faltante_caja fc
        JOIN sucursales s ON fc.sucursal_id = s.codigo
        WHERE 1=1
    ";

    $params_export = [];

    // Aplicar mismos filtros
    if ($operario_id > 0) {
        $sql_export .= " AND fc.cod_contrato = ?"; // Buscar por cod_contrato
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

    // MARCAR COMO COBRADOS AL EXPORTAR FALTANTE CAJA
    foreach ($registros_export as $reg) {
        $stmt_upd = $db->prepare("UPDATE `faltante_caja` SET cobrado = 1 WHERE id = ?");
        $stmt_upd->execute([$reg['id']]);
    }

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código</th>';
    echo '<th>Persona</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha Incidente</th>';
    echo '<th>Fecha Deducción</th>';
    echo '<th>Monto (C$)</th>';
    echo '<th>Detalle</th>'; // MODIFICADO: Incluirá el concepto
    echo '<th>Fecha Registro</th>'; // NUEVA COLUMNA
    echo '<th>Cobrado</th>'; // NUEVA COLUMNA
    // ELIMINAR: echo '<th>Código Contrato</th>'; // Eliminamos esta columna
    echo '</tr>';

    foreach ($registros_export as $registro) {
        // Para faltante_caja, usar fecha directamente sin restar 6 horas
        $fecha_evento_formatted = formatoFechaCorta($registro['fecha_evento']);

        $fecha_deduccion = '';
        if (!empty($registro['fecha_deduccion'])) {
            $fecha_deduccion = formatoFechaCorta($registro['fecha_deduccion']);
        }

        // MODIFICADO: Combinar comentarios con tipo de concepto
        $detalle_combinado = htmlspecialchars($registro['comentarios'] ?? '');
        if (!empty($detalle_combinado)) {
            $detalle_combinado .= " (Faltante de Caja)";
        } else {
            $detalle_combinado = "(Faltante de Caja)";
        }

        // Formatear fecha de registro
        $fecha_registro = '';
        if (!empty($registro['fecha_registro'])) {
            $fecha_reg = new DateTime($registro['fecha_registro']);
            $fecha_reg->sub(new DateInterval('PT6H'));
            $fecha_registro = $fecha_reg->format('d-m-Y H:i:s');
        }

        // MODIFICADO: Mostrar código de contrato + nombre
        $persona_completa = $registro['cod_contrato'] . ' ' . $registro['operario_nombre'];

        echo '<tr>';
        // echo '<td>' . $registro['operario_id'] . '</td>';
        echo '<td>' . htmlspecialchars($registro['cod_contrato'] ?? '') . '</td>'; // MODIFICADO: código de contrato
        echo '<td>' . $persona_completa . '</td>'; // MODIFICADO
        echo '<td>' . $registro['sucursal_nombre'] . '</td>';
        echo '<td>' . $fecha_evento_formatted . '</td>';
        echo '<td>' . $fecha_deduccion . '</td>';
        echo '<td>' . number_format(abs($registro['monto']), 2) . '</td>';
        echo '<td>' . $detalle_combinado . '</td>'; // MODIFICADO
        echo '<td>' . $fecha_registro . '</td>'; // NUEVA COLUMNA
        echo '<td>' . ($registro['cobrado'] == 1 ? 'Sí' : 'No') . '</td>'; // NUEVA COLUMNA
        // ELIMINAR: echo '<td>' . htmlspecialchars($registro['cod_contrato'] ?? '') . '</td>'; // Eliminamos esta columna
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
    <title>Deducciones de Operarios</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="icon" href="../icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
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

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
        }

        th {
            background-color: #0E544C;
            color: white;
        }

        .filtros-container {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
            /* Verde para valores 0 */
        }

        .monto-positivo {
            color: #27ae60;
            /* Rojo para valores > 0 */
        }

        .monto-negativo {
            color: #e74c3c;
        }

        /* Efecto al pasar el mouse sobre las filas */
        tr:hover {
            background-color: rgba(81, 184, 172, 0.1) !important;
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

            .filtros-form {
                grid-template-columns: 1fr;
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
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
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .modal-contenido h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .btn-agregar.excel-contabilidad {
            background-color: #6f42c1;
            border-color: #6f42c1;
            color: white;
        }

        .btn-agregar.excel-contabilidad:hover {
            background-color: #5a2d9e;
            border-color: #5a2d9e;
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
        }

        /* Estilos para el autocompletado */
        #operarios-sugerencias {
            width: calc(100% - 2px);
            /* Mismo ancho que el input */
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-top: -1px;
            /* Para que se pegue al input */
            position: absolute;
            top: 100%;
            /* Posiciona el dropdown justo debajo del input */
            left: 0;
            z-index: 1000;
        }

        #operarios-sugerencias div:hover {
            background-color: #51B8AC !important;
        }

        #operarios-sugerencias div:last-child {
            border-bottom: none;
        }

        /* Asegurar que el input tenga un z-index menor */
        .filtro-group input[type="text"] {
            position: relative;
            z-index: 1;
        }

        .encabezado {
            text-align: center;
        }

        /* Agregar estos estilos en la sección CSS existente */
        .badge-primary {
            background-color: #3498db;
            border: 1px solid #2980b9;
        }

        .badge-info {
            background-color: #17a2b8;
            border: 1px solid #138496;
        }

        .badge-warning {
            background-color: #f39c12;
            border: 1px solid #e67e22;
        }

        .badge-secondary {
            background-color: #6c757d;
            border: 1px solid #545b62;
        }
    </style>
</head>

<body>
    <div class="contenedor-principal">
        <header>
            <div class="header-container">
                <div class="logo-container">
                    <img src="../Logo.svg" alt="Batidos Pitaya" class="logo">
                </div>

                <div class="buttons-container">
                    <?php if ($esAdmin || verificarAccesoCargo([11, 16, 21])): ?>
                        <a href="auditorias_consolidadas.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'auditorias_consolidadas.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Historial</span>
                        </a>
                    <?php endif; ?>

                    <a href="deducciones_total.php"
                        class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'deducciones_total.php' ? 'activo' : '' ?>">
                        <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Deducciones</span>
                    </a>

                    <?php if ($esAdmin || verificarAccesoCargo([2, 5])): ?>
                        <a href="../../../contabilidad/boleta_pago.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'boleta_pago.php' ? 'activo' : '' ?>">
                            <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Boleta de Pago</span>
                        </a>
                    <?php endif; ?>

                    <?php if ($esAdmin || verificarAccesoCargo([8, 16])): ?>
                        <a href="faltante_caja.php"
                            class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'faltante_caja.php' ? 'activo' : '' ?>">
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
                                htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?>
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

        <?php if (!$esOperarioOLider): ?>
            <!-- Filtros - Solo visible para cargos diferentes a 2 y 5 -->
            <div class="filtros-container">
                <form method="get" action="deducciones_total.php" class="filtros-form">
                    <div class="filtro-group">
                        <label for="sucursal">Sucursal</label>
                        <select id="sucursal" name="sucursal">
                            <option value="todas">Todas las sucursales</option>
                            <?php foreach ($sucursales as $sucursal): ?>
                                <option value="<?php echo $sucursal['codigo']; ?>" <?php echo $sucursal['codigo'] == $sucursal_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sucursal['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filtro-group" style="position: relative;">
                        <label for="operario">Colaborador</label>
                        <input type="text" id="operario" name="operario_text" placeholder="Escriba para buscar..." value="<?php
                        if ($operario_id > 0) {
                            foreach ($operarios as $op) {
                                if ($op['id_display'] == $operario_id) { // CAMBIADO: usar id_display
                                    echo htmlspecialchars($op['nombre_completo']);
                                    break;
                                }
                            }
                        } else {
                            echo 'Todos los colaboradores';
                        }
                        ?>" autocomplete="off">
                        <input type="hidden" id="operario_id" name="operario" value="<?php echo $operario_id; ?>">
                        <div id="operarios-sugerencias"
                            style="display: none; position: absolute; top: 100%; left: 0; width: 100%; background: white; border: 1px solid #ddd; border-top: none; max-height: 200px; overflow-y: auto; z-index: 1000;">
                        </div>
                    </div>

                    <div class="filtro-group">
                        <label for="fecha_desde">Desde</label>
                        <input type="date" id="fecha_desde" name="fecha_desde"
                            value="<?php echo htmlspecialchars($fecha_desde); ?>">
                    </div>

                    <div class="filtro-group">
                        <label for="fecha_hasta">Hasta</label>
                        <input type="date" id="fecha_hasta" name="fecha_hasta"
                            value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                    </div>

                    <div class="filtro-group" style="display:none;">
                        <label for="cobrado">Estado de Cobro</label>
                        <select id="cobrado" name="cobrado">
                            <option value="todos" <?php echo $cobrado_filtro === 'todos' ? 'selected' : ''; ?>>Todos</option>
                            <option value="si" <?php echo $cobrado_filtro === 'si' ? 'selected' : ''; ?>>Cobrados</option>
                            <option value="no" <?php echo $cobrado_filtro === 'no' ? 'selected' : ''; ?>>Pendientes</option>
                        </select>
                    </div>

                    <div class="filtro-buttons">
                        <button type="submit" class="btn-aplicar">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                        <a style="display:none;" href="deducciones_total.php" class="btn-limpiar">
                            <i class="fas fa-times"></i> Limpiar
                        </a>

                        <a href="deducciones_total.php?<?php
                        echo http_build_query([
                            'operario' => $operario_id,
                            'sucursal' => $sucursal_id,
                            'fecha_desde' => $fecha_desde,
                            'fecha_hasta' => $fecha_hasta,
                            'exportar_excel' => 1
                        ]);
                        ?>" class="btn-agregar excel">
                            <i class="fas fa-file-excel"></i> Exportar
                        </a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Nota informativa para operarios y líderes -->
            <div
                style="background-color: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                <h4 style="margin: 0 0 10px 0; color: #1976D2;">
                    <i class="fas fa-info-circle"></i> Información Importante
                </h4>
                <p style="margin: 0; color: #555; line-height: 1.5;">
                    <strong>Nota:</strong> Este reporte muestra las deducciones correspondientes a los últimos 20 días,
                    correspondientes a auditorías de desempeño y de efectivo del período
                    <?php echo formatoFechaCorta($fecha_desde) . ' al ' . formatoFechaCorta($fecha_hasta); ?>.
                </p>
            </div>
        <?php endif; ?>

        <!-- Botón de exportar a Excel -->
        <div style="text-align: right; margin-bottom: 10px;">
            <h3 style="margin: 0; color: #333; display:none;">
                Total de Deducciones:
                <span style="color: #e74c3c; font-weight: bold;">
                    C$ <?php echo isset($total_deducciones) ? number_format($total_deducciones, 2) : '0.00'; ?>
                </span>
            </h3>

            <a style="display:none;" href="deducciones_total.php?<?php
            echo http_build_query([
                'operario' => $operario_id,
                'sucursal' => $sucursal_id,
                'fecha_desde' => $fecha_desde,
                'fecha_hasta' => $fecha_hasta,
                'exportar_excel' => 1
            ]);
            ?>" class="btn-agregar excel">
                <i class="fas fa-file-excel"></i> Exportar
            </a>

            <!-- Nuevo botón para exportar para contabilidad -->
            <a style="display:none;" href="deducciones_total.php?<?php
            echo http_build_query([
                'operario' => $operario_id,
                'sucursal' => $sucursal_id,
                'fecha_desde' => $fecha_desde,
                'fecha_hasta' => $fecha_hasta,
                'exportar_contabilidad' => 1
            ]);
            ?>" class="btn-agregar excel-contabilidad" style="background-color: #6f42c1; border-color: #6f42c1;">
                <i class="fas fa-file-excel"></i> Exportar para Contabilidad
            </a>

            <?php if ($esAdmin || verificarAccesoCargo([8, 16])): ?>
                <!-- Nuevo botón para exportar solo faltantes de caja -->
                <a style="display:none;" href="deducciones_total.php?<?php
                echo http_build_query([
                    'operario' => $operario_id,
                    'sucursal' => $sucursal_id,
                    'fecha_desde' => $fecha_desde,
                    'fecha_hasta' => $fecha_hasta,
                    'exportar_faltante_caja' => 1
                ]);
                ?>" class="btn-agregar excel" style="background-color: #f39c12; border-color: #f39c12; color: white;">
                    <i class="fas fa-file-excel"></i> Exportar Faltantes Caja
                </a>
            <?php endif; ?>
        </div>

        <!-- Resumen de total de deducciones -->
        <div
            style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ddd; text-align: center; display:none;">
            <h3 style="margin: 0; color: #333;">
                Total de Deducciones:
                <span style="color: #e74c3c; font-weight: bold;">
                    C$ <?php echo number_format($total_deducciones, 2); ?>
                </span>
            </h3>
            <?php if (!empty($registros)): ?>
                <p style="margin: 5px 0 0; color: #666; font-size: 14px;">
                    Mostrando <?php echo $total_registros; ?> registro(s) -
                    <?php if ($operario_id > 0): ?>
                        Operario: <?php
                        $nombre_operario = '';
                        foreach ($operarios as $op) {
                            if ($op['CodOperario'] == $operario_id) {
                                $nombre_operario = $op['nombre_completo'];
                                break;
                            }
                        }
                        echo htmlspecialchars($nombre_operario);
                        ?> |
                    <?php endif; ?>
                    <?php if ($sucursal_id != 'todas'): ?>
                        Sucursal: <?php
                        $nombre_sucursal = '';
                        foreach ($sucursales as $suc) {
                            if ($suc['codigo'] == $sucursal_id) {
                                $nombre_sucursal = $suc['nombre'];
                                break;
                            }
                        }
                        echo htmlspecialchars($nombre_sucursal);
                        ?> |
                    <?php endif; ?>
                    Período: <?php
                    echo formatoFechaCorta($fecha_desde) . ' - ' . formatoFechaCorta($fecha_hasta);
                    ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Tabla de resultados -->
        <table id="listaDeducciones">
            <thead>
                <tr>
                    <?php if (!$esOperarioOLider): ?>
                        <th class="encabezado">Colaborador</th>
                    <?php endif; ?>
                    <th class="encabezado">Fecha Evento</th>
                    <?php if (verificarAccesoCargo([11]) && !$esOperarioOLider): ?>
                        <!--<th class="encabezado">Fecha a Deducir</th> -->
                    <?php endif; ?>
                    <th class="encabezado">Sucursal</th>
                    <th class="encabezado">Detalle</th>
                    <th class="encabezado">Monto (C$)</th>
                    <th class="encabezado">Tipo</th>
                    <!-- NUEVA COLUMNA: Estado -->
                    <th class="encabezado">Aplicarse en</th>
                    <th class="encabezado">Cobrado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registros)): ?>
                    <tr>
                        <td colspan="<?php echo $esOperarioOLider ? '5' : '7'; ?>"
                            style="text-align:center; background-color:#fff;">
                            No se encontraron registros con los filtros aplicados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($registros as $registro): ?>
                        <tr>
                            <?php if (!$esOperarioOLider): ?>
                                <td><?php echo htmlspecialchars($registro['operario_nombre'] ?? ''); ?></td>
                            <?php endif; ?>
                            <td>
                                <?php
                                // USAR DIRECTAMENTE fecha_evento_local QUE YA ESTÁ CONVERTIDA
                                if ($registro['tipo'] == 'faltante_caja') {
                                    // Para faltante_caja, ya está en hora local
                                    echo formatoFechaCorta($registro['fecha_evento_local']);
                                } else {
                                    // Para los demás tipos, ya está convertida - mostrar fecha y hora
                                    $fecha_evento = new DateTime($registro['fecha_evento_local']);
                                    echo formatoFechaCorta($fecha_evento->format('Y-m-d')) . ' ' . $fecha_evento->format('H:i');
                                }
                                ?>
                            </td>
                            <?php if (verificarAccesoCargo([11]) && !$esOperarioOLider): ?>
                                <!--<td>
                                 <?php
                                 /**if (!empty($registro['fecha_deduccion'])) {
                                     $fecha_deduccion = new DateTime($registro['fecha_deduccion']);
                                     $fecha_deduccion->sub(new DateInterval('PT6H'));
                                     echo formatoFechaCorta($fecha_deduccion->format('Y-m-d'));
                                     echo ' <button onclick="abrirModalAsignacion(\''.$registro['tipo'].'\', '.$registro['id'].', '.$registro['operario_id'].', \''.$registro['sucursal_id'].'\', '.$registro['monto'].', \''.$registro['fecha_evento'].'\', \''.htmlspecialchars($registro['comentarios'], ENT_QUOTES).'\')" style="background: none; border: none; color: #51B8AC; cursor: pointer;">
                                         <i class="fas fa-edit"></i>
                                     </button>';
                                 } else {
                                     echo '<span style="color: #999;">No asignada</span>';
                                     echo ' <button onclick="abrirModalAsignacion(\''.$registro['tipo'].'\', '.$registro['id'].', '.$registro['operario_id'].', \''.$registro['sucursal_id'].'\', '.$registro['monto'].', \''.$registro['fecha_evento'].'\', \''.htmlspecialchars($registro['comentarios'] ?? '', ENT_QUOTES).'\')" style="background: none; border: none; color: #51B8AC; cursor: pointer;">
                                             <i class="fas fa-calendar-plus"></i> Asignar
                                         </button>';
                                 }*/
                                 ?>
                            </td> -->
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($registro['sucursal_nombre']); ?></td>
                            <td style="text-align: left;">
                                <?php if (!empty($registro['comentarios'])): ?>
                                    <?php echo htmlspecialchars($registro['comentarios']); ?>
                                <?php else: ?>
                                    <span style="color: #6c757d; font-style: italic; font-size: 0.9em;">
                                        <?php
                                        // Mostrar "Faltante de caja + fecha" cuando no hay comentarios
                                        if ($registro['tipo'] == 'faltante_caja') {
                                            // USAR fecha_evento_local QUE YA ESTÁ CONVERTIDA
                                            $fecha_evento = new DateTime($registro['fecha_evento_local']);
                                            echo 'Faltante de caja ' . $fecha_evento->format('d/m/Y');
                                        } else {
                                            // Para otros tipos, mantener el texto original
                                            echo 'Sin comentarios';
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td
                                class="monto-faltante <?php echo ($registro['monto'] == 0) ? 'monto-positivo' : 'monto-negativo'; ?>">
                                <?php
                                // Mostrar el monto formateado según el tipo
                                echo number_format(abs($registro['monto']), 2);
                                ?>
                            </td>
                            <td>
                                <?php
                                // Mostrar el tipo de auditoría con un badge de color
                                $tipo = $registro['tipo'] ?? '';
                                $badge_class = 'badge-' . $tipo;
                                $tipo_text = '';

                                switch ($tipo) {
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
                                    default:
                                        $tipo_text = 'Desconocido';
                                        $badge_class = 'badge-default';
                                }

                                echo '<span class="badge-tipo ' . $badge_class . '">' . $tipo_text . '</span>';
                                ?>
                            </td>
                            <!-- NUEVA COLUMNA: Estado -->
                            <td>
                                <?php
                                // Mostrar el estado de la deducción SIN BADGE - SOLO TEXTO PLANO
                                $estado = $registro['estado_deduccion'] ?? '';
                                echo htmlspecialchars($estado);
                                ?>
                            </td>
                            <td>
                                <?php if ($registro['cobrado'] == 1): ?>
                                    <span class="badge-tipo badge-success" style="background-color: #28a745;"><i
                                            class="fas fa-check-circle"></i> Sí</span>
                                <?php else: ?>
                                    <span class="badge-tipo badge-secondary" style="background-color: #6c757d;"><i
                                            class="fas fa-clock"></i> No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal para asignar fecha de deducción -->
    <div id="modalDeduccion" class="modal" style="display: none;">
        <div class="modal-contenido" style="width: 400px;">
            <h3>Asignar Fecha de Deducción</h3>
            <form method="post" id="formDeduccion">
                <input type="hidden" name="asignar_fecha" value="1">
                <input type="hidden" name="tipo" id="modalTipo">
                <input type="hidden" name="id_referencia" id="modalIdReferencia">
                <input type="hidden" name="operario_id" id="modalOperarioId">
                <input type="hidden" name="sucursal_id" id="modalSucursalId">
                <input type="hidden" name="monto" id="modalMonto">
                <input type="hidden" name="fecha_evento" id="modalFechaEvento">
                <input type="hidden" name="comentarios" id="modalComentarios">

                <div style="margin-bottom: 15px;">
                    <label for="fecha_deduccion" style="display: block; margin-bottom: 5px; text-align: left;">Fecha a
                        Deducir:</label>
                    <input type="date" id="fecha_deduccion" name="fecha_deduccion" required
                        style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 20px;">
                    <button type="button" onclick="cerrarModal()"
                        style="padding: 8px 15px; background-color: #f1f1f1; border: none; border-radius: 5px; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="submit"
                        style="padding: 8px 15px; background-color: #51B8AC; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mostrar error si existe -->
    <?php if (!empty($error_asignacion)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
            <?php echo htmlspecialchars($error_asignacion); ?>
        </div>
    <?php endif; ?>

    <script>
        // Función para manejar la edición de la fecha de deducción
        function editarFechaDeduccion(id, tipo) {
            // Aquí puedes implementar la lógica para editar la fecha de deducción
            // Puedes usar un modal o un campo editable directamente en la tabla
            alert('Función para editar fecha de deducción para ' + tipo + ' ID: ' + id);
        }

        // Función para abrir el modal de asignación de fecha
        function abrirModalAsignacion(tipo, idReferencia, operarioId, sucursalId, monto, fechaEvento, comentarios) {
            document.getElementById('modalTipo').value = tipo;
            document.getElementById('modalIdReferencia').value = idReferencia;
            document.getElementById('modalOperarioId').value = operarioId;
            document.getElementById('modalSucursalId').value = sucursalId;
            document.getElementById('modalMonto').value = monto;
            document.getElementById('modalFechaEvento').value = fechaEvento;
            document.getElementById('modalComentarios').value = comentarios;

            // Establecer fecha mínima (hoy)
            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('fecha_deduccion').min = hoy;

            // Mostrar modal
            document.getElementById('modalDeduccion').style.display = 'flex';
        }

        // Función para cerrar el modal
        function cerrarModal() {
            document.getElementById('modalDeduccion').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera del contenido
        window.onclick = function (event) {
            const modal = document.getElementById('modalDeduccion');
            if (event.target == modal) {
                cerrarModal();
            }
        }
    </script>

    <script>
        // Datos de operarios para el autocompletado
        const operariosData = [
            { id: 0, nombre: 'Todos los colaboradores' },
            <?php foreach ($operarios as $op): ?>
                    { id: <?php echo $op['id_display']; ?>, nombre: '<?php echo addslashes($op['nombre_completo']); ?>' },
            <?php endforeach; ?>
        ];

        // Función para buscar operarios
        function buscarOperarios(texto) {
            if (!texto || texto === 'Todos los colaboradores') {
                return [];
            }
            return operariosData.filter(op =>
                op.nombre.toLowerCase().includes(texto.toLowerCase()) && op.id !== 0
            );
        }

        // Manejar el input de operario
        const operarioInput = document.getElementById('operario');
        const operarioIdInput = document.getElementById('operario_id');
        const sugerenciasDiv = document.getElementById('operarios-sugerencias');

        // Mostrar sugerencias al enfocar el campo si tiene texto
        operarioInput.addEventListener('focus', function () {
            const texto = this.value.trim();
            if (texto && texto !== 'Todos los colaboradores') {
                mostrarSugerencias(texto);
            }
        });

        // Modificar el evento input del campo operario
        operarioInput.addEventListener('input', function () {
            const texto = this.value.trim();
            mostrarSugerencias(texto);
        });

        // Ocultar sugerencias al hacer clic fuera
        document.addEventListener('click', function (e) {
            if (!operarioInput.contains(e.target) && !sugerenciasDiv.contains(e.target)) {
                sugerenciasDiv.style.display = 'none';
            }
        });

        function mostrarSugerencias(texto) {
            const resultados = buscarOperarios(texto);

            sugerenciasDiv.innerHTML = '';

            if (resultados.length > 0) {
                resultados.forEach(op => {
                    const div = document.createElement('div');
                    div.textContent = op.nombre;
                    div.style.padding = '8px 12px';
                    div.style.cursor = 'pointer';
                    div.style.borderBottom = '1px solid #f0f0f0';
                    div.style.fontSize = '14px';

                    div.addEventListener('click', function () {
                        operarioInput.value = op.nombre;
                        operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                    });

                    div.addEventListener('mouseover', function () {
                        this.style.backgroundColor = '#51B8AC';
                        this.style.color = 'white';
                    });

                    div.addEventListener('mouseout', function () {
                        this.style.backgroundColor = 'white';
                        this.style.color = 'black';
                    });

                    sugerenciasDiv.appendChild(div);
                });
                sugerenciasDiv.style.display = 'block';
            } else {
                sugerenciasDiv.style.display = 'none';
                // Si no hay texto, resetear a "todos"
                if (!texto) {
                    operarioIdInput.value = '0';
                }
            }
        }

        // Manejar teclas en el input
        operarioInput.addEventListener('keydown', function (e) {
            const sugerenciasVisibles = sugerenciasDiv.style.display === 'block';
            const itemsSugerencias = sugerenciasDiv.querySelectorAll('div');

            if (e.key === 'ArrowDown' && sugerenciasVisibles && itemsSugerencias.length > 0) {
                e.preventDefault();
                itemsSugerencias[0].focus();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (sugerenciasVisibles && itemsSugerencias.length > 0) {
                    // Seleccionar la primera sugerencia
                    const primeraSugerencia = itemsSugerencias[0];
                    operarioInput.value = primeraSugerencia.textContent;
                    // Buscar el ID correspondiente
                    const op = operariosData.find(item => item.nombre === primeraSugerencia.textContent);
                    if (op) {
                        operarioIdInput.value = op.id;
                    }
                }
                sugerenciasDiv.style.display = 'none';
            } else if (e.key === 'Escape') {
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Permitir navegación con teclado en las sugerencias
        sugerenciasDiv.addEventListener('keydown', function (e) {
            const items = this.querySelectorAll('div');
            const itemActivo = document.activeElement;
            let index = Array.from(items).indexOf(itemActivo);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                index = (index + 1) % items.length;
                items[index].focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                index = (index - 1 + items.length) % items.length;
                items[index].focus();
            } else if (e.key === 'Enter' && itemActivo) {
                e.preventDefault();
                itemActivo.click();
            }
        });

        $(document).ready(function () {
            $('#listaDeducciones').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                dom: '<"top"l>rt<"bottom"ip>', // Quitamos la "f" en "top"lf (filter/search)
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                pageLength: 25,

                // CONFIGURACIÓN PARA 3 CLICKS
                order: [], // Sin orden inicial - respeta el orden de la consulta SQL
                ordering: true, // Habilitar ordenamiento
                orderMulti: true, // Permitir ordenamiento múltiple con Ctrl+click

                // Configuración específica para el ciclo de 3 clicks
                columnDefs: [{
                    orderable: true, // Todas las columnas son ordenables
                    targets: '_all' // Aplicar a todas las columnas
                }]
            });
        });
    </script>
</body>

</html>