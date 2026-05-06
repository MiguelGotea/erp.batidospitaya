<?php
    require 'vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/auth/auth.php'; // Cambiado: anteriormente llamaba al auth de auditorías, ahora llama al auth del core
require_once '../../../core/helpers/funciones.php'; // Antes llamaba a funciones.php de auditora
require_once '../../../core/database/conexion.php'; // Cambiado: anteriormente llamaba al conexion de auditor�as, ahora llama al del core;
    
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
// Establecer zona horaria
date_default_timezone_set('America/Managua');

// Función para formatear fecha en español (modificada)
function formatFechaEspanol($fecha = 'now') {
    $meses = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
    ];
    
    // Crear objeto DateTime con la zona horaria correcta
    $date = new DateTime($fecha, new DateTimeZone('America/Managua'));
    
    return $date->format('d').'-'.$meses[$date->format('n')].'-'.$date->format('y').' '.$date->format('h:i a');
}

function formatFechaReporte($fecha) {
    $date = new DateTime($fecha);
    return $date->format('d/m/Y');
}

// Conexión directa a la base de datos
$host = 'localhost';
$dbname = 'u839374897_avisos';
$username = 'u839374897_avisos';
$password = '8GLVR9*k';

//******************************Estándar para header******************************
verificarAutenticacion();

$usuario = obtenerUsuarioActual();
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin';

// Verificar acceso al módulo Operaciones (Código 11 para Jefe de Operaciones)
verificarAccesoCargo([5, 8, 11, 21, 16]);

