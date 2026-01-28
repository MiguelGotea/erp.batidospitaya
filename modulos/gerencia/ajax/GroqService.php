<?php
/**
 * Servicio de integración con Groq API
 * Modelo: llama-3.3-70b-versatile
 */

class GroqService
{

    private $apiKey;
    private $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    private $model = 'llama-3.3-70b-versatile';

    public function __construct()
    {
        $this->apiKey = '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';
    }

    /**
     * Procesar prompt con contexto de negocio
     */
    public function procesarPrompt($prompt, $contexto)
    {
        $systemPrompt = $this->construirSystemPrompt($contexto);

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 2000,
            'top_p' => 0.9
        ];

        try {
            $ch = curl_init($this->endpoint);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                throw new Exception('Error cURL: ' . curl_error($ch));
            }

            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception('Error API: HTTP ' . $httpCode);
            }

            $result = json_decode($response, true);

            if (!isset($result['choices'][0]['message']['content'])) {
                throw new Exception('Respuesta inválida de la API');
            }

            $content = $result['choices'][0]['message']['content'];

            // Extraer JSON de la respuesta
            return $this->extraerJSON($content);

        } catch (Exception $e) {
            error_log('Error GroqService: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Construir system prompt con contexto
     */
    private function construirSystemPrompt($contexto)
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

Prompt: "ventas por sucursal del último mes en gráfico de barras"
```json
{
    "tipo_grafico": "barras",
    "metrica_nombre": "total de ventas",
    "metrica_columna": "Precio",
    "metrica_funcion": "SUM",
    "formato_metrica": "moneda",
    "dimension_nombre": "sucursal",
    "dimension_columna": "Sucursal_Nombre",
    "dimension_tipo": "categorica",
    "rango_temporal": {
        "tipo": "dias",
        "cantidad": 30,
        "descripcion": "último mes"
    },
    "filtros": [
        {
            "columna": "Anulado",
            "operador": "=",
            "valor": "0",
            "descripcion": "ventas válidas"
        }
    ],
    "descripcion_grafico": "Comparación de ventas totales por sucursal durante el último mes",
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

    /**
     * Extraer JSON de la respuesta
     */
    private function extraerJSON($content)
    {
        // Limpiar posibles backticks
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*$/i', '', $content);
        $content = trim($content);

        // Intentar decodificar
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON inválido: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
?>