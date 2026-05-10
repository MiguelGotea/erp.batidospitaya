<?php
// postulacion_evaluacion_jefe.php

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

// Obtener datos del candidato
$sql = "SELECT pp.*, nc.Nombre as cargo_nombre,
               pc.id as id_plaza
        FROM postulacion_plaza pp
        INNER JOIN NivelesCargos nc ON pp.cargo_aplicado = nc.CodNivelesCargos
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
$sqlEval = "SELECT * FROM postulacion_evaluacion_jefe WHERE id_postulacion = :id";
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
    <title>Evaluación Técnica -
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
            <?php echo renderHeader($usuario, false, 'Evaluación Técnica del Jefe de Área'); ?>

            <div class="container-fluid p-4">
                <div class="card shadow-sm mb-4 border-start border-info border-5">
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
                                <span class="badge bg-info text-dark fs-6">Evaluación de Área / Técnica</span>
                            </div>
                        </div>
                        <hr>

                    </div>
                </div>


                <form id="formEvalJefe" enctype="multipart/form-data">
                    <input type="hidden" name="id_postulacion" value="<?php echo $idPostulacion; ?>">

                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Evaluación de Competencias y Técnica -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0 text-info">Evaluación de Competencias y Técnica</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $pTecnicas = [
                                        "¿Qué conoce de Batidos Pitaya?",
                                        "Motivación en la búsqueda de empleo.",
                                        "Deme un resumen de su perfil laboral y profesional (empleos anteriores y formación).",
                                        "¿Qué tan bien laboras con cambios imprevistos y trabajo bajo presión?",
                                        "Disponibilidad.",
                                        "Abordar aspectos técnicos según el tipo de puesto."
                                    ];

                                    foreach ($pTecnicas as $i => $pregunta):
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
                                                placeholder="Registre la respuesta aquí..."><?php echo htmlspecialchars($comentario); ?></textarea>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Evidencia -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0 text-info">Evidencia de Entrevista Física / Prueba Técnica</h5>
                                </div>
                                <div class="card-body">
                                    <div class="upload-area <?php echo ($evaluacion && $evaluacion['evidencia_ruta']) ? 'd-none' : ''; ?>"
                                        id="dropZone">
                                        <i class="bi bi-file-earmark-arrow-up fs-1 text-info"></i>
                                        <p class="mt-2">Arrastra tu archivo aquí o haz clic para seleccionar</p>
                                        <small class="text-muted">PDF o Imágenes - Evidencia de prueba técnica (Máx.
                                            10MB)</small>
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
                                                        target="_blank" class="text-success fw-bold">Ver Archivo</a>
                                                </span>
                                                <button type="button" class="btn btn-sm btn-outline-success"
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
                                        <button type="button" class="btn btn-outline-info w-100"
                                            onclick="activarCamara()">
                                            <i class="bi bi-camera me-2"></i>Capturar Foto de Prueba
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Veredicto Final -->
                            <div class="card shadow-sm mb-4 sticky-top" style="top: 20px;">
                                <div class="card-header bg-info text-dark">
                                    <h5 class="mb-0">Veredicto Final del Jefe</h5>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-4">
                                        <h6>Promedio de Estrellas</h6>
                                        <div class="h2 text-info" id="puntajeDisplay">
                                            <?php echo $evaluacion ? number_format($evaluacion['promedio_estrellas'], 1) : "0.0"; ?>
                                        </div>
                                        <div id="starsDisplay" class="text-warning h4">
                                            <?php
                                            if ($evaluacion) {
                                                $roundPromedio = round((float) $evaluacion['promedio_estrellas']);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo '<i class="bi ' . ($i <= $roundPromedio ? 'bi-star-fill' : 'bi-star') . ' mx-1"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Conclusiones Finales</label>
                                        <textarea class="form-control" name="conclusiones" rows="5" required
                                            placeholder="Detalle si el candidato es apto técnicamente para el puesto..."><?php echo $evaluacion ? htmlspecialchars($evaluacion['conclusiones_finales']) : ''; ?></textarea>
                                    </div>

                                    <?php if ($evaluacion): ?>
                                        <div class="alert alert-info py-2 small mb-3">
                                            <i class="bi bi-info-circle me-2"></i>Ya se ha registrado una evaluación el
                                            <?php echo date('d/m/Y H:i', strtotime($evaluacion['fecha_evaluacion'])); ?>.
                                            Veredicto guardado: <strong><?php echo strtoupper($evaluacion['veredicto']); ?></strong>
                                        </div>
                                    <?php endif; ?>

                                    <hr>

                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-success btn-lg"
                                            onclick="finalizarEvaluacion('Aprobado')">
                                            <i class="bi bi-person-check me-2"></i><?php echo $evaluacion ? 'Actualizar Aprobación' : 'Aprobado para Contratación'; ?>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger"
                                            onclick="finalizarEvaluacion('Descartado')">
                                            <i class="bi bi-person-x me-2"></i><?php echo $evaluacion ? 'Actualizar a Descartado' : 'Descartado por Área'; ?>
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

    <!-- Modal Cámara -->
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
                    <button type="button" class="btn btn-info mt-3" id="snap">
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
    <script src="js/postulacion_evaluacion_jefe.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>