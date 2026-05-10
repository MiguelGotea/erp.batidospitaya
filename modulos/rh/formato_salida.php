<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';
require_once 'editar_colaborador_componentes/logic/funciones_colaborador.php';

$usuarioActual = obtenerUsuarioActual();
$cargoId = $usuarioActual['CodNivelesCargos'] ?? 0;

if (!isset($_GET['id_contrato']) || empty($_GET['id_contrato'])) {
    $_SESSION['error'] = 'No se ha especificado un contrato';
    header('Location: colaboradores.php');
    exit();
}

$idContrato = intval($_GET['id_contrato']);
$contrato = obtenerContratoPorId($idContrato);

if (!$contrato) {
    $_SESSION['error'] = 'Contrato no encontrado';
    header('Location: colaboradores.php');
    exit();
}

$codOperario = $contrato['cod_operario'];
$formato = obtenerFormatoSalidaPorContrato($idContrato);

// Manejar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_formato') {
    $datos = $_POST;
    $datos['cod_contrato'] = $idContrato;
    $datos['usuario_registro'] = $usuarioActual['CodOperario'];
    $datos['usuario_creador'] = $formato ? $formato['usuario_creador'] : $usuarioActual['CodOperario'];
    $datos['usuario_ultima_modificacion'] = $usuarioActual['CodOperario'];
    
    $resultado = guardarFormatoSalida($datos);
    
    if ($resultado['exito']) {
        $_SESSION['exito'] = $resultado['mensaje'];
        header("Location: editar_colaborador.php?id=$codOperario&pestaña=contrato");
        exit();
    } else {
        $error = $resultado['mensaje'];
    }
}

