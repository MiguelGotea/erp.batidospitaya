<?php
/**
 * Procesar foto de factura para reembolso mediante IA
 * Ubicación: /modulos/compras/ajax/reembolsos_ia_procesar_foto.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

@session_start();
require_once '../../../core/database/conexion.php';
require_once '../../../core/ai/AIService.php';

header('Content-Type: application/json');

try {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir la imagen.');
    }

    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filename = time() . '_' . basename($_FILES['foto']['name']);
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $targetPath)) {
        throw new Exception('No se pudo guardar la imagen en el servidor.');
    }

    // Convertir a Base64 para la IA
    $imageData = base64_encode(file_get_contents($targetPath));
    $mimeType = mime_content_type($targetPath);

    // Prompt del sistema
    $systemPrompt = "Eres un asistente experto en contabilidad. Tu tarea es extraer información de fotos de facturas o tickets de gastos. 
    Debes identificar los artículos o conceptos pagados y devolver un JSON con la lista de items.
    
    Cada item debe tener:
    - cantidad: el número de unidades (numérico)
    - detalle: descripción corta del producto o servicio
    - total_cordobas: el monto total de esa línea en Córdobas (numérico)
    
    Si la factura está en otra moneda, intenta convertir a Córdobas si hay un tipo de cambio visible, de lo contrario devuelve el monto tal cual pero indícalo en el detalle.
    
    IMPORTANTE: Devuelve ÚNICAMENTE el objeto JSON, sin explicaciones, sin bloques de código markdown, sin texto adicional.";

    $userPrompt = "Extrae los datos de esta factura para un resumen de reembolso.";

    // Partes extra para Vision (Gemini format)
    $extraParts = [
        [
            'inline_data' => [
                'mime_type' => $mimeType,
                'data' => $imageData
            ]
        ]
    ];

    // Cascada de proveedores que soportan visión
    $proveedores = ['google', 'openai', 'groq'];
    $items = [];
    $proveedorUsado = '';
    $errores = [];

    foreach ($proveedores as $prov) {
        try {
            $ai = new AIService($conn, $prov);
            
            if ($prov === 'openai') {
                $ai->setModel('gpt-4o-mini');
            } else if ($prov === 'groq') {
                $ai->setModel('llama-3.2-90b-vision-preview');
            }

            $respuesta = $ai->procesarPrompt($systemPrompt, $userPrompt, 0.1, $extraParts);
            if ($respuesta) {
                $items = $ai->extraerJSON($respuesta);
                $proveedorUsado = $prov;
                break;
            }
        } catch (Exception $e) {
            $errores[] = strtoupper($prov) . ": " . $e->getMessage();
            continue;
        }
    }

    if (empty($items)) {
        throw new Exception("No se pudo procesar la imagen con ninguna IA. Errores: " . implode(" | ", $errores));
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
