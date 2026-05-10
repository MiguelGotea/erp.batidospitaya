<?php
// postulacion_detalle_candidato.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('postulacion_plazas_activas', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeAprobar = tienePermiso('postulacion_plazas_activas', 'aprobar', $cargoOperario);

// Obtener ID de candidato
$idCandidato = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idCandidato <= 0) {
    header('Location: postulacion_plazas_activas.php');
    exit();
}

// Obtener información del candidato
$sql = "SELECT 
            pp.*,
            nc.Nombre as nombre_cargo,
            nc.Area as area_cargo,
            s.nombre as sucursal_nombre,
            pc.id as id_plaza,
            pc.salario_propuesto,
            ec.fecha_entrevista,
            ec.hora_entrevista,
            ec.modalidad_entrevista,
            ec.notas_adicionales,
            ec.resultado_entrevista,
            ec.reclutador_entrevista,
            CONCAT(o.Nombre, ' ', o.Apellido) as entrevistador_nombre
        FROM postulacion_plaza pp
        INNER JOIN NivelesCargos nc ON pp.cargo_aplicado = nc.CodNivelesCargos
        LEFT JOIN sucursales s ON pp.sucursal_aplicada = s.codigo
        LEFT JOIN plazas_cargos pc ON pc.cargo = pp.cargo_aplicado 
            AND (pc.sucursal = pp.sucursal_aplicada OR (pc.sucursal IS NULL AND pp.sucursal_aplicada IS NULL))
        LEFT JOIN entrevistas_candidatos ec ON ec.id_postulacion = pp.id
        LEFT JOIN Operarios o ON ec.reclutador_entrevista = o.CodOperario
        WHERE pp.id = :id";

$stmt = $conn->prepare($sql);
$stmt->bindValue(':id', $idCandidato, PDO::PARAM_INT);
$stmt->execute();
$candidato = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidato) {
    header('Location: postulacion_plazas_activas.php');
    exit();
}

// Agregar prefijo a la ruta del CV
if ($candidato['ruta_cv']) {
    $candidato['ruta_cv'] = 'https://talento.batidospitaya.com/uploads/' . $candidato['ruta_cv'];
}

// Obtener análisis IA si existe
$sqlIA = "SELECT campo, valor, confianza FROM validacion_cv_ia WHERE id_postulacion = :id ORDER BY campo";
$stmtIA = $conn->prepare($sqlIA);
$stmtIA = $conn->prepare($sqlIA);
$stmtIA->bindValue(':id', $idCandidato, PDO::PARAM_INT);
$stmtIA->execute();
$analisisIA = $stmtIA->fetchAll(PDO::FETCH_ASSOC);

// Obtener datos de la entrevista técnica telefónica si existen
$sqlTel = "SELECT * FROM postulacion_entrevista_telefonica WHERE id_postulacion = :id";
$stmtTel = $conn->prepare($sqlTel);
$stmtTel->bindValue(':id', $idCandidato, PDO::PARAM_INT);
$stmtTel->execute();
$entrevistaTelefonica = $stmtTel->fetch(PDO::FETCH_ASSOC) ?: [];

