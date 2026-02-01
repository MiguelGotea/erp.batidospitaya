<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/permissions/permissions.php';

// Obtener usuario y cargo antes de verificar permisos
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo mediante el sistema de permisos
if (!tienePermiso('gestion_feriados', 'vista', $cargoOperario)) {
    header('Location: /index.php');
    exit();
}


// Obtener todos los departamentos para el filtro
$departamentos = obtenerTodosDepartamentos();

// Procesar formulario de edición/creación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $fecha = $_POST['fecha'];
    $nombre = trim($_POST['nombre']);
    $tipo = $_POST['tipo'];
    $departamento_codigo = $tipo === 'Departamental' ? $_POST['departamento_codigo'] : null;
    $recurrente = isset($_POST['recurrente']) ? 1 : 0;

    // Validaciones
    if (empty($fecha) || empty($nombre) || empty($tipo)) {
        $_SESSION['error'] = "Todos los campos obligatorios deben ser completados";
    } elseif ($tipo === 'Departamental' && empty($departamento_codigo)) {
        $_SESSION['error'] = "Debe seleccionar un departamento para feriados departamentales";
    } else {
        try {
            if ($id) {
                // Actualizar feriado existente
                $sql = "UPDATE feriadosnic SET fecha = ?, nombre = ?, tipo = ?, departamento_codigo = ?, recurrente = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$fecha, $nombre, $tipo, $departamento_codigo, $recurrente, $id]);
                $_SESSION['exito'] = "Feriado actualizado correctamente";
            } else {
                // Crear nuevo feriado
                $sql = "INSERT INTO feriadosnic (fecha, nombre, tipo, departamento_codigo, recurrente) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$fecha, $nombre, $tipo, $departamento_codigo, $recurrente]);
                $_SESSION['exito'] = "Feriado creado correctamente";
            }

            header('Location: editar_feriados.php');
            exit();
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) { // Duplicado
                $_SESSION['error'] = "Ya existe un feriado con la misma fecha y nombre para este departamento";
            } else {
                $_SESSION['error'] = "Error al guardar el feriado: " . $e->getMessage();
            }
        }
    }
}

// Procesar eliminación
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];

    try {
        $sql = "DELETE FROM feriadosnic WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id]);
        $_SESSION['exito'] = "Feriado eliminado correctamente";
        header('Location: editar_feriados.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar el feriado: " . $e->getMessage();
    }
}

// Obtener parámetros de filtro
$filtroTipo = $_GET['tipo'] ?? 'todos'; // 'nacional', 'departamental', 'todos'
$filtroDepartamento = $_GET['departamento'] ?? null;
$filtroAnio = $_GET['anio'] ?? date('Y');
$busqueda = $_GET['busqueda'] ?? '';

// Obtener feriados según filtros
$feriados = [];
$params = [];
$sql = "SELECT f.*, d.nombre as nombre_departamento FROM feriadosnic f 
        LEFT JOIN departamentos d ON CAST(f.departamento_codigo AS CHAR) = d.codigo 
        WHERE YEAR(f.fecha) = ?";
$params[] = $filtroAnio;

if (!empty($busqueda)) {
    $sql .= " AND f.nombre LIKE ?";
    $params[] = "%$busqueda%";
}

if ($filtroTipo === 'nacional') {
    $sql .= " AND f.tipo = 'Nacional'";
} elseif ($filtroTipo === 'departamental') {
    $sql .= " AND f.tipo = 'Departamental'";
    if ($filtroDepartamento) {
        $sql .= " AND f.departamento_codigo = ?";
        $params[] = $filtroDepartamento;
    }
}

$sql .= " ORDER BY f.fecha ASC, f.tipo ASC, d.nombre ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$feriados = $stmt->fetchAll();

// Obtener años disponibles para filtro
$stmt = $conn->query("SELECT DISTINCT YEAR(fecha) as anio FROM feriadosnic ORDER BY anio DESC");
$aniosDisponibles = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array(date('Y'), $aniosDisponibles)) {
    $aniosDisponibles[] = date('Y');
}
rsort($aniosDisponibles);

