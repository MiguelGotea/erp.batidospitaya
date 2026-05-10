<?php
// postulacion_evaluacion_rh.php

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// ID de postulación
$idPostulacion = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idPostulacion <= 0) {
    header('Location: postulacion_plazas_activas.php');
    exit();
}

// Obtener datos del candidato y posible entrevista programada
$sql = "SELECT pp.*, nc.Nombre as cargo_nombre,
               ec.fecha_entrevista as e_fecha, ec.hora_entrevista as e_hora, ec.modalidad_entrevista as e_modalidad,
               CONCAT(o.Nombre, ' ', o.Apellido) as e_entrevistador,
               pc.id as id_plaza
        FROM postulacion_plaza pp
        INNER JOIN NivelesCargos nc ON pp.cargo_aplicado = nc.CodNivelesCargos
        LEFT JOIN entrevistas_candidatos ec ON pp.id = ec.id_postulacion AND ec.resultado_entrevista = 'Pendiente'
        LEFT JOIN Operarios o ON ec.reclutador_entrevista = o.CodOperario
        LEFT JOIN plazas_cargos pc ON pc.cargo = pp.cargo_aplicado 
            AND (pc.sucursal = pp.sucursal_aplicada OR (pc.sucursal IS NULL AND pp.sucursal_aplicada IS NULL))
        WHERE pp.id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindValue(':id', $idPostulacion, PDO::PARAM_INT);
$stmt->execute();
$candidato = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidato) {
    header('Location: postulacion_plazas_activas.php');
    exit();
}

