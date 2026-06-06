<?php
/**
 * marcacion_offline_cache.php
 * ─────────────────────────────────────────────────────────────────────────
 * Devuelve JSON con los operarios de la sucursal (hashes bcrypt) para que
 * marcacion_express.php pueda validar contraseñas localmente en modo offline.
 *
 * Requisito  : Cookie erp_device_token válida (igual que marcacion_express.php)
 * Respuesta  : JSON array de operarios con hash bcrypt
 * Seguridad  : Solo devuelve hash (bcrypt) — nunca la contraseña en texto plano
 * ─────────────────────────────────────────────────────────────────────────
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/helpers/funciones.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/core/database/conexion.php';

// 1. Validar cookie del dispositivo ─────────────────────────────────────
$tokenCookie = $_COOKIE['erp_device_token'] ?? null;
if (empty($tokenCookie)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Dispositivo no autorizado']);
    exit();
}

global $conn;

try {
    // 2. Identificar sucursal por token ───────────────────────────────────
    $stmt = $conn->prepare("SELECT codigo, nombre FROM sucursales WHERE cookie_token = ? LIMIT 1");
    $stmt->execute([$tokenCookie]);
    $sucursal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sucursal) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token de dispositivo inválido o expirado']);
        exit();
    }

    $codSucursalReal = (int)$sucursal['codigo'];
    // Sucursales 6 y 18 se tratan juntas (misma lógica que marcacion_express.php)
    $codSucursal = ($codSucursalReal === 6 || $codSucursalReal === 18) ? 18 : $codSucursalReal;

    // 3. Obtener operarios activos con asignación vigente en la sucursal ──
    // Excluye cargo 27 (Sucursales/Administración) — no pueden marcar
    // Excluye operarios con fecha_salida en contrato ya vencida
    $sql = "
        SELECT DISTINCT
            o.CodOperario,
            o.Nombre,
            o.Nombre2,
            o.Apellido,
            o.Apellido2,
            o.clave,
            o.clave_hash,
            nc.CodNivelesCargos AS cargo_codigo
        FROM Operarios o
        INNER JOIN AsignacionNivelesCargos anc
            ON o.CodOperario = anc.CodOperario
            AND (anc.Fin IS NULL OR anc.Fin >= CURDATE())
            AND anc.Fecha <= CURDATE()
        INNER JOIN NivelesCargos nc
            ON anc.CodNivelesCargos = nc.CodNivelesCargos
        WHERE o.Operativo = 1
          AND nc.CodNivelesCargos != 27
          AND (
              anc.Sucursal = :suc1
              OR (:suc2 = 18 AND anc.Sucursal = 6)
          )
          AND NOT EXISTS (
              SELECT 1 FROM Contratos c
              WHERE c.cod_operario = o.CodOperario
                AND c.fecha_salida IS NOT NULL
                AND c.fecha_salida != '0000-00-00'
                AND c.fecha_salida < CURDATE()
                AND c.CodContrato = (
                    SELECT MAX(c2.CodContrato)
                    FROM Contratos c2
                    WHERE c2.cod_operario = o.CodOperario
                )
          )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':suc1' => $codSucursal, ':suc2' => $codSucursal]);
    $operarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Construir respuesta con hashes bcrypt ────────────────────────────
    $resultado  = [];
    $migrados   = 0; // contador de los que se auto-migran en esta llamada

    $stmtUpdateHash = $conn->prepare("
        UPDATE Operarios SET clave_hash = ? WHERE CodOperario = ? AND clave_hash IS NULL
    ");

    foreach ($operarios as $op) {
        $codOp = (int)$op['CodOperario'];

        // Determinar el hash a usar
        if (!empty($op['clave_hash'])) {
            // Ya tiene hash bcrypt → usar directo
            $hash = $op['clave_hash'];

        } elseif (!empty($op['clave']) && strncmp($op['clave'], '$2y$', 4) !== 0) {
            // Solo tiene clave texto plano → generar bcrypt y guardarlo (auto-migración)
            $hash = password_hash($op['clave'], PASSWORD_BCRYPT, ['cost' => 10]);
            $stmtUpdateHash->execute([$hash, $codOp]);
            $migrados++;

        } else {
            // Sin contraseña usable → saltar (no puede marcar)
            continue;
        }

        $resultado[] = [
            'CodOperario'    => $codOp,
            'nombre_completo'=> obtenerNombreCompletoOperario($op),
            'hash_clave'     => $hash,
            'sucursal_codigo'=> $codSucursalReal,
        ];
    }

    echo json_encode([
        'success'          => true,
        'sucursal_codigo'  => $codSucursalReal,
        'sucursal_nombre'  => $sucursal['nombre'],
        'operarios'        => $resultado,
        'total'            => count($resultado),
        'auto_migrados'    => $migrados,       // para debug, se puede eliminar en producción
        'timestamp_cache'  => date('c'),       // ISO 8601 — el cliente lo guarda para saber cuándo refresar
    ]);

} catch (Exception $e) {
    error_log('[marcacion_offline_cache] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de sistema']);
}