// Verificar acceso al módulo
if (!verificarAccesoCargo(5, 8, 11, 21, 16) && !(isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin')) {
    header('Location: ../index.php');
    exit();
}

// Obtenemos el cargo principal usando la función de funciones.php
$cargoUsuario = obtenerCargoPrincipalUsuario($_SESSION['usuario_id']);
//******************************Estándar para header, termina******************************

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Crear e inicializar tabla de historial de estados
    $db->exec("CREATE TABLE IF NOT EXISTS historial_estados_personal (
                id INT AUTO_INCREMENT PRIMARY KEY,
                personal_id INT NOT NULL,
                estado TINYINT(1) NOT NULL COMMENT '1=Activo, 0=Inactivo',
                fecha_inicio DATE NOT NULL,
                fecha_fin DATE NULL COMMENT 'NULL significa que es el estado actual',
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
              )");
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_historial_personal ON historial_estados_personal(personal_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_historial_fechas ON historial_estados_personal(fecha_inicio, fecha_fin)");
    
    // Migración inicial de datos (solo ejecuta una vez)
    $resultado = $db->query("SELECT COUNT(*) FROM historial_estados_personal")->fetchColumn();
    if ($resultado == 0) {
        $db->exec("INSERT INTO historial_estados_personal (personal_id, estado, fecha_inicio)
                  SELECT id, COALESCE(activo, 1), '2000-01-01' FROM personal");
    }
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

function actualizarEstadoPersonal($db, $personal_id, $nuevo_estado, $fecha_cambio = null) {
    if (!$fecha_cambio) {
        $fecha_cambio = date('Y-m-d');
    }
    
    try {
        $db->beginTransaction();
        
        // Cierra el estado anterior
        $db->prepare("UPDATE historial_estados_personal 
                     SET fecha_fin = DATE_SUB(?, INTERVAL 1 DAY)
                     WHERE personal_id = ? AND fecha_fin IS NULL")
           ->execute([$fecha_cambio, $personal_id]);
        
        // Inserta el nuevo estado
        $db->prepare("INSERT INTO historial_estados_personal 
                     (personal_id, estado, fecha_inicio, fecha_fin)
                     VALUES (?, ?, ?, NULL)")
           ->execute([$personal_id, $nuevo_estado, $fecha_cambio]);
        
        // Actualiza el estado actual en la tabla personal
        $db->prepare("UPDATE personal SET activo = ? WHERE id = ?")
           ->execute([$nuevo_estado, $personal_id]);
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al actualizar estado: " . $e->getMessage());
        return false;
    }
}

// Función para generar reportes Excel
function generarReporteExcel($db, $mes, $anio, $sucursal) {
    $fecha_inicio = "$anio-$mes-01";
    $fecha_fin = date('Y-m-t', strtotime($fecha_inicio));
    
    // Consulta para auditorías consolidadas (solo faltantes)
    $sql = "SELECT 
                va.id,
                DATE_FORMAT(va.fecha_hora, '%Y-%m-%d %H:%i:%s') as fecha_hora,
                CASE 
                    WHEN va.tipo_auditoria = 'facturacion' THEN 'Auditoria de Facturación'
                    WHEN va.tipo_auditoria = 'inventario' THEN 'Auditoria de Inventario'
                END as tipo,
                va.sucursal,
                va.responsable,
                ABS(va.monto) as monto_total,
                va.tipo_auditoria
            FROM vista_auditorias_consolidadas va
            WHERE MONTH(va.fecha_hora) = :mes 
            AND YEAR(va.fecha_hora) = :anio
            AND va.monto < 0
            AND va.tipo_auditoria != 'caja_chica'";
    
    $params = [':mes' => $mes, ':anio' => $anio];
    
    if (!empty($sucursal)) {
        $sql .= " AND va.sucursal = :sucursal";
        $params[':sucursal'] = $sucursal;
    }
    
    $sql .= " ORDER BY va.fecha_hora DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Consulta para faltantes de inventario
    $sql_faltantes = "SELECT 
                        fi.id,
                        DATE_FORMAT(fi.fecha, '%Y-%m-%d %H:%i:%s') as fecha_hora,
                        'Faltante Inventario' as tipo,
                        fi.sucursal,
                        fi.auditor as responsable,
                        fi.total_faltante as monto_total,
                        'faltante_inventario' as tipo_auditoria
                      FROM faltante_inventario fi
                      WHERE MONTH(fi.fecha) = :mes 
                      AND YEAR(fi.fecha) = :anio";
    
    $params_faltantes = [':mes' => $mes, ':anio' => $anio];
    
    if (!empty($sucursal)) {
        $sql_faltantes .= " AND fi.sucursal = :sucursal";
        $params_faltantes[':sucursal'] = $sucursal;
    }
    
    $sql_faltantes .= " ORDER BY fi.fecha DESC";
    
    $stmt_faltantes = $db->prepare($sql_faltantes);
    $stmt_faltantes->execute($params_faltantes);
    $faltantes = $stmt_faltantes->fetchAll(PDO::FETCH_ASSOC);
    
    // Combinar y ordenar resultados
    $registros = array_merge($auditorias, $faltantes);
    usort($registros, function($a, $b) {
        return strtotime($b['fecha_hora']) - strtotime($a['fecha_hora']);
    });
    
    // Obtener personal activo durante el período del reporte
    $personal_por_sucursal = [];
    $sql_personal = "SELECT b.name as sucursal, p.nombre, p.peso_porcentual 
                    FROM branches b
                    JOIN personal p ON b.id = p.branch_id
                    JOIN (
                        SELECT h.personal_id 
                        FROM historial_estados_personal h
                        WHERE h.fecha_inicio <= :fecha_fin
                        AND (h.fecha_fin >= :fecha_inicio OR h.fecha_fin IS NULL)
                        AND h.estado = 1
                        GROUP BY h.personal_id
                    ) he ON p.id = he.personal_id
                    ORDER BY b.name, p.nombre";
    
    $stmt_personal = $db->prepare($sql_personal);
    $stmt_personal->execute([':fecha_inicio' => $fecha_inicio, ':fecha_fin' => $fecha_fin]);
    while ($row = $stmt_personal->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['nombre'])) {
            $personal_por_sucursal[$row['sucursal']][] = [
                'nombre' => $row['nombre'],
                'porcentaje' => $row['peso_porcentual']
            ];
        }
    }
    
    $reporte_data = [];
    $grupos_auditorias = [];
    $current_group_id = 0;
    
    foreach ($registros as $registro) {
        $sucursal = $registro['sucursal'];
        $monto_total = $registro['monto_total'];
        $tipo_auditoria = $registro['tipo_auditoria'];
        
        $grupos_auditorias[$current_group_id] = [
            'tipo' => $registro['tipo'],
            'sucursal' => $sucursal,
            'fecha' => formatFechaReporte($registro['fecha_hora']),
            'total' => $monto_total
        ];
        
        if ($tipo_auditoria == 'facturacion') {
            $reporte_data[] = [
                'fecha' => formatFechaReporte($registro['fecha_hora']),
                'tipo' => $registro['tipo'],
                'sucursal' => $sucursal,
                'participante' => $registro['responsable'],
                'monto_total' => number_format($monto_total, 2),
                'monto_individual' => number_format($monto_total, 2),
                'porcentaje' => '100.00%',
                'group_id' => $current_group_id
            ];
        } 
        elseif ($tipo_auditoria == 'inventario' || $tipo_auditoria == 'faltante_inventario') {
            if (isset($personal_por_sucursal[$sucursal]) && !empty($personal_por_sucursal[$sucursal])) {
                $total_porcentaje = array_sum(array_column($personal_por_sucursal[$sucursal], 'porcentaje'));
                $porcentaje_ajustado = $total_porcentaje > 0 ? $total_porcentaje : 100;
                
                foreach ($personal_por_sucursal[$sucursal] as $participante) {
                    $porcentaje = $participante['porcentaje'] > 0 ? $participante['porcentaje'] : (100 / count($personal_por_sucursal[$sucursal]));
                    $monto_individual = $monto_total * ($porcentaje / 100);
                    
                    $reporte_data[] = [
                        'fecha' => formatFechaReporte($registro['fecha_hora']),
                        'tipo' => $registro['tipo'],
                        'sucursal' => $sucursal,
                        'participante' => $participante['nombre'],
                        'monto_total' => number_format($monto_total, 2),
                        'monto_individual' => number_format($monto_individual, 2),
                        'porcentaje' => number_format($porcentaje, 2) . '%',
                        'group_id' => $current_group_id
                    ];
                }
            } else {
                $reporte_data[] = [
                    'fecha' => formatFechaReporte($registro['fecha_hora']),
                    'tipo' => $registro['tipo'],
                    'sucursal' => $sucursal,
                    'participante' => $registro['responsable'],
                    'monto_total' => number_format($monto_total, 2),
                    'monto_individual' => number_format($monto_total, 2),
                    'porcentaje' => '100.00%',
                    'group_id' => $current_group_id
                ];
            }
        }
        
        $current_group_id++;
        $reporte_data[] = [];
    }
    
    // Generar el archivo Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->setCellValue('A1', 'FECHA');
    $sheet->setCellValue('B1', 'TIPO');
    $sheet->setCellValue('C1', 'SUCURSAL');
    $sheet->setCellValue('D1', 'PARTICIPANTE');
    $sheet->setCellValue('E1', 'MONTO TOTAL');
    $sheet->setCellValue('F1', 'MONTO INDIVIDUAL');
    $sheet->setCellValue('G1', 'PORCENTAJE');
    
    $row = 2;
    $last_group = null;
    
    foreach ($reporte_data as $index => $data) {
        if (empty($data)) {
            if ($last_group !== null && (!isset($reporte_data[$index+1]) || (isset($reporte_data[$index+1]) && $reporte_data[$index+1]['group_id'] !== $last_group))) {
                if (isset($grupos_auditorias[$last_group])) {
                    $grupo = $grupos_auditorias[$last_group];
                    
                    $sheet->setCellValue('A'.$row, 'TOTAL:');
                    $sheet->mergeCells('A'.$row.':D'.$row);
                    $sheet->setCellValue('E'.$row, number_format($grupo['total'], 2));
                    $sheet->mergeCells('E'.$row.':G'.$row);
                    
                    $sheet->getStyle('A'.$row.':G'.$row)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFD9D9D9']
                        ]
                    ]);
                    
                    $row++;
                }
            }
            $row++;
            continue;
        }
        
        $sheet->setCellValue('A'.$row, $data['fecha']);
        $sheet->setCellValue('B'.$row, $data['tipo']);
        $sheet->setCellValue('C'.$row, $data['sucursal']);
        $sheet->setCellValue('D'.$row, $data['participante']);
        $sheet->setCellValue('E'.$row, $data['monto_total']);
        $sheet->setCellValue('F'.$row, $data['monto_individual']);
        $sheet->setCellValue('G'.$row, $data['porcentaje']);
        
        $last_group = $data['group_id'];
        $row++;
    }
    
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    $headerStyle = [
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FFA0A0A0']
        ]
    ];
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
    
    $sheet->getStyle('E2:G'.$row)
          ->getAlignment()
          ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    
    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="reporte_auditorias_'.date('Y-m-d').'.xlsx"');
    $writer->save('php://output');
    exit;
}

