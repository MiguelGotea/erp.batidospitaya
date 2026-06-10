<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';


$usuario = obtenerUsuarioActual();
$cargoUsuarioId = $usuario['CodNivelesCargos'];
// Verificar acceso al módulo Operaciones (Codigo 11 para Jefe de Operaciones)
verificarAccesoCargo([8, 16, 49]);

// Verificar acceso al módulo
if (!verificarAccesoCargo([8, 16, 49])) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);

// Al inicio del archivo, después de los includes pero antes de cualquier HTML
if (isset($_GET['action']) && $_GET['action'] == 'obtener_operarios' && isset($_GET['sucursal'])) {
    header('Content-Type: application/json');
    $operarios = obtenerOperariosActivos($_GET['sucursal']);
    echo json_encode($operarios);
    exit();
}

// Nuevo endpoint para búsqueda de operarios
if (isset($_GET['action']) && $_GET['action'] == 'buscar_operarios' && isset($_GET['query'])) {
    header('Content-Type: application/json');
    $operarios = buscarOperarios($_GET['query']);
    echo json_encode($operarios);
    exit();
}

$esOperaciones = verificarAccesoCargo([11, 49]); // Jefe de Operaciones

/**
 * Obtiene la sucursal principal asignada a un operario
 */
function obtenerSucursalPrincipalOperario($codOperario) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT Sucursal 
        FROM AsignacionNivelesCargos 
        WHERE CodOperario = ? 
        AND (Fin IS NULL OR Fin >= CURDATE())
        ORDER BY Fecha DESC
        LIMIT 1
    ");
    $stmt->execute([$codOperario]);
    $result = $stmt->fetch();
    
    return $result['Sucursal'] ?? null;
}

// Obtener todas las sucursales (el jefe de operaciones puede ver todas)
$sucursales = obtenerTodasSucursales();

