<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? 0;

$tienePermisoVista = tienePermiso('administracion_colaboradores_lideres', 'vista', $cargoOperario);

if (!$tienePermisoVista) {
    header('Location: /login.php');
    exit();
}

$tienePermisoPlanificacion = tienePermiso('administracion_colaboradores_lideres', 'planificacion', $cargoOperario);
$tienePermisoEditar = tienePermiso('administracion_colaboradores_lideres', 'editar_colaborador', $cargoOperario);
$tienePermisoEditarSupervisor = tienePermiso('administracion_colaboradores_lideres', 'editar_supervisor', $cargoOperario);
$tienePermisoExportar = tienePermiso('administracion_colaboradores_lideres', 'exportar', $cargoOperario);

// Obtener cargo principal
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Determinar la semana a mostrar
$semanaActualRef = obtenerSemanaActual();
$semanaId = $_GET['semana'] ?? 'actual';

if ($semanaId === 'actual') {
    $semanaMostrar = $semanaActualRef;
} elseif ($semanaId === 'siguiente') {
    global $conn;
    $stmtNext = $conn->prepare("SELECT * FROM SemanasSistema WHERE fecha_inicio > ? ORDER BY fecha_inicio ASC LIMIT 1");
    $stmtNext->execute([$semanaActualRef['fecha_inicio']]);
    $semanaMostrar = $stmtNext->fetch() ?: $semanaActualRef;
} else {
    // Es un ID de semana específico (numero_semana)
    $semanaMostrar = obtenerSemanaPorId($semanaId) ?: $semanaActualRef;
}


// Determinar tipo de semana para la interfaz (siguiente = editable)
$tipoSemana = ($semanaMostrar['fecha_inicio'] > $semanaActualRef['fecha_inicio']) ? 'siguiente' : 'actual';
if ($semanaMostrar['fecha_inicio'] < $semanaActualRef['fecha_inicio']) {
    $tipoSemana = 'pasada'; // semanas anteriores no editables
}

// Obtener semana anterior y siguiente relativas a la que se muestra
global $conn;
$stmtPrev = $conn->prepare("SELECT * FROM SemanasSistema WHERE fecha_inicio < ? ORDER BY fecha_inicio DESC LIMIT 1");
$stmtPrev->execute([$semanaMostrar['fecha_inicio']]);
$semanaAnteriorObj = $stmtPrev->fetch();

$stmtNext2 = $conn->prepare("SELECT * FROM SemanasSistema WHERE fecha_inicio > ? ORDER BY fecha_inicio ASC LIMIT 1");
$stmtNext2->execute([$semanaMostrar['fecha_inicio']]);
$semanaSiguienteObj = $stmtNext2->fetch();

// Bloquear avanzar más allá de la semana siguiente a la actual
$stmtMaxSemana = $conn->prepare("SELECT * FROM SemanasSistema WHERE fecha_inicio > ? ORDER BY fecha_inicio ASC LIMIT 1");
$stmtMaxSemana->execute([$semanaActualRef['fecha_inicio']]);
$maxSemanaPermitida = $stmtMaxSemana->fetch();

if ($maxSemanaPermitida && $semanaMostrar['fecha_inicio'] >= $maxSemanaPermitida['fecha_inicio']) {
    $semanaSiguienteObj = null;
}


// Mantener variables por compatibilidad
$semanaActual = $semanaActualRef;
$semanaSiguiente = $semanaSiguienteObj;

/**
 * Obtiene el total de colaboradores por sucursal para una semana específica
 */
function obtenerTotalColaboradoresSucursal($codSucursal, $semana)
{
    global $conn;

    $fechaInicioSemana = $semana['fecha_inicio'];
    $fechaFinSemana = $semana['fecha_fin'];

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT anc.CodOperario) as total
        FROM AsignacionNivelesCargos anc
        JOIN Operarios o ON anc.CodOperario = o.CodOperario
        WHERE anc.Sucursal = ?
        AND anc.CodNivelesCargos IN (2, 5, 43, 44, 45, 46, 47)
        AND anc.Fecha <= ? 
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
    ");

    $stmt->execute([$codSucursal, $fechaFinSemana, $fechaInicioSemana]);
    $result = $stmt->fetch();

    return $result['total'] ?? 0;
}

/**
 * Obtiene el total de colaboradores en todas las sucursales para una semana específica
 */
function obtenerTotalColaboradoresGlobal($semana)
{
    global $conn;

    $fechaInicioSemana = $semana['fecha_inicio'];
    $fechaFinSemana = $semana['fecha_fin'];

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT anc.CodOperario) as total
        FROM AsignacionNivelesCargos anc
        JOIN Operarios o ON anc.CodOperario = o.CodOperario
        JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE anc.CodNivelesCargos IN (2, 5, 43, 44, 45, 46, 47)
        AND anc.Fecha <= ? 
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND s.activa = 1
        AND s.sucursal = 1
    ");

    $stmt->execute([$fechaFinSemana, $fechaInicioSemana]);
    $result = $stmt->fetch();

    return $result['total'] ?? 0;
}

