<?php
require_once '../../includes/auth.php';
// Verificar acceso al módulo Operaciones (Código 11 para Jefe de Operaciones)
//verificarAccesoModulo('operaciones');
//verificarAccesoCargo([11]);

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Obtener todas las sucursales (el jefe de operaciones puede ver todas)
$sucursales = obtenerTodasSucursales();

// Procesar aprobación/denegación de faltas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gestionar_falta'])) {
    procesarGestionFalta();
}

// Obtener datos para los filtros
$sucursalSeleccionada = $_GET['sucursal'] ?? ($sucursales[0]['codigo'] ?? null);
$fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');

// Obtener faltas si hay sucursal y fechas seleccionadas
$faltas = [];
if ($sucursalSeleccionada && $fechaDesde && $fechaHasta) {
    $faltas = obtenerFaltas($sucursalSeleccionada, $fechaDesde, $fechaHasta);
}

// Funciones auxiliares específicas para faltas
//function obtenerTodasSucursales() {
//    global $conn;
//    
//    $stmt = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE codigo NOT IN (14, 0) ORDER BY nombre");
//    $stmt->execute();
//    return $stmt->fetchAll();
//}

function obtenerFaltas($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    // 1. Obtener todos los operarios asignados a la sucursal en el rango de fechas
    $operarios = obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta);
    
    $resultados = [];
    
    foreach ($operarios as $operario) {
        // 2. Para cada operario, verificar días laborables en el rango
        $diasLaborables = obtenerDiasLaborablesOperario($operario['CodOperario'], $codSucursal, $fechaDesde, $fechaHasta);
        
        foreach ($diasLaborables as $dia) {
            // 3. Verificar si hay marcación de entrada para ese día
            $marcacion = obtenerMarcacionEntrada($operario['CodOperario'], $dia['fecha']);
            
            if (!$marcacion) {
                // 4. Si no hay marcación, es una falta potencial
                $faltaExistente = obtenerFaltaExistente($operario['CodOperario'], $dia['fecha']);
                
                $resultados[] = [
                    'cod_operario' => $operario['CodOperario'],
                    'nombre_operario' => $operario['Nombre'] . ' ' . $operario['Apellido'],
                    'fecha' => $dia['fecha'],
                    'sucursal_codigo' => $codSucursal,
                    'sucursal_nombre' => $operario['sucursal_nombre'],
                    'hora_entrada_programada' => $dia['hora_entrada'],
                    'hora_salida_programada' => $dia['hora_salida'],
                    'estado' => $faltaExistente['estado'] ?? 'No_Pagado',
                    'observaciones' => $faltaExistente['observaciones'] ?? null,
                    'id_falta' => $faltaExistente['id'] ?? null,
                    'id_horario' => $dia['id_horario']
                ];
            }
        }
    }
    
    return $resultados;
}

function obtenerOperariosSucursalEnRango($codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, s.nombre as sucursal_nombre
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        JOIN sucursales s ON anc.Sucursal = s.codigo
        WHERE anc.Sucursal = ?
        AND o.Operativo = 1
        AND (anc.Fin IS NULL OR anc.Fin >= ?)
        AND anc.Fecha <= ?
        ORDER BY o.Nombre, o.Apellido
    ");
    $stmt->execute([$codSucursal, $fechaDesde, $fechaHasta]);
    return $stmt->fetchAll();
}

