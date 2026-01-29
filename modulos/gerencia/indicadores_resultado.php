<?php
ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/auth.php';
require_once '../../includes/header_universal.php';
require_once '../../includes/menu_lateral.php';
require_once '../../core/permissions/permissions.php';
require_once 'includes/funciones_indicadores.php';

// Obtener información del usuario
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Verificar acceso al módulo
//if (!verificarAccesoCargo([11, 16, 13, 42, 12, 49]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
//    header('Location: ../../../index.php');
//    exit();
//}
if (!tienePermiso('kpi_gestion_resultados', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtener la semana actual del sistema
$semanaActual = obtenerSemanaActual();
$numeroSemanaActual = $semanaActual ? $semanaActual['numero_semana'] : 0;

// Calcular semana anterior
$numeroSemanaAnterior = $numeroSemanaActual > 1 ? $numeroSemanaActual - 1 : null;

// Obtener las últimas 8 semanas anteriores a la actual (de la más reciente a la más antigua)
$semanas = [];
if ($numeroSemanaAnterior) {
    for ($i = 0; $i < 8; $i++) {
        $semanaNum = $numeroSemanaAnterior - $i;
        if ($semanaNum > 0) {
            $semana = obtenerSemanaPorNumero($semanaNum);
            if ($semana) {
                $semanas[] = $semana;
            }
        }
    }
}

// Obtener todos los indicadores activos ordenados por área/cargo - INCLUYENDO TIPO Y DECIMALES
$query = "SELECT 
            isem.id,
            isem.nombre,
            isem.CodNivelesCargos,
            isem.numerador_nombre,
            isem.denominador_nombre,
            isem.formula,
            isem.divide,
            isem.tipometa,
            isem.tipo,
            isem.decimales,
            isem.EnUso,
            isem.automatico,
            isem.acumulativo,
            nc.Area,
            nc.Nombre as nombre_cargo
          FROM IndicadoresSemanales isem
          LEFT JOIN NivelesCargos nc ON isem.CodNivelesCargos = nc.CodNivelesCargos
          WHERE isem.activo = 1
          ORDER BY isem.CodNivelesCargos, isem.id";
$stmt = $conn->prepare($query);
$stmt->execute();
$indicadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar indicadores por área/cargo
$indicadoresPorArea = [];
foreach ($indicadores as $indicador) {
    $area = $indicador['CodNivelesCargos'] . '|' . $indicador['nombre_cargo'];
    if (!isset($indicadoresPorArea[$area])) {
        $indicadoresPorArea[$area] = [
            'nombre_cargo' => $indicador['nombre_cargo'],
            'CodNivelesCargos' => $indicador['CodNivelesCargos'],
            'Area' => $indicador['Area'],
            'indicadores' => []
        ];
    }
    $indicadoresPorArea[$area]['indicadores'][] = $indicador;
}

// Obtener TODOS los resultados y metas para las semanas (incluyendo la anterior)
$resultadosPorIndicador = [];
if (!empty($semanas)) {
    $semanasIds = array_column($semanas, 'id');
    $placeholders = str_repeat('?,', count($semanasIds) - 1) . '?';

    $query = "SELECT 
                isr.id_indicador,
                isr.semana,
                isr.numerador_dato,
                isr.denominador_dato,
                isr.meta
              FROM IndicadoresSemanalesResultados isr
              WHERE isr.semana IN ($placeholders)";
    $stmt = $conn->prepare($query);
    $stmt->execute($semanasIds);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resultados as $resultado) {
        $resultadosPorIndicador[$resultado['id_indicador']][$resultado['semana']] = $resultado;
    }
}

// Función para obtener resultado desde BD CON MULTIPLICADOR Y SOPORTE PARA ACUMULATIVOS
function obtenerResultadoBD($indicador, $resultadoBD, $resultadoBDAnterior = null)
{
    if (!$resultadoBD)
        return null;

    // Indicador ID 1 (Rotación de Personal) tiene multiplicador 4.2 en numerador
    $multiplicador = ($indicador['id'] == 1) ? 4.2 : 1;

    // Si divide=1, calcular división
    if ($indicador['divide'] == 1) {
        if (
            $resultadoBD['numerador_dato'] !== null &&
            $resultadoBD['denominador_dato'] !== null &&
            $resultadoBD['denominador_dato'] != 0
        ) {
            // Aplicar multiplicador al numerador antes de dividir
            return ($resultadoBD['numerador_dato'] * $multiplicador) / $resultadoBD['denominador_dato'];
        }
        return null;
    } else {
        // Si divide=0, mostrar numerador_dato
        if ($resultadoBD['numerador_dato'] !== null) {
            $valorActual = $resultadoBD['numerador_dato'];
            
            // Si es acumulativo, restar el valor de la semana anterior
            if (isset($indicador['acumulativo']) && $indicador['acumulativo'] == 1 && $resultadoBDAnterior && $resultadoBDAnterior['numerador_dato'] !== null) {
                return $valorActual - $resultadoBDAnterior['numerador_dato'];
            }
            
            return $valorActual;
        }
        return null;
    }
}

// Función para determinar el color según la meta y el tipo
function getColorMeta($resultado, $meta, $tipometa, $resultadosemanaanteriordato, $indicadorid)
{
    if ($resultado === null || $meta === null)
        return '';

    // Normalizar tipometa a minúsculas para evitar problemas
    $tipometa = strtolower(trim($tipometa));

    if ($tipometa === 'arriba') {
        // Meta es estar ARRIBA del valor - si resultado >= meta es VERDE
        // Primero se define si es id de indicador específico entonces se aplica una resta con respecto al resultado de la semana antepasada respecto a la anterior para comprar ese resultado
        if ($indicadorid == 20 || $indicadorid == 21) {
            if ($resultado - $resultadosemanaanteriordato >= $meta) {
                return 'meta-cumplida';
            } else {
                return 'meta-no-cumplida';
            }
        } else if ($resultado >= $meta) {
            return 'meta-cumplida';
        } else {
            return 'meta-no-cumplida';
        }
    } else if ($tipometa === 'abajo') {
        // Meta es estar ABAJO del valor - si resultado <= meta es VERDE
        if ($resultado <= $meta) {
            return 'meta-cumplida';
        } else {
            return 'meta-no-cumplida';
        }
    }

    // Si no hay tipometa definido o es inválido, no colorear
    return '';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados vs Metas - Indicadores</title>
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/indicadores_resultado.css?v=<?php echo mt_rand(1, 10000); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, $esAdmin, 'Resultados de KPI'); ?>

            <div class="info-periodo" style="display:none;">
                <i class="fas fa-calendar-alt"></i>
                Semana actual: <?php echo $numeroSemanaActual; ?> |
                Semana anterior: <?php echo $numeroSemanaAnterior; ?>
            </div>

            <?php if (empty($indicadoresPorArea)): ?>
                <div class="mensaje-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    No hay indicadores disponibles para mostrar.
                </div>
            <?php endif; ?>

            <div class="tabla-container">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 200px;">Indicador</th>

                            <th rowspan="2" style="width: 100px;">Meta (Sem <?php echo $numeroSemanaAnterior; ?>)</th>
                            <?php foreach ($semanas as $semana): ?>
                                <?php
                                $fechaFin = formatoFechaCorta($semana['fecha_fin']);
                                ?>
                                <th colspan="1">
                                    <?php echo $semana['numero_semana']; ?>
                                    <br>
                                    <span style="font-size: 10px; font-weight: normal;"><?php echo $fechaFin; ?></span>
                                </th>
                            <?php endforeach; ?>
                            <th rowspan="2" style="width: 150px;">Fórmula</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($indicadoresPorArea as $area => $datosArea): ?>
                            <tr class="area-header">
                                <td colspan="<?php echo 3 + count($semanas); ?>"
                                    style="text-align: left; padding-left: 15px;">
                                    <?php echo htmlspecialchars($datosArea['Area']); ?>
                                </td>
                            </tr>

                            <?php foreach ($datosArea['indicadores'] as $indicador): ?>
                                <tr>
                                    <td class="indicador-nombre">
                                        <?php echo htmlspecialchars($indicador['nombre']); ?>
                                        <?php if ($indicador['automatico'] == 1): ?>
                                            <i class="fas fa-calculator icono-automatico"
                                                title="Indicador calculado automáticamente"></i>
                                        <?php endif; ?>
                                    </td>



                                    <!-- Columna de Meta general (solo semana anterior) -->
                                    <?php
                                    $semanaAnterior = obtenerSemanaPorNumero($numeroSemanaAnterior);
                                    $metaGeneral = null;
                                    if ($semanaAnterior) {
                                        $resultadoAnterior = $resultadosPorIndicador[$indicador['id']][$semanaAnterior['id']] ?? null;
                                        $metaGeneral = $resultadoAnterior ? $resultadoAnterior['meta'] : null;
                                    }
                                    ?>
                                    <td class="celda-meta-editable"
                                        onclick="editarMetaGeneral(<?php echo $indicador['id']; ?>, this)"
                                        data-id="<?php echo $indicador['id']; ?>"
                                        data-semana="<?php echo $semanaAnterior ? $semanaAnterior['id'] : ''; ?>"
                                        title="Haz clic para editar la meta">
                                        <?php echo formatearValor($metaGeneral, $indicador['tipo'], $indicador['decimales'], $indicador['EnUso']); ?>
                                    </td>

                                    <?php foreach ($semanas as $index => $semana): ?>
                                        <?php
                                        $resultado = $resultadosPorIndicador[$indicador['id']][$semana['id']] ?? null;
                                        
                                        // Obtener resultado de la semana anterior para indicadores acumulativos
                                        $resultadoAnterior = null;
                                        if ($index < count($semanas) - 1) {
                                            $semanaAnteriorId = $semanas[$index + 1]['id'];
                                            $resultadoAnterior = $resultadosPorIndicador[$indicador['id']][$semanaAnteriorId] ?? null;
                                        }
                                        
                                        $valorResultado = obtenerResultadoBD($indicador, $resultado, $resultadoAnterior);
                                        $valorResultadosemanaanterior = obtenerResultadoBD($indicador, $resultadosPorIndicador[$indicador['id']][$semana['id'] - 1] ?? null);
                                        $meta = $resultado ? $resultado['meta'] : null;
                                        $colorMeta = getColorMeta($valorResultado, $meta, $indicador['tipometa'], $valorResultadosemanaanterior, $indicador['id']);
                                        ?>

                                        <td class="resultado columna-resultado <?php echo $colorMeta; ?>">
                                            <?php echo formatearValor($valorResultado, $indicador['tipo'], $indicador['decimales'], $indicador['EnUso']); ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="indicador-formula" style="text-align: left; font-style: italic; color: #666;">
                                        <?php echo htmlspecialchars($indicador['formula'] ?? '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para edición de meta GENERAL -->
    <div id="modalMeta">
        <div class="modal-contenido">
            <h3 id="modalMetaTitulo">Editar Meta para Semana <?php echo $numeroSemanaAnterior; ?></h3>
            <form id="formMeta" method="post">
                <input type="hidden" name="id_indicador" id="modalMetaIdIndicador">
                <input type="hidden" name="semana" id="modalMetaSemana">

                <div class="form-group">
                    <label for="modalMetaValor">Valor de la Meta:</label>
                    <input type="number" step="0.01" id="modalMetaValor" name="meta"
                        placeholder="Ingrese el valor de la meta">
                </div>

                <div class="modal-botones">
                    <button type="button" onclick="cerrarModalMeta()" class="btn btn-cancelar">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-guardar">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- <script src="js/indicadores_resultado.js?v=<?php echo $version; ?>"></script> -->
    <script src="js/indicadores_resultado.js?v=<?php echo time(); ?>"></script>
</body>

</html>