// Obtener todas las sucursales agrupadas por departamento
function obtenerSucursalesAgrupadas()
{
    global $conn;

    // Obtener sucursales con información del departamento
    $stmt = $conn->prepare("
        SELECT 
            s.codigo, 
            s.nombre, 
            s.cod_departamento,
            s.supervisor_asignado,
            d.nombre as departamento_nombre
        FROM sucursales s
        JOIN departamentos d ON s.cod_departamento = d.codigo
        WHERE s.activa = 1
        AND s.sucursal = 1  -- Solo sucursales físicas
        ORDER BY 
            CASE WHEN s.cod_departamento = 1 THEN 1 ELSE 2 END,
            s.nombre
    ");
    $stmt->execute();
    $sucursales = $stmt->fetchAll();

    
    // Resolver nombres de supervisores (supervisor_asignado ahora es JSON array)
    foreach ($sucursales as &$suc) {
        $ids = json_decode($suc['supervisor_asignado'] ?? '[]', true) ?: [];
        $ids = array_filter(array_map('intval', $ids));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtSup = $conn->prepare(
                "SELECT CodOperario, Nombre, Apellido FROM Operarios WHERE CodOperario IN ($placeholders)"
            );
            $stmtSup->execute($ids);
            $suc['supervisores'] = $stmtSup->fetchAll();
        } else {
            $suc['supervisores'] = [];
        }
    }
    unset($suc);

    // Agrupar por departamento
    $agrupadas = [
        'Managua' => [],
        'Departamentos' => []
    ];

    foreach ($sucursales as $sucursal) {
        if ($sucursal['cod_departamento'] == 1) {
            $agrupadas['Managua'][] = $sucursal;
        } else {
            $agrupadas['Departamentos'][] = $sucursal;
        }
    }

    return $agrupadas;
}

