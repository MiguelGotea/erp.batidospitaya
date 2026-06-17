<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

// Verificar conexión
if (!$conn) {
    die("Error de conexión a la base de datos");
}

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso a la herramienta
if (!tienePermiso('feriados_v2', 'vista', $cargoOperario)) {
    header('Location: ../index.php');
    exit();
}

$puedeCrear = tienePermiso('feriados_v2', 'crear', $cargoOperario);
$puedeAprobar = tienePermiso('feriados_v2', 'aprobar', $cargoOperario);
$puedeExportar = tienePermiso('feriados_v2', 'exportar', $cargoOperario);

$puedeVerTodasSucursales = tienePermiso('feriados_v2', 'ver_todas_sucursales', $cargoOperario);

// Obtener sucursales según el permiso (patrón idéntico a vacaciones.php)
if ($puedeVerTodasSucursales) {
    $sucursales = obtenerTodasSucursales();
    array_unshift($sucursales, ['codigo' => 'todas', 'nombre' => 'Todas las sucursales']);
} else {
    $sucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
}

// Sucursal seleccionada
if (count($sucursales) === 1 && !isset($_GET['sucursal'])) {
    $sucursalSeleccionada = $sucursales[0]['codigo'];
} else {
    $sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);
}

// Rango de fechas por defecto (mes actual)
$hoy = new DateTime();
$primerDiaMes = $hoy->format('Y-m-01');
$ultimoDiaMes = $hoy->format('Y-m-t');

$fechaDesde = $_GET['desde'] ?? $primerDiaMes;
$fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;

if (empty($fechaDesde)) $fechaDesde = $primerDiaMes;
if (empty($fechaHasta)) $fechaHasta = $ultimoDiaMes;

$operarioSeleccionado = isset($_GET['operario']) ? intval($_GET['operario']) : 0;
$estadoSeleccionado = $_GET['estado'] ?? 'todos';

