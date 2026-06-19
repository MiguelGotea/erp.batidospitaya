<?php
/**
 * colaboradores_exportar_excel.php
 * Exporta el listado de colaboradores a .xls respetando los filtros activos.
 * Requiere permiso 'exportar' en 'gestion_colaboradores'.
 */
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

// Verificar permiso de exportar
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
if (!tienePermiso('gestion_colaboradores', 'exportar', $cargoOperario)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso para exportar']);
    exit;
}

// Recibir parámetros (mismo esquema que colaboradores_get_datos.php)
$filtros = isset($_POST['filtros']) ? json_decode($_POST['filtros'], true) : [];
$orden   = isset($_POST['orden'])   ? json_decode($_POST['orden'], true)   : ['columna' => null, 'direccion' => 'asc'];

// ──────────────────────────────────────────────────────────────────────────────
// CONSTRUIR WHERE (idéntico a colaboradores_get_datos.php, sin paginación)
// ──────────────────────────────────────────────────────────────────────────────
$where  = ["o.CodOperario IS NOT NULL"];
$params = [];

// Excluir cargo 27 (global)
$subqueryCargoIdFiltroGlobal = "
    COALESCE(
        (SELECT anc_ext.CodNivelesCargos
         FROM AsignacionNivelesCargos anc_ext
         WHERE anc_ext.CodOperario = o.CodOperario
         AND anc_ext.CodNivelesCargos != 2
         AND (anc_ext.Fin IS NULL OR anc_ext.Fin >= CURDATE())
         ORDER BY anc_ext.CodAsignacionNivelesCargos DESC
         LIMIT 1),
        (SELECT anc_ext.CodNivelesCargos
         FROM AsignacionNivelesCargos anc_ext
         WHERE anc_ext.CodOperario = o.CodOperario
         AND (anc_ext.Fin IS NULL OR anc_ext.Fin >= CURDATE())
         ORDER BY anc_ext.CodAsignacionNivelesCargos DESC
         LIMIT 1),
        0
    )
";
$where[] = "$subqueryCargoIdFiltroGlobal != 27";

// Filtros de texto
if (!empty($filtros['CodOperario']))    { $where[] = "o.CodOperario LIKE :cod_operario"; $params[':cod_operario'] = '%'.$filtros['CodOperario'].'%'; }
if (!empty($filtros['nombre_completo'])) {
    $where[] = "CONCAT(TRIM(o.Nombre),' ',IFNULL(TRIM(o.Nombre2),''),' ',TRIM(o.Apellido),' ',IFNULL(TRIM(o.Apellido2),'')) LIKE :nombre_completo";
    $params[':nombre_completo'] = '%'.$filtros['nombre_completo'].'%';
}
if (!empty($filtros['Cedula']))      { $where[] = "o.Cedula LIKE :cedula";           $params[':cedula']      = '%'.$filtros['Cedula'].'%'; }
if (!empty($filtros['codigo_inss'])) { $where[] = "o.codigo_inss LIKE :codigo_inss"; $params[':codigo_inss'] = '%'.$filtros['codigo_inss'].'%'; }
if (!empty($filtros['telefonos'])) {
    $where[] = "(o.Celular LIKE :telefonos OR o.telefono_corporativo LIKE :telefonos2)";
    $params[':telefonos']  = '%'.$filtros['telefonos'].'%';
    $params[':telefonos2'] = '%'.$filtros['telefonos'].'%';
}

// Filtro de cargo (lista)
if (!empty($filtros['cargo_nombre']) && is_array($filtros['cargo_nombre'])) {
    $placeholders = [];
    foreach ($filtros['cargo_nombre'] as $idx => $valor) {
        $key = ":cargo_$idx"; $placeholders[] = $key; $params[$key] = $valor;
    }
    $subqueryCargo = "COALESCE(
        (SELECT nc.Nombre FROM AsignacionNivelesCargos anc JOIN NivelesCargos nc ON anc.CodNivelesCargos=nc.CodNivelesCargos WHERE anc.CodOperario=o.CodOperario AND anc.CodNivelesCargos!=2 AND (anc.Fin IS NULL OR anc.Fin>=CURDATE()) ORDER BY anc.CodNivelesCargos DESC LIMIT 1),
        (SELECT nc.Nombre FROM AsignacionNivelesCargos anc JOIN NivelesCargos nc ON anc.CodNivelesCargos=nc.CodNivelesCargos WHERE anc.CodOperario=o.CodOperario AND (anc.Fin IS NULL OR anc.Fin>=CURDATE()) ORDER BY anc.CodNivelesCargos DESC LIMIT 1),
        'Sin cargo definido'
    )";
    $where[] = "$subqueryCargo IN (".implode(',', $placeholders).")";
}