// Obtener evaluación previa si existe
$sqlEval = "SELECT * FROM postulacion_evaluacion_rh WHERE id_postulacion = :id";
$stmtEval = $conn->prepare($sqlEval);
$stmtEval->bindValue(':id', $idPostulacion, PDO::PARAM_INT);
$stmtEval->execute();
$evaluacion = $stmtEval->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluación RH -
        <?php echo htmlspecialchars($candidato['nombre']); ?>
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/postulacion_evaluacion.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Evaluación de Candidato - RH'); ?>

            <div class="container-fluid p-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body bg-light">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1">
                                    <?php echo htmlspecialchars($candidato['nombre']); ?>
                                </h4>
                                <p class="text-muted mb-2">Postulando para: <strong>
                                        <?php echo htmlspecialchars($candidato['cargo_nombre']); ?>
                                    </strong></p>
                                <div class="d-flex align-items-center mt-2">
                                    <?php if ($candidato['ruta_cv']): ?>
                                        <a href="https://talento.batidospitaya.com/uploads/<?php echo htmlspecialchars($candidato['ruta_cv']); ?>"
                                            target="_blank" class="btn btn-sm btn-primary me-2">
                                            <i class="bi bi-file-earmark-pdf me-1"></i>Ver CV
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($candidato['telefono']): ?>
                                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $candidato['telefono']); ?>"
                                            target="_blank" class="btn btn-sm btn-success">
                                            <i class="bi bi-whatsapp me-1"></i>WhatsApp
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="badge bg-primary fs-6">Mesa de Evaluación RH</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalles de la Entrevista Programada (SI EXISTE) -->
                <?php if ($candidato['e_entrevistador']): ?>
                    <div class="card shadow-sm mb-4 border-start border-success border-5">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 text-success"><i class="bi bi-calendar-check me-2"></i>Detalles de la Entrevista Programada</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <p class="mb-1 small text-muted">Entrevistador:</p>
                                    <p class="fw-bold"><?php echo htmlspecialchars($candidato['e_entrevistador']); ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p class="mb-1 small text-muted">Fecha:</p>
                                    <p class="fw-bold"><?php echo date('d/m/Y', strtotime($candidato['e_fecha'])); ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p class="mb-1 small text-muted">Hora:</p>
                                    <p class="fw-bold"><?php echo date('H:i', strtotime($candidato['e_hora'])); ?></p>
                                </div>
                                <div class="col-md-3">
                                    <p class="mb-1 small text-muted">Modalidad:</p>
                                    <p class="fw-bold"><?php echo htmlspecialchars($candidato['e_modalidad']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form id="formEvalRH" enctype="multipart/form-data">
                    <input type="hidden" name="id_postulacion" value="<?php echo $idPostulacion; ?>">

                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Preguntas Clave -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0 text-primary">Preguntas Clave (RH)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">

                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Hora de Inicio Entrevista</label>
                                            <input type="time" name="hora_inicio" class="form-control form-control-sm"
                                                required value="<?php echo $evaluacion ? $evaluacion['hora_inicio'] : ''; ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold">Hora de Fin Entrevista</label>
                                            <input type="time" name="hora_fin" class="form-control form-control-sm"
                                                required value="<?php echo $evaluacion ? $evaluacion['hora_fin'] : ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <?php
                            $preguntas = [
                                "Entorno Familiar y Valores",
                                "Experiencia Laboral Relevante",
                                "Preguntas de Comportamiento",
                                "Actitud y Cultura",
                                "Cierre de Entrevista"
                            ];

                            foreach ($preguntas as $i => $pregunta):
                                $num = $i + 1;
                                $val = $evaluacion ? (int) $evaluacion["p{$num}_calificacion"] : 0;
                                $comentario = $evaluacion ? $evaluacion["p{$num}_comentario"] : '';
                            ?>
                                <div class="mb-4 question-row p-3 border rounded shadow-sm">
                                    <label class="form-label d-block fw-bold text-dark">
                                        <?php echo $num . ". " . $pregunta; ?>
                                    </label>
                                    <div class="star-rating mb-2" data-name="p<?php echo $num; ?>">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="bi <?php echo ($s <= $val) ? 'bi-star-fill active' : 'bi-star'; ?> star"
                                                data-value="<?php echo $s; ?>"></i>
                                        <?php endfor; ?>
                                        <input type="hidden" name="p<?php echo $num; ?>" value="<?php echo $val; ?>"
                                            required>
                                    </div>
                                    <textarea class="form-control" name="p<?php echo $num; ?>_comentario" rows="3"
                                        placeholder="Desarrolle la respuesta del candidato aquí..."><?php echo htmlspecialchars($comentario); ?></textarea>
                                </div>
                            <?php endforeach; ?>

                            <!-- Evidencia -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0 text-primary">Evidencia de Entrevista Física</h5>
                                </div>
                                <div class="card-body">
                                    <div class="upload-area <?php echo ($evaluacion && $evaluacion['evidencia_ruta']) ? 'd-none' : ''; ?>"
                                        id="dropZone">
                                        <i class="bi bi-cloud-upload fs-1 text-primary"></i>
                                        <p class="mt-2">Arrastra tu archivo aquí o haz clic para seleccionar</p>
                                        <small class="text-muted">PDF o Imágenes (Máx. 10MB)</small>
                                        <input type="file" name="evidencia" id="fileInput" class="d-none"
                                            accept=".pdf,image/*">
                                    </div>

                                    <?php if ($evaluacion && $evaluacion['evidencia_ruta']): ?>
                                        <div id="existingEvidence" class="mb-3">
                                            <div class="alert alert-success d-flex justify-content-between align-items-center">
                                                <span>
                                                    <i class="bi bi-file-earmark-check me-2"></i>
                                                    Evidencia guardada:
                                                    <a href="uploads/<?php echo htmlspecialchars($evaluacion['evidencia_ruta']); ?>"
                                                        target="_blank" class="text-primary fw-bold">Ver Archivo</a>
                                                </span>
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="document.getElementById('dropZone').classList.remove('d-none'); document.getElementById('existingEvidence').classList.add('d-none');">
                                                    Cambiar
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div id="fileInfo" class="mt-3 d-none">
                                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-file-earmark-check me-2"></i><span
                                                    id="fileName"></span></span>
                                            <button type="button" class="btn-close" id="removeFile"></button>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-outline-secondary w-100"
                                            onclick="activarCamara()">
                                            <i class="bi bi-camera me-2"></i>Tomar Foto con Cámara
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Resultados Recap -->
                            <div class="card shadow-sm mb-4 sticky-top" style="top: 20px;">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">Resumen y Veredicto</h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-4">
                                        <h6>Puntaje RH Acumulado</h6>
                                        <div class="h2 text-primary" id="puntajeDisplay">
                                            <?php echo $evaluacion ? number_format($evaluacion['puntaje_acumulado'], 1) : "0.0"; ?>
                                        </div>
                                        <div id="starsDisplay" class="text-warning h4">
                                            <?php
                                            if ($evaluacion) {
                                                $roundPromedio = round((float) $evaluacion['puntaje_acumulado']);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo '<i class="bi ' . ($i <= $roundPromedio ? 'bi-star-fill' : 'bi-star') . ' mx-1"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Conclusiones Generales</label>
                                        <textarea class="form-control" name="conclusiones" rows="4" required
                                            placeholder="Escriba aquí las observaciones finales del reclutador..."><?php echo $evaluacion ? htmlspecialchars($evaluacion['conclusiones_generales']) : ''; ?></textarea>
                                    </div>

                                    <?php if ($evaluacion): ?>
                                        <div class="alert alert-info py-2 small mb-3">
                                            <i class="bi bi-info-circle me-2"></i>Ya se ha registrado una evaluación el
                                            <?php echo date('d/m/Y H:i', strtotime($evaluacion['fecha_evaluacion'])); ?>.
                                            Veredicto guardado: <strong><?php echo strtoupper($evaluacion['veredicto']); ?></strong>
                                        </div>
                                    <?php endif; ?>

                                    <hr>

                                    <!-- CARD DE PROGRAMACIÓN (Estilo similar a detalle candidato) -->
                                    <div class="card shadow-sm mb-4 border-top border-success border-5">
                                        <div class="card-header bg-white">
                                            <h6 class="mb-0 text-success fw-bold"><i class="bi bi-calendar-plus me-2"></i>Programar Entrevista Final</h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="small text-muted mb-3">Complete estos datos si desea programar la entrevista técnica con el jefe de área al aprobar.</p>
                                            <div class="row g-3">
                                                <div class="col-md-12">
                                                    <label class="form-label small fw-bold">Jefe Inmediato *</label>
                                                    <select name="entrevistador_jefe" id="entrevistadorJefe" class="form-select form-select-sm" required>
                                                        <option value="">Cargando responsable...</option>
                                                    </select>
                                                    <small id="msgJefe"></small>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label small fw-bold">Fecha *</label>
                                                    <input type="date" name="fecha_entrevista" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label small fw-bold">Hora *</label>
                                                    <input type="time" name="hora_entrevista" class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label small fw-bold">Modalidad *</label>
                                                    <select name="modalidad_entrevista" class="form-select form-select-sm" required>
                                                        <option value="Presencial">Presencial</option>
                                                        <option value="Virtual (Google Meet)">Virtual (Google Meet)</option>
                                                        <option value="Telefónica">Telefónica</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label small fw-bold">Notas para el Jefe</label>
                                                    <textarea name="notas_entrevista" class="form-control form-control-sm" rows="2" placeholder="Información relevante..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-success btn-lg"
                                            onclick="finalizarEvaluacion('Aprobado')">
                                            <i class="bi bi-check-circle me-2"></i><?php echo $evaluacion ? 'Actualizar y Programar' : 'Aprobado y Programar'; ?>
                                        </button>
                                        <button type="button" class="btn btn-danger"
                                            onclick="finalizarEvaluacion('Rechazado')">
                                            <i class="bi bi-x-circle me-2"></i><?php echo $evaluacion ? 'Actualizar a Rechazado' : 'Rechazado (Descartar)'; ?>
                                        </button>
                                        <a href="postulacion_detalle_candidato.php?id=<?php echo $idPostulacion; ?>"
                                            class="btn btn-outline-secondary">
                                            Regresar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Cámara Placeholder -->
    <div class="modal fade" id="cameraModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Capturar Foto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <video id="video" width="100%" autoplay></video>
                    <canvas id="canvas" class="d-none"></canvas>
                    <button type="button" class="btn btn-primary mt-3" id="snap">
                        <i class="bi bi-camera"></i> Tomar Foto
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const idPlaza = <?php echo (int)($candidato['id_plaza'] ?? 0); ?>;
    </script>
    <script src="js/postulacion_evaluacion_rh.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>