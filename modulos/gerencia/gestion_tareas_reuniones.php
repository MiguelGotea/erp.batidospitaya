<?php
// gestion_tareas_reuniones.php
// Panel principal de gestión de tareas y reuniones del equipo de liderazgo

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('gestion_tareas_reuniones', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtener permisos del usuario
$permisoCrearTarea = tienePermiso('gestion_tareas_reuniones', 'crear_tarea', $cargoOperario);
$permisoSolicitarTarea = tienePermiso('gestion_tareas_reuniones', 'solicitar_tarea', $cargoOperario);
$permisoSolicitarReunion = tienePermiso('gestion_tareas_reuniones', 'solicitar_reunion', $cargoOperario);
$permisoCancelar = tienePermiso('gestion_tareas_reuniones', 'cancelar_tarea_reunion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tareas y Reuniones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/gestion_tareas_reuniones.css?v=<?php echo mt_rand(1, 10000); ?>">

    <!-- FullCalendar v6 -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Gestión de Tareas y Reuniones'); ?>

            <div class="container-fluid p-3">
                <!-- Botones de acción y agrupación en la misma fila -->
                <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($permisoCrearTarea): ?>
                            <button class="btn btn-success" onclick="abrirModalNuevaTarea()">
                                <i class="bi bi-plus-circle"></i> Nueva Tarea
                            </button>
                        <?php endif; ?>

                        <?php if ($permisoSolicitarTarea): ?>
                            <button class="btn btn-primary" onclick="abrirModalSolicitarTarea()">
                                <i class="bi bi-clipboard-check"></i> Solicitar Tarea
                            </button>
                        <?php endif; ?>

                        <?php if ($permisoSolicitarReunion): ?>
                            <button class="btn btn-info text-white" onclick="abrirModalNuevaReunion()">
                                <i class="bi bi-calendar-event"></i> Solicitar Reunión
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary active" data-agrupacion="mes"
                            onclick="cambiarAgrupacion('mes')">
                            <i class="bi bi-calendar3"></i> Por Mes
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-agrupacion="semana"
                            onclick="cambiarAgrupacion('semana')">
                            <i class="bi bi-calendar-week"></i> Por Semana
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-agrupacion="cargo"
                            onclick="cambiarAgrupacion('cargo')">
                            <i class="bi bi-person-badge"></i> Por Cargo
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-agrupacion="estado"
                            onclick="cambiarAgrupacion('estado')">
                            <i class="bi bi-flag"></i> Por Estado
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-agrupacion="calendario"
                            onclick="cambiarAgrupacion('calendario')">
                            <i class="bi bi-calendar-range"></i> Calendario
                        </button>
                    </div>
                </div>

                <!-- Contenedor de tareas y reuniones agrupadas -->
                <div id="contenedorTareasReuniones">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>
                </div>

                <!-- Contenedor para el Calendario -->
                <div id="contenedorCalendario"
                    style="display:none; background: white; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6;">
                    <div id="calendarioTareas"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Tarea -->
    <div class="modal fade" id="modalNuevaTarea" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva Tarea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNuevaTarea">
                        <div class="mb-3">
                            <label for="tituloTarea" class="form-label">Título *</label>
                            <input type="text" class="form-control" id="tituloTarea" name="titulo" required
                                maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="descripcionTarea" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcionTarea" name="descripcion" rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="cargoAsignadoTarea" class="form-label">Asignar a *</label>
                            <select class="form-select" id="cargoAsignadoTarea" name="cod_cargo_asignado" required>
                                <option value="">Seleccione un cargo...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="fechaMetaTarea" class="form-label">Fecha Límite *</label>
                            <input type="date" class="form-control" id="fechaMetaTarea" name="fecha_meta" required>
                        </div>

                        <div class="mb-3">
                            <label for="archivosTarea" class="form-label">Archivos Adjuntos</label>
                            <input type="file" class="form-control" id="archivosTarea" name="archivos[]" multiple
                                accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Máximo 10MB por archivo. Formatos: PDF, JPG, PNG</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="guardarTarea('crear')">Crear Tarea</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Solicitar Tarea -->
    <div class="modal fade" id="modalSolicitarTarea" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Solicitar Tarea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formSolicitarTarea">
                        <div class="mb-3">
                            <label for="tituloTareaSolicitud" class="form-label">Título *</label>
                            <input type="text" class="form-control" id="tituloTareaSolicitud" name="titulo" required
                                maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="descripcionTareaSolicitud" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcionTareaSolicitud" name="descripcion"
                                rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="cargoAsignadoTareaSolicitud" class="form-label">Solicitar a *</label>
                            <select class="form-select" id="cargoAsignadoTareaSolicitud" name="cod_cargo_asignado"
                                required>
                                <option value="">Seleccione un cargo...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="fechaMetaTareaSolicitud" class="form-label">Fecha Límite *</label>
                            <input type="date" class="form-control" id="fechaMetaTareaSolicitud" name="fecha_meta"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="archivosTareaSolicitud" class="form-label">Archivos Adjuntos</label>
                            <input type="file" class="form-control" id="archivosTareaSolicitud" name="archivos[]"
                                multiple accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Máximo 10MB por archivo. Formatos: PDF, JPG, PNG</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarTarea('solicitar')">Solicitar
                        Tarea</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Reunión -->
    <div class="modal fade" id="modalNuevaReunion" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Solicitar Reunión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNuevaReunion">
                        <div class="mb-3">
                            <label for="tituloReunion" class="form-label">Título *</label>
                            <input type="text" class="form-control" id="tituloReunion" name="titulo" required
                                maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="descripcionReunion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcionReunion" name="descripcion"
                                rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="fechaReunion" class="form-label">Fecha y Hora de Reunión *</label>
                            <input type="datetime-local" class="form-control" id="fechaReunion" name="fecha_reunion"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Invitados *</label>
                            <div id="listaInvitados" class="border rounded p-3"
                                style="max-height: 200px; overflow-y: auto;">
                                <!-- Se carga dinámicamente -->
                            </div>
                            <small class="text-muted">Seleccione los cargos que participarán en la reunión</small>
                        </div>

                        <div class="mb-3">
                            <label for="archivosReunion" class="form-label">Archivos Adjuntos</label>
                            <input type="file" class="form-control" id="archivosReunion" name="archivos[]" multiple
                                accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">Máximo 10MB por archivo. Formatos: PDF, JPG, PNG</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-info text-white" onclick="guardarReunion()">Solicitar
                        Reunión</button>
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
                        <input type="hidden" id="finalizarIdItem" value="">
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
    <script>
        // Variables globales
        const cargoActual = <?php echo $cargoOperario; ?>;
        const permisoCancelar = <?php echo $permisoCancelar ? 'true' : 'false'; ?>;
    </script>
    <script src="js/gestion_tareas_reuniones.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>