// Obtener operarios para el filtro
function obtenerTodosOperarios() {
    global $conn;
    $sql = "SELECT o.CodOperario, 
                   CONCAT_WS(' ', 
                       TRIM(o.Nombre), 
                       NULLIF(TRIM(o.Nombre2), ''), 
                       TRIM(o.Apellido), 
                       NULLIF(TRIM(o.Apellido2), '')
                   ) AS nombre_completo 
            FROM Operarios o
            LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            WHERE (anc.CodNivelesCargos IS NULL OR anc.CodNivelesCargos != 27)
            AND o.Operativo = 1
            GROUP BY o.CodOperario
            ORDER BY nombre_completo";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}
$operarios = obtenerTodosOperarios();

// Construcción de filtros para queries SQL
$where = " WHERE 1=1";
$params = [];

$where .= " AND fs.fecha_feriado BETWEEN ? AND ?";
$params[] = $fechaDesde;
$params[] = $fechaHasta;

if ($puedeVerTodasSucursales) {
    if ($sucursalSeleccionada && $sucursalSeleccionada !== 'todas') {
        $where .= " AND COALESCE(c.cod_sucursal_contrato, anc.Sucursal) = ?";
        $params[] = $sucursalSeleccionada;
    }
} else {
    $codigosSucursalesLider = array_map(function($suc) { return $suc['codigo']; }, $sucursales);
    if (!empty($codigosSucursalesLider)) {
        if ($sucursalSeleccionada && in_array($sucursalSeleccionada, $codigosSucursalesLider)) {
            $where .= " AND COALESCE(c.cod_sucursal_contrato, anc.Sucursal) = ?";
            $params[] = $sucursalSeleccionada;
        } else {
            $placeholders = implode(',', array_fill(0, count($codigosSucursalesLider), '?'));
            $where .= " AND COALESCE(c.cod_sucursal_contrato, anc.Sucursal) IN ($placeholders)";
            foreach ($codigosSucursalesLider as $cod) {
                $params[] = $cod;
            }
        }
    } else {
        $where .= " AND 1=0";
    }
}

if ($operarioSeleccionado > 0) {
    $where .= " AND fs.cod_operario = ?";
    $params[] = $operarioSeleccionado;
}

if ($estadoSeleccionado && $estadoSeleccionado !== 'todos') {
    $where .= " AND fs.estado = ?";
    $params[] = $estadoSeleccionado;
}

// EXCEL EXPORT LÓGICA
if (isset($_GET['exportar_excel'])) {
    if (!$puedeExportar) {
        die("No tiene permisos para exportar.");
    }
    
    // Obtener todos los registros sin paginación
    $sqlExport = "
        SELECT fs.id, fs.fecha_feriado, fs.horas_trabajadas, fs.estado, fs.observaciones, fs.fecha_creacion,
               o.CodOperario,
               c.CodContrato,
               CONCAT_WS(' ',
                   TRIM(o.Nombre),
                   NULLIF(TRIM(o.Nombre2), ''),
                   TRIM(o.Apellido),
                   NULLIF(TRIM(o.Apellido2), '')
               ) as nombre_completo,
               COALESCE(s.nombre, s_actual.nombre, 'Sin sucursal') as sucursal_nombre,
               CONCAT_WS(' ', TRIM(creado.Nombre), TRIM(creado.Apellido)) as creador_nombre,
               CONCAT_WS(' ', TRIM(act.Nombre), TRIM(act.Apellido)) as actualizador_nombre,
               fs.fecha_actualizacion
        FROM FeriadosStatus fs
        INNER JOIN Operarios o ON fs.cod_operario = o.CodOperario
        LEFT JOIN Contratos c ON fs.cod_contrato = c.CodContrato
        LEFT JOIN sucursales s ON c.cod_sucursal_contrato = s.codigo
        LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
            AND fs.fecha_feriado >= anc.Fecha 
            AND (anc.Fin IS NULL OR anc.Fin = '0000-00-00' OR fs.fecha_feriado <= anc.Fin)
        LEFT JOIN sucursales s_actual ON anc.Sucursal = s_actual.codigo
        LEFT JOIN Operarios creado ON fs.creado_por = creado.CodOperario
        LEFT JOIN Operarios act ON fs.actualizado_por = act.CodOperario
        $where
        GROUP BY fs.id
        ORDER BY fs.fecha_feriado DESC, nombre_completo ASC
    ";
    
    $stmtExport = $conn->prepare($sqlExport);
    $stmtExport->execute($params);
    $recordsExport = $stmtExport->fetchAll();

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="solicitudes_feriados_' . $fechaDesde . '_a_' . $fechaHasta . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>';
    echo '<table border="1">';
    echo '<tr style="background-color:#0E544C; color:white;">';
    echo '<th>CÓDIGO OPERARIO</th>';
    echo '<th>CÓDIGO CONTRATO</th>';
    echo '<th>COLABORADOR</th>';
    echo '<th>SUCURSAL</th>';
    echo '<th>FECHA FERIADO</th>';
    echo '<th>HORAS LABORADAS</th>';
    echo '<th>ESTADO</th>';
    echo '<th>OBSERVACIONES</th>';
    echo '<th>CREADO POR</th>';
    echo '<th>FECHA CREACIÓN</th>';
    echo '<th>ACTUALIZADO POR</th>';
    echo '<th>FECHA ACTUALIZACIÓN</th>';
    echo '</tr>';

    foreach ($recordsExport as $r) {
        $fechaF = date('d-m-Y', strtotime($r['fecha_feriado']));
        $fechaC = date('d-m-Y H:i', strtotime($r['fecha_creacion']));
        $fechaA = $r['fecha_actualizacion'] ? date('d-m-Y H:i', strtotime($r['fecha_actualizacion'])) : '-';
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($r['CodOperario']) . '</td>';
        echo '<td>' . htmlspecialchars($r['CodContrato'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($r['nombre_completo']) . '</td>';
        echo '<td>' . htmlspecialchars($r['sucursal_nombre']) . '</td>';
        echo '<td>' . $fechaF . '</td>';
        echo '<td>' . number_format($r['horas_trabajadas'] ?? 0, 2) . '</td>';
        echo '<td>' . htmlspecialchars($r['estado']) . '</td>';
        echo '<td>' . htmlspecialchars($r['observaciones'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($r['creador_nombre'] ?? '-') . '</td>';
        echo '<td>' . $fechaC . '</td>';
        echo '<td>' . htmlspecialchars($r['actualizador_nombre'] ?? '-') . '</td>';
        echo '<td>' . $fechaA . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

// PAGINACIÓN LÓGICA
$paginaActual = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$limitesValidos = [25, 50, 100];
$registrosPorPagina = in_array((int)($_GET['limit'] ?? 25), $limitesValidos) ? (int)($_GET['limit'] ?? 25) : 25;
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Conteo total
$sqlCount = "
    SELECT COUNT(DISTINCT fs.id) as total
    FROM FeriadosStatus fs
    INNER JOIN Operarios o ON fs.cod_operario = o.CodOperario
    LEFT JOIN Contratos c ON fs.cod_contrato = c.CodContrato
    LEFT JOIN sucursales s ON c.cod_sucursal_contrato = s.codigo
    LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
        AND fs.fecha_feriado >= anc.Fecha 
        AND (anc.Fin IS NULL OR anc.Fin = '0000-00-00' OR fs.fecha_feriado <= anc.Fin)
    LEFT JOIN sucursales s_actual ON anc.Sucursal = s_actual.codigo
    $where
";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute($params);
$totalRegistros = $stmtCount->fetchColumn();
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Obtener registros paginados
$sqlList = "
    SELECT fs.id, fs.fecha_feriado, fs.horas_trabajadas, fs.estado, fs.observaciones, fs.fecha_creacion,
           o.CodOperario,
           c.CodContrato,
           CONCAT_WS(' ',
               TRIM(o.Nombre),
               NULLIF(TRIM(o.Nombre2), ''),
               TRIM(o.Apellido),
               NULLIF(TRIM(o.Apellido2), '')
           ) as nombre_completo,
           COALESCE(s.nombre, s_actual.nombre, 'Sin sucursal') as sucursal_nombre,
           COALESCE(s.codigo, s_actual.codigo) as sucursal_codigo,
           CONCAT_WS(' ', TRIM(creado.Nombre), TRIM(creado.Apellido)) as creador_nombre,
           fs.creado_por
    FROM FeriadosStatus fs
    INNER JOIN Operarios o ON fs.cod_operario = o.CodOperario
    LEFT JOIN Contratos c ON fs.cod_contrato = c.CodContrato
    LEFT JOIN sucursales s ON c.cod_sucursal_contrato = s.codigo
    LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario 
        AND fs.fecha_feriado >= anc.Fecha 
        AND (anc.Fin IS NULL OR anc.Fin = '0000-00-00' OR fs.fecha_feriado <= anc.Fin)
    LEFT JOIN sucursales s_actual ON anc.Sucursal = s_actual.codigo
    LEFT JOIN Operarios creado ON fs.creado_por = creado.CodOperario
    $where
    GROUP BY fs.id
    ORDER BY fs.fecha_feriado DESC, nombre_completo ASC
    LIMIT $registrosPorPagina OFFSET $offset
";
$stmtList = $conn->prepare($sqlList);
$stmtList->execute($params);
$records = $stmtList->fetchAll();

// Formato de fechas amigable
function formatoFechaLocal($fecha) {
    if (empty($fecha) || $fecha === '0000-00-00') return '-';
    try {
        $d = new DateTime($fecha);
        return $d->format('d-m-Y');
    } catch (Exception $e) {
        return '-';
    }
}

function getEstadoBadgeClass($estado) {
    if ($estado === 'Pendiente') return 'badge-status badge-pendiente';
    if ($estado === 'Pagado') return 'badge-status badge-pagado';
    if ($estado === 'Descansado') return 'badge-status badge-descansado';
    return 'badge-status';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Feriados Trabajados V2</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/feriados_v2.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Solicitudes de Feriados Trabajados'); ?>

            <div class="container-fluid p-3">
                
                <!-- Buscador / Filtros -->
                <div class="filtros-container">
                    <div class="filtros-form">
                        <div class="filtro-group">
                            <label for="sucursal">Sucursal</label>
                            <select id="sucursal" name="sucursal" onchange="actualizarFiltros()">
                                <?php foreach ($sucursales as $suc): ?>
                                    <option value="<?= $suc['codigo'] ?>" <?= $sucursalSeleccionada == $suc['codigo'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($suc['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filtro-group">
                            <label for="operario">Colaborador</label>
                            <input type="text" id="operario" placeholder="Buscar colaborador..." autocomplete="off" value="<?php
                                if ($operarioSeleccionado > 0) {
                                    foreach ($operarios as $op) {
                                        if ($op['CodOperario'] == $operarioSeleccionado) {
                                            echo htmlspecialchars($op['nombre_completo']);
                                            break;
                                        }
                                    }
                                }
                            ?>">
                            <input type="hidden" id="operario_id" name="operario" value="<?= $operarioSeleccionado ?>">
                            <div id="operarios-sugerencias" class="sugerencias-lista" style="display: none;"></div>
                        </div>

                        <div class="filtro-group">
                            <label for="desde">Desde</label>
                            <input type="date" id="desde" name="desde" value="<?= htmlspecialchars($fechaDesde) ?>" onchange="actualizarFiltros()">
                        </div>

                        <div class="filtro-group">
                            <label for="hasta">Hasta</label>
                            <input type="date" id="hasta" name="hasta" value="<?= htmlspecialchars($fechaHasta) ?>" onchange="actualizarFiltros()">
                        </div>

                        <div class="filtro-group">
                            <label for="estado_filtro">Estado</label>
                            <select id="estado_filtro" name="estado" onchange="actualizarFiltros()">
                                <option value="todos" <?= $estadoSeleccionado === 'todos' ? 'selected' : '' ?>>Todos</option>
                                <option value="Pendiente" <?= $estadoSeleccionado === 'Pendiente' ? 'selected' : '' ?>>Pendientes</option>
                                <option value="Pagado" <?= $estadoSeleccionado === 'Pagado' ? 'selected' : '' ?>>Pagados</option>
                                <option value="Descansado" <?= $estadoSeleccionado === 'Descansado' ? 'selected' : '' ?>>Compensados (Descanso)</option>
                            </select>
                        </div>

                        <div class="filtro-buttons">
                            <button type="button" class="btn-aplicar" onclick="actualizarFiltros()">
                                <i class="fas fa-search"></i> Buscar
                            </button>

                            <?php if ($puedeExportar): ?>
                                <a href="feriados_v2.php?<?= http_build_query([
                                    'sucursal' => $sucursalSeleccionada ?? '',
                                    'desde' => $fechaDesde,
                                    'hasta' => $fechaHasta,
                                    'operario' => $operarioSeleccionado,
                                    'estado' => $estadoSeleccionado,
                                    'exportar_excel' => 1
                                ]) ?>" class="btn-aplicar" style="background-color: #28a745; color: white;">
                                    <i class="fas fa-file-excel"></i> Exportar
                                </a>
                            <?php endif; ?>


                        </div>
                    </div>
                </div>

                <!-- Tabla de Resultados -->
                <?php
                // Determinar si se debe mostrar la columna de Acciones
                $mostrarColumnaAcciones = false;
                if ($puedeAprobar) {
                    $mostrarColumnaAcciones = true;
                } elseif ($puedeCrear) {
                    foreach ($records as $r) {
                        if ($r['estado'] === 'Pendiente' && $r['creado_por'] == $_SESSION['usuario_id']) {
                            $mostrarColumnaAcciones = true;
                            break;
                        }
                    }
                }
                ?>
                <?php
                // Calcular total de columnas visibles para el colspan del mensaje vacío
                // Columnas fijas: Colaborador, Sucursal, Fecha Feriado, Estado, Observaciones, Registrado por = 6
                $totalColumnas = 6;
                if ($mostrarColumnaAcciones) $totalColumnas += 1; // Acciones
                ?>
                <div class="table-container">
                    <?php if (!empty($records)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Sucursal</th>
                                    <th>Fecha Feriado</th>
                                    <th>Estado</th>
                                    <th>Observaciones</th>
                                    <th>Registrado por</th>
                                    <?php if ($mostrarColumnaAcciones): ?>
                                        <th style="text-align: center;">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $r): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($r['nombre_completo']) ?></strong></td>
                                        <td><?= htmlspecialchars($r['sucursal_nombre']) ?></td>
                                        <td><?= formatoFechaLocal($r['fecha_feriado']) ?></td>
                                        <td>
                                            <span class="<?= getEstadoBadgeClass($r['estado']) ?>">
                                                <?= $r['estado'] === 'Descansado' ? 'COMPENSADO' : htmlspecialchars($r['estado']) ?>
                                            </span>
                                        </td>
                                        <td title="<?= htmlspecialchars($r['observaciones'] ?? '') ?>">
                                            <?= $r['observaciones'] ? htmlspecialchars(substr($r['observaciones'], 0, 40)) . (strlen($r['observaciones']) > 40 ? '...' : '') : '-' ?>
                                        </td>
                                        <td><?= htmlspecialchars($r['creador_nombre'] ?? 'Sistema') ?></td>

                                        <?php if ($mostrarColumnaAcciones): ?>
                                            <td style="text-align: center;">
                                                <div class="action-buttons-cell">
                                                    <?php if ($puedeAprobar): ?>
                                                        <button type="button" class="btn-action-table btn-action-edit" title="Gestionar solicitud"
                                                                onclick="mostrarModalAprobacion(
                                                                    <?= $r['id'] ?>,
                                                                    '<?= htmlspecialchars(addslashes($r['nombre_completo'])) ?>',
                                                                    '<?= htmlspecialchars(addslashes($r['sucursal_nombre'])) ?>',
                                                                    '<?= $r['fecha_feriado'] ?>',
                                                                    '<?= $r['horas_trabajadas'] ?>',
                                                                    '<?= $r['estado'] ?>',
                                                                    '<?= htmlspecialchars(addslashes($r['observaciones'] ?? '')) ?>'
                                                                )">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <table>
                            <thead><tr><?php for ($ci = 0; $ci < $totalColumnas; $ci++): ?><th></th><?php endfor; ?></tr></thead>
                            <tbody>
                                <tr>
                                    <td colspan="<?= $totalColumnas ?>" class="text-center text-muted p-4">
                                        No se encontraron solicitudes registradas para los filtros seleccionados en este rango de fechas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <!-- Paginación -->
                    <div class="d-flex justify-content-between align-items-center mt-3 px-3 pb-3">
                        <div class="d-flex align-items-center gap-2">
                            <label class="mb-0 small text-muted">Mostrar:</label>
                            <select class="form-select form-select-sm" id="registrosPorPagina" style="width: auto;"
                                onchange="cambiarRegistrosPorPagina()">
                                <option value="25" <?= $registrosPorPagina == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $registrosPorPagina == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $registrosPorPagina == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                            <span class="small text-muted">registros</span>
                            <?php if ($totalRegistros > 0): ?>
                                <span class="small text-muted ms-2">
                                    (<?= $offset + 1 ?>–<?= min($offset + $registrosPorPagina, $totalRegistros) ?> de <?= $totalRegistros ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($totalPaginas > 1): ?>
                        <div id="paginacion" class="d-flex gap-1">
                            <button class="pagination-btn" <?= $paginaActual <= 1 ? 'disabled' : '' ?>
                                onclick="window.location.href='feriados_v2.php?<?= http_build_query(array_merge($_GET, ['p' => $paginaActual - 1])) ?>'">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <?php
                            $inicio = max(1, $paginaActual - 2);
                            $fin    = min($totalPaginas, $paginaActual + 2);
                            if ($inicio > 1):
                            ?>
                                <button class="pagination-btn" onclick="window.location.href='feriados_v2.php?<?= http_build_query(array_merge($_GET, ['p' => 1])) ?>'">1</button>
                                <?php if ($inicio > 2): ?>
                                    <span class="pagination-btn" style="cursor:default;">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                                <button class="pagination-btn <?= $paginaActual == $i ? 'active' : '' ?>"
                                    onclick="window.location.href='feriados_v2.php?<?= http_build_query(array_merge($_GET, ['p' => $i])) ?>'"><?= $i ?></button>
                            <?php endfor; ?>
                            <?php if ($fin < $totalPaginas):
                                if ($fin < $totalPaginas - 1): ?>
                                    <span class="pagination-btn" style="cursor:default;">...</span>
                                <?php endif; ?>
                                <button class="pagination-btn" onclick="window.location.href='feriados_v2.php?<?= http_build_query(array_merge($_GET, ['p' => $totalPaginas])) ?>'"><?= $totalPaginas ?></button>
                            <?php endif; ?>
                            <button class="pagination-btn" <?= $paginaActual >= $totalPaginas ? 'disabled' : '' ?>
                                onclick="window.location.href='feriados_v2.php?<?= http_build_query(array_merge($_GET, ['p' => $paginaActual + 1])) ?>'">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL 1: REGISTRAR SOLICITUD (Líder / RH) -->
    <?php if ($puedeCrear): ?>
        <div class="modal fade" id="modalSolicitud" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow" style="border-radius: 8px;">
                    <div class="modal-header border-0 py-3 px-3" style="background: #0E544C; color: #fff;">
                        <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                                style="width: 40px; height: 40px;">
                                <i class="fas fa-plus-circle fs-4"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0 text-white">Solicitud de Pago de Feriado</h5>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3 bg-light">
                        <form id="formNuevaSolicitud">
                            <div class="mb-3">
                                <label for="solicitud_sucursal"
                                    class="form-label small fw-bold text-muted text-uppercase">Tienda:</label>
                                <select id="solicitud_sucursal" name="cod_sucursal" class="form-select" required>
                                    <?php if ($puedeVerTodasSucursales): ?>
                                        <option value="">Seleccione una tienda</option>
                                        <?php foreach (obtenerTodasSucursales() as $suc): ?>
                                            <option value="<?= $suc['codigo'] ?>"><?= htmlspecialchars($suc['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <?php foreach ($sucursales as $suc): ?>
                                            <option value="<?= $suc['codigo'] ?>"><?= htmlspecialchars($suc['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="solicitud_fecha"
                                    class="form-label small fw-bold text-muted text-uppercase">Feriado Trabajado:</label>
                                <select id="solicitud_fecha" name="fecha_feriado" class="form-select" required>
                                    <option value="">⏳ Seleccione primero una sucursal...</option>
                                </select>
                                <small class="text-muted" style="font-size:0.78rem; margin-top:4px; display:block;">Solo se muestran feriados registrados en el sistema aplicables al departamento de la sucursal seleccionada.</small>
                            </div>

                            <div class="mb-3">
                                <label for="solicitud_operario"
                                    class="form-label small fw-bold text-muted text-uppercase">Colaborador:</label>
                                <select id="solicitud_operario" name="cod_operario" class="form-select" required>
                                    <option value="">Seleccione un colaborador</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="solicitud_observaciones"
                                    class="form-label small fw-bold text-muted text-uppercase">Observaciones / Justificación:</label>
                                <textarea id="solicitud_observaciones" name="observaciones" class="form-control" rows="2" style="resize: none;" placeholder="Escriba aquí los detalles..." required></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer border-0 p-3 bg-white d-flex justify-content-between">
                        <button type="button" class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" form="formNuevaSolicitud" class="btn-modern btn-modern-primary">
                            <i class="fas fa-save me-2"></i>Registrar Solicitud
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL 2: APROBAR / GESTIONAR (RH / Aprobadores) -->
    <?php if ($puedeAprobar): ?>
        <div class="modal fade" id="modalAprobacion" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow" style="border-radius: 8px;">
                    <div class="modal-header border-0 py-3 px-3" style="background: #0E544C; color: #fff;">
                        <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-25 rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                                style="width: 40px; height: 40px;">
                                <i class="fas fa-check-double fs-4"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold mb-0 text-white">Solicitud de Pago de Feriado</h5>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3 bg-light">
                        <div class="info-resumen alert alert-info py-2 mb-3">
                            <p class="mb-1"><strong>Colaborador:</strong> <span id="aprobacion_nombre">-</span></p>
                            <p class="mb-1"><strong>Sucursal:</strong> <span id="aprobacion_sucursal">-</span></p>
                            <p class="mb-0"><strong>Fecha Feriado:</strong> <span id="aprobacion_fecha">-</span></p>
                        </div>

                        <form id="formAprobacionSolicitud">
                            <input type="hidden" id="aprobacion_id" name="id">
                            <input type="hidden" id="aprobacion_estado" name="estado" value="Pendiente">

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase">Observaciones:</label>
                                <textarea id="aprobacion_observaciones" name="observaciones" class="form-control bg-white" rows="3" style="resize: none;" readonly></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer border-0 p-3 bg-white d-flex justify-content-end gap-2">
                        <button type="button" class="btn-modern btn-modern-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <button id="btn-rechazar-feriado" type="button" class="btn-modern" style="background:#dc3545;color:#fff;"
                            onclick="submitAprobacion('Descansado')">
                            <i class="fas fa-ban me-1"></i>Rechazar
                        </button>
                        <button id="btn-aprobar-feriado" type="button" class="btn-modern btn-modern-primary"
                            onclick="submitAprobacion('Pagado')">
                            <i class="fas fa-check me-1"></i>Aprobar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Config de autocompletado para el buscador de filtros
        window.CONFIG_FERIADOS = {
            operariosData: [
                <?php foreach ($operarios as $op): ?>
                    { id: <?= $op['CodOperario'] ?>, nombre: '<?= addslashes($op['nombre_completo']) ?>' },
                <?php endforeach; ?>
            ],
            puedeAprobar: <?= $puedeAprobar ? 'true' : 'false' ?>,
            esRH: <?= $puedeVerTodasSucursales ? 'true' : 'false' ?>
        };

        function cambiarRegistrosPorPagina() {
            const limit = document.getElementById('registrosPorPagina').value;
            const params = new URLSearchParams(window.location.search);
            params.set('limit', limit);
            params.set('p', '1');
            window.location.href = 'feriados_v2.php?' + params.toString();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/feriados_v2.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

    <!-- Botón Flotante con opciones -->
    <?php if ($puedeCrear): ?>
        <div class="fab-container">
            <div class="fab-options">
                <div class="fab-option" onclick="mostrarModalSolicitud()">
                    <span class="fab-label">Nueva Solicitud</span>
                    <div class="fab-icon-holder"><i class="fas fa-plus"></i></div>
                </div>
            </div>
            <div class="btn-floating-pitaya" title="Herramientas">
                <i class="fas fa-wrench"></i>
            </div>
        </div>
    <?php endif; ?>
    <!-- FAB Draggable: permite mover el botón flotante libremente en el viewport -->
    <script src="/core/assets/js/fab_button.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>
</html>
