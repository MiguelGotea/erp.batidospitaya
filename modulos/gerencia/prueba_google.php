<?php
/**
 * Script de prueba para DeepSeek API - Generación de SQL
 * 
 * Optimizado para mínimo consumo de tokens y generación precisa de SQL
 */


class DeepSeekConfig
{
    // Reemplaza con tu API key de DeepSeek
    const API_KEY = 'sk-5b2ba9f0d22e4a438c166ea1f96f0ee5';

    // Modelos disponibles (febrero 2026)
    const MODEL_V4 = 'deepseek-v4';           // Modelo principal recomendado
    const MODEL_LITE = 'deepseek-lite';       // Versión más económica
    const MODEL_CODER = 'deepseek-coder';     // Especializado en código

    // Modelo activo (cambia según necesidad)
    const ACTIVE_MODEL = self::MODEL_V4;

    // URLs de API
    const API_URL = 'https://api.deepseek.com/v1/chat/completions';

    // Configuración de caché (importante para ahorrar tokens)
    const ENABLE_CACHE = true;
    const CACHE_TTL = 3600; // 1 hora en segundos
}

/**
 * Clase para generar SQL usando DeepSeek API
 */
class DeepSeekSQLGenerator
{
    private $apiKey;
    private $model;
    private $cache = [];

    public function __construct($apiKey, $model = DeepSeekConfig::MODEL_V4)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Genera SQL a partir de una pregunta en lenguaje natural
     * 
     * @param string $question Pregunta del usuario
     * @param string $schema Esquema de la base de datos
     * @param array $options Opciones adicionales
     * @return array Respuesta con SQL generado
     */
    public function generateSQL($question, $schema, $options = [])
    {
        // Verificar caché primero
        if (DeepSeekConfig::ENABLE_CACHE) {
            $cacheKey = md5($question . $schema);
            if (isset($this->cache[$cacheKey])) {
                $cached = $this->cache[$cacheKey];
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        // Configuración por defecto
        $defaultOptions = [
            'temperature' => 0.2,        // Bajo para respuestas precisas
            'max_tokens' => 500,          // Suficiente para SQL típico
            'top_p' => 0.9,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ];

        $options = array_merge($defaultOptions, $options);

        // Prompt optimizado para mínimo consumo de tokens
        $systemPrompt = "Eres un experto en SQL. Genera SOLO la consulta SQL, sin explicaciones. Usa el esquema proporcionado.";

        $userPrompt = "Esquema: $schema\n\nPregunta: $question\n\nSQL:";

        // Preparar mensajes
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        // Calcular tokens aproximados (para monitoreo)
        $inputTokens = $this->estimateTokens($systemPrompt . $userPrompt);

        // Configurar la solicitud
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
            'top_p' => $options['top_p'],
            'frequency_penalty' => $options['frequency_penalty'],
            'presence_penalty' => $options['presence_penalty'],
            'stream' => false
        ];

        // Si el modelo soporta y queremos formato JSON
        if ($this->model === DeepSeekConfig::MODEL_V4) {
            $data['response_format'] = ['type' => 'text'];
        }

        // Realizar la solicitud
        $result = $this->callAPI($data);

        if ($result['success']) {
            // Extraer solo el SQL de la respuesta
            $sql = $this->extractSQL($result['content']);

            $response = [
                'success' => true,
                'sql' => $sql,
                'full_response' => $result['content'],
                'input_tokens' => $inputTokens,
                'output_tokens' => $result['usage']['completion_tokens'] ?? $this->estimateTokens($sql),
                'total_tokens' => $inputTokens + ($result['usage']['completion_tokens'] ?? $this->estimateTokens($sql)),
                'model' => $this->model,
                'from_cache' => false
            ];

            // Guardar en caché
            if (DeepSeekConfig::ENABLE_CACHE) {
                $cacheKey = md5($question . $schema);
                $this->cache[$cacheKey] = $response;
            }

            return $response;
        }

        return $result;
    }

    /**
     * Llama a la API de DeepSeek
     */
    private function callAPI($data)
    {
        $ch = curl_init(DeepSeekConfig::API_URL);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $error
            ];
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $result['error']['message'] ?? 'Error desconocido';
            return [
                'success' => false,
                'error' => $errorMsg,
                'http_code' => $httpCode
            ];
        }

        return [
            'success' => true,
            'content' => $result['choices'][0]['message']['content'] ?? '',
            'usage' => $result['usage'] ?? []
        ];
    }

    /**
     * Extrae solo SQL de la respuesta (elimina explicaciones)
     */
    private function extractSQL($text)
    {
        // Buscar bloques de código SQL
        if (preg_match('/```sql\s*(.*?)\s*```/is', $text, $matches)) {
            return trim($matches[1]);
        }

        // Buscar cualquier bloque de código
        if (preg_match('/```\s*(.*?)\s*```/is', $text, $matches)) {
            return trim($matches[1]);
        }

        // Si no hay bloques, tomar todo el texto (asumiendo que es SQL)
        return trim($text);
    }

    /**
     * Estima tokens (aproximado - para monitoreo)
     */
    private function estimateTokens($text)
    {
        return (int) (strlen($text) / 4); // Aproximación: 4 caracteres ≈ 1 token
    }

    /**
     * Obtiene estadísticas de costo
     */
    public function calculateCost($tokens, $type = 'total')
    {
        // Tarifas aproximadas (febrero 2026)
        $rates = [
            'input' => 0.00000040,  // $0.40 por millón = $0.0000004 por token
            'output' => 0.00000160   // $1.60 por millón = $0.0000016 por token
        ];

        if ($type === 'input') {
            return $tokens * $rates['input'];
        } elseif ($type === 'output') {
            return $tokens * $rates['output'];
        } else {
            return $tokens * $rates['output']; // Simplificación
        }
    }
}

