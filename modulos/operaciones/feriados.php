<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Verificar acceso
if (!tienePermiso('gestion_feriados', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Verificar si se solicitó la exportación a Excel para contabilidad
if (isset($_GET['exportar_excel_contabilidad'])) {
    // Obtener parámetros de filtro
    $sucursalSeleccionada = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
    $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
    $operarioSeleccionado = $_GET['operario'] ?? null;

    // Obtener los datos con los mismos filtros
    $feriadosTrabajados = obtenerFeriadosTrabajados(
        !empty($sucursalSeleccionada) ? $sucursalSeleccionada : null,
        $fechaDesde,
        $fechaHasta,
        !empty($operarioSeleccionado) ? $operarioSeleccionado : null
    );

    // Filtrar solo los feriados con estado "Pagado"
    $feriadosPagados = array_filter($feriadosTrabajados, function ($ft) {
        return $ft['estado'] === 'Pagado';
    });

    // Agrupar feriados por colaborador (solo los pagados)
    $feriadosAgrupados = [];
    foreach ($feriadosPagados as $ft) {
        $codOperario = $ft['cod_operario'];
        if (!isset($feriadosAgrupados[$codOperario])) {
            $feriadosAgrupados[$codOperario] = [
                'nombre' => $ft['nombre_operario'],
                'sucursal' => $ft['sucursal_nombre'],
                'departamento' => $ft['sucursal_departamento'], // Usar departamento de la sucursal
                'total_horas_trabajadas' => 0,
                'total_horas_pagar' => 0,
                'feriados_nacionales' => 0,
                'feriados_departamentales' => 0,
                'dias_feriados' => 0,
                'detalle_feriados' => []
            ];
        }

        $feriadosAgrupados[$codOperario]['total_horas_trabajadas'] += $ft['horas_trabajadas'];
        $feriadosAgrupados[$codOperario]['total_horas_pagar'] += 8;
        $feriadosAgrupados[$codOperario]['dias_feriados']++;

        // Guardar detalle del feriado CON DEPARTAMENTO
        $feriadosAgrupados[$codOperario]['detalle_feriados'][] = [
            'fecha' => $ft['fecha'],
            'nombre' => $ft['feriado_nombre'],
            'tipo' => $ft['feriado_tipo'],
            'departamento' => $ft['departamento_nombre'] // Agregar departamento al detalle
        ];

        if ($ft['feriado_tipo'] === 'Nacional') {
            $feriadosAgrupados[$codOperario]['feriados_nacionales']++;
        } else {
            $feriadosAgrupados[$codOperario]['feriados_departamentales']++;
        }
    }

    // Configurar headers para descarga de archivo Excel CON UTF-8
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="feriados_pagados_contabilidad_' . $fechaDesde . '_a_' . $fechaHasta . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
    echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '</head>';
    echo '<body>';

    // HOJA 1: RESUMEN
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Colaborador</th>';
    echo '<th>Código</th>';
    echo '<th>Código Contrato</th>'; // NUEVA COLUMNA
    echo '<th>Sucursal</th>';
    echo '<th>Departamento Sucursal</th>';
    echo '<th style="display:none;">Total Horas Trabajadas</th>';
    echo '<th>Total Horas a Pagar (8 x día)</th>';
    echo '<th>Días Feriados</th>';
    echo '<th>Feriados Nacionales</th>';
    echo '<th>Feriados Departamentales</th>';
    echo '</tr>';

    foreach ($feriadosAgrupados as $codOperario => $datos) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($datos['nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $codOperario . '</td>';
        echo '<td>' . ($datos['cod_contrato'] ?? '') . '</td>'; // NUEVA COLUMNA
        echo '<td>' . htmlspecialchars($datos['sucursal'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($datos['departamento'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . number_format($datos['total_horas_trabajadas'], 2) . '</td>';
        echo '<td>' . number_format($datos['total_horas_pagar'], 2) . '</td>';
        echo '<td>' . $datos['dias_feriados'] . '</td>';
        echo '<td>' . $datos['feriados_nacionales'] . '</td>';
        echo '<td>' . $datos['feriados_departamentales'] . '</td>';
        echo '</tr>';
    }

    echo '</table>';

    // HOJA 2: DETALLE INDIVIDUAL
    echo '<br><br><br>'; // Separación entre hojas
    echo '<table border="1">';
    echo '<tr>';
    echo '<th colspan="7" style="text-align: center; background-color: #0E544C; color: white;">DETALLE INDIVIDUAL DE FERIADOS TRABAJADOS</th>';
    echo '</tr>';
    echo '<tr>';
    echo '<th>Colaborador</th>';
    echo '<th>Código</th>';
    echo '<th>Código Contrato</th>'; // NUEVA COLUMNA
    echo '<th>Sucursal</th>';
    echo '<th>Departamento Sucursal</th>';
    echo '<th>Fecha Feriado</th>';
    echo '<th>Nombre Feriado</th>';
    echo '<th>Tipo Feriado (Departamento)</th>';
    echo '</tr>';

    foreach ($feriadosAgrupados as $codOperario => $datos) {
        foreach ($datos['detalle_feriados'] as $detalle) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($datos['nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . $codOperario . '</td>';
            echo '<td>' . ($datos['cod_contrato'] ?? '') . '</td>'; // NUEVA COLUMNA
            echo '<td>' . htmlspecialchars($datos['sucursal'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($datos['departamento'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . formatoFecha($detalle['fecha']) . '</td>';
            echo '<td>' . htmlspecialchars($detalle['nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($detalle['tipo'] . ($detalle['tipo'] === 'Departamental' ? ' (' . $detalle['departamento'] . ')' : ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
    }

    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Verificar si se solicitó la exportación a Excel
if (isset($_GET['exportar_excel'])) {
    // Obtener parámetros de filtro
    $sucursalSeleccionada = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
    $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
    $operarioSeleccionado = $_GET['operario'] ?? null;

    // Obtener los datos con los mismos filtros - USAR FUNCIÓN MODIFICADA
    $feriadosTrabajados = obtenerFeriadosTrabajados(
        !empty($sucursalSeleccionada) ? $sucursalSeleccionada : null,
        $fechaDesde,
        $fechaHasta,
        !empty($operarioSeleccionado) ? $operarioSeleccionado : null
    );

    // Filtrar solo los feriados con estado "Pagado"
    //SI en algún momento se necesita mostrar todos sería:
    //$feriadosPagados = $feriadosTrabajados; // Temporal: ver todos los registros
    $feriadosPagados = array_filter($feriadosTrabajados, function ($ft) {
        return $ft['estado'] === 'Pagado';
    });

    // Configurar headers para descarga de archivo Excel CON UTF-8
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="feriados_trabajados_pagados_' . $fechaDesde . '_a_' . $fechaHasta . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
    echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '</head>';
    echo '<body>';

    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Código</th>'; // NUEVA COLUMNA
    echo '<th>Persona</th>';
    // echo '<th>CODIGO</th>'; // Código de operario de la tabla Operarios
    echo '<th>SUCURSAL</th>';
    // echo '<th>DEPARTAMENTO SUCURSAL</th>';
    echo '<th>FECHA</th>';
    //echo '<th>NOMBRE FERIADO</th>'; // NUEVA COLUMNA
    //echo '<th>TIPO FERIADO</th>'; // NUEVA COLUMNA
    // echo '<th>FECHA</th>';
    // SE ELIMINAN LAS SIGUIENTES COLUMNAS:
    // echo '<th>Departamento</th>';
    // echo '<th>Feriado</th>';
    // echo '<th>Tipo</th>';
    // echo '<th>Hora Entrada</th>';
    // echo '<th>Hora Salida</th>';
    // echo '<th style="display:none;">Horas Trabajadas</th>';
    echo '<th>HORAS LABORADAS EN FERIADOS</th>';
    // echo '<th>Estado</th>';
    echo '<th>OBSERVACIONES</th>';
    echo '<th>Fecha Registro</th>';
    echo '</tr>';

    foreach ($feriadosPagados as $ft) {
        echo '<tr>';
        echo '<td>' . ($ft['cod_contrato'] ?? '') . '</td>'; // NUEVA COLUMNA
        // echo '<td>' . htmlspecialchars($ft['nombre_operario'], ENT_QUOTES, 'UTF-8') . '</td>';
        // COLUMNA MODIFICADA: Combina cod_contrato + nombre_operario
        echo '<td>';
        if (!empty($ft['cod_contrato'])) {
            echo htmlspecialchars($ft['cod_contrato'] . ' ' . $ft['nombre_operario'], ENT_QUOTES, 'UTF-8');
        } else {
            echo htmlspecialchars($ft['nombre_operario'], ENT_QUOTES, 'UTF-8');
        }
        echo '</td>';
        // echo '<td>' . htmlspecialchars($ft['cod_operario'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($ft['sucursal_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        // echo '<td>' . htmlspecialchars($ft['sucursal_departamento'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . formatoFecha($ft['fecha']) . '</td>';
        // echo '<td>' . htmlspecialchars($ft['feriado_nombre']) . '</td>'; // NUEVO DATO
        //echo '<td>' . htmlspecialchars($ft['feriado_tipo']) . '</td>'; // NUEVO DATO
        // echo '<td>' . formatoFecha($ft['fecha']) . '</td>';
        // SE ELIMINAN LOS SIGUIENTES DATOS:
        // echo '<td>' . htmlspecialchars($ft['sucursal_departamento']) . '</td>';
        // echo '<td>' . formatoFecha($ft['fecha']) . '</td>';
        // echo '<td>' . htmlspecialchars($ft['feriado_nombre']) . '</td>';
        // echo '<td>' . htmlspecialchars($ft['feriado_tipo']) . '</td>';
        // echo '<td>' . ($ft['hora_entrada'] ? $ft['hora_entrada'] : '-') . '</td>';
        // echo '<td>' . ($ft['hora_salida'] ? $ft['hora_salida'] : '-') . '</td>';
        // echo '<td>' . number_format($ft['horas_trabajadas'], 2) . '</td>';
        echo '<td>8.00</td>';
        // echo '<td>' . $ft['estado'] . '</td>';
        //echo '<td>' . ($ft['observaciones'] ? htmlspecialchars($ft['observaciones']) : '-') . '</td>';
        // MODIFICADO: Mostrar nombre del feriado y tipo con departamento
        $observacionCompleta = $ft['feriado_nombre'] . ' (' . $ft['feriado_tipo'];
        if ($ft['feriado_tipo'] === 'Departamental') {
            $observacionCompleta .= ' - ' . $ft['departamento_nombre'];
        }
        $observacionCompleta .= ')';
        echo '<td>' . htmlspecialchars($observacionCompleta, ENT_QUOTES, 'UTF-8') . '</td>';
        // NUEVA COLUMNA: FECHA REGISTRO
        echo '<td>';
        if (!empty($ft['fecha_creacion'])) {
            echo formatoFecha($ft['fecha_creacion']); // Formatea la fecha de creación
            // Si quieres incluir la hora también, puedes usar:
            // echo date('d-m-Y H:i', strtotime($ft['fecha_creacion']));
        } else {
            echo '-';
        }
        echo '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Obtener todas las sucursales (el jefe de operaciones puede ver todas)
$sucursales = obtenerTodasSucursales();

// Obtener lista de operarios para el filtro
function obtenerTodosOperarios()
{
    global $conn;

    $sql = "SELECT o.CodOperario, 
                   CONCAT(
                       IFNULL(o.Nombre, ''), ' ', 
                       IFNULL(o.Nombre2, ''), ' ', 
                       IFNULL(o.Apellido, ''), ' ', 
                       IFNULL(o.Apellido2, '')
                   ) AS nombre_completo 
            FROM Operarios o
            LEFT JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            WHERE (anc.CodNivelesCargos IS NULL OR anc.CodNivelesCargos != 27)
            AND o.Operativo = 1
            GROUP BY o.CodOperario
            ORDER BY nombre_completo";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

$operarios = obtenerTodosOperarios();

// Procesar aprobación/denegación de feriados trabajados
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aprobar_feriado'])) {
    procesarAprobacionFeriado();
}

// Obtener datos para los filtros
$sucursalSeleccionada = $_GET['sucursal'] ?? null;
$fechaDesde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
$operarioSeleccionado = $_GET['operario'] ?? null;

// Obtener feriados trabajados si hay fechas seleccionadas
$feriadosTrabajados = [];
if ($fechaDesde && $fechaHasta) {
    $feriadosTrabajados = obtenerFeriadosTrabajados(
        !empty($sucursalSeleccionada) ? $sucursalSeleccionada : null,
        $fechaDesde,
        $fechaHasta,
        !empty($operarioSeleccionado) ? $operarioSeleccionado : null
    );
}

// Funciones auxiliares específicas para feriados
function obtenerFeriadosTrabajados($codSucursal, $fechaDesde, $fechaHasta, $codOperario = null)
{
    global $conn;

    try {
        // 1. PRIMERO: Obtener TODOS los operarios asignados a la(s) sucursal(es) en el período
        $sqlOperarios = "
            SELECT DISTINCT o.CodOperario, 
                   CONCAT(
                       IFNULL(o.Nombre, ''), ' ', 
                       IFNULL(o.Nombre2, ''), ' ', 
                       IFNULL(o.Apellido, ''), ' ', 
                       IFNULL(o.Apellido2, '')
                   ) AS nombre_completo,
                   s.codigo as sucursal_codigo,
                   s.nombre as sucursal_nombre,
                   s.supervisor_asignado,
                   s.cod_departamento,
                   d.nombre as nombre_departamento
            FROM Operarios o
            INNER JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
            INNER JOIN sucursales s ON anc.Sucursal = s.codigo
            INNER JOIN departamentos d ON s.cod_departamento = d.codigo
            WHERE o.Operativo = 1
            AND (anc.Fin IS NULL OR anc.Fin >= ?) -- Verificar asignación activa
            AND o.CodOperario NOT IN (
                SELECT DISTINCT anc2.CodOperario 
                FROM AsignacionNivelesCargos anc2
                WHERE anc2.CodNivelesCargos = 27
                AND (anc2.Fin IS NULL OR anc2.Fin >= CURDATE())
            )
        ";

        $paramsOperarios = [$fechaHasta]; // Fecha hasta para verificar asignaciones activas

        if ($codSucursal) {
            $sqlOperarios .= " AND s.codigo = ?";
            $paramsOperarios[] = $codSucursal;
        }

        if ($codOperario) {
            $sqlOperarios .= " AND o.CodOperario = ?";
            $paramsOperarios[] = $codOperario;
        }

        $sqlOperarios .= " ORDER BY o.Nombre, o.Apellido";

        $stmtOperarios = $conn->prepare($sqlOperarios);
        $stmtOperarios->execute($paramsOperarios);
        $operarios = $stmtOperarios->fetchAll();

        // 2. SEGUNDO: Obtener feriados en el rango de fechas
        $feriadosEnRango = obtenerFeriadosEnRango($fechaDesde, $fechaHasta);

        $resultados = [];

        // 3. TERCERO: Para cada operario, verificar cada fecha feriado
        foreach ($operarios as $operario) {
            foreach ($feriadosEnRango as $feriado) {
                // Verificar si el feriado aplica al departamento del operario
                if (
                    $feriado['tipo'] === 'Nacional' ||
                    ($feriado['tipo'] === 'Departamental' &&
                        $feriado['departamento_codigo'] == $operario['cod_departamento'])
                ) {

                    // Buscar marcación si existe
                    $sqlMarcacion = "
                        SELECT m.id, m.hora_ingreso, m.hora_salida
                        FROM marcaciones m
                        WHERE m.CodOperario = ? 
                        AND m.fecha = ?
                        AND m.sucursal_codigo = ?
                        LIMIT 1
                    ";

                    $stmtMarcacion = $conn->prepare($sqlMarcacion);
                    $stmtMarcacion->execute([
                        $operario['CodOperario'],
                        $feriado['fecha'],
                        $operario['sucursal_codigo']
                    ]);
                    $marcacion = $stmtMarcacion->fetch();

                    // Calcular horas trabajadas si hay marcación
                    $horasTrabajadas = 0;
                    $horaEntrada = null;
                    $horaSalida = null;

                    if ($marcacion && $marcacion['hora_ingreso'] && $marcacion['hora_salida']) {
                        $entrada = new DateTime($marcacion['hora_ingreso']);
                        $salida = new DateTime($marcacion['hora_salida']);
                        $diferencia = $salida->diff($entrada);
                        $horasTrabajadas = $diferencia->h + ($diferencia->i / 60);
                        $horaEntrada = $marcacion['hora_ingreso'];
                        $horaSalida = $marcacion['hora_salida'];
                    }

                    // Obtener estado del feriado trabajado - ACTUALIZADO PARA INCLUIR cod_contrato
                    $estadoFeriado = obtenerEstadoFeriadoTrabajado(
                        $marcacion ? $marcacion['id'] : null,
                        $operario['CodOperario'],
                        $feriado['fecha']
                    );

                    // Determinar el nombre del departamento para mostrar
                    $departamentoMostrar = $feriado['tipo'] === 'Nacional'
                        ? 'Nacional'
                        : ($feriado['nombre_departamento'] ?? $operario['nombre_departamento']);

                    // Obtener fecha de inicio de contrato
                    $inicioContrato = obtenerUltimaFechaInicioContrato($operario['CodOperario']);

                    $resultados[] = [
                        'id_marcacion' => $marcacion ? $marcacion['id'] : null,
                        'cod_operario' => $operario['CodOperario'],
                        'nombre_operario' => $operario['nombre_completo'],
                        'inicio_contrato' => $inicioContrato,
                        'fecha' => $feriado['fecha'],
                        'sucursal_codigo' => $operario['sucursal_codigo'],
                        'sucursal_nombre' => $operario['sucursal_nombre'],
                        'supervisor_asignado' => $operario['supervisor_asignado'],
                        'sucursal_departamento' => $operario['nombre_departamento'],
                        'sucursal_cod_departamento' => $operario['cod_departamento'],
                        'hora_entrada' => $horaEntrada,
                        'hora_salida' => $horaSalida,
                        'horas_trabajadas' => $horasTrabajadas,
                        'feriado_id' => $feriado['id'],
                        'feriado_nombre' => $feriado['nombre'],
                        'feriado_tipo' => $feriado['tipo'],
                        'departamento_nombre' => $departamentoMostrar,
                        'feriado_departamento_codigo' => $feriado['departamento_codigo'],
                        'estado' => $estadoFeriado ? ($estadoFeriado['estado'] ?? 'Pendiente') : ($marcacion ? 'Con Marcación' : 'Sin marcación'),
                        'observaciones' => $estadoFeriado['observaciones'] ?? null,
                        'id_aprobacion' => $estadoFeriado['id'] ?? null,
                        'cod_contrato' => $estadoFeriado['cod_contrato'] ?? null,
                        'fecha_creacion' => $estadoFeriado['fecha_creacion'] ?? null,
                        'tiene_marcacion' => !empty($marcacion)
                    ];
                }
            }
        }

        // Ordenar resultados por fecha descendente, luego por nombre
        usort($resultados, function ($a, $b) {
            if ($a['fecha'] == $b['fecha']) {
                return strcmp($a['nombre_operario'], $b['nombre_operario']);
            }
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });

        return $resultados;

    } catch (PDOException $e) {
        error_log("Error al obtener feriados trabajados: " . $e->getMessage());
        return [];
    }
}

function obtenerEstadoFeriadoTrabajado($idMarcacion, $codOperario = null, $fechaFeriado = null)
{
    global $conn;

    $sql = "
        SELECT id, estado, observaciones, horas_trabajadas, cod_contrato, fecha_creacion 
        FROM FeriadosStatus 
        WHERE ";

    $params = [];

    if ($idMarcacion) {
        $sql .= "(id_marcacion = ?)";
        $params[] = $idMarcacion;

        // Respaldo por operario y fecha si tenemos los datos
        if ($codOperario && $fechaFeriado) {
            $sql .= " OR (cod_operario = ? AND fecha_feriado = ?)";
            $params[] = $codOperario;
            $params[] = $fechaFeriado;
        }
    } elseif ($codOperario && $fechaFeriado) {
        $sql .= "cod_operario = ? AND fecha_feriado = ?";
        $params[] = $codOperario;
        $params[] = $fechaFeriado;
    } else {
        return null;
    }

    $sql .= " ORDER BY id_marcacion DESC LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function procesarAprobacionFeriado()
{
    global $conn;

    try {
        $idMarcacion = $_POST['id_marcacion'];
        $codOperario = $_POST['cod_operario'];
        $estado = $_POST['estado'];
        $observaciones = $_POST['observaciones'] ?? null;
        $horasTrabajadas = $_POST['horas_trabajadas'];
        $fechaFeriado = $_POST['fecha_feriado'] ?? null;

        // Validar que tenemos la fecha del feriado
        if (empty($fechaFeriado)) {
            throw new Exception("Fecha del feriado no especificada");
        }

        // OBTENER EL ÚLTIMO CÓDIGO DE CONTRATO DEL OPERARIO
        $codContrato = obtenerUltimoCodigoContrato($codOperario);

        // Obtener los parámetros de filtro del POST
        $sucursalFiltro = $_POST['sucursal_filtro'] ?? '';
        $desdeFiltro = $_POST['desde_filtro'] ?? date('Y-m-d', strtotime('-1 month'));
        $hastaFiltro = $_POST['hasta_filtro'] ?? date('Y-m-d');
        $operarioFiltro = $_POST['operario_filtro'] ?? '';

        // Verificar si ya existe un registro para este operario en esta fecha
        $sqlBuscarExistente = "
            SELECT id 
            FROM FeriadosStatus 
            WHERE cod_operario = ? 
            AND (
                (id_marcacion IS NOT NULL AND id_marcacion = ?) OR
                (id_marcacion IS NULL AND fecha_feriado = ?)
            )
            LIMIT 1
        ";

        $paramsBuscar = [$codOperario];
        if (!empty($idMarcacion)) {
            $paramsBuscar[] = $idMarcacion;
            $paramsBuscar[] = $fechaFeriado;
        } else {
            $paramsBuscar[] = null;
            $paramsBuscar[] = $fechaFeriado;
        }

        $stmtBuscar = $conn->prepare($sqlBuscarExistente);
        $stmtBuscar->execute($paramsBuscar);
        $existente = $stmtBuscar->fetch();

        if ($existente) {
            // Actualizar registro existente
            $sqlActualizar = "
                UPDATE FeriadosStatus 
                SET estado = ?, observaciones = ?, horas_trabajadas = ?, 
                    actualizado_por = ?, fecha_actualizacion = NOW(), cod_contrato = ?
                WHERE id = ?
            ";
            $stmtActualizar = $conn->prepare($sqlActualizar);
            $stmtActualizar->execute([
                $estado,
                $observaciones,
                $horasTrabajadas,
                $_SESSION['usuario_id'],
                $codContrato,
                $existente['id']
            ]);
        } else {
            // Crear nuevo registro
            $sqlInsertar = "
                INSERT INTO FeriadosStatus (
                    id_marcacion, cod_operario, fecha_feriado, estado, observaciones, 
                    horas_trabajadas, creado_por, actualizado_por, cod_contrato
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            $stmtInsertar = $conn->prepare($sqlInsertar);
            $stmtInsertar->execute([
                !empty($idMarcacion) ? $idMarcacion : null,
                $codOperario,
                $fechaFeriado,
                $estado,
                $observaciones,
                $horasTrabajadas,
                $_SESSION['usuario_id'],
                $_SESSION['usuario_id'],
                $codContrato
            ]);
        }

        $_SESSION['exito'] = 'Estado del feriado trabajado actualizado correctamente';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error al procesar el feriado trabajado: ' . $e->getMessage();
    }

    // Redireccionar manteniendo los filtros originales
    $params = [
        'sucursal' => $_POST['sucursal_filtro'] ?? '',
        'desde' => $_POST['desde_filtro'] ?? '',
        'hasta' => $_POST['hasta_filtro'] ?? '',
        'operario' => $_POST['operario_filtro'] ?? ''
    ];

    header('Location: feriados.php?' . http_build_query($params));
    exit();
}

// Función para obtener el nombre del operario por su código
function obtenerNombreOperario($codOperario)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT CONCAT(
            IFNULL(Nombre, ''), ' ', 
            IFNULL(Nombre2, ''), ' ', 
            IFNULL(Apellido, ''), ' ', 
            IFNULL(Apellido2, '')
        ) AS nombre_completo 
        FROM Operarios 
        WHERE CodOperario = ?
    ");
    $stmt->execute([$codOperario]);
    $result = $stmt->fetch();

    return $result ? $result['nombre_completo'] : '';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feriados Trabajados - Operaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <link rel="stylesheet" href="css/feriados.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Feriados Trabajados'); ?>

            <div class="container-feriados">

                <?php if (isset($_SESSION['exito'])): ?>
                    <div class="alert alert-success">
                        <?= $_SESSION['exito'] ?>
                        <?php unset($_SESSION['exito']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error'] ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="filters-container">
                    <div class="filters">
                        <div class="filter-group">
                            <label for="sucursal">Sucursal</label>
                            <select id="sucursal" name="sucursal" onchange="actualizarFiltros()">
                                <option value="">Todas las sucursales</option>
                                <?php foreach ($sucursales as $sucursal): ?>
                                    <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sucursal['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="operario">Colaborador</label>
                            <input type="text" id="operario" name="operario" placeholder="Escriba para buscar..." value="<?php
                            if ($operarioSeleccionado) {
                                echo htmlspecialchars(obtenerNombreOperario($operarioSeleccionado));
                            } else {
                                echo 'Todos los colaboradores';
                            }
                            ?>">
                            <input type="hidden" id="operario_id" name="operario" value="<?= $operarioSeleccionado ?>">
                            <div id="operarios-sugerencias" style="display: none;"></div>
                        </div>

                        <div class="filter-group">
                            <label for="desde">Desde</label>
                            <input type="date" id="desde" name="desde" value="<?= $fechaDesde ?>"
                                onchange="actualizarFiltros()">
                        </div>

                        <div class="filter-group">
                            <label for="hasta">Hasta</label>
                            <input type="date" id="hasta" name="hasta" value="<?= $fechaHasta ?>"
                                onchange="actualizarFiltros()">
                        </div>

                        <div class="filter-group" style="align-self: flex-end;">
                            <button type="button" onclick="actualizarFiltros()" class="btn">
                                <i class="fas fa-search"></i> Buscar
                            </button>
                        </div>

                        <?php if (tienePermiso('gestion_feriados', 'exportar', $cargoOperario)): ?>
                            <div class="action-buttons">
                                <!-- Botón de exportación normal (existente) -->
                                <a href="feriados.php?<?= http_build_query([
                                    'sucursal' => $sucursalSeleccionada ?? '',
                                    'operario' => $operarioSeleccionado ?? '',
                                    'desde' => $fechaDesde,
                                    'hasta' => $fechaHasta,
                                    'exportar_excel' => 1
                                ]) ?>" class="btn btn-primary">
                                    <i class="fas fa-file-excel"></i> Exportar
                                </a>

                                <!-- Nuevo botón de exportación para contabilidad (comparten permiso de exportar) -->
                                <a href="feriados.php?<?= http_build_query([
                                    'sucursal' => $sucursalSeleccionada ?? '',
                                    'operario' => $operarioSeleccionado ?? '',
                                    'desde' => $fechaDesde,
                                    'hasta' => $fechaHasta,
                                    'exportar_excel_contabilidad' => 1
                                ]) ?>" class="btn btn-contabilidad" style="margin-left: 10px;">
                                    <i class="fas fa-file-excel"></i> Exportar para Contabilidad
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-container">
                    <?php if (!empty($feriadosTrabajados)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Sucursal</th>
                                    <th>Inicio Contrato</th>
                                    <th>Fecha Feriado</th>
                                    <th>Feriado</th>
                                    <th>Tipo (Departamento)</th>
                                    <th style="display:none;">Horas Trabajadas</th>
                                    <th>Status</th>
                                    <th>Observaciones</th>
                                    <?php if (tienePermiso('gestion_feriados', 'aprobar', $cargoOperario)): ?>
                                        <th style="text-align: center; min-width: 180px;">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feriadosTrabajados as $ft): ?>
                                    <?php 
                                    $id_fila = $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'];
                                    
                                    // Lógica de permiso de aprobar: debe tener el permiso Y ser el supervisor asignado o admin
                                    // supervisor_asignado ahora es un JSON array, ej: [42, 78]
                                    $supervisoresIds = json_decode($ft['supervisor_asignado'] ?? '[]', true) ?: [];
                                    $esSupervisorAsignado = in_array((int)$_SESSION['usuario_id'], array_map('intval', $supervisoresIds));
                                    $puedeAprobar = (tienePermiso('gestion_feriados', 'aprobar', $cargoOperario) && ($esSupervisorAsignado));
                                    
                                    $yaTieneDecision = !empty($ft['id_aprobacion']);
                                    $puedeEditarObservacion = $puedeAprobar && $yaTieneDecision;
                                    ?>
                                    <tr id="feriado-row-<?= $id_fila ?>">
                                        <td><?= htmlspecialchars($ft['nombre_operario']) ?></td>
                                        <td><?= htmlspecialchars($ft['sucursal_nombre']) ?></td>
                                        <td class="text-nowrap"><?= formatoFecha($ft['inicio_contrato']) ?></td>
                                        <td><?= formatoFecha($ft['fecha']) ?></td>
                                        <td><?= htmlspecialchars($ft['feriado_nombre']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($ft['feriado_tipo']) ?>
                                            <?php if ($ft['feriado_tipo'] === 'Departamental'): ?>
                                                (<?= htmlspecialchars($ft['departamento_nombre']) ?>)
                                            <?php endif; ?>
                                        </td>
                                        <td style="display:none;"><?= number_format($ft['horas_trabajadas'], 2) ?></td>
                                        <td>
                                            <?php if ($ft['estado'] === 'Con Marcación'): ?>
                                                <span class="status-badge status-con-marcacion"
                                                    id="status-badge-<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>">
                                                    <i class="fas fa-clock"></i>
                                                    <?= date('H:i', strtotime($ft['hora_entrada'])) ?> -
                                                    <?= date('H:i', strtotime($ft['hora_salida'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="status-badge status-<?= strtolower(str_replace(' ', '-', $ft['estado'])) ?>"
                                                    id="status-badge-<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>">
                                                    <?= $ft['estado'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="observaciones-cell <?= $puedeEditarObservacion ? 'editable' : '' ?>"
                                                id="obs-display-<?= $id_fila ?>"
                                                <?= $puedeEditarObservacion ? "onclick=\"toggleEditObservacionesFeriado('$id_fila')\"" : "" ?>
                                                title="<?= $puedeEditarObservacion ? 'Click para editar' : ($puedeAprobar ? 'Debe cambiar el estado (Pagado/Descansado) para poder editar' : '') ?>">
                                                <?php if ($ft['observaciones']): ?>
                                                    <?= nl2br(htmlspecialchars($ft['observaciones'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin observaciones</span>
                                                <?php endif; ?>
                                            </div>
                                            <textarea
                                                id="obs-edit-<?= $id_fila ?>"
                                                class="observaciones-edit" style="display: none;"
                                                onblur="guardarObservacionesFeriado('<?= $id_fila ?>', '<?= $ft['cod_operario'] ?>', '<?= $ft['fecha'] ?>')"
                                                onkeyup="manejarTeclasObservaciones(event, '<?= $id_fila ?>', '<?= $ft['cod_operario'] ?>', '<?= $ft['fecha'] ?>')"
                                                rows="3"><?= htmlspecialchars($ft['observaciones'] ?? '') ?></textarea>
                                        </td>

                                        <?php if (tienePermiso('gestion_feriados', 'aprobar', $cargoOperario)): ?>
                                            <td style="text-align: center;">
                                                <?php if ($puedeAprobar): ?>
                                                    <div class="action-buttons-inline"
                                                        id="actions-<?= $id_fila ?>">
                                                        <?php if ($ft['estado'] === 'Pendiente' || $ft['estado'] === 'Sin marcación' || $ft['estado'] === 'Con Marcación'): ?>
                                                            <!-- Botones para estado Pendiente/Sin marcación -->
                                                            <button type="button" class="btn-action btn-approve"
                                                                onclick="actualizarEstadoFeriado('<?= $id_fila ?>', 'Pagado', '<?= $ft['cod_operario'] ?>', '<?= $ft['fecha'] ?>')"
                                                                title="Marcar como Pagado">
                                                                <i class="fas fa-dollar-sign"></i>
                                                            </button>
                                                            <button type="button" class="btn-action btn-compensado"
                                                                onclick="actualizarEstadoFeriado('<?= $id_fila ?>', 'Descansado', '<?= $ft['cod_operario'] ?>', '<?= $ft['fecha'] ?>')"
                                                                title="Marcar como Compensado/Descansado">
                                                                <i class="fas fa-bed"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <!-- Botones para estados Pagado/Descansado -->
                                                            <button type="button" class="btn-action btn-change"
                                                                onclick="cambiarEstadoFeriado('<?= $id_fila ?>', '<?= $ft['estado'] ?>', '<?= $ft['cod_operario'] ?>', '<?= $ft['fecha'] ?>')"
                                                                title="Cambiar estado">
                                                                <i class="fas fa-exchange-alt"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <?php if ($fechaDesde && $fechaHasta): ?>
                                <?php if (empty($sucursalSeleccionada) && empty($operarioSeleccionado)): ?>
                                    No se encontraron feriados trabajados entre <?= formatoFecha($fechaDesde) ?> y
                                    <?= formatoFecha($fechaHasta) ?>.
                                <?php elseif (!empty($operarioSeleccionado)): ?>
                                    No se encontraron feriados trabajados para
                                    <?= htmlspecialchars(obtenerNombreOperario($operarioSeleccionado)) ?>
                                    <?php if (!empty($sucursalSeleccionada)): ?>
                                        en <?= htmlspecialchars(obtenerNombreSucursal($sucursalSeleccionada)) ?>
                                    <?php endif; ?>
                                    entre <?= formatoFecha($fechaDesde) ?> y <?= formatoFecha($fechaHasta) ?>.
                                <?php else: ?>
                                    No se encontraron feriados trabajados para
                                    <?= htmlspecialchars(obtenerNombreSucursal($sucursalSeleccionada)) ?>
                                    entre <?= formatoFecha($fechaDesde) ?> y <?= formatoFecha($fechaHasta) ?>.
                                <?php endif; ?>
                            <?php else: ?>
                                Seleccione un rango de fechas para buscar feriados trabajados.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para aprobación de feriados trabajados -->
    <div class="modal" id="modalAprobacion">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Registrar Feriado Trabajado</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formAprobacion" method="post">
                <input type="hidden" name="aprobar_feriado" value="1">
                <input type="hidden" id="id_marcacion" name="id_marcacion">
                <input type="hidden" id="cod_operario" name="cod_operario">
                <input type="hidden" id="horas_trabajadas" name="horas_trabajadas">
                <input type="hidden" id="fecha_feriado" name="fecha_feriado">

                <div class="modal-body">
                    <div class="info-group">
                        <span class="info-label">Colaborador:</span>
                        <span class="info-value" id="modal-nombre"></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Sucursal:</span>
                        <span class="info-value" id="modal-sucursal"></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Fecha:</span>
                        <span class="info-value" id="modal-fecha"></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Feriado:</span>
                        <span class="info-value" id="modal-feriado"></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Tipo:</span>
                        <span class="info-value" id="modal-tipo"></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Hora Entrada:</span>
                        <span class="info-value" id="modal-hora-entrada"></span>
                    </div>

                    <div class="info-group">
                        <span class="info-label">Hora Salida:</span>
                        <span class="info-value" id="modal-hora-salida"></span>
                    </div>

                    <div style="display:none;" class="info-group">
                        <span class="info-label">Horas Trabajadas:</span>
                        <span class="info-value" id="modal-horas-trabajadas"></span>
                    </div>

                    <div class="form-group">
                        <label for="estado" class="form-label">Estado:</label>
                        <select id="estado" name="estado" class="form-select" required>
                            <option value="Pagado">Pagado</option>
                            <option value="Descansado">Compensado/Descansado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="observaciones" class="form-label">Observaciones:</label>
                        <textarea id="observaciones" name="observaciones" class="form-textarea"></textarea>
                    </div>
                </div>

                <!-- Dentro del formulario en el modal, antes del modal-footer -->
                <input type="hidden" name="sucursal_filtro"
                    value="<?= htmlspecialchars($sucursalSeleccionada ?? '') ?>">
                <input type="hidden" name="desde_filtro" value="<?= htmlspecialchars($fechaDesde) ?>">
                <input type="hidden" name="hasta_filtro" value="<?= htmlspecialchars($fechaHasta) ?>">
                <input type="hidden" name="operario_filtro"
                    value="<?= htmlspecialchars($operarioSeleccionado ?? '') ?>">

                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Datos de operarios para el autocompletado (generados dinámicamente)
        const operariosData = [
            { id: 0, nombre: 'Todos los colaboradores' },
            <?php foreach ($operarios as $op): ?>
                { id: <?= $op['CodOperario'] ?>, nombre: '<?= addslashes($op['nombre_completo']) ?>' },
            <?php endforeach; ?>
        ];
    </script>
    <script src="js/feriados.js?v=<?php echo mt_rand(1, 10000); ?>"></script>

</body>

</html>