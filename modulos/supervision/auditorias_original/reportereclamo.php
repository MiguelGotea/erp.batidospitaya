<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/menu_lateral.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/layout/header_universal.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/permissions/permissions.php';

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo (Cargos permitidos: admin, gerencia, operaciones, supervisión)
if (!verificarAccesoCargo([49, 16, 11, 21, 42, 50]) && !$esAdmin) {
    header('Location: ../../../index.php');
    exit();
}

// Configuración de zona horaria
date_default_timezone_set('America/Managua');
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'es');

// Variables para controlar el modo de visualización
$modoSeleccionDirecta = isset($_GET['reclamo_id']);
$reclamoPreSeleccionado = null;
$detallesReclamo = null;

// Verificar si se pasó un reclamo_id por GET
if ($modoSeleccionDirecta) {
    $reclamoIdGet = intval($_GET['reclamo_id']);

    // Obtener información completa del reclamo
    $queryVerificar = "SELECT r.*, 
                      ri.tipo_reclamo_operaciones,
                      rg.nombre as grupo_nombre,
                      rt.nombre as tipo_nombre,
                      DATE_FORMAT(r.fecha_evento, '%d-%b-%y') as fecha_evento_formatted,
                      DATE_FORMAT(CONVERT_TZ(r.fecha_hora, '+00:00', '-06:00'), '%d-%b-%y %h:%i %p') as fecha_registro_formateada
                      FROM reclamos r 
                      LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id 
                      LEFT JOIN reclamos_grupos rg ON r.grupo_id = rg.id
                      LEFT JOIN reclamos_tipos rt ON r.tipo_reclamo_id = rt.id
                      WHERE r.id = :id AND ri.id IS NULL";

    $stmtVerificar = $conn->prepare($queryVerificar);
    $stmtVerificar->execute([':id' => $reclamoIdGet]);
    $reclamoPreSeleccionado = $stmtVerificar->fetch();

    if ($reclamoPreSeleccionado) {
        // Obtener productos del reclamo
        $queryProductos = "SELECT producto, precio FROM reclamos_productos WHERE reclamo_id = :id";
        $stmtProductos = $conn->prepare($queryProductos);
        $stmtProductos->execute([':id' => $reclamoIdGet]);
        $productos = $stmtProductos->fetchAll();

        // Obtener imágenes del reclamo
        $queryImagenes = "SELECT ruta_imagen FROM reclamos_imagenes WHERE reclamo_id = :id";
        $stmtImagenes = $conn->prepare($queryImagenes);
        $stmtImagenes->execute([':id' => $reclamoIdGet]);
        $imagenes = $stmtImagenes->fetchAll();

        // Verificar si el cargo actual tiene permiso para investigar este reclamo
        $puedeInvestigar = false;
        if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin') {
            $puedeInvestigar = true;
        } else {
            // Obtenemos el CodNivelesCargos del usuario actual
            $stmt_cargo_user = $conn->prepare("SELECT CodNivelesCargos FROM AsignacionNivelesCargos WHERE CodOperario = ? AND (Fin IS NULL OR Fin >= CURDATE()) LIMIT 1");
            $stmt_cargo_user->execute([$_SESSION['usuario_id']]);
            $codCargoUser = $stmt_cargo_user->fetchColumn();

            if ($codCargoUser) {
                // Verificar en la tabla de cargos responsables
                $queryResponsable = "SELECT count(*) FROM reclamos_cargos_responsables 
                                   WHERE cod_niveles_cargos = :cod_cargo 
                                   AND (grupo_id = :grupo_id OR tipo_id = :tipo_id)";
                $stmtResp = $conn->prepare($queryResponsable);
                $stmtResp->execute([
                    ':cod_cargo' => $codCargoUser,
                    ':grupo_id' => $reclamoPreSeleccionado['grupo_id'],
                    ':tipo_id' => $reclamoPreSeleccionado['tipo_reclamo_id']
                ]);
                if ($stmtResp->fetchColumn() > 0) {
                    $puedeInvestigar = true;
                }
            }
        }

        // Construir HTML de detalles del reclamo
        $detallesReclamo = '
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                <h4 class="fw-bold text-dark mb-0">Reclamo #' . $reclamoPreSeleccionado['id'] . '</h4>
                <span class="badge bg-warning text-dark">ABIERTO</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="small fw-bold text-muted text-uppercase mb-1">Fecha de Registro</label>
                        <div class="p-2 bg-light rounded border-start border-primary border-4">
                            ' . htmlspecialchars($reclamoPreSeleccionado['fecha_registro_formateada']) . '
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-bold text-muted text-uppercase mb-1">Medio de Compra</label>
                        <div class="p-2 bg-light rounded border-start border-primary border-4">
                            ' . htmlspecialchars($reclamoPreSeleccionado['medio_compra'] ?? '--') . '
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="small fw-bold text-muted text-uppercase mb-1">Sucursal</label>
                        <div class="p-2 bg-light rounded border-start border-primary border-4">
                            ' . htmlspecialchars($reclamoPreSeleccionado['sucursal']) . '
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-muted text-uppercase mb-1">Categoría de Reclamo</label>
                        <div class="p-2 bg-light rounded border-start border-primary border-4">
                            ' . (!empty($reclamoPreSeleccionado['grupo_nombre']) ?
            '<strong>' . htmlspecialchars($reclamoPreSeleccionado['grupo_nombre']) . '</strong> - ' . htmlspecialchars($reclamoPreSeleccionado['tipo_nombre']) :
            htmlspecialchars($reclamoPreSeleccionado['tipo_reclamo'] ?? '--')) . '
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold text-muted text-uppercase mb-1">Fecha y Hora del Evento</label>
                        <div class="p-2 bg-light rounded border-start border-primary border-4">
                            ' . traducirMes(date('d-M-Y', strtotime($reclamoPreSeleccionado['fecha_evento']))) . ' | ' . htmlspecialchars($reclamoPreSeleccionado['hora_evento']) . '
                        </div>
                    </div>
                </div>

                ' . (!$puedeInvestigar ? '
                    <div class="alert alert-warning border-0 shadow-sm mt-4 d-flex align-items-center" style="border-radius: 12px;">
                        <i class="fas fa-exclamation-triangle fs-4 me-3 text-warning"></i>
                        <div>
                            <strong>Atención:</strong> Su cargo no está autorizado para realizar la investigación final de este tipo de reclamo.
                        </div>
                    </div>' : '') . '

                <div class="mt-4">
                    <label class="small fw-bold text-muted text-uppercase mb-1">Investigación Preliminar</label>
                    <div class="p-3 bg-light rounded border-start border-info border-4">
                        ' . htmlspecialchars($reclamoPreSeleccionado['investigacion_preliminar'] ?? '--') . '
                    </div>
                </div>

                <div class="mt-4">
                    <label class="small fw-bold text-muted text-uppercase mb-1">Descripción</label>
                    <div class="p-3 bg-light rounded border-start border-info border-4">
                        ' . nl2br(htmlspecialchars($reclamoPreSeleccionado['descripcion'])) . '
                    </div>
                </div>';

        if (!empty($productos)) {
            $detallesReclamo .= '
                <div class="mt-4">
                    <label class="small fw-bold text-muted text-uppercase mb-1">Producto(s) en Reclamo</label>
                    <div class="table-responsive rounded border overflow-hidden">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3 py-2 border-0 small text-muted">Producto</th>
                                    <th class="pe-3 py-2 border-0 small text-muted text-end">Precio</th>
                                </tr>
                            </thead>
                            <tbody>';

            foreach ($productos as $producto) {
                $detallesReclamo .= '
                                <tr>
                                    <td class="ps-3 py-2 border-0">' . htmlspecialchars($producto['producto']) . '</td>
                                    <td class="pe-3 py-2 border-0 text-end">C$ ' . number_format($producto['precio'], 2) . '</td>
                                </tr>';
            }

            $detallesReclamo .= '
                            </tbody>
                        </table>
                    </div>
                </div>';
        }

        if (!empty($imagenes)) {
            $detallesReclamo .= '
                <div class="mt-4">
                    <label class="small fw-bold text-muted text-uppercase mb-1">Fotos de Evidencia</label>
                    <div class="galeria-imagenes">';

            foreach ($imagenes as $imagen) {
                $detallesReclamo .= '
                        <img src="' . htmlspecialchars($imagen['ruta_imagen']) . '" alt="Evidencia del reclamo" class="imagen-evidencia" onclick="mostrarImagenModal(this)">';
            }

            $detallesReclamo .= '
                    </div>
                </div>';
        }

        if (!empty($reclamoPreSeleccionado['accion_inmediata'])) {
            $detallesReclamo .= '
                <div class="mt-4">
                    <label class="small fw-bold text-muted text-uppercase mb-1">Acción Inmediata</label>
                    <div class="p-3 bg-light rounded border-start border-info border-4">
                        ' . nl2br(htmlspecialchars($reclamoPreSeleccionado['accion_inmediata'])) . '
                    </div>
                </div>';
        }

        $detallesReclamo .= '
            </div>
        </div>';

        // Prellenar datos del formulario si no hay datos POST
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $_SESSION['datos_formulario'] = [
                'reclamo_id' => $reclamoPreSeleccionado['id'],
                'reclamo_search' => 'Reclamo #' . $reclamoPreSeleccionado['id']
            ];
        }

        // $puedeInvestigar ya fue calculado antes de construir el HTML
    } else {
        // Si el reclamo no existe o ya tiene reporte, redirigir
        header("Location: reclamospend.php");
        exit();
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validar campos requeridos
        $camposRequeridos = [
            'reclamo_id' => 'Código de Reclamo',
            'colaboradores' => 'Colaboradores involucrados',
            'resolucion' => 'Resolución',
            'investigacion' => 'Investigación',
            'plan_accion' => 'Plan de acción'
        ];

        $errores = [];
        $datosFormulario = [];

        foreach ($camposRequeridos as $campo => $nombre) {
            if (empty($_POST[$campo])) {
                $errores[] = "El campo $nombre es requerido";
            } else {
                $datosFormulario[$campo] = $_POST[$campo];
            }
        }

        // Validar colaboradores (debe tener al menos uno)
        if (empty($_POST['colaboradores']) || empty(json_decode($_POST['colaboradores'], true))) {
            $errores[] = "Debe agregar al menos un colaborador";
        }

        // Si hay errores, mostrarlos
        if (!empty($errores)) {
            $_SESSION['errores'] = $errores;
            $_SESSION['datos_formulario'] = $datosFormulario;
            header("Location: {$_SERVER['PHP_SELF']}");
            exit();
        }

        $conn->beginTransaction();

        // 1. Insertar el reporte de investigación
        $query = "INSERT INTO reportes_investigacion (
            reclamo_id, fecha_resolucion, resolucion, investigacion, plan_accion, tipo_reclamo_operaciones
        ) VALUES (
            :reclamo_id, CURDATE(), :resolucion, :investigacion, :plan_accion, :tipo_reclamo_operaciones
        )";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':reclamo_id' => $_POST['reclamo_id'],
            ':resolucion' => $_POST['resolucion'],
            ':investigacion' => $_POST['investigacion'],
            ':plan_accion' => $_POST['plan_accion'],
            ':tipo_reclamo_operaciones' => $_POST['tipo_reclamo_operaciones'] ?? null
        ]);

        $reporteId = $conn->lastInsertId();

        // 2. Insertar colaboradores involucrados
        $colaboradores = json_decode($_POST['colaboradores'], true);
        $queryColaborador = "INSERT INTO reportes_colaboradores 
            (reporte_id, colaborador, monto_responsabilidad) 
            VALUES (:reporte_id, :colaborador, :monto)";
        $stmtColaborador = $conn->prepare($queryColaborador);

        foreach ($colaboradores as $colaborador) {
            $stmtColaborador->execute([
                ':reporte_id' => $reporteId,
                ':colaborador' => $colaborador['nombre'],
                ':monto' => $colaborador['monto']
            ]);
        }

        // 3. Actualizar KPI según la resolución
        $queryReclamo = "SELECT r.sucursal_codigo, 
                         MONTH(r.fecha_registro) as mes,
                         YEAR(r.fecha_registro) as anio 
                         FROM reclamos r 
                         WHERE r.id = :reclamo_id";

        $stmtReclamo = $conn->prepare($queryReclamo);
        $stmtReclamo->execute([':reclamo_id' => $_POST['reclamo_id']]);
        $reclamo = $stmtReclamo->fetch();

        if ($reclamo) {
            if ($_POST['resolucion'] !== 'Equipo de Tienda') {
                // RESTAR 1 solo si NO es "Equipo de Tienda"
                $queryKpi = "UPDATE kpi_reclamos 
                            SET reclamos_cantidad = GREATEST(reclamos_cantidad - 1, 0)
                            WHERE cod_sucursal = :cod_sucursal 
                            AND mes = :mes 
                            AND anio = :anio";

                $stmtKpi = $conn->prepare($queryKpi);
                $stmtKpi->execute([
                    ':cod_sucursal' => $reclamo['sucursal_codigo'],
                    ':mes' => $reclamo['mes'],
                    ':anio' => $reclamo['anio']
                ]);

                error_log("KPI actualizado (NO Equipo de Tienda) - Restando 1 - Código Sucursal: " . $reclamo['sucursal_codigo'] .
                    ", Mes: " . $reclamo['mes'] . ", Año: " . $reclamo['anio']);
            }
            // Si es "Equipo de Tienda", no hacemos nada (ya se sumó 1 en nuevoreclamo.php)
        }

        $conn->commit();

        // Éxito - redirigir a página de confirmación
        $_SESSION['reporte_exitoso'] = true;
        $_SESSION['reporte_id'] = $reporteId;
        header("Location: confirmacion_reporte.php");
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $_SESSION['errores'] = ["Ocurrió un error al procesar el reporte. Error: " . $e->getMessage()];
        $_SESSION['datos_formulario'] = $_POST;
        header("Location: {$_SERVER['PHP_SELF']}");
        exit();
    }
}

