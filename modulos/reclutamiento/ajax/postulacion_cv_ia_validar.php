<?php

/**
 * AJAX - Validador de CV con IA para el módulo de reclutamiento
 */
require_once '../../../core/database/conexion.php';
require_once '../../../core/ai/AIService.php';
require_once '../../../core/utils/DocumentParser.php';

header('Content-Type: application/json');

// Deshabilitar salida de errores para no romper el JSON
error_reporting(0);
ini_set('display_errors', 0);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $idPostulacion = (int)($input['id_postulacion'] ?? 0);
    $idPlaza = (int)($input['id_plaza'] ?? 0);

    if ($idPostulacion <= 0) {
        throw new Exception("ID de postulación inválido.");
    }

    // 1. Obtener datos del candidato y el perfil de la plaza
    if ($idPlaza > 0) {
        // Si tenemos el ID de la plaza específica, es más directo
        $sql = "SELECT pp.ruta_cv, pp.nombre as candidato_nombre, pc.perfil_ia_destilado
                FROM postulacion_plaza pp
                JOIN plazas_cargos pc ON pc.id = :id_plaza
                WHERE pp.id = :id_postulacion";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_plaza', $idPlaza, PDO::PARAM_INT);
        $stmt->bindValue(':id_postulacion', $idPostulacion, PDO::PARAM_INT);
    } else {
        // Fallback: buscar por cargo y sucursal aplicados
        $sql = "SELECT pp.ruta_cv, pp.nombre as candidato_nombre, pc.perfil_ia_destilado
                FROM postulacion_plaza pp
                JOIN plazas_cargos pc ON pp.cargo_aplicado = pc.cargo 
                WHERE pp.id = :id_postulacion
                AND (pp.sucursal_aplicada = pc.sucursal OR pc.sucursal IS NULL OR pc.sucursal = 0 OR pc.sucursal = 18 OR pc.sucursal = 6)
                ORDER BY pc.perfil_ia_destilado DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id_postulacion', $idPostulacion, PDO::PARAM_INT);
    }

    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception("No se encontró la postulación o el cargo asociado con perfil configurado.");
    }

    $rutaCv = $data['ruta_cv'];
    $perfilDestilado = $data['perfil_ia_destilado'];

    if (empty($rutaCv)) {
        throw new Exception("El candidato no tiene un CV cargado.");
    }

    if (empty($perfilDestilado)) {
        throw new Exception("La plaza no tiene un perfil de IA destilado. Por favor, asegúrese de que el perfil haya sido procesado en el Panel de Control.");
    }

    // 2. Localizar el archivo físico
    // Intentar varias rutas comunes según el entorno
    $possiblePaths = [
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $rutaCv,
        dirname(__DIR__, 3) . '/uploads/' . $rutaCv,
        '../../../uploads/' . $rutaCv
    ];

    $filePath = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $filePath = $path;
            break;
        }
    }

    // Si no se encuentra localmente, intentamos descargarlo del subdominio de talento
    // NOTA: Si rutaCv empieza con '../uploads/', el navegador lo resuelve bien con el prefijo, 
    // pero para CURL es mejor limpiar la ruta si detectamos que ya trae el prefijo 'uploads'
    $cleanRutaCv = $rutaCv;
    if (strpos($rutaCv, '../uploads/') === 0) {
        $cleanRutaCv = str_replace('../uploads/', '', $rutaCv);
    } else if (strpos($rutaCv, 'uploads/') === 0) {
        $cleanRutaCv = str_replace('uploads/', '', $rutaCv);
    }
    $cleanRutaCv = ltrim($cleanRutaCv, './');
    
    $remoteUrl = 'https://talento.batidospitaya.com/uploads/' . $cleanRutaCv;
    if (!$filePath) {
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cv_ai_' . $idPostulacion . '_' . basename($rutaCv);
        
        $ch = curl_init($remoteUrl);
        $fp = fopen($tempPath, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode === 200 && filesize($tempPath) > 0) {
            $filePath = $tempPath;
            $isTemp = true;
        } else if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    if (!$filePath) {
        throw new Exception("El archivo del CV no se pudo localizar localmente ni descargar desde: " . $remoteUrl);
    }

    // 3. Simular el objeto $_FILES para DocumentParser
    $mockFile = [
        'tmp_name' => $filePath,
        'name' => basename($filePath),
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($filePath),
        'type' => mime_content_type($filePath)
    ];

    $parsedCV = DocumentParser::parseDocument($mockFile);

    // 4. Preparar el análisis con IA
    $systemPrompt = "Eres un reclutador experto con años de experiencia en selección de personal. Analiza el CV del candidato y compáralo con el perfil del puesto requerido. Devuelve ÚNICAMENTE un objeto JSON válido, sin markdown ni explicaciones adicionales.";

    $promptExtra = "";
    $extraParts = [];
    if ($parsedCV['type'] === 'text') {
        $promptExtra = "\n\nCONTENIDO DEL CV:\n\"\"\"" . $parsedCV['content'] . "\"\"\"";
    } else {
        $extraParts[] = ['inline_data' => ['mime_type' => $parsedCV['mime_type'], 'data' => $parsedCV['data']]];
    }

    $prompt = construirPromptAnalisis($perfilDestilado) . $promptExtra;

    // 5. Llamar al servicio AI (Probando proveedores en cascada)
    $proveedores = ['google', 'groq', 'deepseek'];
    $texto_respuesta = null;
    $erroresProveedores = [];

    foreach ($proveedores as $prov) {
        try {
            $aiService = new AIService($conn, $prov);
            $texto_respuesta = $aiService->procesarPrompt($systemPrompt, $prompt, 0.2, $extraParts);
            if (!empty($texto_respuesta)) break;
        } catch (Exception $eAI) {
            $erroresProveedores[] = strtoupper($prov) . ": " . $eAI->getMessage();
        }
    }

    if (empty($texto_respuesta)) {
        throw new Exception("Sin respuesta de IA tras intentar con varios proveedores. Detalles: " . implode(" | ", $erroresProveedores));
    }

    // 6. Parsear resultado
    $resultadoParsed = parsearRespuesta($texto_respuesta);

    // 7. Guardar en validacion_cv_ia
    // El campo 'id_postulacion' es la FK hacia postulacion_plaza
    // Guardamos el puntaje en 'confianza' para que AVG(confianza) funcione en las consultas generales
    // Guardamos el JSON completo en 'valor' para consulta detallada

    // Limpiar análisis previos para este candidato en este campo específico si existieran (opcional, pero recomendado para AVG)
    $stmtDel = $conn->prepare("DELETE FROM validacion_cv_ia WHERE id_postulacion = ? AND campo = 'IA_Match_General'");
    $stmtDel->execute([$idPostulacion]);

    $sqlSave = "INSERT INTO validacion_cv_ia (id_postulacion, campo, valor, confianza, fecha_creacion) 
                VALUES (?, 'IA_Match_General', ?, ?, NOW())";
    $stmtSave = $conn->prepare($sqlSave);
    $stmtSave->execute([
        $idPostulacion,
        json_encode($resultadoParsed),
        $resultadoParsed['puntaje']
    ]);

    // También actualizamos el campo denormalizado analisis_ia en postulacion_plaza para acceso rápido
    $stmtUpdatePP = $conn->prepare("UPDATE postulacion_plaza SET analisis_ia = ? WHERE id = ?");
    $stmtUpdatePP->execute([json_encode($resultadoParsed), $idPostulacion]);

    echo json_encode([
        'success' => true,
        'puntaje' => $resultadoParsed['puntaje'],
        'resultado' => $resultadoParsed
    ]);

    // Limpiar archivo temporal si fue descargado
    if (isset($isTemp) && $isTemp && file_exists($filePath)) {
        unlink($filePath);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Auxiliares de Prompt y Parsing
 */
function construirPromptAnalisis($perfil)
{
    return "Analiza el CV del candidato contra el siguiente perfil de puesto:\n\"\"\"$perfil\"\"\"\n\nDevuelve ÚNICAMENTE un objeto JSON con esta estructura exacta (sin markdown, sin texto extra):\n{\n  \"puntaje\": (número entero del 0 al 100 que indica la compatibilidad del candidato con el perfil),\n  \"fortalezas\": [\"punto 1\", \"punto 2\", ...],\n  \"brechas\": [\"brecha 1\", \"brecha 2\", ...],\n  \"recomendacion\": \"Contratar\" | \"Entrevistar\" | \"Descartar\",\n  \"explicacion\": \"Análisis breve de 2-3 oraciones sobre la idoneidad del candidato\"\n}";
}

function parsearRespuesta($texto)
{
    $res = ['puntaje' => 0, 'fortalezas' => [], 'brechas' => [], 'recomendacion' => 'N/A', 'explicacion' => 'N/A'];

    $limpio = trim($texto);
    // Eliminar bloques de código markdown
    $limpio = preg_replace('/^```(?:json)?\s*/i', '', $limpio);
    $limpio = preg_replace('/\s*```$/i', '', $limpio);

    $json = json_decode($limpio, true);

    if ($json !== null && json_last_error() === JSON_ERROR_NONE) {
        $res['puntaje']      = isset($json['puntaje'])      ? (int)$json['puntaje']            : 0;
        $res['recomendacion'] = isset($json['recomendacion']) ? trim($json['recomendacion'])      : 'N/A';
        $res['explicacion']  = isset($json['explicacion'])  ? trim($json['explicacion'])        : 'N/A';
        $res['fortalezas']   = isset($json['fortalezas'])   && is_array($json['fortalezas'])   ? $json['fortalezas'] : [];
        $res['brechas']      = isset($json['brechas'])      && is_array($json['brechas'])      ? $json['brechas']    : [];
    } else {
        // Fallback robusto por si la IA no devuelve JSON puro
        if (preg_match('/puntaje[":]?\s*(\d+)/i', $texto, $m)) $res['puntaje'] = (int)$m[1];
        if (preg_match('/recomendaci[oó]n[":]?\s*["\']?([^"\',\n}]+)/iu', $texto, $m)) $res['recomendacion'] = trim($m[1]);
        if (preg_match('/explicaci[oó]n[":]?\s*["\']?([^"\'\n}]{10,})/iu', $texto, $m)) $res['explicacion'] = trim($m[1]);

        // Extraer listas
        foreach (['fortalezas' => 'fortalezas', 'brechas' => 'brechas'] as $key => $field) {
            if (preg_match('/"?' . $key . '"?\s*:\s*\[([^\]]+)\]/is', $texto, $m)) {
                foreach (preg_split('/["\'],\s*["\']/', trim($m[1], '[]"\' ')) as $l) {
                    if (trim($l)) $res[$field][] = trim(trim($l, '"\' '));
                }
            }
        }
    }
    return $res;
}
