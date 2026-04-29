<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/database/conexion.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');

verificarAutenticacion();

$sucursal = $_GET['sucursal'] ?? '';
$desde    = $_GET['desde']    ?? '';
$hasta    = $_GET['hasta']    ?? '';
$operario = $_GET['operario'] ?? '';
$estado   = $_GET['estado']   ?? ''; // '', 'Pendiente', 'Aprobado', 'Denegado'

$usuarioInfo  = obtenerUsuarioActual();
$cargoUsuario = $usuarioInfo['CodNivelesCargos'] ?? 0;
$esAdmin      = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] == 'admin';

if (!tienePermiso('horas_extras_manual', 'vista', $cargoUsuario) && !$esAdmin) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para ver estos datos.']);
    exit;
}

// Regla especial para Líderes (5 o 43)
if (in_array($cargoUsuario, [5, 43])) {
    $misSucursales = obtenerSucursalesLider($_SESSION['usuario_id']);
    $sucursal = $misSucursales[0]['codigo'] ?? 'NINGUNA';
}

// Si no hay fechas, usar el mes actual por defecto
if (empty($desde)) $desde = date('Y-m-01');
if (empty($hasta)) $hasta = date('Y-m-t');

try {
    // Construir columna de día dinámicamente para horarios
    // Nota: como la fecha es de la tabla hem, necesitamos calcular el día para el JOIN.
    // Usamos subconsultas para obtener horario y marcación por cada registro.
    $sql = "
        SELECT 
            hem.*,
            CONCAT(o.Nombre, ' ', IFNULL(o.Nombre2, ''), ' ', o.Apellido, ' ', IFNULL(o.Apellido2, '')) as operario_nombre,
            s.nombre as sucursal_nombre,
            CONCAT(IFNULL(r.Nombre, 'Sistema'), ' ', IFNULL(r.Apellido, '')) as registrador_nombre,
            c.CodContrato,

            -- Hora marcada entrada y salida del día de la solicitud
            marc.hora_ingreso as hora_entrada_marcada,
            marc.hora_salida  as hora_salida_marcada,

            -- Horario programado según día de la semana
            CASE DAYOFWEEK(hem.fecha)
                WHEN 2 THEN COALESCE(hso.lunes_entrada,   hs.lunes_entrada)
                WHEN 3 THEN COALESCE(hso.martes_entrada,  hs.martes_entrada)
                WHEN 4 THEN COALESCE(hso.miercoles_entrada, hs.miercoles_entrada)
                WHEN 5 THEN COALESCE(hso.jueves_entrada,  hs.jueves_entrada)
                WHEN 6 THEN COALESCE(hso.viernes_entrada, hs.viernes_entrada)
                WHEN 7 THEN COALESCE(hso.sabado_entrada,  hs.sabado_entrada)
                WHEN 1 THEN COALESCE(hso.domingo_entrada, hs.domingo_entrada)
            END as hora_entrada_programada,

            CASE DAYOFWEEK(hem.fecha)
                WHEN 2 THEN COALESCE(hso.lunes_salida,   hs.lunes_salida)
                WHEN 3 THEN COALESCE(hso.martes_salida,  hs.martes_salida)
                WHEN 4 THEN COALESCE(hso.miercoles_salida, hs.miercoles_salida)
                WHEN 5 THEN COALESCE(hso.jueves_salida,  hs.jueves_salida)
                WHEN 6 THEN COALESCE(hso.viernes_salida, hs.viernes_salida)
                WHEN 7 THEN COALESCE(hso.sabado_salida,  hs.sabado_salida)
                WHEN 1 THEN COALESCE(hso.domingo_salida, hs.domingo_salida)
            END as hora_salida_programada

        FROM horas_extras_manual hem
        JOIN Operarios o ON hem.cod_operario = o.CodOperario
        LEFT JOIN sucursales s ON hem.cod_sucursal = s.codigo
        LEFT JOIN Operarios r ON hem.registrado_por = r.CodOperario
        LEFT JOIN Contratos c ON hem.cod_contrato = c.CodContrato

        -- Marcación real del día
        LEFT JOIN marcaciones marc
            ON marc.CodOperario = hem.cod_operario
            AND marc.fecha = hem.fecha

        -- Semana sistema para obtener horario de esa fecha
        LEFT JOIN SemanasSistema ss
            ON hem.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin

        -- Horario de operaciones (prioritario)
        LEFT JOIN HorariosSemanalesOperaciones hso
            ON hso.cod_operario = hem.cod_operario
            AND hso.id_semana_sistema = ss.id

        -- Horario general (fallback)
        LEFT JOIN HorariosSemanales hs
            ON hs.cod_operario = hem.cod_operario
            AND hs.id_semana_sistema = ss.id

        WHERE hem.fecha BETWEEN ? AND ?
    ";

    $params = [$desde, $hasta];

    if (!empty($sucursal)) {
        $sql .= " AND hem.cod_sucursal = ?";
        $params[] = $sucursal;
    }

    if (!empty($operario)) {
        $sql .= " AND hem.cod_operario = ?";
        $params[] = $operario;
    }

    if (!empty($estado)) {
        $sql .= " AND hem.estado = ?";
        $params[] = $estado;
    }

    $sql .= " ORDER BY hem.fecha DESC, o.Nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