function obtenerDiasLaborablesOperario($codOperario, $codSucursal, $fechaDesde, $fechaHasta) {
    global $conn;
    
    // Obtener todas las semanas que cubren el rango de fechas
    $stmt = $conn->prepare("
        SELECT * FROM SemanasSistema 
        WHERE fecha_inicio <= ? AND fecha_fin >= ?
    ");
    $stmt->execute([$fechaHasta, $fechaDesde]);
    $semanas = $stmt->fetchAll();
    
    $diasLaborables = [];
    
    foreach ($semanas as $semana) {
        // Obtener horario programado para esta semana
        $stmt = $conn->prepare("
            SELECT * FROM HorariosSemanalesOperaciones
            WHERE cod_operario = ? 
            AND cod_sucursal = ?
            AND id_semana_sistema = ?
            LIMIT 1
        ");
        $stmt->execute([$codOperario, $codSucursal, $semana['id']]);
        $horario = $stmt->fetch();
        
        if ($horario) {
            // Verificar cada día de la semana
            $dias = [
                'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 
                'jueves' => 4, 'viernes' => 5, 'sabado' => 6, 'domingo' => 7
            ];
            
            foreach ($dias as $dia => $diaNumero) {
                $columnaEstado = $dia . '_estado';
                $columnaEntrada = $dia . '_entrada';
                $columnaSalida = $dia . '_salida';
                
                if ($horario[$columnaEstado] === 'Activo' && $horario[$columnaEntrada] !== null) {
                    // Calcular fecha del día específico
                    $fechaDia = date('Y-m-d', strtotime($semana['fecha_inicio'] . ' + ' . ($diaNumero - 1) . ' days'));
                    
                    // Verificar si la fecha está dentro del rango solicitado
                    if ($fechaDia >= $fechaDesde && $fechaDia <= $fechaHasta) {
                        $diasLaborables[] = [
                            'fecha' => $fechaDia,
                            'hora_entrada' => $horario[$columnaEntrada],
                            'hora_salida' => $horario[$columnaSalida],
                            'id_horario' => $horario['id']
                        ];
                    }
                }
            }
        }
    }
    
    return $diasLaborables;
}

function obtenerMarcacionEntrada($codOperario, $fecha) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM marcaciones 
        WHERE CodOperario = ? 
        AND fecha = ?
        AND hora_ingreso IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fecha]);
    return $stmt->fetch();
}

function obtenerFaltaExistente($codOperario, $fecha) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT id, estado, observaciones 
        FROM Faltas 
        WHERE cod_operario = ? AND fecha = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $fecha]);
    return $stmt->fetch();
}

