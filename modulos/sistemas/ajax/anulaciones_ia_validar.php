<?php
/**
 * ajax/anulaciones_ia_validar.php
 * Valida una solicitud de anulación usando IA (AIService).
 * Analiza el pedido a anular vs. el pedido de cambio y emite una decisión textual.
 */

@session_start();
require_once '../../../core/database/conexion.php';
require_once '../../../core/ai/AIService.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // ── Parámetros requeridos ───────────────────────────────
    $codAnulacion  = isset($input['cod_anulacion_host']) ? intval($input['cod_anulacion_host']) : 0;
    $motivo        = isset($input['motivo'])             ? trim($input['motivo'])               : '';
    $pedidoPrinc   = isset($input['pedido_principal'])   ? $input['pedido_principal']           : null;
    $pedidoCambio  = isset($input['pedido_cambio'])      ? $input['pedido_cambio']              : null;

    if (!$codAnulacion || !$pedidoPrinc) {
        throw new Exception('Faltan datos del pedido para el análisis IA.');
    }

    // ── Construir contexto de los pedidos ───────────────────
    $textoPedidoPrincipal = formatearPedidoParaIA($pedidoPrinc);
    $textoPedidoCambio    = $pedidoCambio ? formatearPedidoParaIA($pedidoCambio) : null;

    // ── System Prompt ────────────────────────────────────────
    $systemPrompt = construirSystemPromptAnulacion();

    // ── User Prompt ──────────────────────────────────────────
    $userPrompt = "SOLICITUD DE ANULACIÓN #$codAnulacion\n\n";
    $userPrompt .= "MOTIVO DECLARADO: " . ($motivo ?: 'No especificado') . "\n\n";
    $userPrompt .= "=== PEDIDO A ANULAR ===\n" . $textoPedidoPrincipal . "\n\n";

    if ($textoPedidoCambio) {
        $userPrompt .= "=== PEDIDO DE CAMBIO / SUSTITUTO ===\n" . $textoPedidoCambio . "\n\n";
    } else {
        $userPrompt .= "=== PEDIDO DE CAMBIO ===\nNo aplica — es una anulación simple sin pedido sustituto.\n\n";
    }

    $userPrompt .= "Analiza esta solicitud y emite tu decisión en el formato JSON especificado.";

    // ── Llamar a IA en cascada de proveedores ────────────────
    $proveedores = ['google', 'openai', 'deepseek', 'mistral', 'cerebras', 'groq'];
    $respuestaTexto = null;
    $aiService      = null;
    $erroresAcum    = [];

    foreach ($proveedores as $prov) {
        try {
            $svc = new AIService($conn, $prov);
            $respuestaTexto = $svc->procesarPrompt($systemPrompt, $userPrompt, 0.1);
            if ($respuestaTexto) {
                $aiService = $svc;
                break;
            }
        } catch (Exception $e) {
            $erroresAcum[] = strtoupper($prov) . ': ' . $e->getMessage();
            continue;
        }
    }

    if (!$respuestaTexto) {
        throw new Exception('Ningún proveedor de IA pudo procesar la solicitud. ' . implode(' | ', $erroresAcum));
    }

    // ── Extraer JSON de respuesta ────────────────────────────
    $resultado = $aiService->extraerJSON($respuestaTexto);

    // Validación mínima
    if (empty($resultado['decision'])) {
        throw new Exception('La IA no retornó una decisión válida.');
    }

    $decision   = strtolower($resultado['decision']);
    $confianza  = $resultado['confianza']  ?? 'media';
    $comentario = $resultado['comentario'] ?? '';
    $puntos     = $resultado['puntos']     ?? [];
    $proveedor  = $aiService->getProveedor();

    // ── Guardar veredicto en la fila de AnulacionPedidosHost ─
    $iaResultadoJson = json_encode([
        'decision'   => $decision,
        'confianza'  => $confianza,
        'comentario' => $comentario,
        'puntos'     => $puntos,
        'proveedor'  => $proveedor,
        'fecha'      => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);

    try {
        $conn->prepare(
            "UPDATE AnulacionPedidosHost
             SET ia_decision  = :dec,
                 ia_resultado = :res
             WHERE CodAnulacionHost = :id"
        )->execute([
            ':dec' => $decision,
            ':res' => $iaResultadoJson,
            ':id'  => $codAnulacion,
        ]);
    } catch (Exception $eDb) {
        // No fatal — el veredicto igual se retorna al frontend
        error_log('anulaciones_ia_validar: no pudo guardar ia_resultado: ' . $eDb->getMessage());
    }

    echo json_encode([
        'success'    => true,
        'decision'   => $decision,
        'confianza'  => $confianza,
        'comentario' => $comentario,
        'puntos'     => $puntos,
        'proveedor'  => $proveedor,
    ]);

} catch (Exception $e) {
    error_log('Error anulaciones_ia_validar: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

// ── Helpers ──────────────────────────────────────────────────

/**
 * Convierte la estructura de un pedido (resumen + items) en texto legible para la IA.
 */
function formatearPedidoParaIA($pedido): string
{
    $resumen = $pedido['resumen'] ?? [];
    $items   = $pedido['items']   ?? [];

    $lineas = [];
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
        $nombre = $it['DBBatidos_Nombre'] ?? $it['NombreGrupo'] ?? '?';
        $cant   = $it['Cantidad']         ?? 0;
        $medida = $it['Medida']           ?? '';
        $precio = number_format(floatval($it['Precio_Unitario_Sin_Descuento'] ?? $it['Precio'] ?? 0), 2);
        $lineas[] = "  - $nombre | $medida | Cant: $cant | P.U: C$ $precio";
    }

    return implode("\n", $lineas);
}

/**
 * System prompt específico para validación de anulaciones.
 */
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
- Los montos son comparables.
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
