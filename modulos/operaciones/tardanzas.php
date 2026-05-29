<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoUsuarioId = $usuario['CodNivelesCargos'];

// Verificar permiso de vista para tardanzas
if (!tienePermiso('tardanzas_manual', 'vista', $cargoUsuarioId)) {
    header('Location: ../../../index.php');
    exit();
}

$puedeAprobar = tienePermiso('tardanzas_manual', 'aprobar', $cargoUsuarioId);

// Obtener todas las sucursales (el jefe de operaciones puede ver todas)
$sucursales = obtenerTodasSucursales();

// Procesar aprobación/denegación de tardanzas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aprobar_tardanza'])) {
    if (!$puedeAprobar) {
        $_SESSION['error'] = 'No tiene permiso para realizar esta acción';
        header('Location: tardanzas.php?' . http_build_query([
            'sucursal' => $_GET['sucursal'] ?? '',
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? ''
        ]));
        exit();
    }
    procesarAprobacionTardanza();
}

// Obtener datos para los filtros
$sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);
$fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');

// Obtener tardanzas si hay sucursal y fechas seleccionadas
$tardanzas = [];
if ($sucursalSeleccionada && $fechaDesde && $fechaHasta) {
    $tardanzas = obtenerTardanzas($sucursalSeleccionada, $fechaDesde, $fechaHasta);
}

// Funciones auxiliares específicas para tardanzas
//function obtenerTodasSucursales() {
//    global $conn;
//    
//    $stmt = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE codigo NOT IN (14, 0) ORDER BY nombre");
//    $stmt->execute();
//    return $stmt->fetchAll();
//}

function obtenerTardanzas($codSucursal, $fechaDesde, $fechaHasta)
{
    global $conn;

    // Primero obtenemos todas las marcaciones con entrada en el rango de fechas y sucursal
    $stmt = $conn->prepare("
        SELECT m.id, m.CodOperario, m.nombre_operario, m.fecha, m.hora_ingreso, 
               m.sucursal_codigo, s.nombre as sucursal_nombre
        FROM marcaciones m
        JOIN sucursales s ON m.sucursal_codigo = s.codigo
        WHERE m.sucursal_codigo = ?
        AND m.fecha BETWEEN ? AND ?
        AND m.hora_ingreso IS NOT NULL
        ORDER BY m.fecha DESC, m.hora_ingreso DESC
    ");
    $stmt->execute([$codSucursal, $fechaDesde, $fechaHasta]);
    $marcaciones = $stmt->fetchAll();

    $resultados = [];

    foreach ($marcaciones as $marcacion) {
        // Obtener la semana a la que pertenece esta fecha
        $semana = obtenerSemanaPorFecha($marcacion['fecha']);
        if (!$semana) continue;

        // Obtener el horario programado para ese operario en esa semana y sucursal
        $horarioProgramado = obtenerHorarioOperacionesPorDia(
            $marcacion['CodOperario'],
            $semana['id'],
            $marcacion['sucursal_codigo'],
            $marcacion['fecha']
        );

        if ($horarioProgramado && $horarioProgramado['hora_entrada']) {
            // Calcular diferencia entre hora programada y hora marcada
            $horaProgramada = new DateTime($horarioProgramado['hora_entrada']);
            $horaMarcada = new DateTime($marcacion['hora_ingreso']);

            // Solo considerar como tardanza si la hora marcada es posterior a la programada
            if ($horaMarcada > $horaProgramada) {
                $diferencia = $horaMarcada->diff($horaProgramada);
                $minutosTardanza = ($diferencia->h * 60) + $diferencia->i;

                // Obtener el estado actual de esta tardanza (si ya fue aprobada/denegada)
                $estadoTardanza = obtenerEstadoTardanza($marcacion['id']);

                $resultados[] = [
                    'id_marcacion' => $marcacion['id'],
                    'cod_operario' => $marcacion['CodOperario'],
                    'nombre_operario' => $marcacion['nombre_operario'],
                    'fecha' => $marcacion['fecha'],
                    'sucursal_codigo' => $marcacion['sucursal_codigo'],
                    'sucursal_nombre' => $marcacion['sucursal_nombre'],
                    'hora_entrada_programada' => $horarioProgramado['hora_entrada'],
                    'hora_entrada_marcada' => $marcacion['hora_ingreso'],
                    'minutos_tardanza' => $minutosTardanza,
                    'estado' => $estadoTardanza['estado'] ?? 'Pendiente',
                    'observaciones' => $estadoTardanza['observaciones'] ?? null,
                    'id_aprobacion' => $estadoTardanza['id'] ?? null
                ];
            }
        }
    }

    return $resultados;
}

function obtenerEstadoTardanza($idMarcacion)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id, estado, observaciones 
        FROM TardanzasStatus 
        WHERE id_marcacion = ?
        LIMIT 1
    ");
    $stmt->execute([$idMarcacion]);
    return $stmt->fetch();
}

