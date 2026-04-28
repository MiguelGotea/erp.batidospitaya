<?php
/**
 * ajax/anulaciones_ia_auto_batch.php
 * Procesamiento automático de anulaciones pendientes con IA.
 *
 * Puede ser llamado:
 *   A) Por sync_anulacion_pedidos.php (API layer) vía curl interno.
 *   B) Por el VBA de Access directamente vía proxy.
 *   C) Manualmente desde el ERP.
 *
 * POST JSON:
 *   sucursal          : Código de sucursal (requerido)
 *   cod_anulacion_host: ID específico a procesar (opcional; si no se manda, procesa todos los pendientes de la sucursal)
 *
 * Lógica:
 *   - Obtiene registros Status=0 (pendientes) sin veredicto IA aún.
 *   - Para cada uno: carga detalles del pedido + pedido de cambio.
 *   - Llama a IA (AIService en cascada).
 *   - Si "aprobar" → Status=1, AprobadoPor="IA Automática".
 *   - Si "rechazar"/"revisar" → deja Status=0, guarda ia_resultado.
 *   - En ambos casos guarda ia_decision + ia_resultado en la fila.
 */

@session_start();
require_once '../../../core/database/conexion.php';
require_once '../../../core/ai/AIService.php';

header('Content-Type: application/json; charset=utf-8');

// ── Token para llamadas internas (mismo que usa Access) ──────
define('IA_BATCH_TOKEN', 'a8f5e2d9c4b7a1e6f3d8c5b2a9e6d3f0c7a4b1e8d5c2a9f6e3d0c7b4a1e8f5d2');

