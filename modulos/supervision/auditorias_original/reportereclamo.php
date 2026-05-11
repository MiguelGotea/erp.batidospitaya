<?php
// Al inicio del archivo, verificar autenticación y acceso al módulo
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;

// Verificar acceso al módulo 'publico' (o el nombre que corresponda según tus permisos)
//verificarAccesoModulo('operaciones');

//******************************Estándar para header******************************

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo
if (!verificarAccesoCargo([11, 16, 42]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

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
        <div class="card" style="margin-bottom: 30px;">
            <div class="card-header">
                <h2 class="card-title">Reclamo #' . $reclamoPreSeleccionado['id'] . '</h2>
                <span class="badge badge-pendiente">ABIERTO</span>
            </div>
            <div class="card-body">
                <div class="info-group">
                    <span class="info-label">Fecha de Registro:</span>
                    <div class="info-value">
                        ' . htmlspecialchars($reclamoPreSeleccionado['fecha_registro_formateada']) . '
                    </div>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Medio de Compra:</span>
                    <div class="info-value">
                        ' . htmlspecialchars($reclamoPreSeleccionado['medio_compra'] ?? '--') . '
                    </div>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Sucursal:</span>
                    <div class="info-value">
                        ' . htmlspecialchars($reclamoPreSeleccionado['sucursal']) . '
                    </div>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Categoría de Reclamo:</span>
                    <div class="info-value">
                        ' . (!empty($reclamoPreSeleccionado['grupo_nombre']) ?
            '<strong>' . htmlspecialchars($reclamoPreSeleccionado['grupo_nombre']) . '</strong> - ' . htmlspecialchars($reclamoPreSeleccionado['tipo_nombre']) :
            htmlspecialchars($reclamoPreSeleccionado['tipo_reclamo'] ?? '--')) . '
                    </div>
                </div>

                <div class="info-group">
                    <span class="info-label">Fecha y Hora del Evento:</span>
                    <div class="info-value">
                        ' . traducirMes(date('d-M-Y', strtotime($reclamoPreSeleccionado['fecha_evento']))) . ' | ' . htmlspecialchars($reclamoPreSeleccionado['hora_evento']) . '
                    </div>
                </div>';

        if (!$puedeInvestigar) {
            $detallesReclamo .= '
                <div class="alert-warning" style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; border: 1px solid #ffeeba; margin-top: 20px;">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Atención:</strong> Su cargo no está autorizado para realizar la investigación final de este tipo de reclamo.
                </div>';
        }

        $detallesReclamo .= '
                <div class="info-group">
                    <span class="info-label">Investigación Preliminar:</span>
                    <div class="info-value">
                        ' . htmlspecialchars($reclamoPreSeleccionado['investigacion_preliminar'] ?? '--') . '
                    </div>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Descripción:</span>
                    <div class="info-value">
                        ' . nl2br(htmlspecialchars($reclamoPreSeleccionado['descripcion'])) . '
                    </div>
                </div>';

        if (!empty($productos)) {
            $detallesReclamo .= '
                <div class="info-group">
                    <span class="info-label">Producto(s) en Reclamo:</span>
                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                            </tr>
                        </thead>
                        <tbody>';

            foreach ($productos as $producto) {
                $detallesReclamo .= '
                            <tr>
                                <td>' . htmlspecialchars($producto['producto']) . '</td>
                                <td>C$ ' . number_format($producto['precio'], 2) . '</td>
                            </tr>';
            }

            $detallesReclamo .= '
                        </tbody>
                    </table>
                </div>';
        }

        if (!empty($imagenes)) {
            $detallesReclamo .= '
                <div class="info-group">
                    <span class="info-label">Fotos de Evidencia:</span>
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
                <div class="info-group">
                    <span class="info-label">Acción Inmediata:</span>
                    <div class="info-value">
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
    <title>Reporte de Investigación Final</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Calibri', sans-serif;
        }

        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 20px;
            color: #333;
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

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #0E544C;
            text-align: center;
            margin-bottom: 25px;
            font-size: 24px;
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 20px;
            width: 100%;
        }

        .form-group.half-width {
            width: calc(50% - 10px);
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .required:after {
            content: " *";
            color: red;
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .error {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: #51B8AC;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0E544C;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .colaboradores-container {
            margin-bottom: 20px;
        }

        .colaborador-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .colaborador-item input {
            flex: 1;
        }

        .btn-remove-colaborador {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px;
            cursor: pointer;
        }

        .btn-add-colaborador {
            background-color: #0E544C;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 15px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }

        .search-container {
            position: relative;
            margin-bottom: 15px;
        }

        .search-results {
            position: absolute;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            z-index: 100;
            display: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        .search-result-item:hover {
            background-color: #f5f5f5;
        }

        /* Estilos para la tarjeta de reclamo */
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .card-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            color: #0E544C;
            margin: 0;
            font-size: 22px;
        }

        .card-body {
            line-height: 1.6;
        }

        .info-group {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }

        .info-value {
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
            border-left: 3px solid #51B8AC;
        }

        .productos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .productos-table th,
        .productos-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .productos-table th {
            background-color: #51B8AC;
            color: white;
        }

        .productos-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

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
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .imagen-evidencia:hover {
            transform: scale(1.05);
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
        }

        .badge-pendiente {
            background-color: #FFC107;
            color: #333;
        }

        /* Modal para imágenes */
        .modal-imagen {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: auto;
        }

        .modal-contenido {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            margin-top: 50px;
        }

        .cerrar-modal {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
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

            .container {
                padding: 15px;
            }

            h1 {
                font-size: 20px;
            }

            .form-group.half-width {
                width: 100%;
            }

            .colaborador-item {
                flex-direction: column;
                gap: 5px;
            }

            .colaborador-item input {
                width: 100%;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
                margin-bottom: 10px;
            }

            .imagen-evidencia {
                width: 100px;
                height: 100px;
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
        }
    </style>
</head>

<body>
    <header>
        <div class="header-container">
            <div class="logo-container">
                <img src="/core/assets/img/Logo.svg" alt="Batidos Pitaya" class="logo">
            </div>

            <div class="buttons-container">
                <a href="index_avisos.php"
                    class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'index_avisos.php' ? 'activo' : '' ?>">
                    <i class="fas fa-bullhorn"></i> <span class="btn-text">Nuevo Aviso</span>
                </a>
                <a href="kpi.php"
                    class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'kpi.php' ? 'activo' : '' ?>">
                    <i class="fas fa-chart-line"></i> <span class="btn-text">KPI</span>
                </a>
                <a href="reclamospend.php"
                    class="btn-agregar <?= basename($_SERVER['PHP_SELF']) == 'reclamospend.php' ? 'activo' : '' ?>">
                    <i class="fas fa-search"></i> <span class="btn-text">Reclamos</span>
                </a>
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

    <div class="container">
        <?php if ($modoSeleccionDirecta && $detallesReclamo): ?>
            <?php echo $detallesReclamo; ?>
        <?php endif; ?>

        <h1><strong>REPORTE DE INVESTIGACIÓN FINAL</strong></h1>

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
            <div class="form-header">
                <div class="form-group half-width">
                    <label>Fecha de Resolución</label>
                    <input type="text" value="<?php echo strftime('%d-%b-%Y'); ?>" readonly>
                </div>

                <div class="form-group half-width">
                    <label class="required">Código de Reclamo</label>
                    <?php if ($modoSeleccionDirecta): ?>
                        <input type="text" value="Reclamo #<?php echo htmlspecialchars($reclamoPreSeleccionado['id']); ?>"
                            readonly>
                        <input type="hidden" name="reclamo_id"
                            value="<?php echo htmlspecialchars($reclamoPreSeleccionado['id']); ?>">
                    <?php else: ?>
                        <div class="search-container">
                            <div style="display: flex; gap: 5px;">
                                <input type="text" id="reclamoSearch" placeholder="Ingrese código o sucursal"
                                    value="<?php echo htmlspecialchars($datosFormulario['reclamo_search'] ?? ''); ?>"
                                    autocomplete="off" style="flex: 1;">
                                <button type="button" id="btnBuscarReclamo" class="btn" style="padding: 10px;">
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

            <div class="form-group">
                <label class="required">Colaborador(es) involucrado(s) y responsabilidad</label>
                <div class="colaboradores-container" id="colaboradoresContainer">
                    <!-- Los colaboradores se agregarán aquí dinámicamente -->
                </div>
                <button type="button" class="btn-add-colaborador" id="btnAddColaborador">
                    <i class="fas fa-plus"></i> Agregar Colaborador
                </button>
                <input type="hidden" name="colaboradores" id="colaboradoresHidden"
                    value="<?php echo htmlspecialchars($datosFormulario['colaboradores'] ?? '[]'); ?>">
            </div>

            <div class="form-group">
                <label>Tipo de Reclamo (Determinado por Operaciones)</label>
                <select name="tipo_reclamo_operaciones">
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

            <div class="form-group">
                <label class="required">Resolución</label>
                <select name="resolucion" required>
                    <option value="">Seleccione una opción</option>
                    <option value="Empresa" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Empresa' ? 'selected' : ''; ?>>Empresa</option>
                    <option value="Equipo de Tienda" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Equipo de Tienda' ? 'selected' : ''; ?>>Equipo de Tienda</option>
                    <option value="Pedidos Ya" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Pedidos Ya' ? 'selected' : ''; ?>>Pedidos Ya</option>
                    <option value="Sin resolución" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Sin resolución' ? 'selected' : ''; ?>>Sin resolución</option>
                    <option value="Atención al cliente digital" <?php echo ($datosFormulario['resolucion'] ?? '') === 'Atención al cliente digital' ? 'selected' : ''; ?>>Atención al cliente digital</option>
                </select>
            </div>

            <div class="form-group">
                <label class="required">Investigación</label>
                <textarea name="investigacion"
                    required><?php echo htmlspecialchars($datosFormulario['investigacion'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label class="required">Plan de acción</label>
                <textarea name="plan_accion"
                    required><?php echo htmlspecialchars($datosFormulario['plan_accion'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="confirmCancel()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Reporte</button>
            </div>
        </form>
    </div>

    <!-- Modal para imágenes -->
    <div id="modalImagen" class="modal-imagen">
        <span class="cerrar-modal" onclick="cerrarModal()">&times;</span>
        <img class="modal-contenido" id="imagenAmpliada">
    </div>

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
                    <input type="text" placeholder="Nombre del colaborador" class="colaborador-nombre" value="${colaborador.nombre}" data-index="${index}">
                    <input type="number" placeholder="Monto responsabilidad (C$)" class="colaborador-monto" value="${colaborador.monto}" data-index="${index}" step="0.01" min="0">
                    <button type="button" class="btn-remove-colaborador" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                `;

                colaboradoresContainer.appendChild(colaboradorDiv);
            });

            colaboradoresHidden.value = JSON.stringify(colaboradores);

            document.querySelectorAll('.colaborador-nombre').forEach(input => {
                input.addEventListener('change', updateColaboradores);
            });

            document.querySelectorAll('.colaborador-monto').forEach(input => {
                input.addEventListener('blur', function () {
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
            colaboradores.push({ nombre: '', monto: 0 });
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
            reclamoSearch.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(triggerSearch, 500);
            });

            btnBuscarReclamo.addEventListener('click', function () {
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
                        item.dataset.cod_sucursal = reclamo.sucursal_codigo;  // NUEVO
                        item.dataset.details = `
                        <strong>Sucursal:</strong> ${reclamo.sucursal}<br>
                        <strong>Fecha:</strong> ${reclamo.fecha_evento_formatted}<br>
                        <strong>Descripción:</strong> ${reclamo.descripcion}
                    `;

                        item.addEventListener('click', function () {
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
            document.addEventListener('click', function (e) {
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

        document.getElementById('reporteForm').addEventListener('submit', function (e) {
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
                        firstErrorField.addEventListener('blur', function () {
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
        window.onclick = function (event) {
            var modal = document.getElementById("modalImagen");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>

</html>
