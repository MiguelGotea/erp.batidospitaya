<?php
/**
 * Página web de prueba para Google Gemini API - MODELOS ACTUALIZADOS 2026
 * 
 * Compatible con los modelos disponibles actualmente
 */

// Configuración
class GeminiConfig
{
    // Reemplaza con tu API key de Google AI Studio
    const API_KEY = 'AIzaSyDuH_tIYexDItYyDswnoYf6hG2BFVsHg6Q';

    // Opción 1: Gemini 2.5 Flash (recomendado - versión estable actual)
    const MODEL_STABLE = 'gemini-2.5-flash';

    // Opción 2: Gemini 2.5 Flash Preview (últimas características)
    const MODEL_PREVIEW = 'gemini-2.5-flash-preview-09-2025';

    // Opción 3: Gemini 3 Flash Preview (modelo más reciente)
    const MODEL_LATEST = 'gemini-3-flash-preview';

    // Modelo activo (cambia esta línea para usar diferentes modelos)
    const ACTIVE_MODEL = self::MODEL_STABLE;

    const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
}

/**
 * Clase para interactuar con Gemini API
 */
class GeminiTest
{
    private $apiKey;
    private $model;

    public function __construct($apiKey, $model)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * Obtiene la lista de modelos disponibles
     */
    public function listModels()
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        return null;
    }

    /**
     * Envía una pregunta a Gemini
     */
    public function askQuestion($question, $config = [])
    {
        // Configuración por defecto
        $defaultConfig = [
            'temperature' => 0.7,
            'maxOutputTokens' => 800,
            'topP' => 0.95,
            'topK' => 40
        ];

        $config = array_merge($defaultConfig, $config);

        // Construir URL con el modelo seleccionado
        $url = GeminiConfig::API_URL . $this->model . ':generateContent?key=' . $this->apiKey;

        // Preparar el payload
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $question]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $config['temperature'],
                'maxOutputTokens' => $config['maxOutputTokens'],
                'topP' => $config['topP'],
                'topK' => $config['topK']
            ]
        ];

        // Configurar la solicitud HTTP
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Ejecutar la solicitud
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

            // Mensaje más amigable para errores de modelo
            if (strpos($errorMsg, 'not found') !== false) {
                $errorMsg .= ' | Sugerencia: Prueba con gemini-2.5-flash o gemini-3-flash-preview';
            }

            return [
                'success' => false,
                'error' => $errorMsg,
                'http_code' => $httpCode
            ];
        }

        // Extraer la respuesta
        $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'success' => true,
            'answer' => $answer,
            'usage' => $result['usageMetadata'] ?? [],
            'model' => $this->model
        ];
    }
}

// Procesar el formulario
$response = null;
$question = '';
$temperature = 0.7;
$maxTokens = 800;
$selectedModel = GeminiConfig::ACTIVE_MODEL;
$modelsList = null;

