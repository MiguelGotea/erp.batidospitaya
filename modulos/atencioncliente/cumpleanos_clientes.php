<?php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
// Incluir el menú lateral
require_once '../../includes/menu_lateral.php';
// Incluir el header universal
require_once '../../includes/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
// Obtener cargo del operario para el menú
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

//if (!verificarAccesoCargo([22, 16, 28, 42, 26]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
//    header('Location: ../index.php');
//    exit();
//}
if (!tienePermiso('cumpleanos_club_pitaya', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Procesar filtros
$mesSeleccionado = isset($_GET['mes']) ? intval($_GET['mes']) : null;
$diasSeleccionados = isset($_GET['dias']) ? $_GET['dias'] : [];
if (!is_array($diasSeleccionados)) {
    $diasSeleccionados = [$diasSeleccionados];
}
$diasSeleccionados = array_filter(array_map('intval', $diasSeleccionados));

// Función para normalizar nombres
function normalizarNombre($nombre) {
    // Eliminar espacios al inicio y final
    $nombre = trim($nombre);
    // Eliminar múltiples espacios entre palabras
    $nombre = preg_replace('/\s+/', ' ', $nombre);
    // Tomar solo el primer nombre (eliminar segundos nombres si existen)
    $partes = explode(' ', $nombre);
    $primerNombre = $partes[0];
    // Convertir a formato "Primera letra mayúscula, resto minúsculas"
    return ucfirst(strtolower($primerNombre));
}

// Consulta para obtener clientes según filtros
$clientes = [];
$clientesInvalidos = [];
$todosClientesValidos = []; // Para almacenar todos los clientes válidos (para copiar)

if ($mesSeleccionado && !empty($diasSeleccionados)) {
    // Construir condición para días
    $condicionesDias = [];
    foreach ($diasSeleccionados as $dia) {
        $condicionesDias[] = "DAY(fecha_nacimiento) = $dia";
    }
    $condicionDias = implode(' OR ', $condicionesDias);

    $query = "
        SELECT 
            membresia, 
            nombre, 
            apellido, 
            celular, 
            nombre_sucursal, 
            fecha_nacimiento, 
            fecha_registro
        FROM clientesclub
        WHERE MONTH(fecha_nacimiento) = :mes
        AND ($condicionDias)
        ORDER BY DAY(fecha_nacimiento), nombre, apellido
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':mes', $mesSeleccionado, PDO::PARAM_INT);
    $stmt->execute();
    
    // Separar clientes válidos e inválidos
    while ($cliente = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Normalizar nombre
        $cliente['nombre'] = normalizarNombre($cliente['nombre']);
        
        // Validar celular (8 dígitos, sin espacios ni caracteres especiales)
        $celular = preg_replace('/[^0-9]/', '', $cliente['celular']);
        if (strlen($celular) === 8) {
            $cliente['celular'] = $celular;
            $clientes[] = $cliente;
            $todosClientesValidos[] = $cliente; // Agregar a la lista completa
        } else {
            $clientesInvalidos[] = $cliente;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cumpleaños de Clientes - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(12px, 2vw, 18px) !important;
        }
      
        body {
            background-color: #F6F6F6;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .filtros-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filtro-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }
        
        select, .dias-container {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }
        
        .dias-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px;
        }
        
        .dia-checkbox {
            display: none;
        }
        
        .dia-label {
            padding: 8px 15px;
            background-color: #f0f0f0;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .dia-checkbox:checked + .dia-label {
            background-color: #51B8AC;
            color: white;
        }
        
        .btn-filtrar {
            background-color: #51B8AC;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
            margin-right: 10px;
        }
        
        .btn-limpiar {
            background-color: #d9534f;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .btn-filtrar:hover {
            background-color: #0E544C;
        }
        
        .btn-limpiar:hover {
            background-color: #c9302c;
        }
        
        .botones-accion {
            display: flex;
            margin-top: 15px;
        }
        
        .tabla-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #51B8AC;
            color: white;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .btn-copiar {
            background-color: #0E544C;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .btn-copiar:hover {
            background-color: #08332e;
        }
        
        .clientes-invalidos {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
        }
        
        .clientes-invalidos h3 {
            color: #d9534f;
            margin-bottom: 15px;
        }
        
        .no-resultados {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        .contador-clientes {
            margin-bottom: 15px;
            font-weight: bold;
            color: #0E544C;
        }
        
        .btn-regresar {
            display: inline-block;
            margin-top: 15px;
        }
        
        .btn-regresar i {
            margin-right: 5px;
        }
    </style>
</head>

<body>
    
    <!-- Renderizar menú lateral -->
    <?php echo renderMenuLateral($cargoOperario); ?>
    
    <!-- Contenido principal -->
    <div class="main-container">   <!-- ya existe en el css de menu lateral -->
        <div class="contenedor-principal"> <!-- ya existe en el css de menu lateral -->
            <!-- todo el contenido existente -->
            <!-- Renderizar header universal -->
            <?php echo renderHeader($usuario, $esAdmin, 'Cumpleaños de Clientes Club'); ?>
            <div class="container">
                
                <div class="filtros-container">
                    <form method="GET" action="" id="formFiltros">
                        <div class="filtro-group">
                            <label for="mes">Mes de Cumpleaños:</label>
                            <select name="mes" id="mes" required>
                                <option value="">Seleccione un mes</option>
                                <option value="1" <?= $mesSeleccionado == 1 ? 'selected' : '' ?>>Enero</option>
                                <option value="2" <?= $mesSeleccionado == 2 ? 'selected' : '' ?>>Febrero</option>
                                <option value="3" <?= $mesSeleccionado == 3 ? 'selected' : '' ?>>Marzo</option>
                                <option value="4" <?= $mesSeleccionado == 4 ? 'selected' : '' ?>>Abril</option>
                                <option value="5" <?= $mesSeleccionado == 5 ? 'selected' : '' ?>>Mayo</option>
                                <option value="6" <?= $mesSeleccionado == 6 ? 'selected' : '' ?>>Junio</option>
                                <option value="7" <?= $mesSeleccionado == 7 ? 'selected' : '' ?>>Julio</option>
                                <option value="8" <?= $mesSeleccionado == 8 ? 'selected' : '' ?>>Agosto</option>
                                <option value="9" <?= $mesSeleccionado == 9 ? 'selected' : '' ?>>Septiembre</option>
                                <option value="10" <?= $mesSeleccionado == 10 ? 'selected' : '' ?>>Octubre</option>
                                <option value="11" <?= $mesSeleccionado == 11 ? 'selected' : '' ?>>Noviembre</option>
                                <option value="12" <?= $mesSeleccionado == 12 ? 'selected' : '' ?>>Diciembre</option>
                            </select>
                        </div>
                        
                        <div class="filtro-group">
                            <label>Días de Cumpleaños:</label>
                            <div class="dias-container">
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                    <input type="checkbox" 
                                           class="dia-checkbox" 
                                           id="dia-<?= $i ?>" 
                                           name="dias[]" 
                                           value="<?= $i ?>"
                                           <?= in_array($i, $diasSeleccionados) ? 'checked' : '' ?>>
                                    <label for="dia-<?= $i ?>" class="dia-label"><?= $i ?></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="botones-accion">
                            <button type="submit" class="btn-filtrar">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <button type="button" id="btnLimpiar" class="btn-limpiar">
                                <i class="fas fa-broom"></i> Limpiar Filtros
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php if ($mesSeleccionado && !empty($diasSeleccionados)): ?>
                    <div class="tabla-container">
                        <?php if (!empty($clientes)): ?>
                            <div class="contador-clientes">
                                Total de clientes válidos: <?= count($todosClientesValidos) ?>
                            </div>
                            
                            <?php if (tienePermiso('cumpleanos_club_pitaya','csv_wasender',$cargoOperario)): ?>
                            <button id="btnCopiar" class="btn-copiar">
                                <i class="fas fa-copy"></i> Copiar todos los clientes válidos al portapapeles
                            </button>
                            <?php endif; ?>
                            
                            <table id="tablaClientes">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Nombre</th>
                                        <th>Apellido</th>
                                        <th>Celular</th>
                                        <th>Sucursal</th>
                                        <th>Fecha Cumpleaños</th>
                                        <th>Fecha Inscripción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($cliente['membresia']) ?></td>
                                            <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                                            <td><?= htmlspecialchars($cliente['apellido']) ?></td>
                                            <td><?= htmlspecialchars($cliente['celular']) ?></td>
                                            <td><?= htmlspecialchars($cliente['nombre_sucursal']) ?></td>
                                            <td><?= formatoFecha($cliente['fecha_nacimiento']) ?></td>
                                            <td><?= formatoFecha($cliente['fecha_registro']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-resultados">
                                No se encontraron clientes con los filtros aplicados.
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($clientesInvalidos)): ?>
                            <div class="clientes-invalidos">
                                <h3>Clientes con datos inválidos (no se pueden copiar)</h3>
                                <div class="contador-clientes">
                                    Total de clientes inválidos: <?= count($clientesInvalidos) ?>
                                </div>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Apellido</th>
                                            <th>Celular</th>
                                            <th>Sucursal</th>
                                            <th>Fecha Cumpleaños</th>
                                            <th>Fecha Inscripción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($clientesInvalidos as $cliente): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($cliente['membresia']) ?></td>
                                                <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                                                <td><?= htmlspecialchars($cliente['apellido']) ?></td>
                                                <td><?= htmlspecialchars($cliente['celular']) ?></td>
                                                <td><?= htmlspecialchars($cliente['nombre_sucursal']) ?></td>
                                                <td><?= formatoFecha($cliente['fecha_nacimiento']) ?></td>
                                                <td><?= formatoFecha($cliente['fecha_registro']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="tabla-container">
                        <div class="no-resultados">
                            Seleccione un mes y al menos un día para mostrar los resultados.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div> <!-- cierre contenedor-principal -->
    </div> <!-- cierre main-container -->
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar DataTable si hay resultados
            <?php if (!empty($clientes)): ?>
                $('#tablaClientes').DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                    },
                    dom: '<"top"lf>rt<"bottom"ip>',
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]]
                });
            <?php endif; ?>
            
            // Botón para limpiar filtros
            $('#btnLimpiar').click(function() {
                // Resetear el formulario
                $('#formFiltros')[0].reset();
                // Enviar el formulario (recargar sin filtros)
                window.location.href = window.location.pathname;
            });
            
            // Función para copiar al portapapeles (todos los clientes válidos)
            $('#btnCopiar').click(function() {
                let datosParaCopiar = '';
                
                // Usamos los datos de PHP (todosClientesValidos) en lugar de los de la tabla HTML
                <?php foreach ($todosClientesValidos as $cliente): ?>
                    datosParaCopiar += `<?= $cliente['celular'] ?>\t<?= $cliente['nombre'] ?>\t<?= $cliente['nombre_sucursal'] ?>\n`;
                <?php endforeach; ?>
                
                // Crear elemento temporal para copiar
                const elementoTemporal = $('<textarea>');
                $('body').append(elementoTemporal);
                elementoTemporal.val(datosParaCopiar).select();
                document.execCommand('copy');
                elementoTemporal.remove();
                
                // Mostrar notificación con cantidad copiada
                alert(`Se copiaron <?= count($todosClientesValidos) ?> registros al portapapeles:\nCelular, Nombre, Sucursal`);
            });
        });
    </script>
</body>
</html>