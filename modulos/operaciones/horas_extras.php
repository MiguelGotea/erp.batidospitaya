<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';

// Verificar acceso al módulo Operaciones (Código 11 para Jefe de Operaciones)
verificarAccesoModulo('operaciones');

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Obtener todas las sucursales (el jefe de operaciones puede ver todas)
$sucursales = obtenerTodasSucursales();

// Procesar aprobación/denegación de horas extras
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aprobar_horas'])) {
    procesarAprobacionHorasExtras();
}

// Obtener datos para los filtros
$sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);
$fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');

// Obtener horas extras si hay sucursal y fechas seleccionadas
$horasExtras = [];
if ($sucursalSeleccionada && $fechaDesde && $fechaHasta) {
    $horasExtras = obtenerHorasExtras($sucursalSeleccionada, $fechaDesde, $fechaHasta);
}

// Funciones auxiliares específicas para horas extras
//function obtenerTodasSucursales() {
//    global $conn;
//    
//    $stmt = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE codigo NOT IN (14, 0) ORDER BY nombre");
//    $stmt->execute();
//    return $stmt->fetchAll();
//}

function obtenerHorasExtras($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    // Primero obtenemos todas las marcaciones con salida en el rango de fechas y sucursal
    $stmt = $conn->prepare("
        SELECT m.id, m.CodOperario, m.nombre_operario, m.fecha, m.hora_salida, 
               m.sucursal_codigo, s.nombre as sucursal_nombre
        FROM marcaciones m
        JOIN sucursales s ON m.sucursal_codigo = s.codigo
        WHERE m.sucursal_codigo = ?
        AND m.fecha BETWEEN ? AND ?
        AND m.hora_salida IS NOT NULL
        ORDER BY m.fecha DESC, m.hora_salida DESC
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
        
        if ($horarioProgramado && $horarioProgramado['hora_salida']) {
            // Calcular diferencia entre hora programada y hora marcada
            $horaProgramada = new DateTime($horarioProgramado['hora_salida']);
            $horaMarcada = new DateTime($marcacion['hora_salida']);
            
            // Solo considerar como horas extras si la hora marcada es posterior a la programada
            if ($horaMarcada > $horaProgramada) {
                $diferencia = $horaMarcada->diff($horaProgramada);
                $horasExtras = $diferencia->h + ($diferencia->i / 60);
                
                // Obtener el estado actual de estas horas extras (si ya fue aprobado/denegado)
                $estadoHorasExtras = obtenerEstadoHorasExtras($marcacion['id']);
                
                $resultados[] = [
                    'id_marcacion' => $marcacion['id'],
                    'cod_operario' => $marcacion['CodOperario'],
                    'nombre_operario' => $marcacion['nombre_operario'],
                    'fecha' => $marcacion['fecha'],
                    'sucursal_codigo' => $marcacion['sucursal_codigo'],
                    'sucursal_nombre' => $marcacion['sucursal_nombre'],
                    'hora_salida_programada' => $horarioProgramado['hora_salida'],
                    'hora_salida_marcada' => $marcacion['hora_salida'],
                    'horas_extras' => $horasExtras,
                    'estado' => $estadoHorasExtras['estado'] ?? 'Pendiente',
                    'observaciones' => $estadoHorasExtras['observaciones'] ?? null,
                    'id_aprobacion' => $estadoHorasExtras['id'] ?? null
                ];
            }
        }
    }
    
    return $resultados;
}

function obtenerEstadoHorasExtras($idMarcacion) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT id, estado, observaciones 
        FROM HorasExtraStatus 
        WHERE id_marcacion = ?
        LIMIT 1
    ");
    $stmt->execute([$idMarcacion]);
    return $stmt->fetch();
}

