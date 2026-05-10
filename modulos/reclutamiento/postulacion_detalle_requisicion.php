<?php
// postulacion_detalle_requisicion.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/helpers/funciones.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('postulacion_detalle_requisicion', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeAprobar = tienePermiso('postulacion_detalle_requisicion', 'aprobar_gerencia', $cargoOperario);

// Obtener ID de requisición
$idRequisicion = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idRequisicion <= 0) {
    header('Location: postulacion_requisicion.php');
    exit();
}

// Obtener datos de la requisición
require_once '../../core/database/conexion.php';

$sql = "SELECT 
            rp.*,
            s.nombre as sucursal_nombre,
            u.Nombre as jefe_nombre,
            u.Apellido as jefe_apellido,
            nc_jefe.Nombre as jefe_cargo_nombre,
            u.Nombre as usuario_nombre,
            u.Apellido as usuario_apellido
        FROM requisicion_personal rp
        LEFT JOIN sucursales s ON rp.sucursal = s.codigo
        LEFT JOIN NivelesCargos nc_jefe ON rp.cargo_reporta_a = nc_jefe.CodNivelesCargos
        LEFT JOIN Operarios u ON rp.usuario_registra = u.CodOperario
        WHERE rp.id = :id";

$stmt = $conn->prepare($sql);
$stmt->bindValue(':id', $idRequisicion, PDO::PARAM_INT);
$stmt->execute();
$requisicion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$requisicion) {
    header('Location: postulacion_requisicion.php');
    exit();
}

