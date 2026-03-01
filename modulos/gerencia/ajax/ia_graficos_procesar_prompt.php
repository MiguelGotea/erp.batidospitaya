<?php
@session_start();
require_once '../../../core/database/conexion.php';
require_once '../../../core/ai/AIService.php';

header('Content-Type: application/json');

try {
    // Obtener prompt
    $input = json_decode(file_get_contents('php://input'), true);
    $prompt = isset($input['prompt']) ? trim($input['prompt']) : '';

    if (empty($prompt)) {
        throw new Exception('Prompt vacío');
    }

    // Validar longitud
    if (strlen($prompt) > 500) {
        throw new Exception('El prompt es demasiado largo (máximo 500 caracteres)');
    }

    // Verificar caché
    $promptHash = md5($prompt);
    $cacheStmt = $conn->prepare("
        SELECT estructura_json, hits 
        FROM ia_graficos_cache 
        WHERE prompt_hash = ? 
        AND expires_at > NOW()
        LIMIT 1
    ");
    $cacheStmt->execute([$promptHash]);
    $cache = $cacheStmt->fetch();

    if ($cache) {
        // Actualizar hits
        $updateStmt = $conn->prepare("UPDATE ia_graficos_cache SET hits = hits + 1 WHERE prompt_hash = ?");
        $updateStmt->execute([$promptHash]);

        echo json_encode([
            'success' => true,
            'data' => json_decode($cache['estructura_json'], true),
            'from_cache' => true
        ]);
        exit();
    }

    // Cargar contexto de negocio
    $contexto = cargarContextoNegocio($conn);
    $systemPrompt = construirSystemPromptGraficos($contexto);

    // Procesar con IA usando proveedor 'groq' por defecto
    $aiService = new AIService($conn, 'groq');
    // AIService Core returns the text response instead of parsed data, so we must parse it
    $respuestaTexto = $aiService->procesarPrompt($systemPrompt, $prompt);
    $estructura = $aiService->extraerJSON($respuestaTexto);

    // Validar estructura
    $validacion = validarEstructura($estructura, $conn);
    if (!$validacion['valido']) {
        throw new Exception('Estructura inválida generada por la IA: ' . implode(', ', $validacion['errores']));
    }

    // Guardar en caché
    $usuarioId = $_SESSION['usuario_id'] ?? 1;
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $insertStmt = $conn->prepare("
        INSERT INTO ia_graficos_cache 
        (prompt_hash, prompt_original, estructura_json, usuario_id, expires_at)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        estructura_json = VALUES(estructura_json),
        expires_at = VALUES(expires_at),
        usuario_id = VALUES(usuario_id)
    ");
    $insertStmt->execute([
        $promptHash,
        $prompt,
        json_encode($estructura),
        $usuarioId,
        $expiresAt
    ]);

    echo json_encode([
        'success' => true,
        'data' => $estructura,
        'from_cache' => false
    ]);

} catch (Exception $e) {
    error_log('Error ia_graficos_procesar_prompt: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Cargar contexto de negocio
 */
function cargarContextoNegocio($conn)
{
    // Diccionario de columnas
    $stmtDict = $conn->query("
        SELECT tabla_origen, columna_bd, nombre_negocio, tipo_dato, descripcion, 
               es_metrica, es_dimension, alias_busqueda, valores_enum
        FROM ia_graficos_diccionario_columnas
        WHERE activo = 1
    ");
    $diccionario = $stmtDict->fetchAll();

    // Métricas predefinidas
    $stmtMetricas = $conn->query("
        SELECT nombre_metrica, palabras_clave, funcion_sql, columna_origen, 
               tabla_origen, formato_salida, alias_sql
        FROM ia_graficos_metricas_predefinidas
        WHERE activo = 1
    ");
    $metricas = $stmtMetricas->fetchAll();

    // Filtros conceptuales
    $stmtFiltros = $conn->query("
        SELECT concepto, palabras_clave, condicion_sql, descripcion
        FROM ia_graficos_filtros_conceptuales
        WHERE activo = 1
        ORDER BY prioridad DESC
    ");
    $filtros = $stmtFiltros->fetchAll();

    return [
        'diccionario_columnas' => json_encode($diccionario),
        'metricas_predefinidas' => json_encode($metricas),
        'filtros_conceptuales' => json_encode($filtros)
    ];
}

/**
 * Validar estructura generada por IA
 */
function validarEstructura($estructura, $conn)
{
    $errores = [];

    // Validar campos requeridos
    if (empty($estructura['tipo_grafico'])) {
        $errores[] = 'Falta tipo de gráfico';
    }

    if (empty($estructura['metrica_nombre'])) {
        $errores[] = 'Falta métrica';
    }

    // Validar tipo de gráfico
    $tiposValidos = ['lineal', 'barras', 'circular', 'area', 'columnas'];
    if (!in_array($estructura['tipo_grafico'], $tiposValidos)) {
        $errores[] = 'Tipo de gráfico no válido';
    }

    // Validar que las columnas existan
    if (!empty($estructura['metrica_columna'])) {
        if (!existeColumna($estructura['metrica_columna'], $conn)) {
            $errores[] = 'Columna de métrica no existe: ' . $estructura['metrica_columna'];
        }
    }

    if (!empty($estructura['dimension_columna'])) {
        if (!existeColumna($estructura['dimension_columna'], $conn)) {
            $errores[] = 'Columna de dimensión no existe: ' . $estructura['dimension_columna'];
        }
    }

    return [
        'valido' => empty($errores),
        'errores' => $errores
    ];
}

/**
 * Verificar si columna existe
 */
function existeColumna($columna, $conn)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM ia_graficos_diccionario_columnas 
        WHERE columna_bd = ? AND activo = 1
    ");
    $stmt->execute([$columna]);
    $result = $stmt->fetch();
    return $result['total'] > 0;
}
/**
 * Construir system prompt con contexto
 */
function construirSystemPromptGraficos($contexto)
{
    return <<<PROMPT
Eres un asistente especializado en analizar consultas de ventas y convertirlas en estructuras de datos para gráficos.

**TU OBJETIVO:**
Interpretar el prompt del usuario y devolver ÚNICAMENTE un objeto JSON válido con la estructura requerida.

**CONTEXTO DE NEGOCIO:**
{$contexto['diccionario_columnas']}

**MÉTRICAS DISPONIBLES:**
{$contexto['metricas_predefinidas']}

**FILTROS CONCEPTUALES:**
{$contexto['filtros_conceptuales']}

**REGLAS ESTRICTAS:**
1. SOLO devuelve JSON válido, sin texto adicional
2. SOLO usa columnas que existen en el diccionario
3. SOLO usa métricas predefinidas
4. Aplica filtros conceptuales cuando corresponda
5. El tipo de gráfico debe ser: "lineal", "barras", "circular", "area" o "columnas"
6. Siempre incluye una descripción clara del gráfico

**ESTRUCTURA JSON REQUERIDA:**
```json
{
    "tipo_grafico": "tipo del gráfico",
    "metrica_nombre": "nombre de la métrica",
    "metrica_columna": "columna de BD",
    "metrica_funcion": "función SQL (SUM, AVG, COUNT, etc)",
    "formato_metrica": "moneda|numero|porcentaje",
    "dimension_nombre": "nombre de la dimensión",
    "dimension_columna": "columna para agrupar",
    "dimension_tipo": "temporal|categorica",
    "rango_temporal": {
        "tipo": "dias|semanas|meses",
        "cantidad": numero,
        "descripcion": "descripción legible"
    },
    "filtros": [
        {
            "columna": "nombre columna",
            "operador": "=|>|<|LIKE|IN",
            "valor": "valor",
            "descripcion": "descripción del filtro"
        }
    ],
    "descripcion_grafico": "descripción completa de lo que muestra el gráfico",
    "observaciones": "notas adicionales si aplica"
}
```

**EJEMPLOS:**

Prompt: "gráfico lineal de ventas diarias de las últimas dos semanas"
```json
{
    "tipo_grafico": "lineal",
    "metrica_nombre": "total de ventas",
    "metrica_columna": "Precio",
    "metrica_funcion": "SUM",
    "formato_metrica": "moneda",
    "dimension_nombre": "fecha de venta",
    "dimension_columna": "Fecha",
    "dimension_tipo": "temporal",
    "rango_temporal": {
        "tipo": "dias",
        "cantidad": 14,
        "descripcion": "últimas 2 semanas"
    },
    "filtros": [
        {
            "columna": "Anulado",
            "operador": "=",
            "valor": "0",
            "descripcion": "ventas válidas"
        }
    ],
    "descripcion_grafico": "Evolución de ventas totales por día durante las últimas dos semanas",
    "observaciones": null
}
```

**IMPORTANTE:**
- Devuelve SOLO el JSON, sin ```json ni explicaciones
- Valida que las columnas existan en el diccionario
- Si algo no está claro, usa valores por defecto razonables
- Siempre aplica filtro de "Anulado = 0" para ventas válidas
PROMPT;
}
?>