$tituloPagina = "Formato de Salida - " . $contrato['operario_nombre'] . " " . $contrato['operario_apellido'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $tituloPagina ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="/core/assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css">
    <link rel="stylesheet" href="css/formato_salida.css?v=<?= time() ?>">
</head>
<body>
    <?= renderMenuLateral($cargoId) ?>
    <div class="main-container">
        <div class="sub-container">
            <?= renderHeader($usuarioActual, false, 'Recursos Humanos') ?>
            
            <div class="exit-form-container">
                <div class="form-header">
                    <h2>Formato de Entrevista de Salida</h2>
                    <p>Información del Colaborador</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <?= $_SESSION['exito'] ?>
                        <?php unset($_SESSION['exito']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="accion" value="guardar_formato">
                    
                    <!-- Información General -->
                    <div class="form-section">
                        <h3>I. Datos Generales</h3>
                        <div class="info-grid">
                            <div class="form-group">
                                <label>Nombre del Colaborador</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($contrato['operario_nombre'] . " " . $contrato['operario_apellido']) ?>" readonly style="background: #f1f3f5;">
                            </div>
                            <div class="form-group">
                                <label>Cargo / Puesto</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($contrato['cargo'] ?? 'No especificado') ?>" readonly style="background: #f1f3f5;">
                            </div>
                            <div class="form-group">
                                <label>Sucursal / Area</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($contrato['sucursal'] ?? 'No especificada') ?>" readonly style="background: #f1f3f5;">
                            </div>
                            <div class="form-group">
                                <label>Fecha de Entrevista *</label>
                                <input type="date" name="fecha" class="form-control" value="<?= $formato ? $formato['fecha'] : date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>

                    <!-- Sección II: Inducción y Entrenamiento -->
                    <div class="form-section">
                        <h3>II. Inducción y/o Entrenamiento</h3>
                        <div class="form-group">
                            <label>1. ¿Recibió inducción al ingresar a la empresa? Explique brevemente.</label>
                            <textarea name="induccion_p1" class="form-control" rows="2"><?= htmlspecialchars($formato['induccion_p1'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>2. ¿Cómo califica el entrenamiento recibido para su puesto?</label>
                            <textarea name="induccion_p2" class="form-control" rows="2"><?= htmlspecialchars($formato['induccion_p2'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Sección III: Información Laboral -->
                    <div class="form-section">
                        <h3>III. Información Laboral</h3>
                        <div class="form-group">
                            <label>1. ¿Qué es lo que más le gustó de trabajar en la empresa?</label>
                            <textarea name="laboral_p1" class="form-control" rows="2"><?= htmlspecialchars($formato['laboral_p1'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>2. ¿Qué es lo que menos le gustó de trabajar en la empresa?</label>
                            <textarea name="laboral_p2" class="form-control" rows="2"><?= htmlspecialchars($formato['laboral_p2'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>3. ¿Cómo califica la comunicación interna?</label>
                            <textarea name="laboral_p3" class="form-control" rows="2"><?= htmlspecialchars($formato['laboral_p3'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>4. ¿Existían las herramientas necesarias para su labor?</label>
                            <textarea name="laboral_p4" class="form-control" rows="2"><?= htmlspecialchars($formato['laboral_p4'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>5. ¿Recibió retroalimentación sobre su desempeño?</label>
                            <textarea name="laboral_p5" class="form-control" rows="2"><?= htmlspecialchars($formato['laboral_p5'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>6. Motivo principal de su retiro:</label>
                            <textarea name="laboral_p6" class="form-control" rows="2"><?= htmlspecialchars($formato['laboral_p6'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Sección IV: Niveles de Satisfacción -->
                    <div class="form-section">
                        <h3>IV. Niveles de Satisfacción (1=Muy Insatisfecho, 5=Muy Satisfecho)</h3>
                        <?php
                        $ratings = [
                            'sat_salario' => 'Salario y Beneficios',
                            'sat_ambiente' => 'Ambiente Físico de Trabajo',
                            'sat_relacion_companeros' => 'Relación con Compañeros',
                            'sat_relacion_jefe' => 'Relación con Jefe Inmediato',
                            'sat_relacion_superiores' => 'Relación con Superiores',
                            'sat_horario' => 'Horario de Trabajo',
                            'sat_trabajo_equipo' => 'Trabajo en Equipo',
                            'sat_recomendaria' => '¿Recomendaría la empresa para trabajar?'
                        ];
                        foreach ($ratings as $name => $label): ?>
                            <div class="rating-group">
                                <span><?= $label ?></span>
                                <div class="rating-options">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <label class="rating-option">
                                            <input type="radio" name="<?= $name ?>" value="<?= $i ?>" <?= (isset($formato[$name]) && $formato[$name] == $i) ? 'checked' : ($i==3 && !isset($formato[$name]) ? 'checked' : '') ?>>
                                            <span class="rating-number"><?= $i ?></span>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Opinión y Clima -->
                    <div class="form-section">
                        <h3>V. Opinión Final y Clima Organizacional</h3>
                        <div class="form-group">
                            <label>Sugerencias o comentarios adicionales sobre el clima laboral:</label>
                            <textarea name="opinion_clima" class="form-control" rows="4"><?= htmlspecialchars($formato['opinion_clima'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Firmas -->
                    <div class="signatures-section">
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <input type="text" name="firma_entrevistado" class="form-control" placeholder="Nombre completo del entrevistado" value="<?= htmlspecialchars($formato['firma_entrevistado'] ?? ($contrato['operario_nombre'] . " " . $contrato['operario_apellido'])) ?>">
                            <div class="signature-title">Firma del Colaborador</div>
                        </div>
                        <div class="signature-box">
                            <div class="signature-line"></div>
                            <input type="text" name="firma_entrevistador" class="form-control" placeholder="Nombre completo del entrevistador" value="<?= htmlspecialchars($formato['firma_entrevistador'] ?? ($usuarioActual['Nombre'] . " " . $usuarioActual['Apellido'])) ?>">
                            <div class="signature-title">Firma de Recursos Humanos / Entrevistador</div>
                        </div>
                    </div>

                    <div class="btn-container">
                        <button type="button" class="btn-premium btn-secondary" onclick="window.location.href='editar_colaborador.php?id=<?= $codOperario ?>&pestaña=contrato'">
                            Cancelar
                        </button>
                        <button type="submit" class="btn-premium btn-primary">
                            <i class="fas fa-save"></i> <?= $formato ? 'Actualizar Formato' : 'Guardar Formato' ?>
                        </button>
                    </div>
                </form>

                <?php if ($formato): ?>
                    <div style="margin-top: 30px; font-size: 0.8rem; color: #666; border-top: 1px solid #eee; padding-top: 10px;">
                        <p>Registro creado el: <?= date('d/m/Y H:i', strtotime($formato['fecha_registro'])) ?></p>
                        <?php if (!empty($formato['fecha_ultima_modificacion'])): ?>
                            <p>Última modificación: <?= date('d/m/Y H:i', strtotime($formato['fecha_ultima_modificacion'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
