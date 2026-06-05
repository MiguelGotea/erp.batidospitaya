<?php
/**
 * marcacion_offline_sync.php
 * ─────────────────────────────────────────────────────────────────────────
 * Recibe la cola de marcaciones offline del cliente (IndexedDB) y las
 * procesa en el servidor usando la hora/fecha exacta del momento offline.
 *
 * Método   : POST, Content-Type: application/json
 * Payload  : { "queue": [ {local_id, CodOperario, clave, fecha, hora, timestamp_iso}, ... ] }
 * Respuesta: { "results": [ {local_id, success, tipo, error}, ... ] }
 * ─────────────────────────────────────────────────────────────────────────
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/helpers/funciones.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';

global $conn;

// Función para verificar si el operario tiene contrato activo (puede marcar)
function puedeMarcarOperario($codOperario)
{
    global $conn;

    // Obtener el último contrato del operario
    $stmt = $conn->prepare("
            SELECT CodContrato, fecha_salida, fin_contrato
            FROM Contratos 
            WHERE cod_operario = ? 
            ORDER BY CodContrato DESC 
            LIMIT 1
        ");
    $stmt->execute([$codOperario]);
    $ultimoContrato = $stmt->fetch();

    // Si no hay contrato, no puede marcar
    if (!$ultimoContrato) {
        return false;
    }

    // Si tiene fecha_salida y esta es menor o igual a la fecha actual, NO puede marcar
    if (!empty($ultimoContrato['fecha_salida']) && $ultimoContrato['fecha_salida'] != '0000-00-00') {
        $fechaSalida = new DateTime($ultimoContrato['fecha_salida']);
        $fechaActual = new DateTime();

        // Si la fecha de salida ya pasó, no puede marcar
        if ($fechaSalida <= $fechaActual) {
            return false;
        }
    }

    return true;
}

// Función para obtener el horario programado del operario
function obtenerHorarioProgramado($codOperario, $sucursalCodigo, $fecha)
{
    // Para sucursales 6 y 18, usar horario fijo con almuerzo
    if ($sucursalCodigo == 6 || $sucursalCodigo == 18) {
        $horaActual = date('H:i:s');

        // Si es entre 12:00 PM y 1:00 PM, es horario de almuerzo
        if ($horaActual >= '12:00:00' && $horaActual <= '13:00:00') {
            return [
                'estado' => 'Almuerzo',
                'hora_entrada' => '13:00:00',
                'hora_salida' => '12:00:00',
                'numero_semana' => date('W', strtotime($fecha))
            ];
        }

        return [
            'estado' => 'Activo',
            'hora_entrada' => '07:00:00',
            'hora_salida' => '17:30:00',
            'numero_semana' => date('W', strtotime($fecha))
        ];
    }

    // Resto del código original para otras sucursales
    $diaSemana = strtolower(date('l', strtotime($fecha)));
    $diasEspanol = [
        'monday' => 'lunes',
        'tuesday' => 'martes',
        'wednesday' => 'miercoles',
        'thursday' => 'jueves',
        'friday' => 'viernes',
        'saturday' => 'sabado',
        'sunday' => 'domingo'
    ];
    $dia = $diasEspanol[$diaSemana] ?? '';

    if (empty($dia)) {
        return null;
    }

    // Buscar semana por fecha
    $sqlSemana = "SELECT numero_semana FROM SemanasSistema 
                      WHERE fecha_inicio <= ? AND fecha_fin >= ?";
    $stmtSemana = ejecutarConsulta($sqlSemana, [$fecha, $fecha]);

    if ($stmtSemana && $semana = $stmtSemana->fetch()) {
        $sqlHorario = "SELECT 
                            id,
                            numero_semana,
                            {$dia}_estado as estado,
                            {$dia}_entrada as hora_entrada,
                            {$dia}_salida as hora_salida
                          FROM HorariosSemanalesOperaciones
                          WHERE numero_semana = ? 
                          AND cod_operario = ? 
                          AND cod_sucursal = ?";
        $stmtHorario = ejecutarConsulta($sqlHorario, [
            $semana['numero_semana'],
            $codOperario,
            $sucursalCodigo
        ]);

        if ($stmtHorario && $horario = $stmtHorario->fetch()) {
            return $horario['estado'] === 'Activo' ? $horario : null;
        }
    }

    return null;
}

// 1. Validar token del dispositivo ─────────────────────────────────────────
$tokenCookie = $_COOKIE['erp_device_token'] ?? null;
if (empty($tokenCookie)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'token_invalido']);
    exit();
}

try {
    $stmtSuc = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE cookie_token = ? LIMIT 1");
    $stmtSuc->execute([$tokenCookie]);
    $sucursal = $stmtSuc->fetch(PDO::FETCH_ASSOC);

    if (!$sucursal) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'token_invalido']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'error_sistema']);
    exit();
}

$codSucursalReal = (int)$sucursal['codigo'];
$codSucursal     = ($codSucursalReal === 6 || $codSucursalReal === 18) ? 18 : $codSucursalReal;

// 2. Parsear payload JSON ───────────────────────────────────────────────────
$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!isset($data['queue']) || !is_array($data['queue'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'payload_invalido']);
    exit();
}

$queue = $data['queue'];
if (empty($queue)) {
    echo json_encode(['success' => true, 'results' => [], 'message' => 'cola_vacia']);
    exit();
}

// 3. Procesar cada marcación ────────────────────────────────────────────────
$results = [];

foreach ($queue as $item) {
    $localId    = $item['local_id']      ?? null;
    $codOperario= (int)($item['CodOperario'] ?? 0);
    $clave      = trim($item['clave']    ?? '');
    $fechaCliente= $item['fecha']         ?? ''; // YYYY-MM-DD
    $horaCliente = $item['hora']          ?? ''; // HH:MM:SS

    // Validaciones básicas del item
    if (!$localId || !$codOperario || empty($clave) || empty($fechaCliente) || empty($horaCliente)) {
        $results[] = ['local_id' => $localId, 'success' => false, 'error' => 'datos_incompletos'];
        continue;
    }

    // Validar formato fecha y hora
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaCliente) ||
        !preg_match('/^\d{2}:\d{2}:\d{2}$/', $horaCliente)) {
        $results[] = ['local_id' => $localId, 'success' => false, 'error' => 'formato_invalido'];
        continue;
    }

    try {
        // 3a. Re-validar contraseña contra BD ─────────────────────────────
        $sqlOp = "SELECT o.CodOperario, o.Nombre, o.Nombre2, o.Apellido, o.Apellido2,
                         o.usuario, o.Cumpleanos, o.clave, o.clave_hash,
                         nc.Nombre AS cargo_nombre, nc.CodNivelesCargos AS cargo_codigo,
                         s.nombre AS sucursal_nombre, s.codigo AS sucursal_codigo
                  FROM Operarios o
                  JOIN AsignacionNivelesCargos anc
                       ON o.CodOperario = anc.CodOperario
                       AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
                       AND anc.Fecha <= CURDATE()
                  JOIN NivelesCargos nc ON anc.CodNivelesCargos = nc.CodNivelesCargos
                  JOIN sucursales s     ON anc.Sucursal = s.codigo
                  WHERE o.CodOperario = ?
                    AND (s.codigo = ? OR (? = 18 AND s.codigo = 6))
                    AND o.Operativo = 1";

        $stmtOp = $conn->prepare($sqlOp);
        $stmtOp->execute([$codOperario, $codSucursal, $codSucursal]);
        $operarios = $stmtOp->fetchAll(PDO::FETCH_ASSOC);

        if (count($operarios) === 0) {
            $results[] = ['local_id' => $localId, 'success' => false, 'error' => 'operario_no_encontrado'];
            continue;
        }

        // Verificar contraseña (texto plano o bcrypt)
        $operario       = $operarios[0];
        $claveValida    = false;

        if (!empty($operario['clave_hash'])) {
            $claveValida = password_verify($clave, $operario['clave_hash']);
        }
        if (!$claveValida && !empty($operario['clave'])) {
            $claveValida = ($clave === $operario['clave']);
        }

        if (!$claveValida) {
            $results[] = ['local_id' => $localId, 'success' => false, 'error' => 'clave_invalida'];
            continue;
        }

        // 3b. Verificar cargo (no puede ser cargo 27 - Sucursales) ─────────
        if ($operario['cargo_codigo'] == 27) {
            $results[] = ['local_id' => $localId, 'success' => false, 'error' => 'cargo_no_permitido'];
            continue;
        }

        // 3c. Verificar contrato vigente ───────────────────────────────────
        if (!puedeMarcarOperario($codOperario)) {
            $results[] = ['local_id' => $localId, 'success' => false, 'error' => 'contrato_vencido'];
            continue;
        }

        // 3d. Verificar si ya existe una marcación para esta fecha y hora exacta
        // (evitar duplicados si el sync se ejecuta dos veces)
        $stmtDup = $conn->prepare("
            SELECT id FROM marcaciones
            WHERE CodOperario = ? AND fecha = ? AND hora_ingreso = ?
            LIMIT 1
        ");
        $stmtDup->execute([$codOperario, $fechaCliente, $horaCliente]);
        if ($stmtDup->fetch()) {
            // Ya existe — marcar como sincronizada sin error
            $results[] = ['local_id' => $localId, 'success' => true, 'tipo' => 'ya_existia', 'error' => null];
            continue;
        }

        // 3e. Obtener horario programado para la fecha offline ─────────────
        $horarioProgramado = obtenerHorarioProgramado($codOperario, $codSucursal, $fechaCliente);
        $esHorarioAlmuerzo = false;

        if ($codSucursal == 6 || $codSucursal == 18) {
            $esHorarioAlmuerzo = ($horaCliente >= '12:00:00' && $horaCliente <= '13:00:00');
        } elseif ($horarioProgramado) {
            $esHorarioAlmuerzo = isset($horarioProgramado['estado']) &&
                                  $horarioProgramado['estado'] === 'Almuerzo';
        }

        // 3f. Verificar última marcación para determinar entrada/salida ────
        $sqlMarcacion = "SELECT * FROM marcaciones
                         WHERE CodOperario = ?
                           AND (sucursal_codigo = ? OR (? = 18 AND sucursal_codigo = 6))
                           AND fecha = ?
                         ORDER BY hora_ingreso DESC
                         LIMIT 1";
        $stmtM = $conn->prepare($sqlMarcacion);
        $stmtM->execute([$codOperario, $codSucursal, $codSucursal, $fechaCliente]);
        $ultimaMarcacion = $stmtM->fetch(PDO::FETCH_ASSOC);

        // Lógica de salida anticipada (igual que marcacion_express.php)
        $registrarSoloSalida = false;
        if ($horarioProgramado && !empty($horarioProgramado['hora_salida']) && !$esHorarioAlmuerzo) {
            $diff = (strtotime($horarioProgramado['hora_salida']) - strtotime($horaCliente)) / 60;
            if ($diff <= 30) {
                $registrarSoloSalida = true;
            }
        }

        $registrarEntrada = true;
        if ($ultimaMarcacion &&
            $ultimaMarcacion['fecha'] === $fechaCliente &&
            $ultimaMarcacion['hora_salida'] === null) {
            $registrarEntrada = false;
        }

        // 3g. Obtener código de contrato ────────────────────────────────────
        $codContrato  = obtenerUltimoCodigoContrato($codOperario);
        $nombreCompleto = obtenerNombreCompletoOperario($operario);

        // 3h. Construir y ejecutar SQL de marcación usando hora/fecha offline
        if ($registrarSoloSalida) {
            $registrarEntrada = false;
            $tipo = 'salida';

            if (!$ultimaMarcacion || $ultimaMarcacion['fecha'] !== $fechaCliente) {
                // Insertar solo salida (sin entrada previa ese día)
                $sql    = "INSERT INTO marcaciones
                           (hora_ingreso, hora_salida, fecha, CodOperario, cod_contrato,
                            sucursal_codigo, nombre_operario, id_horario_semanal, numero_semana)
                           VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [
                    $horaCliente, $fechaCliente, $codOperario, $codContrato,
                    $operario['sucursal_codigo'], $nombreCompleto,
                    $horarioProgramado['id']           ?? null,
                    $horarioProgramado['numero_semana'] ?? null,
                ];
            } else {
                // Actualizar marcación existente con hora de salida
                $sql    = "UPDATE marcaciones
                           SET hora_salida = ?, sucursal_codigo = ?,
                               id_horario_semanal = ?, numero_semana = ?, cod_contrato = ?
                           WHERE id = ?";
                $params = [
                    $horaCliente, $operario['sucursal_codigo'],
                    $horarioProgramado['id']           ?? null,
                    $horarioProgramado['numero_semana'] ?? null,
                    $codContrato, $ultimaMarcacion['id'],
                ];
            }

        } elseif (!$registrarEntrada) {
            $tipo   = 'salida';
            $sql    = "UPDATE marcaciones
                       SET hora_salida = ?, sucursal_codigo = ?,
                           id_horario_semanal = ?, numero_semana = ?, cod_contrato = ?
                       WHERE id = ?";
            $params = [
                $horaCliente, $operario['sucursal_codigo'],
                $horarioProgramado['id']           ?? null,
                $horarioProgramado['numero_semana'] ?? null,
                $codContrato, $ultimaMarcacion['id'],
            ];

        } else {
            $tipo   = 'entrada';
            $sql    = "INSERT INTO marcaciones
                       (hora_ingreso, fecha, CodOperario, cod_contrato,
                        sucursal_codigo, nombre_operario, id_horario_semanal, numero_semana)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $params = [
                $horaCliente, $fechaCliente, $codOperario, $codContrato,
                $operario['sucursal_codigo'], $nombreCompleto,
                $horarioProgramado['id']           ?? null,
                $horarioProgramado['numero_semana'] ?? null,
            ];
        }

        $stmtInsert = $conn->prepare($sql);
        $stmtInsert->execute($params);

        $results[] = [
            'local_id' => $localId,
            'success'  => true,
            'tipo'     => $tipo,
            'nombre'   => $nombreCompleto,
            'fecha'    => $fechaCliente,
            'hora'     => $horaCliente,
            'error'    => null,
        ];

    } catch (Exception $e) {
        error_log('[marcacion_offline_sync] Error item ' . $localId . ': ' . $e->getMessage());
        $results[] = [
            'local_id' => $localId,
            'success'  => false,
            'error'    => 'error_servidor',
        ];
    }
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'procesados' => count($results),
    'exitosos'   => count(array_filter($results, fn($r) => $r['success'])),
]);
