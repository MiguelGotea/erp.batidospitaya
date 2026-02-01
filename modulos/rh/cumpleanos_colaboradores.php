require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso mediante sistema de permisos
if (!tienePermiso('cumpleanos_colaboradores', 'vista', $cargoOperario)) {
header('Location: /index.php');
exit();
}

// Procesar filtros - SOLO por mes, sin año
$mesSeleccionado = isset($_GET['mes']) ? intval($_GET['mes']) : null;

// Función para normalizar nombres
function normalizarNombre($nombre)
{
// Eliminar espacios al inicio y final
$nombre = trim($nombre);
// Eliminar múltiples espacios entre palabras
$nombre = preg_replace('/\s+/', ' ', $nombre);
// Convertir a formato "Primera letra mayúscula, resto minúsculas"
return ucfirst(strtolower($nombre));
}

// Consulta para obtener colaboradores según filtros
$colaboradores = [];
$colaboradoresInvalidos = [];
$todosColaboradoresValidos = []; // Para almacenar todos los colaboradores válidos (para copiar)

if ($mesSeleccionado) {
// Depuración: mostrar el valor del filtro
error_log("Filtrando por mes: $mesSeleccionado");

$query = "
SELECT
o.CodOperario,
o.Nombre,
o.Nombre2,
o.Apellido,
o.Apellido2,
o.Celular,
o.Cumpleanos,
o.Cedula,
o.fecha_hora_regsys,
GROUP_CONCAT(DISTINCT s.nombre SEPARATOR ', ') as sucursales
FROM Operarios o
LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
LEFT JOIN sucursales s ON anc.Sucursal = s.codigo
WHERE MONTH(o.Cumpleanos) = :mes
AND o.Operativo = 1
GROUP BY o.CodOperario
ORDER BY DAY(o.Cumpleanos), o.Nombre, o.Apellido
";

error_log("Consulta SQL: $query");

try {
$stmt = $conn->prepare($query);
$stmt->bindParam(':mes', $mesSeleccionado, PDO::PARAM_INT);
$stmt->execute();

$count = $stmt->rowCount();
error_log("Número de resultados: $count");

// Separar colaboradores válidos e inválidos
while ($colaborador = $stmt->fetch(PDO::FETCH_ASSOC)) {
error_log("Procesando colaborador: " . $colaborador['CodOperario'] . " - " . $colaborador['Nombre']);

// Construir nombre completo
$nombreCompleto = trim($colaborador['Nombre'] . ' ' .
($colaborador['Nombre2'] ? $colaborador['Nombre2'] . ' ' : '') .
$colaborador['Apellido'] . ' ' .
($colaborador['Apellido2'] ? $colaborador['Apellido2'] : ''));

$colaborador['nombre_completo'] = normalizarNombre($nombreCompleto);

// Validar celular (8 dígitos, sin espacios ni caracteres especiales)
$celular = preg_replace('/[^0-9]/', '', $colaborador['Celular']);
if (strlen($celular) === 8) {
$colaborador['Celular'] = $celular;
$colaboradores[] = $colaborador;
$todosColaboradoresValidos[] = $colaborador; // Agregar a la lista completa
} else {
$colaboradoresInvalidos[] = $colaborador;
}
}
} catch (PDOException $e) {
error_log("Error en la consulta: " . $e->getMessage());
$error = "Error al ejecutar la consulta: " . $e->getMessage();
}
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cumpleaños de Colaboradores - Batidos Pitaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <style>
        .container-cumpleanos {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .filtros-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Cumpleaños de Colaboradores'); ?>

            <div class="container-fluid p-3">
                <div class="container-cumpleanos">

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

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

                    <?php if ($mesSeleccionado): ?>
                        <div class="tabla-container">
                            <?php if (!empty($colaboradores)): ?>
                                <div class="contador-colaboradores">
                                    Total de colaboradores válidos: <?= count($todosColaboradoresValidos) ?>
                                </div>

                                <button id="btnCopiar" class="btn-copiar">
                                    <i class="fas fa-copy"></i> Copiar todos los colaboradores válidos al portapapeles
                                </button>

                                <table id="tablaColaboradores">
                                    <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Celular</th>
                                            <th>Fecha Cumpleaños</th>
                                            <th>Cédula</th>
                                            <th>Sucursal(es)</th>
                                            <th>Fecha Registro</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($colaboradores as $colaborador): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($colaborador['CodOperario']) ?></td>
                                                <td><?= htmlspecialchars($colaborador['nombre_completo']) ?></td>
                                                <td><?= htmlspecialchars($colaborador['Celular']) ?></td>
                                                <td><?= formatoFecha($colaborador['Cumpleanos']) ?></td>
                                                <td><?= htmlspecialchars($colaborador['Cedula']) ?></td>
                                                <td><?= htmlspecialchars($colaborador['sucursales']) ?></td>
                                                <td><?= formatoFecha($colaborador['fecha_hora_regsys']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="no-resultados">
                                    No se encontraron colaboradores con cumpleaños en el mes seleccionado.
                                    <?php if ($mesSeleccionado): ?>
                                        <br><small>Mes:
                                            <?= DateTime::createFromFormat('!m', $mesSeleccionado)->format('F') ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($colaboradoresInvalidos)): ?>
                                <div class="colaboradores-invalidos">
                                    <h3>Colaboradores con datos inválidos (no se pueden copiar)</h3>
                                    <div class="contador-colaboradores">
                                        Total de colaboradores inválidos: <?= count($colaboradoresInvalidos) ?>
                                    </div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Nombre</th>
                                                <th>Celular</th>
                                                <th>Fecha Cumpleaños</th>
                                                <th>Cédula</th>
                                                <th>Sucursal(es)</th>
                                                <th>Fecha Registro</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($colaboradoresInvalidos as $colaborador): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($colaborador['CodOperario']) ?></td>
                                                    <td><?= htmlspecialchars($colaborador['nombre_completo']) ?></td>
                                                    <td><?= htmlspecialchars($colaborador['Celular']) ?></td>
                                                    <td><?= formatoFecha($colaborador['Cumpleanos']) ?></td>
                                                    <td><?= htmlspecialchars($colaborador['Cedula']) ?></td>
                                                    <td><?= htmlspecialchars($colaborador['sucursales']) ?></td>
                                                    <td><?= formatoFecha($colaborador['fecha_hora_regsys']) ?></td>
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
                                Seleccione un mes para mostrar los colaboradores que cumplen años en ese mes.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                <script>
                    $(document).ready(function () {
                        // Inicializar DataTable si hay resultados
                        <?php if (!empty($colaboradores)): ?>
                            $('#tablaColaboradores').DataTable({
                                language: {
                                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                                },
                                dom: '<"top"lf>rt<"bottom"ip>',
                                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]]
                            });
                        <?php endif; ?>

                        // Botón para limpiar filtros
                        $('#btnLimpiar').click(function () {
                            // Resetear el formulario
                            $('#formFiltros')[0].reset();
                            // Enviar el formulario (recargar sin filtros)
                            window.location.href = window.location.pathname;
                        });

                        // Función para copiar al portapapeles (todos los colaboradores válidos)
                        $('#btnCopiar').click(function () {
                            let datosParaCopiar = '';

                            // Usamos los datos de PHP (todosColaboradoresValidos) en lugar de los de la tabla HTML
                            <?php foreach ($todosColaboradoresValidos as $colaborador): ?>
                                datosParaCopiar += `<?= $colaborador['Celular'] ?>\t<?= $colaborador['nombre_completo'] ?>\t<?= $colaborador['sucursales'] ?>\n`;
                            <?php endforeach; ?>

                            // Crear elemento temporal para copiar
                            const elementoTemporal = $('<textarea>');
                            $('body').append(elementoTemporal);
                            elementoTemporal.val(datosParaCopiar).select();
                            document.execCommand('copy');
                            elementoTemporal.remove();

                            // Mostrar notificación con cantidad copiada
                            alert(`Se copiaron <?= count($todosColaboradoresValidos) ?> registros al portapapeles:\nCelular, Nombre, Sucursal(es)`);
                        });
                    });
                </script>
</body>

</html>