// Crear instancia para listar modelos (solo si la API key está configurada)
if (GeminiConfig::API_KEY !== 'TU_API_KEY_AQUI') {
    $tempGemini = new GeminiTest(GeminiConfig::API_KEY, '');
    $modelsList = $tempGemini->listModels();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim($_POST['question'] ?? '');
    $temperature = floatval($_POST['temperature'] ?? 0.7);
    $maxTokens = intval($_POST['max_tokens'] ?? 800);
    $selectedModel = $_POST['model'] ?? GeminiConfig::ACTIVE_MODEL;

    if (!empty($question) && GeminiConfig::API_KEY !== 'TU_API_KEY_AQUI') {
        $gemini = new GeminiTest(GeminiConfig::API_KEY, $selectedModel);

        $config = [
            'temperature' => $temperature,
            'maxOutputTokens' => $maxTokens
        ];

        $response = $gemini->askQuestion($question, $config);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemini API - Modelos 2026</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a2a6c 0%, #b21f1f 50%, #fdbb2d 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1a2a6c 0%, #b21f1f 50%, #fdbb2d 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .api-key-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
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

        .models-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #0d47a1;
            padding: 20px;
            margin: 20px;
            border-radius: 10px;
        }

        .models-info h3 {
            margin-bottom: 10px;
        }

        .models-info ul {
            margin-left: 20px;
        }

        .model-tag {
            display: inline-block;
            background: #0d47a1;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            margin: 2px;
        }

        .form-container {
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
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            min-height: 100px;
            resize: vertical;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #1a2a6c;
        }

        .model-selector {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .model-option {
            flex: 1;
            min-width: 200px;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .model-option.selected {
            border-color: #1a2a6c;
            background: #e3f2fd;
        }

        .model-option input[type="radio"] {
            margin-right: 10px;
        }

        .model-name {
            font-weight: bold;
            color: #1a2a6c;
        }

        .model-desc {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
            color: #555;
        }

        .config-item input[type="range"] {
            width: 100%;
            margin: 10px 0;
        }

        .value-display {
            display: inline-block;
            background: #1a2a6c;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        button {
            background: linear-gradient(135deg, #1a2a6c 0%, #b21f1f 50%, #fdbb2d 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(26, 42, 108, 0.3);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .response-container {
            margin-top: 30px;
            padding: 30px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }

        .response-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .response-box.success {
            border-left: 5px solid #28a745;
        }

        .response-box.error {
            border-left: 5px solid #dc3545;
        }

        .model-badge {
            background: #1a2a6c;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 15px;
            font-size: 0.9em;
        }

        .answer {
            font-size: 1.1em;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .stats {
            margin-top: 20px;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 10px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #1a2a6c;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .examples {
            margin-top: 20px;
        }

        .example-btn {
            background: white;
            border: 2px solid #1a2a6c;
            color: #1a2a6c;
            padding: 8px 15px;
            border-radius: 20px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .example-btn:hover {
            background: #1a2a6c;
            color: white;
        }

        .models-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }

        .models-table th,
        .models-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .models-table tr:hover {
            background: #f5f5f5;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Gemini API 2026</h1>
            <p>Modelos actualizados: Gemini 2.5 Flash, Gemini 3 Flash Preview</p>
        </div>

        <?php if (GeminiConfig::API_KEY === 'TU_API_KEY_AQUI'): ?>
            <div class="api-key-warning">
                <h3>⚠️ Configuración requerida</h3>
                <p>Para usar esta aplicación, necesitas configurar tu API key:</p>
                <ol>
                    <li>Obtén tu API key en: <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI
                            Studio</a></li>
                    <li>Edita el archivo PHP y reemplaza 'TU_API_KEY_AQUI' en la línea 9</li>
                </ol>
            </div>
        <?php else: ?>

            <?php if ($modelsList && isset($modelsList['models'])): ?>
                <div class="models-info">
                    <h3>📋 Modelos disponibles en tu cuenta:</h3>
                    <table class="models-table">
                        <tr>
                            <th>Modelo</th>
                            <th>Versión</th>
                        </tr>
                        <?php foreach (array_slice($modelsList['models'], 0, 5) as $model): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($model['name']); ?></td>
                                <td><?php echo htmlspecialchars($model['version'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <p><small>Mostrando 5 de <?php echo count($modelsList['models']); ?> modelos</small></p>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <div class="models-info" style="background: #e8f5e8; border-color: #c3e6cb; color: #155724;">
                    <h3>✅ Modelos recomendados (2026):</h3>
                    <p><span class="model-tag">gemini-2.5-flash</span> - Versión estable actual (recomendada)</p>
                    <p><span class="model-tag">gemini-2.5-flash-preview-09-2025</span> - Preview con últimas características
                    </p>
                    <p><span class="model-tag">gemini-3-flash-preview</span> - Modelo más reciente (Gemini 3)</p>
                    <p><small>El modelo "gemini-2.0-flash-exp" ya no está disponible [citation:1]</small></p>
                </div>

                <form id="geminiForm" method="POST" onsubmit="showLoading()">
                    <div class="form-group">
                        <label for="question">Tu pregunta:</label>
                        <textarea id="question" name="question" placeholder="Escribe tu pregunta aquí..."
                            required><?php echo htmlspecialchars($question); ?></textarea>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="font-weight: 600; margin-bottom: 10px; display: block;">🤖 Selecciona modelo:</label>
                        <div class="model-selector">
                            <label
                                class="model-option <?php echo $selectedModel === GeminiConfig::MODEL_STABLE ? 'selected' : ''; ?>">
                                <input type="radio" name="model" value="<?php echo GeminiConfig::MODEL_STABLE; ?>" <?php echo $selectedModel === GeminiConfig::MODEL_STABLE ? 'checked' : ''; ?>>
                                <span class="model-name">Gemini 2.5 Flash</span>
                                <div class="model-desc">Versión estable, producción</div>
                            </label>
                            <label
                                class="model-option <?php echo $selectedModel === GeminiConfig::MODEL_PREVIEW ? 'selected' : ''; ?>">
                                <input type="radio" name="model" value="<?php echo GeminiConfig::MODEL_PREVIEW; ?>" <?php echo $selectedModel === GeminiConfig::MODEL_PREVIEW ? 'checked' : ''; ?>>
                                <span class="model-name">Gemini 2.5 Preview</span>
                                <div class="model-desc">Últimas características</div>
                            </label>
                            <label
                                class="model-option <?php echo $selectedModel === GeminiConfig::MODEL_LATEST ? 'selected' : ''; ?>">
                                <input type="radio" name="model" value="<?php echo GeminiConfig::MODEL_LATEST; ?>" <?php echo $selectedModel === GeminiConfig::MODEL_LATEST ? 'checked' : ''; ?>>
                                <span class="model-name">Gemini 3 Flash</span>
                                <div class="model-desc">Modelo más reciente</div>
                            </label>
                        </div>
                    </div>

                    <div class="config-grid">
                        <div class="config-item">
                            <label>🌡️ Temperatura <span id="tempValue"
                                    class="value-display"><?php echo $temperature; ?></span></label>
                            <input type="range" name="temperature" min="0" max="1" step="0.1"
                                value="<?php echo $temperature; ?>" oninput="updateTempValue(this.value)">
                            <small>0 = preciso, 1 = creativo</small>
                        </div>
                        <div class="config-item">
                            <label>📝 Máx. tokens <span id="tokensValue"
                                    class="value-display"><?php echo $maxTokens; ?></span></label>
                            <input type="range" name="max_tokens" min="100" max="2048" step="50"
                                value="<?php echo $maxTokens; ?>" oninput="updateTokensValue(this.value)">
                            <small>Longitud máxima</small>
                        </div>
                    </div>

                    <button type="submit" <?php echo GeminiConfig::API_KEY === 'TU_API_KEY_AQUI' ? 'disabled' : ''; ?>>
                        Enviar pregunta
                    </button>
                </form>

                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p>Generando respuesta...</p>
                </div>

                <?php if ($response !== null): ?>
                    <div class="response-container">
                        <h2>Respuesta:</h2>
                        <div class="response-box <?php echo $response['success'] ? 'success' : 'error'; ?>">
                            <?php if ($response['success']): ?>
                                <div class="model-badge">
                                    Modelo: <?php echo htmlspecialchars($response['model'] ?? 'N/A'); ?>
                                </div>
                                <div class="answer">
                                    <?php echo nl2br(htmlspecialchars($response['answer'])); ?>
                                </div>

                                <?php if (!empty($response['usage'])): ?>
                                    <div class="stats">
                                        <div class="stat-item">
                                            <strong>Tokens entrada:</strong> <?php echo $response['usage']['promptTokenCount'] ?? 0; ?>
                                        </div>
                                        <div class="stat-item">
                                            <strong>Tokens salida:</strong>
                                            <?php echo $response['usage']['candidatesTokenCount'] ?? 0; ?>
                                        </div>
                                        <div class="stat-item">
                                            <strong>Total:</strong> <?php echo $response['usage']['totalTokenCount'] ?? 0; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <div style="color: #dc3545;">
                                    <strong>Error:</strong> <?php echo htmlspecialchars($response['error']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="examples">
                    <h3>📝 Ejemplos rápidos:</h3>
                    <button type="button" class="example-btn" onclick="setQuestion('Explica qué es Gemini 2.5 Flash')">🤖
                        Gemini 2.5</button>
                    <button type="button" class="example-btn"
                        onclick="setQuestion('¿Cuáles son las novedades de Gemini 3?')">✨ Gemini 3</button>
                    <button type="button" class="example-btn"
                        onclick="setQuestion('Escribe un poema sobre la inteligencia artificial')">📜 Poema</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateTempValue(value) {
            document.getElementById('tempValue').textContent = parseFloat(value).toFixed(1);
        }

        function updateTokensValue(value) {
            document.getElementById('tokensValue').textContent = value;
        }

        function showLoading() {
            const question = document.getElementById('question').value.trim();
            if (question) {
                document.getElementById('loading').classList.add('active');
            }
        }

        function setQuestion(text) {
            document.getElementById('question').value = text;
        }

        // Marcar visualmente la opción seleccionada
        document.querySelectorAll('input[name="model"]').forEach(radio => {
            radio.addEventListener('change', function () {
                document.querySelectorAll('.model-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.closest('.model-option').classList.add('selected');
            });
        });
    </script>
</body>

</html>