function procesarGestionFalta() {
    global $conn;
    
    try {
        $idFalta = $_POST['id_falta'] ?? null;
        $codOperario = (int)$_POST['cod_operario'];
        $fecha = $_POST['fecha'];
        $codSucursal = $_POST['cod_sucursal']; // Ahora es varchar(10)
        $estado = $_POST['estado'];
        $observaciones = $_POST['observaciones'] ?? null;
        $horaEntradaProgramada = $_POST['hora_entrada_programada'];
        $horaSalidaProgramada = $_POST['hora_salida_programada'];
        $idHorario = $_POST['id_horario'] ? (int)$_POST['id_horario'] : null;
        
        if ($idFalta) {
            // Actualizar registro existente
            $stmt = $conn->prepare("
                UPDATE Faltas 
                SET estado = ?, observaciones = ?, 
                    hora_entrada_programada = ?, hora_salida_programada = ?,
                    id_horario_programado = ?, actualizado_por = ?, 
                    fecha_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $estado, $observaciones, 
                $horaEntradaProgramada, $horaSalidaProgramada,
                $idHorario, $_SESSION['usuario_id'],
                $idFalta
            ]);
        } else {
            // Crear nuevo registro
            $stmt = $conn->prepare("
                INSERT INTO Faltas (
                    cod_operario, fecha, cod_sucursal, estado, observaciones,
                    hora_entrada_programada, hora_salida_programada, id_horario_programado,
                    creado_por, actualizado_por
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $codOperario, $fecha, $codSucursal, $estado, $observaciones,
                $horaEntradaProgramada, $horaSalidaProgramada, $idHorario,
                $_SESSION['usuario_id'], $_SESSION['usuario_id']
            ]);
        }
        
        $_SESSION['exito'] = 'Registro de falta actualizado correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al procesar la falta: ' . $e->getMessage();
    }
    
    header('Location: faltas.php?' . http_build_query([
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
    <title>Faltas - Operaciones</title>
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
        
        .status-vacaciones {
            color: #0c5460;
            background-color: #d1ecf1;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        
        .status-subsidio {
            color: #856404;
            background-color: #fff3cd;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        
        .status-dias_mas_septimo {
            color: #155724;
            background-color: #d4edda;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        
        .status-no_pagado {
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
            <h1 class="title">Gestión de Faltas</h1>
            <div class="user-info">
                <span><?= htmlspecialchars($usuario['Nombre'] . ' ' . $usuario['Apellido']) ?></span>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Regresar a Módulo
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
            <?php if (!empty($faltas)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Fecha</th>
                            <th>Hora Entrada Programada</th>
                            <th>Hora Salida Programada</th>
                            <th>Status</th>
                            <th>Observaciones</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faltas as $falta): ?>
                            <tr>
                                <td><?= htmlspecialchars($falta['nombre_operario']) ?></td>
                                <td><?= formatoFechaCorta($falta['fecha']) ?></td>
                                <td><?= $falta['hora_entrada_programada'] ? formatoHoraAmPm($falta['hora_entrada_programada']) : '-' ?></td>
                                <td><?= $falta['hora_salida_programada'] ? formatoHoraAmPm($falta['hora_salida_programada']) : '-' ?></td>
                                <td>
                                    <span class="status-<?= strtolower($falta['estado']) ?>">
                                        <?= str_replace('_', ' ', $falta['estado']) ?>
                                    </span>
                                </td>
                                <td><?= $falta['observaciones'] ? htmlspecialchars($falta['observaciones']) : '-' ?></td>
                                <td style="text-align: center;">
                                    <button type="button" onclick="mostrarModalGestionFalta(
                                        <?= $falta['id_falta'] ?? 'null' ?>, 
                                        '<?= htmlspecialchars($falta['nombre_operario']) ?>', 
                                        '<?= htmlspecialchars($falta['sucursal_nombre']) ?>', 
                                        '<?= $falta['fecha'] ?>', 
                                        '<?= $falta['hora_entrada_programada'] ?>', 
                                        '<?= $falta['hora_salida_programada'] ?>', 
                                        '<?= $falta['estado'] ?>', 
                                        '<?= htmlspecialchars($falta['observaciones'] ?? '') ?>',
                                        <?= $falta['cod_operario'] ?>,
                                        <?= $falta['sucursal_codigo'] ?>,
                                        <?= $falta['id_horario'] ?>
                                    )" class="btn btn-info">
                                        <!-- <i class="fas fa-edit"></i> <?= $falta['id_falta'] ? 'Modificar' : 'Gestionar' ?> -->
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <?php if ($sucursalSeleccionada && $fechaDesde && $fechaHasta): ?>
                        No se encontraron faltas para los filtros seleccionados.
                    <?php else: ?>
                        Seleccione una sucursal y rango de fechas para buscar faltas.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal para gestión de faltas -->
    <div class="modal" id="modalGestionFalta">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Gestionar Falta</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formGestionFalta" method="post">
                <input type="hidden" name="gestionar_falta" value="1">
                <input type="hidden" id="id_falta" name="id_falta">
                <input type="hidden" id="cod_operario" name="cod_operario">
                <input type="hidden" id="cod_sucursal" name="cod_sucursal">
                <input type="hidden" id="fecha" name="fecha">
                <input type="hidden" id="id_horario" name="id_horario">
                <input type="hidden" id="hora_entrada_programada" name="hora_entrada_programada">
                <input type="hidden" id="hora_salida_programada" name="hora_salida_programada">
                
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
                        <span class="info-value" id="modal-hora-entrada"></span>
                    </div>
                    
                    <div class="info-group">
                        <span class="info-label">Hora Salida Programada:</span>
                        <span class="info-value" id="modal-hora-salida"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado" class="form-label">Estado:</label>
                        <select id="estado" name="estado" class="form-select" required>
                            <option value="No_Pagado">No Pagado</option>
                            <option value="Vacaciones">Vacaciones</option>
                            <option value="Subsidio">Subsidio</option>
                            <option value="Dias_mas_septimo">Días más el séptimo</option>
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
                window.location.href = 'faltas.php?' + new URLSearchParams({
                    sucursal: sucursal,
                    desde: desde,
                    hasta: hasta
                });
            }
        }
        
        // Mostrar modal de gestión de falta
        function mostrarModalGestionFalta(idFalta, nombre, sucursal, fecha, horaEntrada, horaSalida, estado, observaciones, codOperario, codSucursal, idHorario) {
            document.getElementById('id_falta').value = idFalta || '';
            document.getElementById('cod_operario').value = codOperario;
            document.getElementById('cod_sucursal').value = codSucursal;
            document.getElementById('fecha').value = fecha;
            document.getElementById('id_horario').value = idHorario;
            document.getElementById('hora_entrada_programada').value = horaEntrada;
            document.getElementById('hora_salida_programada').value = horaSalida;
            
            document.getElementById('modal-nombre').textContent = nombre;
            document.getElementById('modal-sucursal').textContent = sucursal;
            document.getElementById('modal-fecha').textContent = new Date(fecha).toLocaleDateString('es-ES');
            document.getElementById('modal-hora-entrada').textContent = horaEntrada ? formatoHoraAmPm(horaEntrada) : '-';
            document.getElementById('modal-hora-salida').textContent = horaSalida ? formatoHoraAmPm(horaSalida) : '-';
            
            document.getElementById('estado').value = estado;
            document.getElementById('observaciones').value = observaciones || '';
            
            document.getElementById('modalGestionFalta').style.display = 'flex';
        }
        
        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modalGestionFalta').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera del contenido
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('modalGestionFalta');
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