// Filtro de estado
if (!empty($filtros['Operativo']) && is_array($filtros['Operativo'])) {
    $statusConds = [];
    foreach ($filtros['Operativo'] as $valor) {
        if ($valor == '1') $statusConds[] = "(uc.fecha_salida IS NULL OR uc.fecha_salida > CURDATE())";
        if ($valor == '0') $statusConds[] = "(uc.fecha_salida IS NOT NULL AND uc.fecha_salida <= CURDATE())";
    }
    if ($statusConds) $where[] = "(".implode(' OR ', $statusConds).")";
}

// Filtros de sucursal (lista)
if (!empty($filtros['nombre_sucursal']) && is_array($filtros['nombre_sucursal'])) {
    $ph = [];
    foreach ($filtros['nombre_sucursal'] as $idx => $v) { $k=":suc_$idx"; $ph[]=$k; $params[$k]=$v; }
    $where[] = "COALESCE(s.nombre,'Sin tienda') IN (".implode(',',$ph).")";
}
if (!empty($filtros['sucursal_actual_nombre']) && is_array($filtros['sucursal_actual_nombre'])) {
    $ph = [];
    foreach ($filtros['sucursal_actual_nombre'] as $idx => $v) { $k=":suc_act_$idx"; $ph[]=$k; $params[$k]=$v; }
    $subSucAct = "(SELECT s2.nombre FROM AsignacionNivelesCargos anc2 JOIN sucursales s2 ON anc2.Sucursal=s2.codigo WHERE anc2.CodOperario=o.CodOperario AND (anc2.Fin IS NULL OR anc2.Fin>=CURDATE()) ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC LIMIT 1)";
    $where[] = "COALESCE($subSucAct,'Sin tienda') IN (".implode(',',$ph).")";
}

// Filtros de fechas (rango)
if (!empty($filtros['fecha_inicio_ultimo_contrato']['desde'])) { $where[] = "uc.inicio_contrato >= :fi_desde"; $params[':fi_desde'] = $filtros['fecha_inicio_ultimo_contrato']['desde']; }
if (!empty($filtros['fecha_inicio_ultimo_contrato']['hasta'])) { $where[] = "uc.inicio_contrato <= :fi_hasta"; $params[':fi_hasta'] = $filtros['fecha_inicio_ultimo_contrato']['hasta']; }
if (!empty($filtros['fecha_salida_ultimo']['desde']))          { $where[] = "uc.fecha_salida >= :fs_desde";    $params[':fs_desde'] = $filtros['fecha_salida_ultimo']['desde']; }
if (!empty($filtros['fecha_salida_ultimo']['hasta']))          { $where[] = "uc.fecha_salida <= :fs_hasta";    $params[':fs_hasta'] = $filtros['fecha_salida_ultimo']['hasta']; }
if (!empty($filtros['ultima_fecha_laborada']['desde'])) { $where[] = "m.fecha >= :ul_desde"; $params[':ul_desde'] = $filtros['ultima_fecha_laborada']['desde']; }
if (!empty($filtros['ultima_fecha_laborada']['hasta'])) { $where[] = "m.fecha <= :ul_hasta"; $params[':ul_hasta'] = $filtros['ultima_fecha_laborada']['hasta']; }

// Filtro de tiempo trabajado
if (!empty($filtros['tiempo_trabajado_dias']) && is_array($filtros['tiempo_trabajado_dias'])) {
    $conds = [];
    $exprDias = "DATEDIFF(COALESCE(uc.fecha_salida, IF(uc.fin_contrato IS NOT NULL AND uc.fin_contrato < CURDATE(), uc.fin_contrato, CURDATE())), uc.inicio_contrato)";
    foreach ($filtros['tiempo_trabajado_dias'] as $rango) {
        switch ($rango) {
            case 'menos_6_meses': $conds[] = "$exprDias < 180"; break;
            case '6_meses_1_año': $conds[] = "($exprDias >= 180 AND $exprDias < 365)"; break;
            case '1_2_años':      $conds[] = "($exprDias >= 365 AND $exprDias < 730)"; break;
            case '2_5_años':      $conds[] = "($exprDias >= 730 AND $exprDias < 1825)"; break;
            case 'mas_5_años':    $conds[] = "$exprDias >= 1825"; break;
        }
    }
    if ($conds) $where[] = "(".implode(' OR ', $conds).")";
}