// Verificación simple de token (permite llamadas server-to-server sin sesión)
$headers = getallheaders();
$incomingToken = str_replace('Bearer ', '', trim($headers['Authorization'] ?? ''));
$esLlamadaInterna = hash_equals(IA_BATCH_TOKEN, $incomingToken);
// Si no tiene token de API, debe haber sesión PHP activa (llamada desde ERP)
if (!$esLlamadaInterna && empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado.']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $sucursal         = isset($input['sucursal'])           ? intval($input['sucursal'])           : 0;
    $codAnulacionHost = isset($input['cod_anulacion_host']) ? intval($input['cod_anulacion_host']) : 0;

    if ($sucursal < 1 && $codAnulacionHost < 1) {
        throw new Exception('Se requiere sucursal o cod_anulacion_host.');
    }

    /** @var PDO $conn */
    global $conn;
    $pdo = $conn;

    // ── Cargar registros pendientes a procesar ───────────────
    $params = [];
    $where  = 'Status = 0 AND (ia_decision IS NULL OR ia_decision = \'\')';

    if ($codAnulacionHost > 0) {
        $where .= ' AND CodAnulacionHost = :id';
        $params[':id'] = $codAnulacionHost;
    } elseif ($sucursal > 0) {
        $where .= ' AND Sucursal = :suc';
        $params[':suc'] = $sucursal;
    }

    $stmtPend = $pdo->prepare(
        "SELECT CodAnulacionHost, CodPedido, CodPedidoCambio, Sucursal, Motivo
         FROM AnulacionPedidosHost
         WHERE $where
         ORDER BY CodAnulacionHost ASC"
    );
    $stmtPend->execute($params);
    $pendientes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendientes)) {
        echo json_encode([
            'success'  => true,
            'message'  => 'No hay solicitudes pendientes para procesar con IA.',
            'procesados' => 0
        ]);
        exit();
    }

    // ── Cargar helpers ────────────────────────────────────────
    $systemPrompt = construirSystemPromptAnulacion();

    $procesados  = 0;
    $aprobados   = 0;
    $pendientesIA = 0;
    $erroresList = [];

    foreach ($pendientes as $reg) {
        $codAnu   = intval($reg['CodAnulacionHost']);
        $codPed   = intval($reg['CodPedido']);
        $codCam   = intval($reg['CodPedidoCambio'] ?? 0);
        $suc      = intval($reg['Sucursal']);
        $motivo   = $reg['Motivo'] ?? '';

        try {
            // ── Cargar detalle pedido principal ──────────────
            $pedPrincipal = cargarDetallePedido($pdo, $codPed, $suc);
            if (!$pedPrincipal) {
                $erroresList[] = "CodAnulacionHost=$codAnu: No se encontraron líneas del pedido $codPed.";
                continue;
            }

            // ── Cargar pedido de cambio si existe ────────────
            $pedCambio = null;
            if ($codCam > 0) {
                $pedCambio = cargarDetallePedido($pdo, $codCam, $suc);
            }

            // ── Construir user prompt ────────────────────────
            $userPrompt = construirUserPrompt($codAnu, $motivo, $pedPrincipal, $pedCambio);

            // ── Llamar IA en cascada ─────────────────────────
            $proveedores = ['google', 'openai', 'deepseek', 'mistral', 'cerebras', 'groq'];
            $respuestaTexto = null;
            $aiService      = null;

            foreach ($proveedores as $prov) {
                try {
                    $svc = new AIService($pdo, $prov);
                    $respuestaTexto = $svc->procesarPrompt($systemPrompt, $userPrompt, 0.1);
                    if ($respuestaTexto) { $aiService = $svc; break; }
                } catch (Exception $e) { continue; }
            }

            if (!$respuestaTexto || !$aiService) {
                $erroresList[] = "CodAnulacionHost=$codAnu: Ningún proveedor IA respondió.";
                continue;
            }

            $resultado = $aiService->extraerJSON($respuestaTexto);
            if (empty($resultado['decision'])) {
                $erroresList[] = "CodAnulacionHost=$codAnu: IA no retornó decisión válida.";
                continue;
            }

            $decision   = strtolower($resultado['decision']);   // aprobar|rechazar|revisar
            $confianza  = $resultado['confianza']  ?? 'media';
            $comentario = $resultado['comentario'] ?? '';
            $puntos     = $resultado['puntos']     ?? [];

            $iaResultadoJson = json_encode([
                'decision'   => $decision,
                'confianza'  => $confianza,
                'comentario' => $comentario,
                'puntos'     => $puntos,
                'proveedor'  => $aiService->getProveedor(),
                'fecha'      => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE);

            // ── Guardar veredicto y aprobar si aplica ────────
            if ($decision === 'aprobar') {
                // Auto-aprobación: Status=1
                $pdo->prepare(
                    "UPDATE AnulacionPedidosHost
                     SET Status               = 1,
                         ComentarioAprobacion = :com,
                         AprobadoPor          = 'IA Automática',
                         FechaAprobacion      = NOW(),
                         ia_decision          = :dec,
                         ia_resultado         = :res
                     WHERE CodAnulacionHost = :id"
                )->execute([
                    ':com' => $comentario,
                    ':dec' => $decision,
                    ':res' => $iaResultadoJson,
                    ':id'  => $codAnu,
                ]);
                $aprobados++;
            } else {
                // Queda pendiente, solo guarda veredicto
                $pdo->prepare(
                    "UPDATE AnulacionPedidosHost
                     SET ia_decision  = :dec,
                         ia_resultado = :res
                     WHERE CodAnulacionHost = :id"
                )->execute([
                    ':dec' => $decision,
                    ':res' => $iaResultadoJson,
                    ':id'  => $codAnu,
                ]);
                $pendientesIA++;
            }

            $procesados++;

        } catch (Exception $e) {
            $erroresList[] = "CodAnulacionHost=$codAnu: " . $e->getMessage();
        }
    }

    echo json_encode([
        'success'      => true,
        'procesados'   => $procesados,
        'aprobados'    => $aprobados,
        'pendientes_ia'=> $pendientesIA,
        'errores'      => $erroresList,
        'message'      => "IA procesó $procesados solicitudes: $aprobados aprobadas, $pendientesIA pendientes de revisión manual.",
    ]);

} catch (Exception $e) {
    error_log('Error anulaciones_ia_auto_batch: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ═══════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════

function cargarDetallePedido(PDO $pdo, int $codPedido, int $sucursal): ?array
{
    // Obtener nombre de sucursal para filtrar correctamente
    $stmtSuc = $pdo->prepare('SELECT nombre, codigo FROM sucursales WHERE codigo = :suc LIMIT 1');
    $stmtSuc->execute([':suc' => $sucursal]);
    $sucRow = $stmtSuc->fetch(PDO::FETCH_ASSOC);

    $params   = [':cod' => $codPedido];
    $sqlWhere = 'WHERE CodPedido = :cod';

    if ($sucRow) {
        $sqlWhere .= ' AND (Sucursal_Nombre = :sn OR local = :sc OR local = :scs)';
        $params[':sn']  = $sucRow['nombre'];
        $params[':sc']  = $sucRow['codigo'];
        $params[':scs'] = 'S' . $sucRow['codigo'];
    }

    $stmt = $pdo->prepare(
        "SELECT v.CodPedido, v.DBBatidos_Nombre, v.NombreGrupo, v.Medida, v.Cantidad,
                v.Precio, v.Precio_Unitario_Sin_Descuento, v.Anulado, v.MotivoAnulado,
                v.Fecha, v.Hora, v.aPOS, v.Modalidad, v.Delivery_Nombre,
                v.CodCliente, v.MontoFactura, v.Sucursal_Nombre,
                c.nombre AS Cliente_Nombre, c.apellido AS Cliente_Apellido
         FROM VentasGlobalesAccessCSV v
         LEFT JOIN clientesclub c ON v.CodCliente = c.membresia
         $sqlWhere
         ORDER BY v.DBBatidos_Nombre ASC"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) return null;

    $resumen = [
        'CodPedido'        => $items[0]['CodPedido'],
        'Fecha'            => $items[0]['Fecha'],
        'Hora'             => $items[0]['Hora'],
        'aPOS'             => $items[0]['aPOS'],
        'Modalidad'        => $items[0]['Modalidad'],
        'Delivery_Nombre'  => $items[0]['Delivery_Nombre'],
        'CodCliente'       => $items[0]['CodCliente'],
        'Cliente_Nombre'   => $items[0]['Cliente_Nombre'],
        'Cliente_Apellido' => $items[0]['Cliente_Apellido'],
        'MontoFactura'     => $items[0]['MontoFactura'],
        'Sucursal_Nombre'  => $items[0]['Sucursal_Nombre'],
        'Anulado'          => $items[0]['Anulado'],
        'MotivoAnulado'    => $items[0]['MotivoAnulado'],
    ];

    return ['resumen' => $resumen, 'items' => $items];
}

function construirUserPrompt(int $codAnu, string $motivo, array $pedPrincipal, ?array $pedCambio): string
{
    $txt  = "SOLICITUD DE ANULACIÓN #$codAnu\n\n";
    $txt .= "MOTIVO DECLARADO: " . ($motivo ?: 'No especificado') . "\n\n";
    $txt .= "=== PEDIDO A ANULAR ===\n" . formatearPedidoParaIA($pedPrincipal) . "\n\n";

    if ($pedCambio) {
        $txt .= "=== PEDIDO DE CAMBIO / SUSTITUTO ===\n" . formatearPedidoParaIA($pedCambio) . "\n\n";
    } else {
        $txt .= "=== PEDIDO DE CAMBIO ===\nNo aplica — anulación simple sin pedido sustituto.\n\n";
    }

    $txt .= "Analiza esta solicitud y emite tu decisión en el formato JSON especificado.";
    return $txt;
}

function formatearPedidoParaIA(array $pedido): string
{
    $resumen = $pedido['resumen'] ?? [];
    $items   = $pedido['items']   ?? [];

    $lineas   = [];
    $lineas[] = "Pedido #" . ($resumen['CodPedido']    ?? '?');
    $lineas[] = "Fecha: "  . ($resumen['Fecha']        ?? '?') . ' ' . ($resumen['Hora'] ?? '');
    $lineas[] = "Sucursal: ". ($resumen['Sucursal_Nombre'] ?? '?');
    $lineas[] = "Modalidad: ". ($resumen['Modalidad']  ?? '?');
    $lineas[] = "Cliente: " . (
        !empty($resumen['Cliente_Nombre'])
            ? $resumen['Cliente_Nombre'] . ' ' . ($resumen['Cliente_Apellido'] ?? '')
            : ($resumen['CodCliente'] ?? '—')
    );
    $lineas[] = "Monto Factura: C$ " . number_format(floatval($resumen['MontoFactura'] ?? 0), 2);
    $anulado  = intval($resumen['Anulado'] ?? 0);
    $lineas[] = "Estado en tienda: " . ($anulado === -1 || $anulado === 1 ? 'ANULADO' : 'ACTIVO');

    $lineas[] = "\nDetalle de productos:";
    foreach ($items as $it) {
        $nombre   = $it['DBBatidos_Nombre'] ?? $it['NombreGrupo'] ?? '?';
        $cant     = $it['Cantidad']         ?? 0;
        $medida   = $it['Medida']           ?? '';
        $precio   = number_format(floatval($it['Precio_Unitario_Sin_Descuento'] ?? $it['Precio'] ?? 0), 2);
        $lineas[] = "  - $nombre | $medida | Cant: $cant | P.U: C$ $precio";
    }

    return implode("\n", $lineas);
}

function construirSystemPromptAnulacion(): string
{
    return <<<PROMPT
Eres un auditor inteligente del sistema ERP de Batidos Pitaya. Tu rol es analizar solicitudes de anulación de pedidos y emitir una recomendación objetiva.

**CONTEXTO DEL NEGOCIO:**
- Batidos Pitaya es una cadena de tiendas de batidos y alimentos saludables.
- Las solicitudes de anulación pueden ser por error del operador, cambio de pedido, duplicado, o solicitud del cliente.
- Cuando existe un "Pedido de Cambio", este sustituye al pedido anulado; ambos deberían tener productos similares o equivalentes.
- Un pedido "ANULADO EN TIENDA" ya fue cancelado localmente pero requiere aprobación en el ERP para sincronización.

**TU TAREA:**
1. Analizar el motivo declarado.
2. Comparar los productos del pedido a anular vs. el pedido de cambio (si existe).
3. Verificar coherencia: montos similares, mismos productos o equivalentes, misma sucursal/fecha, etc.
4. Emitir tu recomendación final.

**CRITERIOS PARA APROBAR:**
- El motivo es coherente y comprensible.
- Si hay pedido de cambio, los productos son iguales o muy similares (indica que es un cambio legítimo).
- Los montos son comparables (diferencia menor al 30%).
- El pedido a anular no está marcado como "ya ejecutado/anulado" sin justificación clara.

**CRITERIOS PARA RECHAZAR:**
- El motivo es vago, incoherente, o ausente sin justificación.
- Si hay pedido de cambio, los productos son completamente diferentes sin relación (posible fraude o error grave).
- Los montos difieren en más del 50% sin explicación.

**CRITERIOS PARA REVISAR (duda):**
- Información insuficiente para decidir con certeza.
- Discrepancias menores que requieren revisión humana.

**FORMATO DE RESPUESTA (solo JSON válido, sin texto adicional):**
{
  "decision": "aprobar|rechazar|revisar",
  "confianza": "alta|media|baja",
  "comentario": "Explicación clara y concisa de tu decisión (máx. 300 chars). Escribe en español, tono profesional.",
  "puntos": [
    "Punto clave 1 del análisis",
    "Punto clave 2 del análisis"
  ]
}

**REGLAS:**
- Solo devuelve JSON, sin markdown ni explicaciones adicionales.
- El campo "comentario" debe ser comprensible para un operador no técnico.
- Sé conciso y directo.
PROMPT;
}
?>
