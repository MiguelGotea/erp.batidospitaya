<?php
require_once '../../core/auth/auth.php'; // Se centralizó el acceso a auth, db y funciones
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso con sistema de permisos tools ERP
if (!tienePermiso('horarios_programados', 'exportar', $cargoOperario)) {
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

// --- Helper Functions ---

/**
 * Obtiene TODOS los operarios que trabajan en una sucursal (principal + externos)
 * para una semana específica
 */
function obtenerTodosOperariosEnSucursal($codSucursal, $idSemana, $buscarEnLideres = false)
{
    global $conn;

    if ($buscarEnLideres) {
        $tabla = 'HorariosSemanales';
    } else {
        $tabla = 'HorariosSemanalesOperaciones';
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT o.CodOperario, o.Nombre, o.Apellido, o.Apellido2
        FROM Operarios o
        JOIN $tabla h ON o.CodOperario = h.cod_operario
        WHERE h.id_semana_sistema = ?
        AND (
            -- Sucursal principal del horario
            h.cod_sucursal = ?
            OR 
            -- O es Otra.Tienda para esta sucursal en algún día
            (h.lunes_estado = 'Otra.Tienda' AND h.lunes_sucursal_externa = ?)
            OR (h.martes_estado = 'Otra.Tienda' AND h.martes_sucursal_externa = ?)
            OR (h.miercoles_estado = 'Otra.Tienda' AND h.miercoles_sucursal_externa = ?)
            OR (h.jueves_estado = 'Otra.Tienda' AND h.jueves_sucursal_externa = ?)
            OR (h.viernes_estado = 'Otra.Tienda' AND h.viernes_sucursal_externa = ?)
            OR (h.sabado_estado = 'Otra.Tienda' AND h.sabado_sucursal_externa = ?)
            OR (h.domingo_estado = 'Otra.Tienda' AND h.domingo_sucursal_externa = ?)
        )
        ORDER BY o.Nombre, o.Apellido, o.Apellido2
    ");

    $stmt->execute([
        $idSemana,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal
    ]);

    $result = $stmt->fetchAll();

    // Deduplicar por CodOperario
    $vistos = [];
    $deduplicados = [];
    foreach ($result as $row) {
        $cod = $row['CodOperario'];
        if (!isset($vistos[$cod])) {
            $vistos[$cod] = true;
            $deduplicados[] = $row;
        }
    }
    return $deduplicados;
}

/**
 * Obtiene el horario FILTRADO para una sucursal específica
 */
function obtenerHorarioFiltradoPorSucursal($codOperario, $idSemana, $codSucursal, $buscarEnLideres = false)
{
    global $conn;

    if ($buscarEnLideres) {
        $tabla = 'HorariosSemanales';
    } else {
        $tabla = 'HorariosSemanalesOperaciones';
    }

    $stmt = $conn->prepare("
        SELECT * FROM $tabla 
        WHERE cod_operario = ? 
        AND id_semana_sistema = ?
        AND (
            -- Sucursal principal
            cod_sucursal = ?
            OR 
            -- O es Otra.Tienda para esta sucursal
            (lunes_estado = 'Otra.Tienda' AND lunes_sucursal_externa = ?)
            OR (martes_estado = 'Otra.Tienda' AND martes_sucursal_externa = ?)
            OR (miercoles_estado = 'Otra.Tienda' AND miercoles_sucursal_externa = ?)
            OR (jueves_estado = 'Otra.Tienda' AND jueves_sucursal_externa = ?)
            OR (viernes_estado = 'Otra.Tienda' AND viernes_sucursal_externa = ?)
            OR (sabado_estado = 'Otra.Tienda' AND sabado_sucursal_externa = ?)
            OR (domingo_estado = 'Otra.Tienda' AND domingo_sucursal_externa = ?)
        )
        ORDER BY CASE WHEN cod_sucursal = ? THEN 0 ELSE 1 END
        LIMIT 1
    ");

    $stmt->execute([
        $codOperario,
        $idSemana,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal,
        $codSucursal  // para el ORDER BY CASE WHEN cod_sucursal = ?
    ]);

    $horario = $stmt->fetch();

    if (!$horario) {
        return null;
    }

    // Filtrar solo los días que aplican para esta sucursal
    return filtrarHorarioPorSucursal($horario, $codSucursal);
}

/**
 * Filtra un horario completo para mostrar solo días que aplican a una sucursal
 */
function filtrarHorarioPorSucursal($horario, $codSucursal)
{
    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $horarioFiltrado = $horario;
    $sucursalPrincipal = (string)($horario['cod_sucursal'] ?? '');
    $esSucursalPrincipal = ($sucursalPrincipal === (string)$codSucursal);

    foreach ($dias as $dia) {
        $estadoDia = $horario["{$dia}_estado"] ?? '';
        $sucursalExternaDia = (string)($horario["{$dia}_sucursal_externa"] ?? '');
        $codSucursalStr = (string)$codSucursal;

        if ($esSucursalPrincipal) {
            // No se filtra nada - todo aplica para la sucursal principal
        } else {
            // Solo mostrar los días donde está como Otra.Tienda para ESTA sucursal
            $aplica = ($estadoDia === 'Otra.Tienda' && $sucursalExternaDia === $codSucursalStr);
            if (!$aplica) {
                $horarioFiltrado["{$dia}_estado"] = '';
                $horarioFiltrado["{$dia}_entrada"] = null;
                $horarioFiltrado["{$dia}_salida"] = null;
                $horarioFiltrado["{$dia}_horas"] = 0;
                $horarioFiltrado["{$dia}_comentario"] = null;
                $horarioFiltrado["{$dia}_sucursal_externa"] = null;
            }
        }
    }

    return $horarioFiltrado;
}

/**
 * Determina si un día aplica para una sucursal
 */
function diaAplicaParaSucursalCompleto($horario, $dia, $codSucursal)
{
    if (!$horario || !$codSucursal) {
        return false;
    }

    $codSucursalBuscar = (string)$codSucursal;
    $sucursalPrincipal = (string)($horario['cod_sucursal'] ?? '');
    $estadoDia = $horario["{$dia}_estado"] ?? '';
    $sucursalExterna = (string)($horario["{$dia}_sucursal_externa"] ?? '');

    if ($sucursalPrincipal === $codSucursalBuscar) {
        return true;
    }

    if ($estadoDia === 'Otra.Tienda' && $sucursalExterna === $codSucursalBuscar) {
        return true;
    }

    return false;
}

/**
 * Obtiene el nombre de la sucursal externa si existe
 */
function obtenerNombreSucursalExterna($codSucursalExterna)
{
    global $conn;

    if (empty($codSucursalExterna)) {
        return null;
    }

    $stmt = $conn->prepare("SELECT nombre FROM sucursales WHERE codigo = ? LIMIT 1");
    $stmt->execute([$codSucursalExterna]);
    $result = $stmt->fetch();

    return $result['nombre'] ?? null;
}

/**
 * Obtiene las categorías vigentes de los operarios
 */
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

// --- Carga de Sucursales según Cargo ---
$puedeVerTodasSucursales = tienePermiso('horarios_programados', 'ver_todas_sucursales', $cargoOperario);
$esFiltroSucursalPropia  = tienePermiso('horarios_programados', 'filtro_sucursal_propia', $cargoOperario);

if ($puedeVerTodasSucursales) {
    $sucursales = obtenerSucursalesFisicas();
} else {
    $sucursales = obtenerSucursalesUsuario($usuario['CodOperario']);
}

// Validar que no intente exportar "todas" si no tiene permiso
if ($mostrarTodas && !$puedeVerTodasSucursales) {
    if (!empty($sucursales)) {
        $sucursalSeleccionada = $sucursales[0]['codigo'];
        $mostrarTodas = false;
    } else {
        die("No tienes sucursales asignadas.");
    }
}

if (empty($sucursales)) {
    die("No tienes sucursales asignadas.");
}

// --- Recopilar datos ---
$horariosPorSucursal = [];

if ($mostrarTodas) {
    foreach ($sucursales as $sucursal) {
        $usarLiderLocal = false;
        $ops = obtenerTodosOperariosEnSucursal($sucursal['codigo'], $semana['id'], false);

        // Fallback a Horarios de Líderes
        if (empty($ops)) {
            $ops = obtenerTodosOperariosEnSucursal($sucursal['codigo'], $semana['id'], true);
            $usarLiderLocal = true;
        }

        if (!empty($ops)) {
            $horariosFiltrados = [];
            foreach ($ops as &$operario) {
                $codOperario = $operario['CodOperario'];
                $horario = obtenerHorarioFiltradoPorSucursal(
                    $codOperario,
                    $semana['id'],
                    $sucursal['codigo'],
                    $usarLiderLocal
                );
                if ($horario) {
                    $horariosFiltrados[$codOperario] = $horario;
                }
            }
            unset($operario);

            $codigosOperarios = array_column($ops, 'CodOperario');
            $categorias = obtenerCategoriasOperariosLocal($codigosOperarios);

            foreach ($ops as &$operario) {
                $codOperario = $operario['CodOperario'];
                $operario['categoria'] = $categorias[$codOperario] ?? [
                    'NombreCategoria' => 'Sin categoría',
                    'Peso' => '-',
                    'idCategoria' => 0
                ];
            }
            unset($operario);

            $horariosPorSucursal[$sucursal['codigo']] = [
                'nombre' => $sucursal['nombre'],
                'operarios' => $ops,
                'horarios' => $horariosFiltrados,
                'esDeLider' => $usarLiderLocal
            ];
        }
    }
} else {
    // Verificar que la sucursal seleccionada sea válida para este usuario
    $sucursalValida = false;
    $nombreSucursal = '';
    foreach ($sucursales as $suc) {
        if ($suc['codigo'] == $sucursalSeleccionada) {
            $sucursalValida = true;
            $nombreSucursal = $suc['nombre'];
            break;
        }
    }

    if (!$sucursalValida) {
        die("No tienes acceso a la sucursal seleccionada o no existe.");
    }

    $usarLiderLocal = false;
    $ops = obtenerTodosOperariosEnSucursal($sucursalSeleccionada, $semana['id'], false);

    // Fallback a Horarios de Líderes
    if (empty($ops)) {
        $ops = obtenerTodosOperariosEnSucursal($sucursalSeleccionada, $semana['id'], true);
        $usarLiderLocal = true;
    }

    if (!empty($ops)) {
        $horariosFiltrados = [];
        foreach ($ops as &$operario) {
            $codOperario = $operario['CodOperario'];
            $horario = obtenerHorarioFiltradoPorSucursal(
                $codOperario,
                $semana['id'],
                $sucursalSeleccionada,
                $usarLiderLocal
            );
            if ($horario) {
                $horariosFiltrados[$codOperario] = $horario;
            }
        }
        unset($operario);

        $codigosOperarios = array_column($ops, 'CodOperario');
        $categorias = obtenerCategoriasOperariosLocal($codigosOperarios);

        foreach ($ops as &$operario) {
            $codOperario = $operario['CodOperario'];
            $operario['categoria'] = $categorias[$codOperario] ?? [
                'NombreCategoria' => 'Sin categoría',
                'Peso' => '-',
                'idCategoria' => 0
            ];
        }
        unset($operario);

        $horariosPorSucursal[$sucursalSeleccionada] = [
            'nombre' => $nombreSucursal,
            'operarios' => $ops,
            'horarios' => $horariosFiltrados,
            'esDeLider' => $usarLiderLocal
        ];
    }
}

if (empty($horariosPorSucursal)) {
    die("No hay registros de horarios para esta selección.");
}

// Configurar nombre de archivo dinámico y estado
$contieneLider = false;
foreach ($horariosPorSucursal as $sucData) {
    if ($sucData['esDeLider']) {
        $contieneLider = true;
        break;
    }
}
$tipoEstado = $contieneLider ? 'BORRADOR_LIDER' : 'OFICIAL';
$marcaTiempo = date('Ymd_Hi');
$sufijoSucursal = $mostrarTodas ? 'todas' : $sucursalSeleccionada;
$nombreArchivo = "horarios_semana_{$semanaSeleccionada}_{$sufijoSucursal}_{$tipoEstado}_{$marcaTiempo}.xls";

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
    .nota-lider { color: #0c5460; background-color: #d1ecf1; padding: 8px; border: 1px solid #bee5eb; margin-bottom: 10px; font-weight: bold; }
    .meta-table td { border: none !important; padding: 3px !important; }
</style>
</head><body>';

// Renderizar encabezado de verificación de validez y control de cambios
$nombreCompletoUsuario = trim($usuario['Nombre'] . ' ' . $usuario['Apellido'] . ' ' . ($usuario['Apellido2'] ?? ''));
$nombreTiendaFiltro = $mostrarTodas ? 'Todas las sucursales' : ($nombreSucursal ?? obtenerNombreSucursalExterna($sucursalSeleccionada));

echo '<table class="meta-table" style="border: none; margin-bottom: 20px; width: 100%;">';
echo '<tr><td colspan="9" style="font-size: 14pt; font-weight: bold; color: #0E544C;">REPORTE DE HORARIOS - SEMANA ' . htmlspecialchars($semanaSeleccionada) . '</td></tr>';
echo '<tr><td colspan="9"><b>Filtro de Tienda:</b> ' . htmlspecialchars($nombreTiendaFiltro) . '</td></tr>';
echo '<tr><td colspan="9"><b>Generado por:</b> ' . htmlspecialchars($nombreCompletoUsuario) . '</td></tr>';
echo '<tr><td colspan="9"><b>Fecha y Hora de Generación:</b> ' . date('d/m/Y h:i A') . '</td></tr>';
echo '<tr><td colspan="9"><b>Estado del Reporte:</b> ' . ($tipoEstado === 'OFICIAL' ? '<span style="color: #27ae60; font-weight: bold; font-size: 11pt;">✔ OFICIAL (Confirmado por Operaciones)</span>' : '<span style="color: #d35400; font-weight: bold; font-size: 11pt;">⚠ PREVIO (Borrador de Líderes - Sujeto a confirmación)</span>') . '</td></tr>';
echo '<tr><td colspan="9" style="font-size: 8.5pt; color: #7f8c8d; font-style: italic; border-top: 1px solid #ccc; padding-top: 5px !important;">Nota: Este documento representa una captura estática de la base de datos a la fecha y hora indicadas. Los horarios válidos y vigentes son únicamente los que se visualizan en tiempo real en la plataforma ERP Batidos Pitaya.</td></tr>';
echo '</table><br>';

foreach ($horariosPorSucursal as $codSucursal => $data) {
    echo "<h3>" . htmlspecialchars($data['nombre']) . "</h3>";
    if ($data['esDeLider']) {
        echo "<div class='nota-lider'>Horarios programados por líderes (no confirmados aún por operaciones)</div>";
    }

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

    foreach ($data['operarios'] as $operario) {
        $horario = $data['horarios'][$operario['CodOperario']] ?? null;
        if (!$horario) continue;

        echo '<tr>';
        echo '<td>' . htmlspecialchars($operario['Nombre'] . ' ' . $operario['Apellido'] . ' ' . $operario['Apellido2']) . '</td>';
        echo '<td>' . htmlspecialchars($operario['categoria']['NombreCategoria']) . '</td>';

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
                        $nomExt = obtenerNombreSucursalExterna($sucursalExt);
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