// Filtro de cantidad de hijos
if (!empty($filtros['cantidad_hijos']) && is_array($filtros['cantidad_hijos'])) {
    $ph = [];
    foreach ($filtros['cantidad_hijos'] as $idx => $v) { $k=":cant_h_$idx"; $ph[]=$k; $params[$k]=$v; }
    $where[] = "COALESCE(o.cantidad_hijos, 0) IN (".implode(',',$ph).")";
}

// Filtro de talla camisa
if (!empty($filtros['talla_camisa']) && is_array($filtros['talla_camisa'])) {
    $ph = [];
    foreach ($filtros['talla_camisa'] as $idx => $v) { $k=":talla_c_$idx"; $ph[]=$k; $params[$k]=$v; }
    $where[] = "COALESCE(o.talla_camisa,'Sin talla') IN (".implode(',',$ph).")";
}

// Filtro de fecha vencimiento certificado salud (rango)
if (!empty($filtros['fecha_vencimiento_salud']['desde'])) {
    $where[] = "(SELECT MAX(fecha_vencimiento) FROM ArchivosAdjuntos WHERE cod_operario = o.CodOperario AND id_tipo_documento = 2) >= :venc_salud_desde";
    $params[':venc_salud_desde'] = $filtros['fecha_vencimiento_salud']['desde'];
}
if (!empty($filtros['fecha_vencimiento_salud']['hasta'])) {
    $where[] = "(SELECT MAX(fecha_vencimiento) FROM ArchivosAdjuntos WHERE cod_operario = o.CodOperario AND id_tipo_documento = 2) <= :venc_salud_hasta";
    $params[':venc_salud_hasta'] = $filtros['fecha_vencimiento_salud']['hasta'];
}