// Manejo de solicitudes de reporte
if (isset($_GET['generar_reporte'])) {
    generarReporteExcel($db, $_GET['mes'], $_GET['anio'], $_GET['sucursal_reporte']);
}

// Parámetros de filtrado actualizados con un solo campo de fecha
$filtros = [
    'fecha' => $_GET['fecha'] ?? null,
    'sucursal' => $_GET['sucursal'] ?? null,
    'tipo' => $_GET['tipo'] ?? null
];

// Construir consulta con filtros (solo faltantes)
$sql = "SELECT * FROM vista_auditorias_consolidadas WHERE monto < 0";
$params = [];

// Aplicar filtros dinámicos con validación
if (!empty($filtros['fecha'])) {
    // Convertir fecha a formato UTC considerando la zona horaria
    $fechaLocal = new DateTime($filtros['fecha'], new DateTimeZone('America/Managua'));
    $fechaUTC = clone $fechaLocal;
    $fechaUTC->setTimezone(new DateTimeZone('UTC'));

    // Buscar registros en un rango de 24 horas (para cubrir diferencia horaria)
    $sql .= " AND fecha_hora >= :fecha_inicio AND fecha_hora < :fecha_fin";
    $params[':fecha_inicio'] = $fechaUTC->format('Y-m-d 00:00:00');
    $params[':fecha_fin'] = $fechaUTC->modify('+1 day')->format('Y-m-d 00:00:00');
}

