<?php
// Incluir configuración y verificar autenticación
require_once '../auth.php';
require_once '../funciones.php';
require_once 'config.php';

//******************************Estándar para header******************************
verificarAutenticacion();

// Obtener información del usuario actual
$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo 'supervision'
verificarAccesoCargo([8, 11, 16, 21]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 11, 21, 16]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../../../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

date_default_timezone_set('America/Managua');

function nombreMes($mes) {
    $meses = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", 
              "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
    return $meses[$mes];
}

try {
    //$conn = new mysqli($host, $username, $password, $dbname);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Crear o actualizar tabla personal_auditorias (nueva tabla para no modificar Operarios)
    $conn->query("
        CREATE TABLE IF NOT EXISTS `personal_auditorias` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `cod_operario` int(11) NOT NULL,
            `sucursal_id` int(11) NOT NULL,
            `peso_porcentual` DECIMAL(5,2) DEFAULT 0.00,
            `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
            `mes` TINYINT(2) NOT NULL DEFAULT ".date('n').",
            `anio` SMALLINT(4) NOT NULL DEFAULT ".date('Y').",
            `activo` TINYINT(1) DEFAULT 1,
            PRIMARY KEY (`id`),
            KEY `sucursal_id` (`sucursal_id`),
            CONSTRAINT `personal_auditorias_ibfk_1` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Procesar formularios
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['agregar_personal'])) {
            $sucursal_id = intval($_POST['sucursal_id']);
            $cod_operario = intval($_POST['cod_operario']);
            $peso_porcentual = floatval($_POST['peso_porcentual']);
            $mes = intval($_POST['mes'] ?? date('n'));
            $anio = intval($_POST['anio'] ?? date('Y'));
            
            // Obtener nombre del operario
            $operario = $conn->query("SELECT Nombre, Apellido FROM Operarios WHERE CodOperario = $cod_operario")->fetch_assoc();
            $nombre_completo = trim($operario['Nombre'] . ' ' . $operario['Apellido']);
            
            if ($peso_porcentual < 0 || $peso_porcentual > 100) {
                $_SESSION['error_message'] = "El peso debe ser entre 0 y 100";
            } else {
                $stmt = $conn->prepare("INSERT INTO personal_auditorias (cod_operario, sucursal_id, peso_porcentual, mes, anio) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iidii", $cod_operario, $sucursal_id, $peso_porcentual, $mes, $anio);
                $stmt->execute();
                $_SESSION['success_message'] = $stmt->affected_rows > 0 ? "Personal agregado" : "Error al agregar";
                $stmt->close();
            }
        }
        elseif (isset($_POST['editar_peso'])) {
            $id = intval($_POST['id']);
            $peso_porcentual = floatval($_POST['peso_porcentual']);
            
            if ($peso_porcentual >= 0 && $peso_porcentual <= 100) {
                $conn->query("UPDATE personal_auditorias SET peso_porcentual = $peso_porcentual WHERE id = $id");
                $_SESSION['success_message'] = "Peso actualizado";
            } else {
                $_SESSION['error_message'] = "El peso debe ser entre 0 y 100";
            }
        }
        elseif (isset($_POST['cambiar_estado'])) {
            $id = intval($_POST['id']);
            $nuevo_estado = intval($_POST['nuevo_estado']);
            $conn->query("UPDATE personal_auditorias SET activo = $nuevo_estado WHERE id = $id");
            $_SESSION['success_message'] = "Estado actualizado";
        }
        elseif (isset($_POST['eliminar_personal'])) {
            $id = intval($_POST['id']);
            $conn->query("DELETE FROM personal_auditorias WHERE id = $id");
            $_SESSION['success_message'] = "Personal eliminado";
        }
        elseif (isset($_POST['migrar_mes'])) {
            $mes_origen = intval($_POST['mes_origen']);
            $anio_origen = intval($_POST['anio_origen']);
            
            // Calcular mes/año destino (siguiente mes)
            if ($mes_origen == 12) {
                $mes_destino = 1;
                $anio_destino = $anio_origen + 1;
            } else {
                $mes_destino = $mes_origen + 1;
                $anio_destino = $anio_origen;
            }
            
            // Migrar solo empleados activos que no existan en el destino
            $conn->query("
                INSERT INTO personal_auditorias (cod_operario, sucursal_id, peso_porcentual, mes, anio, activo)
                SELECT p.cod_operario, p.sucursal_id, p.peso_porcentual, $mes_destino, $anio_destino, 1
                FROM personal_auditorias p
                WHERE p.mes = $mes_origen 
                AND p.anio = $anio_origen 
                AND p.activo = 1
                AND NOT EXISTS (
                    SELECT 1 FROM personal_auditorias 
                    WHERE mes = $mes_destino 
                    AND anio = $anio_destino 
                    AND cod_operario = p.cod_operario
                    AND sucursal_id = p.sucursal_id
                )
            ");
            
            $afectados = $conn->affected_rows;
            if ($afectados > 0) {
                $_SESSION['success_message'] = "Se migraron $afectados empleados a ".nombreMes($mes_destino)." $anio_destino";
            } else {
                $_SESSION['warning_message'] = "Todos los empleados activos ya existen en el mes destino";
            }
        }
        
        header('Location: personal.php'.(isset($_GET['mes']) ? '?mes='.$_GET['mes'].'&anio='.$_GET['anio'] : ''));
        exit;
    }

    // Obtener parámetros de filtrado
    $mes_filtro = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
    $anio_filtro = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

    // Obtener sucursales (de la tabla sucursales)
    $sucursales = $conn->query("SELECT id, nombre FROM sucursales WHERE activa = 1 ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
    
    // Obtener operarios (de la tabla Operarios)
    $operarios = $conn->query("SELECT CodOperario, Nombre, Apellido FROM Operarios WHERE Operativo = 1 ORDER BY Nombre, Apellido")->fetch_all(MYSQLI_ASSOC);
    
    // Obtener personal para mostrar (con joins a las nuevas tablas)
    $personal = $conn->query("
        SELECT pa.*, 
               s.nombre as sucursal,
               o.Nombre as nombre_operario,
               o.Apellido as apellido_operario
        FROM personal_auditorias pa 
        JOIN sucursales s ON pa.sucursal_id = s.id
        JOIN Operarios o ON pa.cod_operario = o.CodOperario
        WHERE pa.mes = $mes_filtro AND pa.anio = $anio_filtro
        ORDER BY s.nombre, o.Nombre, o.Apellido
    ")->fetch_all(MYSQLI_ASSOC);

    // Obtener meses/años disponibles para filtrado
    $periodos = $conn->query("
        SELECT DISTINCT mes, anio 
        FROM personal_auditorias 
        ORDER BY anio DESC, mes DESC
    ")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Administración de Personal</title>
    <link href="https://fonts.googleapis.com/css2?family=Calibri&display=swap" rel="stylesheet">
    <link rel="icon" href="icon12.png" type="image/png">
    <style>
        *{
            font-size: clamp(11px, 2vw, 16px) !important;
            font-family: 'Calibri', sans-serif;
        }
        
        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 10px;
            display: flex;
            width: 95%;
            overflow-x: hidden;
        }
        .container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px -20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-content {
            display: flex;
            align-items: center;
            width: 100%;
            justify-content: space-between;
        }
        .logo {
            height: 50px;
        }
        h1 {
            color: black;
            margin: 0;
            flex-grow: 1;
            text-align: center;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background-color: #0E544C;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-special {
            background-color: #0E544C;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-info {
            background-color: #0E544C;
        }
        .btn:hover {
            opacity: 0.9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #0E544C;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .inactivo {
            background-color: #ffecec;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .invalid {
            border: 1px solid red !important;
        }
        .btn-cancelar {
            background-color: #6c757d !important;
            color: white !important;
        }
        .btn-cancelar:hover {
            background-color: #5a6268 !important;
            color: white !important;
        }
        .form-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .form-title {
            margin-top: 0;
            color: #0E544C;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
        }
        .close {
            color: #aaa;
            float: right;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .filtro-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: flex-end;
        }
        .filtro-item {
            flex: 1;
        }
        /* Estilos nuevos para acciones compactas */
        .acciones-container {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
        }
        .btn-compact {
            padding: 5px 8px;
            min-width: 60px;
        }
        .btn-icon {
            width: 30px;
            padding: 5px;
            text-align: center;
        }
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .container {
                padding: 10px;
            }
            table {
                display: block;
                overflow-x: auto;
            }
            th, td {
                padding: 5px;
            }
            .header-content {
                flex-direction: column;
                align-items: center;
            }
            .logo {
                margin-bottom: 10px;
            }
            h1 {
                text-align: center;
            }
            .modal-content {
                width: 90%;
                margin: 30% auto;
            }
            .filtro-container {
                flex-direction: column;
                gap: 15px;
            }
            .filtro-item {
                width: 100%;
            }
            .filtro-item button {
                width: 100%;
                margin-top: 5px;
            }
            .btn {
                padding: 8px;
            }
        }
        @media (max-width: 480px) {
            .form-group div {
                flex-direction: column;
            }
            .btn {
                padding: 8px 12px;
            }
            td {
                white-space: nowrap;
            }
            .acciones-container {
                flex-direction: column;
            }
            .btn-compact {
                width: 100%;
            }
        }
        
        .btn-container {
            display: flex;
            flex-wrap: wrap; /* permite que los botones bajen si no hay espacio */
            gap: 10px; /* espacio entre botones */
            justify-content: center; /* o center / flex-start según necesidad */
        }
        
        .btn {
            flex: 1 1 45%; /* permite que crezcan, se encojan, y tengan base de 45% */
            min-width: 120px; /* asegura que no se hagan muy pequeños */
            padding: 10px 20px;
            font-weight: bold;
            box-sizing: border-box;
        }
        
        @media (max-width: 600px) {
            .btn {
              flex: 1 1 100%; /* en pantallas pequeñas, cada botón ocupa una línea */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <img src="Logo.svg" alt="Logo" class="logo">
                <h1>Administración de Personal</h1>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success"><?= $_SESSION['success_message'] ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error"><?= $_SESSION['error_message'] ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="message warning"><?= $_SESSION['warning_message'] ?></div>
            <?php unset($_SESSION['warning_message']); ?>
        <?php endif; ?>
        
        <div class="form-container" style="display:none;">
    <h3 class="form-title">Agregar Nuevo Personal</h3>
    <form id="personalForm" method="post">
        <div class="form-group">
            <label for="sucursal_id">Sucursal:</label>
            <select id="sucursal_id" name="sucursal_id" required>
                <option value="">Seleccione una sucursal</option>
                <?php foreach ($sucursales as $sucursal): ?>
                    <option value="<?= $sucursal['id'] ?>"><?= htmlspecialchars($sucursal['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="cod_operario">Operario:</label>
            <select id="cod_operario" name="cod_operario" required>
                <option value="">Seleccione un operario</option>
                <?php foreach ($operarios as $operario): ?>
                    <option value="<?= $operario['CodOperario'] ?>">
                        <?= htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="peso_porcentual">Peso Porcentual (%):</label>
            <input type="number" id="peso_porcentual" name="peso_porcentual" min="0" max="100" step="0.01" value="0" required>
        </div>
        
        <input type="hidden" name="mes" value="<?= $mes_filtro ?>">
        <input type="hidden" name="anio" value="<?= $anio_filtro ?>">
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn" name="agregar_personal">Agregar</button>
            <button type="button" class="btn btn-cancelar" onclick="window.location.href='auditorias_consolidadas.php'">Regresar</button>
        </div>
    </form>
</div>
        
        <div class="form-container">
            <h3 class="form-title">Migrar al Siguiente Mes</h3>
            <form id="migracionForm" method="post">
                <input type="hidden" name="mes_origen" value="<?= $mes_filtro ?>">
                <input type="hidden" name="anio_origen" value="<?= $anio_filtro ?>">
                
                <div class="form-group">
                    <label>Mes/Año Origen:</label>
                    <div style="background-color: #e9ecef; padding: 8px; border-radius: 4px;">
                        <?= nombreMes($mes_filtro) ?> <?= $anio_filtro ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Mes/Año Destino:</label>
                    <div style="background-color: #e9ecef; padding: 8px; border-radius: 4px;">
                        <?php 
                        if ($mes_filtro == 12) {
                            echo nombreMes(1)." ".($anio_filtro + 1);
                        } else {
                            echo nombreMes($mes_filtro + 1)." ".$anio_filtro;
                        }
                        ?>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-special" name="migrar_mes">Migrar Empleados Activos</button>
                <p style="font-size: 0.9em; margin-top: 10px; color: #6c757d;">
                    Solo se migrarán los empleados activos que no existan en el mes destino.
                </p>
            </form>
        </div>
        
        <div class="form-container">
            <h3 class="form-title">Filtrar por Periodo</h3>
            <form method="get">
                <div class="filtro-container">
                    <div class="filtro-item">
                        <label for="mes">Mes:</label>
                        <select id="mes" name="mes" required>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $i == $mes_filtro ? 'selected' : '' ?>><?= nombreMes($i) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="filtro-item">
                        <label for="anio">Año:</label>
                        <input type="number" id="anio" name="anio" min="2020" max="2030" value="<?= $anio_filtro ?>" required>
                    </div>
                    <div class="filtro-item" style="display: flex; gap: 10px;">
                        <button type="submit" class="btn" style="flex: 1;">Filtrar</button>
                        <button type="button" class="btn btn-info" style="flex: 1;" onclick="window.location.href='personal.php'">Mes Actual</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="table-responsive">
            <h3>Listado de Personal (<?= nombreMes($mes_filtro) ?> <?= $anio_filtro ?>)</h3>
            <?php if (empty($personal)): ?>
                <p>No hay personal registrado este mes.</p>
            <?php else: ?>
                <table>
    <thead>
        <tr>
            <th>Sucursal</th>
            <th>Operario</th>
            <th>Peso %</th>
            <th style="width: 150px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($personal as $persona): ?>
            <tr class="<?= $persona['activo'] ? '' : 'inactivo' ?>">
                <td><?= htmlspecialchars($persona['sucursal']) ?></td>
                <td><?= htmlspecialchars($persona['nombre_operario'] . ' ' . $persona['apellido_operario']) ?></td>
                <td><?= number_format($persona['peso_porcentual'], 2) ?>%</td>
                <td>
                    <div class="acciones-container">
                        <button class="btn btn-compact" onclick="abrirModalEdicion(<?= $persona['id'] ?>, <?= $persona['peso_porcentual'] ?>)">Editar</button>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="id" value="<?= $persona['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-compact" name="eliminar_personal" onclick="return confirm('¿Eliminar este personal?')">Eliminar</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
            <?php endif; ?>
        </div>
        
        <div class="btn-container">
            <button type="button" class="btn btn-reset" onclick="window.location.href='auditorias_consolidadas.php'">Cancelar</button>
        </div>
    </div>

    <!-- Modal para edición -->
    <div id="modalEdicion" class="modal">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h3>Editar Peso Porcentual</h3>
            <form id="editPesoForm" method="post">
                <input type="hidden" id="editId" name="id">
                <div class="form-group">
                    <label for="editPeso">Peso (%):</label>
                    <input type="number" id="editPeso" name="peso_porcentual" min="0" max="100" step="0.01" required>
                </div>
                <div style="margin-top: 20px;">
                    <button type="button" class="btn btn-cancelar" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn" name="editar_peso">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para mensajes -->
    <div id="modalMensaje" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <span class="close" onclick="cerrarModalMensaje()">&times;</span>
            <h3 id="modalTitulo"></h3>
            <p id="modalContenido"></p>
            <div style="margin-top: 20px; text-align: right;">
                <button type="button" class="btn" onclick="cerrarModalMensaje()">Aceptar</button>
            </div>
        </div>
    </div>

    <script>
        // Funciones para los modales
        function abrirModalEdicion(id, peso) {
            document.getElementById('editId').value = id;
            document.getElementById('editPeso').value = peso;
            document.getElementById('modalEdicion').style.display = 'block';
        }

        function cerrarModal() {
            document.getElementById('modalEdicion').style.display = 'none';
        }

        function mostrarModal(titulo, mensaje) {
            document.getElementById('modalTitulo').textContent = titulo;
            document.getElementById('modalContenido').textContent = mensaje;
            document.getElementById('modalMensaje').style.display = 'block';
        }

        function cerrarModalMensaje() {
            document.getElementById('modalMensaje').style.display = 'none';
        }

        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            if (event.target == document.getElementById('modalEdicion')) {
                cerrarModal();
            }
            if (event.target == document.getElementById('modalMensaje')) {
                cerrarModalMensaje();
            }
        }

        // Validación específica para cada formulario
        document.addEventListener('DOMContentLoaded', function() {
            // Validación para formulario de agregar
            document.getElementById('personalForm').addEventListener('submit', function(e) {
                const nombre = document.getElementById('nombre').value;
                const peso = parseFloat(document.getElementById('peso_porcentual').value);
                
                if (/\d/.test(nombre)) {
                    e.preventDefault();
                    mostrarModal('Error', 'El nombre no puede contener números');
                    document.getElementById('nombre').focus();
                    return;
                }
                
                if (isNaN(peso) || peso < 0 || peso > 100) {
                    e.preventDefault();
                    mostrarModal('Error', 'El peso porcentual debe estar entre 0 y 100');
                    document.getElementById('peso_porcentual').focus();
                }
            });

            // Validación para formulario de edición
            document.getElementById('editPesoForm').addEventListener('submit', function(e) {
                const peso = parseFloat(document.getElementById('editPeso').value);
                if (isNaN(peso) || peso < 0 || peso > 100) {
                    e.preventDefault();
                    mostrarModal('Error', 'El peso porcentual debe estar entre 0 y 100');
                    document.getElementById('editPeso').focus();
                }
            });
        });
    </script>
</body>
</html>