<?php

/**
 * Funciones auxiliares para el módulo de líderes
 * Este archivo contiene todas las funciones de negocio para el dashboard de líderes
 */

/**
 * Obtiene el total de tardanzas pendientes de reportar para un líder de sucursal
 */
function obtenerTardanzasPendientesLider($codOperario)
{
    global $conn;

    // Determinar el periodo a revisar según el día del mes
    $hoy = new DateTime();
    $diaMes = (int) $hoy->format('d');
    $diasRestantes = calcularDiasRestantesReporte();

    if ($diaMes <= 2) {
        // Días 1-2: revisar mes anterior
        $mesRevisar = new DateTime('first day of last month');
        $fechaDesde = $mesRevisar->format('Y-m-01');
        $fechaHasta = $mesRevisar->format('Y-m-t');
        $periodo = 'mes_anterior';
        $mesNombre = obtenerMesEspanol($mesRevisar) . ' ' . $mesRevisar->format('Y');
    } else {
        // Días 3+: revisar mes actual
        $fechaDesde = $hoy->format('Y-m-01');
        $fechaHasta = $hoy->format('Y-m-t');
        $periodo = 'mes_actual';
        $mesNombre = obtenerMesEspanol($hoy) . ' ' . $hoy->format('Y');
    }

    // Obtener sucursales del líder
    $sucursalesLider = obtenerSucursalesLider($codOperario);
    if (empty($sucursalesLider)) {
        return [
            'total' => 0,
            'color' => 'verde',
            'texto' => 'Sin tardanzas pendientes',
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_tardanzas' => '../operaciones/tardanzas_manual.php',
            'mes_nombre' => $mesNombre,
            'detalles' => []
        ];
    }

    $sucursalesCodigos = array_column($sucursalesLider, 'codigo');
    $placeholders = implode(',', array_fill(0, count($sucursalesCodigos), '?'));

    // Consulta para obtener tardanzas reales no reportadas CON DETALLES
    $sql = "SELECT 
        m.CodOperario,
        m.fecha,
        m.sucursal_codigo,
        s.nombre as sucursal_nombre,
        CONCAT(
            IFNULL(o.Nombre, ''), ' ', 
            IFNULL(o.Nombre2, ''), ' ', 
            IFNULL(o.Apellido, ''), ' ', 
            IFNULL(o.Apellido2, '')
        ) AS nombre_completo,
        m.hora_ingreso,
        CASE DAYOFWEEK(m.fecha)
            WHEN 2 THEN hso.lunes_entrada
            WHEN 3 THEN hso.martes_entrada
            WHEN 4 THEN hso.miercoles_entrada
            WHEN 5 THEN hso.jueves_entrada
            WHEN 6 THEN hso.viernes_entrada
            WHEN 7 THEN hso.sabado_entrada
            WHEN 1 THEN hso.domingo_entrada
        END as hora_programada,
        TIMESTAMPDIFF(
            MINUTE, 
            CASE DAYOFWEEK(m.fecha)
                WHEN 2 THEN hso.lunes_entrada
                WHEN 3 THEN hso.martes_entrada
                WHEN 4 THEN hso.miercoles_entrada
                WHEN 5 THEN hso.jueves_entrada
                WHEN 6 THEN hso.viernes_entrada
                WHEN 7 THEN hso.sabado_entrada
                WHEN 1 THEN hso.domingo_entrada
            END,
            m.hora_ingreso
        ) as minutos_tardanza
    FROM marcaciones m
    INNER JOIN HorariosSemanalesOperaciones hso ON m.CodOperario = hso.cod_operario 
        AND m.sucursal_codigo = hso.cod_sucursal
    INNER JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
    INNER JOIN sucursales s ON m.sucursal_codigo = s.codigo
    INNER JOIN Operarios o ON m.CodOperario = o.CodOperario
    WHERE m.sucursal_codigo IN ($placeholders)
        AND m.fecha BETWEEN ? AND ?
        AND m.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        AND m.hora_ingreso IS NOT NULL
        AND TIMESTAMPDIFF(
            MINUTE, 
            CASE DAYOFWEEK(m.fecha)
                WHEN 2 THEN hso.lunes_entrada
                WHEN 3 THEN hso.martes_entrada
                WHEN 4 THEN hso.miercoles_entrada
                WHEN 5 THEN hso.jueves_entrada
                WHEN 6 THEN hso.viernes_entrada
                WHEN 7 THEN hso.sabado_entrada
                WHEN 1 THEN hso.domingo_entrada
            END,
            m.hora_ingreso
        ) > 1
        AND NOT EXISTS (
            SELECT 1 FROM TardanzasManuales tm
            WHERE tm.cod_operario = m.CodOperario
                AND tm.fecha_tardanza = m.fecha
                AND tm.cod_sucursal = m.sucursal_codigo
        )
        -- Solo el primer marcaje (por id) por operario/sucursal/fecha
        AND m.id = (
            SELECT MIN(m2.id)
            FROM marcaciones m2
            WHERE m2.CodOperario = m.CodOperario
                AND m2.sucursal_codigo = m.sucursal_codigo
                AND m2.fecha = m.fecha
                AND m2.hora_ingreso IS NOT NULL
        )
    ORDER BY m.fecha DESC, m.sucursal_codigo, nombre_completo
    ";

    $params = array_merge($sucursalesCodigos, [$fechaDesde, $fechaHasta]);

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $detalles = $stmt->fetchAll();

        $totalTardanzas = count($detalles);
        $color = determinarColorTardanzas($totalTardanzas, $diasRestantes);

        // Construir URL con parámetros
        $urlTardanzas = "../operaciones/tardanzas_manual.php?" . http_build_query([
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'sucursales' => implode(',', $sucursalesCodigos),
            'modo' => 'lider',
            'periodo' => $periodo
        ]);

        return [
            'total' => $totalTardanzas,
            'color' => $color,
            'texto' => obtenerTextoIndicador($totalTardanzas, $periodo, $mesNombre),
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_tardanzas' => $urlTardanzas,
            'mes_nombre' => $mesNombre,
            'sucursales' => $sucursalesCodigos,
            'detalles' => $detalles
        ];
    } catch (Exception $e) {
        error_log("Error obteniendo tardanzas pendientes: " . $e->getMessage());

        // URL por defecto sin parámetros en caso de error
        $urlTardanzas = "../operaciones/tardanzas_manual.php";

        return [
            'total' => 0,
            'color' => 'verde',
            'texto' => 'Error en cálculo',
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_tardanzas' => $urlTardanzas,
            'mes_nombre' => $mesNombre,
            'detalles' => []
        ];
    }
}

