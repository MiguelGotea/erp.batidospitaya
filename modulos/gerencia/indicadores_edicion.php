<?php
ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/auth.php';
require_once '../../includes/header_universal.php';
require_once '../../includes/menu_lateral.php';
require_once '../../core/permissions/permissions.php';
require_once 'calcular_indicadores.php';
require_once 'includes/funciones_indicadores.php';

// Obtener información del usuario
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Verificar acceso al módulo
//if (!verificarAccesoCargo([11, 16, 13, 42, 12]) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
//    header('Location: ../../../index.php');
//    exit();
//}
if (!tienePermiso('kpi_gestion_soporte', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtener la semana actual del sistema
$semanaActual = obtenerSemanaActual();
$numeroSemanaActual = $semanaActual ? $semanaActual['numero_semana'] : 0;

// Obtener semanas anteriores
$semanas = obtenerUltimasSemanas(8);
$semanasFiltradas = [];
foreach ($semanas as $semana) {
    if ($semana['numero_semana'] < $numeroSemanaActual) {
        $semanasFiltradas[] = $semana;
    }
}

usort($semanasFiltradas, function($a, $b) {
    return $b['numero_semana'] - $a['numero_semana'];
});

$semanasFiltradas = array_slice($semanasFiltradas, 0, 8);
$semanaAnterior = !empty($semanasFiltradas) ? $semanasFiltradas[0] : null;

// Obtener todos los indicadores activos EXCLUYENDO los automáticos - INCLUYENDO TIPO Y DECIMALES
$query = "SELECT 
            isem.id,
            isem.nombre,
            isem.CodNivelesCargos,
            isem.numerador_nombre,
            isem.denominador_nombre,
            isem.formula,
            isem.divide,
            isem.tipo,
            isem.decimales,
            isem.EnUso,
            nc.Nombre as nombre_cargo,
            nc.Area
          FROM IndicadoresSemanales isem
          LEFT JOIN NivelesCargos nc ON isem.CodNivelesCargos = nc.CodNivelesCargos
          WHERE isem.activo = 1
          AND (isem.automatico = 0 OR isem.automatico IS NULL)
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

// Obtener resultados para las semanas
$resultadosPorIndicador = [];
if (!empty($semanasFiltradas)) {
    $semanasIds = array_column($semanasFiltradas, 'id');
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

// Función para calcular resultado CON MULTIPLICADOR
function calcularResultado($indicador, $resultadoBD, $conn, $semanaId = null) {
    if (!$resultadoBD) return null;
    
    // Indicador ID 1 (Rotación de Personal) tiene multiplicador 4.2 en numerador
    $multiplicador = ($indicador['id'] == 1) ? 4.2 : 1;
    
    if ($indicador['divide'] == 1) {
        if ($resultadoBD['numerador_dato'] !== null && $resultadoBD['denominador_dato'] !== null && $resultadoBD['denominador_dato'] != 0) {
            // Aplicar multiplicador al numerador antes de dividir
            return ($resultadoBD['numerador_dato'] * $multiplicador) / $resultadoBD['denominador_dato'];
        }
        return null;
    } else {
        if ($resultadoBD['numerador_dato'] !== null) {
            return $resultadoBD['numerador_dato'];
        } elseif ($resultadoBD['denominador_dato'] !== null) {
            return $resultadoBD['denominador_dato'];
        }
        return null;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edición de Indicadores Semanales</title>
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/global_tools.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/indicadores_edicion.css?v=<?= mt_rand(1, 10000) ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, $esAdmin, 'Edicion de KPI Semanal'); ?>
            
            <?php if (empty($indicadoresPorArea)): ?>
                <div class="info-box" style="background-color: #fff3cd; border-left-color: #ffc107;">
                    <p><strong>Sin indicadores para mostrar</strong></p>
                    <p>No hay indicadores editables disponibles en este momento.</p>
                </div>
            <?php endif; ?>
            
            <div class="tabla-container">
                <table>
                    <thead>
                        <tr>
                            <th rowspan="3" style="width: 200px; min-width: 200px; text-align: center; padding-left: 15px;">Área / Indicador</th>
                            <?php foreach ($semanasFiltradas as $index => $semana): ?>
                                <?php 
                                $esSemanaAnterior = $semanaAnterior && $semana['id'] == $semanaAnterior['id'];
                                $claseSemana = $esSemanaAnterior ? 'semana-header-anterior' : '';
                                $fechaFin = formatoFechaCorta($semana['fecha_fin']);
                                ?>
                                <th colspan="1" class="<?php echo $claseSemana; ?>">
                                    <?php echo $semana['numero_semana']; ?>
                                    <br>
                                    <span style="font-size: 10px; font-weight: normal;"><?php echo $fechaFin; ?></span>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($indicadoresPorArea as $area => $datosArea): ?>
                            <tr class="area-header">
                                <td colspan="<?php echo 1 + count($semanasFiltradas); ?>" style="text-align: left; padding-left: 15px;">
                                    <?php echo htmlspecialchars($datosArea['Area']); ?>
                                </td>
                            </tr>
                            
                            <?php foreach ($datosArea['indicadores'] as $indicador): ?>
                                <?php if ($indicador['divide'] != 0): ?>
                                    <tr>
                                        <td class="indicador-nombre" style="font-weight: bold; background-color: #e8f5e8;">
                                            <?php echo htmlspecialchars($indicador['nombre']); ?>
                                        </td>
                                        <?php foreach ($semanasFiltradas as $semana): ?>
                                            <td style="background-color: #e8f5e8;"></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php if ($indicador['divide'] == 0): ?>
                                    <?php 
                                    $nombreCampo = !empty($indicador['numerador_nombre']) ? $indicador['numerador_nombre'] : 'Valor';
                                    ?>
                                    <tr class="fila-unica">
                                        <td style="padding-left: 30px !important; text-align: left;">
                                            <?php echo htmlspecialchars($nombreCampo); ?>
                                        </td>
                                        <?php foreach ($semanasFiltradas as $semana): ?>
                                            <?php 
                                            $resultado = $resultadosPorIndicador[$indicador['id']][$semana['id']] ?? null;
                                            $esSemanaAnterior = $semanaAnterior && $semana['id'] == $semanaAnterior['id'];
                                            $claseCelda = $esSemanaAnterior ? 'celda-semana-anterior' : '';
                                            $esEditable = $esSemanaAnterior;
                                            $claseEditable = $esEditable ? 'celda-editable' : 'celda-no-editable';
                                            
                                            $valor = null;
                                            if ($resultado) {
                                                $valor = $resultado['numerador_dato'] !== null ? $resultado['numerador_dato'] : 
                                                        ($resultado['denominador_dato'] !== null ? $resultado['denominador_dato'] : null);
                                            }
                                            ?>
                                            
                                            <td class="<?php echo $claseCelda . ' ' . $claseEditable; ?>"
                                                <?php if ($esEditable): ?>
                                                    data-editable="true"
                                                    data-id="<?php echo $indicador['id']; ?>"
                                                    data-semana="<?php echo $semana['id']; ?>"
                                                    data-tipo="unico"
                                                    data-divide="0"
                                                    data-tipo-indicador="<?php echo $indicador['tipo']; ?>"
                                                    data-decimales="<?php echo $indicador['decimales']; ?>"
                                                <?php endif; ?>
                                                title="<?php echo $esEditable ? 'Haz clic para editar' : 'Solo semana anterior editable'; ?>">
                                                <?php
$valor = null;
if ($resultado) {
    $valor = $resultado['numerador_dato'] !== null ? $resultado['numerador_dato'] : 
            ($resultado['denominador_dato'] !== null ? $resultado['denominador_dato'] : null);
}
?>
<span class="valor-display"><?php echo formatearValor($valor, $indicador['tipo'], $indicador['decimales'], $indicador['EnUso']); ?></span>
                                                <?php if ($esEditable): ?>
                                                    <input type="number" step="0.01" class="input-inline" style="display: none;" value="<?php echo $valor !== null ? $valor : ''; ?>">
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                
                                <?php else: ?>
                                    <!-- Fila del Numerador -->
                                    <tr class="fila-numerador">
                                        <td style="padding-left: 40px !important; text-align: left;">
                                            <i class="fas fa-arrow-up" style="color: #2196F3;"></i> 
                                            <?php echo htmlspecialchars($indicador['numerador_nombre'] ?: 'Numerador'); ?>
                                        </td>
                                        <?php foreach ($semanasFiltradas as $semana): ?>
                                            <?php 
                                            $resultado = $resultadosPorIndicador[$indicador['id']][$semana['id']] ?? null;
                                            $esSemanaAnterior = $semanaAnterior && $semana['id'] == $semanaAnterior['id'];
                                            $claseCelda = $esSemanaAnterior ? 'celda-semana-anterior' : '';
                                            $esEditable = $esSemanaAnterior && $indicador['divide'] == 1;
                                            $claseEditable = $esEditable ? 'celda-editable' : 'celda-no-editable';
                                            $valor = $resultado && $resultado['numerador_dato'] !== null ? $resultado['numerador_dato'] : null;
                                            ?>
                                            
                                            <td class="<?php echo $claseCelda . ' ' . $claseEditable; ?>"
                                                <?php if ($esEditable): ?>
                                                    data-editable="true"
                                                    data-id="<?php echo $indicador['id']; ?>"
                                                    data-semana="<?php echo $semana['id']; ?>"
                                                    data-tipo="numerador"
                                                    data-divide="1"
                                                    data-tipo-indicador="<?php echo $indicador['tipo']; ?>"
                                                    data-decimales="<?php echo $indicador['decimales']; ?>"
                                                <?php endif; ?>
                                                title="<?php echo $esEditable ? 'Haz clic para editar' : 'Solo semana anterior editable'; ?>">
                                                <?php
$valor = $resultado && $resultado['numerador_dato'] !== null ? $resultado['numerador_dato'] : null;
?>
<span class="valor-display"><?php echo formatearValor($valor, 'entero', $indicador['decimales'], $indicador['EnUso']); ?></span>
                                                <?php if ($esEditable): ?>
                                                    <input type="number" step="0.01" class="input-inline" style="display: none;" value="<?php echo $valor !== null ? $valor : ''; ?>">
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    
                                    <!-- Fila del Denominador -->
                                    <tr class="fila-denominador">
                                        <td style="padding-left: 40px !important; text-align: left;">
                                            <i class="fas fa-arrow-down" style="color: #f44336;"></i> 
                                            <?php echo htmlspecialchars($indicador['denominador_nombre'] ?: 'Denominador'); ?>
                                        </td>
                                        <?php foreach ($semanasFiltradas as $semana): ?>
                                            <?php 
                                            $resultado = $resultadosPorIndicador[$indicador['id']][$semana['id']] ?? null;
                                            $esSemanaAnterior = $semanaAnterior && $semana['id'] == $semanaAnterior['id'];
                                            $claseCelda = $esSemanaAnterior ? 'celda-semana-anterior' : '';
                                            $esEditable = $esSemanaAnterior;
                                            $claseEditable = $esEditable ? 'celda-editable' : 'celda-no-editable';
                                            $valor = $resultado && $resultado['denominador_dato'] !== null ? $resultado['denominador_dato'] : null;
                                            ?>
                                            
                                            <td class="<?php echo $claseCelda . ' ' . $claseEditable; ?>"
                                                <?php if ($esEditable): ?>
                                                    data-editable="true"
                                                    data-id="<?php echo $indicador['id']; ?>"
                                                    data-semana="<?php echo $semana['id']; ?>"
                                                    data-tipo="denominador"
                                                    data-divide="1"
                                                    data-tipo-indicador="<?php echo $indicador['tipo']; ?>"
                                                    data-decimales="<?php echo $indicador['decimales']; ?>"
                                                <?php endif; ?>
                                                title="<?php echo $esEditable ? 'Haz clic para editar' : 'Solo semana anterior editable'; ?>">
                                                <?php
$valor = $resultado && $resultado['denominador_dato'] !== null ? $resultado['denominador_dato'] : null;
?>
<span class="valor-display"><?php echo formatearValor($valor, 'entero', $indicador['decimales'], $indicador['EnUso']); ?></span>
                                                <?php if ($esEditable): ?>
                                                    <input type="number" step="0.01" class="input-inline" style="display: none;" value="<?php echo $valor !== null ? $valor : ''; ?>">
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    
                                    <!-- Fila del Resultado -->
                                    <tr class="fila-resultado">
                                        <td style="padding-left: 40px !important; font-weight: bold; text-align: left;">
                                            Resultado
                                        </td>
                                        <?php foreach ($semanasFiltradas as $semana): ?>
                                            <?php 
                                            $resultado = $resultadosPorIndicador[$indicador['id']][$semana['id']] ?? null;
                                            $valorResultado = calcularResultado($indicador, $resultado, $conn, $semana['id']);
                                            ?>
                                            <td style="font-weight: bold;"
                                                data-id="<?php echo $indicador['id']; ?>"
                                                data-semana="<?php echo $semana['id']; ?>"
                                                data-tipo="resultado">
                                                <?php
$valorResultado = calcularResultado($indicador, $resultado, $conn, $semana['id']);
?>
<?php echo formatearResultado($valorResultado, $indicador); ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                                
                                <tr style="display:none;">
                                    <td colspan="<?php echo 1 + count($semanasFiltradas); ?>" style="height: 10px; background-color: #f8f9fa;"></td>
                                </tr>
                                
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="js/indicadores_edicion.js"></script>
</body>
</html>