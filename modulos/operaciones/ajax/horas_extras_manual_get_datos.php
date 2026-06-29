<?php
require_once '../../../core/auth/auth.php';
require_once '../../../core/permissions/permissions.php';

header('Content-Type: application/json');


$sucursal = $_GET['sucursal'] ?? '';
$desde = $_GET['desde'] ?? '';
$hasta = $_GET['hasta'] ?? '';
$operario = $_GET['operario'] ?? '';
$estado = $_GET['estado'] ?? ''; // '', 'Pendiente', 'Aprobado', 'Denegado'

$usuarioInfo = obtenerUsuarioActual();
$cargoUsuario = $usuarioInfo['CodNivelesCargos'] ?? 0;

if (!tienePermiso('horas_extras_manual', 'vista', $cargoUsuario)) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para ver estos datos.']);
    exit;
}

// Regla de permisos para filtrar sucursales
$puedeVerTodo = tienePermiso('horas_extras_manual', 'ver_todo', $cargoUsuario);
$puedeFiltroAll = tienePermiso('horas_extras_manual', 'filtro_todas_tiendas', $cargoUsuario);

$codigosSucursales = []; // se llena solo si el usuario está restringido

if (!$puedeVerTodo && !$puedeFiltroAll) {
    // Obtener todas las sucursales asignadas al usuario (funciona para cualquier cargo de líder)
    $misSucursales = obtenerSucursalesUsuario($_SESSION['usuario_id']);
    $codigosSucursales = array_column($misSucursales, 'codigo');

    if (empty($codigosSucursales)) {
        // Sin sucursales asignadas: forzar resultado vacío
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    if (!empty($sucursal)) {
        // Si pidió una sucursal, validar que sea una de las permitidas
        if (!in_array($sucursal, $codigosSucursales)) {
            $sucursal = $codigosSucursales[0]; // fallback a la primera
        }
        // $sucursal queda como string; se usa con = ? más abajo
    } else {
        // Sin filtro de sucursal: se restringirá con IN(...) más abajo
        $sucursal = '__MULTIPLE__'; // marcador para usar IN
    }
}


// Si no hay fechas, usar el mes actual por defecto
if (empty($desde))
    $desde = date('Y-m-01');
if (empty($hasta))
    $hasta = date('Y-m-t');

try {
    // Construir columna de día dinámicamente para horarios
    // Nota: como la fecha es de la tabla hem, necesitamos calcular el día para el JOIN.
    // Usamos subconsultas para obtener horario y marcación por cada registro.
    $sql = "
        SELECT
            hem.*,
            CONCAT_WS(' ',
                NULLIF(TRIM(o.Nombre),    ''),
                NULLIF(TRIM(o.Nombre2),   ''),
                NULLIF(TRIM(o.Apellido),  ''),
                NULLIF(TRIM(o.Apellido2), '')
            ) AS operario_nombre,
            s.nombre AS sucursal_nombre,
            CONCAT_WS(' ',
                NULLIF(TRIM(r.Nombre),   ''),
                NULLIF(TRIM(r.Apellido), '')
            ) AS registrador_nombre,
            c.CodContrato,

            -- Marcación real: subconsulta para evitar multiplicar filas
            (SELECT marc.hora_ingreso
             FROM marcaciones marc
             WHERE marc.CodOperario = hem.cod_operario
               AND marc.fecha = hem.fecha
             LIMIT 1) AS hora_entrada_marcada,

            (SELECT marc.hora_salida
             FROM marcaciones marc
             WHERE marc.CodOperario = hem.cod_operario
               AND marc.fecha = hem.fecha
             LIMIT 1) AS hora_salida_marcada,

            -- Horario programado: subconsultas para evitar multiplicar filas
            (SELECT CASE DAYOFWEEK(hem.fecha)
                WHEN 2 THEN COALESCE(hso2.lunes_entrada,   hs2.lunes_entrada)
                WHEN 3 THEN COALESCE(hso2.martes_entrada,  hs2.martes_entrada)
                WHEN 4 THEN COALESCE(hso2.miercoles_entrada, hs2.miercoles_entrada)
                WHEN 5 THEN COALESCE(hso2.jueves_entrada,  hs2.jueves_entrada)
                WHEN 6 THEN COALESCE(hso2.viernes_entrada, hs2.viernes_entrada)
                WHEN 7 THEN COALESCE(hso2.sabado_entrada,  hs2.sabado_entrada)
                WHEN 1 THEN COALESCE(hso2.domingo_entrada, hs2.domingo_entrada)
             END
             FROM SemanasSistema ss2
             LEFT JOIN HorariosSemanalesOperaciones hso2
                 ON hso2.cod_operario = hem.cod_operario
                 AND hso2.id_semana_sistema = ss2.id
             LEFT JOIN HorariosSemanales hs2
                 ON hs2.cod_operario = hem.cod_operario
                 AND hs2.id_semana_sistema = ss2.id
             WHERE hem.fecha BETWEEN ss2.fecha_inicio AND ss2.fecha_fin
             LIMIT 1) AS hora_entrada_programada,

            (SELECT CASE DAYOFWEEK(hem.fecha)
                WHEN 2 THEN COALESCE(hso2.lunes_salida,   hs2.lunes_salida)
                WHEN 3 THEN COALESCE(hso2.martes_salida,  hs2.martes_salida)
                WHEN 4 THEN COALESCE(hso2.miercoles_salida, hs2.miercoles_salida)
                WHEN 5 THEN COALESCE(hso2.jueves_salida,  hs2.jueves_salida)
                WHEN 6 THEN COALESCE(hso2.viernes_salida, hs2.viernes_salida)
                WHEN 7 THEN COALESCE(hso2.sabado_salida,  hs2.sabado_salida)
                WHEN 1 THEN COALESCE(hso2.domingo_salida, hs2.domingo_salida)
             END
             FROM SemanasSistema ss2
             LEFT JOIN HorariosSemanalesOperaciones hso2
                 ON hso2.cod_operario = hem.cod_operario
                 AND hso2.id_semana_sistema = ss2.id
             LEFT JOIN HorariosSemanales hs2
                 ON hs2.cod_operario = hem.cod_operario
                 AND hs2.id_semana_sistema = ss2.id
             WHERE hem.fecha BETWEEN ss2.fecha_inicio AND ss2.fecha_fin
             LIMIT 1) AS hora_salida_programada

        FROM horas_extras_manual hem
        JOIN Operarios o ON hem.cod_operario = o.CodOperario
        LEFT JOIN sucursales s ON hem.cod_sucursal = s.codigo
        LEFT JOIN Operarios r ON hem.registrado_por = r.CodOperario
        LEFT JOIN Contratos c ON hem.cod_contrato = c.CodContrato

        WHERE hem.fecha BETWEEN ? AND ?
    ";

    $params = [$desde, $hasta];

    if (!empty($sucursal) && $sucursal !== '__MULTIPLE__') {
        $sql .= " AND hem.cod_sucursal = ?";
        $params[] = $sucursal;
    } elseif ($sucursal === '__MULTIPLE__' && !empty($codigosSucursales)) {
        $placeholders = implode(',', array_fill(0, count($codigosSucursales), '?'));
        $sql .= " AND hem.cod_sucursal IN ($placeholders)";
        foreach ($codigosSucursales as $cod) {
            $params[] = $cod;
        }
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