/**
 * Obtiene el total de faltas/ausencias pendientes de reportar para un líder de sucursal
 */
function obtenerFaltasPendientesLider($codOperario)
{
    global $conn;

    // Determinar el periodo a revisar según el día del mes
    $hoy = new DateTime();
    $diaMes = (int) $hoy->format('d');
    $diasRestantes = calcularDiasRestantesReporteFaltas();

    if ($diaMes <= 1) {
        // Día 1: revisar mes anterior
        $mesRevisar = new DateTime('first day of last month');
        $fechaDesde = $mesRevisar->format('Y-m-01');
        $fechaHasta = $mesRevisar->format('Y-m-t');
        $periodo = 'mes_anterior';
        $mesNombre = obtenerMesEspanol($mesRevisar) . ' ' . $mesRevisar->format('Y');
    } else {
        // Días 2+: revisar mes actual (hasta ayer para evitar futuros)
        $fechaDesde = $hoy->format('Y-m-01');
        $fechaHasta = date('Y-m-d', strtotime('-1 day')); // Solo hasta ayer
        $periodo = 'mes_actual';
        $mesNombre = obtenerMesEspanol($hoy) . ' ' . $hoy->format('Y');
    }

    // Obtener sucursales del líder
    $sucursalesLider = obtenerSucursalesLider($codOperario);
    if (empty($sucursalesLider)) {
        return [
            'total' => 0,
            'color' => 'verde',
            'texto' => 'Sin faltas pendientes',
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_faltas' => 'faltas_manual.php',
            'mes_nombre' => $mesNombre,
            'detalles' => []
        ];
    }

    $sucursalesCodigos = array_column($sucursalesLider, 'codigo');
    $placeholders = implode(',', array_fill(0, count($sucursalesCodigos), '?'));

    // Consulta CORREGIDA para obtener ausencias reales no reportadas
    $sql = "
        SELECT 
            hso.cod_operario,
            hso.cod_sucursal,
            s.nombre as sucursal_nombre,
            CONCAT(
                IFNULL(o.Nombre, ''), ' ', 
                IFNULL(o.Nombre2, ''), ' ', 
                IFNULL(o.Apellido, ''), ' ', 
                IFNULL(o.Apellido2, '')
            ) AS nombre_completo,
            h.fecha,
            -- Horario programado para ese día
            CASE DAYOFWEEK(h.fecha)
                WHEN 2 THEN hso.lunes_entrada
                WHEN 3 THEN hso.martes_entrada
                WHEN 4 THEN hso.miercoles_entrada
                WHEN 5 THEN hso.jueves_entrada
                WHEN 6 THEN hso.viernes_entrada
                WHEN 7 THEN hso.sabado_entrada
                WHEN 1 THEN hso.domingo_entrada
            END as hora_entrada_programada,
            CASE DAYOFWEEK(h.fecha)
                WHEN 2 THEN hso.lunes_salida
                WHEN 3 THEN hso.martes_salida
                WHEN 4 THEN hso.miercoles_salida
                WHEN 5 THEN hso.jueves_salida
                WHEN 6 THEN hso.viernes_salida
                WHEN 7 THEN hso.sabado_salida
                WHEN 1 THEN hso.domingo_salida
            END as hora_salida_programada,
            -- Estado del día
            CASE DAYOFWEEK(h.fecha)
                WHEN 2 THEN hso.lunes_estado
                WHEN 3 THEN hso.martes_estado
                WHEN 4 THEN hso.miercoles_estado
                WHEN 5 THEN hso.jueves_estado
                WHEN 6 THEN hso.viernes_estado
                WHEN 7 THEN hso.sabado_estado
                WHEN 1 THEN hso.domingo_estado
            END as estado_dia
        FROM (
            -- Generar todas las fechas en el rango
            SELECT DATE(?) + INTERVAL (a.a + (10 * b.a)) DAY as fecha
            FROM 
            (SELECT 0 a UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
             UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
            (SELECT 0 a UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
             UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
            WHERE DATE(?) + INTERVAL (a.a + (10 * b.a)) DAY <= ?
        ) h
        INNER JOIN HorariosSemanalesOperaciones hso ON hso.cod_sucursal IN ($placeholders)
        INNER JOIN SemanasSistema ss ON hso.id_semana_sistema = ss.id
        INNER JOIN sucursales s ON hso.cod_sucursal = s.codigo
        INNER JOIN Operarios o ON hso.cod_operario = o.CodOperario
        WHERE h.fecha BETWEEN ? AND ?
        -- Verificar que la fecha esté dentro de la semana del horario
        AND h.fecha BETWEEN ss.fecha_inicio AND ss.fecha_fin
        -- Verificar que el día tenga horario programado activo
        AND (
            (DAYOFWEEK(h.fecha) = 2 AND hso.lunes_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 3 AND hso.martes_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 4 AND hso.miercoles_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 5 AND hso.jueves_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 6 AND hso.viernes_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 7 AND hso.sabado_estado = 'Activo') OR
            (DAYOFWEEK(h.fecha) = 1 AND hso.domingo_estado = 'Activo')
        )
        -- Excluir días que SÍ tienen marcación (entrada o salida)
        AND NOT EXISTS (
            SELECT 1 FROM marcaciones m
            WHERE m.CodOperario = hso.cod_operario
            AND m.sucursal_codigo = hso.cod_sucursal
            AND m.fecha = h.fecha
            AND (m.hora_ingreso IS NOT NULL OR m.hora_salida IS NOT NULL)
        )
        -- Excluir las que ya fueron reportadas como faltas manuales (sin importar estado)
        AND NOT EXISTS (
            SELECT 1 FROM faltas_manual fm
            WHERE fm.cod_operario = hso.cod_operario
            AND fm.fecha_falta = h.fecha
            AND fm.cod_sucursal = hso.cod_sucursal
        )
        -- Solo operarios activos
        AND o.Operativo = 1
        AND EXISTS (
            SELECT 1 FROM AsignacionNivelesCargos anc
            WHERE anc.CodOperario = o.CodOperario
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
        )
        ORDER BY h.fecha DESC, hso.cod_sucursal, nombre_completo
    ";

    $params = array_merge(
        [$fechaDesde, $fechaDesde, $fechaHasta], // Para la subquery de fechas
        $sucursalesCodigos,
        [$fechaDesde, $fechaHasta]
    );

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $detalles = $stmt->fetchAll();

        $totalFaltas = count($detalles);
        $color = determinarColorFaltas($totalFaltas, $diasRestantes);

        // Construir URL con parámetros
        $urlFaltas = "faltas_manual.php?" . http_build_query([
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'sucursales' => implode(',', $sucursalesCodigos),
            'modo' => 'lider',
            'periodo' => $periodo
        ]);

        return [
            'total' => $totalFaltas,
            'color' => $color,
            'texto' => obtenerTextoIndicadorFaltas($totalFaltas, $periodo, $mesNombre),
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_faltas' => $urlFaltas,
            'mes_nombre' => $mesNombre,
            'sucursales' => $sucursalesCodigos,
            'detalles' => $detalles
        ];
    } catch (Exception $e) {
        error_log("Error obteniendo faltas pendientes: " . $e->getMessage());

        // URL por defecto sin parámetros en caso de error
        $urlFaltas = "faltas_manual.php";

        return [
            'total' => 0,
            'color' => 'verde',
            'texto' => 'Error en cálculo',
            'periodo' => $periodo,
            'dias_restantes' => $diasRestantes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'url_faltas' => $urlFaltas,
            'mes_nombre' => $mesNombre,
            'detalles' => []
        ];
    }
}

/**
 * Obtiene el estado del horario pendiente de subir para un líder - VERSIÓN CORREGIDA
 */
function obtenerEstadoHorarioPendiente($codOperario)
{
    global $conn;

    // Obtener semana actual y siguiente
    $semanaActual = obtenerSemanaActual();
    $semanaSiguiente = obtenerSemanaPorNumero($semanaActual['numero_semana'] + 1);

    if (!$semanaSiguiente) {
        return [
            'estado' => 'no_disponible',
            'texto' => 'Semana siguiente no disponible',
            'color' => 'gris',
            'url' => 'programar_horarios_lider2.php',
            'semana_siguiente' => null,
            'dias_restantes' => 0,
            'periodo_activo' => false,
            'permite_modificacion' => false
        ];
    }

    // Obtener sucursales del líder
    $sucursalesLider = obtenerSucursalesLider($codOperario);
    if (empty($sucursalesLider)) {
        return [
            'estado' => 'sin_sucursales',
            'texto' => 'Sin sucursales asignadas',
            'color' => 'gris',
            'url' => 'programar_horarios_lider2.php',
            'semana_siguiente' => $semanaSiguiente,
            'dias_restantes' => 0,
            'periodo_activo' => false,
            'permite_modificacion' => false
        ];
    }

    // Determinar si estamos en período activo (lunes 00:00 a viernes 23:59 de la semana ACTUAL)
    $hoy = new DateTime('now', new DateTimeZone('America/Managua'));
    $lunesSemanaActual = new DateTime($semanaActual['fecha_inicio'], new DateTimeZone('America/Managua'));
    $viernesSemanaActual = clone $lunesSemanaActual;
    $viernesSemanaActual->modify('+4 days'); // Viernes de la semana actual

    // Establecer horarios para el período de edición
    $lunesSemanaActual->setTime(0, 0, 0); // Lunes 00:00:00
    $viernesSemanaActual->setTime(23, 59, 59); // Viernes 23:59:59

    $periodoActivo = ($hoy >= $lunesSemanaActual && $hoy <= $viernesSemanaActual);

    // Verificar si ya se subió el horario para alguna sucursal
    $horarioSubido = false;
    $sucursalesSinHorario = [];
    $sucursalesConHorario = [];

    foreach ($sucursalesLider as $sucursal) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM HorariosSemanales 
            WHERE id_semana_sistema = ? 
            AND cod_sucursal = ?
        ");
        $stmt->execute([$semanaSiguiente['id'], $sucursal['codigo']]);
        $result = $stmt->fetch();

        if ($result['total'] > 0) {
            $horarioSubido = true;
            $sucursalesConHorario[] = $sucursal;
        } else {
            $sucursalesSinHorario[] = $sucursal;
        }
    }

    // Calcular días restantes CORREGIDO
    $diasRestantes = 0;
    if ($periodoActivo) {
        $diferencia = $hoy->diff($viernesSemanaActual);
        $diasRestantes = $diferencia->days;

        // Si es viernes y estamos antes de las 23:59, mostrar 0 días (vence hoy)
        if ($diferencia->days == 0) {
            if ($hoy->format('H:i') <= '23:59') {
                $diasRestantes = 0; // Vence hoy
            } else {
                $diasRestantes = -1; // Ya venció
            }
        }
    }

    // Determinar si permite modificación (siempre que esté en período activo)
    $permiteModificacion = $periodoActivo;

    // Determinar estado y color
    if (!$periodoActivo) {
        // Fuera del período (sábado y domingo)
        $texto = 'Fuera del período de programación';
        if ($horarioSubido && empty($sucursalesSinHorario)) {
            $texto = 'Horario completo - Fuera de período';
        } elseif ($horarioSubido && !empty($sucursalesSinHorario)) {
            $texto = 'Horario parcial - Fuera de período';
        }

        return [
            'estado' => 'fuera_periodo',
            'texto' => $texto,
            'color' => 'gris',
            'url' => 'programar_horarios_lider2.php?semana=' . $semanaSiguiente['numero_semana'],
            'semana_siguiente' => $semanaSiguiente,
            'dias_restantes' => 0,
            'periodo_activo' => false,
            'sucursales_sin_horario' => $sucursalesSinHorario,
            'sucursales_con_horario' => $sucursalesConHorario,
            'permite_modificacion' => false
        ];
    }

    // DENTRO DEL PERÍODO ACTIVO (lunes a viernes)
    if ($horarioSubido && empty($sucursalesSinHorario)) {
        // Horario completo subido
        $color = 'verde';
        $texto = 'Horario de la semana ' . $semanaSiguiente['numero_semana'] . ' completo';

        return [
            'estado' => 'completo',
            'texto' => $texto,
            'color' => $color,
            'url' => 'programar_horarios_lider2.php?semana=' . $semanaSiguiente['numero_semana'],
            'semana_siguiente' => $semanaSiguiente,
            'dias_restantes' => $diasRestantes,
            'periodo_activo' => true,
            'sucursales_sin_horario' => [],
            'sucursales_con_horario' => $sucursalesConHorario,
            'permite_modificacion' => true
        ];
    }

    if ($horarioSubido && !empty($sucursalesSinHorario)) {
        // Horario parcialmente subido
        $color = determinarColorHorarioPendiente($diasRestantes);
        $texto = 'Horario pendiente en ' . count($sucursalesSinHorario) . ' sucursal(es)';

        return [
            'estado' => 'parcial',
            'texto' => $texto,
            'color' => $color,
            'url' => 'programar_horarios_lider2.php?semana=' . $semanaSiguiente['numero_semana'],
            'semana_siguiente' => $semanaSiguiente,
            'dias_restantes' => $diasRestantes,
            'periodo_activo' => true,
            'sucursales_sin_horario' => $sucursalesSinHorario,
            'sucursales_con_horario' => $sucursalesConHorario,
            'permite_modificacion' => true
        ];
    }

    // No se ha subido horario para ninguna sucursal
    $color = determinarColorHorarioPendiente($diasRestantes);
    $texto = 'Horario de la semana ' . $semanaSiguiente['numero_semana'] . ' pendiente de subir';

    return [
        'estado' => 'pendiente',
        'texto' => $texto,
        'color' => $color,
        'url' => 'programar_horarios_lider2.php?semana=' . $semanaSiguiente['numero_semana'],
        'semana_siguiente' => $semanaSiguiente,
        'dias_restantes' => $diasRestantes,
        'periodo_activo' => true,
        'sucursales_sin_horario' => $sucursalesSinHorario,
        'sucursales_con_horario' => [],
        'permite_modificacion' => true
    ];
}

