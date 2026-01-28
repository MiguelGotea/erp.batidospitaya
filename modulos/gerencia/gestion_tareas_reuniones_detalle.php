<?php
// gestion_tareas_reuniones_detalle.php
// Página de detalle de tarea o reunión

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$codOperario = $usuario['CodOperario'];

// Verificar acceso
if (!tienePermiso('gestion_tareas_reuniones_detalle', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$idItem = intval($_GET['id'] ?? 0);

if ($idItem <= 0) {
    header('Location: gestion_tareas_reuniones.php');
    exit();
}

// Obtener datos del item
require_once '../../core/database/conexion.php';

$sql = "SELECT 
            i.*,
            nc.Nombre as nombre_cargo_asignado,
            nc_creador.Nombre as nombre_cargo_creador,
            o_creador.Nombre as nombre_creador,
            o_creador.Apellido as apellido_creador,
            o_creador.foto_perfil as avatar_creador,
            -- Lógica de responsable dinámico
            CASE 
                WHEN i.tipo = 'reunion' THEN o_creador.foto_perfil
                ELSE o_asignado.foto_perfil
            END as avatar_url,
            CASE 
                WHEN i.tipo = 'reunion' THEN CONCAT(o_creador.Nombre, ' ', o_creador.Apellido)
                ELSE COALESCE(CONCAT(o_asignado.Nombre, ' ', o_asignado.Apellido), nc.Nombre)
            END as nombre_responsable
        FROM gestion_tareas_reuniones_items i
        LEFT JOIN NivelesCargos nc ON i.cod_cargo_asignado = nc.CodNivelesCargos
        LEFT JOIN NivelesCargos nc_creador ON i.cod_cargo_creador = nc_creador.CodNivelesCargos
        LEFT JOIN Operarios o_creador ON i.cod_operario_creador = o_creador.CodOperario
        LEFT JOIN Operarios o_asignado ON o_asignado.CodOperario = (
            SELECT anc.CodOperario 
            FROM AsignacionNivelesCargos anc 
            WHERE anc.CodNivelesCargos = i.cod_cargo_asignado 
            AND anc.Fecha <= CURDATE() 
            AND (anc.Fin >= CURDATE() OR anc.Fin IS NULL)
            ORDER BY anc.Fecha DESC 
            LIMIT 1
        )
        WHERE i.id = :id";

$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $idItem]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if ($item) {
    // Contar subtareas
    $sqlCountSub = "SELECT COUNT(*) FROM gestion_tareas_reuniones_items WHERE id_padre = :id AND tipo = 'subtarea'";
    $stmtSub = $conn->prepare($sqlCountSub);
    $stmtSub->execute([':id' => $idItem]);
    $totalSubtareas = intval($stmtSub->fetchColumn());

    // Contar comentarios
    $sqlCountCom = "SELECT COUNT(*) FROM gestion_tareas_reuniones_comentarios WHERE id_item = :id";
    $stmtCom = $conn->prepare($sqlCountCom);
    $stmtCom->execute([':id' => $idItem]);
    $totalComentarios = intval($stmtCom->fetchColumn());

    $item['total_subtareas'] = $totalSubtareas;
    $item['total_comentarios'] = $totalComentarios;
}

if (!$item) {
    header('Location: gestion_tareas_reuniones.php');
    exit();
}

$esTarea = ($item['tipo'] == 'tarea');
$esReunion = ($item['tipo'] == 'reunion');
$esSubtarea = ($item['tipo'] == 'subtarea');

// Verificar permisos específicos
$esAsignado = ($item['cod_cargo_asignado'] == $cargoOperario);
$esCreador = ($item['cod_operario_creador'] == $codOperario);
$puedeEditar = ($esAsignado || $esCreador) && $item['estado'] != 'finalizado' && $item['estado'] != 'cancelado';
$permisoCancelar = tienePermiso('gestion_tareas_reuniones', 'cancelar_tarea_reunion', $cargoOperario);

// Si es reunión, verificar si es participante
$esParticipante = false;
if ($esReunion) {
    $sqlParticipante = "SELECT confirmacion FROM gestion_tareas_reuniones_participantes 
                        WHERE id_item = :id_item AND cod_cargo = :cod_cargo";
    $stmtP = $conn->prepare($sqlParticipante);
    $stmtP->execute([':id_item' => $idItem, ':cod_cargo' => $cargoOperario]);
    $participante = $stmtP->fetch(PDO::FETCH_ASSOC);
    $esParticipante = ($participante !== false);
    $confirmacion = $participante['confirmacion'] ?? 'pendiente';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $esTarea ? 'Tarea' : 'Reunión'; ?>:
        <?php echo htmlspecialchars($item['titulo']); ?>
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/gestion_tareas_reuniones_detalle.css?v=<?php echo mt_rand(1, 10000); ?>">
    <!-- Quill Editor para resumen de reunión -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, ($esTarea ? 'Detalle de Tarea' : 'Detalle de Reunión')); ?>

            <div class="container-fluid p-3">

                <div class="row">
                    <!-- Columna principal -->
                    <div class="col-lg-9">
                        <!-- Información del item -->
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2 flex-grow-1">
                                    <i
                                        class="bi <?php echo $esReunion ? 'bi-calendar-event' : 'bi-file-earmark-text'; ?> fs-4"></i>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($item['titulo']); ?></h5>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge-estado <?php echo $item['estado']; ?>">
                                        <?php
                                        $estados = [
                                            'solicitado' => 'Solicitado',
                                            'en_progreso' => 'En Progreso',
                                            'finalizado' => 'Finalizado',
                                            'cancelado' => 'Cancelado'
                                        ];
                                        echo $estados[$item['estado']];
                                        ?>
                                    </span>

                                    <?php if ($item['estado'] == 'solicitado' && $esAsignado && $esTarea): ?>
                                        <button class="btn btn-success btn-sm" onclick="aprobarTarea()">
                                            <i class="bi bi-check-circle"></i> Aprobar
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($esTarea && $item['estado'] == 'en_progreso' && $esAsignado && $item['total_subtareas'] == 0): ?>
                                        <button class="btn btn-success btn-sm" onclick="finalizarManual()">
                                            <i class="bi bi-check2-circle"></i> Finalizar
                                        </button>
                                    <?php endif; ?>

                                    <?php if (($permisoCancelar || $esCreador) && $item['estado'] != 'cancelado' && $item['estado'] != 'finalizado'): ?>
                                        <button class="btn btn-outline-danger btn-sm" onclick="cancelarItem()">
                                            <i class="bi bi-x-circle"></i> Cancelar
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($item['descripcion']): ?>
                                    <div class="mb-2">
                                        <label class="fw-bold small">Descripción:</label>
                                        <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($item['descripcion'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div class="row g-2 info-compacta">
                                    <?php if ($esTarea): ?>
                                        <div class="col-md-3">
                                            <label class="fw-bold small text-muted">Asignado a:</label>
                                            <p class="mb-0 small">
                                                <?php echo htmlspecialchars($item['nombre_cargo_asignado']); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="fw-bold small text-muted">Fecha Límite:</label>
                                            <p class="mb-0 small">
                                                <span
                                                    id="fechaMetaDisplay"><?php echo date('d-M-Y', strtotime($item['fecha_meta'])); ?></span>
                                                <?php if ($puedeEditar && $item['estado'] == 'en_progreso'): ?>
                                                    <button class="btn btn-sm btn-link p-0" onclick="editarFechaMeta()">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <div class="col-md-6">
                                            <label class="fw-bold small text-muted">Fecha de Reunión:</label>
                                            <p class="mb-0 small">
                                                <?php echo date('d-M-Y H:i', strtotime($item['fecha_reunion'])); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-md-3">
                                        <label class="fw-bold small text-muted">Creado por:</label>
                                        <p class="mb-0 small">
                                            <?php echo htmlspecialchars($item['nombre_creador'] . ' ' . $item['apellido_creador']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="fw-bold small text-muted">Fecha de Creación:</label>
                                        <p class="mb-0 small">
                                            <?php echo date('d-M-Y H:i', strtotime($item['fecha_creacion'])); ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Archivos adjuntos del item -->
                                <div id="archivosItem" class="mt-3">
                                    <!-- Cargado por AJAX -->
                                </div>

                                <!-- Botones de acción (Confirmación de reunión) -->
                                <div class="mt-2 d-flex gap-2 flex-wrap">
                                    <?php if ($item['estado'] == 'solicitado' && $esParticipante && $esReunion && $confirmacion == 'pendiente'): ?>
                                        <button class="btn btn-success btn-sm" onclick="confirmarAsistencia('asistire')">
                                            <i class="bi bi-check-circle"></i> Asistiré
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="confirmarAsistencia('no_asistire')">
                                            <i class="bi bi-x-circle"></i> No Asistiré
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Pestañas -->
                        <ul class="nav nav-tabs" id="detalleTabs" role="tablist">
                            <?php if ($esTarea): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="subtareas-tab" data-bs-toggle="tab"
                                        data-bs-target="#subtareas" type="button">
                                        <i class="bi bi-list-check"></i> Subtareas
                                        <span class="badge bg-secondary ms-1"
                                            id="badge-subtareas"><?php echo $item['total_subtareas']; ?></span>
                                    </button>
                                </li>
                            <?php endif; ?>

                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $esReunion ? 'active' : ''; ?>" id="comentarios-tab"
                                    data-bs-toggle="tab" data-bs-target="#comentarios" type="button">
                                    <i class="bi bi-chat-left-text"></i> Comentarios
                                    <span class="badge bg-secondary ms-1"
                                        id="badge-comentarios"><?php echo $item['total_comentarios']; ?></span>
                                </button>
                            </li>

                            <?php if ($esReunion): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen"
                                        type="button">
                                        <i class="bi bi-file-earmark-text"></i> Resumen
                                    </button>
                                </li>
                            <?php endif; ?>

                            <?php if ($esTarea && $item['estado'] == 'finalizado' && $item['total_subtareas'] == 0): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="finalizacion-tab" data-bs-toggle="tab"
                                        data-bs-target="#finalizacion" type="button">
                                        <i class="bi bi-check2-all"></i> Finalización
                                    </button>
                                </li>
                            <?php endif; ?>
                        </ul>

                        <div class="tab-content" id="detalleTabContent">
                            <?php if ($esTarea): ?>
                                <!-- Pestaña Subtareas -->
                                <div class="tab-pane fade show active" id="subtareas" role="tabpanel">
                                    <div class="p-3">
                                        <?php if ($esAsignado && $item['estado'] == 'en_progreso'): ?>
                                            <button class="btn btn-primary btn-sm mb-3" onclick="abrirModalSubtarea()">
                                                <i class="bi bi-plus-circle"></i> Nueva Subtarea
                                            </button>
                                        <?php endif; ?>

                                        <div id="listaSubtareas">
                                            <div class="text-center py-3">
                                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Pestaña Comentarios -->
                            <div class="tab-pane fade <?php echo $esReunion ? 'show active' : ''; ?>" id="comentarios"
                                role="tabpanel">
                                <div class="p-3">
                                    <?php if ($item['estado'] != 'finalizado'): ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Agregar Comentario:</label>
                                            <textarea class="form-control mb-2" id="nuevoComentario" rows="3"
                                                placeholder="Escribe tu comentario..."></textarea>
                                            <input type="file" class="form-control mb-2" id="archivosComentario" multiple
                                                accept=".pdf,.jpg,.jpeg,.png">
                                            <button class="btn btn-primary btn-sm" onclick="agregarComentario()">
                                                <i class="bi bi-send"></i> Enviar Comentario
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <div id="listaComentarios">
                                        <div class="text-center py-3">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($esReunion): ?>
                                <!-- Pestaña Resumen -->
                                <div class="tab-pane fade" id="resumen" role="tabpanel">
                                    <div class="p-3">
                                        <?php if ($esCreador && $item['estado'] != 'cancelado'): ?>
                                            <div id="editor-container" style="min-height: 200px;">
                                                <!-- Editor Quill -->
                                            </div>
                                            <button class="btn btn-primary mt-2" onclick="guardarResumen()">
                                                <i class="bi bi-save"></i> Guardar Resumen
                                            </button>
                                        <?php else: ?>
                                            <div id="resumen-display"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($esTarea && $item['estado'] == 'finalizado' && $item['total_subtareas'] == 0): ?>
                                <!-- Pestaña Finalización Manual -->
                                <div class="tab-pane fade" id="finalizacion" role="tabpanel">
                                    <div class="p-3">
                                        <div class="card bg-light border-0">
                                            <div class="card-body">
                                                <h6 class="fw-bold mb-3">Detalles de la Finalización</h6>
                                                <p class="mb-3 small">
                                                    <?php echo nl2br(htmlspecialchars($item['detalles_finalizacion'])); ?>
                                                </p>

                                                <div id="archivosFinalizacion" class="mt-3">
                                                    <!-- Se cargará por AJAX o se puede inyectar aquí si lo buscamos -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Columna lateral -->
                    <div class="col-lg-3">
                        <!-- Avatar del responsable -->
                        <div class="card mb-3">
                            <div class="card-body text-center">
                                <label class="fw-bold mb-2">
                                    <?php echo $esReunion ? 'Creador' : 'Responsable'; ?>
                                </label>
                                <?php if ($item['avatar_url']): ?>
                                    <img src="<?php echo $item['avatar_url']; ?>" class="avatar-grande mb-2" alt="Avatar">
                                <?php else: ?>
                                    <div class="avatar-grande-placeholder mb-2">
                                        <?php
                                        $nombre = $item['nombre_responsable'];
                                        $iniciales = substr($nombre, 0, 2);
                                        echo strtoupper($iniciales);
                                        ?>
                                    </div>
                                <?php endif; ?>
                                <p class="mb-0 small">
                                    <?php echo htmlspecialchars($item['nombre_responsable']); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Progreso -->
                        <div class="card">
                            <div class="card-body">
                                <label class="fw-bold mb-2">Progreso</label>
                                <div id="progresoContainer">
                                    <!-- Cargado por AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Subtarea -->
    <div class="modal fade" id="modalSubtarea" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Subtarea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formSubtarea">
                        <div class="mb-3">
                            <label for="tituloSubtarea" class="form-label">Título *</label>
                            <input type="text" class="form-control" id="tituloSubtarea" required>
                        </div>
                        <div class="mb-3">
                            <label for="descripcionSubtarea" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcionSubtarea" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="fechaMetaSubtarea" class="form-label">Fecha Límite *</label>
                            <input type="date" class="form-control" id="fechaMetaSubtarea"
                                value="<?php echo $item['fecha_meta']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="archivosSubtarea" class="form-label">Archivos Adjuntos</label>
                            <input type="file" class="form-control" id="archivosSubtarea" multiple
                                accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarSubtarea()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ver Detalle Subtarea -->
    <div class="modal fade" id="modalVerSubtarea" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="verSubtareaTitulo">Detalle de Subtarea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="verSubtareaContenido">
                        <div class="text-center p-4">
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Finalizar Manuelamente (Tarea sin subtareas) -->
    <div class="modal fade" id="modalFinalizarTarea" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Finalizar Tarea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formFinalizarTarea">
                        <div class="mb-3">
                            <label class="form-label">Detalles de Finalización *</label>
                            <textarea class="form-control" id="detallesFinalizacionTarea" rows="4" required
                                placeholder="Describe el resultado final..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Evidencias / Archivos</label>
                            <input type="file" class="form-control" id="archivosFinalizacionTarea" multiple
                                accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="confirmarFinalizarManual()">Finalizar
                        Tarea</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
        // Variables globales
        const idItem = <?php echo $idItem; ?>;
        const tipoItem = '<?php echo $item['tipo']; ?>';
        const estadoItem = '<?php echo $item['estado']; ?>';
        const esAsignado = <?php echo $esAsignado ? 'true' : 'false'; ?>;
        const esCreador = <?php echo $esCreador ? 'true' : 'false'; ?>;
        const esParticipante = <?php echo ($esParticipante ?? false) ? 'true' : 'false'; ?>;
        const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    </script>
    <script src="js/gestion_tareas_reuniones_detalle.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>