// Obtener colaboradores asignados a una sucursal para la semana específica
function obtenerColaboradoresPorSucursal($codSucursal, $semana)
{
    global $conn;

    // Obtener la fecha de inicio de la semana
    $fechaInicioSemana = $semana['fecha_inicio'];
    $fechaFinSemana = $semana['fecha_fin'];

    $stmt = $conn->prepare("
        SELECT 
            anc.CodOperario,
            anc.CodNivelesCargos,
            anc.CodContrato,
            anc.codigo_contrato_asociado,
            o.Nombre,
            o.Apellido,
            o.Apellido2,
            nc.Nombre as cargo_nombre,
            c.codigo_manual_contrato
        FROM AsignacionNivelesCargos anc
        JOIN Operarios o ON anc.CodOperario = o.CodOperario
        JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
        LEFT JOIN Contratos c ON anc.CodContrato = c.CodContrato
        WHERE anc.Sucursal = ?
        AND anc.CodNivelesCargos IN (2, 5, 43, 44, 45, 46, 47)  -- Solo cargos permitidos
        -- Verificar que la asignación esté activa durante la semana
        AND anc.Fecha <= ? 
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        ORDER BY 
            CASE 
                WHEN anc.CodNivelesCargos IN (5, 43) THEN 1  -- Líderes primero
                ELSE 2 
            END,
            nc.Peso DESC,
            o.Nombre, o.Apellido
    ");

    $stmt->execute([$codSucursal, $fechaFinSemana, $fechaInicioSemana]);
    $colaboradores = $stmt->fetchAll();

    // Separar en líderes y colaboradores generales
    $resultado = [
        'lideres' => [],
        'colaboradores' => []
    ];

    foreach ($colaboradores as $colaborador) {
        $datosColaborador = [
            'codigo' => $colaborador['CodOperario'],
            'cod_cargo' => $colaborador['CodNivelesCargos'],
            'cod_contrato' => $colaborador['CodContrato'],
            'codigo_contrato_asociado' => $colaborador['codigo_contrato_asociado'],
            'codigo_manual_contrato' => $colaborador['codigo_manual_contrato'],
            'cargo_nombre' => $colaborador['cargo_nombre'],
            'nombre' => trim($colaborador['Nombre'] . ' ' . $colaborador['Apellido'] . ' ' . ($colaborador['Apellido2'] ?? ''))
        ];

        if (in_array($colaborador['CodNivelesCargos'], [5, 43])) {
            // Líder
            $resultado['lideres'][] = $datosColaborador;
        } else {
            // Colaborador general
            $resultado['colaboradores'][] = $datosColaborador;
        }
    }

    return $resultado;
}

// Obtener colaboradores no asignados (para el pool de arrastre)
function obtenerColaboradoresNoAsignados($semana)
{
    global $conn;

    $fechaInicioSemana = $semana['fecha_inicio'];
    $fechaFinSemana = $semana['fecha_fin'];

    // Buscar operarios con cargos permitidos que no estén asignados a ninguna sucursal activa
    $stmt = $conn->prepare("
        SELECT 
            o.CodOperario,
            o.Nombre,
            o.Apellido,
            o.Apellido2,
            -- Obtener el último cargo del operario (de los permitidos)
            (SELECT anc2.CodNivelesCargos 
             FROM AsignacionNivelesCargos anc2 
             WHERE anc2.CodOperario = o.CodOperario 
             AND anc2.CodNivelesCargos IN (2, 5, 43, 44, 45, 46, 47)
             ORDER BY anc2.Fecha DESC, anc2.CodAsignacionNivelesCargos DESC 
             LIMIT 1) as ultimo_cargo,
            uc.CodContrato as ultimo_cod_contrato,
            uc.codigo_manual_contrato as ultimo_codigo_manual
        FROM Operarios o
        INNER JOIN Contratos uc ON uc.cod_operario = o.CodOperario 
            AND uc.CodContrato = (
                SELECT MAX(CodContrato) 
                FROM Contratos 
                WHERE cod_operario = o.CodOperario
            )
        WHERE o.CodOperario NOT IN (
            -- Excluir operarios que ya tienen asignación activa durante esta semana
            SELECT DISTINCT anc.CodOperario
            FROM AsignacionNivelesCargos anc
            WHERE anc.CodNivelesCargos IN (2, 5, 43, 44, 45, 46, 47)
            AND anc.Fecha <= ? 
            AND (anc.Fin IS NULL OR anc.Fin >= ?)
        )
        AND (uc.fecha_salida IS NULL OR uc.fecha_salida >= ?)
        ORDER BY o.Nombre, o.Apellido
    ");

    $stmt->execute([$fechaFinSemana, $fechaInicioSemana, $fechaInicioSemana]);
    $noAsignados = $stmt->fetchAll();

    // Filtrar para incluir solo los que tienen un cargo permitido
    $resultado = [];
    foreach ($noAsignados as $operario) {
        if ($operario['ultimo_cargo'] && in_array($operario['ultimo_cargo'], [2, 5, 43, 44, 45, 46, 47])) {
            // Obtener nombre del cargo
            $stmtCargo = $conn->prepare("SELECT Nombre FROM NivelesCargos WHERE CodNivelesCargos = ?");
            $stmtCargo->execute([$operario['ultimo_cargo']]);
            $cargo = $stmtCargo->fetch();

            $resultado[] = [
                'codigo' => $operario['CodOperario'],
                'cod_cargo' => $operario['ultimo_cargo'],
                'cod_contrato' => $operario['ultimo_cod_contrato'],
                'codigo_manual_contrato' => $operario['ultimo_codigo_manual'],
                'cargo_nombre' => $cargo['Nombre'] ?? 'Sin cargo',
                'nombre' => trim($operario['Nombre'] . ' ' . $operario['Apellido'] . ' ' . ($operario['Apellido2'] ?? ''))
            ];
        }
    }

    return $resultado;
}



// Obtener datos para la vista
$sucursalesAgrupadas = obtenerSucursalesAgrupadas();
$colaboradoresPorSucursal = [];
$colaboradoresNoAsignados = ($tipoSemana === 'siguiente') ? obtenerColaboradoresNoAsignados($semanaMostrar) : [];

foreach ($sucursalesAgrupadas as $departamento => $sucursales) {
    foreach ($sucursales as $sucursal) {
        $colaboradoresPorSucursal[$sucursal['codigo']] = obtenerColaboradoresPorSucursal(
            $sucursal['codigo'],
            $semanaMostrar
        );
    }
}

// Contar asignaciones múltiples de colaboradores
$conteoAsignaciones = [];
foreach ($colaboradoresPorSucursal as $codSucursal => $grupos) {
    foreach (['lideres', 'colaboradores'] as $tipoGrupo) {
        foreach ($grupos[$tipoGrupo] as $col) {
            $codigo = $col['codigo'];
            $conteoAsignaciones[$codigo] = ($conteoAsignaciones[$codigo] ?? 0) + 1;
        }
    }
}

// Calcular totales
$totalColaboradoresGlobal = obtenerTotalColaboradoresGlobal($semanaMostrar);
$totalesPorSucursal = [];

// Calcular totales por sucursal
foreach ($sucursalesAgrupadas as $departamento => $sucursales) {
    foreach ($sucursales as $sucursal) {
        $totalesPorSucursal[$sucursal['codigo']] = obtenerTotalColaboradoresSucursal(
            $sucursal['codigo'],
            $semanaMostrar
        );
    }
}

// Preparar datos para exportar
$datosExportar = [];
$semana_str = "Semana " . ($semanaMostrar['numero_semana'] ?? 'N/A');
$rango_fechas = formatoFecha($semanaMostrar['fecha_inicio'] ?? '') . " - " . formatoFecha($semanaMostrar['fecha_fin'] ?? '');

foreach ($sucursalesAgrupadas as $departamentoNombre => $sucursales) {
    foreach ($sucursales as $sucursal) {
        $supervisores_arr = [];
        if (!empty($sucursal['supervisores'])) {
            foreach ($sucursal['supervisores'] as $sup) {
                $supervisores_arr[] = trim($sup['Nombre'] . ' ' . $sup['Apellido']);
            }
        }
        $supervisores_str = empty($supervisores_arr) ? 'Sin supervisor' : implode(', ', $supervisores_arr);
        
        $colaboradores = $colaboradoresPorSucursal[$sucursal['codigo']] ?? ['lideres' => [], 'colaboradores' => []];
        $todos = array_merge($colaboradores['lideres'], $colaboradores['colaboradores']);
        
        foreach ($todos as $col) {
            $datosExportar[] = [
                'Semana' => $semana_str,
                'Rango Fechas' => $rango_fechas,
                'Tienda' => $sucursal['nombre'],
                'Colaborador' => $col['nombre'],
                'Cargo' => $col['cargo_nombre'],
                'Supervisor' => $supervisores_str,
                'Departamento' => $departamentoNombre
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Colaboradores por Sucursal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
    <link rel="stylesheet" href="/core/assets/css/fab_button.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/gestion_colaboradores.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body data-tipo-semana="<?= htmlspecialchars($tipoSemana) ?>"
    data-tiene-permiso-editar="<?= $tienePermisoEditar ? '1' : '0' ?>"
    data-tiene-permiso-planificacion="<?= $tienePermisoPlanificacion ? '1' : '0' ?>"
    data-tiene-permiso-editar-supervisor="<?= $tienePermisoEditarSupervisor ? '1' : '0' ?>">
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Panel de Equipos Tiendas'); ?>

            <div class="container-fluid p-3">
                <div class="gestion-container">

                    <!-- Navegación de Semana -->
                    <div class="semana-nav">
                        <div class="nav-left">
                            <?php if ($semanaAnteriorObj): ?>
                                <a href="gestion_colaboradores.php?semana=<?= $semanaAnteriorObj['numero_semana'] ?>"
                                    class="btn-semana prev" title="Semana anterior">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn-semana prev disabled" disabled>
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                            <?php endif; ?>

                            <div class="semana-info">
                                <span class="semana-label">Semana <?= $semanaMostrar['numero_semana'] ?? 'N/A' ?></span>
                                <span class="semana-rango"><?= formatoFecha($semanaMostrar['fecha_inicio'] ?? '') ?> -
                                    <?= formatoFecha($semanaMostrar['fecha_fin'] ?? '') ?></span>
                                <?php if ($semanaMostrar['id'] == $semanaActualRef['id']): ?>
                                    <span class="badge-actual">Actual</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($semanaSiguienteObj): ?>
                                <a href="gestion_colaboradores.php?semana=<?= $semanaSiguienteObj['numero_semana'] ?>"
                                    class="btn-semana next" title="Semana siguiente">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn-semana next disabled" disabled>
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="nav-right" style="display: flex; align-items: center; gap: 10px;">
                            <div style="position: relative;">
                                <i class="bi bi-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 14px; pointer-events: none;"></i>
                                <input
                                    type="text"
                                    id="buscadorTienda"
                                    placeholder="Buscar tienda..."
                                    autocomplete="off"
                                    style="padding: 6px 12px 6px 32px; border-radius: 20px; border: 1px solid #ced4da; font-size: 13px; outline: none; width: 190px; transition: border-color .2s, box-shadow .2s;"
                                    onfocus="this.style.borderColor='#0E544C'; this.style.boxShadow='0 0 0 3px rgba(14,84,76,.15)';"
                                    onblur="this.style.borderColor='#ced4da'; this.style.boxShadow='none';"
                                >
                            </div>
                            <span class="global-counter"
                                style="background: #0E544C; color: white; padding: 6px 16px; border-radius: 20px; font-weight: bold; font-size: 14px; white-space: nowrap;">
                                Total: <?= $totalColaboradoresGlobal ?> colaboradores
                            </span>
                        </div>
                    </div>

                    <!-- Información de ayuda -->
                    <div class="help-info" style="display:none;">
                        <h4><i class="fas fa-info-circle"></i> Instrucciones:</h4>
                        <p>
                            <strong>Semana Actual:</strong> Solo visualización de asignaciones actuales.<br>
                            <strong>Semana Siguiente:</strong> Arrastra y suelta colaboradores entre sucursales.
                            Los cambios se aplicarán a partir de la semana siguiente.
                        </p>
                        <p style="margin-top: 8px;">
                            <i class="fas fa-user-shield"></i> <strong>Líderes:</strong> Máximo 2 por sucursal (cargos 5
                            o 43)<br>
                            <i class="fas fa-users"></i> <strong>Colaboradores:</strong> Cargos 2, 44, 45, 46, 47
                        </p>
                    </div>

                    <?php if (isset($_SESSION['exito'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= $_SESSION['exito'] ?>
                            <?php unset($_SESSION['exito']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= $_SESSION['error'] ?>
                            <?php unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario para guardar movimientos -->
                    <form id="movimientosForm">
                        <input type="hidden" name="semana" value="siguiente">
                        <div id="movimientosData"></div>

                        <!-- Secciones por departamento -->
                        <?php foreach ($sucursalesAgrupadas as $departamentoNombre => $sucursales): ?>
                            <?php if (!empty($sucursales)): ?>
                                <div class="departamento-section">
                                    <h2 class="departamento-title"><?= htmlspecialchars($departamentoNombre) ?></h2>

                                    <div class="sucursales-grid">
                                        <?php foreach ($sucursales as $sucursal):
                                            $colaboradores = $colaboradoresPorSucursal[$sucursal['codigo']] ?? ['lideres' => [], 'colaboradores' => []];
                                            ?>
                                            <div class="sucursal-card" data-sucursal-id="<?= $sucursal['codigo'] ?>">
                                                <div class="sucursal-header" style="flex-direction: column; align-items: flex-start;">
                                                    <div style="display: flex; justify-content: space-between; width: 100%;">
                                                        <span><?= htmlspecialchars($sucursal['nombre']) ?></span>
                                                        <div style="display: flex; align-items: center; gap: 8px;">
                                                            <span class="sucursal-counter"
                                                                style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.85em;">
                                                                <?= $totalesPorSucursal[$sucursal['codigo']] ?? 0 ?>
                                                            </span>
                                                            <small
                                                                style="opacity: 0.8; display:none;">#<?= $sucursal['codigo'] ?></small>
                                                        </div>
                                                    </div>
                                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:6px; margin-top:4px;">
                                                        <div style="font-size: 0.8em; opacity: 0.9; font-weight: normal; font-style: <?= empty($sucursal['supervisores']) ? 'italic' : 'normal' ?>;" class="supervisor-display" data-cod-sucursal="<?= $sucursal['codigo'] ?>">
                                                            <i class="fas fa-user-tie"></i>
                                                            <?php if (!empty($sucursal['supervisores'])): ?>
                                                                Supervisor<?= count($sucursal['supervisores']) > 1 ? 'es' : '' ?>:
                                                                <?= htmlspecialchars(implode(', ', array_map(fn($s) => trim($s['Nombre'] . ' ' . $s['Apellido']), $sucursal['supervisores']))) ?>
                                                            <?php else: ?>
                                                                Sin supervisor asignado
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($tienePermisoEditarSupervisor): ?>
                                                            <button type="button"
                                                                class="btn-edit-supervisor"
                                                                title="Editar supervisores"
                                                                data-cod-sucursal="<?= $sucursal['codigo'] ?>"
                                                                data-nombre-sucursal="<?= htmlspecialchars($sucursal['nombre']) ?>"
                                                                data-supervisores='<?= htmlspecialchars(json_encode(array_map(fn($s) => ['id' => (int)$s['CodOperario'], 'nombre' => trim($s['Nombre'] . ' ' . $s['Apellido'])], $sucursal['supervisores'])), ENT_QUOTES) ?>'>
                                                                <i class="fas fa-pencil-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="sucursal-body">
                                                    <!-- Líderes -->
                                                    <div class="lideres-section">
                                                        <div class="section-title">
                                                            <i class="fas fa-user-shield"></i> Líderes
                                                        </div>
                                                        <div class="drag-area lideres 
                                                <?= $tipoSemana === 'siguiente' ? 'sortable-lideres' : '' ?>"
                                                            data-sucursal="<?= $sucursal['codigo'] ?>" data-tipo="lideres"
                                                            data-max="2">
                                                            <?php foreach ($colaboradores['lideres'] as $lider): ?>
                                                                <div class="drag-item lider" data-id="<?= $lider['codigo'] ?>"
                                                                    data-cargo="<?= $lider['cod_cargo'] ?>" title="<?= htmlspecialchars($lider['nombre']) ?>&#10;Código: <?= $lider['codigo'] ?>&#10;Cargo: <?= htmlspecialchars($lider['cargo_nombre']) ?>">
                                                                    <?php $cargo_lider_simplificado = preg_replace('/^Vendedor\s+/i', '', $lider['cargo_nombre']); ?>
                                                                    <div class="item-info">
                                                                        <div class="item-name">
                                                                            <?= htmlspecialchars($lider['nombre']) ?>
                                                                            <?php if (($conteoAsignaciones[$lider['codigo']] ?? 0) >= 2): ?>
                                                                                <i class="fas fa-exclamation-triangle text-danger ms-1" title="Asignado a 2 o más tiendas"></i>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                    <span class="item-badge"><?= htmlspecialchars($cargo_lider_simplificado) ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if (empty($colaboradores['lideres'])): ?>
                                                                <div style="text-align: center; color: #999; padding: 10px;">
                                                                    Sin líderes asignados
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <!-- Colaboradores generales -->
                                                    <div class="colaboradores-section">
                                                        <div class="section-title">
                                                            <i class="fas fa-users"></i> Vendedores
                                                        </div>
                                                        <div class="drag-area colaboradores 
                                                <?= $tipoSemana === 'siguiente' ? 'sortable-colaboradores' : '' ?>"
                                                            data-sucursal="<?= $sucursal['codigo'] ?>" data-tipo="colaboradores">
                                                            <?php foreach ($colaboradores['colaboradores'] as $colaborador): ?>
                                                                <div class="drag-item colaborador"
                                                                    data-id="<?= $colaborador['codigo'] ?>"
                                                                    data-cargo="<?= $colaborador['cod_cargo'] ?>" title="<?= htmlspecialchars($colaborador['nombre']) ?>&#10;Código: <?= $colaborador['codigo'] ?>&#10;Cargo: <?= htmlspecialchars($colaborador['cargo_nombre']) ?>">
                                                                    <?php $cargo_colaborador_simplificado = preg_replace('/^Vendedor\s+/i', '', $colaborador['cargo_nombre']); ?>
                                                                    <div class="item-info">
                                                                        <div class="item-name">
                                                                            <?= htmlspecialchars($colaborador['nombre']) ?>
                                                                            <?php if (($conteoAsignaciones[$colaborador['codigo']] ?? 0) >= 2): ?>
                                                                                <i class="fas fa-exclamation-triangle text-danger ms-1" title="Asignado a 2 o más tiendas"></i>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                    <span class="item-badge"><?= htmlspecialchars($cargo_colaborador_simplificado) ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if (empty($colaboradores['colaboradores'])): ?>
                                                                <div style="text-align: center; color: #999; padding: 10px;">
                                                                    Sin vendedores asignados
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <!-- Pool de no asignados (solo para semana siguiente) -->
                        <?php if ($tipoSemana === 'siguiente' && !empty($colaboradoresNoAsignados)): ?>
                            <div class="departamento-section">
                                <h2 class="departamento-title">Colaboradores No Asignados</h2>

                                <div class="no-asignados-section">
                                    <div class="section-title">
                                        <i class="fas fa-user-clock"></i> Disponibles para asignar
                                    </div>
                                    <div class="drag-area no-asignados sortable-no-asignados" data-sucursal="0"
                                        data-tipo="no-asignados">
                                        <?php foreach ($colaboradoresNoAsignados as $colaborador): ?>
                                            <div class="drag-item no-asignado" data-id="<?= $colaborador['codigo'] ?>"
                                                data-cargo="<?= $colaborador['cod_cargo'] ?>" title="<?= htmlspecialchars($colaborador['nombre']) ?>&#10;Código: <?= $colaborador['codigo'] ?>&#10;Cargo: <?= htmlspecialchars($colaborador['cargo_nombre']) ?>">
                                                <?php $cargo_no_asignado_simplificado = preg_replace('/^Vendedor\s+/i', '', $colaborador['cargo_nombre']); ?>
                                                <div class="item-info">
                                                    <div class="item-name"><?= htmlspecialchars($colaborador['nombre']) ?></div>
                                                </div>
                                                <span class="item-badge"><?= htmlspecialchars($cargo_no_asignado_simplificado) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Botones de acción (solo para semana siguiente) -->
                        <?php if ($tipoSemana === 'siguiente' && $tienePermisoPlanificacion): ?>
                            <div class="actions-bar">
                                <button type="button" id="btnReset" class="btn-reset">
                                    <i class="fas fa-undo"></i> Restaurar Original
                                </button>
                                <button type="button" id="btnGuardar" class="btn-guardar">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts requeridos para el header y menú -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/gestion_colaboradores.js"></script>

    <script>
    (function () {
        const input = document.getElementById('buscadorTienda');
        if (!input) return;

        input.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();

            // Recorrer cada sección de departamento
            document.querySelectorAll('.departamento-section').forEach(function (seccion) {
                // Ignorar el pool de no-asignados (no tiene tarjetas de sucursal)
                const cards = seccion.querySelectorAll('.sucursal-card');
                if (cards.length === 0) return; // sección sin tarjetas (ej. pool)

                let visibles = 0;
                cards.forEach(function (card) {
                    // Obtener el nombre de la sucursal del span del header
                    const nombreElem = card.querySelector('.sucursal-header span');
                    const nombre = nombreElem ? nombreElem.textContent.toLowerCase() : '';

                    if (!query || nombre.includes(query)) {
                        card.style.display = '';
                        visibles++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Ocultar la sección completa si ninguna tarjeta es visible
                seccion.style.display = visibles === 0 ? 'none' : '';
            });
        });
    })();
    </script>

    <!-- Modal para editar supervisores -->
    <div id="modalSupervisor" class="modal-supervisor-overlay" style="display:none;">
        <div class="modal-supervisor-box">
            <div class="modal-supervisor-header">
                <span><i class="fas fa-user-tie"></i> Supervisores: <strong id="modalSucursalNombre"></strong></span>
                <button type="button" id="btnCerrarModalSupervisor" class="modal-supervisor-close">&times;</button>
            </div>
            <div class="modal-supervisor-body">
                <!-- Chips de supervisores actuales -->
                <div id="supervisoresChips" class="supervisores-chips-container"></div>

                <!-- Buscador autocomplete -->
                <div class="supervisor-search-wrap">
                    <i class="fas fa-search supervisor-search-icon"></i>
                    <input type="text" id="inputBuscarSupervisor" placeholder="Buscar colaborador por nombre…" autocomplete="off" class="supervisor-search-input">
                    <ul id="supervisorDropdown" class="supervisor-dropdown"></ul>
                </div>
                <p class="supervisor-hint">Escribe un nombre para buscar. Haz clic en un resultado para agregar como supervisor.</p>
            </div>
            <div class="modal-supervisor-footer">
                <button type="button" id="btnCancelarSupervisor" class="btn-modal-cancelar">Cancelar</button>
                <button type="button" id="btnGuardarSupervisor" class="btn-modal-guardar">
                    <i class="fas fa-save"></i> Guardar supervisores
                </button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const tienePermisoEditarSupervisor = document.body.dataset.tienePermisoEditarSupervisor === '1';
        if (!tienePermisoEditarSupervisor) return;

        let currentCodSucursal = null;
        let currentSupervisores = []; // [{id, nombre}]
        let searchTimer = null;

        const modal = document.getElementById('modalSupervisor');
        const modalNombre = document.getElementById('modalSucursalNombre');
        const chipsContainer = document.getElementById('supervisoresChips');
        const inputBuscar = document.getElementById('inputBuscarSupervisor');
        const dropdown = document.getElementById('supervisorDropdown');
        const btnCerrar = document.getElementById('btnCerrarModalSupervisor');
        const btnCancelar = document.getElementById('btnCancelarSupervisor');
        const btnGuardar = document.getElementById('btnGuardarSupervisor');

        // Abrir modal al hacer clic en el lápiz
        document.querySelectorAll('.btn-edit-supervisor').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                currentCodSucursal = this.dataset.codSucursal;
                modalNombre.textContent = this.dataset.nombreSucursal;
                try {
                    currentSupervisores = JSON.parse(this.dataset.supervisores) || [];
                } catch(ex) {
                    currentSupervisores = [];
                }
                renderChips();
                inputBuscar.value = '';
                dropdown.innerHTML = '';
                dropdown.style.display = 'none';
                modal.style.display = 'flex';
                setTimeout(() => inputBuscar.focus(), 100);
            });
        });

        function cerrarModal() {
            modal.style.display = 'none';
            currentCodSucursal = null;
        }

        btnCerrar.addEventListener('click', cerrarModal);
        btnCancelar.addEventListener('click', cerrarModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) cerrarModal();
        });

        // Renderizar chips
        function renderChips() {
            chipsContainer.innerHTML = '';
            if (currentSupervisores.length === 0) {
                chipsContainer.innerHTML = '<span class="supervisor-chip-empty">Sin supervisores asignados</span>';
                return;
            }
            currentSupervisores.forEach(function(sup) {
                const chip = document.createElement('span');
                chip.className = 'supervisor-chip';
                chip.dataset.id = sup.id;
                chip.innerHTML = '<i class="fas fa-user-tie"></i> ' + escapeHtml(sup.nombre) +
                    ' <button type="button" class="chip-remove" data-id="' + sup.id + '" title="Quitar">&times;</button>';
                chipsContainer.appendChild(chip);
            });

            // Listeners para quitar
            chipsContainer.querySelectorAll('.chip-remove').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = parseInt(this.dataset.id);
                    currentSupervisores = currentSupervisores.filter(s => s.id !== id);
                    renderChips();
                });
            });
        }

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        // Autocomplete
        inputBuscar.addEventListener('input', function() {
            const query = this.value.trim();
            clearTimeout(searchTimer);
            if (query.length < 1) {
                dropdown.innerHTML = '';
                dropdown.style.display = 'none';
                return;
            }
            searchTimer = setTimeout(() => buscarColaboradores(query), 250);
        });

        function buscarColaboradores(query) {
            fetch('ajax/buscar_colaboradores.php?q=' + encodeURIComponent(query))
                .then(r => r.json())
                .then(function(data) {
                    dropdown.innerHTML = '';
                    if (!data.length) {
                        dropdown.innerHTML = '<li class="supervisor-dropdown-empty">Sin resultados</li>';
                        dropdown.style.display = 'block';
                        return;
                    }
                    data.forEach(function(op) {
                        const li = document.createElement('li');
                        li.className = 'supervisor-dropdown-item';
                        li.dataset.id = op.id;
                        li.dataset.nombre = op.nombre;
                        // Indicar si ya está agregado
                        const yaAgregado = currentSupervisores.some(s => s.id === op.id);
                        if (yaAgregado) li.classList.add('ya-agregado');
                        li.innerHTML = '<i class="fas fa-user"></i> ' + escapeHtml(op.nombre) +
                            (yaAgregado ? ' <span class="chip-ya">(ya agregado)</span>' : '');
                        li.addEventListener('click', function() {
                            if (yaAgregado) return;
                            currentSupervisores.push({ id: op.id, nombre: op.nombre });
                            renderChips();
                            inputBuscar.value = '';
                            dropdown.innerHTML = '';
                            dropdown.style.display = 'none';
                        });
                        dropdown.appendChild(li);
                    });
                    dropdown.style.display = 'block';
                })
                .catch(() => {
                    dropdown.innerHTML = '';
                    dropdown.style.display = 'none';
                });
        }

        // Cerrar dropdown al clic fuera
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.supervisor-search-wrap')) {
                dropdown.innerHTML = '';
                dropdown.style.display = 'none';
            }
        });

        // Guardar
        btnGuardar.addEventListener('click', function() {
            if (!currentCodSucursal) return;
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';

            const ids = currentSupervisores.map(s => s.id);

            fetch('ajax/guardar_supervisores.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cod_sucursal: currentCodSucursal, supervisores: ids })
            })
            .then(r => r.json())
            .then(function(data) {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar supervisores';
                if (data.success) {
                    // Actualizar display en la tarjeta sin recargar
                    const displayEl = document.querySelector('.supervisor-display[data-cod-sucursal="' + currentCodSucursal + '"]');
                    if (displayEl) {
                        if (currentSupervisores.length === 0) {
                            displayEl.innerHTML = '<i class="fas fa-user-tie"></i> Sin supervisor asignado';
                            displayEl.style.fontStyle = 'italic';
                        } else {
                            const label = currentSupervisores.length > 1 ? 'Supervisores' : 'Supervisor';
                            const nombres = currentSupervisores.map(s => s.nombre).join(', ');
                            displayEl.innerHTML = '<i class="fas fa-user-tie"></i> ' + label + ': ' + escapeHtml(nombres);
                            displayEl.style.fontStyle = 'normal';
                        }
                    }
                    // Actualizar el data del botón lápiz para reflejar el nuevo estado
                    const btnEdit = document.querySelector('.btn-edit-supervisor[data-cod-sucursal="' + currentCodSucursal + '"]');
                    if (btnEdit) {
                        btnEdit.dataset.supervisores = JSON.stringify(currentSupervisores);
                    }
                    cerrarModal();
                } else {
                    alert(data.error || 'Error al guardar.');
                }
            })
            .catch(function() {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar supervisores';
                alert('Error de conexión.');
            });
        });
    })();
    </script>
    
    <script>
    (function () {
        const btnExportar = document.getElementById('btnExportarFab');
        if (btnExportar) {
            btnExportar.addEventListener('click', function() {
                const datos = <?= json_encode($datosExportar, JSON_UNESCAPED_UNICODE) ?>;
                if (!datos || datos.length === 0) {
                    alert('No hay datos para exportar en esta semana.');
                    return;
                }

                // Convertir a CSV
                const separador = ',';
                const llaves = Object.keys(datos[0]);
                
                const csv = [
                    llaves.join(separador),
                    ...datos.map(fila => llaves.map(llave => {
                        let valor = fila[llave] || '';
                        valor = String(valor).replace(/"/g, '""');
                        return `"${valor}"`;
                    }).join(separador))
                ].join('\r\n');

                // Crear y descargar archivo (incluye BOM para Excel)
                const blob = new Blob(["\ufeff", csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.setAttribute('href', url);
                link.setAttribute('download', 'Colaboradores_Equipos_Tiendas.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }
    })();
    </script>
    
    <!-- Botón Flotante con opciones -->
    <?php if ($tienePermisoExportar): ?>
        <div class="fab-container">
            <div class="fab-options">
                <div class="fab-option" id="btnExportarFab">
                    <span class="fab-label">Exportar CSV</span>
                    <div class="fab-icon-holder"><i class="fas fa-file-excel"></i></div>
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