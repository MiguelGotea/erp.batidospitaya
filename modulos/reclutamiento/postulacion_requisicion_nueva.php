<?php
// postulacion_requisicion_nueva.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('postulacion_requisicion', 'crear', $cargoOperario)) {
    header('Location: postulacion_requisicion.php');
    exit();
}

// Obtener ID para edición si existe
$idRequisicion = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$requisicion = null;

if ($idRequisicion > 0) {
    $sql = "SELECT * FROM requisicion_personal WHERE id = :id AND status = 'Solicitado'";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $idRequisicion, PDO::PARAM_INT);
    $stmt->execute();
    $requisicion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$requisicion) {
        header('Location: postulacion_requisicion.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $idRequisicion > 0 ? 'Editar Requisición' : 'Nueva Requisición'; ?> de Personal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/postulacion_requisicion.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, ($idRequisicion > 0 ? 'Editar' : 'Nueva') . ' Requisición de Personal'); ?>

            <div class="container-fluid p-4">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <form id="formRequisicion">
                            <?php if ($idRequisicion > 0): ?>
                                <input type="hidden" name="id_requisicion" value="<?php echo $idRequisicion; ?>">
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="nombreCargo" class="form-label">Nombre del Cargo *</label>
                                    <input type="text" class="form-control" id="nombreCargo" name="nombre_cargo"
                                        required placeholder="Ingrese el nombre completo del nuevo cargo a crear">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="areaCargo" class="form-label">Área del Cargo <small
                                            class="text-muted">(Opcional)</small></label>
                                    <select class="form-select" id="areaCargo" name="area_cargo">
                                        <option value="">Sin especificar</option>
                                        <!-- Cargado dinámicamente -->
                                    </select>

                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="sucursalSelect" class="form-label">Sucursal *</label>
                                    <select class="form-select" id="sucursalSelect" name="sucursal" required>
                                        <option value="">Cargando sucursales...</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="cantidad" class="form-label">Cantidad *</label>
                                    <input type="number" class="form-control" id="cantidad" name="cantidad" required
                                        min="1" value="1">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="salarioPropuesto" class="form-label">Salario Propuesto *</label>
                                    <input type="number" class="form-control" id="salarioPropuesto"
                                        name="salario_propuesto" required min="0" step="0.01" placeholder="0.00">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="nivelUrgencia" class="form-label">Nivel de Urgencia *</label>
                                    <select class="form-select urgencia-select" id="nivelUrgencia" name="nivel_urgencia"
                                        required>
                                        <option value="">Seleccione nivel...</option>
                                        <option value="1">⚪ No urgente</option>
                                        <option value="2">🟡 Medio</option>
                                        <option value="3">🟠 Urgente</option>
                                        <option value="4">🔴 Crítico</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="jefeDirectoSelect" class="form-label">Jefe Directo (Cargo) *</label>
                                    <select class="form-select" id="jefeDirectoSelect" name="cargo_reporta_a" required>
                                        <option value="">Cargando cargos...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="justificacion" class="form-label">Justificación de la Vacante *</label>
                                <textarea class="form-control" id="justificacion" name="justificacion" rows="5" required
                                    placeholder="Describe el motivo de la nueva vacante..."></textarea>
                                <small class="text-muted">Mínimo 20 caracteres</small>
                            </div>

                            <div class="mt-4 mb-4">
                                <h5 class="text-primary border-bottom pb-2 mb-3">Perfil del Candidato y Requerimiento
                                </h5>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="estudiosMinimos" class="form-label">Estudios mínimos
                                            requeridos</label>
                                        <input type="text" class="form-control" id="estudiosMinimos"
                                            name="estudios_minimos" placeholder="Ej: Licenciatura completa, etc..">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="carrerasAptas" class="form-label">Carreras profesionales aptas
                                            (hasta 4 opciones)</label>
                                        <input type="text" class="form-control" id="carrerasAptas" name="carreras_aptas"
                                            placeholder="Ej: Ing. Sistemas, Computación, etc.">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="conocimientosEspecificos" class="form-label">Conocimientos
                                            específicos</label>
                                        <input type="text" class="form-control" id="conocimientosEspecificos"
                                            name="conocimientos_especificos"
                                            placeholder="Ej: Desarrollo web, Contabilidad avanzada, etc.">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="idiomas" class="form-label">Idiomas</label>
                                        <input type="text" class="form-control" id="idiomas" name="idiomas"
                                            placeholder="Ej: Inglés técnico, B2, etc.">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="herramientasOffice" class="form-label">Uso de
                                            herramientas/Office</label>
                                        <input type="text" class="form-control" id="herramientasOffice"
                                            name="herramientas_office" placeholder="Ej: Excel avanzado, SAP, etc.">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="aptitudesEspecificas" class="form-label">Aptitudes específicas
                                            deseadas</label>
                                        <textarea class="form-control" id="aptitudesEspecificas"
                                            name="aptitudes_especificas" rows="2"
                                            placeholder="Ej: Liderazgo, Trabajo en equipo, capacidad analítica..."></textarea>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="experienciaDeseada" class="form-label">Experiencia deseada</label>
                                        <textarea class="form-control" id="experienciaDeseada"
                                            name="experiencia_deseada" rows="2"
                                            placeholder="Describa el tiempo y áreas de experiencia previa detalladamente..."></textarea>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="funcionesResponsabilidades" class="form-label">Funciones y
                                            responsabilidades generales del puesto</label>
                                        <textarea class="form-control" id="funcionesResponsabilidades"
                                            name="funciones_responsabilidades" rows="3"
                                            placeholder="Detalle las principales tareas y responsabilidades del día a día..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="postulacion_requisicion.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle me-2"></i>Cancelar
                                </a>
                                <button type="button" class="btn btn-success btn-lg" onclick="enviarSolicitud()">
                                    <i class="bi bi-send me-2"></i><?php echo $idRequisicion > 0 ? 'Guardar Cambios' : 'Enviar Solicitud'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Nueva Requisición
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="bi bi-search me-2"></i> Búsqueda de Cargos
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Escribe al menos 2 caracteres en el campo de cargo para ver las coincidencias
                                        disponibles. Selecciona el cargo exacto de la lista.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="bi bi-exclamation-triangle me-2"></i> Campos Obligatorios
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Todos los campos marcados con (*) son obligatorios. La justificación debe tener
                                        al menos 20 caracteres.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="bi bi-file-earmark me-2"></i> Perfil de Puesto
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Puedes arrastrar el archivo PDF al área indicada o hacer clic en "Seleccionar
                                        archivo". Máximo 5MB.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="bi bi-check-circle me-2"></i> Envío
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Al enviar, la solicitud será enviada a gerencia para su aprobación. Podrás ver
                                        el estado en la lista de requisiciones.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const idRequisicionEdit = <?php echo $idRequisicion; ?>;
        const datosRequisicionEdit = <?php echo $requisicion ? json_encode($requisicion) : 'null'; ?>;
    </script>
    <script src="js/postulacion_requisicion_nueva.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>