// En la sección de procesamiento de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_viatico'])) {
        procesarViatico();
    } elseif (isset($_POST['eliminar_viatico'])) {
        eliminarViatico();
    } elseif (isset($_POST['guardar_nocturno'])) {
        // Procesar guardado de viático automático (nocturno o diurno)
        $codOperario = $_POST['cod_operario_nocturno'];
        $fecha = $_POST['fecha_nocturno'];
        $sucursalCodigo = $_POST['sucursal_codigo_nocturno'];
        $cantidad = $_POST['cantidad_nocturno'];
        $observaciones = $_POST['observaciones_nocturno'];
        $fechaPago = $_POST['fecha_pago'] ?? null;
        $tipo = $_POST['tipo_viatico'] ?? 'Nocturno'; // Nuevo campo para distinguir tipo
        
        if (guardarViaticoAutomatico($codOperario, $fecha, $sucursalCodigo, $cantidad, $observaciones, $fechaPago, $tipo)) {
            $_SESSION['exito'] = 'Viático guardado correctamente';
        } else {
            $_SESSION['error'] = 'Error al guardar el viático o ya existe';
        }
        
        // Redirigir manteniendo los filtros
        header('Location: viaticos.php?' . http_build_query([
            'sucursal' => $_GET['sucursal'] ?? '',
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? '',
            'operario' => $_GET['operario'] ?? ''
        ]));
        exit();
    } elseif (isset($_POST['guardar_fecha_pago'])) {
            // Procesar actualización de fecha de pago
            $idViatico = $_POST['id_viatico'];
            $fechaPago = $_POST['fecha_pago'] ?? null;
            
            try {
                $stmt = $conn->prepare("
                    UPDATE viaticos 
                    SET fecha_pago = ?, 
                        actualizado_por = ?, 
                        fecha_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $fechaPago, 
                    $_SESSION['usuario_id'], 
                    $idViatico
                ]);
                
                $_SESSION['exito'] = 'Fecha de pago actualizada correctamente';
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error al actualizar la fecha de pago: ' . $e->getMessage();
            }
            
            // Redirigir manteniendo los filtros
            header('Location: viaticos.php?' . http_build_query([
                'sucursal' => $_GET['sucursal'] ?? '',
                'desde' => $_GET['desde'] ?? '',
                'hasta' => $_GET['hasta'] ?? '',
                'operario' => $_GET['operario'] ?? ''
            ]));
            exit();
    } elseif (isset($_POST['guardar_fecha_pago_masivo'])) {
        // Procesar GUARDADO MASIVO de viáticos nocturnos con fecha de pago
        $fechaPago = $_POST['fecha_pago_masivo'] ?? null;
        $sucursalCodigo = $_POST['sucursal_masivo'] ?? null;
        $fechaDesde = $_POST['desde_masivo'] ?? null;
        $fechaHasta = $_POST['hasta_masivo'] ?? null;
        
        if ($fechaPago && $fechaDesde && $fechaHasta) {
            try {
                // Primero obtener todos los viáticos nocturnos automáticos en el rango
                $viaticosNocturnos = obtenerViaticosNocturnosAutomaticos($sucursalCodigo, $fechaDesde, $fechaHasta, null);
                
                $filasAfectadas = 0;
                
                foreach ($viaticosNocturnos as $viatico) {
                    // Verificar si ya existe en la BD
                    $stmt = $conn->prepare("
                        SELECT id FROM viaticos 
                        WHERE cod_operario = ? 
                        AND fecha = ? 
                        AND tipo = 'Nocturno'
                        AND sucursal_codigo = ?
                    ");
                    $stmt->execute([$viatico['cod_operario'], $viatico['fecha'], $viatico['sucursal_codigo']]);
                    $existe = $stmt->fetch();
                    
                    if ($existe) {
                        // Actualizar existente
                        $stmt = $conn->prepare("
                            UPDATE viaticos 
                            SET fecha_pago = ?, 
                                actualizado_por = ?, 
                                fecha_actualizacion = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$fechaPago, $_SESSION['usuario_id'], $existe['id']]);
                    } else {
                        // Crear nuevo viático nocturno
                        $stmt = $conn->prepare("
                            INSERT INTO viaticos (
                                cod_operario, fecha, tipo, cantidad, observaciones, 
                                creado_por, actualizado_por, sucursal_codigo, fecha_pago
                            ) VALUES (?, ?, 'Nocturno', ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $viatico['cod_operario'], 
                            $viatico['fecha'], 
                            $viatico['cantidad'], 
                            $viatico['observaciones'], 
                            $_SESSION['usuario_id'], 
                            $_SESSION['usuario_id'], 
                            $viatico['sucursal_codigo'], 
                            $fechaPago
                        ]);
                    }
                    
                    $filasAfectadas++;
                }
                
                $_SESSION['exito'] = "Viáticos nocturnos guardados/actualizados: $filasAfectadas";
                
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error al procesar viáticos nocturnos: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Por favor complete todos los campos requeridos';
        }
        
        // Redirigir manteniendo los filtros
        header('Location: viaticos.php?' . http_build_query([
            'sucursal' => $_GET['sucursal'] ?? '',
            'desde' => $_GET['desde'] ?? '',
            'hasta' => $_GET['hasta'] ?? '',
            'operario' => $_GET['operario'] ?? ''
        ]));
        exit();
    } elseif (isset($_POST['guardar_y_exportar_nocturnos'])) {
        // Procesar GUARDADO MASIVO de viáticos nocturnos con fecha_pago NULL
        $sucursalCodigo = $_POST['sucursal_masivo'] ?? null;
        $fechaDesde = $_POST['desde_masivo'] ?? null;
        $fechaHasta = $_POST['hasta_masivo'] ?? null;
        $operarioParam = $_POST['operario_masivo'] ?? null;
        
        if ($fechaDesde && $fechaHasta) {
            try {
                // Primero obtener todos los viáticos nocturnos automáticos en el rango
                $viaticosNocturnos = obtenerViaticosNocturnosAutomaticos($sucursalCodigo, $fechaDesde, $fechaHasta, $operarioParam);
                
                $filasAfectadas = 0;
                
                foreach ($viaticosNocturnos as $viatico) {
                    // CONSULTA DIRECTA PARA OBTENER CÓDIGO DE CONTRATO
                    $codContrato = null;
                    try {
                        $stmtContrato = $conn->prepare("
                            SELECT CodContrato 
                            FROM Contratos 
                            WHERE cod_operario = ? 
                            ORDER BY inicio_contrato DESC, CodContrato DESC 
                            LIMIT 1
                        ");
                        $stmtContrato->execute([$viatico['cod_operario']]);
                        $resultContrato = $stmtContrato->fetch();
                        
                        if ($resultContrato && !empty($resultContrato['CodContrato'])) {
                            $codContrato = $resultContrato['CodContrato'];
                        }
                    } catch (Exception $e) {
                        error_log("Error en consulta de contrato masivo: " . $e->getMessage());
                    }
                    
                    // Verificar si ya existe en la BD
                    $stmt = $conn->prepare("
                        SELECT id FROM viaticos 
                        WHERE cod_operario = ? 
                        AND fecha = ? 
                        AND tipo = ?
                        AND sucursal_codigo = ?
                    ");
                    $stmt->execute([$viatico['cod_operario'], $viatico['fecha'], $viatico['tipo'], $viatico['sucursal_codigo']]);
                    $existe = $stmt->fetch();
                    
                    if ($existe) {
                        // Actualizar existente (solo si no tiene fecha_pago)
                        $stmt = $conn->prepare("
                            UPDATE viaticos 
                            SET cantidad = ?, observaciones = ?, 
                                actualizado_por = ?, fecha_actualizacion = NOW(),
                                cod_contrato = ?
                            WHERE id = ? AND fecha_pago IS NULL
                        ");
                        $stmt->execute([
                            $viatico['cantidad'], 
                            $viatico['observaciones'], 
                            $_SESSION['usuario_id'], 
                            $codContrato,
                            $existe['id']
                        ]);
                    } else {
                        // Crear nuevo viático con fecha_pago NULL
                        $stmt = $conn->prepare("
                            INSERT INTO viaticos (
                                cod_operario, fecha, tipo, cantidad, observaciones, 
                                creado_por, actualizado_por, sucursal_codigo, fecha_pago, cod_contrato
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)
                        ");
                        $stmt->execute([
                            $viatico['cod_operario'], 
                            $viatico['fecha'], 
                            $viatico['tipo'], 
                            $viatico['cantidad'], 
                            $viatico['observaciones'], 
                            $_SESSION['usuario_id'], 
                            $_SESSION['usuario_id'], 
                            $viatico['sucursal_codigo'],
                            $codContrato
                        ]);
                    }
                    
                    if ($stmt->rowCount() > 0) {
                        $filasAfectadas++;
                    }
                }
                
                // Ahora exportar a Excel TODOS los viáticos nocturnos (no solo los recién guardados)
                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment;filename="viaticos_nocturnos_guardados_' . $fechaDesde . '_a_' . $fechaHasta . '.xls"');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // Iniciar salida con BOM para UTF-8 y estructura HTML correcta
                echo pack("CCC", 0xef, 0xbb, 0xbf); // BOM para UTF-8
                echo '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
                
                // Obtener TODOS los viáticos nocturnos de la BD para el rango, no solo los recién guardados
                $viaticosNocturnosBD = obtenerViaticosNocturnosBDParaExport($sucursalCodigo, $fechaDesde, $fechaHasta, $operarioParam);
                
                // Crear contenido Excel con el nuevo formato
                echo '<table border="1">';
                echo '<tr>';
                echo '<th>Codigo</th>';
                echo '<th>Persona</th>';
                // echo '<th>Codigo</th>';
                echo '<th>Sucursal</th>';
                echo '<th>Fecha de Pago</th>';
                // echo '<th>Turnos 1er quincena</th>';
                // echo '<th>Turnos 2da quincena</th>';
                echo '<th>Total Turnos</th>';
                echo '<th>Total a recibir</th>';
                echo '</tr>';
                
                $totalesOperarios = [];
                foreach ($viaticosNocturnosBD as $viatico) {
                    $codOperario = $viatico['cod_operario'];
                    if (!isset($totalesOperarios[$codOperario])) {
                        $totalesOperarios[$codOperario] = [
                            'nombre' => implode(' ', array_filter([
                                $viatico['Nombre'],
                                $viatico['Nombre2'] ?? '',
                                $viatico['Apellido'],
                                $viatico['Apellido2'] ?? ''
                            ], fn($v) => trim($v) !== '')),
                            'sucursal' => $viatico['sucursal_nombre'],
                            'total_turnos' => 0,
                            'total_monto' => 0,
                            'primer_quincena' => 0,
                            'segunda_quincena' => 0,
                            'cod_contrato' => $viatico['cod_contrato'] ?? '' // ← CAPTURAR EL CÓDIGO DE CONTRATO
                        ];
                    }
                    
                    // Solo contar viáticos que están dentro del rango de fechas
                    $quincena = determinarQuincenaPorDiaMesEnRango($viatico['fecha'], $fechaDesde, $fechaHasta);
                    
                    if ($quincena !== 'fuera_rango') {
                        $totalesOperarios[$codOperario]['total_turnos']++;
                        $totalesOperarios[$codOperario]['total_monto'] += $viatico['cantidad'];
                        
                        if ($quincena === 'primera') {
                            $totalesOperarios[$codOperario]['primer_quincena']++;
                        } else {
                            $totalesOperarios[$codOperario]['segunda_quincena']++;
                        }
                    }
                }
                
                // Mostrar una fila por operario
                foreach ($totalesOperarios as $codOperario => $datos) {
                    // Crear el valor combinado: código de contrato + nombre
                    $personaCompleta = ($datos['cod_contrato'] ?? '') . ' ' . htmlspecialchars($datos['nombre'], ENT_QUOTES, 'UTF-8');
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($datos['cod_contrato']) . '</td>'; // ← USAR EL CÓDIGO CAPTURADO
                    echo '<td>' . $personaCompleta . '</td>'; // Código contrato + Nombre completo
                    // echo '<td>' . htmlspecialchars($codOperario) . '</td>';
                    echo '<td>' . htmlspecialchars($datos['sucursal']) . '</td>';
                    echo '<td></td>'; // Fecha de Pago vacía
                    // echo '<td>' . $datos['primer_quincena'] . '</td>';
                    // echo '<td>' . $datos['segunda_quincena'] . '</td>';
                    echo '<td>' . $datos['total_turnos'] . '</td>';
                    echo '<td>' . number_format($datos['total_monto'], 2) . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
                echo '</body></html>';
                exit();
                
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Error al procesar viáticos nocturnos: ' . $e->getMessage();
                header('Location: viaticos.php?' . http_build_query([
                    'sucursal' => $_GET['sucursal'] ?? '',
                    'desde' => $_GET['desde'] ?? '',
                    'hasta' => $_GET['hasta'] ?? '',
                    'operario' => $_GET['operario'] ?? ''
                ]));
                exit();
            }
        } else {
            $_SESSION['error'] = 'Por favor complete todos los campos requeridos';
            header('Location: viaticos.php?' . http_build_query([
                'sucursal' => $_GET['sucursal'] ?? '',
                'desde' => $_GET['desde'] ?? '',
                'hasta' => $_GET['hasta'] ?? '',
                'operario' => $_GET['operario'] ?? ''
            ]));
            exit();
        }
    }
}

// Establecer rango del mes actual por defecto
$hoy = new DateTime();
$primerDiaMes = $hoy->format('Y-m-01');
$ultimoDiaMes = $hoy->format('Y-m-t');

// Obtener datos para los filtros
$sucursalSeleccionada = $_GET['sucursal'] ?? null;
$fechaDesde = $_GET['desde'] ?? $primerDiaMes;
$fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;
$operarioSeleccionado = $_GET['operario'] ?? null;

// Obtener viáticos si hay fechas seleccionadas
$viaticos = [];
if ($fechaDesde && $fechaHasta) {
    // Para el jefe de operaciones, si no hay sucursal seleccionada, pasamos null
    $sucursalParam = ($esOperaciones && empty($sucursalSeleccionada)) ? null : $sucursalSeleccionada;
    $viaticos = obtenerViaticos($sucursalParam, $fechaDesde, $fechaHasta, $operarioSeleccionado);
}

// Obtener operarios activos para el formulario
$operarios = [];
if ($sucursalSeleccionada || ($esOperaciones && empty($sucursalSeleccionada))) {
    $sucursalParam = $sucursalSeleccionada ?: null; // Si es "Todas las sucursales", pasa null
    $operarios = obtenerOperariosActivos($sucursalParam);
}

// Obtener todos los operarios para el autocompletado
$todosOperarios = obtenerTodosOperarios();

// Funciones específicas para viáticos
function obtenerViaticos($codSucursal, $fechaDesde, $fechaHasta, $codOperario = null) {
    global $conn, $esOperaciones;
    
    try {
        // Obtener viáticos manuales (Alimentación y Transporte)
        $sql = "
            SELECT v.id, v.cod_operario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2, v.fecha, 
                   v.tipo, v.cantidad, v.observaciones, 'Manual' as origen,
                   s.nombre as sucursal_nombre, s.codigo as sucursal_codigo,
                   v.creado_por, v.fecha_creacion, v.actualizado_por, v.fecha_actualizacion,
                   v.fecha_pago, v.cod_contrato,  -- NUEVA COLUMNA
                   creador.Nombre as creador_nombre, creador.Apellido as creador_apellido,
                   actualizador.Nombre as actualizador_nombre, actualizador.Apellido as actualizador_apellido
            FROM viaticos v
            JOIN Operarios o ON v.cod_operario = o.CodOperario
            JOIN sucursales s ON v.sucursal_codigo = s.codigo
            JOIN Operarios creador ON v.creado_por = creador.CodOperario
            LEFT JOIN Operarios actualizador ON v.actualizado_por = actualizador.CodOperario
            WHERE v.fecha BETWEEN ? AND ?
            AND v.tipo IN ('Alimentación', 'Transporte')
        ";
        
        $params = [$fechaDesde, $fechaHasta];
        
        if (!empty($codSucursal)) {
            $sql .= " AND v.sucursal_codigo = ?";
            $params[] = $codSucursal;
        }
        
        if (!empty($codOperario)) {
            $sql .= " AND v.cod_operario = ?";
            $params[] = $codOperario;
        }
        
        $sql .= " ORDER BY v.fecha DESC, o.Nombre, o.Apellido";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $viaticosManuales = $stmt->fetchAll();
        
        // Obtener viáticos nocturnos automáticos (solo de sucursales permitidas)
        $viaticosNocturnos = obtenerViaticosNocturnosAutomaticos($codSucursal, $fechaDesde, $fechaHasta, $codOperario);
        
        // Combinar ambos resultados
        return array_merge($viaticosManuales, $viaticosNocturnos);
        
    } catch (PDOException $e) {
        error_log("Error al obtener viáticos: " . $e->getMessage());
        return [];
    }
}

function obtenerViaticosNocturnosAutomaticos($codSucursal, $fechaDesde, $fechaHasta, $codOperario = null) {
    global $conn;
    
    try {
        $sql = "
            SELECT 
                v.id as id,
                m.CodOperario as cod_operario,
                o.Nombre,
                o.Nombre2,
                o.Apellido,
                o.Apellido2,
                m.fecha,
                CASE 
                    WHEN (m.hora_ingreso BETWEEN '05:00:00' AND '05:40:00' OR m.hora_salida BETWEEN '05:00:00' AND '05:40:00')
                    AND s.codigo = 19 THEN 'Diurno'
                    ELSE 'Nocturno'
                END as tipo, 
                CASE 
                    WHEN (m.hora_ingreso BETWEEN '05:00:00' AND '05:40:00' OR m.hora_salida BETWEEN '05:00:00' AND '05:40:00')
                    AND s.codigo = 19 THEN d.viatico_diurno
                    ELSE d.viatico_nocturno
                END as cantidad,
                CONCAT(
                    'Turno marcado<div class=\"hora-viatico\">',
                    CASE 
                        WHEN m.hora_ingreso IS NOT NULL AND m.hora_salida IS NOT NULL THEN
                            CONCAT(TIME_FORMAT(m.hora_ingreso, '%h:%i %p'), ' - ', TIME_FORMAT(m.hora_salida, '%h:%i %p'))
                        WHEN m.hora_ingreso IS NOT NULL THEN
                            CONCAT(TIME_FORMAT(m.hora_ingreso, '%h:%i %p'), ' - Sin salida')
                        WHEN m.hora_salida IS NOT NULL THEN
                            CONCAT('Sin entrada - ', TIME_FORMAT(m.hora_salida, '%h:%i %p'))
                        ELSE 'Sin marcaciones'
                    END,
                    '</div>'
                ) as observaciones,
                CASE WHEN v.id IS NULL THEN 'Automático' ELSE 'Manual' END as origen,
                s.nombre as sucursal_nombre, 
                s.codigo as sucursal_codigo,
                d.nombre as departamento_nombre,
                d.codigo as cod_departamento,
                v.creado_por,
                v.fecha_creacion,
                v.actualizado_por,
                v.fecha_actualizacion,
                v.fecha_pago,
                v.cod_contrato
            FROM marcaciones m
            JOIN Operarios o ON m.CodOperario = o.CodOperario
            JOIN sucursales s ON m.sucursal_codigo = s.codigo
            JOIN departamentos d ON s.cod_departamento = d.codigo
            LEFT JOIN viaticos v ON m.CodOperario = v.cod_operario 
                AND m.fecha = v.fecha 
                AND v.tipo IN ('Nocturno', 'Diurno')
                AND m.sucursal_codigo = v.sucursal_codigo
            WHERE m.fecha BETWEEN ? AND ?
            AND (
                -- Condiciones para viáticos nocturnos (horario después de las 20:00)
                (
                    d.viatico_nocturno IS NOT NULL
                    AND (
                        (m.hora_salida >= '20:00:00' AND m.hora_salida IS NOT NULL) OR
                        (m.hora_ingreso >= '20:00:00' AND m.hora_ingreso IS NOT NULL) OR
                        (m.hora_ingreso IS NULL AND m.hora_salida IS NOT NULL AND m.hora_salida >= '20:00:00') OR
                        (m.hora_salida IS NULL AND m.hora_ingreso IS NOT NULL AND m.hora_ingreso >= '20:00:00')
                    )
                )
                OR 
                -- Condiciones para viáticos diurnos (sucursal 19, horario 5:00-5:40 AM)
                (
                    s.codigo = 19 
                    AND d.viatico_diurno IS NOT NULL
                    AND (
                        (m.hora_ingreso BETWEEN '05:00:00' AND '05:40:00') OR
                        (m.hora_salida BETWEEN '05:00:00' AND '05:40:00')
                    )
                )
            )
            AND (m.hora_ingreso IS NOT NULL OR m.hora_salida IS NOT NULL)
            -- Excluir operarios cuya sucursal principal sea 6 o 18
            AND m.CodOperario NOT IN (
                SELECT DISTINCT anc_ex.CodOperario
                FROM AsignacionNivelesCargos anc_ex
                WHERE anc_ex.Sucursal IN (6, 18)
                AND (anc_ex.Fin IS NULL OR anc_ex.Fin >= ?)
            )
        ";
        
        $params = [$fechaDesde, $fechaHasta, $fechaDesde];
        
        if (!empty($codSucursal)) {
            $sql .= " AND m.sucursal_codigo = ?";
            $params[] = $codSucursal;
        }
        
        if (!empty($codOperario)) {
            $sql .= " AND m.CodOperario = ?";
            $params[] = $codOperario;
        }
        
        $sql .= " ORDER BY m.fecha DESC, o.Nombre, o.Apellido";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll();
        
        // FILTRAR POR REGLAS ESPECÍFICAS DE DEPARTAMENTO
        $resultadosFiltrados = [];
        foreach ($resultados as $viatico) {
            $codDepartamento = $viatico['cod_departamento'];
            $fecha = $viatico['fecha'];
            $tipo = $viatico['tipo'];
            
            // Para viáticos diurnos (solo sucursal 19), aplican todos los días
            if ($tipo === 'Diurno') {
                $resultadosFiltrados[] = $viatico;
                continue;
            }
            
            // Para viáticos nocturnos, aplicar reglas por departamento
            if ($codDepartamento == 1 || $codDepartamento == 3) {
                // Managua (1) y Masaya (3) - aplican todos los días
                $resultadosFiltrados[] = $viatico;
            } elseif ($codDepartamento == 4) {
                // Granada (4) - solo aplica jueves a domingo
                $diaSemana = date('N', strtotime($fecha)); // 1=lunes, 7=domingo
                if ($diaSemana >= 4 && $diaSemana <= 7) { // Jueves=4 a Domingo=7
                    $resultadosFiltrados[] = $viatico;
                }
            }
        }
        
        return $resultadosFiltrados;
        
    } catch (PDOException $e) {
        error_log("Error al obtener viáticos automáticos: " . $e->getMessage());
        return [];
    }
}

function guardarViaticoAutomatico($codOperario, $fecha, $sucursalCodigo, $cantidad, $observaciones, $fechaPago = null, $tipo = 'Nocturno') {
    global $conn;
    
    try {
        // Para viáticos diurnos, validaciones diferentes
        if ($tipo === 'Diurno') {
            // Solo sucursal 19 aplica para viáticos diurnos
            if ($sucursalCodigo != 19) {
                throw new Exception("Los viáticos diurnos solo aplican para la sucursal 19");
            }
            
            // Obtener el monto correcto del departamento
            $codDepartamento = obtenerCodigoDepartamentoSucursal($sucursalCodigo);
            $montoCorrecto = obtenerViaticoDiurnoDepartamento($codDepartamento);
            if ($montoCorrecto != $cantidad) {
                throw new Exception("El monto no coincide con el viático diurno del departamento");
            }
        } else {
            // Validaciones para viáticos nocturnos
            $codDepartamento = obtenerCodigoDepartamentoSucursal($sucursalCodigo);
            if (!$codDepartamento || !aplicaViaticoDepartamento($codDepartamento, $fecha)) {
                throw new Exception("Este departamento no aplica para viáticos nocturnos en la fecha seleccionada");
            }
            
            // Obtener el monto correcto del departamento
            $montoCorrecto = obtenerViaticoNocturnoDepartamento($codDepartamento);
            if ($montoCorrecto != $cantidad) {
                throw new Exception("El monto no coincide con el viático nocturno del departamento");
            }
        }
        
        // CONSULTA DIRECTA MEJORADA PARA OBTENER EL CÓDIGO DE CONTRATO
        $codContrato = null;
        try {
            $stmtContrato = $conn->prepare("
                SELECT CodContrato 
                FROM Contratos 
                WHERE cod_operario = ? 
                ORDER BY inicio_contrato DESC, CodContrato DESC 
                LIMIT 1
            ");
            $stmtContrato->execute([$codOperario]);
            $resultContrato = $stmtContrato->fetch();
            
            if ($resultContrato && !empty($resultContrato['CodContrato'])) {
                $codContrato = $resultContrato['CodContrato'];
                error_log("Código de contrato encontrado para operario $codOperario: $codContrato");
            } else {
                error_log("No se encontró código de contrato para operario: $codOperario");
            }
        } catch (Exception $e) {
            error_log("Error en consulta de contrato para operario $codOperario: " . $e->getMessage());
        }
        
        // Verificar si ya existe un viático para este operario en esta fecha y tipo
        $stmt = $conn->prepare("
            SELECT id FROM viaticos 
            WHERE cod_operario = ? 
            AND fecha = ? 
            AND tipo = ?
            AND sucursal_codigo = ?
        ");
        $stmt->execute([$codOperario, $fecha, $tipo, $sucursalCodigo]);
        $existe = $stmt->fetch();
        
        if ($existe) {
            // Actualizar existente
            $stmt = $conn->prepare("
                UPDATE viaticos 
                SET cantidad = ?, observaciones = ?, 
                    actualizado_por = ?, fecha_actualizacion = NOW(),
                    fecha_pago = ?, cod_contrato = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $cantidad, $observaciones, 
                $_SESSION['usuario_id'], $fechaPago,
                $codContrato, $existe['id']
            ]);
            
            error_log("Viático actualizado - ID: {$existe['id']}, Contrato: $codContrato");
            return true;
        }
        
        // Crear nuevo viático
        $stmt = $conn->prepare("
            INSERT INTO viaticos (
                cod_operario, fecha, tipo, cantidad, observaciones, 
                creado_por, actualizado_por, sucursal_codigo, fecha_pago, cod_contrato
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $codOperario, $fecha, $tipo, $cantidad, $observaciones, 
            $_SESSION['usuario_id'], $_SESSION['usuario_id'], $sucursalCodigo, 
            $fechaPago, $codContrato
        ]);
        
        $nuevoId = $conn->lastInsertId();
        error_log("Nuevo viático creado - ID: $nuevoId, Contrato: $codContrato");
        
        return true;
    } catch (PDOException $e) {
        error_log("Error al guardar viático: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Error de validación: " . $e->getMessage());
        return false;
    }
}

function obtenerOperariosActivos($codSucursal = null) {
    global $conn;
    
    // Calcular fechas de inicio y fin de la semana actual (lunes a domingo)
    $lunesSemana = date('Y-m-d', strtotime('monday this week'));
    $domingoSemana = date('Y-m-d', strtotime('sunday this week'));
    
    $sql = "
        SELECT o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2 
        FROM Operarios o
        JOIN AsignacionNivelesCargos anc ON o.CodOperario = anc.CodOperario
        WHERE o.Operativo = 1
        AND anc.CodNivelesCargos NOT IN (27)
        AND (
            anc.Fin IS NULL 
            OR anc.Fin >= ?  -- Si la fecha de fin es mayor o igual al lunes de esta semana
            OR (anc.Fin BETWEEN ? AND ?) -- O si terminó en algún momento de esta semana
        )
    ";
    
    $params = [$lunesSemana, $lunesSemana, $domingoSemana];
    
    if ($codSucursal !== null) {
        $sql .= " AND anc.Sucursal = ?";
        $params[] = $codSucursal;
    }
    
    $sql .= " GROUP BY o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2
              ORDER BY o.Nombre, o.Apellido";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

function obtenerTodosOperarios() {
    global $conn;
    
    $sql = "SELECT CodOperario, Nombre, Nombre2, Apellido, Apellido2 
            FROM Operarios 
            WHERE Operativo = 1
            ORDER BY Nombre, Apellido";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function buscarOperarios($query) {
    global $conn;
    
    $sql = "SELECT CodOperario, Nombre, Nombre2, Apellido, Apellido2 
            FROM Operarios 
            WHERE Operativo = 1
            AND (Nombre LIKE ? OR Nombre2 LIKE ? OR Apellido LIKE ? OR Apellido2 LIKE ?)
            ORDER BY Nombre, Apellido
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $searchTerm = '%' . $query . '%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    
    return $stmt->fetchAll();
}

function procesarViatico() {
    global $conn;
    
    try {
        $idViatico = $_POST['id_viatico'] ?? null;
        $codOperario = $_POST['cod_operario'];
        $fecha = $_POST['fecha'];
        $tipo = $_POST['tipo'];
        $cantidad = $_POST['cantidad'];
        $observaciones = $_POST['observaciones'] ?? null;
        $sucursalCodigo = $_POST['sucursal_codigo'];
        $fechaPago = $_POST['fecha_pago'] ?? null; // Nuevo campo
        
        // CONSULTA DIRECTA PARA OBTENER EL CÓDIGO DE CONTRATO
        $codContrato = null;
        try {
            $stmtContrato = $conn->prepare("
                SELECT CodContrato 
                FROM Contratos 
                WHERE cod_operario = ? 
                ORDER BY inicio_contrato DESC, CodContrato DESC 
                LIMIT 1
            ");
            $stmtContrato->execute([$codOperario]);
            $resultContrato = $stmtContrato->fetch();
            
            if ($resultContrato && !empty($resultContrato['CodContrato'])) {
                $codContrato = $resultContrato['CodContrato'];
                error_log("Código de contrato encontrado para operario $codOperario: $codContrato");
            } else {
                error_log("No se encontró código de contrato para operario: $codOperario");
            }
        } catch (Exception $e) {
            error_log("Error en consulta de contrato para operario $codOperario: " . $e->getMessage());
        }
        
        if ($tipo === 'Nocturno' || $tipo === 'Diurno') {
            throw new Exception("Los viáticos nocturnos y diurnos se generan automáticamente");
        }
        
        if ($idViatico) {
            // Actualizar viático existente
            $stmt = $conn->prepare("
                UPDATE viaticos 
                SET cod_operario = ?, fecha = ?, tipo = ?, cantidad = ?, 
                    observaciones = ?, actualizado_por = ?, 
                    fecha_actualizacion = NOW(), sucursal_codigo = ?,
                    fecha_pago = ?, cod_contrato = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $codOperario, $fecha, $tipo, $cantidad, $observaciones, 
                $_SESSION['usuario_id'], $sucursalCodigo, $fechaPago, 
                $codContrato, $idViatico
            ]);
            
            error_log("Viático manual actualizado - ID: $idViatico, Contrato: $codContrato");
        } else {
            // Crear nuevo viático
            $stmt = $conn->prepare("
                INSERT INTO viaticos (
                    cod_operario, fecha, tipo, cantidad, observaciones, 
                    creado_por, actualizado_por, sucursal_codigo, fecha_pago, cod_contrato
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $codOperario, $fecha, $tipo, $cantidad, $observaciones, 
                $_SESSION['usuario_id'], $_SESSION['usuario_id'], $sucursalCodigo, 
                $fechaPago, $codContrato
            ]);
            
            $nuevoId = $conn->lastInsertId();
            error_log("Nuevo viático manual creado - ID: $nuevoId, Contrato: $codContrato");
        }
        
        $_SESSION['exito'] = 'Viático guardado correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al procesar el viático: ' . $e->getMessage();
        error_log("Error PDO en procesarViatico: " . $e->getMessage());
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        error_log("Error Exception en procesarViatico: " . $e->getMessage());
    }
    
    // Redirigir manteniendo los filtros
    header('Location: viaticos.php?' . http_build_query([
        'sucursal' => $_GET['sucursal'] ?? '',
        'desde' => $_GET['desde'] ?? '',
        'hasta' => $_GET['hasta'] ?? '',
        'operario' => $_GET['operario'] ?? ''
    ]));
    exit();
}

function eliminarViatico() {
    global $conn;
    
    try {
        $idViatico = $_POST['id_viatico'];
        
        $stmt = $conn->prepare("DELETE FROM viaticos WHERE id = ?");
        $stmt->execute([$idViatico]);
        
        $_SESSION['exito'] = 'Viático eliminado correctamente';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error al eliminar el viático: ' . $e->getMessage();
    }
    
    // Redirigir manteniendo los filtros
    header('Location: viaticos.php?' . http_build_query([
        'sucursal' => $_GET['sucursal'] ?? '',
        'desde' => $_GET['desde'] ?? '',
        'hasta' => $_GET['hasta'] ?? '',
        'operario' => $_GET['operario'] ?? ''
    ]));
    exit();
}

// Exportar a Excel - Viáticos Nocturnos Automáticos
if (isset($_GET['exportar_nocturnos'])) {
    // Configurar headers para descarga de archivo Excel CON UTF-8
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment;filename="viaticos_nocturnos_' . date('Y-m-d') . '.xls"');
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
    
    $sucursalParam = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? $primerDiaMes;
    $fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;
    $operarioParam = $_GET['operario'] ?? null;
    
    $viaticosNocturnos = obtenerMarcacionesNocturnasParaExcel($sucursalParam, $fechaDesde, $fechaHasta, $operarioParam);
    
    // Calcular totales por operario
    $totalesOperarios = [];
    foreach ($viaticosNocturnos as $viatico) {
        $codOperario = $viatico['cod_operario'];
        if (!isset($totalesOperarios[$codOperario])) {
            $totalesOperarios[$codOperario] = [
                'nombre' => implode(' ', array_filter([
                    $viatico['Nombre'],
                    $viatico['Nombre2'] ?? '',
                    $viatico['Apellido'],
                    $viatico['Apellido2'] ?? ''
                ], fn($v) => trim($v) !== '')),
                'total' => 0,
                'cantidad' => 0,
                'registrados' => 0,
                'pendientes' => 0
            ];
        }
        $totalesOperarios[$codOperario]['total'] += $viatico['viatico_nocturno'];
        $totalesOperarios[$codOperario]['cantidad']++;
        
        if ($viatico['estado'] === 'Registrado') {
            $totalesOperarios[$codOperario]['registrados']++;
        } else {
            $totalesOperarios[$codOperario]['pendientes']++;
        }
    }
    
    // Crear contenido Excel
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Cod. Operario</th>';
    echo '<th>Cod. Contrato</th>';
    echo '<th>Colaborador</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha Turno</th>';
    echo '<th>Estado</th>';
    echo '<th>Fecha Pago</th>';
    echo '<th>Viático Nocturno (C$)</th>';
    echo '<th>Horario</th>';
    echo '<th>Total Viáticos</th>';
    echo '<th>Cantidad</th>';
    echo '<th>Registrados</th>';
    echo '<th>Pendientes</th>';
    echo '</tr>';
    
    foreach ($viaticosNocturnos as $viatico) {
        $nombreCompleto = implode(' ', array_filter([
            $viatico['Nombre'],
            $viatico['Nombre2'] ?? '',
            $viatico['Apellido'],
            $viatico['Apellido2'] ?? ''
        ], fn($v) => trim($v) !== ''));
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($viatico['cod_operario'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . ($viatico['cod_contrato'] ?? '') . '</td>';
        $codContrato = $viatico['cod_contrato'] ?? '';
        $nombreCompleto = trim($viatico['Nombre'] . ' ' . $viatico['Nombre2'] . ' ' . $viatico['Apellido'] . ' ' . $viatico['Apellido2']);
        $nombreConContrato = $codContrato . ' ' . $nombreCompleto;
        
        echo '<td>' . htmlspecialchars($nombreConContrato, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($viatico['sucursal_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . formatoFechaCorta($viatico['fecha']) . '</td>';
        echo '<td>' . $viatico['estado'] . '</td>';
        echo '<td>' . ($viatico['fecha_pago'] ? formatoFechaCorta($viatico['fecha_pago']) : 'Pendiente') . '</td>';
        echo '<td>' . number_format($viatico['viatico_nocturno'], 2) . '</td>';
        echo '<td>' . htmlspecialchars(str_replace('Turno marcado: ', '', $viatico['observaciones']), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . number_format($totalesOperarios[$viatico['cod_operario']]['total'], 2) . '</td>';
        echo '<td>' . $totalesOperarios[$viatico['cod_operario']]['cantidad'] . '</td>';
        echo '<td>' . $totalesOperarios[$viatico['cod_operario']]['registrados'] . '</td>';
        echo '<td>' . $totalesOperarios[$viatico['cod_operario']]['pendientes'] . '</td>';
        echo '</tr>';
    }
    
    // Agregar fila de total general
    $totalGeneral = array_sum(array_column($totalesOperarios, 'total'));
    $cantidadGeneral = array_sum(array_column($totalesOperarios, 'cantidad'));
    $registradosGeneral = array_sum(array_column($totalesOperarios, 'registrados'));
    $pendientesGeneral = array_sum(array_column($totalesOperarios, 'pendientes'));
    
    echo '<tr>';
    echo '<td colspan="6"><strong>TOTAL GENERAL</strong></td>';
    echo '<td><strong>' . number_format($totalGeneral, 2) . '</strong></td>';
    echo '<td></td>';
    echo '<td><strong>' . number_format($totalGeneral, 2) . '</strong></td>';
    echo '<td><strong>' . $cantidadGeneral . '</strong></td>';
    echo '<td><strong>' . $registradosGeneral . '</strong></td>';
    echo '<td><strong>' . $pendientesGeneral . '</strong></td>';
    echo '</tr>';
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

/**
 * Función especial para obtener marcaciones nocturnas con todos los datos necesarios para Excel
 */
function obtenerMarcacionesNocturnasParaExcel($codSucursal, $fechaDesde, $fechaHasta, $codOperario = null) {
    global $conn;
    
    try {
        $sql = "
            SELECT 
                m.CodOperario as cod_operario,
                o.Nombre,
                o.Nombre2,
                o.Apellido,
                o.Apellido2,
                m.fecha,
                v.fecha_pago,
                d.viatico_nocturno,  -- Usar monto de departamentos
                s.nombre as sucursal_nombre,
                d.nombre as departamento_nombre,
                'Nocturno' as tipo,
                CONCAT(
                    'Turno marcado: ',
                    CASE 
                        WHEN m.hora_ingreso IS NOT NULL AND m.hora_salida IS NOT NULL THEN
                            CONCAT(TIME_FORMAT(m.hora_ingreso, '%h:%i %p'), ' - ', TIME_FORMAT(m.hora_salida, '%h:%i %p'))
                        WHEN m.hora_ingreso IS NOT NULL THEN
                            CONCAT(TIME_FORMAT(m.hora_ingreso, '%h:%i %p'), ' - Sin salida')
                        WHEN m.hora_salida IS NOT NULL THEN
                            CONCAT('Sin entrada - ', TIME_FORMAT(m.hora_salida, '%h:%i %p'))
                        ELSE 'Sin marcaciones'
                    END
                ) as observaciones,
                CASE WHEN v.id IS NULL THEN 'Pendiente' ELSE 'Registrado' END as estado
            FROM marcaciones m
            JOIN Operarios o ON m.CodOperario = o.CodOperario
            JOIN sucursales s ON m.sucursal_codigo = s.codigo
            JOIN departamentos d ON s.cod_departamento = d.codigo
            LEFT JOIN viaticos v ON m.CodOperario = v.cod_operario 
                AND m.fecha = v.fecha 
                AND v.tipo = 'Nocturno'
                AND m.sucursal_codigo = v.sucursal_codigo
            WHERE m.fecha BETWEEN ? AND ?
            AND (
                (m.hora_salida >= '20:00:00' AND m.hora_salida IS NOT NULL) OR
                (m.hora_ingreso >= '20:00:00' AND m.hora_ingreso IS NOT NULL) OR
                (m.hora_ingreso IS NULL AND m.hora_salida IS NOT NULL AND m.hora_salida >= '20:00:00') OR
                (m.hora_salida IS NULL AND m.hora_ingreso IS NOT NULL AND m.hora_ingreso >= '20:00:00')
            )
            AND d.viatico_nocturno IS NOT NULL  -- Solo departamentos con viáticos
            AND (m.hora_ingreso IS NOT NULL OR m.hora_salida IS NOT NULL)
            -- Excluir operarios cuya sucursal principal sea 6 o 18
            AND m.CodOperario NOT IN (
                SELECT DISTINCT anc_ex.CodOperario
                FROM AsignacionNivelesCargos anc_ex
                WHERE anc_ex.Sucursal IN (6, 18)
                AND (anc_ex.Fin IS NULL OR anc_ex.Fin >= ?)
            )
        ";
        
        $params = [$fechaDesde, $fechaHasta, $fechaDesde];
        
        if (!empty($codSucursal)) {
            $sql .= " AND m.sucursal_codigo = ?";
            $params[] = $codSucursal;
        }
        
        if (!empty($codOperario)) {
            $sql .= " AND m.CodOperario = ?";
            $params[] = $codOperario;
        }
        
        $sql .= " ORDER BY m.fecha DESC, o.Nombre, o.Apellido";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll();
        
        // Filtrar por reglas específicas de departamento
        $resultadosFiltrados = [];
        foreach ($resultados as $viatico) {
            if (aplicaViaticoDepartamento(obtenerCodigoDepartamentoSucursal($viatico['sucursal_codigo']), $viatico['fecha'])) {
                $resultadosFiltrados[] = $viatico;
            }
        }
        
        return $resultadosFiltrados;
        
    } catch (PDOException $e) {
        error_log("Error al obtener marcaciones nocturnas para Excel: " . $e->getMessage());
        return [];
    }
}

function obtenerNombreSucursalViaticos($codigo) {
    global $sucursales;
    foreach ($sucursales as $sucursal) {
        if ($sucursal['codigo'] == $codigo) {
            return $sucursal['nombre'];
        }
    }
    return $codigo;
}

// Exportar a Excel - Viáticos Nocturnos 2 (solo de BD)
if (isset($_GET['exportar_nocturnos2'])) {
    // Configurar headers para descarga de archivo Excel CON UTF-8
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment;filename="viaticos_nocturnos_bd_' . date('Y-m-d') . '.xls"');
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
    
    $sucursalParam = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? $primerDiaMes;
    $fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;
    $operarioParam = $_GET['operario'] ?? null;
    
    $viaticosNocturnosBD = obtenerViaticosNocturnosBD($sucursalParam, $fechaDesde, $fechaHasta, $operarioParam);
    
    // Calcular totales por operario
    $totalesOperarios = [];
    foreach ($viaticosNocturnosBD as $viatico) {
        $codOperario = $viatico['cod_operario'];
        if (!isset($totalesOperarios[$codOperario])) {
            $totalesOperarios[$codOperario] = [
                'nombre' => implode(' ', array_filter([
                    $viatico['Nombre'],
                    $viatico['Nombre2'] ?? '',
                    $viatico['Apellido'],
                    $viatico['Apellido2'] ?? ''
                ], fn($v) => trim($v) !== '')),
                'total' => 0,
                'cantidad_turnos' => 0
            ];
        }
        $totalesOperarios[$codOperario]['total'] += $viatico['cantidad'];
        $totalesOperarios[$codOperario]['cantidad_turnos']++;
    }
    
    // Crear contenido Excel
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Persona</th>';
    echo '<th>Codigo</th>';
    echo '<th>Cod. Contrato</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha de pago</th>';
    echo '<th>Turnos</th>';
    echo '<th>Total turnos</th>';
    echo '<th>Total a recibir</th>';
    echo '<th>Notas</th>';
    echo '</tr>';
    foreach ($viaticosNocturnosBD as $viatico) {
        $totalOperario = $totalesOperarios[$viatico['cod_operario']]['total'];
        $totalTurnos = $totalesOperarios[$viatico['cod_operario']]['cantidad_turnos'];
        
        echo '<tr>';
        $codContrato = $viatico['cod_contrato'] ?? '';
        $nombreCompleto = implode(' ', array_filter([
            $viatico['Nombre'],
            $viatico['Nombre2'] ?? '',
            $viatico['Apellido'],
            $viatico['Apellido2'] ?? ''
        ], fn($v) => trim($v) !== ''));
        $nombreConContrato = $codContrato . ' ' . $nombreCompleto;
        
        echo '<td>' . htmlspecialchars($nombreConContrato, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($viatico['cod_operario'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . ($viatico['cod_contrato'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($viatico['sucursal_nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . ($viatico['fecha_pago'] ? formatoFechaCorta($viatico['fecha_pago']) : 'Pendiente') . '</td>';
        echo '<td></td>'; // Columna Turnos vacía
        echo '<td>' . $totalTurnos . '</td>';
        echo '<td>' . number_format($totalOperario, 2) . '</td>';
        echo '<td>' . htmlspecialchars($viatico['observaciones'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

/**
 * Función para obtener viáticos nocturnos desde la BD (solo los ya guardados)
 */
function obtenerViaticosNocturnosBD($codSucursal, $fechaDesde, $fechaHasta, $codOperario = null) {
    global $conn;
    
    try {
        $sql = "
            SELECT 
                v.id,
                v.cod_operario,
                o.Nombre,
                o.Nombre2,
                o.Apellido,
                o.Apellido2,
                v.fecha,
                v.cantidad,
                v.observaciones,
                v.fecha_pago,
                s.nombre as sucursal_nombre
            FROM viaticos v
            JOIN Operarios o ON v.cod_operario = o.CodOperario
            JOIN sucursales s ON v.sucursal_codigo = s.codigo
            WHERE v.tipo = 'Nocturno'
            AND v.fecha BETWEEN ? AND ?
        ";
        
        $params = [$fechaDesde, $fechaHasta];
        
        if (!empty($codSucursal)) {
            $sql .= " AND v.sucursal_codigo = ?";
            $params[] = $codSucursal;
        }
        
        if (!empty($codOperario)) {
            $sql .= " AND v.cod_operario = ?";
            $params[] = $codOperario;
        }
        
        $sql .= " ORDER BY o.Nombre, o.Apellido, v.fecha";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error al obtener viáticos nocturnos de BD: " . $e->getMessage());
        return [];
    }
}

/**
 * Función para obtener viáticos nocturnos desde la BD para exportar (solo los ya guardados)
 * con fecha_pago NULL y en el rango especificado
 */
function obtenerViaticosNocturnosBDParaExport($codSucursal, $fechaDesde, $fechaHasta, $codOperario = null) {
    global $conn;
    
    try {
        $sql = "
            SELECT 
                v.id,
                v.cod_operario,
                o.Nombre,
                o.Nombre2,
                o.Apellido,
                o.Apellido2,
                v.fecha,
                v.cantidad,
                v.observaciones,
                v.fecha_pago,
                s.nombre as sucursal_nombre,
                s.codigo as sucursal_codigo,
                v.tipo,
                v.cod_contrato
            FROM viaticos v
            JOIN Operarios o ON v.cod_operario = o.CodOperario
            JOIN sucursales s ON v.sucursal_codigo = s.codigo
            WHERE v.tipo IN ('Nocturno', 'Diurno')
            AND v.fecha BETWEEN ? AND ?
            AND s.codigo IN (7, 9, 10, 11, 12, 13, 16, 19, 20, 22)
        ";
        
        $params = [$fechaDesde, $fechaHasta];
        
        if (!empty($codSucursal)) {
            $sql .= " AND v.sucursal_codigo = ?";
            $params[] = $codSucursal;
        } else {
            $sql .= " AND v.sucursal_codigo IN (7, 9, 10, 11, 12, 13, 16, 19, 20, 22)";
        }
        
        if (!empty($codOperario)) {
            $sql .= " AND v.cod_operario = ?";
            $params[] = $codOperario;
        }
        
        $sql .= " ORDER BY o.Nombre, o.Apellido, v.fecha";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error al obtener viáticos automáticos de BD para export: " . $e->getMessage());
        return [];
    }
}

// Exportar a Excel - Viáticos Nocturnos Guardados (con fecha_pago NULL)
if (isset($_GET['exportar_nocturnos_guardados'])) {
    // Configurar headers para descarga de archivo Excel CON UTF-8
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment;filename="viaticos_nocturnos_guardados_' . $fechaDesde . '_a_' . $fechaHasta . '.xls"');
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
    
    $sucursalParam = $_GET['sucursal'] ?? null;
    $fechaDesde = $_GET['desde'] ?? $primerDiaMes;
    $fechaHasta = $_GET['hasta'] ?? $ultimoDiaMes;
    $operarioParam = $_GET['operario'] ?? null;
    
    $viaticosNocturnosBD = obtenerViaticosNocturnosBDParaExport($sucursalParam, $fechaDesde, $fechaHasta, $operarioParam);
    
    // Crear contenido Excel con el nuevo formato
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Codigo</th>';
    echo '<th>Persona</th>';
    // echo '<th>Codigo</th>';
    echo '<th>Sucursal</th>';
    echo '<th>Fecha de Pago</th>';
    // echo '<th>Turnos 1er quincena</th>';
    // echo '<th>Turnos 2da quincena</th>';
    echo '<th>Total Turnos</th>';
    echo '<th>Total a recibir</th>';
    echo '</tr>';
    
    // Calcular totales por operario
    $totalesOperarios = [];
    foreach ($viaticosNocturnosBD as $viatico) {
        $codOperario = $viatico['cod_operario'];
        if (!isset($totalesOperarios[$codOperario])) {
            $totalesOperarios[$codOperario] = [
                'nombre' => implode(' ', array_filter([
                    $viatico['Nombre'],
                    $viatico['Nombre2'] ?? '',
                    $viatico['Apellido'],
                    $viatico['Apellido2'] ?? ''
                ], fn($v) => trim($v) !== '')),
                'sucursal' => $viatico['sucursal_nombre'],
                'total_turnos' => 0,
                'total_monto' => 0,
                'primer_quincena' => 0,
                'segunda_quincena' => 0,
                'cod_contrato' => $viatico['cod_contrato'] ?? '' // ← AGREGAR ESTA LÍNEA
            ];
        }
        
        $totalesOperarios[$codOperario]['total_turnos']++;
        $totalesOperarios[$codOperario]['total_monto'] += $viatico['cantidad'];
        
        // Determinar quincena BASADA EN EL DÍA DEL MES (1-15: primera, 16-31: segunda)
        $quincena = determinarQuincenaPorDiaMes($viatico['fecha']);
        
        if ($quincena === 'primera') {
            $totalesOperarios[$codOperario]['primer_quincena']++;
        } else {
            $totalesOperarios[$codOperario]['segunda_quincena']++;
        }
    }
    
    // Mostrar una fila por operario
    foreach ($totalesOperarios as $codOperario => $datos) {
        // Crear el valor combinado: código de contrato + nombre
        $personaCompleta = ($viatico['cod_contrato'] ?? '') . ' ' . htmlspecialchars($datos['nombre'], ENT_QUOTES, 'UTF-8');
        
        echo '<tr>';
        echo '<td>' . ($viatico['cod_contrato'] ?? '') . '</td>';
        echo '<td>' . $personaCompleta . '</td>'; // Código contrato + Nombre completo
        // echo '<td>' . htmlspecialchars($codOperario, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($datos['sucursal'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td></td>'; // Fecha de Pago vacía
        // echo '<td>' . $datos['primer_quincena'] . '</td>';
        // echo '<td>' . $datos['segunda_quincena'] . '</td>';
        echo '<td>' . $datos['total_turnos'] . '</td>';
        echo '<td>' . number_format($datos['total_monto'], 2) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viáticos - Operaciones</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Calibri', sans-serif;
            font-size: clamp(11px, 2vw, 16px) !important;
        }
        
        body {
            background-color: #F6F6F6;
            color: #333;
            padding: 5px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px;
        }
        
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #ddd; /* Esta es la línea horizontal */
    margin-bottom: 30px; /* Espacio después del header */
    flex-wrap: wrap;
    gap: 15px;
}

/* Header styles - Estilo modificado para logo izquierda, botones centro, usuario derecha */
.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 0 5px;
    box-sizing: border-box;
    margin: 1px auto;
    flex-wrap: wrap;
}

.logo {
    height: 50px;
}

.logo-container {
    flex-shrink: 0;
    margin-right: auto; /* Empuja los demás elementos hacia la derecha */
}

.buttons-container {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center; /* Centra los botones */
    flex-grow: 1;
    position: absolute; /* Posicionamiento absoluto para centrado real */
    left: 50%;
    transform: translateX(-50%);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto; /* Empuja este contenedor a la derecha */
}

.btn-agregar {
    background-color: transparent;
    color: #51B8AC;
    border: 1px solid #51B8AC;
    text-decoration: none;
    padding: 6px 10px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    white-space: nowrap;
    font-size: 14px;
    flex-shrink: 0;
}

.btn-agregar.activo {
    background-color: #51B8AC;
    color: white;
    font-weight: normal;
}

.btn-agregar:hover {
    background-color: #0E544C;
    color: white;
    border-color: #0E544C;
}

.user-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: #51B8AC;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.btn-logout {
    background: #51B8AC;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-logout:hover {
    background: #0E544C;
}
        
        .title {
            color: #0E544C;
            font-size: 1.5rem !important;
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
        
        select, input, button {
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

        th, td {
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
        
        .status-aprobado {
            color: #155724;
            background-color: #d4edda;
            padding: 5px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        
        .status-denegado {
            color: #721c24;
            background-color: #f8d7da;
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
            background-color: rgba(0,0,0,0.5);
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        
        .form-select, .form-textarea, .form-input {
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
        
        .actions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .total-viaticos {
            font-weight: bold;
            color: #0E544C;
            font-size: 1.1rem;
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
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em !important;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-auto {
            background-color: #6f42c1;
            color: white;
        }
        
        .badge-manual {
            background-color: #20c997;
            color: white;
        }
        
        .badge-nocturno {
            background-color: #343a40;
            color: white;
        }
        
        .badge-alimentacion {
            background-color: #fd7e14;
            color: white;
        }
        
        .badge-transporte {
            background-color: #007bff;
            color: white;
        }
        
        .badge-diurno {
            background-color: #ffc107;
            color: #000;
        }
        
        .hora-viatico {
            margin-top: 3px;
            font-weight: bold;
        }
        
        .cod-operario {
            display: inline-block;
            background-color: #e9ecef;
            color: #495057;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 8px;
            font-size: 0.9em !important;
        }
        
        .nombre-operario {
            display: inline;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
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

/* Estilos para el autocompletado */
#operarios-sugerencias {
    width: calc(100% - 2px); /* Mismo ancho que el input */
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 5px 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    margin-top: -1px; /* Para que se pegue al input */
    position: absolute;
    top: 100%; /* Posiciona el dropdown justo debajo del input */
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
    border-bottom: 1px solid #f0f0f0;
}

#operarios-sugerencias div:hover {
    background-color: #f5f5f5 !important;
}

/* Asegurar que el input tenga un z-index menor */
.filtro-group input[type="text"] {
    position: relative;
    z-index: 1;
}

/* Estilo para el texto del operario seleccionado */
.operario-seleccionado {
    font-weight: bold;
    color: #0E544C;
}
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoUsuarioId); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, 'Gestión de Viáticos'); ?>
            
            <div class="container-fluid p-3">
        
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
                        <?php if (verificarAccesoCargo([8, 16, 49])): ?>
                            <option value="" <?= empty($sucursalSeleccionada) ? 'selected' : '' ?>>Todas las sucursales</option>
                        <?php endif; ?>
                        <?php foreach ($sucursales as $sucursal): ?>
                            <option value="<?= $sucursal['codigo'] ?>" <?= $sucursalSeleccionada == $sucursal['codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sucursal['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="operario">Colaborador</label>
                    <input type="text" id="operario" name="operario" 
                           placeholder="Escriba para buscar..." 
                           value="<?php 
                               if ($operarioSeleccionado) {
                                   foreach ($todosOperarios as $op) {
                                       if ($op['CodOperario'] == $operarioSeleccionado) {
                                           echo htmlspecialchars(trim($op['Nombre'] . ' ' . ($op['Nombre2'] ?? '') . ' ' . $op['Apellido'] . ' ' . ($op['Apellido2'] ?? '')));
                                           break;
                                       }
                                   }
                               } else {
                                   echo 'Todos los colaboradores';
                               }
                           ?>">
                    <input type="hidden" id="operario_id" name="operario" value="<?= $operarioSeleccionado ?>">
                    <div id="operarios-sugerencias" style="display: none;"></div>
                </div>
                
                <div class="filter-group">
                    <label for="desde">Desde</label>
                    <input type="date" id="desde" name="desde" value="<?= $fechaDesde ?>" onchange="actualizarFiltros()">
                </div>
                
                <div class="filter-group">
                    <label for="hasta">Hasta</label>
                    <input type="date" id="hasta" name="hasta" value="<?= $fechaHasta ?>" onchange="actualizarFiltros()">
                </div>
                
                <div class="filter-group" style="align-self: flex-end;">
                    <button type="button" onclick="actualizarFiltros()" class="btn">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
                
                <div class="action-buttons">
                    <?php if (verificarAccesoCargo([16, 49])): ?>
                        <button type="button" onclick="mostrarModalNuevoViatico()" class="btn btn-success">
                            <i class="fas fa-plus"></i> Nuevo
                        </button>
                    <?php endif; ?>
                    
                    <?php if (!empty($viaticos)): ?>
                        <?php if (verificarAccesoCargo([8, 16, 49])): ?>
                            <button style="display:none;" type="button" onclick="exportarNocturnosExcel()" class="btn btn-primary">
                                <i class="fas fa-file-excel"></i> Exportar Nocturnos
                            </button>
                            <!-- NUEVO BOTÓN PARA GUARDAR Y EXPORTAR CON FECHA PAGO NULL -->
                            <button type="button" onclick="guardarYExportarNocturnos()" class="btn btn-warning">
                                <i class="fas fa-save"></i> Guardar y Exportar
                            </button>
                            <button style="display:none;" type="button" onclick="mostrarModalFechaPagoMasivo()" class="btn btn-warning">
                                <i class="fas fa-calendar-check"></i> Fecha Pago Masivo
                            </button>
                            <!-- NUEVO BOTÓN PARA EXPORTAR NOCTURNOS 2 que serán solo y únicamente los viáticos nocturnos guardados en bd -->
                            <button style="display:none;" type="button" onclick="exportarNocturnos2Excel()" class="btn btn-info">
                                <i class="fas fa-file-excel"></i> Exportar Nocturnos 2
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div style="display:none;" class="actions-header">
            <?php if (!empty($viaticos)): ?>
                <div class="total-viaticos">
                    Total: C$ <?= number_format(array_sum(array_column($viaticos, 'cantidad')), 2) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="table-container">
            <?php if (!empty($viaticos)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Sucursal</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Cantidad (C$)</th>
                            <th style="display:none;">Origen</th>
                            <th>Observaciones</th>
                            <?php if (verificarAccesoCargo([8, 16, 49])): ?>
                                <th>Fecha Pago Viático</th>
                            <?php endif; ?>
                            <th style="display:none;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viaticos as $viatico): ?>
                            <tr>
                                <td>
                                    <span class="cod-operario"><?= htmlspecialchars($viatico['cod_operario']) ?></span>
                                    <span class="nombre-operario">
                                        <?= htmlspecialchars(
                                            trim($viatico['Nombre'] . ' ' . 
                                                 $viatico['Apellido'] . ' ' . ($viatico['Apellido2'] ?? ''))
                                        ) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($viatico['sucursal_nombre']) ?></td>
                                <td><?= formatoFechaCorta($viatico['fecha']) ?></td>
                                <td>
                                    <?php if ($viatico['tipo'] === 'Nocturno'): ?>
                                        <span class="badge badge-nocturno">Nocturno</span>
                                    <?php elseif ($viatico['tipo'] === 'Diurno'): ?>
                                        <span class="badge badge-diurno">Diurno</span>
                                    <?php elseif ($viatico['tipo'] === 'Alimentación'): ?>
                                        <span class="badge badge-alimentacion">Alimentación</span>
                                    <?php else: ?>
                                        <span class="badge badge-transporte">Transporte</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($viatico['cantidad'], 2) ?></td>
                                <td style="display:none;"><?= htmlspecialchars($viatico['origen']) ?></td>
                                <td>
                                    <?php 
                                    if (strlen(strip_tags($viatico['observaciones'])) > 45) {
                                        // Para contenido con HTML (como los viáticos automáticos)
                                        $texto = strip_tags($viatico['observaciones']);
                                        echo htmlspecialchars(substr($texto, 0, 45).'...');
                                    } else {
                                        echo $viatico['observaciones'];
                                    }
                                    ?>
                                </td>
                                
                                <?php if (verificarAccesoCargo([8, 16, 49])): ?>
                                    <td><?= $viatico['fecha_pago'] ? formatoFechaCorta($viatico['fecha_pago']) : 'Pendiente' ?></td>
                                <?php endif; ?>
                                
                                <td style="display:none;">
                                    <?= htmlspecialchars($viatico['creador_nombre'] . ' ' . $viatico['creador_apellido']) ?>
                                    <?php if ($viatico['actualizador_nombre']): ?>
                                        <br><small>(Editado por: <?= htmlspecialchars($viatico['actualizador_nombre'] . ' ' . $viatico['actualizador_apellido']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                
                                <td style="text-align: center; display:none;">
                                    <?php if ($viatico['origen'] === 'Automático' && (verificarAccesoCargo([8, 49]))): ?>
                                        <button type="button" onclick="mostrarModalGuardarNocturno(
                                                <?= $viatico['cod_operario'] ?>, 
                                                '<?= htmlspecialchars($viatico['Nombre']) ?>', 
                                                '<?= htmlspecialchars($viatico['Nombre2'] ?? '') ?>', 
                                                '<?= htmlspecialchars($viatico['Apellido']) ?>', 
                                                '<?= htmlspecialchars($viatico['Apellido2'] ?? '') ?>', 
                                                '<?= $viatico['fecha'] ?>', 
                                                <?= $viatico['cantidad'] ?>, 
                                                '<?= htmlspecialchars($viatico['observaciones'] ?? '') ?>',
                                                '<?= $viatico['sucursal_codigo'] ?>',
                                                '<?= $viatico['fecha_pago'] ?? '' ?>'
                                            )" class="btn btn-success">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    <?php elseif ($viatico['origen'] === 'Manual'): ?>
                                        <?php if (verificarAccesoCargo([11, 49])): ?>
                                            <button type="button" onclick="mostrarModalEditarViatico(
                                                    <?= (int)$viatico['id'] ?>, 
                                                    '<?= htmlspecialchars($viatico['Nombre'], ENT_QUOTES) ?>', 
                                                    '<?= htmlspecialchars($viatico['Nombre2'] ?? '', ENT_QUOTES) ?>', 
                                                    '<?= htmlspecialchars($viatico['Apellido'], ENT_QUOTES) ?>', 
                                                    '<?= htmlspecialchars($viatico['Apellido2'] ?? '', ENT_QUOTES) ?>', 
                                                    '<?= $viatico['fecha'] ?>', 
                                                    '<?= $viatico['tipo'] ?>', 
                                                    <?= (float)$viatico['cantidad'] ?>, 
                                                    `<?= addslashes(htmlspecialchars($viatico['observaciones'] ?? '', ENT_QUOTES)) ?>`,
                                                    <?= (int)$viatico['cod_operario'] ?>,
                                                    '<?= $viatico['sucursal_codigo'] ?>',
                                                    '<?= $viatico['fecha_pago'] ?? '' ?>'
                                                )" class="btn btn-info">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" onclick="confirmarEliminarViatico(<?= $viatico['id'] ?>)" 
                                                class="btn btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-auto">Automático</span>
                                    <?php endif; ?>
                                    
                                    <?php if ((verificarAccesoCargo([8, 49])) && $viatico['tipo'] !== 'Nocturno'): ?>
                                        <button type="button" onclick="mostrarModalFechaPago(
                                            <?= (int)$viatico['id'] ?>,
                                            '<?= htmlspecialchars($viatico['Nombre'] . ' ' . $viatico['Apellido'], ENT_QUOTES) ?>',
                                            '<?= $viatico['fecha'] ?>',
                                            '<?= $viatico['tipo'] ?>',
                                            '<?= $viatico['fecha_pago'] ?? '' ?>'
                                        )" class="btn btn-warning" style="background-color: #ffc107; color: #000; margin-top: 3px;">
                                            <i class="fas fa-calendar-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <?php if ($fechaDesde && $fechaHasta): ?>
                        <?php if (empty($sucursalSeleccionada) && $esOperaciones): ?>
                            No se encontraron viáticos entre <?= formatoFechaCorta($fechaDesde) ?> y <?= formatoFechaCorta($fechaHasta) ?>.
                        <?php else: ?>
                            No se encontraron viáticos para <?= htmlspecialchars(obtenerNombreSucursal($sucursalSeleccionada)) ?> 
                            entre <?= formatoFechaCorta($fechaDesde) ?> y <?= formatoFechaCorta($fechaHasta) ?>.
                        <?php endif; ?>
                    <?php else: ?>
                        Seleccione un rango de fechas para buscar viáticos.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Modal para editar fecha de pago -->
<div class="modal" id="modalFechaPago">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Editar Fecha de Pago</h2>
            <button class="modal-close" onclick="cerrarModal()">&times;</button>
        </div>
        <form id="formFechaPago" method="post">
            <input type="hidden" name="guardar_fecha_pago" value="1">
            <input type="hidden" id="id_viatico_fecha_pago" name="id_viatico">
            
            <div class="modal-body">
                <div class="info-group">
                    <span class="info-label">Colaborador:</span>
                    <span class="info-value" id="modal-fp-nombre"></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Fecha Viático:</span>
                    <span class="info-value" id="modal-fp-fecha"></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Tipo:</span>
                    <span class="info-value" id="modal-fp-tipo"></span>
                </div>
                
                <div class="form-group">
                    <label for="fecha_pago_edit" class="form-label">Fecha de Pago:</label>
                    <input type="date" id="fecha_pago_edit" name="fecha_pago" class="form-input">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Fecha</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal para guardar viático nocturno -->
<div class="modal" id="modalGuardarNocturno">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Guardar Viático Nocturno</h2>
            <button class="modal-close" onclick="cerrarModal()">&times;</button>
        </div>
        <form id="formGuardarNocturno" method="post">
            <input type="hidden" name="guardar_nocturno" value="1">
            <input type="hidden" id="cod_operario_nocturno" name="cod_operario_nocturno">
            <input type="hidden" id="fecha_nocturno" name="fecha_nocturno">
            <input type="hidden" id="sucursal_codigo_nocturno" name="sucursal_codigo_nocturno">
            <input type="hidden" id="cantidad_nocturno" name="cantidad_nocturno">
            <input type="hidden" id="observaciones_nocturno" name="observaciones_nocturno">
            
            <div class="modal-body">
                <div class="info-group">
                    <span class="info-label">Colaborador:</span>
                    <span class="info-value" id="modal-nocturno-nombre"></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Sucursal:</span>
                    <span class="info-value" id="modal-nocturno-sucursal"></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Fecha:</span>
                    <span class="info-value" id="modal-nocturno-fecha"></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Tipo:</span>
                    <span class="info-value">Nocturno</span>
                </div>
                
                <div class="form-group">
                    <label for="fecha_pago" class="form-label">Fecha de Pago (Contabilidad):</label>
                    <input type="date" id="fecha_pago" name="fecha_pago" class="form-input">
                </div>
                
                <div class="info-group">
                    <span class="info-label">Cantidad:</span>
                    <span class="info-value" id="modal-nocturno-cantidad"></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Observaciones:</span>
                    <span class="info-value" id="modal-nocturno-observaciones"></span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Confirmar Guardado</button>
            </div>
        </form>
    </div>
</div>
    
    <!-- Modal para nuevo/editar viático -->
    <div class="modal" id="modalViatico">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalViaticoTitulo">Nuevo Viático</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formViatico" method="post">
                <input type="hidden" name="guardar_viatico" value="1">
                <input type="hidden" id="id_viatico" name="id_viatico">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="sucursal_codigo" class="form-label">Sucursal:</label>
                        <select id="sucursal_codigo" name="sucursal_codigo" class="form-select" required>
                            <?php foreach ($sucursales as $sucursal): ?>
                                <option value="<?= $sucursal['codigo'] ?>">
                                    <?= htmlspecialchars($sucursal['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha" class="form-label">Fecha:</label>
                        <input type="date" id="fecha" name="fecha" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cod_operario" class="form-label">Operario:</label>
                        <select id="cod_operario" name="cod_operario" class="form-select" required onchange="actualizarSucursalOperario()">
                            <option value="">Seleccione un operario</option>
                            <?php foreach ($operarios as $operario): 
                                $nombreCompleto = trim($operario['Nombre'] . ' ' . ($operario['Nombre2'] ?? '') . ' ' . 
                                                 $operario['Apellido'] . ' ' . ($operario['Apellido2'] ?? ''));
                                $nombreCompleto = preg_replace('/\s+/', ' ', $nombreCompleto); // Eliminar espacios múltiples
                            ?>
                                <option value="<?= $operario['CodOperario'] ?>" data-sucursal="<?= obtenerSucursalPrincipalOperario($operario['CodOperario']) ?>">
                                    <?= htmlspecialchars($nombreCompleto) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo" class="form-label">Tipo de viático:</label>
                        <select id="tipo" name="tipo" class="form-select" required>
                            <option value="Alimentación">Alimentación</option>
                            <option value="Transporte">Transporte</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="cantidad" class="form-label">Cantidad (C$):</label>
                        <input type="number" id="cantidad" name="cantidad" step="0.01" min="0" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="observaciones" class="form-label">Observaciones:</label>
                        <textarea id="observaciones" name="observaciones" class="form-textarea"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    
<!-- Modal para fecha de pago masivo -->
<div class="modal" id="modalFechaPagoMasivo">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Fecha de Pago Masivo - Viáticos Nocturnos</h2>
            <button class="modal-close" onclick="cerrarModal()">&times;</button>
        </div>
        <form id="formFechaPagoMasivo" method="post">
            <input type="hidden" name="guardar_fecha_pago_masivo" value="1">
            <input type="hidden" id="desde_masivo" name="desde_masivo" value="<?= $fechaDesde ?>">
            <input type="hidden" id="hasta_masivo" name="hasta_masivo" value="<?= $fechaHasta ?>">
            <input type="hidden" id="sucursal_masivo" name="sucursal_masivo" value="<?= $sucursalSeleccionada ?>">
            
            <div class="modal-body">
                <div class="info-group">
                    <span class="info-label">Rango de fechas:</span>
                    <span class="info-value">
                        <?= formatoFechaCorta($fechaDesde) ?> a <?= formatoFechaCorta($fechaHasta) ?>
                    </span>
                </div>
                
                <div class="info-group">
                    <span class="info-label">Sucursal:</span>
                    <span class="info-value">
                        <?= empty($sucursalSeleccionada) ? 'Todas las sucursales' : htmlspecialchars(obtenerNombreSucursal($sucursalSeleccionada)) ?>
                    </span>
                </div>
                
                <?php if ($operarioSeleccionado): ?>
                <div class="info-group">
                    <span class="info-label">Operario:</span>
                    <span class="info-value">
                        <?php 
                        $nombreOperario = 'Todos los operarios';
                        foreach ($todosOperarios as $op) {
                            if ($op['CodOperario'] == $operarioSeleccionado) {
                                $nombreOperario = trim($op['Nombre'] . ' ' . ($op['Nombre2'] ?? '') . ' ' . $op['Apellido'] . ' ' . ($op['Apellido2'] ?? ''));
                                break;
                            }
                        }
                        echo htmlspecialchars($nombreOperario);
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="fecha_pago_masivo" class="form-label">Fecha de Pago:</label>
                    <input type="date" id="fecha_pago_masivo" name="fecha_pago_masivo" class="form-input" required>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Esta acción <strong>GUARDARÁ</strong> todos los viáticos nocturnos automáticos 
                    en la base de datos con la fecha de pago seleccionada.
                    <?php if (empty($sucursalSeleccionada)): ?>
                    <br><strong>Incluye todas las sucursales</strong>
                    <?php endif; ?>
                    <br><strong>Nota:</strong> Los viáticos se guardarán en la tabla de viáticos.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar Masivamente</button>
            </div>
        </form>
    </div>
</div>
    
    <!-- Modal para confirmar eliminación -->
    <div class="modal" id="modalConfirmarEliminar">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Confirmar Eliminación</h2>
                <button class="modal-close" onclick="cerrarModal()">&times;</button>
            </div>
            <form id="formEliminarViatico" method="post">
                <input type="hidden" name="eliminar_viatico" value="1">
                <input type="hidden" id="id_viatico_eliminar" name="id_viatico">
                
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar este viático?</p>
                    <p>Esta acción no se puede deshacer.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="cerrarModal()" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Datos de operarios para el autocompletado
        const operariosData = [
            {id: 0, nombre: 'Todos los colaboradores'},
            <?php foreach ($todosOperarios as $op): ?>
            {id: <?= $op['CodOperario'] ?>, nombre: '<?= addslashes(trim($op['Nombre'] . ' ' . ($op['Nombre2'] ?? '') . ' ' . $op['Apellido'] . ' ' . ($op['Apellido2'] ?? ''))) ?>'},
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
        operarioInput.addEventListener('input', function() {
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
                    div.addEventListener('click', function() {
                        operarioInput.value = op.nombre;
                        operarioIdInput.value = op.id;
                        sugerenciasDiv.style.display = 'none';
                    });
                    div.addEventListener('mouseover', function() {
                        this.style.backgroundColor = '#f5f5f5';
                    });
                    div.addEventListener('mouseout', function() {
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
        document.addEventListener('click', function(e) {
            if (e.target !== operarioInput) {
                sugerenciasDiv.style.display = 'none';
            }
        });
        
        // Manejar tecla Enter en el input
        operarioInput.addEventListener('keydown', function(e) {
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
        
        // Mostrar modal para editar fecha de pago
        function mostrarModalFechaPago(id, nombre, fecha, tipo, fechaPago) {
            document.getElementById('id_viatico_fecha_pago').value = id;
            document.getElementById('modal-fp-nombre').textContent = nombre;
            document.getElementById('modal-fp-fecha').textContent = formatoFechaCorta(fecha);
            document.getElementById('modal-fp-tipo').textContent = tipo;
            
            if (fechaPago) {
                document.getElementById('fecha_pago_edit').value = fechaPago;
            } else {
                document.getElementById('fecha_pago_edit').value = '';
            }
            
            document.getElementById('modalFechaPago').style.display = 'flex';
        }
        
        // Mostrar modal para guardar viático nocturno
        function mostrarModalGuardarNocturno(codOperario, nombre, nombre2, apellido, apellido2, fecha, cantidad, observaciones, sucursalCodigo, fechaPago) {
            // Construir nombre completo
            const nombreCompleto = [nombre, nombre2, apellido, apellido2]
                .filter(part => part && part.trim() !== '')
                .join(' ');
            
            document.getElementById('cod_operario_nocturno').value = codOperario;
            document.getElementById('fecha_nocturno').value = fecha;
            document.getElementById('sucursal_codigo_nocturno').value = sucursalCodigo;
            document.getElementById('cantidad_nocturno').value = cantidad;
            document.getElementById('observaciones_nocturno').value = observaciones;
            
            // Establecer la fecha de pago si existe
            if (fechaPago) {
                document.getElementById('fecha_pago').value = fechaPago;
            } else {
                document.getElementById('fecha_pago').value = '';
            }
            
            document.getElementById('modal-nocturno-nombre').textContent = nombreCompleto;
            document.getElementById('modal-nocturno-sucursal').textContent = obtenerNombreSucursal(sucursalCodigo);
            document.getElementById('modal-nocturno-fecha').textContent = formatoFechaCorta(fecha);
            document.getElementById('modal-nocturno-cantidad').textContent = 'C$ ' + parseFloat(cantidad).toFixed(2);
            document.getElementById('modal-nocturno-observaciones').innerHTML = observaciones;
            
            document.getElementById('modalGuardarNocturno').style.display = 'flex';
        }
        
        // Función auxiliar para obtener nombre de sucursal (simulada)
        function obtenerNombreSucursal(codigo) {
            const select = document.getElementById('sucursal');
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === codigo) {
                    return select.options[i].text;
                }
            }
            return codigo;
        }
        
        // Función auxiliar para formatear fecha
        function formatoFechaCorta(fechaStr) {
            const fecha = new Date(fechaStr);
            const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            return fecha.getDate() + '-' + meses[fecha.getMonth()] + '-' + fecha.getFullYear().toString().slice(-2);
        }
        
        // Actualizar la sucursal según el operario seleccionado
        function actualizarSucursalOperario() {
            const operarioSelect = document.getElementById('cod_operario');
            const sucursalSelect = document.getElementById('sucursal_codigo');
            const selectedOption = operarioSelect.options[operarioSelect.selectedIndex];
            
            if (selectedOption && selectedOption.dataset.sucursal) {
                // Buscar la sucursal del operario en las opciones
                for (let i = 0; i < sucursalSelect.options.length; i++) {
                    if (sucursalSelect.options[i].value === selectedOption.dataset.sucursal) {
                        sucursalSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        }
        
        // Actualizar filtros y recargar la página
        function actualizarFiltros() {
            const sucursal = document.getElementById('sucursal').value;
            const desde = document.getElementById('desde').value;
            const hasta = document.getElementById('hasta').value;
            const operario = document.getElementById('operario_id').value;
            
            // Validar fechas
            if (!desde || !hasta) {
                alert('Por favor seleccione ambas fechas');
                return;
            }
            
            if (new Date(desde) > new Date(hasta)) {
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }
            
            // Construir URL con parámetros
            const params = new URLSearchParams();
            if (sucursal) params.append('sucursal', sucursal);
            params.append('desde', desde);
            params.append('hasta', hasta);
            if (operario && operario !== '0') params.append('operario', operario);
            
            window.location.href = 'viaticos.php?' + params.toString();
        }
        
        // Mostrar modal para nuevo viático
        function mostrarModalNuevoViatico() {
            document.getElementById('modalViaticoTitulo').textContent = 'Nuevo Viático';
            document.getElementById('id_viatico').value = '';
            document.getElementById('formViatico').reset();
            document.getElementById('fecha').valueAsDate = new Date();
            document.getElementById('modalViatico').style.display = 'flex';
        }
        
        function mostrarModalEditarViatico(id, nombre, nombre2, apellido, apellido2, fecha, tipo, cantidad, observaciones, codOperario, sucursalCodigo, fechaPago) {
            try {
                // Construir nombre completo
                const nombreCompleto = [nombre, nombre2, apellido, apellido2]
                    .filter(part => part && part.trim() !== '')
                    .join(' ');
                
                document.getElementById('modalViaticoTitulo').textContent = 'Editar Viático';
                document.getElementById('id_viatico').value = id;
                
                // Seleccionar operario
                const operarioSelect = document.getElementById('cod_operario');
                for (let i = 0; i < operarioSelect.options.length; i++) {
                    if (operarioSelect.options[i].value == codOperario) {
                        operarioSelect.selectedIndex = i;
                        break;
                    }
                }
                
                document.getElementById('fecha').value = fecha;
                document.getElementById('tipo').value = tipo;
                document.getElementById('cantidad').value = cantidad;
                document.getElementById('observaciones').value = observaciones;
                
                // Seleccionar sucursal
                const sucursalSelect = document.getElementById('sucursal_codigo');
                if (sucursalSelect) {
                    for (let i = 0; i < sucursalSelect.options.length; i++) {
                        if (sucursalSelect.options[i].value === sucursalCodigo) {
                            sucursalSelect.selectedIndex = i;
                            break;
                        }
                    }
                }
                
                // Establecer fecha de pago si existe
                if (fechaPago) {
                    document.getElementById('fecha_pago').value = fechaPago;
                } else {
                    document.getElementById('fecha_pago').value = '';
                }
                
                document.getElementById('modalViatico').style.display = 'flex';
            } catch (error) {
                console.error('Error al mostrar modal de edición:', error);
                alert('Ocurrió un error al intentar editar el viático. Detalles en consola.');
            }
        }
        
// Mostrar modal para fecha de pago masivo
function mostrarModalFechaPagoMasivo() {
    // Verificar que hay viáticos nocturnos
    const tieneNocturnos = document.querySelector('tr:has(.badge-nocturno)');
    if (!tieneNocturnos) {
        alert('No hay viáticos nocturnos en el rango seleccionado');
        return;
    }
    
    document.getElementById('modalFechaPagoMasivo').style.display = 'flex';
}

// Función para exportar viáticos nocturnos 2 (solo de BD)
function exportarNocturnos2Excel() {
    const sucursal = document.getElementById('sucursal').value;
    const desde = document.getElementById('desde').value;
    const hasta = document.getElementById('hasta').value;
    const operario = document.getElementById('operario_id').value;
    
    if (!desde || !hasta) {
        alert('Por favor seleccione ambas fechas');
        return;
    }
    
    if (new Date(desde) > new Date(hasta)) {
        alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
        return;
    }
    
    const params = new URLSearchParams();
    if (sucursal) params.append('sucursal', sucursal);
    params.append('desde', desde);
    params.append('hasta', hasta);
    if (operario && operario !== '0') params.append('operario', operario);
    params.append('exportar_nocturnos2', '1');
    
    window.location.href = 'viaticos.php?' + params.toString();
}
        
        // Mostrar modal para confirmar eliminación
        function confirmarEliminarViatico(id) {
            document.getElementById('id_viatico_eliminar').value = id;
            document.getElementById('modalConfirmarEliminar').style.display = 'flex';
        }
        
        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modalViatico').style.display = 'none';
            document.getElementById('modalConfirmarEliminar').style.display = 'none';
            document.getElementById('modalGuardarNocturno').style.display = 'none';
            document.getElementById('modalFechaPago').style.display = 'none';
            document.getElementById('modalFechaPagoMasivo').style.display = 'none'; // Nueva línea
        }
        
        // Función para exportar viáticos nocturnos a Excel
        function exportarNocturnosExcel() {
            const sucursal = document.getElementById('sucursal').value;
            const desde = document.getElementById('desde').value;
            const hasta = document.getElementById('hasta').value;
            const operario = document.getElementById('operario_id').value;
            
            if (!desde || !hasta) {
                alert('Por favor seleccione ambas fechas');
                return;
            }
            
            if (new Date(desde) > new Date(hasta)) {
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }
            
            // Confirmar que hay datos para exportar
            if (confirm('¿Exportar viáticos nocturnos ya registrados con fecha de pago?')) {
                const params = new URLSearchParams();
                if (sucursal) params.append('sucursal', sucursal);
                params.append('desde', desde);
                params.append('hasta', hasta);
                if (operario && operario !== '0') params.append('operario', operario);
                params.append('exportar_nocturnos', '1');
                
                window.location.href = 'viaticos.php?' + params.toString();
            }
        }
        
        // Función para guardar y exportar viáticos nocturnos
        function guardarYExportarNocturnos() {
            const sucursal = document.getElementById('sucursal').value;
            const desde = document.getElementById('desde').value;
            const hasta = document.getElementById('hasta').value;
            const operario = document.getElementById('operario_id').value;
            
            if (!desde || !hasta) {
                alert('Por favor seleccione ambas fechas');
                return;
            }
            
            if (new Date(desde) > new Date(hasta)) {
                alert('La fecha "Desde" no puede ser mayor que la fecha "Hasta"');
                return;
            }
            
            if (confirm('¿Guardar viáticos nocturnos con fecha de pago NULL y exportar a Excel?')) {
                // Crear formulario dinámico para enviar por POST
                const form = document.createElement('form');
                form.method = 'post';
                form.action = 'viaticos.php';
                
                // Agregar parámetros
                const sucursalInput = document.createElement('input');
                sucursalInput.type = 'hidden';
                sucursalInput.name = 'sucursal_masivo';
                sucursalInput.value = sucursal;
                form.appendChild(sucursalInput);
                
                const desdeInput = document.createElement('input');
                desdeInput.type = 'hidden';
                desdeInput.name = 'desde_masivo';
                desdeInput.value = desde;
                form.appendChild(desdeInput);
                
                const hastaInput = document.createElement('input');
                hastaInput.type = 'hidden';
                hastaInput.name = 'hasta_masivo';
                hastaInput.value = hasta;
                form.appendChild(hastaInput);
                
                const operarioInput = document.createElement('input');
                operarioInput.type = 'hidden';
                operarioInput.name = 'operario_masivo';
                operarioInput.value = operario && operario !== '0' ? operario : '';
                form.appendChild(operarioInput);
                
                const accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'guardar_y_exportar_nocturnos';
                accionInput.value = '1';
                form.appendChild(accionInput);
                
                // Agregar al documento y enviar
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Cerrar modal al hacer clic fuera del contenido
        window.addEventListener('click', function(event) {
            const modalViatico = document.getElementById('modalViatico');
            const modalConfirmar = document.getElementById('modalConfirmarEliminar');
            const modalNocturno = document.getElementById('modalGuardarNocturno');
            const modalFechaPago = document.getElementById('modalFechaPago');
            
            if (event.target === modalViatico || event.target === modalConfirmar || event.target === modalNocturno || event.target === modalFechaPago) {
                cerrarModal();
            }
        });
    </script>
            </div>
        </div>
    </div>
</body>
</html>