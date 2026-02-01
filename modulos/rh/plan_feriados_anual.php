<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/permissions/permissions.php';

// Obtener usuario y cargo antes de verificar permisos
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo mediante el sistema de permisos
if (!tienePermiso('plan_feriados_anual', 'vista', $cargoOperario)) {
    header('Location: /index.php');
    exit();
}

$anio = isset($_GET['anio']) ? intval($_GET['anio']) : date('Y');

// Obtener feriados del año seleccionado
$stmt = $conn->prepare("
    SELECT f.*, d.nombre as nombre_departamento 
    FROM feriadosnic f 
    LEFT JOIN departamentos d ON CAST(f.departamento_codigo AS CHAR) = d.codigo 
    WHERE YEAR(f.fecha) = ?
    ORDER BY f.fecha ASC
");
$stmt->execute([$anio]);
$feriados = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

// Reorganizar feriados por fecha para fácil acceso: [YYYY-MM-DD] => [feriados...]
$feriadosPorFecha = [];
foreach ($feriados as $mesNumero => $listaFeriados) {
    foreach ($listaFeriados as $f) {
        $feriadosPorFecha[$f['fecha']][] = $f;
    }
}

$meses = [
    1 => 'Enero',
    2 => 'Febrero',
    3 => 'Marzo',
    4 => 'Abril',
    5 => 'Mayo',
    6 => 'Junio',
    7 => 'Julio',
    8 => 'Agosto',
    9 => 'Septiembre',
    10 => 'Octubre',
    11 => 'Noviembre',
    12 => 'Diciembre'
];

// Obtener años disponibles
$stmt = $conn->query("SELECT DISTINCT YEAR(fecha) as anio FROM feriadosnic ORDER BY anio DESC");
$aniosDisponibles = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array(date('Y'), $aniosDisponibles)) {
    $aniosDisponibles[] = date('Y');
}
sort($aniosDisponibles);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Anual de Feriados
        <?= $anio ?>
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link rel="stylesheet" href="../../core/assets/css/global_tools.css">
    <link rel="stylesheet" href="css/plan_feriados_anual.css">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <?php echo renderHeader($usuario, $esAdmin, "Plan Anual de Feriados $anio"); ?>

        <div class="container-fluid">
            <div class="controls">
                <div class="anio-selector">
                    <label for="anioSelect">Seleccionar Año:</label>
                    <select id="anioSelect" onchange="cambiarAnio(this.value)">
                        <?php foreach (array_reverse($aniosDisponibles) as $a): ?>
                            <option value="<?= $a ?>" <?= $a == $anio ? 'selected' : '' ?>>
                                <?= $a ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="calendar-grid">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <div class="month-card">
                        <div class="month-header">
                            <?= $meses[$m] ?>
                        </div>
                        <div class="days-grid">
                            <div class="day-name">L</div>
                            <div class="day-name">M</div>
                            <div class="day-name">M</div>
                            <div class="day-name">J</div>
                            <div class="day-name">V</div>
                            <div class="day-name">S</div>
                            <div class="day-name">D</div>

                            <?php
                            $primerDiaMes = date('N', strtotime("$anio-$m-01"));
                            $diasEnMes = date('t', strtotime("$anio-$m-01"));

                            // Espacios en blanco para el inicio del mes
                            for ($i = 1; $i < $primerDiaMes; $i++) {
                                echo '<div></div>';
                            }

                            for ($d = 1; $d <= $diasEnMes; $d++) {
                                $fecha = sprintf('%04d-%02d-%02d', $anio, $m, $d);
                                $clases = ['day'];
                                $infoFeriado = '';

                                if ($fecha === date('Y-m-d'))
                                    $clases[] = 'hoy';

                                if (isset($feriadosPorFecha[$fecha])) {
                                    $clases[] = 'has-feriado';
                                    foreach ($feriadosPorFecha[$fecha] as $f) {
                                        $clases[] = strtolower($f['tipo']);
                                        $infoFeriado .= '<div>' . htmlspecialchars($f['nombre']) . ($f['tipo'] === 'Departamental' ? ' (' . htmlspecialchars($f['nombre_departamento']) . ')' : '') . '</div>';
                                    }
                                }

                                echo '<div class="' . implode(' ', $clases) . '">';
                                echo $d;
                                if ($infoFeriado) {
                                    echo '<div class="feriado-info">' . $infoFeriado . '</div>';
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="legend">
                <div class="legend-item">
                    <div class="box box-nacional"></div>
                    <span>Feriado Nacional</span>
                </div>
                <div class="legend-item">
                    <div class="box box-departamental"></div>
                    <span>Feriado Departamental</span>
                </div>
                <div class="legend-item">
                    <div class="box" style="background: #fff3cd; border: 1px solid #ffca2c;"></div>
                    <span>Hoy</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function cambiarAnio(anio) {
            window.location.href = 'plan_feriados_anual.php?anio=' + anio;
        }
    </script>
</body>

</html>