/**
 * Determina el color según días restantes para subir horario - VERSIÓN MEJORADA
 */
function determinarColorHorarioPendiente($diasRestantes)
{
    if ($diasRestantes < 0) {
        return 'rojo'; // Vencido (después del viernes 23:59)
    } elseif ($diasRestantes === 0) {
        return 'amarillo'; // Vence hoy (viernes)
    } elseif ($diasRestantes <= 2) {
        return 'amarillo'; // 1-2 días restantes
    } else {
        return 'verde'; // 3+ días restantes
    }
}

/**
 * Obtiene el texto descriptivo para el indicador de horarios
 */
function obtenerTextoIndicadorHorarios($estadoHorario)
{
    switch ($estadoHorario['estado']) {
        case 'completo':
            return $estadoHorario['texto'];
        case 'parcial':
            return $estadoHorario['texto'] . ' (' . $estadoHorario['dias_restantes'] . ' días restantes)';
        case 'pendiente':
            return $estadoHorario['texto'] . ' (' . $estadoHorario['dias_restantes'] . ' días restantes)';
        case 'fuera_periodo':
            return $estadoHorario['texto'];
        case 'sin_sucursales':
            return $estadoHorario['texto'];
        default:
            return 'Estado no disponible';
    }
}

/**
 * Calcula días restantes para reportar faltas (fecha límite: día 1 de cada mes)
 */
