<?php
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

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
    <style>
        .container-feriados {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px;
        }

        .filters-container {
            margin-bottom: 20px;
        }

        .filters {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
            position: relative;
        }

        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }

        select,
        input,
        button {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 8px 15px;
            background-color: #51B8AC;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
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

        .btn-success {
            background-color: #28a745;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-danger {
            background-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-primary {
            background-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0069d9;
        }

        .btn-info {
            background-color: #17a2b8;
        }

        .btn-info:hover {
            background-color: #138496;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            vertical-align: middle;
        }

        th {
            background-color: #0E544C;
            color: white;
            text-align: center;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .status-pendiente {
            color: #856404;
            background-color: #fff3cd;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }

        .status-pagado {
            color: #155724;
            background-color: #d4edda;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }

        .status-compensado {
            color: #0c5460;
            background-color: #d1ecf1;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }

        .status-descansado {
            color: #0c5460;
            background-color: #d1ecf1;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .modal-title {
            color: #0E544C;
            font-size: 1.2rem !important;
            font-weight: bold;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            margin-bottom: 15px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .info-group {
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: bold;
            color: #0E544C;
        }

        .info-value {
            margin-left: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #0E544C;
        }

        .form-select,
        .form-textarea,
        .form-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-textarea {
            min-height: 80px;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                margin-left: 0;
                justify-content: flex-start;
            }

            .filter-group {
                width: 100%;
            }

            .header-container {
                flex-direction: row;
                align-items: center;
                gap: 10px;
            }

            .buttons-container {
                position: static;
                transform: none;
                order: 3;
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }

            .logo-container {
                order: 1;
                margin-right: 0;
            }

            .user-info {
                order: 2;
                margin-left: auto;
            }

            .btn-agregar {
                padding: 6px 10px;
                font-size: 13px;
            }
        }

        .btn-contabilidad {
            background-color: #6f42c1;
            /* Color morado */
        }

        .btn-contabilidad:hover {
            background-color: #5a2d9a;
        }

        @media (max-width: 480px) {
            .btn-agregar {
                flex-grow: 1;
                justify-content: center;
                white-space: normal;
                text-align: center;
                padding: 8px 5px;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-end;
            }
        }

        a.btn {
            text-decoration: none;
        }

        /* Estilos para el autocompletado */
        #operarios-sugerencias {
            width: calc(100% - 2px);
            /* Mismo ancho que el input */
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-top: -1px;
            /* Para que se pegue al input */
            position: absolute;
            top: 100%;
            /* Posiciona el dropdown justo debajo del input */
            left: 0;
            z-index: 1000;
            background: white;
            max-height: 200px;
            overflow-y: auto;
            display: none;
        }

        #operarios-sugerencias div {
            padding: 8px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }

        #operarios-sugerencias div:hover {
            background-color: #f5f5f5 !important;
        }

        /* Asegurar que el input tenga un z-index menor */
        .filtro-group input[type="text"] {
            position: relative;
            z-index: 1;
        }

        .status-sin-marcacion {
            color: #721c24;
            background-color: #f8d7da;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }

        /* Estilos para botones de acción inline - AGREGAR AL FINAL DEL CSS EXISTENTE */
        .action-buttons-inline {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        .save-cancel-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #218838 0%, #1fa886 100%);
        }

        .btn-compensado {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
        }

        .btn-compensado:hover {
            background: linear-gradient(135deg, #138496 0%, #1fa886 100%);
        }

        .btn-change {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
        }

        .btn-change:hover {
            background: linear-gradient(135deg, #e0a800 0%, #e68900 100%);
        }

        .btn-edit {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #0069d9 0%, #004085 100%);
        }

        .btn-save {
            background: linear-gradient(135deg, #51B8AC 0%, #0E544C 100%);
            color: white;
        }

        .btn-save:hover {
            background: linear-gradient(135deg, #0E544C 0%, #0a3d3a 100%);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }

        .btn-cancel:hover {
            background: linear-gradient(135deg, #5a6268 0%, #343a40 100%);
        }

        /* Estilos para badges de estado mejorados */
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            text-align: center;
            font-weight: 600;
            display: inline-block;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .status-pendiente {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-pagado {
            background: linear-gradient(135deg, #d4edda 0%, #b8e6c0 100%);
            color: #155724;
            border: 1px solid #b8e6c0;
        }

        .status-descansado {
            background: linear-gradient(135deg, #d1ecf1 0%, #a6e1ec 100%);
            color: #0c5460;
            border: 1px solid #a6e1ec;
        }

        .status-compensado {
            background: linear-gradient(135deg, #d1ecf1 0%, #a6e1ec 100%);
            color: #0c5460;
            border: 1px solid #a6e1ec;
        }

        .status-sin-marcacion,
        .status-sin-marcación {
            background: linear-gradient(135deg, #f8d7da 0%, #f5b7bd 100%);
            color: #721c24;
            border: 1px solid #f5b7bd;
        }

        .status-con-marcacion {
            background: linear-gradient(135deg, #d4edda 0%, #a7f3d0 100%);
            color: #155724;
            border: 1px solid #a7f3d0;
        }

        /* Estilos para edición de observaciones */
        .observaciones-cell {
            max-width: 300px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .observaciones-edit {
            width: 100%;
            padding: 8px;
            border: 2px solid #51B8AC;
            border-radius: 4px;
            font-family: inherit;
            resize: vertical;
            min-height: 60px;
        }

        .text-muted {
            color: #6c757d;
            font-style: italic;
        }

        /* Loading spinner */
        .btn-action.loading {
            pointer-events: none;
            opacity: 0.6;
        }

        .btn-action.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* Estilos para notificaciones */
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {

            .action-buttons-inline,
            .save-cancel-buttons {
                flex-direction: column;
                gap: 4px;
            }

            .btn-action {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, $esAdmin, 'Feriados Trabajados'); ?>

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

                        <?php if ($esAdmin || verificarAccesoCargo([11, 8, 13])): ?>
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

                                <!-- Nuevo botón de exportación para contabilidad -->
                                <a style="display:none;" href="feriados.php?<?= http_build_query([
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
                                    <th>Inicio Contrato</th>
                                    <th>Fecha Feriado</th>
                                    <th>Feriado</th>
                                    <th>Tipo (Departamento)</th>
                                    <th style="display:none;">Horas Trabajadas</th>
                                    <th>Status</th>
                                    <th>Observaciones</th>
                                    <?php if ($esAdmin || verificarAccesoCargo([11, 16, 8, 21])): ?>
                                        <th style="text-align: center; min-width: 180px;">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feriadosTrabajados as $ft): ?>
                                    <tr
                                        id="feriado-row-<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>">
                                        <td><?= htmlspecialchars($ft['nombre_operario']) ?></td>
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
                                            <div class="observaciones-cell"
                                                id="obs-display-<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>">
                                                <?php if ($ft['observaciones']): ?>
                                                    <?= nl2br(htmlspecialchars($ft['observaciones'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin observaciones</span>
                                                <?php endif; ?>
                                            </div>
                                            <textarea
                                                id="obs-edit-<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>"
                                                class="observaciones-edit" style="display: none;"
                                                rows="3"><?= htmlspecialchars($ft['observaciones'] ?? '') ?></textarea>
                                        </td>

                                        <?php if ($esAdmin || verificarAccesoCargo([11, 16, 8, 21])): ?>
                                            <td style="text-align: center;">
                                                <div class="action-buttons-inline"
                                                    id="actions-<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>">
                                                    <?php if ($ft['estado'] === 'Pendiente' || $ft['estado'] === 'Sin marcación' || $ft['estado'] === 'Con Marcación'): ?>
                                                        <!-- Botones para estado Pendiente/Sin marcación -->
                                                        <button type="button" class="btn-action btn-approve"
                                                            onclick="actualizarEstadoFeriado('<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>', 'Pagado', '<?= $ft['cod_operario'] ?>', '<?= $ft['fecha'] ?>')"
                                                            title="Marcar como Pagado">
                                                            <i class="fas fa-dollar-sign"></i>
                                                        </button>
                                                        <button type="button" class="btn-action btn-compensado"
                                                            onclick="actualizarEstadoFeriado('<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>', 'Descansado', '<?= $ft['cod_operario'] ?>', '<?= $ft['fecha'] ?>')"
                                                            title="Marcar como Compensado/Descansado">
                                                            <i class="fas fa-bed"></i>
                                                        </button>
                                                        <button type="button" class="btn-action btn-edit"
                                                            onclick="toggleEditObservacionesFeriado('<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>')"
                                                            title="Editar observaciones">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Botones para estados Pagado/Descansado -->
                                                        <button type="button" class="btn-action btn-change"
                                                            onclick="cambiarEstadoFeriado('<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>', '<?= $ft['estado'] ?>', '<?= $ft['cod_operario'] ?>', '<?= $ft['fecha'] ?>')"
                                                            title="Cambiar estado">
                                                            <i class="fas fa-exchange-alt"></i>
                                                        </button>
                                                        <button type="button" class="btn-action btn-edit"
                                                            onclick="toggleEditObservacionesFeriado('<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>')"
                                                            title="Editar observaciones">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Botones de guardar/cancelar (ocultos por defecto) -->
                                                <div class="save-cancel-buttons"
                                                    id="save-cancel-<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>"
                                                    style="display: none;">
                                                    <button type="button" class="btn-action btn-save"
                                                        onclick="guardarObservacionesFeriado('<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>', '<?= $ft['cod_operario'] ?>', '<?= $ft['fecha'] ?>')"
                                                        title="Guardar">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                    <button type="button" class="btn-action btn-cancel"
                                                        onclick="cancelarEditObservacionesFeriado('<?= $ft['id_aprobacion'] ?? 'temp_' . $ft['cod_operario'] . '_' . $ft['fecha'] ?>')"
                                                        title="Cancelar">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </div>
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
        // Variables para manejar el estado de edición de feriados
        let editandoObservacionesFeriado = {};
        let observacionesOriginalesFeriado = {};

        /**
         * Actualiza el estado de un feriado (Pagado/Descansado)
         */
        function actualizarEstadoFeriado(elementId, nuevoEstado, codOperario, fecha) {
            const confirmMessage = nuevoEstado === 'Pagado'
                ? '¿Está seguro de marcar este feriado como PAGADO? (8 horas a pagar)'
                : '¿Está seguro de marcar este feriado como DESCANSADO/COMPENSADO?';

            if (!confirm(confirmMessage)) {
                return;
            }

            // Para registros sin ID (nuevos), crear uno
            if (elementId.startsWith('temp_')) {
                crearRegistroFeriado(elementId, nuevoEstado, codOperario, fecha);
                return;
            }

            // Para registros existentes, actualizar
            actualizarRegistroFeriado(elementId, nuevoEstado);
        }

        /**
         * Crea un nuevo registro de feriado trabajado
         */
        function crearRegistroFeriado(elementId, estado, codOperario, fecha) {
            const observaciones = document.getElementById(`obs-edit-${elementId}`)?.value || '';

            // Mostrar loading
            const actionsDiv = document.getElementById(`actions-${elementId}`);
            const originalHTML = actionsDiv.innerHTML;
            actionsDiv.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-spinner fa-spin"></i> Creando registro...</div>';

            // Enviar petición AJAX
            fetch('ajax/crear_feriado_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'cod_operario': codOperario,
                    'fecha_feriado': fecha,
                    'estado': estado,
                    'observaciones': observaciones
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Recargar la página para mostrar el nuevo registro con ID real
                        mostrarNotificacion('success', data.message);
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        // Restaurar HTML original en caso de error
                        actionsDiv.innerHTML = originalHTML;
                        mostrarNotificacion('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    actionsDiv.innerHTML = originalHTML;
                    mostrarNotificacion('error', 'Error al crear el registro del feriado');
                });
        }

        /**
         * Cambia el estado de un feriado ya procesado
         */
        function cambiarEstadoFeriado(id, estadoActual, codOperario, fecha) {
            const nuevoEstado = estadoActual === 'Pagado' ? 'Descansado' : 'Pagado';
            actualizarEstadoFeriado(id, nuevoEstado, codOperario, fecha);
        }

        /**
         * Activa el modo de edición de observaciones para feriados
         */
        function toggleEditObservacionesFeriado(id) {
            const displayDiv = document.getElementById(`obs-display-${id}`);
            const editTextarea = document.getElementById(`obs-edit-${id}`);
            const actionsDiv = document.getElementById(`actions-${id}`);
            const saveCancelDiv = document.getElementById(`save-cancel-${id}`);

            // Guardar valor original
            if (!editandoObservacionesFeriado[id]) {
                observacionesOriginalesFeriado[id] = editTextarea ? editTextarea.value : '';
            }

            // Alternar visibilidad
            if (displayDiv) displayDiv.style.display = 'none';
            if (editTextarea) editTextarea.style.display = 'block';
            if (actionsDiv) actionsDiv.style.display = 'none';
            if (saveCancelDiv) saveCancelDiv.style.display = 'flex';

            // Marcar como editando
            editandoObservacionesFeriado[id] = true;

            // Focus en el textarea si existe
            if (editTextarea) {
                editTextarea.focus();
            }
        }

        /**
         * Guarda las observaciones editadas para feriados
         */
        function guardarObservacionesFeriado(id, codOperario, fecha) {
            const editTextarea = document.getElementById(`obs-edit-${id}`);
            const nuevasObservaciones = editTextarea ? editTextarea.value.trim() : '';

            // Obtener estado actual
            const badge = document.getElementById(`status-badge-${id}`);
            const estadoActual = badge ? badge.textContent.trim() : 'Pendiente';

            // Mostrar loading
            const saveCancelDiv = document.getElementById(`save-cancel-${id}`);
            const originalHTML = saveCancelDiv ? saveCancelDiv.innerHTML : '';
            if (saveCancelDiv) saveCancelDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            // Para registros sin ID (nuevos), crear uno
            if (id.startsWith('temp_')) {
                crearRegistroFeriado(id, estadoActual, codOperario, fecha);
                return;
            }

            // Para registros existentes, actualizar observaciones
            fetch('ajax/actualizar_feriado_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'id': id,
                    'estado': estadoActual,
                    'observaciones': nuevasObservaciones
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Actualizar display de observaciones
                        const displayDiv = document.getElementById(`obs-display-${id}`);
                        if (displayDiv) {
                            if (nuevasObservaciones) {
                                displayDiv.innerHTML = nuevasObservaciones.replace(/\n/g, '<br>');
                            } else {
                                displayDiv.innerHTML = '<span class="text-muted">Sin observaciones</span>';
                            }
                        }

                        // Salir del modo edición
                        finalizarEdicionObservacionesFeriado(id);

                        mostrarNotificacion('success', 'Observaciones actualizadas correctamente');
                    } else {
                        if (saveCancelDiv) saveCancelDiv.innerHTML = originalHTML;
                        mostrarNotificacion('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (saveCancelDiv) saveCancelDiv.innerHTML = originalHTML;
                    mostrarNotificacion('error', 'Error al guardar las observaciones');
                });
        }

        /**
         * Cancela la edición de observaciones para feriados
         */
        function cancelarEditObservacionesFeriado(id) {
            const editTextarea = document.getElementById(`obs-edit-${id}`);

            // Restaurar valor original
            if (observacionesOriginalesFeriado[id] !== undefined && editTextarea) {
                editTextarea.value = observacionesOriginalesFeriado[id];
            }

            finalizarEdicionObservacionesFeriado(id);
        }

        /**
         * Finaliza el modo de edición de observaciones para feriados
         */
        function finalizarEdicionObservacionesFeriado(id) {
            const displayDiv = document.getElementById(`obs-display-${id}`);
            const editTextarea = document.getElementById(`obs-edit-${id}`);
            const actionsDiv = document.getElementById(`actions-${id}`);
            const saveCancelDiv = document.getElementById(`save-cancel-${id}`);

            // Alternar visibilidad
            if (displayDiv) displayDiv.style.display = 'block';
            if (editTextarea) editTextarea.style.display = 'none';
            if (actionsDiv) actionsDiv.style.display = 'flex';
            if (saveCancelDiv) saveCancelDiv.style.display = 'none';

            // Limpiar estado
            delete editandoObservacionesFeriado[id];
            delete observacionesOriginalesFeriado[id];
        }

        /**
         * Actualiza los botones de acción según el nuevo estado del feriado
         */
        function actualizarBotonesAccionFeriado(id, nuevoEstado) {
            const actionsDiv = document.getElementById(`actions-${id}`);

            if (!actionsDiv) return;

            // Extraer código de operario y fecha del ID si es temporal
            let codOperario = '';
            let fecha = '';

            if (id.startsWith('temp_')) {
                const parts = id.split('_');
                if (parts.length >= 3) {
                    codOperario = parts[1];
                    fecha = parts[2];
                }
            }

            if (nuevoEstado === 'Pendiente' || nuevoEstado === 'Sin marcación' || nuevoEstado === 'Con Marcación') {
                actionsDiv.innerHTML = `
                    <button type="button" class="btn-action btn-approve" 
                            onclick="actualizarEstadoFeriado('${id}', 'Pagado', '${codOperario}', '${fecha}')" title="Marcar como Pagado">
                        <i class="fas fa-dollar-sign"></i>
                    </button>
                    <button type="button" class="btn-action btn-compensado" 
                            onclick="actualizarEstadoFeriado('${id}', 'Descansado', '${codOperario}', '${fecha}')" title="Marcar como Compensado/Descansado">
                        <i class="fas fa-bed"></i>
                    </button>
                    <button type="button" class="btn-action btn-edit" 
                            onclick="toggleEditObservacionesFeriado('${id}')" title="Editar observaciones">
                        <i class="fas fa-edit"></i>
                    </button>
                `;
            } else {
                actionsDiv.innerHTML = `
                    <button type="button" class="btn-action btn-change" 
                            onclick="cambiarEstadoFeriado('${id}', '${nuevoEstado}', '${codOperario}', '${fecha}')" title="Cambiar estado">
                        <i class="fas fa-exchange-alt"></i>
                    </button>
                    <button type="button" class="btn-action btn-edit" 
                            onclick="toggleEditObservacionesFeriado('${id}')" title="Editar observaciones">
                        <i class="fas fa-edit"></i>
                    </button>
                `;
            }
        }

        /**
         * Muestra notificaciones toast (reutilizable)
         */
        function mostrarNotificacion(tipo, mensaje) {
            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.className = `notification notification-${tipo}`;
            notification.innerHTML = `
                <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${mensaje}</span>
            `;

            // Estilos inline para la notificación
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: bold;
                display: flex;
                align-items: center;
                gap: 10px;
                z-index: 10000;
                animation: slideIn 0.3s ease;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                background: ${tipo === 'success' ? 'linear-gradient(135deg, #28a745 0%, #20c997 100%)' : 'linear-gradient(135deg, #dc3545 0%, #e83e8c 100%)'};
            `;

            document.body.appendChild(notification);

            // Eliminar después de 3 segundos
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        /**
         * Actualiza un registro existente de feriado
         */
        function actualizarRegistroFeriado(id, nuevoEstado) {
            const observaciones = document.getElementById(`obs-edit-${id}`)?.value || '';

            // Mostrar loading
            const actionsDiv = document.getElementById(`actions-${id}`);
            const originalHTML = actionsDiv.innerHTML;
            actionsDiv.innerHTML = '<div style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-spinner fa-spin"></i> Procesando...</div>';

            // Enviar petición AJAX
            fetch('ajax/actualizar_feriado_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'id': id,
                    'estado': nuevoEstado,
                    'observaciones': observaciones
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Actualizar badge de estado
                        const badge = document.getElementById(`status-badge-${id}`);
                        badge.textContent = nuevoEstado;
                        badge.className = `status-badge status-${nuevoEstado.toLowerCase().replace(' ', '-')}`;

                        // Actualizar botones de acción
                        actualizarBotonesAccionFeriado(id, nuevoEstado);

                        // Mostrar mensaje de éxito
                        mostrarNotificacion('success', data.message);
                    } else {
                        // Restaurar HTML original en caso de error
                        actionsDiv.innerHTML = originalHTML;
                        mostrarNotificacion('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    actionsDiv.innerHTML = originalHTML;
                    mostrarNotificacion('error', 'Error al actualizar el estado del feriado');
                });
        }

        // Datos de operarios para el autocompletado
        const operariosData = [
            { id: 0, nombre: 'Todos los colaboradores' },
            <?php foreach ($operarios as $op): ?>
                                                { id: <?= $op['CodOperario'] ?>, nombre: '<?= addslashes($op['nombre_completo']) ?>' },
            <?php endforeach; ?>
        ];

        // Función para buscar operarios
        function buscarOperarios(texto) {
            if (!texto) {
                return operariosData;
            }
            return operariosData.filter(op =>
                op.nombre.toLowerCase().includes(texto.toLowerCase())
            );
        }

        // Manejar el input de operario
        const operarioInput = document.getElementById('operario');
        const operarioIdInput = document.getElementById('operario_id');
        const sugerenciasDiv = document.getElementById('operarios-sugerencias');

        // Modificar el evento input del campo operario
        operarioInput.addEventListener('input', function () {
            const texto = this.value.trim();

            // Si el campo está vacío, resetear a "todos"
            if (texto === '') {
                operarioIdInput.value = '0';
                sugerenciasDiv.style.display = 'none';
                return;
            }

            const resultados = buscarOperarios(texto);

            sugerenciasDiv.innerHTML = '';

            if (resultados.length > 0) {
                resultados.forEach(op => {
                    const div = document.createElement('div');
                    div.textContent = op.nombre;
                    div.style.padding = '8px';
                    div.style.cursor = 'pointer';
                    div.addEventListener('click', function () {
                        operarioInput.value = op.nombre;
                        operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                    });
                    div.addEventListener('mouseover', function () {
                        this.style.backgroundColor = '#f5f5f5';
                    });
                    div.addEventListener('mouseout', function () {
                        this.style.backgroundColor = 'white';
                    });
                    sugerenciasDiv.appendChild(div);
                });
                sugerenciasDiv.style.display = 'block';
            } else {
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Ocultar sugerencias al hacer clic fuera
        document.addEventListener('click', function (e) {
            if (e.target !== operarioInput) {
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Manejar tecla Enter en el input
        operarioInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const texto = this.value.trim();
                const resultados = buscarOperarios(texto);
                if (resultados.length > 0) {
                    this.value = resultados[0].nombre;
                    operarioIdInput.value = resultados[0].id;
                }
                sugerenciasDiv.style.display = 'none';
            }
        });

        // Actualizar filtros y recargar la página
        function actualizarFiltros() {
            const sucursal = document.getElementById('sucursal').value;
            const operario = document.getElementById('operario_id').value;
            const desde = document.getElementById('desde').value;
            const hasta = document.getElementById('hasta').value;

            // Validar fechas
            if (!desde || !hasta) {
                alert('Por favor seleccione ambas fechas');
                return;
            }

            // Validar que la fecha desde no sea mayor que hasta
            if (new Date(desde) > new Date(hasta)) {
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }

            // Construir URL con parámetros
            const params = new URLSearchParams();
            if (sucursal) params.append('sucursal', sucursal);
            if (operario && operario != '0') params.append('operario', operario);
            params.append('desde', desde);
            params.append('hasta', hasta);

            window.location.href = 'feriados.php?' + params.toString();
        }

        // Mostrar modal de aprobación
        function mostrarModalAprobacion(
            idMarcacion, nombre, sucursal, fecha, horaEntrada, horaSalida,
            horasTrabajadas, feriadoNombre, feriadoTipo, departamentoNombre,
            estado, observaciones, codOperario
        ) {
            // Manejar idMarcacion null o undefined
            if (idMarcacion === null || idMarcacion === 'null' || idMarcacion === '' || idMarcacion === undefined) {
                document.getElementById('id_marcacion').value = '';
            } else {
                document.getElementById('id_marcacion').value = idMarcacion;
            }

            document.getElementById('cod_operario').value = codOperario;
            document.getElementById('horas_trabajadas').value = horasTrabajadas;

            // Formatear fecha localmente
            function formatearFechaLocal(fechaStr) {
                try {
                    const fecha = new Date(fechaStr + 'T00:00:00');
                    const opciones = { day: '2-digit', month: 'short', year: '2-digit' };
                    return fecha.toLocaleDateString('es-ES', opciones);
                } catch (e) {
                    return fechaStr; // Si hay error, devolver la fecha original
                }
            }

            // Establecer valores en el modal
            document.getElementById('modal-nombre').textContent = nombre;
            document.getElementById('modal-sucursal').textContent = sucursal;
            document.getElementById('modal-fecha').textContent = formatearFechaLocal(fecha);
            document.getElementById('modal-feriado').textContent = feriadoNombre;
            document.getElementById('modal-tipo').textContent = feriadoTipo +
                (feriadoTipo === 'Departamental' ? ` (${departamentoNombre})` : '');

            // Manejar horas (pueden estar vacías)
            document.getElementById('modal-hora-entrada').textContent =
                (horaEntrada && horaEntrada.trim() !== '') ? horaEntrada : 'No registrada';
            document.getElementById('modal-hora-salida').textContent =
                (horaSalida && horaSalida.trim() !== '') ? horaSalida : 'No registrada';

            document.getElementById('modal-horas-trabajadas').textContent = horasTrabajadas.toFixed(2);
            document.getElementById('estado').value = estado;
            document.getElementById('observaciones').value = observaciones || '';

            // IMPORTANTE: Establecer la fecha del feriado en un campo oculto
            document.getElementById('fecha_feriado').value = fecha;

            // Guardar filtros actuales
            document.querySelector('input[name="sucursal_filtro"]').value =
                document.getElementById('sucursal').value;
            document.querySelector('input[name="desde_filtro"]').value =
                document.getElementById('desde').value;
            document.querySelector('input[name="hasta_filtro"]').value =
                document.getElementById('hasta').value;
            document.querySelector('input[name="operario_filtro"]').value =
                document.getElementById('operario_id').value;

            // Mostrar el modal
            document.getElementById('modalAprobacion').style.display = 'flex';
        }

        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modalAprobacion').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera del contenido
        window.addEventListener('click', function (event) {
            const modal = document.getElementById('modalAprobacion');
            if (event.target === modal) {
                cerrarModal();
            }
        });
    </script>
</body>

</html>