// Obtener reclamos pendientes de investigación (solo si no viene de selección directa)
if (!$modoSeleccionDirecta) {
    $queryReclamosPendientes = "SELECT r.id, 
                               DATE_FORMAT(r.fecha_evento, '%d-%b-%y') as fecha_evento_formatted, 
                               r.sucursal, 
                               r.sucursal_codigo,
                               r.descripcion, 
                               r.fecha_evento
                               FROM reclamos r 
                               LEFT JOIN reportes_investigacion ri ON r.id = ri.reclamo_id 
                               WHERE ri.id IS NULL 
                               ORDER BY r.fecha_evento DESC";
    $reclamosPendientes = $conn->query($queryReclamosPendientes)->fetchAll();
}

// Recuperar datos del formulario si hubo error
$datosFormulario = $_SESSION['datos_formulario'] ?? [];
$errores = $_SESSION['errores'] ?? [];
unset($_SESSION['datos_formulario'], $_SESSION['errores']);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Investigación Final | Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/modales_premium.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="/core/assets/css/fab_button.css">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Calibri', sans-serif;
        }



        /* Estilos específicos para este módulo */
        .search-results {
            position: absolute;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .search-result-item {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }

        .search-result-item:hover {
            background-color: #f8f9fa;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .selected-reclamo-container {
            display: none;
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #0D6EFD;
        }

        .selected-reclamo-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: #495057;
        }

        /* Ajustes para fotos de evidencia */
        .galeria-imagenes {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }

        .imagen-evidencia {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .imagen-evidencia:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        /* Modal para imágenes */
        .modal-imagen {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(5px);
        }

        .modal-contenido {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 85%;
            margin-top: 50px;
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.1);
        }

        .cerrar-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .cerrar-modal:hover {
            color: #bbb;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Reporte de Investigación Final'); ?>

            <div class="container-fluid p-4">
                <?php if ($modoSeleccionDirecta && $detallesReclamo): ?>
                    <?php echo $detallesReclamo; ?>
                <?php endif; ?>

                <?php if (!empty($errores)): ?>
                    <div style="color: red; margin-bottom: 20px; padding: 10px; background-color: #ffeeee; border-radius: 4px;">
                        <strong>Errores:</strong>
                        <ul>
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="reporteForm" method="POST">
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted text-uppercase small">Fecha de Resolución</label>
                            <input type="text" class="form-control bg-light" value="<?php echo strftime('%d-%b-%Y'); ?>" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted text-uppercase small required">Código de Reclamo</label>
                            <?php if ($modoSeleccionDirecta): ?>
                                <input type="text" class="form-control bg-light" value="Reclamo #<?php echo htmlspecialchars($reclamoPreSeleccionado['id']); ?>"
                                    readonly>
                                <input type="hidden" name="reclamo_id"
                                    value="<?php echo htmlspecialchars($reclamoPreSeleccionado['id']); ?>">
                            <?php else: ?>
                                <div class="search-container">
                                    <div class="input-group">
                                        <input type="text" id="reclamoSearch" class="form-control" placeholder="Ingrese código o sucursal"
                                            value="<?php echo htmlspecialchars($datosFormulario['reclamo_search'] ?? ''); ?>"
                                            autocomplete="off">
                                        <button type="button" id="btnBuscarReclamo" class="btn btn-outline-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="reclamo_id" id="reclamoId"
                                        value="<?php echo htmlspecialchars($datosFormulario['reclamo_id'] ?? ''); ?>" required>
                                    <div class="search-results" id="searchResults"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$modoSeleccionDirecta): ?>
                        <!-- Contenedor del reclamo seleccionado (solo en modo búsqueda) -->
                        <div class="selected-reclamo-container" id="reclamoInfo">
                            <div class="selected-reclamo-title">Reclamo seleccionado:</div>
                            <div class="selected-reclamo-details" id="reclamoDetails"></div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted text-uppercase small required">Colaborador(es) involucrado(s) y responsabilidad</label>
                        <div class="colaboradores-container mb-3" id="colaboradoresContainer">
                            <!-- Los colaboradores se agregarán aquí dinámicamente -->
                        </div>
                        <button type="button" class="btn btn-outline-dark btn-sm rounded-pill px-3" id="btnAddColaborador">
                            <i class="fas fa-plus me-1"></i> Agregar Colaborador
                        </button>
                        <input type="hidden" name="colaboradores" id="colaboradoresHidden"
                            value="<?php echo htmlspecialchars($datosFormulario['colaboradores'] ?? '[]'); ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted text-uppercase small">Tipo de Reclamo (Determinado por Operaciones)</label>
                        <select name="tipo_reclamo_operaciones" class="form-select">
                            <option value="">Seleccione una opción (si difiere del original)</option>
                            <option value="Producto fuera de estándar" <?php echo ($datosFormulario['tipo_reclamo_operaciones'] ?? '') === 'Producto fuera de estándar' ? 'selected' : ''; ?>>Producto fuera de estándar</option>
                            <option value="Producto con contaminante" <?php echo ($datosFormulario['tipo_reclamo_operaciones'] ?? '') === 'Producto con contaminante' ? 'selected' : ''; ?>>Producto con contaminante</option>
                            <option value="Producto incompleto" <?php echo ($datosFormulario['tipo_reclamo_operaciones'] ?? '') === 'Producto incompleto' ? 'selected' : ''; ?>>Producto incompleto</option>
                            <option value="Producto no siguió indicaciones del cliente" <?php echo ($datosFormulario['tipo_reclamo_operaciones'] ?? '') === 'Producto no siguió indicaciones del cliente' ? 'selected' : ''; ?>>Producto no siguió indicaciones del cliente</option>
                            <option value="Mala atención" <?php echo ($datosFormulario['tipo_reclamo_operaciones'] ?? '') === 'Mala atención' ? 'selected' : ''; ?>>Mala atención</option>
                            <option value="No se entregó factura" <?php echo ($datosFormulario['tipo_reclamo_operaciones'] ?? '') === 'No se entregó factura' ? 'selected' : ''; ?>>No se entregó factura</option>
                            <option value="Se cobró monto diferente a la factura" <?php echo ($datosFormulario['tipo_reclamo_operaciones'] ?? '') === 'Se cobró monto diferente a la factura' ? 'selected' : ''; ?>>Se cobró monto diferente a la factura</option>
                            <option value="Infraestructura inadecuada" <?php echo ($datosFormulario['tipo_reclamo_operaciones'] ?? '') === 'Infraestructura inadecuada' ? 'selected' : ''; ?>>Infraestructura inadecuada</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted text-uppercase small required">Resolución</label>
                        <select name="resolucion" class="form-select" required>
                            <option value="">Seleccione una opción</option>
                            <option value="Empresa" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Empresa' ? 'selected' : ''; ?>>Empresa</option>
                            <option value="Equipo de Tienda" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Equipo de Tienda' ? 'selected' : ''; ?>>Equipo de Tienda</option>
                            <option value="Pedidos Ya" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Pedidos Ya' ? 'selected' : ''; ?>>Pedidos Ya</option>
                            <option value="Sin resolución" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Sin resolución' ? 'selected' : ''; ?>>Sin resolución</option>
                            <option value="Atención al cliente digital" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Atención al cliente digital' ? 'selected' : ''; ?>>Atención al cliente digital</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted text-uppercase small required">Investigación</label>
                        <textarea name="investigacion" class="form-control" rows="4"
                            required><?php echo htmlspecialchars($datosFormulario['investigacion'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted text-uppercase small required">Plan de acción</label>
                        <textarea name="plan_accion" class="form-control" rows="4"
                            required><?php echo htmlspecialchars($datosFormulario['plan_accion'] ?? ''); ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-5">
                        <button type="button" class="btn btn-light border px-4" onclick="confirmCancel()">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm">Guardar Reporte</button>
                    </div>
                </form>
            </div>

            <!-- Modal para imágenes -->
            <div id="modalImagen" class="modal-imagen">
                <span class="cerrar-modal" onclick="cerrarModal()">&times;</span>
                <img class="modal-contenido" id="imagenAmpliada">
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Colaboradores
        const colaboradoresContainer = document.getElementById('colaboradoresContainer');
        const colaboradoresHidden = document.getElementById('colaboradoresHidden');
        const btnAddColaborador = document.getElementById('btnAddColaborador');

        let colaboradores = JSON.parse(colaboradoresHidden.value);

        function renderColaboradores() {
            colaboradoresContainer.innerHTML = '';

            colaboradores.forEach((colaborador, index) => {
                const colaboradorDiv = document.createElement('div');
                colaboradorDiv.className = 'colaborador-item';

                colaboradorDiv.innerHTML = `
                    <div class="row g-2 align-items-center w-100 mb-2">
                        <div class="col-7">
                            <input type="text" placeholder="Nombre del colaborador" class="form-control colaborador-nombre" value="${colaborador.nombre}" data-index="${index}">
                        </div>
                        <div class="col-4">
                            <div class="input-group">
                                <span class="input-group-text">C$</span>
                                <input type="number" placeholder="Monto" class="form-control colaborador-monto" value="${colaborador.monto}" data-index="${index}" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="col-1 text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm border-0 btn-remove-colaborador" data-index="${index}">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;

                colaboradoresContainer.appendChild(colaboradorDiv);
            });

            colaboradoresHidden.value = JSON.stringify(colaboradores);

            document.querySelectorAll('.colaborador-nombre').forEach(input => {
                input.addEventListener('change', updateColaboradores);
            });

            document.querySelectorAll('.colaborador-monto').forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.value = '0';
                        updateColaboradores.call(this);
                    }
                });
            });

            document.querySelectorAll('.btn-remove-colaborador').forEach(btn => {
                btn.addEventListener('click', removeColaborador);
            });
        }

        function updateColaboradores() {
            const index = parseInt(this.dataset.index);
            const field = this.classList.contains('colaborador-nombre') ? 'nombre' : 'monto';

            let value;
            if (field === 'monto') {
                // Si el campo está vacío, forzarlo a 0
                value = this.value.trim() === '' ? 0 : parseFloat(this.value) || 0;
                this.value = value; // Actualizar el input para que no quede vacío
            } else {
                value = this.value;
            }

            colaboradores[index][field] = value;
            colaboradoresHidden.value = JSON.stringify(colaboradores);
        }

        function addColaborador() {
            colaboradores.push({
                nombre: '',
                monto: 0
            });
            renderColaboradores();
        }

        function removeColaborador() {
            const index = parseInt(this.dataset.index);
            colaboradores.splice(index, 1);
            renderColaboradores();
        }

        btnAddColaborador.addEventListener('click', addColaborador);

        if (colaboradores.length === 0) {
            addColaborador();
        } else {
            renderColaboradores();
        }

        <?php if (!$modoSeleccionDirecta): ?>
            // Búsqueda de reclamos (solo en modo no selección directa)
            const reclamoSearch = document.getElementById('reclamoSearch');
            const btnBuscarReclamo = document.getElementById('btnBuscarReclamo');
            const searchResults = document.getElementById('searchResults');
            const reclamoId = document.getElementById('reclamoId');
            const reclamoInfo = document.getElementById('reclamoInfo');
            const reclamoDetails = document.getElementById('reclamoDetails');

            let searchTimeout;
            let reclamosPendientes = <?php echo isset($reclamosPendientes) ? json_encode($reclamosPendientes) : '[]'; ?>;

            // Eventos de búsqueda
            reclamoSearch.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(triggerSearch, 500);
            });

            btnBuscarReclamo.addEventListener('click', function() {
                clearTimeout(searchTimeout);
                triggerSearch();
            });

            // Función que dispara la búsqueda
            function triggerSearch() {
                const searchTerm = reclamoSearch.value.trim();

                if (searchTerm.length < 1) {
                    searchResults.style.display = 'none';
                    return;
                }

                // Si tenemos reclamos en memoria y el término es corto (ID), buscar localmente
                if (reclamosPendientes.length > 0 && /^\d{1,6}$/.test(searchTerm)) {
                    searchReclamosLocally(searchTerm);
                } else {
                    // Si no, hacer búsqueda por AJAX
                    searchReclamosAjax(searchTerm);
                }
            }

            // Búsqueda local (en memoria)
            function searchReclamosLocally(searchTerm) {
                searchResults.innerHTML = '';

                const filtered = reclamosPendientes.filter(reclamo =>
                    reclamo.id.toString().includes(searchTerm) ||
                    reclamo.sucursal.toLowerCase().includes(searchTerm.toLowerCase())
                );

                displayResults(filtered);
            }

            // Búsqueda por AJAX
            function searchReclamosAjax(searchTerm) {
                fetch(`buscar_reclamos.php?q=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(filtered => {
                        displayResults(filtered);
                    })
                    .catch(error => {
                        console.error('Error en búsqueda:', error);
                        searchResults.innerHTML = '<div class="search-result-item">Error al buscar reclamos</div>';
                        searchResults.style.display = 'block';
                    });
            }

            // Mostrar resultados
            function displayResults(filtered) {
                searchResults.innerHTML = '';

                if (filtered.length > 0) {
                    filtered.forEach(reclamo => {
                        const item = document.createElement('div');
                        item.className = 'search-result-item';
                        item.innerHTML = `
                        <div><strong>#${reclamo.id}</strong> - ${reclamo.sucursal}</div>
                        <small>${reclamo.fecha_evento_formatted} - ${reclamo.descripcion.substring(0, 50)}...</small>
                    `;
                        item.dataset.id = reclamo.id;
                        item.dataset.cod_sucursal = reclamo.sucursal_codigo; // NUEVO
                        item.dataset.details = `
                        <strong>Sucursal:</strong> ${reclamo.sucursal}<br>
                        <strong>Fecha:</strong> ${reclamo.fecha_evento_formatted}<br>
                        <strong>Descripción:</strong> ${reclamo.descripcion}
                    `;

                        item.addEventListener('click', function() {
                            selectReclamo(this.dataset.id, this.dataset.cod_sucursal, this.dataset.details);
                        });

                        searchResults.appendChild(item);
                    });
                    searchResults.style.display = 'block';
                } else {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.textContent = 'No se encontraron reclamos pendientes';
                    searchResults.appendChild(item);
                    searchResults.style.display = 'block';
                }
            }

            // Seleccionar reclamo
            function selectReclamo(id, cod_sucursal, details) {
                reclamoId.value = id;
                reclamoSearch.value = `Reclamo #${id}`;
                // Guardar también el código de sucursal si es necesario
                reclamoDetails.innerHTML = details;
                reclamoInfo.style.display = 'block';
                searchResults.style.display = 'none';
            }

            // Cerrar resultados al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!reclamoSearch.contains(e.target) && !btnBuscarReclamo.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.style.display = 'none';
                }
            });
        <?php endif; ?>

        // Confirmación antes de enviar o cancelar
        function confirmCancel() {
            if (confirm('¿Está seguro que desea cancelar? Los datos ingresados se perderán.')) {
                window.location.href = 'reclamospend.php';
            }
        }

        const puedeInvestigar = <?php echo $puedeInvestigar ? 'true' : 'false'; ?>;

        document.getElementById('reporteForm').addEventListener('submit', function(e) {
            if (!puedeInvestigar) {
                e.preventDefault();
                alert('No tiene autorización para guardar este reporte de investigación.');
                return;
            }
            // Validar que al menos un colaborador tenga nombre
            const colaboradoresValidos = colaboradores.filter(c => c.nombre.trim() !== '');

            if (colaboradoresValidos.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos un colaborador válido');
                return;
            }

            // Validar que ningún monto esté vacío (aunque sí puede ser 0)
            const montosInvalidos = colaboradores.some(c => c.monto === null || c.monto === undefined || c.monto === '');

            if (montosInvalidos) {
                e.preventDefault();
                alert('El campo de Monto no puede estar vacío. Si no hay responsabilidad, ingrese 0.');
                return;
            }

            colaboradoresHidden.value = JSON.stringify(colaboradoresValidos);

            if (!confirm('¿Está seguro que desea guardar este reporte de investigación?')) {
                e.preventDefault();
            }
        });

        // Mostrar errores específicos
        <?php if (!empty($errores)): ?>
            setTimeout(() => {
                const firstErrorField = document.querySelector('[name="<?php echo array_key_first($datosFormulario); ?>"]');
                if (firstErrorField) {
                    firstErrorField.focus();

                    if (firstErrorField.tagName === 'SELECT') {
                        firstErrorField.size = firstErrorField.options.length;
                        firstErrorField.addEventListener('blur', function() {
                            this.size = 1;
                        });
                    }
                }
            }, 100);
        <?php endif; ?>

        // Funciones para el modal de imágenes
        function mostrarImagenModal(imagen) {
            var modal = document.getElementById("modalImagen");
            var modalImg = document.getElementById("imagenAmpliada");

            modal.style.display = "block";
            modalImg.src = imagen.src;
        }

        function cerrarModal() {
            document.getElementById("modalImagen").style.display = "none";
        }

        // Cerrar modal al hacer clic fuera de la imagen
        window.onclick = function(event) {
            var modal = document.getElementById("modalImagen");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>

</html>