function calcularDiasRestantesReporteFaltas()
{
    $hoy = new DateTime();
    $diaMes = (int) $hoy->format('d');

    if ($diaMes <= 1) {
        // Día 1: fecha límite es hoy mismo
        return 0;
    } else {
        // Días 2+: fecha límite es día 1 del próximo mes
        $proximoMes = new DateTime('first day of next month');
        $diferencia = $hoy->diff($proximoMes);
        return $diferencia->days;
    }
}

/**
 * Determina el color del indicador según total de faltas y días restantes
 */
function determinarColorFaltas($totalFaltas, $diasRestantes)
{
    if ($totalFaltas == 0) {
        return 'verde';
    }

    if ($diasRestantes <= 0) {
        return 'rojo'; // Vencido
    } elseif ($diasRestantes <= 1) {
        return 'rojo'; // 1 día o menos
    } elseif ($diasRestantes <= 3) {
        return 'amarillo'; // 2-3 días
    } else {
        return 'verde'; // 4+ días
    }
}

/**
 * Genera el texto descriptivo para el indicador de faltas
 */
function obtenerTextoIndicadorFaltas($totalFaltas, $periodo, $mesNombre)
{
    if ($totalFaltas == 0) {
        return 'Sin faltas pendientes';
    }

    $mesTexto = ($periodo === 'mes_anterior') ? 'del mes anterior' : 'del mes actual';
    return "$totalFaltas faltas pendientes $mesTexto";
}

