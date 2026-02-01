<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/layout/menu_lateral.php';

// Verificar acceso al módulo (solo cargo nivel 13 - RH o Admin)
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

if (!verificarAccesoCargo([13, 16, 39, 30, 37, 28]) && !$esAdmin) {
    header('Location: ../index.php');
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
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
        }

        body {
            background-color: #F6F6F6;
            color: #333;
        }

        .container-fluid {
            padding: 20px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .anio-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .anio-selector select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            color: #0E544C;
            font-weight: bold;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .month-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 15px;
            display: flex;
            flex-direction: column;
        }

        .month-header {
            text-align: center;
            color: #0E544C;
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #51B8AC;
        }

        .days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            font-size: 0.8rem;
        }

        .day-name {
            text-align: center;
            font-weight: bold;
            color: #666;
            padding: 5px 0;
        }

        .day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            position: relative;
            cursor: default;
        }

        .day.has-feriado {
            font-weight: bold;
            cursor: pointer;
        }

        .day.nacional {
            background-color: rgba(0, 123, 255, 0.2);
            color: #0056b3;
            border: 1px solid #007bff;
        }

        .day.departamental {
            background-color: rgba(111, 66, 193, 0.2);
            color: #4e2e8a;
            border: 1px solid #6f42c1;
        }

        .day.hoy {
            background-color: #fff3cd;
            border: 1px solid #ffca2c;
        }

        .day:hover.has-feriado {
            transform: scale(1.1);
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .feriado-info {
            display: none;
            position: absolute;
            bottom: 110%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .feriado-info::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        .day:hover .feriado-info {
            display: block;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        .box-nacional {
            background-color: rgba(0, 123, 255, 0.2);
            border: 1px solid #007bff;
        }

        .box-departamental {
            background-color: rgba(111, 66, 193, 0.2);
            border: 1px solid #6f42c1;
        }

        .btn {
            padding: 8px 15px;
            background-color: #51B8AC;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #0E544C;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
    </style>
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
                <div>
                    <a href="editar_feriados.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Ver Lista / Editar
                    </a>
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