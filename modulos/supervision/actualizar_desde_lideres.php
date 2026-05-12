<?php
// actualizar_desde_lideres.php
// require_once '../../includes/auth.php';
// require_once '../../includes/funciones.php';
require_once '../../core/auth/auth.php'; 
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;

if (!tienePermiso('confirmar_horarios', 'gestionar', $cargoOperario)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No tiene permiso para realizar esta acción']);
    exit();
}


// Función para obtener horarios del líder
function obtenerHorariosLiderPorSemanaYSucursal($idSemana, $codSucursal)
{
    global $conn;
    $stmt = $conn->prepare("
        SELECT cod_operario, 
               lunes_estado, lunes_comentario, lunes_entrada, lunes_salida, lunes_horas, lunes_sucursal_externa,
               martes_estado, martes_comentario, martes_entrada, martes_salida, martes_horas, martes_sucursal_externa,
               miercoles_estado, miercoles_comentario, miercoles_entrada, miercoles_salida, miercoles_horas, miercoles_sucursal_externa,
               jueves_estado, jueves_comentario, jueves_entrada, jueves_salida, jueves_horas, jueves_sucursal_externa,
               viernes_estado, viernes_comentario, viernes_entrada, viernes_salida, viernes_horas, viernes_sucursal_externa,
               sabado_estado, sabado_comentario, sabado_entrada, sabado_salida, sabado_horas, sabado_sucursal_externa,
               domingo_estado, domingo_comentario, domingo_entrada, domingo_salida, domingo_horas, domingo_sucursal_externa,
               total_horas
        FROM HorariosSemanales
        WHERE id_semana_sistema = ? AND cod_sucursal = ?
    ");
    $stmt->execute([$idSemana, $codSucursal]);

    $resultados = [];
    while ($row = $stmt->fetch()) {
        $resultados[$row['cod_operario']] = $row;
    }
    return $resultados;
}

// Función para obtener horario de operaciones
function obtenerHorarioOperaciones($codOperario, $idSemana, $codSucursal)
{
    global $conn;
    $stmt = $conn->prepare("
        SELECT * FROM HorariosSemanalesOperaciones
        WHERE cod_operario = ? 
        AND id_semana_sistema = ? 
        AND cod_sucursal = ?
        LIMIT 1
    ");
    $stmt->execute([$codOperario, $idSemana, $codSucursal]);
    return $stmt->fetch();
}

// Función para actualizar horario de operaciones
function actualizarHorarioOperaciones($idHorario, $horario)
{
    global $conn;

    $stmt = $conn->prepare("
        UPDATE HorariosSemanalesOperaciones SET
        lunes_estado = ?, lunes_comentario = ?, lunes_entrada = ?, lunes_salida = ?, lunes_horas = ?, lunes_sucursal_externa = ?,
        martes_estado = ?, martes_comentario = ?, martes_entrada = ?, martes_salida = ?, martes_horas = ?, martes_sucursal_externa = ?,
        miercoles_estado = ?, miercoles_comentario = ?, miercoles_entrada = ?, miercoles_salida = ?, miercoles_horas = ?, miercoles_sucursal_externa = ?,
        jueves_estado = ?, jueves_comentario = ?, jueves_entrada = ?, jueves_salida = ?, jueves_horas = ?, jueves_sucursal_externa = ?,
        viernes_estado = ?, viernes_comentario = ?, viernes_entrada = ?, viernes_salida = ?, viernes_horas = ?, viernes_sucursal_externa = ?,
        sabado_estado = ?, sabado_comentario = ?, sabado_entrada = ?, sabado_salida = ?, sabado_horas = ?, sabado_sucursal_externa = ?,
        domingo_estado = ?, domingo_comentario = ?, domingo_entrada = ?, domingo_salida = ?, domingo_horas = ?, domingo_sucursal_externa = ?,
        total_horas = ?, actualizado_por = ?, fecha_actualizacion = NOW(),
        confirmado = 0, fecha_confirmacion = NULL
        WHERE id = ?
    ");

    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $params = [];

    foreach ($dias as $dia) {
        array_push(
            $params,
            $horario["{$dia}_estado"] ?? 'Activo',
            $horario["{$dia}_comentario"] ?? '',
            $horario["{$dia}_entrada"] ?? null,
            $horario["{$dia}_salida"] ?? null,
            $horario["{$dia}_horas"] ?? 0,
            $horario["{$dia}_sucursal_externa"] ?? null
        );
    }

    array_push(
        $params,
        $horario['total_horas'] ?? 0,
        $_SESSION['usuario_id'],
        $idHorario
    );

    return $stmt->execute($params);
}

// Función para crear nuevo horario de operaciones
function crearHorarioOperaciones($idSemana, $codOperario, $codSucursal, $horario)
{
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO HorariosSemanalesOperaciones (
            id_semana_sistema, cod_operario, cod_sucursal,
            lunes_estado, lunes_comentario, lunes_entrada, lunes_salida, lunes_horas, lunes_sucursal_externa,
            martes_estado, martes_comentario, martes_entrada, martes_salida, martes_horas, martes_sucursal_externa,
            miercoles_estado, miercoles_comentario, miercoles_entrada, miercoles_salida, miercoles_horas, miercoles_sucursal_externa,
            jueves_estado, jueves_comentario, jueves_entrada, jueves_salida, jueves_horas, jueves_sucursal_externa,
            viernes_estado, viernes_comentario, viernes_entrada, viernes_salida, viernes_horas, viernes_sucursal_externa,
            sabado_estado, sabado_comentario, sabado_entrada, sabado_salida, sabado_horas, sabado_sucursal_externa,
            domingo_estado, domingo_comentario, domingo_entrada, domingo_salida, domingo_horas, domingo_sucursal_externa,
            total_horas, creado_por, fecha_creacion, confirmado
        ) VALUES (
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, NOW(), 0
        )
    ");

    $dias = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    $params = [$idSemana, $codOperario, $codSucursal];

    foreach ($dias as $dia) {
        array_push(
            $params,
            $horario["{$dia}_estado"] ?? 'Activo',
            $horario["{$dia}_comentario"] ?? '',
            $horario["{$dia}_entrada"] ?? null,
            $horario["{$dia}_salida"] ?? null,
            $horario["{$dia}_horas"] ?? 0,
            $horario["{$dia}_sucursal_externa"] ?? null
        );
    }

    array_push($params, $horario['total_horas'] ?? 0, $_SESSION['usuario_id']);

    return $stmt->execute($params);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semana = $_POST['semana'] ?? null;
    $sucursal = $_POST['sucursal'] ?? null;

    if (!$semana || !$sucursal) {
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // Obtener semana del sistema
        $semanaObj = obtenerSemanaPorNumero($semana);
        if (!$semanaObj) {
            throw new Exception('Semana no válida');
        }

        // Obtener horarios del líder
        $horariosLider = obtenerHorariosLiderPorSemanaYSucursal($semanaObj['id'], $sucursal);

        if (empty($horariosLider)) {
            throw new Exception('No hay horarios programados por el líder');
        }

        // Para cada horario del líder, actualizar el correspondiente en operaciones
        foreach ($horariosLider as $codOperario => $horarioLider) {
            // Verificar si ya existe en operaciones
            $existente = obtenerHorarioOperaciones($codOperario, $semanaObj['id'], $sucursal);

            if ($existente) {
                // Actualizar registro existente
                actualizarHorarioOperaciones($existente['id'], $horarioLider);
            } else {
                // Crear nuevo registro
                crearHorarioOperaciones($semanaObj['id'], $codOperario, $sucursal, $horarioLider);
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Horarios actualizados correctamente desde los valores del líder. Los horarios ahora están pendientes de confirmación.']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