/**
 * Calcula días restantes para reportar tardanzas
 */
function calcularDiasRestantesReporte()
{
    $hoy = new DateTime();
    $diaMes = (int) $hoy->format('d');

    if ($diaMes <= 2) {
        // Días 1-2: fecha límite es el día 2
        return max(0, 2 - $diaMes);
    } else {
        // Días 3+: fecha límite es día 2 del próximo mes
        $proximoMes = new DateTime('first day of next month');
        $proximoMes->modify('+1 day'); // Día 2 del próximo mes
        $diferencia = $hoy->diff($proximoMes);
        return $diferencia->days;
    }
}

/**
 * Determina el color del indicador según total de tardanzas y días restantes
 */
function determinarColorTardanzas($totalTardanzas, $diasRestantes)
{
    if ($totalTardanzas == 0) {
        return 'verde';
    }

    if ($diasRestantes <= 0) {
        return 'rojo'; // Vencido
    } elseif ($diasRestantes <= 1) {
        return 'rojo'; // 1 día o menos
    } elseif ($diasRestantes <= 2) {
        return 'amarillo'; // 2 días
    } else {
        return 'verde'; // 3+ días
    }
}

/**
 * Genera el texto descriptivo para el indicador
 */
function obtenerTextoIndicador($totalTardanzas, $periodo, $mesNombre)
{
    if ($totalTardanzas == 0) {
        return 'Sin tardanzas pendientes';
    }

    $mesTexto = ($periodo === 'mes_anterior') ? 'del mes anterior' : 'del mes actual';
    return "$totalTardanzas tardanzas pendientes $mesTexto";
}
