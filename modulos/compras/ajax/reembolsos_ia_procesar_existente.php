<?php
/**
 * Procesar foto de factura existente en mantenimiento mediante IA
 * Ubicación: /modulos/compras/ajax/reembolsos_ia_procesar_existente.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

@session_start();
require_once '../../../core/database/conexion.php';
require_once '../../../core/ai/AIService.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $ruta_relativa = isset($input['ruta']) ? $input['ruta'] : null;

    if (!$ruta_relativa) {
        throw new Exception('Ruta de imagen no proporcionada.');
    }

    // Validar que la ruta sea de mantenimiento para seguridad
    if (strpos($ruta_relativa, 'modulos/mantenimiento/uploads/compras/') === false) {
        throw new Exception('Ruta de imagen no válida o no permitida.');
    }

    $sourcePath = '../../../' . $ruta_relativa;
    if (!file_exists($sourcePath)) {
        throw new Exception('El archivo original no existe en el servidor: ' . $ruta_relativa);
    }

    // Copiar a la carpeta de reembolsos para mantener consistencia
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = 'maint_' . time() . '_' . basename($ruta_relativa);
    $targetPath = $uploadDir . $filename;

    if (!copy($sourcePath, $targetPath)) {
        throw new Exception('No se pudo copiar la imagen a la carpeta de reembolsos.');
    }

    // Convertir a Base64 para la IA
    $imageData = base64_encode(file_get_contents($targetPath));
    $mimeType = mime_content_type($targetPath);

    // Prompt del sistema (Idéntico al original)
    $systemPrompt = "Eres un asistente experto en contabilidad. Tu tarea es extraer información de facturas o tickets (imagenes o PDF). 
    Debes identificar los artículos o conceptos pagados y devolver un JSON con la lista de items.
    
    Cada item debe tener:
    - cantidad: el número de unidades (numérico)
    - detalle: descripción corta del producto o servicio
    - total_cordobas: el monto total de esa línea en Córdobas (numérico)
    
    CRÍTICO: Incluye líneas específicas para IMPUESTOS (IVA, IR, etc.) si aparecen en la factura, para que la suma total sea correcta.
    
    Si la factura está en otra moneda, intenta convertir a Córdobas si hay un tipo de cambio visible, de lo contrario devuelve el monto tal cual pero indícalo en el detalle.
    
    IMPORTANTE: Devuelve ÚNICAMENTE un ARREGLO JSON (array de objetos), sin explicaciones ni bloques de código markdown.";

    $userPrompt = "Extrae los datos de este documento para un resumen de reembolso.";

    $extraParts = [
        [
            'inline_data' => [
                'mime_type' => $mimeType,
                'data' => $imageData
            ]
        ]
    ];

    $proveedores = ['google', 'openai', 'groq'];
    $items = [];
    $proveedorUsado = '';

    foreach ($proveedores as $prov) {
        try {
            $ai = new AIService($conn, $prov);
            if ($prov === 'openai') $ai->setModel('gpt-4o-mini');
            else if ($prov === 'groq') $ai->setModel('llama-3.2-90b-vision-preview');

            $respuesta = $ai->procesarPrompt($systemPrompt, $userPrompt, 0.1, $extraParts);
            if ($respuesta) {
                $items = $ai->extraerJSON($respuesta);
                $proveedorUsado = $prov;
                break;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    if (empty($items)) {
        throw new Exception("No se pudo procesar el documento con IA.");
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
        'proveedor' => $proveedorUsado,
        'foto_path' => 'modulos/compras/uploads/' . $filename
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