// Obtener feriado para edición si se especifica ID
$feriadoEdicion = null;
if (isset($_GET['editar'])) {
    $id = $_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM feriadosnic WHERE id = ?");
    $stmt->execute([$id]);
    $feriadoEdicion = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Feriados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="../../core/assets/css/global_tools.css">
    <link rel="stylesheet" href="css/editar_feriados.css">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <?php echo renderHeader($usuario, $esAdmin, 'Gestión de Feriados'); ?>

        <div class="container">
            <!-- Cabecera eliminada por redundancia con el header universal -->

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

            <!-- Botón para abrir modal de agregar feriado -->
            <div class="actions" style="margin-bottom: 20px;">
                <button class="btn" onclick="abrirModal()">
                    <i class="fas fa-plus"></i> Agregar Feriado
                </button>
            </div>

            <!-- Filtros -->
            <div class="filters">
                <div class="filter-group">
                    <label for="anio">Año</label>
                    <select id="anio" name="anio" onchange="aplicarFiltros()">
                        <?php foreach ($aniosDisponibles as $anio): ?>
                            <option value="<?= $anio ?>" <?= $filtroAnio == $anio ? 'selected' : '' ?>><?= $anio ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="tipo-filtro">Tipo de Feriado</label>
                    <select id="tipo-filtro" name="tipo" onchange="aplicarFiltros()">
                        <option value="todos" <?= $filtroTipo === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="nacional" <?= $filtroTipo === 'nacional' ? 'selected' : '' ?>>Nacionales</option>
                        <option value="departamental" <?= $filtroTipo === 'departamental' ? 'selected' : '' ?>>
                            Departamentales
                        </option>
                    </select>
                </div>

                <div class="filter-group" id="departamento-filtro-group"
                    style="<?= $filtroTipo !== 'departamental' ? 'display: none;' : '' ?>">
                    <label for="departamento-filtro">Departamento</label>
                    <select id="departamento-filtro" name="departamento" onchange="aplicarFiltros()">
                        <option value="">Todos</option>
                        <?php foreach ($departamentos as $dep): ?>
                            <option value="<?= $dep['codigo'] ?>" <?= $filtroDepartamento == $dep['codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dep['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group search-box">
                    <label for="busqueda">Buscar Feriado</label>
                    <input type="text" id="busqueda" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>"
                        placeholder="Nombre del feriado..." oninput="buscarFeriado()">
                    <i class="fas fa-search"></i>
                </div>

                <div class="filter-group" style="align-self: flex-end;">
                    <button class="btn" onclick="aplicarFiltros()">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                </div>
            </div>

            <!-- Tabla de feriados -->
            <div class="table-container">
                <?php if (empty($feriados)): ?>
                    <div class="no-results">
                        No se encontraron feriados para los filtros seleccionados.
                    </div>
                <?php else: ?>
                    <table id="tabla-feriados">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Departamento</th>
                                <th>Recurrente</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feriados as $feriado): ?>
                                <tr>
                                    <td><?= formatoFecha($feriado['fecha']) ?></td>
                                    <td><?= htmlspecialchars($feriado['nombre']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($feriado['tipo']) ?>">
                                            <?= $feriado['tipo'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($feriado['tipo'] === 'Departamental'): ?>
                                            <?= htmlspecialchars($feriado['nombre_departamento'] ?? 'Desconocido') ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?= $feriado['recurrente'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>' ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-secondary btn-sm" onclick="editarFeriado(<?= $feriado['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="editar_feriados.php?eliminar=<?= $feriado['id'] ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('¿Está seguro que desea eliminar este feriado?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal para agregar/editar feriados -->
        <div id="feriadoModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitulo">Agregar Nuevo Feriado</h3>
                    <span class="close" onclick="cerrarModal()">&times;</span>
                </div>
                <form method="POST" id="feriadoForm">
                    <input type="hidden" name="id" id="feriadoId" value="">

                    <div class="form-row">
                        <div class="form-col">
                            <label for="modalFecha" class="required">Fecha</label>
                            <input type="date" id="modalFecha" name="fecha" required>
                        </div>

                        <div class="form-col">
                            <label for="modalNombre" class="required">Nombre del Feriado</label>
                            <input type="text" id="modalNombre" name="nombre" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label for="modalTipo" class="required">Tipo</label>
                            <select id="modalTipo" name="tipo" required onchange="actualizarDepartamentoField()">
                                <option value="Nacional">Nacional</option>
                                <option value="Departamental">Departamental</option>
                            </select>
                        </div>

                        <div class="form-col" id="modalDepartamentoCol" style="display: none;">
                            <label for="modalDepartamento" class="required">Departamento</label>
                            <select id="modalDepartamento" name="departamento_codigo">
                                <option value="">Seleccione un departamento</option>
                                <?php foreach ($departamentos as $dep): ?>
                                    <option value="<?= $dep['codigo'] ?>">
                                        <?= htmlspecialchars($dep['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="modalRecurrente" name="recurrente" value="1" checked>
                            Feriado recurrente (se repite cada año)
                        </label>
                    </div>

                    <div class="actions">
                        <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            // Variables para controlar el tiempo de búsqueda
            let timeoutBusqueda = null;

            // Mostrar/ocultar campo departamento según tipo de feriado
            function actualizarDepartamentoField() {
                const tipo = document.getElementById('modalTipo').value;
                const departamentoCol = document.getElementById('modalDepartamentoCol');

                if (tipo === 'Departamental') {
                    departamentoCol.style.display = 'flex';
                    document.getElementById('modalDepartamento').required = true;
                } else {
                    departamentoCol.style.display = 'none';
                    document.getElementById('modalDepartamento').required = false;
                }
            }

            // Mostrar/ocultar filtro departamento según tipo de feriado
            function actualizarFiltroDepartamento() {
                const tipoFiltro = document.getElementById('tipo-filtro').value;
                const departamentoFiltroGroup = document.getElementById('departamento-filtro-group');

                if (tipoFiltro === 'departamental') {
                    departamentoFiltroGroup.style.display = 'flex';
                } else {
                    departamentoFiltroGroup.style.display = 'none';
                }
            }

            // Aplicar filtros
            function aplicarFiltros() {
                const anio = document.getElementById('anio').value;
                const tipoFiltro = document.getElementById('tipo-filtro').value;
                const departamentoFiltro = tipoFiltro === 'departamental' ? document.getElementById('departamento-filtro').value : '';
                const busqueda = document.getElementById('busqueda').value;

                let url = `editar_feriados.php?anio=${anio}&tipo=${tipoFiltro}`;

                if (departamentoFiltro) {
                    url += `&departamento=${departamentoFiltro}`;
                }

                if (busqueda) {
                    url += `&busqueda=${encodeURIComponent(busqueda)}`;
                }

                window.location.href = url;
            }

            // Buscar feriado con delay
            function buscarFeriado() {
                clearTimeout(timeoutBusqueda);

                timeoutBusqueda = setTimeout(() => {
                    aplicarFiltros();
                }, 500);
            }

            // Funciones para manejar el modal
            function abrirModal() {
                // Limpiar formulario
                document.getElementById('feriadoForm').reset();
                document.getElementById('modalTitulo').textContent = 'Agregar Nuevo Feriado';
                document.getElementById('feriadoId').value = '';
                document.getElementById('modalDepartamentoCol').style.display = 'none';

                // Mostrar modal
                document.getElementById('feriadoModal').style.display = 'block';
            }

            function cerrarModal() {
                document.getElementById('feriadoModal').style.display = 'none';
            }

            function editarFeriado(id) {
                // Hacer una petición AJAX para obtener los datos del feriado
                fetch(`obtener_feriado.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data) {
                            // Llenar el formulario con los datos
                            document.getElementById('modalTitulo').textContent = 'Editar Feriado';
                            document.getElementById('feriadoId').value = data.id;
                            document.getElementById('modalFecha').value = data.fecha;
                            document.getElementById('modalNombre').value = data.nombre;
                            document.getElementById('modalTipo').value = data.tipo;
                            document.getElementById('modalRecurrente').checked = data.recurrente == 1;

                            // Manejar el campo departamento
                            if (data.tipo === 'Departamental') {
                                document.getElementById('modalDepartamentoCol').style.display = 'flex';
                                document.getElementById('modalDepartamento').value = data.departamento_codigo;
                            } else {
                                document.getElementById('modalDepartamentoCol').style.display = 'none';
                            }

                            // Mostrar modal
                            document.getElementById('feriadoModal').style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error al obtener feriado:', error);
                        mostrarNotificacion('Error al cargar los datos del feriado', 'error');
                    });
            }

            // Cerrar modal al hacer clic fuera del contenido
            window.onclick = function (event) {
                const modal = document.getElementById('feriadoModal');
                if (event.target === modal) {
                    cerrarModal();
                }
            }

            // Función para mostrar notificaciones
            function mostrarNotificacion(mensaje, tipo = 'info') {
                const estilos = {
                    success: { background: '#d4edda', color: '#155724', icon: 'check-circle' },
                    error: { background: '#f8d7da', color: '#721c24', icon: 'exclamation-circle' },
                    info: { background: '#e2e3e5', color: '#383d41', icon: 'info-circle' }
                };

                const estilo = estilos[tipo] || estilos.info;

                const notificacion = document.createElement('div');
                notificacion.style.position = 'fixed';
                notificacion.style.top = '20px';
                notificacion.style.right = '20px';
                notificacion.style.padding = '15px';
                notificacion.style.borderRadius = '4px';
                notificacion.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
                notificacion.style.backgroundColor = estilo.background;
                notificacion.style.color = estilo.color;
                notificacion.style.zIndex = '1000';
                notificacion.style.display = 'flex';
                notificacion.style.alignItems = 'center';
                notificacion.style.gap = '10px';
                notificacion.style.maxWidth = '300px';
                notificacion.innerHTML = `
                <i class="fas fa-${estilo.icon}" style="font-size: 1.2rem;"></i>
                <span>${mensaje}</span>
            `;

                document.body.appendChild(notificacion);

                setTimeout(() => {
                    notificacion.style.opacity = '0';
                    notificacion.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => notificacion.remove(), 500);
                }, 3000);
            }

            // Inicializar eventos
            document.addEventListener('DOMContentLoaded', function () {
                // Escuchar cambios en el filtro de tipo
                document.getElementById('tipo-filtro').addEventListener('change', actualizarFiltroDepartamento);

                // Si hay un feriado para editar en la URL, abrir el modal
                <?php if (isset($_GET['editar'])): ?>
                    editarFeriado(<?= $_GET['editar'] ?>);
                <?php endif; ?>
            });

            // Mostrar notificaciones si hay en sesión
            <?php if (isset($_SESSION['exito'])): ?>
                mostrarNotificacion('<?= $_SESSION['exito'] ?>', 'success');
                <?php unset($_SESSION['exito']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                mostrarNotificacion('<?= $_SESSION['error'] ?>', 'error');
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        </script>
</body>

</html>