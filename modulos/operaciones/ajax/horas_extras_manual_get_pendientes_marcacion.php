<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

verificarAutenticacion();

$sucursal = $_GET['sucursal'] ?? '';
$fecha = $_GET['fecha'] ?? date('Y-m-d');

$usuarioInfo = obtenerUsuarioActual();
$cargoUsuario = $usuarioInfo['CodNivelesCargos'] ?? 0;
$esAdmin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] == 'admin';

if (!tienePermiso('horas_extras_manual', 'solicitar', $cargoUsuario) && !$esAdmin) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para solicitar horas extras.']);
    exit;
}

// Regla especial para Líderes (5 o 43)
if (in_array($cargoUsuario, [5, 43])) {
    $misSucursales = obtenerSucursalesLider($_SESSION['usuario_id']);
    $sucursal = $misSucursales[0]['codigo'] ?? 'NINGUNA';
}

try {
    // Lógica para determinar el día de la semana y comparar la hora de salida
    $diaSemana = strtolower(date('l', strtotime($fecha)));
    // Mapeo de inglés a español para las columnas de horario
    $mapDias = [
        'monday' => 'lunes',
        'tuesday' => 'martes',
        'wednesday' => 'miercoles',
        'thursday' => 'jueves',
        'friday' => 'viernes',
        'saturday' => 'sabado',
        'sunday' => 'domingo'
    ];
    $colSalida = $mapDias[$diaSemana] . '_salida';

    // Validación defensiva
    $validCols = ['lunes_salida', 'martes_salida', 'miercoles_salida', 'jueves_salida', 'viernes_salida', 'sabado_salida', 'domingo_salida'];
    if (!in_array($colSalida, $validCols)) {
        echo json_encode(['success' => false, 'message' => "Día no válido: $diaSemana"]);
        exit;
    }

    // Buscar colaboradores
    // Se asume que marcaciones.hora_salida > horario.salida
    $sql = "
        SELECT 
            m.CodOperario,
            CONCAT(o.Nombre, ' ', IFNULL(o.Nombre2, ''), ' ', o.Apellido, ' ', IFNULL(o.Apellido2, '')) as operario_nombre,
            m.hora_salida,
            m.fecha,
            m.sucursal_codigo,
            s.nombre as sucursal_nombre,
            m.id as id_marcacion,
            COALESCE(hso.$colSalida, hs.$colSalida) as hora_salida_programada
        FROM marcaciones m
        JOIN Operarios o ON m.CodOperario = o.CodOperario
        JOIN sucursales s ON m.sucursal_codigo = s.codigo
        JOIN SemanasSistema ss ON m.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        -- Intentar unir con HorariosSemanalesOperaciones primero, luego HorariosSemanales
        -- Se une por operario y semana sistema para obtener el horario de esa semana
        LEFT JOIN HorariosSemanalesOperaciones hso ON m.CodOperario = hso.cod_operario AND ss.id = hso.id_semana_sistema
        LEFT JOIN HorariosSemanales hs ON m.CodOperario = hs.cod_operario AND ss.id = hs.id_semana_sistema
        WHERE m.fecha = ?
    ";

    $params = [$fecha];

    if (!empty($sucursal)) {
        $sql .= " AND m.sucursal_codigo = ?";
        $params[] = $sucursal;
    }

    // Solo incluir si la hora de salida de la marcación es posterior a la planificada
    $sql .= " AND (
        (hso.$colSalida IS NOT NULL AND m.hora_salida > hso.$colSalida)
        OR (hs.$colSalida IS NOT NULL AND m.hora_salida > hs.$colSalida)
    )";

    // Filtrar los que ya tienen una solicitud (Pendiente o Aprobado)
    $sql .= " 
        AND NOT EXISTS (
            SELECT 1 FROM horas_extras_manual hem 
            WHERE hem.cod_operario = m.CodOperario AND hem.fecha = m.fecha
        )
    ";

    $sql .= " GROUP BY m.id ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    // Check total marcations for this date
    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM marcaciones WHERE fecha = ?");
    $stmtCheck->execute([$fecha]);
    $totalMarcaciones = $stmtCheck->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => $data,
        'debug' => [
            'total_marcaciones_dia' => $totalMarcaciones,
            'fecha_consultada' => $fecha,
            'dia_semana' => $diaSemana,
            'columna_salida' => $colSalida
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
