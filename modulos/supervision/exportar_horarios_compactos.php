<?php
// require_once '../../includes/auth.php';
// require_once '../../includes/funciones.php';
require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones


// Verificar acceso al módulo y cargos específicos (8, 16, 41) o admin
if (!verificarAccesoCargo([8, 16, 41, 49])) {
    header('Location: ../index.php');
    exit();
}

// Obtener parámetros
$semanaSeleccionada = $_GET['semana'] ?? null;
$sucursalSeleccionada = $_GET['sucursal'] ?? null;

if (!$semanaSeleccionada) {
    die("Semana no especificada.");
}

$semana = obtenerSemanaPorNumero($semanaSeleccionada);
if (!$semana) {
    die("Semana no válida.");
}

$mostrarTodas = ($sucursalSeleccionada === 'todas');

// --- Funciones Locales Refactorizadas ---

/**
 * Obtiene los horarios de la tabla oficial para una semana y sucursal.
 * Evita duplicados al filtrar solo por la sucursal de origen del registro.
 */
function obtenerHorariosOficiales($idSemana, $codSucursal = null)
{
    global $conn;

    $sql = "
        SELECT h.*, o.Nombre, o.Apellido, o.Apellido2, s.nombre as sucursal_nombre
        FROM HorariosSemanalesOperaciones h
        JOIN Operarios o ON h.cod_operario = o.CodOperario
        JOIN sucursales s ON h.cod_sucursal = s.codigo
        WHERE h.id_semana_sistema = ?
    ";

    $params = [$idSemana];

    if ($codSucursal && $codSucursal !== 'todas') {
        $sql .= " AND h.cod_sucursal = ?";
        $params[] = $codSucursal;
    }

    $sql .= " ORDER BY s.nombre, o.Nombre, o.Apellido";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerCategoriasOperariosLocal($codigosOperarios)
{
    global $conn;
    if (empty($codigosOperarios))
        return [];
    $placeholders = implode(',', array_fill(0, count($codigosOperarios), '?'));
    $stmt = $conn->prepare("
        SELECT anc.CodOperario, nc.Nombre as NombreCategoria, nc.Peso, nc.CodNivelesCargos as idCategoria, nc.color 
        FROM AsignacionNivelesCargos anc
        JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
        WHERE anc.CodOperario IN ($placeholders)
        AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        AND anc.Fecha <= CURDATE()
        ORDER BY anc.Fecha DESC
    ");
    $stmt->execute($codigosOperarios);
    $categorias = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    $resultados = [];
    foreach ($categorias as $codOperario => $categoriasOp) {
        $resultados[$codOperario] = $categoriasOp[0];
    }
    return $resultados;
}

// Recolectar datos
$todosHorarios = obtenerHorariosOficiales($semana['id'], $sucursalSeleccionada);

if (empty($todosHorarios)) {
    die("No hay registros de horarios oficiales para esta selección.");
}

$codigosOperarios = array_column($todosHorarios, 'cod_operario');
$categorias = obtenerCategoriasOperariosLocal(array_unique($codigosOperarios));

// Agrupar por sucursal para el reporte
$horariosPorSucursal = [];
foreach ($todosHorarios as $h) {
    $sucursalCod = $h['cod_sucursal'];
    if (!isset($horariosPorSucursal[$sucursalCod])) {
        $horariosPorSucursal[$sucursalCod] = [
            'nombre' => $h['sucursal_nombre'],
            'horarios' => []
        ];
    }

    // Adjuntar categoría
    $h['categoria'] = $categorias[$h['cod_operario']] ?? ['NombreCategoria' => 'Sin categoría', 'Peso' => '-', 'idCategoria' => 0];
    $horariosPorSucursal[$sucursalCod]['horarios'][] = $h;
}

// Configurar Excel
$nombreArchivo = "horarios_oficiales_semana_{$semanaSeleccionada}.xls";
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Salida HTML para Excel
echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
echo '<!DOCTYPE html><html><head><meta charset="utf-8">
<style>
    th { background-color: #0E544C; color: white; border: 1px solid #ccc; padding: 5px; }
    td { border: 1px solid #ccc; padding: 5px; vertical-align: top; }
    h3 { color: #0E544C; margin-bottom: 5px; }
    .comentario { color: #555; font-size: 0.9em; font-style: italic; }
</style>
</head><body>';

foreach ($horariosPorSucursal as $codSucursal => $data) {
    echo "<h3>" . htmlspecialchars($data['nombre']) . "</h3>";

    echo '<table border="1">';
    echo '<thead><tr>';
    echo '<th>Colaborador</th>';
    echo '<th>Categoría</th>';

    $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    $fechaActual = new DateTime($semana['fecha_inicio']);
    foreach ($diasSemana as $dia) {
        echo '<th>' . $dia . ' (' . $fechaActual->format('d/m/y') . ')</th>';
        $fechaActual->modify('+1 day');
    }
    echo '<th>Total Horas</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($data['horarios'] as $horario) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($horario['Nombre'] . ' ' . $horario['Apellido'] . ' ' . $horario['Apellido2']) . '</td>';
        echo '<td>' . htmlspecialchars($horario['categoria']['NombreCategoria']) . '</td>';

        $totalHoras = 0;
        $diasStr = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];

        foreach ($diasStr as $dia) {
            $estado = $horario["{$dia}_estado"] ?? '';
            $entrada = $horario["{$dia}_entrada"] ?? null;
            $salida = $horario["{$dia}_salida"] ?? null;
            $horas = $horario["{$dia}_horas"] ?? 0;
            $comentario = $horario["{$dia}_comentario"] ?? '';
            $esOtraTienda = ($estado === 'Otra.Tienda');
            $sucursalExt = $horario["{$dia}_sucursal_externa"] ?? null;

            echo '<td>';
            if (!empty($estado)) {
                $totalHoras += $horas;

                if ($estado === 'Activo' && $entrada && $salida) {
                    echo formatoHoraAmPm($entrada) . ' - ' . formatoHoraAmPm($salida);
                } else {
                    echo htmlspecialchars($estado);
                    if ($esOtraTienda && $sucursalExt) {
                        $nomExt = obtenerNombreSucursal($sucursalExt);
                        echo ": " . htmlspecialchars($nomExt);
                    }
                }

                if ($horas > 0) {
                    echo "<br><b>" . number_format($horas, 1) . " hrs</b>";
                }

                if (!empty($comentario)) {
                    echo "<div class='comentario'>Obs: " . htmlspecialchars($comentario) . "</div>";
                }
            } else {
                echo "-";
            }
            echo '</td>';
        }

        echo '<td><b>' . number_format($totalHoras, 1) . '</b></td>';
        echo '</tr>';
    }

    echo '</tbody></table><br>';
}

echo '</body></html>';
exit;
