<?php
/**
 * Página web interactiva para Generación SQL con DeepSeek API
 * 
 * Interfaz web para generar consultas SQL desde lenguaje natural
 */

class DeepSeekConfig
{
    // Reemplaza con tu API key de DeepSeek
    const API_KEY = 'sk-5b2ba9f0d22e4a438c166ea1f96f0ee5';

    // Modelos disponibles (febrero 2026)
    const MODEL_V4 = 'deepseek-v4';           // Modelo principal recomendado
    const MODEL_LITE = 'deepseek-lite';       // Versión más económica
    const MODEL_CODER = 'deepseek-coder';     // Especializado en código

    // URLs de API
    const API_URL = 'https://api.deepseek.com/v1/chat/completions';
}

/**
 * Clase para generar SQL usando DeepSeek API
 */
class DeepSeekSQLGenerator
{
    private $apiKey;
    private $model;

    public function __construct($apiKey, $model = DeepSeekConfig::MODEL_V4)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Genera SQL a partir de una pregunta en lenguaje natural
     */
    public function generateSQL($question, $schema, $options = [])
    {
        // Configuración por defecto
        $defaultOptions = [
            'temperature' => 0.2,
            'max_tokens' => 500,
            'top_p' => 0.9
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

        // Configurar la solicitud
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
            'top_p' => $options['top_p'],
            'stream' => false
        ];

        // Realizar la solicitud
        $result = $this->callAPI($data);

        if ($result['success']) {
            // Extraer solo el SQL de la respuesta
            $sql = $this->extractSQL($result['content']);

            return [
                'success' => true,
                'sql' => $sql,
                'full_response' => $result['content'],
                'input_tokens' => $this->estimateTokens($systemPrompt . $userPrompt),
                'output_tokens' => $result['usage']['completion_tokens'] ?? $this->estimateTokens($sql),
                'total_tokens' => $this->estimateTokens($systemPrompt . $userPrompt) +
                    ($result['usage']['completion_tokens'] ?? $this->estimateTokens($sql)),
                'model' => $this->model
            ];
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
     * Extrae solo SQL de la respuesta
     */
    private function extractSQL($text)
    {
        // Buscar bloques de código SQL
        if (preg_match('/```sql\s*(.*?)\s*```/is', $text, $matches)) {
            return trim($matches[1]);
        }
        if (preg_match('/```\s*(.*?)\s*```/is', $text, $matches)) {
            return trim($matches[1]);
        }
        return trim($text);
    }

    /**
     * Estima tokens (aproximado)
     */
    private function estimateTokens($text)
    {
        return (int) (strlen($text) / 4);
    }

    /**
     * Calcula costo estimado
     */
    public function calculateCost($tokens, $type = 'total')
    {
        $rates = [
            'input' => 0.00000040,
            'output' => 0.00000160
        ];

        if ($type === 'input') {
            return $tokens * $rates['input'];
        } elseif ($type === 'output') {
            return $tokens * $rates['output'];
        } else {
            return $tokens * $rates['output'];
        }
    }
}

/**
 * Clase para manejo de esquemas
 */
class DatabaseSchema
{
    private $schemas = [];

    public function __construct()
    {
        // Esquema por defecto: Tienda online
        $this->schemas['tienda'] = [
            'name' => 'Tienda Online',
            'tables' => [
                'usuarios(id:int, nombre:varchar, email:varchar, fecha_registro:date)',
                'productos(id:int, nombre:varchar, precio:decimal, stock:int)',
                'pedidos(id:int, usuario_id:int, fecha:datetime, total:decimal)',
                'detalles_pedido(id:int, pedido_id:int, producto_id:int, cantidad:int, precio_unitario:decimal)'
            ]
        ];

        // Esquema 2: Biblioteca
        $this->schemas['biblioteca'] = [
            'name' => 'Biblioteca',
            'tables' => [
                'libros(id:int, titulo:varchar, autor_id:int, genero:varchar, año_publicacion:int)',
                'autores(id:int, nombre:varchar, nacionalidad:varchar)',
                'prestamos(id:int, libro_id:int, usuario_id:int, fecha_prestamo:date, fecha_devolucion:date)',
                'usuarios(id:int, nombre:varchar, email:varchar, telefono:varchar)'
            ]
        ];

        // Esquema 3: RRHH
        $this->schemas['rrhh'] = [
            'name' => 'Recursos Humanos',
            'tables' => [
                'empleados(id:int, nombre:varchar, departamento_id:int, salario:decimal, fecha_contratacion:date)',
                'departamentos(id:int, nombre:varchar, presupuesto:decimal)',
                'proyectos(id:int, nombre:varchar, presupuesto:decimal, fecha_inicio:date)',
                'asignaciones(id:int, empleado_id:int, proyecto_id:int, horas:decimal)'
            ]
        ];
    }

    public function getSchema($name = 'tienda')
    {
        return $this->schemas[$name] ?? $this->schemas['tienda'];
    }

    public function getAllSchemas()
    {
        return $this->schemas;
    }

    public function formatSchemaText($schema)
    {
        return implode(' ', $schema['tables']);
    }
}

// Procesar solicitud
$response = null;
$sql_result = null;
$error = null;
$selected_schema = 'tienda';
$question = '';

$schemaManager = new DatabaseSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question'])) {
    $question = trim($_POST['question']);
    $selected_schema = $_POST['schema'] ?? 'tienda';
    $temperature = floatval($_POST['temperature'] ?? 0.2);
    $model = $_POST['model'] ?? DeepSeekConfig::MODEL_V4;

    if (!empty($question) && DeepSeekConfig::API_KEY !== 'TU_API_KEY_DEEPSEEK_AQUI') {
        $generator = new DeepSeekSQLGenerator(DeepSeekConfig::API_KEY, $model);

        $schema = $schemaManager->getSchema($selected_schema);
        $schemaText = $schemaManager->formatSchemaText($schema);

        $options = [
            'temperature' => $temperature,
            'max_tokens' => 500
        ];

        $sql_result = $generator->generateSQL($question, $schemaText, $options);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DeepSeek SQL Generator - Web Interface</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 3em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .api-key-warning {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            color: #856404;
            padding: 20px;
            margin: 20px;
            border-radius: 10px;
        }

        .api-key-warning h3 {
            margin-bottom: 10px;
        }

        .api-key-warning ol {
            margin-left: 20px;
            margin-top: 10px;
        }

        .api-key-warning code {
            background: #ffe8b5;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            margin: 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
        }

        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .form-section {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #2a5298;
        }

        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .config-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        .config-item label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }

        .config-item select,
        .config-item input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .config-item input[type="range"] {
            padding: 0;
        }

        .value-display {
            display: inline-block;
            background: #2a5298;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .schema-info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #2a5298;
        }

        .schema-info h4 {
            color: #2a5298;
            margin-bottom: 10px;
        }

        .schema-tables {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-tag {
            background: white;
            border: 1px solid #2a5298;
            color: #2a5298;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-family: monospace;
        }

        button {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(42, 82, 152, 0.3);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 30px;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2a5298;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .result-section {
            padding: 30px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }

        .result-section h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .sql-result {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            line-height: 1.5;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            border-left: 5px solid #4caf50;
        }

        .sql-result.error {
            border-left-color: #f44336;
            color: #ff8a80;
        }

        .cost-info {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .cost-item {
            flex: 1;
            min-width: 120px;
        }

        .cost-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .cost-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #2a5298;
        }

        .examples-section {
            padding: 20px 30px 30px;
        }

        .examples-section h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .example-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .example-btn {
            background: white;
            border: 2px solid #2a5298;
            color: #2a5298;
            padding: 8px 15px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }

        .example-btn:hover {
            background: #2a5298;
            color: white;
        }

        .copy-btn {
            background: white;
            border: 2px solid #28a745;
            color: #28a745;
            padding: 8px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9em;
            margin-top: 10px;
            transition: all 0.3s;
        }

        .copy-btn:hover {
            background: #28a745;
            color: white;
        }

        footer {
            text-align: center;
            color: white;
            margin-top: 20px;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2em;
            }

            .config-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🤖 DeepSeek SQL Generator</h1>
            <p>Convierte lenguaje natural en consultas SQL optimizadas</p>
        </div>

        <div class="main-card">
            <?php if (DeepSeekConfig::API_KEY === 'TU_API_KEY_DEEPSEEK_AQUI'): ?>
                <div class="api-key-warning">
                    <h3>⚠️ Configuración requerida</h3>
                    <p>Para usar esta aplicación, necesitas configurar tu API key de DeepSeek:</p>
                    <ol>
                        <li>Regístrate en <a href="https://platform.deepseek.com/" target="_blank">DeepSeek Platform</a>
                        </li>
                        <li>Ve a "API Keys" y crea una nueva key</li>
                        <li>Edita el archivo PHP y reemplaza <code>'TU_API_KEY_DEEPSEEK_AQUI'</code> en la línea 9</li>
                    </ol>
                </div>
            <?php endif; ?>

            <?php if (DeepSeekConfig::API_KEY !== 'TU_API_KEY_DEEPSEEK_AQUI'): ?>
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-value">$0.40</div>
                        <div class="stat-label">/M tokens entrada</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">$1.60</div>
                        <div class="stat-label">/M tokens salida</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">⚡</div>
                        <div class="stat-label">Costo por consulta: &lt;$0.0002</div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-section">
                <form id="sqlForm" method="POST" onsubmit="showLoading()">
                    <div class="form-group">
                        <label for="question">📝 Describe lo que necesitas en lenguaje natural:</label>
                        <textarea id="question" name="question"
                            placeholder="Ej: 'Lista todos los usuarios que compraron más de 5 productos en el último mes'"
                            required><?php echo htmlspecialchars($question); ?></textarea>
                    </div>

                    <div class="config-grid">
                        <div class="config-item">
                            <label>📊 Esquema de base de datos:</label>
                            <select name="schema" id="schema" onchange="updateSchemaInfo()">
                                <?php foreach ($schemaManager->getAllSchemas() as $key => $schema): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $selected_schema === $key ? 'selected' : ''; ?>>
                                        <?php echo $schema['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="config-item">
                            <label>🤖 Modelo DeepSeek:</label>
                            <select name="model">
                                <option value="<?php echo DeepSeekConfig::MODEL_V4; ?>" <?php echo (!isset($_POST['model']) || $_POST['model'] === DeepSeekConfig::MODEL_V4) ? 'selected' : ''; ?>>
                                    DeepSeek V4 (Recomendado)
                                </option>
                                <option value="<?php echo DeepSeekConfig::MODEL_LITE; ?>" <?php echo (isset($_POST['model']) && $_POST['model'] === DeepSeekConfig::MODEL_LITE) ? 'selected' : ''; ?>>
                                    DeepSeek Lite (Económico)
                                </option>
                                <option value="<?php echo DeepSeekConfig::MODEL_CODER; ?>" <?php echo (isset($_POST['model']) && $_POST['model'] === DeepSeekConfig::MODEL_CODER) ? 'selected' : ''; ?>>
                                    DeepSeek Coder (Especializado)
                                </option>
                            </select>
                        </div>

                        <div class="config-item">
                            <label>🌡️ Temperatura: <span id="tempValue"
                                    class="value-display"><?php echo $_POST['temperature'] ?? 0.2; ?></span></label>
                            <input type="range" name="temperature" min="0" max="1" step="0.1"
                                value="<?php echo $_POST['temperature'] ?? 0.2; ?>" oninput="updateTemp(this.value)">
                            <small>0.2 = preciso, 0.8 = creativo</small>
                        </div>
                    </div>

                    <div class="schema-info" id="schemaInfo">
                        <?php
                        $currentSchema = $schemaManager->getSchema($selected_schema);
                        ?>
                        <h4>📋 Tablas disponibles en <?php echo $currentSchema['name']; ?>:</h4>
                        <div class="schema-tables">
                            <?php foreach ($currentSchema['tables'] as $table): ?>
                                <span class="table-tag"><?php echo htmlspecialchars($table); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" <?php echo DeepSeekConfig::API_KEY === 'TU_API_KEY_DEEPSEEK_AQUI' ? 'disabled' : ''; ?>>
                        🔍 Generar SQL
                    </button>
                </form>

                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>DeepSeek está generando tu consulta SQL...</p>
                    <small>Esto tomará solo unos segundos</small>
                </div>

                <?php if ($sql_result !== null): ?>
                    <div class="result-section">
                        <h2>📌 Resultado:</h2>

                        <div class="sql-result <?php echo $sql_result['success'] ? '' : 'error'; ?>">
                            <?php if ($sql_result['success']): ?>
                                <?php echo htmlspecialchars($sql_result['sql']); ?>
                            <?php else: ?>
                                ❌ Error: <?php echo htmlspecialchars($sql_result['error']); ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($sql_result['success']): ?>
                            <button class="copy-btn" onclick="copySQL()">📋 Copiar SQL</button>

                            <div class="cost-info">
                                <div class="cost-item">
                                    <div class="cost-label">Tokens de entrada</div>
                                    <div class="cost-value"><?php echo number_format($sql_result['input_tokens']); ?></div>
                                </div>
                                <div class="cost-item">
                                    <div class="cost-label">Tokens de salida</div>
                                    <div class="cost-value"><?php echo number_format($sql_result['output_tokens']); ?></div>
                                </div>
                                <div class="cost-item">
                                    <div class="cost-label">Total tokens</div>
                                    <div class="cost-value"><?php echo number_format($sql_result['total_tokens']); ?></div>
                                </div>
                                <div class="cost-item">
                                    <div class="cost-label">Costo estimado</div>
                                    <div class="cost-value">$<?php
                                    $generator = new DeepSeekSQLGenerator(DeepSeekConfig::API_KEY);
                                    $totalCost = $generator->calculateCost($sql_result['total_tokens']);
                                    echo number_format($totalCost, 6);
                                    ?></div>
                                </div>
                                <div class="cost-item">
                                    <div class="cost-label">Modelo usado</div>
                                    <div class="cost-value"><?php echo $sql_result['model']; ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="examples-section">
                <h3>🎯 Preguntas de ejemplo:</h3>
                <div class="example-buttons">
                    <button class="example-btn" onclick="setExample('Lista todos los usuarios registrados en 2025')">
                        📅 Usuarios 2025
                    </button>
                    <button class="example-btn"
                        onclick="setExample('Muestra el total de ventas por producto con los 5 más vendidos')">
                        📊 Top 5 productos
                    </button>
                    <button class="example-btn" onclick="setExample('Clientes que han gastado más de 1000€ en total')">
                        💰 Clientes VIP
                    </button>
                    <button class="example-btn"
                        onclick="setExample('Pedidos del último mes con sus detalles y totales')">
                        📦 Pedidos recientes
                    </button>
                    <button class="example-btn" onclick="setExample('Productos con stock inferior a 10 unidades')">
                        ⚠️ Stock bajo
                    </button>
                    <button class="example-btn"
                        onclick="setExample('Empleados del departamento de ventas con salario > 30000')">
                        👥 Empleados ventas
                    </button>
                </div>
            </div>
        </div>

        <footer>
            <p>DeepSeek SQL Generator - Costo aproximado por consulta: $0.0001 - $0.0003</p>
        </footer>
    </div>

    <script>
        function updateTemp(value) {
            document.getElementById('tempValue').textContent = parseFloat(value).toFixed(1);
        }

        function showLoading() {
            const question = document.getElementById('question').value.trim();
            if (question) {
                document.getElementById('loading').classList.add('active');
            }
        }

        function setExample(text) {
            document.getElementById('question').value = text;
            document.getElementById('question').focus();
        }

        function copySQL() {
            const sqlElement = document.querySelector('.sql-result');
            if (sqlElement) {
                const text = sqlElement.textContent;
                navigator.clipboard.writeText(text).then(() => {
                    alert('✅ SQL copiado al portapapeles');
                });
            }
        }

        function updateSchemaInfo() {
            const schema = document.getElementById('schema').value;
            // Aquí podrías hacer una petición AJAX para actualizar la info del esquema
            // Por simplicidad, recargamos la página
            document.getElementById('sqlForm').submit();
        }

        // Ocultar loading si hay resultado
        window.onload = function () {
            <?php if ($sql_result !== null): ?>
                document.getElementById('loading').classList.remove('active');
            <?php endif; ?>
        }
    </script>
</body>

</html>