$urgenciaTextos = ['', 'No urgente', 'Medio', 'Urgente', 'Crítico'];
$urgenciaClases = ['', 'urgencia-badge-1', 'urgencia-badge-2', 'urgencia-badge-3', 'urgencia-badge-4'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Requisición</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/postulacion_detalle_requisicion.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Detalle de Requisición de Personal'); ?>

            <div class="container-fluid p-4">
                <div class="detalle-card card">
                    <div class="detalle-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($requisicion['nombre_cargo']); ?></h4>
                                <small>Requisición #<?php echo $requisicion['id']; ?></small>
                            </div>
                            <div>
                                <?php
                                $statusBadge = '';
                                switch ($requisicion['status']) {
                                    case 'Solicitado':
                                        $statusBadge = '<span class="badge bg-secondary fs-6">Solicitado</span>';
                                        break;
                                    case 'Aprobado':
                                        $statusBadge = '<span class="badge bg-success fs-6">Aprobado</span>';
                                        break;
                                    case 'Rechazado':
                                        $statusBadge = '<span class="badge bg-danger fs-6">Rechazado</span>';
                                        break;
                                }
                                echo $statusBadge;
                                ?>
                                <?php if ($requisicion['status'] === 'Solicitado'): ?>
                                    <a href="postulacion_requisicion_nueva.php?id=<?php echo $idRequisicion; ?>" class="btn btn-outline-warning ms-2">
                                        <i class="bi bi-pencil me-1"></i>Editar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="detalle-body">
                        <h5 class="section-title">Información de la Requisición</h5>

                        <div class="row">
                            <div class="col-md-4 info-row">
                                <div class="info-label">Sucursal</div>
                                <div class="info-value">
                                    <?php echo $requisicion['sucursal_nombre'] ?? 'Corporativo - Central'; ?>
                                </div>
                            </div>

                            <div class="col-md-4 info-row">
                                <div class="info-label">Cantidad de Posiciones</div>
                                <div class="info-value">
                                    <strong><?php echo $requisicion['cantidad']; ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-4 info-row">
                                <div class="info-label">Salario Propuesto</div>
                                <div class="info-value">
                                    C$ <?php echo number_format($requisicion['salario_propuesto'], 2); ?>
                                </div>
                            </div>

                            <div class="col-md-4 info-row">
                                <div class="info-label">Nivel de Urgencia</div>
                                <div class="info-value">
                                    <?php
                                    $urgenciaTexto = $urgenciaTextos[$requisicion['nivel_urgencia']];
                                    $urgenciaClase = $urgenciaClases[$requisicion['nivel_urgencia']];
                                    echo "<span class='badge {$urgenciaClase} urgencia-indicator'>{$urgenciaTexto}</span>";
                                    ?>
                                </div>
                            </div>

                            <div class="col-md-4 info-row">
                                <div class="info-label">Fecha de Solicitud</div>
                                <div class="info-value">
                                    <?php echo date('d/m/Y H:i', strtotime($requisicion['fecha_creacion'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6 info-row">
                                <div class="info-label">Jefe Directo</div>
                                <div class="info-value">
                                    <?php
                                    echo htmlspecialchars((string) ($requisicion['jefe_cargo_nombre'] ?? 'Sin especificar'));
                                    ?>
                                </div>
                            </div>

                            <div class="col-md-6 info-row">
                                <div class="info-label">Solicitado Por</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($requisicion['usuario_nombre'] . ' ' . $requisicion['usuario_apellido']); ?>
                                </div>
                            </div>
                        </div>

                        <h5 class="section-title mt-4">Justificación de la Vacante</h5>
                        <div class="alert alert-light border">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($requisicion['justificacion'])); ?></p>
                        </div>

                        <h5 class="section-title mt-4">Perfil del Candidato y Requerimiento</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="info-label">Estudios mínimos requeridos</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars((string) $requisicion['estudios_minimos']); ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="info-label">Carreras profesionales aptas</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars((string) $requisicion['carreras_aptas']); ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="info-label">Conocimientos específicos</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars((string) $requisicion['conocimientos_especificos']); ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="info-label">Idiomas</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars((string) $requisicion['idiomas']); ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="info-label">Uso de herramientas/Office</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars((string) $requisicion['herramientas_office']); ?>
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <div class="info-label">Aptitudes específicas deseadas</div>
                                <div class="info-value">
                                    <?php echo nl2br(htmlspecialchars((string) $requisicion['aptitudes_especificas'])); ?>
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <div class="info-label">Experiencia deseada</div>
                                <div class="info-value">
                                    <?php echo nl2br(htmlspecialchars((string) $requisicion['experiencia_deseada'])); ?>
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <div class="info-label">Funciones y responsabilidades generales del puesto</div>
                                <div class="info-value">
                                    <?php echo nl2br(htmlspecialchars((string) $requisicion['funciones_responsabilidades'])); ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($requisicion['status'] !== 'Solicitado'): ?>
                            <div class="comentarios-section mt-4">
                                <h5 class="section-title">Comentarios de Aprobación / Rechazo</h5>
                                <?php if ($requisicion['comentario_aprobacion_rechazo']): ?>
                                    <p class="mb-0">
                                        <?php echo nl2br(htmlspecialchars($requisicion['comentario_aprobacion_rechazo'])); ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">Sin comentarios</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($puedeAprobar && $requisicion['status'] === 'Solicitado'): ?>
                            <div class="mt-5 pt-4 border-top">
                                <h5 class="section-title">Acciones de Revisión</h5>

                                <div class="mb-3">
                                    <label for="comentarioRevision" class="form-label">Comentarios / Notas de
                                        Decisión</label>
                                    <textarea class="form-control" id="comentarioRevision" rows="4"
                                        placeholder="Ingrese sus observaciones aquí..."></textarea>
                                </div>

                                <div class="d-flex gap-3">
                                    <button class="btn btn-aprobar btn-lg"
                                        onclick="aprobarSolicitud(<?php echo $idRequisicion; ?>)">
                                        <i class="bi bi-check-circle me-2"></i>Aprobar Solicitud
                                    </button>
                                    <button class="btn btn-rechazar btn-lg"
                                        onclick="rechazarSolicitud(<?php echo $idRequisicion; ?>)">
                                        <i class="bi bi-x-circle me-2"></i>Rechazar Solicitud
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
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
                        Guía del Detalle de Requisición
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
                                        <i class="bi bi-info-circle me-2"></i> Vista de Detalle
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Esta página muestra toda la información de la requisición incluyendo
                                        justificación, perfil del puesto y estado actual.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="bi bi-check-circle me-2"></i> Aprobar
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Al aprobar una requisición, la plaza se habilita automáticamente para recibir
                                        postulaciones de candidatos.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-danger border-bottom pb-2 fw-bold">
                                        <i class="bi bi-x-circle me-2"></i> Rechazar
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Si la requisición no procede, puedes rechazarla agregando comentarios para el
                                        solicitante.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="bi bi-file-earmark me-2"></i> Perfil de Puesto
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Si el solicitante adjuntó un perfil de puesto, puedes descargarlo haciendo clic
                                        en el enlace.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
                        <br>
                        Solo el personal de gerencia con los permisos apropiados puede aprobar o rechazar requisiciones.
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
        async function aprobarSolicitud(idRequisicion) {
            const comentario = document.getElementById('comentarioRevision').value;

            const result = await Swal.fire({
                title: '¿Aprobar requisición?',
                text: 'La plaza se habilitará para recibir postulaciones',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, aprobar',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) return;

            try {
                Swal.fire({
                    title: 'Procesando...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch('ajax/postulacion_detalle_requisicion_aprobar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id_requisicion: idRequisicion,
                        comentario: comentario
                    })
                });

                const data = await response.json();

                if (data.success) {
                    await Swal.fire('Aprobado', 'La requisición ha sido aprobada exitosamente', 'success');
                    window.location.reload();
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', error.message, 'error');
            }
        }

        async function rechazarSolicitud(idRequisicion) {
            const comentario = document.getElementById('comentarioRevision').value;

            if (!comentario.trim()) {
                Swal.fire('Validación', 'Debe ingresar un comentario explicando el rechazo', 'warning');
                return;
            }

            const result = await Swal.fire({
                title: '¿Rechazar requisición?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, rechazar',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) return;

            try {
                Swal.fire({
                    title: 'Procesando...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch('ajax/postulacion_detalle_requisicion_rechazar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id_requisicion: idRequisicion,
                        comentario: comentario
                    })
                });

                const data = await response.json();

                if (data.success) {
                    await Swal.fire('Rechazado', 'La requisición ha sido rechazada', 'success');
                    window.location.reload();
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', error.message, 'error');
            }
        }
    </script>
</body>

</html>