if (!empty($filtros['sucursal'])) {
    $sql .= " AND sucursal = :sucursal";
    $params[':sucursal'] = $filtros['sucursal'];
}

if (!empty($filtros['tipo'])) {
    // Validar que el tipo sea uno de los permitidos
    $tipos_permitidos = ['caja_chica', 'facturacion', 'inventario'];
    if (in_array($filtros['tipo'], $tipos_permitidos)) {
        $sql .= " AND tipo_auditoria = :tipo";
        $params[':tipo'] = $filtros['tipo'];
    }
}

$sql .= " ORDER BY fecha_hora DESC";

// Obtener datos
$stmt = $db->prepare($sql);
$stmt->execute($params);
$auditorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener listas para filtros
$sucursales = $db->query("SELECT DISTINCT name FROM branches ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$anios = $db->query("SELECT DISTINCT YEAR(fecha_hora) as anio FROM (
                        SELECT fecha_hora FROM vista_auditorias_consolidadas WHERE tipo_auditoria != 'caja_chica' AND monto < 0
                        UNION ALL
                        SELECT fecha as fecha_hora FROM faltante_inventario
                    ) fechas ORDER BY anio DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditorías en Efectivo</title>
    <link rel="icon" href="icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{
            font-size: clamp(11px, 2vw, 16px) !important;
        }
    
        @font-face {
            font-family: 'Calibri';
            src: url('https://fonts.cdnfonts.com/css/calibri');
        }
        
        body {
            font-family: 'Calibri', sans-serif;
            background-color: #F6F6F6;
            padding: 0;
            margin: 0;
        }
        
        .container-fluid {
            padding: 0 5px;
            width: 100%;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            width: 100%;
            margin: 0;
        }
        
        .table thead th {
            background-color: #0E544C !important;
            color: white;
            vertical-align: middle;
            position: relative;
        }
        
        .badge-caja_chica { background-color: #3a7bd5; }
        .badge-facturacion { background-color: #00d2ff; }
        .badge-inventario { background-color: #f46b45; }
        
        .header-logo {
            height: 40px;
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 10;
        }
        
        /* Estilos para filtros desplegables */
        .filtro-header {
            cursor: pointer;
            position: relative;
        }
        
        .filtro-desplegable {
            display: none;
            position: absolute;
            background: white;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 100;
            width: 280px;
            top: 100%;
            left: 0;
        }
        
        .filtro-header.active .filtro-desplegable {
            display: block;
        }
        
        .filtro-desplegable .form-group {
            margin-bottom: 10px;
        }
        
        .filtro-desplegable label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .filtro-acciones {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .filtro-icono {
            margin-left: 5px;
            transition: transform 0.3s;
        }
        
        .filtro-header.active .filtro-icono {
            transform: rotate(180deg);
        }
        
        .dropdown-reporte {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .dropdown-contenido {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            min-width: 250px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            padding: 12px;
            z-index: 1;
            right: 0;
            border-radius: 5px;
        }
        
        .dropdown-reporte:hover .dropdown-contenido {
            display: block;
        }
        
        .form-group-reporte {
            margin-bottom: 10px;
        }
        
        .btn-reporte {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        
        .btn-reporte:hover {
            background-color: #218838;
        }
        
        /* Estilos móviles */
        @media (max-width: 768px) {
            .btn {
                padding: 8px 5px;
            }
            
            .filtro-desplegable {
                width: 250px;
                left: -50px;
            }
            
            .dropdown-contenido {
                right: -50px;
                width: 200px;
            }
            
            .header-logo {
                height: 30px;
                top: 10px;
                left: 10px;
            }
            
            .table td, .table th {
                padding: 8px 5px;
            }
            
            .botones-container {
                margin-top: 50px;
            }
            
            .col-md-2 {
                padding: 0 3px;
            }
        }
        
        .btn {
            padding: 8px 5px;
            transition: all 0.3s ease;
            border: none;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .btn:hover {
            background-color: #13866f !important;
            transform: scale(1.02);
        }
        
        .rounded-0 {
            border-radius: 0 !important;
        }
        
        .row.no-gutters {
            margin-right: 0;
            margin-left: 0;
        }
        
        .row.no-gutters > [class^="col-"] {
            padding-right: 0;
            padding-left: 0;
        }
        
        .botones-container {
            margin-top: 60px;
        }
        
        /* Nuevos estilos añadidos */
        .header-container {
            background-color: #ffffff; /* Cambiado a blanco */
            padding: 15px 0;
            margin-bottom: 20px;
            position: relative;
            width: 100%;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .no-results {
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin: 10px 0;
        }

        .no-results i {
            margin-bottom: 10px;
            color: #6c757d;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Asegurar que la tabla ocupe el 100% en móviles */
        @media (max-width: 576px) {
            .container-fluid {
                padding: 0;
            }
            
            .card {
                border-radius: 0;
            }
            
            .table {
                width: 100%;
                margin-bottom: 0;
            }
            
            .table thead th {
                padding: 8px 5px;
            }
            
            .table td {
                padding: 8px 5px;
            }
            
            .filtro-desplegable {
                width: 200px;
                left: -100px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-0">
        <div class="row">
            <div class="col-md-12 p-0">
                <div class="card shadow">
                    <!-- Contenedor con fondo blanco para logo y botones -->
                    <div class="header-container">
                        <img src="Logo.svg" alt="Logo" class="header-logo">
                        
                        <div class="container botones-container">
                            <div class="row no-gutters">
                                <!-- Botón Auditoría Caja Facturación -->
                                <div class="col-md-2 col-6 mb-2 px-1">
                                    <button 
                                        type="button" 
                                        class="btn btn-block text-white" 
                                        style="background-color: #16a085;"
                                        onclick="window.location.href='auditoria_caja_facturacion.php'"
                                    >
                                        <i class="fas fa-file-invoice-dollar"></i> Caja Facturación
                                    </button>
                                </div>

                                <!-- Botón Auditoría Caja Chica -->
                                <div class="col-md-2 col-6 mb-2 px-1">
                                    <button 
                                        type="button" 
                                        class="btn btn-block text-white" 
                                        style="background-color: #16a085;"
                                        onclick="window.location.href='auditoria_caja_chica.php'"
                                    >
                                        <i class="fas fa-money-bill-wave"></i> Caja Chica
                                    </button>
                                </div>

                                <!-- Botón Auditoría Inventario -->
                                <div class="col-md-2 col-6 mb-2 px-1">
                                    <button 
                                        type="button" 
                                        class="btn btn-block text-white" 
                                        style="background-color: #16a085;"
                                        onclick="window.location.href='auditoria_inventario.php'"
                                    >
                                        <i class="fas fa-boxes"></i> Auditoria Inventario
                                    </button>
                                </div>

                                <!-- Botón Faltante Inventario -->
                                <div class="col-md-2 col-6 mb-2 px-1">
                                    <button 
                                        type="button" 
                                        class="btn btn-block text-white" 
                                        style="background-color: #16a085;"
                                        onclick="window.location.href='faltante_inventario.php'"
                                    >
                                        <i class="fas fa-exclamation-triangle"></i> Faltante Inventario.
                                    </button>
                                </div>

                                <!-- Botón Personal -->
                                <div class="col-md-2 col-6 mb-2 px-1">
                                    <button 
                                        type="button" 
                                        class="btn btn-block text-white" 
                                        style="background-color: #16a085;"
                                        onclick="window.location.href='personal.php'"
                                    >
                                        <i class="fas fa-users"></i> Personal
                                    </button>
                                </div>

                                <!-- Botón Generar Reporte -->
                                <div class="col-md-2 col-6 mb-2 px-1">
                                    <div class="dropdown-reporte">
                                        <button class="btn btn-block text-white" style="background-color: #16a085;">
                                            <i class="fas fa-file-excel"></i> Reporte
                                        </button>
                                        <div class="dropdown-contenido">
                                            <form method="get" action="">
                                                <div class="form-group-reporte">
                                                    <label>Mes:</label>
                                                    <select name="mes" class="form-control" required>
                                                        <option value="1">Enero</option>
                                                        <option value="2">Febrero</option>
                                                        <option value="3">Marzo</option>
                                                        <option value="4">Abril</option>
                                                        <option value="5">Mayo</option>
                                                        <option value="6">Junio</option>
                                                        <option value="7">Julio</option>
                                                        <option value="8">Agosto</option>
                                                        <option value="9">Septiembre</option>
                                                        <option value="10">Octubre</option>
                                                        <option value="11">Noviembre</option>
                                                        <option value="12">Diciembre</option>
                                                    </select>
                                                </div>
                                                <div class="form-group-reporte">
                                                    <label>Año:</label>
                                                    <select name="anio" class="form-control" required>
                                                        <?php foreach ($anios as $anio): ?>
                                                            <option value="<?= $anio['anio'] ?>"><?= $anio['anio'] ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group-reporte">
                                                    <label>Sucursal:</label>
                                                    <select name="sucursal_reporte" class="form-control">
                                                        <option value="">Todas</option>
                                                        <?php foreach ($sucursales as $suc): ?>
                                                            <option value="<?= htmlspecialchars($suc['name']) ?>"><?= htmlspecialchars($suc['name']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" name="generar_reporte" value="1" class="btn-reporte btn-block">
                                                    <i class="fas fa-download"></i> Descargar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="get">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="filtro-header">
                                            <div class="d-flex align-items-center">
                                                <span style="font-size: 14px;">Fecha</span>
                                                <i class="fas fa-caret-down filtro-icono"></i>
                                            </div>
                                            <div class="filtro-desplegable">
                                                <div class="form-group">
                                                    <label>Seleccionar Fecha</label>
                                                    <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($filtros['fecha']) ?>">
                                                </div>
                                                <div class="filtro-acciones">
                                                    <button type="button" class="btn btn-sm btn-secondary cerrar-filtro">Cerrar</button>
                                                    <button type="submit" class="btn btn-sm btn-primary">Aplicar</button>
                                                </div>
                                            </div>
                                        </th>
                                        <th class="filtro-header">
                                            <div class="d-flex align-items-center">
                                                <span style="font-size: 14px;">Sucursal</span>
                                                <i class="fas fa-caret-down filtro-icono"></i>
                                            </div>
                                            <div class="filtro-desplegable">
                                                <div class="form-group">
                                                    <label>Seleccione Sucursal</label>
                                                    <select class="form-select" name="sucursal">
                                                        <option value="">Todas</option>
                                                        <?php foreach ($sucursales as $suc): ?>
                                                            <option value="<?= htmlspecialchars($suc['name']) ?>" <?= $suc['name'] == $filtros['sucursal'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($suc['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="filtro-acciones">
                                                    <button type="button" class="btn btn-sm btn-secondary cerrar-filtro">Cerrar</button>
                                                    <button type="submit" class="btn btn-sm btn-primary">Aplicar</button>
                                                </div>
                                            </div>
                                        </th>
                                        <th class="filtro-header">
                                            <div class="d-flex align-items-center">
                                                <span style="font-size: 14px;">Tipo</span>
                                                <i class="fas fa-caret-down filtro-icono"></i>
                                            </div>
                                            <div class="filtro-desplegable">
                                                <div class="form-group">
                                                    <label>Seleccione Tipo</label>
                                                    <select class="form-select" name="tipo">
                                                        <option value="">Todos</option>
                                                        <option value="caja_chica" <?= $filtros['tipo'] == 'caja_chica' ? 'selected' : '' ?>>Caja Chica</option>
                                                        <option value="facturacion" <?= $filtros['tipo'] == 'facturacion' ? 'selected' : '' ?>>Facturación</option>
                                                        <option value="inventario" <?= $filtros['tipo'] == 'inventario' ? 'selected' : '' ?>>Inventario</option>
                                                    </select>
                                                </div>
                                                <div class="filtro-acciones">
                                                    <button type="button" class="btn btn-sm btn-secondary cerrar-filtro">Cerrar</button>
                                                    <button type="submit" class="btn btn-sm btn-primary">Aplicar</button>
                                                </div>
                                            </div>
                                        </th>
                                        <th class="text-end" style="font-size: 14px;">Faltante</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($auditorias)): ?>
                                        <tr>
                                            <td colspan="5">
                                                <div class="no-results">
                                                    <i class="fas fa-search"></i>
                                                    <h5>No se encontraron auditorías</h5>
                                                    <p>Intenta con otros criterios de búsqueda</p>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limpiarFiltros()">
                                                        Limpiar filtros
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($auditorias as $aud): ?>
                                            <tr>
                                                <td><?= formatFechaEspanol($aud['fecha_hora']) ?></td>
                                                <td><?= htmlspecialchars($aud['sucursal']) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $aud['tipo_auditoria'] ?> rounded-pill" style="font-size: 12px;">
                                                        <?= ucfirst(str_replace('_', ' ', $aud['tipo_auditoria'])) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end text-danger">
                                                    C$ <?= number_format(abs($aud['monto']), 2) ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $pagina_detalle = [
                                                        'caja_chica' => 'ver_auditorias_caja_chica.php',
                                                        'facturacion' => 'ver_auditorias_facturacion.php',
                                                        'inventario' => 'ver_auditorias_inventario.php'
                                                    ][$aud['tipo_auditoria']];
                                                    ?>
                                                    <a href="<?= $pagina_detalle ?>?id=<?= $aud['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                    
                    <!-- Paginación -->
                    <div class="card-footer d-flex justify-content-between align-items-center py-2">
                        <div class="text-muted small">
                            Mostrando <?= count($auditorias) ?> registros
                        </div>
                        <nav>
                            <ul class="pagination mb-0">
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" tabindex="-1">Anterior</a>
                                </li>
                                <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                <li class="page-item">
                                    <a class="page-link" href="#">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para limpiar todos los filtros
        function limpiarFiltros() {
            // Redirigir a la misma página sin parámetros de filtro
            window.location.href = window.location.pathname;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Manejar clic en los encabezados de filtro
            const filtroHeaders = document.querySelectorAll('.filtro-header');
            
            filtroHeaders.forEach(header => {
                header.addEventListener('click', function(e) {
                    // Evitar que se active cuando se hace clic en los elementos del filtro
                    if (e.target.closest('.filtro-desplegable, .cerrar-filtro, .btn')) {
                        return;
                    }
                    
                    // Cerrar otros filtros abiertos
                    document.querySelectorAll('.filtro-header').forEach(h => {
                        if (h !== header) {
                            h.classList.remove('active');
                        }
                    });
                    
                    // Alternar el filtro actual
                    header.classList.toggle('active');
                });
            });
            
            // Cerrar filtros al hacer clic en el botón Cerrar
            document.querySelectorAll('.cerrar-filtro').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.closest('.filtro-header').classList.remove('active');
                });
            });
            
            // Cerrar filtros al hacer clic fuera de ellos
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.filtro-header, .filtro-desplegable')) {
                    document.querySelectorAll('.filtro-header').forEach(header => {
                        header.classList.remove('active');
                    });
                }
            });

            // Nueva función para mantener abierto el filtro al hacer clic en el botón Aplicar
            document.querySelectorAll('.filtro-desplegable .btn-primary').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    this.closest('.filtro-header').classList.remove('active');
                });
            });
            
            // Ajustar tamaño de los filtros en móviles
            function adjustFiltersForMobile() {
                if (window.innerWidth < 768) {
                    document.querySelectorAll('.filtro-desplegable').forEach(filter => {
                        filter.style.width = (window.innerWidth - 40) + 'px';
                        filter.style.left = '50%';
                        filter.style.transform = 'translateX(-50%)';
                    });
                } else {
                    document.querySelectorAll('.filtro-desplegable').forEach(filter => {
                        filter.style.width = '280px';
                        filter.style.left = '0';
                        filter.style.transform = 'none';
                    });
                }
            }
            
            // Ejecutar al cargar y al redimensionar
            adjustFiltersForMobile();
            window.addEventListener('resize', adjustFiltersForMobile);
        });
    </script>
</body>
</html>
