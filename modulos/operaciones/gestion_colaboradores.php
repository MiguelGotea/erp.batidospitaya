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

// Obtener cargo principal
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Determinar la semana a mostrar (actual o siguiente)
$tipoSemana = isset($_GET['semana']) && $_GET['semana'] === 'siguiente' ? 'siguiente' : 'actual';

// Obtener las semanas del sistema
$semanaActual = obtenerSemanaActual();
$semanasDisponibles = obtenerSemanasDisponibles();

// Encontrar la semana siguiente
$semanaSiguiente = null;
$hoy = date('Y-m-d');
foreach ($semanasDisponibles as $semana) {
    if ($semana['fecha_inicio'] > $hoy) {
        $semanaSiguiente = $semana;
        break;
    }
}

// Determinar qué semana mostrar
$semanaMostrar = ($tipoSemana === 'siguiente' && $semanaSiguiente) ? $semanaSiguiente : $semanaActual;

// Si es la semana siguiente, verificar que existe
if ($tipoSemana === 'siguiente' && !$semanaSiguiente) {
    $tipoSemana = 'actual';
    $semanaMostrar = $semanaActual;
}

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
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
        AND o.Operativo = 1
    ");

    $stmt->execute([$codSucursal, $fechaInicioSemana, $fechaFinSemana]);
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
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
        AND o.Operativo = 1
        AND s.activa = 1
        AND s.sucursal = 1
    ");

    $stmt->execute([$fechaInicioSemana, $fechaFinSemana]);
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
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
        AND o.Operativo = 1
        ORDER BY 
            CASE 
                WHEN anc.CodNivelesCargos IN (5, 43) THEN 1  -- Líderes primero
                ELSE 2 
            END,
            o.Nombre, o.Apellido
    ");

    $stmt->execute([$codSucursal, $fechaInicioSemana, $fechaFinSemana]);
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
            -- Obtener el último contrato
            (SELECT c.CodContrato
             FROM Contratos c
             WHERE c.cod_operario = o.CodOperario
             ORDER BY c.inicio_contrato DESC, c.CodContrato DESC 
             LIMIT 1) as ultimo_cod_contrato,
            -- Obtener el código manual del último contrato
            (SELECT c.codigo_manual_contrato
             FROM Contratos c
             WHERE c.cod_operario = o.CodOperario
             ORDER BY c.inicio_contrato DESC, c.CodContrato DESC 
             LIMIT 1) as ultimo_codigo_manual
        FROM Operarios o
        WHERE o.Operativo = 1
        AND o.CodOperario NOT IN (
            -- Excluir operarios que ya tienen asignación activa durante esta semana
            SELECT DISTINCT anc.CodOperario
            FROM AsignacionNivelesCargos anc
            WHERE anc.CodNivelesCargos IN (2, 5, 43, 44, 45, 46, 47)
            AND (anc.Fin IS NULL OR anc.Fin >= ?)
            AND anc.Fecha <= ?
        )
        ORDER BY o.Nombre, o.Apellido
    ");

    $stmt->execute([$fechaInicioSemana, $fechaFinSemana]);
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
    <link rel="stylesheet" href="css/gestion_colaboradores.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body data-tipo-semana="<?= htmlspecialchars($tipoSemana) ?>"
    data-tiene-permiso-editar="<?= $tienePermisoEditar ? '1' : '0' ?>"
    data-tiene-permiso-planificacion="<?= $tienePermisoPlanificacion ? '1' : '0' ?>">
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Colaboradores'); ?>

            <div class="container-fluid p-3">
                <div class="gestion-container">
                    <h1 class="title" style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                        Gestión de Colaboradores por Sucursal
                        <span class="global-counter"
                            style="font-size: 0.6em !important; background: #0E544C; color: white; padding: 4px 12px; border-radius: 20px; vertical-align: middle;">
                            Total: <?= $totalColaboradoresGlobal ?> colaboradores
                        </span>
                    </h1>

                    <!-- Controles de semana -->
                    <div class="week-controls">
                        <a href="gestion_colaboradores.php?semana=actual"
                            class="week-btn actual <?= $tipoSemana === 'actual' ? 'active' : '' ?>">
                            <i class="fas fa-calendar-week"></i> Semana Actual
                        </a>

                        <div class="week-info">
                            <div class="week-number">Semana <?= $semanaMostrar['numero_semana'] ?? 'N/A' ?></div>
                            <div class="week-dates">
                                <?= formatoFecha($semanaMostrar['fecha_inicio'] ?? '') ?> -
                                <?= formatoFecha($semanaMostrar['fecha_fin'] ?? '') ?>
                            </div>
                        </div>

                        <?php if ($semanaSiguiente): ?>
                            <a href="gestion_colaboradores.php?semana=siguiente"
                                class="week-btn siguiente <?= $tipoSemana === 'siguiente' ? 'active' : '' ?>">
                                <i class="fas fa-calendar-alt"></i> Semana Siguiente
                            </a>
                        <?php else: ?>
                            <button class="week-btn siguiente disabled" disabled>
                                <i class="fas fa-calendar-alt"></i> No hay semana siguiente
                            </button>
                        <?php endif; ?>
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
                                                <div class="sucursal-header">
                                                    <?= htmlspecialchars($sucursal['nombre']) ?>
                                                    <div style="float: right; display: flex; align-items: center; gap: 8px;">
                                                        <span class="sucursal-counter"
                                                            style="background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.85em;">
                                                            <?= $totalesPorSucursal[$sucursal['codigo']] ?? 0 ?>
                                                        </span>
                                                        <small
                                                            style="opacity: 0.8; display:none;">#<?= $sucursal['codigo'] ?></small>
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
                                                                    data-cargo="<?= $lider['cod_cargo'] ?>">
                                                                    <div class="item-info">
                                                                        <div class="item-name"><?= htmlspecialchars($lider['nombre']) ?>
                                                                        </div>
                                                                        <div class="item-cargo">
                                                                            <?= htmlspecialchars($lider['cargo_nombre']) ?>
                                                                        </div>
                                                                    </div>
                                                                    <span class="item-badge"><?= $lider['codigo'] ?></span>
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
                                                            <i class="fas fa-users"></i> Colaboradores
                                                        </div>
                                                        <div class="drag-area colaboradores 
                                                <?= $tipoSemana === 'siguiente' ? 'sortable-colaboradores' : '' ?>"
                                                            data-sucursal="<?= $sucursal['codigo'] ?>" data-tipo="colaboradores">
                                                            <?php foreach ($colaboradores['colaboradores'] as $colaborador): ?>
                                                                <div class="drag-item colaborador"
                                                                    data-id="<?= $colaborador['codigo'] ?>"
                                                                    data-cargo="<?= $colaborador['cod_cargo'] ?>">
                                                                    <div class="item-info">
                                                                        <div class="item-name">
                                                                            <?= htmlspecialchars($colaborador['nombre']) ?>
                                                                        </div>
                                                                        <div class="item-cargo">
                                                                            <?= htmlspecialchars($colaborador['cargo_nombre']) ?>
                                                                        </div>
                                                                    </div>
                                                                    <span class="item-badge"><?= $colaborador['codigo'] ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <?php if (empty($colaboradores['colaboradores'])): ?>
                                                                <div style="text-align: center; color: #999; padding: 10px;">
                                                                    Sin colaboradores asignados
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
                                                data-cargo="<?= $colaborador['cod_cargo'] ?>">
                                                <div class="item-info">
                                                    <div class="item-name"><?= htmlspecialchars($colaborador['nombre']) ?></div>
                                                    <div class="item-cargo">
                                                        <?= htmlspecialchars($colaborador['cargo_nombre']) ?>
                                                    </div>
                                                </div>
                                                <span class="item-badge">#<?= $colaborador['codigo'] ?></span>
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
</body>

</html>