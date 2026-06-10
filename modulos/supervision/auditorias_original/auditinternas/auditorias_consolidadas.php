<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// Configuración inicial y autenticación
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';

// Establecer conexión a la base de datos
$db = $conn;

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar permisos
$puede_ver = tienePermiso('auditoria_efectivo', 'vista', $cargoOperario);
$puede_nuevo = tienePermiso('auditoria_efectivo', 'nuevo', $cargoOperario);
$puede_exportar = tienePermiso('auditoria_efectivo', 'exportar', $cargoOperario);
$puede_editar = tienePermiso('auditoria_efectivo', 'editar', $cargoOperario);

if (!$puede_ver) {
    header('Location: /index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

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
            fecha_hora AS fecha_hora, 
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
            fecha_hora AS fecha_hora, 
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
        -- NOTA: inventario usa fecha_hora_regsys que ya tiene -6h en BD, y el display NO aplica sub(6H) para no tipo_auditoria = inventario/faltante*
        
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

// Redirigir exportación a Excel al archivo ajax correspondiente
if (isset($_GET['exportar_excel'])) {
    $query = http_build_query([
        'tipo' => $tipo_seleccionado,
        'sucursal' => $sucursal_id,
        'operario' => $operario_id,
        'fecha_desde' => $fecha_desde,
        'fecha_hasta' => $fecha_hasta,
    ]);
    header('Location: ajax/exportar_excel.php?' . $query);
    exit;
}

// Generar opciones de meses y años para los selectores
$meses = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

$anios = range(2020, date('Y') + 1); // Desde 2020 hasta el próximo año

// Construir URL base para los filtros
$url_base = 'auditorias_consolidadas.php?';
$params = [];
if (isset($_GET['tipo'])) {
    $params[] = 'tipo=' . urlencode($_GET['tipo']);
}
if (isset($_GET['sucursal'])) {
    $params[] = 'sucursal=' . urlencode($_GET['sucursal']);
}
$url_filtros = $url_base . implode('&', $params);

// Redirigir exportación de deducciones al archivo ajax correspondiente
if (isset($_GET['exportar_deducciones'])) {
    $query = http_build_query([
        'sucursal' => $sucursal_id,
        'operario' => $operario_id,
        'fecha_desde' => $fecha_desde,
        'fecha_hasta' => $fecha_hasta,
    ]);
    header('Location: ajax/exportar_deducciones.php?' . $query);
    exit;
}



// Calcular el total de faltantes según los filtros aplicados
$total_faltante = 0;

foreach ($registros as $registro) {
    $tipo = $registro['tipo_auditoria'];
    $monto = $registro['monto_faltante'];

    switch ($tipo) {
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

// Redirigir exportación contabilidad al archivo ajax correspondiente
if (isset($_GET['exportar_contabilidad'])) {
    $query = http_build_query([
        'fecha_desde' => $fecha_desde,
        'fecha_hasta' => $fecha_hasta,
    ]);
    header('Location: ajax/exportar_contabilidad.php?' . $query);
    exit;
}



// Redirigir exportación faltante de caja al archivo ajax correspondiente
if (isset($_GET['exportar_faltante_caja'])) {
    $query = http_build_query([
        'sucursal' => $sucursal_id,
        'operario' => $operario_id,
        'fecha_desde' => $fecha_desde,
        'fecha_hasta' => $fecha_hasta,
    ]);
    header('Location: ajax/exportar_faltante_caja.php?' . $query);
    exit;
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditorías de Efectivo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/auditorias_consolidadas.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Auditorías de Efectivo'); ?>

            <div class="container-fluid p-3">

                <!-- Filtros -->
                <div class="filtros-container">
                    <form method="get" action="auditorias_consolidadas.php" class="filtros-form">
                        <!-- Filtro de Tipo -->
                        <div style="display:none;" class="filtro-group">
                            <label for="tipo">Tipo</label>
                            <select id="tipo" name="tipo" class="filtro-select">
                                <option value="todos" <?= $tipo_seleccionado == 'todos' ? 'selected' : '' ?>>Todos los
                                    tipos</option>
                                <option value="facturacion" <?= $tipo_seleccionado == 'facturacion' ? 'selected' : '' ?>>
                                    Caja Facturación</option>
                                <option value="caja_chica" <?= $tipo_seleccionado == 'caja_chica' ? 'selected' : '' ?>>Caja
                                    Chica</option>
                                <option value="inventario" <?= $tipo_seleccionado == 'inventario' ? 'selected' : '' ?>>
                                    Auditoría Inventario</option>
                                <option value="faltante_inventario" <?= $tipo_seleccionado == 'faltante_inventario' ? 'selected' : '' ?>>Faltante Inventario</option>
                                <option value="faltante_danos" <?= $tipo_seleccionado == 'faltante_danos' ? 'selected' : '' ?>>Faltante Daños</option>
                                <option value="faltante_caja" <?= $tipo_seleccionado == 'faltante_caja' ? 'selected' : '' ?>>Faltante Caja</option>
                            </select>
                        </div>

                        <!-- Filtro de Sucursal -->
                        <div class="filtro-group">
                            <label for="sucursal">Sucursal</label>
                            <select id="sucursal" name="sucursal" class="filtro-select">
                                <option value="todas" <?= $sucursal_id == 'todas' ? 'selected' : '' ?>>Todas las sucursales
                                </option>
                                <?php foreach ($sucursales as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>" <?= ($sucursal['codigo'] == $sucursal_id || $sucursal['nombre'] == $sucursal_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Filtro de Colaborador -->
                        <div style="display:none;" class="filtro-group">
                            <label for="operario">Colaborador</label>
                            <input type="text" id="operario" name="operario_nombre" placeholder="Escriba para buscar..."
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
                                value="<?= htmlspecialchars($fecha_desde) ?>" max="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="filtro-group">
                            <label for="fecha_hasta">Hasta</label>
                            <input type="date" id="fecha_hasta" name="fecha_hasta"
                                value="<?= htmlspecialchars($fecha_hasta) ?>" max="<?= date('Y-m-d') ?>">
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

                        <?php if ($puede_exportar): ?>
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
                                ?>" class="btn-agregar excel"
                                    style="background-color: #f39c12; border-color: #f39c12; color: white;">
                                    <i class="fas fa-file-excel"></i> Faltantes Caja
                                </a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ($puede_nuevo): ?>
                    <!-- Botón Flotante de Nueva Auditoría (FAB) -->
                    <div class="fab-container" id="fabNuevaAuditoria">
                        <div class="fab-options">
                            <a href="faltante_danos.php" class="fab-option">
                                <span class="fab-label">Faltante Daños</span>
                                <div class="fab-icon-holder"><i class="fas fa-times-circle"></i></div>
                            </a>
                            <a href="faltante_inventario.php" class="fab-option">
                                <span class="fab-label">Faltante Inventario</span>
                                <div class="fab-icon-holder"><i class="fas fa-exclamation-triangle"></i></div>
                            </a>
                            <a href="auditoria_inventario.php" class="fab-option">
                                <span class="fab-label">Auditoría Inventario</span>
                                <div class="fab-icon-holder"><i class="fas fa-boxes"></i></div>
                            </a>
                            <a href="auditoria_caja_chica.php" class="fab-option">
                                <span class="fab-label">Auditoría Caja Chica</span>
                                <div class="fab-icon-holder"><i class="fas fa-wallet"></i></div>
                            </a>
                            <a href="auditoria_caja_facturacion.php" class="fab-option">
                                <span class="fab-label">Auditoría Caja Facturación</span>
                                <div class="fab-icon-holder"><i class="fas fa-cash-register"></i></div>
                            </a>
                        </div>
                        <div class="btn-floating-pitaya" title="Nueva Auditoría">
                            <i class="fas fa-wrench"></i>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Mostrar registros de la tabla seleccionada -->
                <div class="encabezado-historial">
                    <!-- Limpiar campos de Filtros aplicados a la página actual -->
                    <a href="<?php echo $url_limpiar_filtros; ?>" class="btn-agregar"
                        style=" display:none; background-color: #f1f1f1; color: #333; border: 1px solid #ccc;">
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
                    ?>" class="btn-agregar"
                        style="background-color: transparent; color: #9b59b6; border: 1px solid #9b59b6;">
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
                    ?>" class="btn-agregar"
                        style="background-color: transparent; color: #3498db; border: 1px solid #3498db;">
                        <i class="fas fa-file-invoice-dollar"></i> Exportar para Contabilidad
                    </a>

                    <h3 style="display:none; margin: 0; color: #333;">
                        Total de Faltantes:
                        <span
                            style="color: <?php echo ($total_faltante > 0) ? '#e74c3c' : '#27ae60'; ?>; font-weight: bold;">
                            C$ <?php echo number_format($total_faltante, 2); ?>
                        </span>
                    </h3>
                </div>

                <!-- Resumen de total de faltantes -->
                <div
                    style="background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ddd; text-align: center; display:none;">
                    <h3 style="margin: 0; color: #333;">
                        Total de Faltantes:
                        <span
                            style="color: <?php echo ($total_faltante > 0) ? '#e74c3c' : '#27ae60'; ?>; font-weight: bold;">
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
                                        <a
                                            href="?<?= http_build_query(array_merge($params_base, ['sucursal' => 'todas'])) ?>">
                                            Todas
                                        </a>
                                        <?php foreach ($sucursales as $sucursal): ?>
                                            <a
                                                href="?<?= http_build_query(array_merge($params_base, ['sucursal' => $sucursal['codigo']])) ?>">
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
                                        <a
                                            href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'todos'])) ?>">
                                            Todos
                                        </a>
                                        <a
                                            href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'facturacion'])) ?>">
                                            Caja Facturación
                                        </a>
                                        <a
                                            href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'caja_chica'])) ?>">
                                            Caja Chica
                                        </a>
                                        <a
                                            href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'inventario'])) ?>">
                                            Auditoría Inventario
                                        </a>
                                        <a
                                            href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'faltante_inventario'])) ?>">
                                            Faltante Inventario
                                        </a>
                                        <a
                                            href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'faltante_danos'])) ?>">
                                            Faltante Daños
                                        </a>
                                        <a
                                            href="?<?= http_build_query(array_merge($params_base, ['tipo' => 'faltante_caja'])) ?>">
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
                                <td colspan="4" style="text-align:center; background-color:#fff;">Sin registros actualmente.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $registro): ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <?php
                                        $meses_cortos = [
                                            1 => 'ene',
                                            2 => 'feb',
                                            3 => 'mar',
                                            4 => 'abr',
                                            5 => 'may',
                                            6 => 'jun',
                                            7 => 'jul',
                                            8 => 'ago',
                                            9 => 'sep',
                                            10 => 'oct',
                                            11 => 'nov',
                                            12 => 'dic'
                                        ];

                                        if ($registro['tipo_auditoria'] == 'faltante_caja') {
                                            // Para faltante_caja, mostrar solo la fecha (sin hora)
                                            $fecha = new DateTime($registro['fecha_hora']);
                                            $dia = $fecha->format('d');
                                            $mes = $meses_cortos[(int) $fecha->format('m')];
                                            $anio = $fecha->format('y');
                                            echo "$dia-$mes-$anio";
                                        } else {
                                            // Para facturación/caja chica: fecha_hora ya es la hora real de Nicaragua (sin ajuste necesario)
                                            // Para inventario/faltante: fecha_hora_regsys que también ya viene procesada
                                            $fecha = new DateTime($registro['fecha_hora']);

                                            $dia = $fecha->format('d');
                                            $mes = $meses_cortos[(int) $fecha->format('m')];
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

                                        echo '<span class="badge-tipo ' . $badge_class . '">' . $tipo_text . '</span>';
                                        ?>
                                    </td>
                                    <td style="text-align:center;" class="monto-faltante <?php
                                    // Determinar la clase CSS basada en el tipo de auditoría y el monto
                                    $monto_mostrar = 0;

                                    switch ($registro['tipo_auditoria']) {
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
                                            <?php if ($puede_editar): ?>
                                                <a href="<?php echo $registro['url_ver']; ?>?id=<?php echo $registro['id']; ?>"
                                                    style="color:#51B8AC;">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div><!-- /.container-fluid -->
        </div><!-- /.sub-container -->
    </div><!-- /.main-container -->

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // FAB: toggle al hacer clic en móvil / cerrar al hacer clic fuera
        $(document).ready(function () {
            $(document).on('click', '.btn-floating-pitaya', function (e) {
                const container = $(this).closest('.fab-container');
                if (container.length) {
                    container.toggleClass('active');
                    $(this).toggleClass('active');
                }
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.fab-container').length) {
                    $('.fab-container').removeClass('active');
                    $('.btn-floating-pitaya').removeClass('active');
                }
            });
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
    </script>
    <script src="js/auditorias_consolidadas.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
    <!-- FAB Draggable: permite mover el botón flotante libremente en el viewport -->
    <script src="/core/assets/js/fab_button.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>