function procesarAprobacionHorasExtras() {
    global $conn;
    
    try {
        $idMarcacion = $_POST['id_marcacion'];
        $codOperario = $_POST['cod_operario'];
        $estado = $_POST['estado'];
        $observaciones = $_POST['observaciones'] ?? null;
        $horasExtras = $_POST['horas_extras'];
        
        // Verificar si ya existe un registro para esta marcación
        $existente = obtenerEstadoHorasExtras($idMarcacion);
        
        if ($existente) {
            // Actualizar registro existente
            $stmt = $conn->prepare("
                UPDATE HorasExtraStatus 
                SET estado = ?, observaciones = ?, horas_extras = ?, 
                    actualizado_por = ?, fecha_actualizacion = NOW(),
                    cod_operario = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $estado, $observaciones, $horasExtras,
                $_SESSION['usuario_id'], $codOperario, $existente['id']
            ]);
        } else {
            // Crear nuevo registro
            $stmt = $conn->prepare("
                INSERT INTO HorasExtraStatus (
                    id_marcacion, cod_operario, estado, observaciones, horas_extras, 
                    creado_por, actualizado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idMarcacion, $codOperario, $estado, $observaciones, $horasExtras,
                $_SESSION['usuario_id'], $_SESSION['usuario_id']
            ]);
        }
        
        $_SESSION['exito'] = 'Estado de horas extras actualizado correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al procesar las horas extras: ' . $e->getMessage();
    }
    
    header('Location: horas_extras.php?' . http_build_query([
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
    <title>Horas Extras - Operaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        select, input, button {
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

        th, td {
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
            background-color: rgba(0,0,0,0.5);
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        
        .form-select, .form-textarea {
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
    <div class="container">
        <div class="header">
            <h1 class="title">Gestión de Horas Extras</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?></span>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Regresar
                </a>
            </div>
        </div>
        
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
            <?php if (!empty($horasExtras)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Fecha</th>
                            <th>Horas Extras (Horas . minutos)</th>
                            <th>Status</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horasExtras as $he): ?>
                            <tr>
                                <td><?= htmlspecialchars($he['nombre_operario']) ?></td>
                                <td><?= formatoFechaCorta($he['fecha']) ?></td>
                                <td><?= number_format($he['horas_extras'], 2) ?></td>
                                <td>
                                    <span class="status-<?= strtolower($he['estado']) ?>">
                                        <?= $he['estado'] ?>
                                    </span>
                                </td>
                                <td><?= $he['observaciones'] ? htmlspecialchars($he['observaciones']) : '-' ?></td>
                                <td style="text-align: center;">
                                    <button type="button" onclick="mostrarModalAprobacion(
                                        <?= $he['id_marcacion'] ?>, 
                                        '<?= htmlspecialchars($he['nombre_operario']) ?>', 
                                        '<?= htmlspecialchars($he['sucursal_nombre']) ?>', 
                                        '<?= $he['fecha'] ?>', 
                                        '<?= $he['hora_salida_programada'] ?>', 
                                        '<?= $he['hora_salida_marcada'] ?>', 
                                        <?= $he['horas_extras'] ?>, 
                                        '<?= $he['estado'] ?>', 
                                        '<?= htmlspecialchars($he['observaciones'] ?? '') ?>',
                                        <?= $he['cod_operario'] ?>
                                    )" class="btn btn-info">
                                        <i class="fas fa-edit"></i> <?= $he['estado'] == 'Pendiente' ? 'Aprobar' : 'Modificar' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <?php if ($sucursalSeleccionada && $fechaDesde && $fechaHasta): ?>
                        No se encontraron horas extras para los filtros seleccionados.
                    <?php else: ?>
                        Seleccione una sucursal y rango de fechas para buscar horas extras.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal para aprobación de horas extras -->
    <div class="modal" id="modalAprobacion">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Aprobar/Denegar Horas Extras</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formAprobacion" method="post">
                <input type="hidden" name="aprobar_horas" value="1">
                <input type="hidden" id="id_marcacion" name="id_marcacion">
                <input type="hidden" id="cod_operario" name="cod_operario"> <!-- Campo de Código del Operario -->
                <input type="hidden" id="horas_extras" name="horas_extras">
                
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
                        <span class="info-label">Hora Salida Programada:</span>
                        <span class="info-value" id="modal-hora-programada"></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Hora Salida Marcada:</span>
                        <span class="info-value" id="modal-hora-marcada"></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Horas Extras:</span>
                        <span class="info-value" id="modal-horas-extras"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado" class="form-label">Estado:</label>
                        <select id="estado" name="estado" class="form-select" required>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Aprobado">Aprobado</option>
                            <option value="Denegado">Denegado</option>
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
                window.location.href = 'horas_extras.php?' + new URLSearchParams({
                    sucursal: sucursal,
                    desde: desde,
                    hasta: hasta
                });
            }
        }
        
        // Mostrar modal de aprobación
        function mostrarModalAprobacion(idMarcacion, nombre, sucursal, fecha, horaProgramada, horaMarcada, horasExtras, estado, observaciones, codOperario) {
            document.getElementById('id_marcacion').value = idMarcacion;
            document.getElementById('cod_operario').value = codOperario;
            document.getElementById('horas_extras').value = horasExtras;
            
            document.getElementById('modal-nombre').textContent = nombre;
            document.getElementById('modal-sucursal').textContent = sucursal;
            document.getElementById('modal-fecha').textContent = new Date(fecha).toLocaleDateString('es-ES');
            document.getElementById('modal-hora-programada').textContent = formatoHoraAmPm(horaProgramada);
            document.getElementById('modal-hora-marcada').textContent = formatoHoraAmPm(horaMarcada);
            document.getElementById('modal-horas-extras').textContent = horasExtras.toFixed(2);
            
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
        
        // Función para formatear hora a formato 12h AM/PM
        function formatoHoraAmPm(hora) {
            if (!hora || hora === '00:00:00') return '-';
            
            const [horas, minutos] = hora.split(':');
            let horas12 = parseInt(horas, 10);
            const ampm = horas12 >= 12 ? 'PM' : 'AM';
            horas12 = horas12 % 12;
            horas12 = horas12 ? horas12 : 12; // la hora 0 debe mostrarse como 12
            
            return `${horas12.toString().padStart(2, '0')}:${minutos} ${ampm}`;
        }
    </script>
</body>
</html>