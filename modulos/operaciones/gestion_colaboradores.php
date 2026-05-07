<?php
require_once '../../core/auth/auth.php';

verificarAutenticacion();

// Verificar acceso al módulo (RH y admin)
verificarAccesoCargo([13, 16, 39, 30, 37, 49, 8, 42, 39]);

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

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

// Procesar movimientos (solo para semana siguiente)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tipoSemana === 'siguiente') {
    // Verificar permisos para editar
    if (!verificarAccesoCargo([13, 16]) && !$esAdmin) {
        header('Location: gestion_colaboradores.php');
        exit();
    }

    if (isset($_POST['movimientos']) && is_array($_POST['movimientos'])) {
        foreach ($_POST['movimientos'] as $movimiento) {
            $codOperario = intval($movimiento['cod_operario']);
            $codSucursalDestino = intval($movimiento['cod_sucursal_destino']);
            $codCargo = intval($movimiento['cod_cargo']);

            // Validar datos
            if ($codOperario > 0 && $codSucursalDestino > 0 && in_array($codCargo, [2, 5, 43, 44, 45, 46, 47])) {
                // Obtener el último contrato del operario
                $stmtContrato = $conn->prepare("
                    SELECT 
                        CodContrato,
                        codigo_manual_contrato
                    FROM Contratos 
                    WHERE cod_operario = ? 
                    ORDER BY inicio_contrato DESC, CodContrato DESC 
                    LIMIT 1
                ");
                $stmtContrato->execute([$codOperario]);
                $contrato = $stmtContrato->fetch();

                $codContrato = $contrato['CodContrato'] ?? null;
                $codigoManualContrato = $contrato['codigo_manual_contrato'] ?? null;

                // Obtener la última asignación activa del operario
                $stmtUltima = $conn->prepare("
                    SELECT CodAsignacionNivelesCargos, Sucursal, Fin
                    FROM AsignacionNivelesCargos
                    WHERE CodOperario = ?
                    AND CodNivelesCargos = ?
                    AND (Fin IS NULL OR Fin >= CURDATE())
                    ORDER BY Fecha DESC, CodAsignacionNivelesCargos DESC
                    LIMIT 1
                ");
                $stmtUltima->execute([$codOperario, $codCargo]);
                $ultimaAsignacion = $stmtUltima->fetch();

                // Si hay una asignación activa, cerrarla un día antes del inicio de la semana siguiente
                if ($ultimaAsignacion) {
                    $fechaFin = date('Y-m-d', strtotime($semanaSiguiente['fecha_inicio'] . ' -1 day'));

                    $stmtCerrar = $conn->prepare("
                        UPDATE AsignacionNivelesCargos
                        SET Fin = ?,
                            fecha_ultima_modificacion = NOW(),
                            usuario_ultima_modificacion = ?
                        WHERE CodAsignacionNivelesCargos = ?
                    ");
                    $stmtCerrar->execute([$fechaFin, $_SESSION['usuario_id'], $ultimaAsignacion['CodAsignacionNivelesCargos']]);
                }

                // Crear nueva asignación con los datos del contrato
                $stmtNueva = $conn->prepare("
                    INSERT INTO AsignacionNivelesCargos (
                        CodOperario,
                        CodNivelesCargos,
                        Fecha,
                        Sucursal,
                        Fin,
                        CodContrato,
                        codigo_contrato_asociado,
                        fecha_hora_regsys,
                        fecha_ultima_modificacion,
                        usuario_ultima_modificacion,
                        cod_usuario_creador,
                        es_activo
                    ) VALUES (?, ?, ?, ?, NULL, ?, ?, NOW(), NOW(), ?, ?, 1)
                ");
                $stmtNueva->execute([
                    $codOperario,
                    $codCargo,
                    $semanaSiguiente['fecha_inicio'],  // Inicia con la semana siguiente
                    $codSucursalDestino,
                    $codContrato,                      // CodContrato del último contrato
                    $codigoManualContrato,             // codigo_manual_contrato del último contrato
                    $_SESSION['usuario_id'],
                    $_SESSION['usuario_id']
                ]);
            }
        }

        $_SESSION['exito'] = "Movimientos guardados exitosamente.";
        header('Location: gestion_colaboradores.php?semana=siguiente');
        exit();
    }
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
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
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

        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
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

        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Controles de semana */
        .week-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .week-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .week-btn.actual {
            background-color:
                <?= $tipoSemana === 'actual' ? '#0E544C' : '#51B8AC' ?>
            ;
            color: white;
        }

        .week-btn.siguiente {
            background-color:
                <?= $tipoSemana === 'siguiente' ? '#0E544C' : '#51B8AC' ?>
            ;
            color: white;
        }

        .week-btn:hover:not(.disabled) {
            background-color: #0E544C;
            transform: translateY(-2px);
        }

        .week-btn.disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .week-info {
            text-align: center;
            font-weight: bold;
            color: #333;
        }

        .week-info .week-number {
            color: #0E544C;
            font-size: 1.2em;
        }

        .week-info .week-dates {
            font-size: 0.9em;
            color: #666;
        }

        /* Grid de sucursales */
        .departamento-section {
            margin-bottom: 40px;
        }

        .departamento-title {
            color: #0E544C;
            font-size: 1.3rem !important;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #51B8AC;
        }

        .sucursales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        /* Tarjeta de sucursal */
        .sucursal-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .sucursal-card:hover {
            border-color: #51B8AC;
            box-shadow: 0 4px 10px rgba(81, 184, 172, 0.2);
        }

        .sucursal-header {
            background: linear-gradient(135deg, #0E544C, #51B8AC);
            color: white;
            padding: 12px 15px;
            font-weight: bold;
            font-size: 1.1em;
        }

        .sucursal-body {
            padding: 15px;
        }

        /* Secciones dentro de la tarjeta */
        .lideres-section,
        .colaboradores-section,
        .no-asignados-section {
            margin-bottom: 20px;
        }

        .section-title {
            color: #0E544C;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 0.9em;
            padding-bottom: 5px;
            border-bottom: 1px dashed #ddd;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Áreas de arrastre */
        .drag-area {
            min-height: 60px;
            border: 2px dashed #ddd;
            border-radius: 5px;
            padding: 10px;
            transition: all 0.3s;
        }

        .drag-area.lideres {
            min-height: 40px;
            background-color: rgba(81, 184, 172, 0.05);
        }

        .drag-area.colaboradores {
            min-height: 120px;
            background-color: rgba(14, 84, 76, 0.03);
        }

        .drag-area.no-asignados {
            min-height: 150px;
            background-color: rgba(255, 193, 7, 0.05);
        }

        .drag-area.drag-over {
            border-color: #51B8AC;
            background-color: rgba(81, 184, 172, 0.1);
        }

        /* Elementos arrastrables */
        .drag-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 8px;
            cursor: move;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .drag-item:hover {
            border-color: #51B8AC;
            box-shadow: 0 2px 5px rgba(81, 184, 172, 0.2);
        }

        .drag-item.lider {
            background: linear-gradient(135deg, rgba(81, 184, 172, 0.1), rgba(14, 84, 76, 0.05));
            border-left: 4px solid #0E544C;
        }

        .drag-item.colaborador {
            border-left: 4px solid #51B8AC;
        }

        .drag-item.no-asignado {
            border-left: 4px solid #ffc107;
        }

        .drag-item.grabbing {
            opacity: 0.7;
            transform: rotate(2deg);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .item-info {
            flex-grow: 1;
        }

        .item-name {
            font-weight: 600;
            color: #333;
        }

        .item-cargo {
            font-size: 0.8em;
            color: #666;
        }

        .item-badge {
            background: #0E544C;
            color: white;
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
        }

        /* Botones de acción */
        .actions-bar {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .btn-guardar {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-guardar:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-guardar:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-reset {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-reset:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        /* Mensajes */
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Información de ayuda */
        .help-info {
            background: #e7f6f4;
            border: 1px solid #51B8AC;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .help-info h4 {
            color: #0E544C;
            margin-bottom: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sucursales-grid {
                grid-template-columns: 1fr;
            }

            .week-controls {
                flex-direction: column;
                gap: 10px;
            }

            .drag-area {
                min-height: 50px;
            }

            .drag-area.colaboradores {
                min-height: 100px;
            }
        }

        /* Estado visual */
        .read-only .drag-item {
            cursor: default;
        }

        .read-only .drag-item:hover {
            border-color: #ddd;
            box-shadow: none;
        }

        /* Estilos para contadores */
        .global-counter {
            font-size: 0.8em !important;
            background: #0E544C;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            margin-left: 10px;
            vertical-align: middle;
        }

        .sucursal-counter {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.85em;
            font-weight: bold;
            min-width: 24px;
            text-align: center;
        }

        /* Asegurar que el header de la sucursal tenga suficiente espacio */
        .sucursal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #0E544C, #51B8AC);
            color: white;
            padding: 12px 15px;
            font-weight: bold;
            font-size: 1.1em;
        }

        .sucursal-header>div {
            display: flex;
            align-items: center;
            gap: 8px;
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
                    <a href="index.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </header>

        <h1 class="title">
            Gestión de Colaboradores por Sucursal
            <span class="global-counter"
                style="font-size: 0.8em; background: #0E544C; color: white; padding: 4px 12px; border-radius: 20px; margin-left: 10px;">
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
                <i class="fas fa-user-shield"></i> <strong>Líderes:</strong> Máximo 2 por sucursal (cargos 5 o 43)<br>
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
        <form id="movimientosForm" method="post" action="gestion_colaboradores.php?semana=siguiente">
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
                                            <small style="opacity: 0.8; display:none;">#<?= $sucursal['codigo'] ?></small>
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
                                                data-sucursal="<?= $sucursal['codigo'] ?>" data-tipo="lideres" data-max="2">
                                                <?php foreach ($colaboradores['lideres'] as $lider): ?>
                                                    <div class="drag-item lider" data-id="<?= $lider['codigo'] ?>"
                                                        data-cargo="<?= $lider['cod_cargo'] ?>">
                                                        <div class="item-info">
                                                            <div class="item-name"><?= htmlspecialchars($lider['nombre']) ?></div>
                                                            <div class="item-cargo"><?= htmlspecialchars($lider['cargo_nombre']) ?>
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
                                                    <div class="drag-item colaborador" data-id="<?= $colaborador['codigo'] ?>"
                                                        data-cargo="<?= $colaborador['cod_cargo'] ?>">
                                                        <div class="item-info">
                                                            <div class="item-name"><?= htmlspecialchars($colaborador['nombre']) ?></div>
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
                                        <div class="item-cargo"><?= htmlspecialchars($colaborador['cargo_nombre']) ?></div>
                                    </div>
                                    <span class="item-badge">#<?= $colaborador['codigo'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Botones de acción (solo para semana siguiente) -->
            <?php if ($tipoSemana === 'siguiente'): ?>
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

    <script>
        // Función para actualizar los contadores
        function actualizarContadores() {
            // Actualizar contador global
            let totalGlobal = 0;

            // Actualizar contadores por sucursal
            document.querySelectorAll('.sucursal-card').forEach(function (card) {
                const sucursalId = card.dataset.sucursalId;

                // Contar líderes
                const areaLideres = card.querySelector('.sortable-lideres');
                let totalLideres = 0;
                if (areaLideres) {
                    totalLideres = areaLideres.querySelectorAll('.drag-item').length;
                }

                // Contar colaboradores
                const areaColaboradores = card.querySelector('.sortable-colaboradores');
                let totalColaboradores = 0;
                if (areaColaboradores) {
                    totalColaboradores = areaColaboradores.querySelectorAll('.drag-item').length;
                }

                // Total de la sucursal
                const totalSucursal = totalLideres + totalColaboradores;

                // Actualizar contador en la tarjeta
                const counter = card.querySelector('.sucursal-counter');
                if (counter) {
                    counter.textContent = totalSucursal;
                }

                // Sumar al total global
                totalGlobal += totalSucursal;
            });

            // Contar no asignados (solo en semana siguiente)
            const areaNoAsignados = document.querySelector('.sortable-no-asignados');
            if (areaNoAsignados) {
                totalGlobal += areaNoAsignados.querySelectorAll('.drag-item').length;
            }

            // Actualizar contador global
            const globalCounter = document.querySelector('.global-counter');
            if (globalCounter) {
                globalCounter.textContent = 'Total: ' + totalGlobal + ' colaboradores';
            }
        }

        // Estado inicial para restaurar
        let estadoInicial = null;

        <?php if ($tipoSemana === 'siguiente'): ?>
            // Inicializar Sortable solo para semana siguiente
            document.addEventListener('DOMContentLoaded', function () {
                // Guardar estado inicial
                guardarEstadoInicial();

                // Inicializar contadores
                actualizarContadores();

                // Inicializar áreas de arrastre para líderes
                document.querySelectorAll('.sortable-lideres').forEach(function (el) {
                    new Sortable(el, {
                        group: {
                            name: 'lideres',
                            put: function (to, from, item) {
                                // Validar que solo se puedan mover líderes (cargos 5 o 43)
                                const cargo = parseInt(item.dataset.cargo);
                                if (cargo !== 5 && cargo !== 43) {
                                    return false;
                                }

                                // Validar máximo de 2 líderes
                                if (to.el.children.length >= parseInt(to.el.dataset.max)) {
                                    alert('Máximo ' + to.el.dataset.max + ' líderes por sucursal');
                                    return false;
                                }

                                return true;
                            }
                        },
                        animation: 150,
                        ghostClass: 'grabbing',
                        onEnd: function (evt) {
                            actualizarMovimientos();
                            actualizarContadores();
                        }
                    });
                });

                // Inicializar áreas de arrastre para colaboradores generales
                document.querySelectorAll('.sortable-colaboradores').forEach(function (el) {
                    new Sortable(el, {
                        group: {
                            name: 'colaboradores',
                            put: function (to, from, item) {
                                // Validar que no sean líderes
                                const cargo = parseInt(item.dataset.cargo);
                                if (cargo === 5 || cargo === 43) {
                                    return false;
                                }
                                return true;
                            }
                        },
                        animation: 150,
                        ghostClass: 'grabbing',
                        onEnd: function (evt) {
                            actualizarMovimientos();
                            actualizarContadores();
                        }
                    });
                });

                // Inicializar área de no asignados
                if (document.querySelector('.sortable-no-asignados')) {
                    new Sortable(document.querySelector('.sortable-no-asignados'), {
                        group: {
                            name: 'colaboradores',
                            put: true,
                            pull: true
                        },
                        animation: 150,
                        ghostClass: 'grabbing',
                        onEnd: function (evt) {
                            actualizarMovimientos();
                            actualizarContadores();
                        }
                    });
                }
            });

            // Guardar estado inicial
            function guardarEstadoInicial() {
                estadoInicial = {};

                document.querySelectorAll('.sucursal-card').forEach(function (card) {
                    const sucursalId = card.dataset.sucursalId;
                    estadoInicial[sucursalId] = {
                        lideres: [],
                        colaboradores: []
                    };

                    // Guardar líderes
                    const areaLideres = card.querySelector('.sortable-lideres');
                    if (areaLideres) {
                        areaLideres.querySelectorAll('.drag-item').forEach(function (item) {
                            estadoInicial[sucursalId].lideres.push({
                                id: item.dataset.id,
                                cargo: item.dataset.cargo
                            });
                        });
                    }

                    // Guardar colaboradores
                    const areaColaboradores = card.querySelector('.sortable-colaboradores');
                    if (areaColaboradores) {
                        areaColaboradores.querySelectorAll('.drag-item').forEach(function (item) {
                            estadoInicial[sucursalId].colaboradores.push({
                                id: item.dataset.id,
                                cargo: item.dataset.cargo
                            });
                        });
                    }
                });

                // Guardar no asignados
                const areaNoAsignados = document.querySelector('.sortable-no-asignados');
                if (areaNoAsignados) {
                    estadoInicial['no_asignados'] = [];
                    areaNoAsignados.querySelectorAll('.drag-item').forEach(function (item) {
                        estadoInicial['no_asignados'].push({
                            id: item.dataset.id,
                            cargo: item.dataset.cargo
                        });
                    });
                }
            }

            // Restaurar estado inicial
            function restaurarEstadoInicial() {
                if (!estadoInicial) return;

                if (confirm('¿Está seguro de restaurar todas las asignaciones originales? Se perderán los cambios no guardados.')) {
                    // Limpiar todas las áreas
                    document.querySelectorAll('.sortable-lideres, .sortable-colaboradores, .sortable-no-asignados').forEach(function (area) {
                        area.innerHTML = '';
                    });

                    // Restaurar por sucursal
                    Object.keys(estadoInicial).forEach(function (sucursalId) {
                        if (sucursalId === 'no_asignados') return;

                        const card = document.querySelector(`.sucursal-card[data-sucursal-id="${sucursalId}"]`);
                        if (!card) return;

                        // Restaurar líderes
                        const areaLideres = card.querySelector('.sortable-lideres');
                        if (areaLideres && estadoInicial[sucursalId].lideres) {
                            estadoInicial[sucursalId].lideres.forEach(function (lider) {
                                // Aquí deberías recrear el elemento del líder
                                // Esto es un ejemplo simplificado
                            });
                        }

                        // Restaurar colaboradores
                        const areaColaboradores = card.querySelector('.sortable-colaboradores');
                        if (areaColaboradores && estadoInicial[sucursalId].colaboradores) {
                            estadoInicial[sucursalId].colaboradores.forEach(function (colaborador) {
                                // Aquí deberías recrear el elemento del colaborador
                            });
                        }
                    });

                    // Restaurar no asignados
                    const areaNoAsignados = document.querySelector('.sortable-no-asignados');
                    if (areaNoAsignados && estadoInicial['no_asignados']) {
                        estadoInicial['no_asignados'].forEach(function (colaborador) {
                            // Recrear elemento
                        });
                    }

                    actualizarMovimientos();
                    actualizarContadores();
                    alert('Estado restaurado correctamente');
                }
            }

            // Actualizar datos de movimientos en el formulario
            function actualizarMovimientos() {
                const movimientos = [];

                // Recorrer todas las sucursales
                document.querySelectorAll('.sucursal-card').forEach(function (card) {
                    const sucursalId = card.dataset.sucursalId;

                    // Procesar líderes
                    const areaLideres = card.querySelector('.sortable-lideres');
                    if (areaLideres) {
                        areaLideres.querySelectorAll('.drag-item').forEach(function (item) {
                            movimientos.push({
                                cod_operario: item.dataset.id,
                                cod_sucursal_destino: sucursalId,
                                cod_cargo: item.dataset.cargo
                            });
                        });
                    }

                    // Procesar colaboradores
                    const areaColaboradores = card.querySelector('.sortable-colaboradores');
                    if (areaColaboradores) {
                        areaColaboradores.querySelectorAll('.drag-item').forEach(function (item) {
                            movimientos.push({
                                cod_operario: item.dataset.id,
                                cod_sucursal_destino: sucursalId,
                                cod_cargo: item.dataset.cargo
                            });
                        });
                    }
                });

                // Actualizar campo oculto del formulario
                const movimientosData = document.getElementById('movimientosData');
                movimientosData.innerHTML = '';

                movimientos.forEach(function (mov, index) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `movimientos[${index}][cod_operario]`;
                    input.value = mov.cod_operario;
                    movimientosData.appendChild(input);

                    const input2 = document.createElement('input');
                    input2.type = 'hidden';
                    input2.name = `movimientos[${index}][cod_sucursal_destino]`;
                    input2.value = mov.cod_sucursal_destino;
                    movimientosData.appendChild(input2);

                    const input3 = document.createElement('input');
                    input3.type = 'hidden';
                    input3.name = `movimientos[${index}][cod_cargo]`;
                    input3.value = mov.cod_cargo;
                    movimientosData.appendChild(input3);
                });
            }

            // Validar antes de guardar
            function validarAntesDeGuardar() {
                let errores = [];

                // Verificar que no haya líderes duplicados en la misma sucursal
                document.querySelectorAll('.sucursal-card').forEach(function (card) {
                    const areaLideres = card.querySelector('.sortable-lideres');
                    if (areaLideres) {
                        const liderIds = [];
                        areaLideres.querySelectorAll('.drag-item').forEach(function (item) {
                            if (liderIds.includes(item.dataset.id)) {
                                errores.push(`El líder #${item.dataset.id} está duplicado en una sucursal`);
                            }
                            liderIds.push(item.dataset.id);
                        });
                    }
                });

                return errores;
            }

            // Configurar botones
            document.getElementById('btnReset')?.addEventListener('click', restaurarEstadoInicial);
            document.getElementById('btnGuardar')?.addEventListener('click', function (e) {
                e.preventDefault();

                const errores = validarAntesDeGuardar();
                if (errores.length > 0) {
                    alert('Errores encontrados:\n\n' + errores.join('\n'));
                    return;
                }

                if (confirm('¿Está seguro de guardar los cambios? Las nuevas asignaciones se aplicarán desde la semana siguiente.')) {
                    actualizarMovimientos();
                    document.getElementById('movimientosForm').submit();
                }
            });

        <?php else: ?>
            // Para semana actual, deshabilitar interactividad
            document.addEventListener('DOMContentLoaded', function () {
                document.body.classList.add('read-only');
            });
        <?php endif; ?>
    </script>
</body>

</html>