// Filtro de mes de contrato (lista)
if (!empty($filtros['mes_contrato']) && is_array($filtros['mes_contrato'])) {
    $ph = [];
    foreach ($filtros['mes_contrato'] as $idx => $v) {
        $k = ":mes_contrato_$idx";
        $ph[] = $k;
        $params[$k] = $v;
    }
    $where[] = "MONTH(uc.inicio_contrato) IN (" . implode(',', $ph) . ")";
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// ──────────────────────────────────────────────────────────────────────────────
// ORDER BY
// ──────────────────────────────────────────────────────────────────────────────
$orderClause = "ORDER BY o.Nombre, o.Apellido";
if (!empty($orden['columna'])) {
    $dir = strtoupper($orden['direccion']) === 'DESC' ? 'DESC' : 'ASC';
    switch ($orden['columna']) {
        case 'CodOperario':   $orderClause = "ORDER BY o.CodOperario $dir"; break;
        case 'nombre_completo': $orderClause = "ORDER BY nombre_completo $dir"; break;
        case 'Cedula':        $orderClause = "ORDER BY o.Cedula $dir"; break;
        case 'codigo_inss':   $orderClause = "ORDER BY o.codigo_inss $dir"; break;
        case 'cantidad_hijos':$orderClause = "ORDER BY o.cantidad_hijos $dir"; break;
        case 'talla_camisa':  $orderClause = "ORDER BY o.talla_camisa $dir"; break;
        case 'fecha_inicio_ultimo_contrato': $orderClause = "ORDER BY uc.inicio_contrato $dir"; break;
        case 'fecha_salida_ultimo':          $orderClause = "ORDER BY (CASE WHEN uc.fecha_salida IS NULL OR uc.fecha_salida = '0000-00-00' THEN 1 ELSE 0 END) ASC, uc.fecha_salida $dir"; break;
        case 'ultima_fecha_laborada':        $orderClause = "ORDER BY m.fecha $dir"; break;
        case 'tiempo_trabajado_dias':        $orderClause = "ORDER BY tiempo_trabajado_dias $dir"; break;
        case 'Operativo':     $orderClause = "ORDER BY (CASE WHEN uc.fecha_salida IS NULL OR uc.fecha_salida > CURDATE() THEN 1 ELSE 0 END) $dir"; break;
        case 'nombre_sucursal': $orderClause = "ORDER BY s.nombre $dir"; break;
        case 'cargo_nombre':
            $subqueryCargo = "
                COALESCE(
                    (SELECT nc.Nombre 
                     FROM AsignacionNivelesCargos anc
                     JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                     WHERE anc.CodOperario = o.CodOperario 
                     AND anc.CodNivelesCargos != 2
                     AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                     ORDER BY anc.CodNivelesCargos DESC
                     LIMIT 1),
                    (SELECT nc.Nombre 
                     FROM AsignacionNivelesCargos anc
                     JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                     WHERE anc.CodOperario = o.CodOperario 
                     AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                     ORDER BY anc.CodNivelesCargos DESC
                     LIMIT 1),
                    'Sin cargo definido'
                )
            ";
            $orderClause = "ORDER BY $subqueryCargo $dir";
            break;
        case 'sucursal_actual_nombre':
            $subquerySucursalActual = "
                (SELECT s2.nombre 
                 FROM AsignacionNivelesCargos anc2
                 JOIN sucursales s2 ON anc2.Sucursal = s2.codigo
                 WHERE anc2.CodOperario = o.CodOperario 
                 AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
                 ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC
                 LIMIT 1)
            ";
            $orderClause = "ORDER BY $subquerySucursalActual $dir";
            break;
        case 'mes_contrato':
            $orderClause = "ORDER BY MONTH(uc.inicio_contrato) $dir";
            break;
        case 'fecha_vencimiento_salud':
            $orderClause = "ORDER BY (SELECT MAX(fecha_vencimiento) FROM ArchivosAdjuntos WHERE cod_operario = o.CodOperario AND id_tipo_documento = 2) $dir";
            break;
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// CONSULTA PRINCIPAL (sin LIMIT)
// ──────────────────────────────────────────────────────────────────────────────
$sql = "
    SELECT
        o.CodOperario,
        CONCAT_WS(' ',
            TRIM(o.Nombre),
            NULLIF(TRIM(o.Nombre2), ''),
            TRIM(o.Apellido),
            NULLIF(TRIM(o.Apellido2), '')
        ) as nombre_completo,
        o.Cedula,
        o.codigo_inss,
        o.cantidad_hijos,
        o.talla_camisa,
        o.Celular,
        o.telefono_corporativo,
        IF(uc.fecha_salida IS NULL OR uc.fecha_salida > CURDATE(), 'Activo', 'Inactivo') as Estado,
        COALESCE(
            (SELECT nc.Nombre FROM AsignacionNivelesCargos anc JOIN NivelesCargos nc ON anc.CodNivelesCargos=nc.CodNivelesCargos WHERE anc.CodOperario=o.CodOperario AND anc.CodNivelesCargos!=2 AND (anc.Fin IS NULL OR anc.Fin>=CURDATE()) ORDER BY anc.CodNivelesCargos DESC LIMIT 1),
            (SELECT nc.Nombre FROM AsignacionNivelesCargos anc JOIN NivelesCargos nc ON anc.CodNivelesCargos=nc.CodNivelesCargos WHERE anc.CodOperario=o.CodOperario AND (anc.Fin IS NULL OR anc.Fin>=CURDATE()) ORDER BY anc.CodNivelesCargos DESC LIMIT 1),
            'Sin cargo definido'
        ) as cargo_nombre,
        COALESCE(s.nombre, 'Sin tienda') as nombre_sucursal,
        COALESCE(
            (SELECT s2.nombre FROM AsignacionNivelesCargos anc2 JOIN sucursales s2 ON anc2.Sucursal=s2.codigo WHERE anc2.CodOperario=o.CodOperario AND (anc2.Fin IS NULL OR anc2.Fin>=CURDATE()) ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC LIMIT 1),
            (SELECT CONCAT(s2.nombre, ' (última tienda)') FROM AsignacionNivelesCargos anc2 JOIN sucursales s2 ON anc2.Sucursal=s2.codigo WHERE anc2.CodOperario=o.CodOperario ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC LIMIT 1),
            'Sin tienda'
        ) as sucursal_actual_nombre,
        uc.inicio_contrato   as fecha_inicio_contrato,
        uc.fecha_salida      as fecha_salida,
        m.fecha              as ultima_fecha_marcacion,
        DATEDIFF(
            COALESCE(uc.fecha_salida, IF(uc.fin_contrato IS NOT NULL AND uc.fin_contrato < CURDATE(), uc.fin_contrato, CURDATE())),
            uc.inicio_contrato
        ) as tiempo_trabajado_dias,
        MONTH(uc.inicio_contrato) as mes_contrato,
        (SELECT MAX(fecha_vencimiento) FROM ArchivosAdjuntos WHERE cod_operario = o.CodOperario AND id_tipo_documento = 2) as fecha_vencimiento_salud
    FROM Operarios o
    LEFT JOIN Contratos uc ON uc.cod_operario = o.CodOperario
        AND uc.CodContrato = (SELECT MAX(CodContrato) FROM Contratos WHERE cod_operario = o.CodOperario)
    LEFT JOIN sucursales s ON uc.cod_sucursal_contrato = s.codigo
    LEFT JOIN marcaciones m ON m.CodOperario = o.CodOperario
        AND m.fecha = (SELECT MAX(fecha) FROM marcaciones WHERE CodOperario = o.CodOperario AND (hora_ingreso IS NOT NULL OR hora_salida IS NOT NULL) AND fecha <= CURDATE())
    $whereClause
    $orderClause
";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ──────────────────────────────────────────────────────────────────────────────
// CALCULAR TIEMPO TRABAJADO TEXTO (función local)
// ──────────────────────────────────────────────────────────────────────────────
function tiempoTrabajadoTexto($dias) {
    if ($dias === null || $dias < 0) return '-';
    $años  = intdiv($dias, 365);
    $resto = $dias % 365;
    $meses = intdiv($resto, 30);
    $partes = [];
    if ($años > 0)  $partes[] = $años  . ' año'  . ($años  > 1 ? 's' : '');
    if ($meses > 0) $partes[] = $meses . ' mes'  . ($meses > 1 ? 'es' : '');
    return $partes ? implode(', ', $partes) : '< 1 mes';
}

// ──────────────────────────────────────────────────────────────────────────────
// GENERAR EXCEL (.XLS via HTML table + UTF-8)
// ──────────────────────────────────────────────────────────────────────────────
$fecha     = date('Y-m-d');
$filename  = "colaboradores_{$fecha}.xls";

// Limpiar buffer de salida antes de enviar headers
while (ob_get_level()) ob_end_clean();

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// BOM UTF-8 para que Excel abra correctamente los acentos
echo "\xEF\xBB\xBF";

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Colaboradores</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
echo '<body>';
echo '<table border="1" cellpadding="4" cellspacing="0">';

// ── CABECERA ──
echo '<thead><tr style="background-color:#0E544C; color:#ffffff; font-weight:bold;">';
$headers = [
    'Código', 'Nombre Completo', 'Cédula', 'Seguro INSS',
    'Cargo', 'Teléfono Personal', 'Teléfono Corporativo',
    'Estado', 'Tienda Contrato', 'Tienda Actual',
    'Inicio Contrato', 'Mes Contrato', 'Último Día Marcado', 'Fecha de Salida', 'Tiempo Trabajado',
    'Cant. Hijos', 'Talla Camisa', 'Venc. Cert. Salud'
];
foreach ($headers as $h) {
    echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
}
echo '</tr></thead>';

// ── FILAS ──
echo '<tbody>';
foreach ($datos as $row) {
    $fechaInicio   = !empty($row['fecha_inicio_contrato']) && $row['fecha_inicio_contrato'] !== '0000-00-00'
        ? date('d/m/Y', strtotime($row['fecha_inicio_contrato'])) : '-';
    $ultimaMarca   = !empty($row['ultima_fecha_marcacion']) && $row['ultima_fecha_marcacion'] !== '0000-00-00'
        ? date('d/m/Y', strtotime($row['ultima_fecha_marcacion'])) : '-';
    $tiempoTexto   = tiempoTrabajadoTexto($row['tiempo_trabajado_dias']);

    $fechaSalida   = !empty($row['fecha_salida']) && $row['fecha_salida'] !== '0000-00-00'
        ? date('d/m/Y', strtotime($row['fecha_salida'])) : '-';

    $fechaVencSalud = !empty($row['fecha_vencimiento_salud']) && $row['fecha_vencimiento_salud'] !== '0000-00-00'
        ? date('d/m/Y', strtotime($row['fecha_vencimiento_salud'])) : '-';

    $cols = [
        $row['CodOperario'],
        $row['nombre_completo'],
        $row['Cedula']             ?? '-',
        $row['codigo_inss']        ?? '-',
        $row['cargo_nombre'],
        $row['Celular']            ?? '-',
        $row['telefono_corporativo'] ?? '-',
        $row['Estado'],
        $row['nombre_sucursal'],
        $row['sucursal_actual_nombre'],
        $fechaInicio,
        $row['mes_contrato']       ?? '-',
        $ultimaMarca,
        $fechaSalida,
        $tiempoTexto,
        $row['cantidad_hijos'] !== null ? $row['cantidad_hijos'] : '-',
        $row['talla_camisa']          ?? '-',
        $fechaVencSalud
    ];

    echo '<tr>';
    foreach ($cols as $col) {
        echo '<td>' . htmlspecialchars((string)$col, ENT_QUOTES, 'UTF-8') . '</td>';
    }
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';
echo '</body></html>';
exit;