// Calcular match promedio
$matchPromedio = 0;
if (count($analisisIA) > 0) {
    $sumaConfianza = array_sum(array_column($analisisIA, 'confianza'));
    $matchPromedio = round($sumaConfianza / count($analisisIA), 2);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - <?php echo htmlspecialchars($candidato['nombre']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/postulacion_detalle_candidato.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Perfil del Candidato'); ?>

            <div class="container-fluid p-4">
                <?php
                // El panel derecho se muestra si está solicitado o rechazado, O si ya está aprobado pero el usuario tiene nivel 13 para editar
                $mostrarPanelDerecho = ($candidato['status'] === 'solicitado' || $candidato['status'] === 'rechazado' || ($cargoOperario == 13 && $candidato['status'] === 'aprobado'));
                $colIzquierda = $mostrarPanelDerecho ? 'col-lg-8' : 'col-lg-12';
                ?>
                <div class="row">
                    <!-- Columna Izquierda: Información del Candidato -->
                    <div class="<?php echo $colIzquierda; ?>">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($candidato['nombre']); ?></h5>
                                        <small><?php echo htmlspecialchars($candidato['nombre_cargo']); ?></small>
                                    </div>
                                    <div>
                                        <?php
                                        $statusBadges = [
                                            'solicitado' => '<span class="badge bg-secondary fs-6">SOLICITADO</span>',
                                            'aprobado' => '<span class="badge bg-success fs-6">APROBADO</span>',
                                            'rechazado' => '<span class="badge bg-danger fs-6">RECHAZADO</span>',
                                            'seleccionado' => '<span class="badge bg-primary fs-6">SELECCIONADO</span>',
                                            'denegado' => '<span class="badge bg-warning fs-6">DENEGADO</span>',
                                            'contratado' => '<span class="badge bg-info fs-6">CONTRATADO</span>'
                                        ];
                                        echo $statusBadges[strtolower($candidato['status'])] ?? "<span class='badge bg-dark fs-6'>{$candidato['status']}</span>";
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center">
                                        <?php if ($candidato['ruta_cv']): ?>
                                            <a href="<?php echo htmlspecialchars($candidato['ruta_cv']); ?>" target="_blank"
                                                class="btn btn-primary me-2">
                                                <i class="bi bi-file-earmark-pdf me-2"></i>Ver CV
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($candidato['telefono']): ?>
                                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $candidato['telefono']); ?>"
                                                target="_blank" class="btn btn-success">
                                                <i class="bi bi-whatsapp me-2"></i>WhatsApp
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($matchPromedio > 0): ?>
                                        <div class="match-score">
                                            <div class="match-percentage"><?php echo $matchPromedio; ?>%</div>
                                            <div class="match-label">AI Match Score</div>
                                            <div class="rating-stars">
                                                <?php
                                                $estrellas = round($matchPromedio / 20);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $estrellas ? '★' : '☆';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <h6 class="section-title">Información de Contacto</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6 info-row">
                                        <div class="info-label">Correo Electrónico</div>
                                        <div class="info-value">
                                            <i class="bi bi-envelope me-2"></i>
                                            <?php echo htmlspecialchars($candidato['correo']); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 info-row">
                                        <div class="info-label">Teléfono</div>
                                        <div class="info-value">
                                            <i class="bi bi-telephone me-2"></i>
                                            <?php echo htmlspecialchars($candidato['telefono']); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 info-row">
                                        <div class="info-label">Dirección</div>
                                        <div class="info-value">
                                            <i class="bi bi-geo-alt me-2"></i>
                                            <?php echo htmlspecialchars($candidato['direccion'] ?? 'Sin dirección'); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6 info-row">
                                        <div class="info-label">Fecha de Postulación</div>
                                        <div class="info-value">
                                            <?php echo date('d/m/Y H:i', strtotime($candidato['fecha_postulacion'])); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6 info-row">
                                        <div class="info-label">Aspiración Salarial</div>
                                        <div class="info-value">
                                            <?php echo $candidato['aspiracion_salarial'] ? 'C$ ' . number_format($candidato['aspiracion_salarial'], 2) : '-'; ?>
                                        </div>
                                    </div>
                                </div>

                                <h6 class="section-title mt-4">Experiencia Laboral</h6>
                                <div class="alert alert-light">
                                    <p class="mb-0">
                                        <?php echo $candidato['experiencia_laboral'] ? nl2br(htmlspecialchars($candidato['experiencia_laboral'])) : 'No especificada'; ?>
                                    </p>
                                </div>

                                <?php if ($candidato['comentario']): ?>
                                    <h6 class="section-title mt-4">Comentarios del Candidato</h6>
                                    <div class="alert alert-light">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($candidato['comentario'])); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>


                                <?php if ($candidato['fecha_entrevista']): ?>
                                    <h6 class="section-title mt-4">Detalles de la Entrevista Programada</h6>
                                    <div class="alert alert-success">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <strong>Fecha:</strong>
                                                <?php echo date('d/m/Y', strtotime($candidato['fecha_entrevista'])); ?>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <strong>Hora:</strong>
                                                <?php echo date('H:i', strtotime($candidato['hora_entrevista'])); ?>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <strong>Modalidad:</strong>
                                                <?php echo htmlspecialchars($candidato['modalidad_entrevista']); ?>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <strong>Entrevistador:</strong>
                                                <?php echo htmlspecialchars($candidato['entrevistador_nombre']); ?>
                                            </div>
                                            <?php if ($candidato['notas_adicionales']): ?>
                                                <div class="col-12 mt-2">
                                                    <strong>Notas:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($candidato['notas_adicionales'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Nueva Sección: Entrevista Técnica Telefónica -->
                                <h6 class="section-title mt-4">Entrevista Técnica Telefónica</h6>
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <?php $readonly = !$mostrarPanelDerecho ? 'readonly' : ''; ?>
                                        <?php $disabled = !$mostrarPanelDerecho ? 'disabled' : ''; ?>
                                        <form id="formEntrevistaTelefonica">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Edad *</label>
                                                    <input type="number" class="form-control" name="edad"
                                                        value="<?php echo htmlspecialchars($entrevistaTelefonica['edad'] ?? ''); ?>"
                                                        required <?php echo $readonly; ?>>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Ubicación de Tienda</label>
                                                    <select class="form-select" name="ubicacion_tienda"
                                                        id="selectSucursalesEntrevista"
                                                        data-selected="<?php echo htmlspecialchars($entrevistaTelefonica['ubicacion_tienda'] ?? ''); ?>"
                                                        <?php echo $disabled; ?>>
                                                        <option value="">Seleccione sucursal...</option>
                                                        <!-- Cargado dinámicamente -->
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">¿Trabaja Actualmente?</label>
                                                    <select class="form-select" name="trabaja_actualmente" <?php echo $disabled; ?>>
                                                        <option value="No" <?php echo ($entrevistaTelefonica['trabaja_actualmente'] ?? '') === 'No' ? 'selected' : ''; ?>>No</option>
                                                        <option value="Si" <?php echo ($entrevistaTelefonica['trabaja_actualmente'] ?? '') === 'Si' ? 'selected' : ''; ?>>Si</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Disponibilidad *</label>
                                                    <input type="text" class="form-control" name="disponibilidad"
                                                        placeholder="Ej: Inmediato"
                                                        value="<?php echo htmlspecialchars($entrevistaTelefonica['disponibilidad'] ?? ''); ?>"
                                                        required <?php echo $readonly; ?>>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Lugar de Trabajo (actual o
                                                        último)</label>
                                                    <input type="text" class="form-control" name="lugar_trabajo"
                                                        value="<?php echo htmlspecialchars($entrevistaTelefonica['lugar_trabajo'] ?? ''); ?>"
                                                        <?php echo $readonly; ?>>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Promedio devengado (C$)</label>
                                                    <input type="number" step="0.01" class="form-control"
                                                        name="promedio_devengado"
                                                        value="<?php echo htmlspecialchars($entrevistaTelefonica['promedio_devengado'] ?? ''); ?>"
                                                        <?php echo $readonly; ?>>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Aspiración Salarial (C$) *</label>
                                                    <input type="number" step="0.01" class="form-control"
                                                        name="aspiracion_salarial"
                                                        value="<?php echo htmlspecialchars($entrevistaTelefonica['aspiracion_salarial'] ?? ($candidato['aspiracion_salarial'] ?? '')); ?>"
                                                        required <?php echo $readonly; ?>>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">¿Estudias?</label>
                                                    <select class="form-select" name="estudias" <?php echo $disabled; ?>>
                                                        <option value="No" <?php echo ($entrevistaTelefonica['estudias'] ?? '') === 'No' ? 'selected' : ''; ?>>No</option>
                                                        <option value="Si" <?php echo ($entrevistaTelefonica['estudias'] ?? '') === 'Si' ? 'selected' : ''; ?>>Si</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Modalidad y Horarios</label>
                                                    <input type="text" class="form-control" name="modalidad_horarios"
                                                        placeholder="Ej: Híbrido - L a V"
                                                        value="<?php echo htmlspecialchars($entrevistaTelefonica['modalidad_horarios'] ?? ''); ?>"
                                                        <?php echo $readonly; ?>>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">¿Por qué desea cambiar de
                                                        trabajo?</label>
                                                    <textarea class="form-control" name="motivo_cambio"
                                                        rows="2" <?php echo $readonly; ?>><?php echo htmlspecialchars($entrevistaTelefonica['motivo_cambio'] ?? ''); ?></textarea>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Disponibilidad para trabajar en
                                                        horarios rotativos (detallar)</label>
                                                    <textarea class="form-control"
                                                        name="disponibilidad_horarios_rotativos"
                                                        rows="2" <?php echo $readonly; ?>><?php echo htmlspecialchars($entrevistaTelefonica['disponibilidad_horarios_rotativos'] ?? ''); ?></textarea>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Disponibilidad para traslados de
                                                        tiendas</label>
                                                    <textarea class="form-control" name="disponibilidad_traslados"
                                                        rows="2" <?php echo $readonly; ?>><?php echo htmlspecialchars($entrevistaTelefonica['disponibilidad_traslados'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna Derecha: Programación de Entrevista -->
                    <?php if ($mostrarPanelDerecho): ?>
                        <div class="col-lg-4">
                            <div class="card shadow-sm">
                                <div
                                    class="card-header <?php echo $candidato['status'] === 'Aprobado' ? 'bg-info' : 'bg-success'; ?> text-white">
                                    <h6 class="mb-0">
                                        <?php echo $candidato['status'] === 'Aprobado' ? 'Modificar Entrevista' : 'Detalles de la Entrevista'; ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form id="formEntrevista">
                                        <div class="mb-3">
                                            <label for="fechaEntrevista" class="form-label">Fecha de Entrevista
                                                *</label>
                                            <input type="date" class="form-control" id="fechaEntrevista"
                                                value="<?php echo htmlspecialchars($candidato['fecha_entrevista'] ?? date('Y-m-d')); ?>"
                                                required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="horaEntrevista" class="form-label">Hora de Entrevista *</label>
                                            <input type="time" class="form-control" id="horaEntrevista"
                                                value="<?php echo htmlspecialchars($candidato['hora_entrevista'] ? date('H:i', strtotime($candidato['hora_entrevista'])) : ''); ?>"
                                                required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="entrevistadorRRHH" class="form-label">Entrevistador de RRHH
                                                *</label>
                                            <select class="form-select" id="entrevistadorRRHH"
                                                data-selected="<?php echo htmlspecialchars($candidato['reclutador_entrevista'] ?? ''); ?>"
                                                required>
                                                <option value="">Seleccionar responsable...</option>
                                                <!-- Cargado dinámicamente -->
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="modalidadEntrevista" class="form-label">Modalidad *</label>
                                            <select class="form-select" id="modalidadEntrevista" required>
                                                <option value="">Seleccione...</option>
                                                <option value="Virtual (Google Meet)" <?php echo ($candidato['modalidad_entrevista'] ?? '') === 'Virtual (Google Meet)' ? 'selected' : ''; ?>>Virtual (Google Meet)</option>
                                                <option value="Presencial" <?php echo ($candidato['modalidad_entrevista'] ?? '') === 'Presencial' ? 'selected' : ''; ?>>Presencial</option>
                                                <option value="Telefónica" <?php echo ($candidato['modalidad_entrevista'] ?? '') === 'Telefónica' ? 'selected' : ''; ?>>Telefónica</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="notasAdicionales" class="form-label">Notas Adicionales</label>
                                            <textarea class="form-control" id="notasAdicionales" rows="3"
                                                placeholder="Agregue información relevante..."><?php echo htmlspecialchars($candidato['notas_adicionales'] ?? ''); ?></textarea>
                                        </div>

                                        <?php if ($candidato['status'] === 'solicitado' || $candidato['status'] === 'rechazado'): ?>
                                            <div class="alert alert-info small py-2">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Al confirmar, se enviará automáticamente una invitación de calendario.
                                            </div>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-success btn-lg"
                                                    onclick="aprobarYProgramar()">
                                                    <i class="bi bi-check-circle me-2"></i>Aprobar y Programar
                                                </button>
                                                <button type="button" class="btn btn-primary" onclick="guardarYSalir()">
                                                    <i class="bi bi-save me-2"></i>Guardar y Salir
                                                </button>
                                                <button type="button" class="btn btn-danger" onclick="rechazarCandidato()">
                                                    <i class="bi bi-x-circle me-2"></i>Rechazar
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-warning small py-2">
                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                Al guardar cambios, se reenviará la invitación de calendario actualizada.
                                            </div>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-info text-white btn-lg"
                                                    onclick="modificarEntrevista()">
                                                    <i class="bi bi-save me-2"></i>Guardar Cambios
                                                </button>
                                                <button type="button" class="btn btn-outline-danger"
                                                    onclick="cancelarEntrevista()">
                                                    <i class="bi bi-calendar-x me-2"></i>Cancelar Entrevista
                                                </button>
                                            </div>
                                        <?php endif; ?>


                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal de Ayuda -->
            <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel"
                aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="pageHelpModalLabel">
                                <i class="fas fa-info-circle me-2"></i>
                                Guía del Perfil del Candidato
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
                                                <i class="bi bi-whatsapp me-2"></i> WhatsApp
                                            </h6>
                                            <p class="small text-muted mb-0">
                                                El botón de WhatsApp abre una conversación directa con el candidato
                                                para
                                                coordinar detalles adicionales.
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
                                                Al aprobar, el sistema envía automáticamente invitaciones de
                                                calendario al
                                                candidato y al entrevistador.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-4">
                                    <div class="card h-100 border-0 bg-light">
                                        <div class="card-body">
                                            <h6 class="text-info border-bottom pb-2 fw-bold">
                                                <i class="bi bi-robot me-2"></i> AI Match Score
                                            </h6>
                                            <p class="small text-muted mb-0">
                                                El puntaje de compatibilidad se calcula automáticamente analizando
                                                el CV del
                                                candidato contra los requisitos del puesto.
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
                const idCandidato = <?php echo $idCandidato; ?>;
            </script>
            <script src="js/postulacion_detalle_candidato.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>