/**
 * Clase para manejo de esquemas de base de datos
 */
class DatabaseSchema
{
    private $schema = [];

    /**
     * Agrega una tabla al esquema
     */
    public function addTable($name, $columns, $primaryKey = null)
    {
        $this->schema[$name] = [
            'columns' => $columns,
            'primaryKey' => $primaryKey
        ];
    }

    /**
     * Genera representación textual del esquema (optimizada para tokens)
     */
    public function generateSchemaText()
    {
        $text = "";
        foreach ($this->schema as $tableName => $table) {
            $text .= "$tableName(";
            $cols = [];
            foreach ($table['columns'] as $colName => $colType) {
                $cols[] = "$colName:$colType";
            }
            $text .= implode(',', $cols) . ") ";
        }
        return trim($text);
    }

    /**
     * Ejemplo: esquema de tienda online
     */
    public static function createStoreSchema()
    {
        $schema = new self();
        $schema->addTable('usuarios', [
            'id' => 'int',
            'nombre' => 'varchar',
            'email' => 'varchar',
            'fecha_registro' => 'date'
        ]);
        $schema->addTable('productos', [
            'id' => 'int',
            'nombre' => 'varchar',
            'precio' => 'decimal',
            'stock' => 'int'
        ]);
        $schema->addTable('pedidos', [
            'id' => 'int',
            'usuario_id' => 'int',
            'fecha' => 'datetime',
            'total' => 'decimal'
        ]);
        $schema->addTable('detalles_pedido', [
            'id' => 'int',
            'pedido_id' => 'int',
            'producto_id' => 'int',
            'cantidad' => 'int',
            'precio_unitario' => 'decimal'
        ]);
        return $schema;
    }
}

/**
 * Ejemplo de uso interactivo
 */
function main()
{
    echo "========================================\n";
    echo "   GENERADOR SQL CON DEEPSEEK API\n";
    echo "========================================\n\n";

    // Verificar API key
    if (DeepSeekConfig::API_KEY === 'TU_API_KEY_DEEPSEEK_AQUI') {
        echo "⚠️  Configura tu API key en el archivo\n";
        echo "1. Obtén tu API key en: https://platform.deepseek.com/\n";
        echo "2. Edita la línea 9 con tu key\n\n";
        exit(1);
    }

    // Inicializar generador
    $generator = new DeepSeekSQLGenerator(DeepSeekConfig::API_KEY, DeepSeekConfig::MODEL_V4);

    // Crear esquema de ejemplo
    $schema = DatabaseSchema::createStoreSchema();
    $schemaText = $schema->generateSchemaText();

    echo "✅ Cliente DeepSeek inicializado\n";
    echo "📊 Modelo: " . DeepSeekConfig::ACTIVE_MODEL . "\n";
    echo "💰 Tarifas: Entrada $0.40/M, Salida $1.60/M tokens\n";
    echo "📋 Esquema cargado: usuarios, productos, pedidos, detalles_pedido\n\n";

    echo "Preguntas de ejemplo:\n";
    echo "  - 'Lista todos los usuarios registrados en 2025'\n";
    echo "  - 'Muestra el total de ventas por producto'\n";
    echo "  - 'Encuentra los 5 productos más vendidos'\n";
    echo "  - 'Clientes que han gastado más de 1000€'\n";
    echo "  - 'Pedidos con sus detalles y totales'\n\n";

    while (true) {
        echo "🤔 Tu pregunta (o 'salir'): ";
        $handle = fopen("php://stdin", "r");
        $question = trim(fgets($handle));

        if (strtolower($question) === 'salir' || strtolower($question) === 'exit') {
            echo "👋 ¡Hasta luego!\n";
            break;
        }

        if (empty($question)) {
            echo "⚠️  Por favor ingresa una pregunta\n\n";
            continue;
        }

        echo "\n⏳ Generando SQL...\n";

        // Generar SQL
        $result = $generator->generateSQL($question, $schemaText);

        if ($result['success']) {
            echo "\n✅ SQL GENERADO:\n";
            echo "----------------------------------------\n";
            echo $result['sql'] . "\n";
            echo "----------------------------------------\n";

            // Estadísticas de costo
            $inputCost = $generator->calculateCost($result['input_tokens'], 'input');
            $outputCost = $generator->calculateCost($result['output_tokens'], 'output');
            $totalCost = $inputCost + $outputCost;

            echo "\n📊 Estadísticas:\n";
            echo "  • Tokens entrada: {$result['input_tokens']}\n";
            echo "  • Tokens salida: {$result['output_tokens']}\n";
            echo "  • Total: {$result['total_tokens']}\n";
            echo "  • Costo estimado: $" . number_format($totalCost, 6) . "\n";

            if (isset($result['from_cache']) && $result['from_cache']) {
                echo "  • ⚡ Respuesta desde caché (costo 0)\n";
            }
        } else {
            echo "\n❌ Error: {$result['error']}\n";
        }

        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
}

// Ejecutar si es CLI
if (php_sapi_name() === 'cli') {
    main();
}
?>