function procesarAprobacionTardanza()
{
    global $conn;

    try {
        $idMarcacion = $_POST['id_marcacion'];
        $codOperario = $_POST['cod_operario'];
        $estado = $_POST['estado'];
        $observaciones = $_POST['observaciones'] ?? null;
        $minutosTardanza = $_POST['minutos_tardanza'];

        // Verificar si ya existe un registro para esta marcación
        $existente = obtenerEstadoTardanza($idMarcacion);

        if ($existente) {
            // Actualizar registro existente
            $stmt = $conn->prepare("
                UPDATE TardanzasStatus 
                SET estado = ?, observaciones = ?, minutos_tardanza = ?, 
                    actualizado_por = ?, fecha_actualizacion = NOW(),
                    cod_operario = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $estado,
                $observaciones,
                $minutosTardanza,
                $_SESSION['usuario_id'],
                $codOperario,
                $existente['id']
            ]);
        } else {
            // Crear nuevo registro
            $stmt = $conn->prepare("
                INSERT INTO TardanzasStatus (
                    id_marcacion, cod_operario, estado, observaciones, tiempo_tardanza, 
                    creado_por, actualizado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idMarcacion,
                $codOperario,
                $estado,
                $observaciones,
                $minutosTardanza,
                $_SESSION['usuario_id'],
                $_SESSION['usuario_id']
            ]);
        }

        $_SESSION['exito'] = 'Estado de tardanza actualizado correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al procesar la tardanza: ' . $e->getMessage();
    }

    header('Location: tardanzas.php?' . http_build_query([
        'sucursal' => $_GET['sucursal'] ?? '',
        'desde' => $_GET['desde'] ?? '',
        'hasta' => $_GET['hasta'] ?? ''
    ]));
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tardanzas - Operaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
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

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
        }

        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }

        select,
        input,
        button {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 15px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #0E544C;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-success {
            background-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-danger {
            background-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-primary {
            background-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0069d9;
        }

        .btn-info {
            background-color: #17a2b8;
        }

        .btn-info:hover {
            background-color: #138496;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            vertical-align: middle;
        }

        th {
            background-color: #0E544C;
            color: white;
            text-align: center;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .status-pendiente {
            color: #856404;
            background-color: #fff3cd;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }

        .status-aprobado {
            color: #155724;
            background-color: #d4edda;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }

        .status-denegado {
            color: #721c24;
            background-color: #f8d7da;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .modal-title {
            color: #0E544C;
            font-size: 1.2rem !important;
            font-weight: bold;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            margin-bottom: 15px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .info-group {
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: bold;
            color: #0E544C;
        }

        .info-value {
            margin-left: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }

        .form-select,
        .form-textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-textarea {
            min-height: 80px;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoUsuarioId); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Gestión de Tardanzas'); ?>

            <div class="container-fluid p-3">

                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['exito'] ?>
                        <?php unset($_SESSION['exito']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="filters">
                    <div class="filter-group">
                        <label for="sucursal">Sucursal</label>
                        <select id="sucursal" name="sucursal" onchange="actualizarFiltros()">
                            <?php foreach ($sucursales as $sucursal): ?>
                                <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sucursal['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="desde">Desde</label>
                        <input type="date" id="desde" name="desde" value="<?= $fechaDesde ?>" onchange="actualizarFiltros()">
                    </div>

                    <div class="filter-group">
                        <label for="hasta">Hasta</label>
                        <input type="date" id="hasta" name="hasta" value="<?= $fechaHasta ?>" onchange="actualizarFiltros()">
                    </div>

                    <div class="filter-group" style="align-self: flex-end;">
                        <button type="button" onclick="actualizarFiltros()" class="btn">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <?php if (!empty($tardanzas)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Fecha</th>
                                    <th>Tardanza (min)</th>
                                    <th>Status</th>
                                    <th>Observaciones</th>
                                    <?php if ($puedeAprobar): ?>
                                        <th>Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tardanzas as $t): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($t['nombre_operario']) ?></td>
                                        <td><?= formatoFecha($t['fecha']) ?></td>
                                        <td><?= number_format($t['minutos_tardanza'], 0) ?></td>
                                        <td>
                                            <span class="status-<?= strtolower(str_replace(' ', '-', $t['estado'])) ?>">
                                                <?= $t['estado'] ?>
                                            </span>
                                        </td>
                                        <td><?= $t['observaciones'] ? htmlspecialchars($t['observaciones']) : '-' ?></td>
                                        <?php if ($puedeAprobar): ?>
                                            <td style="text-align: center;">
                                                <button type="button" onclick="mostrarModalAprobacion(
                                            <?= $t['id_marcacion'] ?>, 
                                            '<?= htmlspecialchars($t['nombre_operario']) ?>', 
                                            '<?= htmlspecialchars($t['sucursal_nombre']) ?>', 
                                            '<?= $t['fecha'] ?>', 
                                            '<?= $t['hora_entrada_programada'] ?>', 
                                            '<?= $t['hora_entrada_marcada'] ?>', 
                                            <?= $t['minutos_tardanza'] ?>, 
                                            '<?= $t['estado'] ?>', 
                                            '<?= htmlspecialchars($t['observaciones'] ?? '') ?>',
                                            <?= $t['cod_operario'] ?>
                                        )" class="btn btn-info">
                                                    <i class="fas fa-edit"></i> <?= $t['estado'] == 'Pendiente' ? 'Justificar' : 'Modificar' ?>
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-results">
                            <?php if ($sucursalSeleccionada && $fechaDesde && $fechaHasta): ?>
                                No se encontraron tardanzas para los filtros seleccionados.
                            <?php else: ?>
                                Seleccione una sucursal y rango de fechas para buscar tardanzas.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal para aprobación de tardanzas -->
            <div class="modal" id="modalAprobacion">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title">Justificar Tardanza</h2>
                        <button class="modal-close" onclick="cerrarModal()">&times;</button>
                    </div>
                    <form id="formAprobacion" method="post">
                        <input type="hidden" name="aprobar_tardanza" value="1">
                        <input type="hidden" id="id_marcacion" name="id_marcacion">
                        <input type="hidden" id="cod_operario" name="cod_operario">
                        <input type="hidden" id="minutos_tardanza" name="minutos_tardanza">

                        <div class="modal-body">
                            <div class="info-group">
                                <span class="info-label">Colaborador:</span>
                                <span class="info-value" id="modal-nombre"></span>
                            </div>

                            <div class="info-group">
                                <span class="info-label">Sucursal:</span>
                                <span class="info-value" id="modal-sucursal"></span>
                            </div>

                            <div class="info-group">
                                <span class="info-label">Fecha:</span>
                                <span class="info-value" id="modal-fecha"></span>
                            </div>

                            <div class="info-group">
                                <span class="info-label">Hora Entrada Programada:</span>
                                <span class="info-value" id="modal-hora-programada"></span>
                            </div>

                            <div class="info-group">
                                <span class="info-label">Hora Entrada Marcada:</span>
                                <span class="info-value" id="modal-hora-marcada"></span>
                            </div>

                            <div class="info-group">
                                <span class="info-label">Tardanza:</span>
                                <span class="info-value" id="modal-tardanza"></span>
                            </div>

                            <div class="form-group">
                                <label for="estado" class="form-label">Estado:</label>
                                <select id="estado" name="estado" class="form-select" required>
                                    <option value="Pendiente">Pendiente</option>
                                    <option value="Justificado">Justificado</option>
                                    <option value="No Válido">No Válido</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="observaciones" class="form-label">Observaciones:</label>
                                <textarea id="observaciones" name="observaciones" class="form-textarea"></textarea>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                // Actualizar filtros y recargar la página
                function actualizarFiltros() {
                    const sucursal = document.getElementById('sucursal').value;
                    const desde = document.getElementById('desde').value;
                    const hasta = document.getElementById('hasta').value;

                    if (sucursal && desde && hasta) {
                        window.location.href = 'tardanzas.php?' + new URLSearchParams({
                            sucursal: sucursal,
                            desde: desde,
                            hasta: hasta
                        });
                    }
                }

                // Mostrar modal de aprobación
                function mostrarModalAprobacion(idMarcacion, nombre, sucursal, fecha, horaProgramada, horaMarcada, minutosTardanza, estado, observaciones, codOperario) {
                    document.getElementById('id_marcacion').value = idMarcacion;
                    document.getElementById('cod_operario').value = codOperario;
                    document.getElementById('minutos_tardanza').value = minutosTardanza;

                    document.getElementById('modal-nombre').textContent = nombre;
                    document.getElementById('modal-sucursal').textContent = sucursal;
                    document.getElementById('modal-fecha').textContent = new Date(fecha).toLocaleDateString('es-ES');
                    document.getElementById('modal-hora-programada').textContent = horaProgramada;
                    document.getElementById('modal-hora-marcada').textContent = horaMarcada;

                    // Formatear minutos a horas y minutos si es mayor a 60
                    const horas = Math.floor(minutosTardanza / 60);
                    const minutos = minutosTardanza % 60;
                    let tardanzaTexto = '';
                    if (horas > 0) {
                        tardanzaTexto = `${horas}h ${minutos}m`;
                    } else {
                        tardanzaTexto = `${minutos}m`;
                    }
                    document.getElementById('modal-tardanza').textContent = tardanzaTexto;

                    document.getElementById('estado').value = estado;
                    document.getElementById('observaciones').value = observaciones;

                    document.getElementById('modalAprobacion').style.display = 'flex';
                }

                // Cerrar modal
                function cerrarModal() {
                    document.getElementById('modalAprobacion').style.display = 'none';
                }

                // Cerrar modal al hacer clic fuera del contenido
                window.addEventListener('click', function(event) {
                    const modal = document.getElementById('modalAprobacion');
                    if (event.target === modal) {
                        cerrarModal();
                    }
                });
            </script>
        </div>
    </div>
    </div>
</body>

</html>