<?php
/**
 * Página web de prueba para Google Gemini API - Modelo 2.0 Flash
 * 
 * Este script crea una interfaz web para interactuar con Gemini
 */

// Configuración

class GeminiConfig
{
    // Reemplaza con tu API key de Google AI Studio
    const API_KEY = 'AIzaSyDuH_tIYexDItYyDswnoYf6hG2BFVsHg6Q';
    const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
}

/**
 * Clase para interactuar con Gemini API
 */
class GeminiTest
{
    private $apiKey;
    private $apiUrl;

    public function __construct($apiKey, $apiUrl)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
    }

    /**
     * Envía una pregunta a Gemini y obtiene la respuesta
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
        $url = $this->apiUrl . '?key=' . $this->apiKey;

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
            return [
                'success' => false,
                'error' => $result['error']['message'] ?? 'Error desconocido',
                'details' => $result
            ];
        }

        // Extraer la respuesta
        $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'success' => true,
            'answer' => $answer,
            'usage' => $result['usageMetadata'] ?? []
        ];
    }
}

// Procesar el formulario
$response = null;
$question = '';
$temperature = 0.7;
$maxTokens = 800;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question'])) {
    $question = trim($_POST['question']);
    $temperature = floatval($_POST['temperature'] ?? 0.7);
    $maxTokens = intval($_POST['max_tokens'] ?? 800);

    if (!empty($question)) {
        $gemini = new GeminiTest(GeminiConfig::API_KEY, GeminiConfig::API_URL);

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
    <title>Prueba Gemini 2.0 Flash</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            padding: 15px;
            margin: 20px;
            border-radius: 10px;
            font-size: 0.95em;
        }

        .api-key-warning strong {
            display: block;
            margin-bottom: 10px;
            font-size: 1.1em;
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

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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

        .config-item .value-display {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        .response-container {
            margin-top: 30px;
            padding: 30px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }

        .response-container h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .response-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            line-height: 1.6;
        }

        .response-box.success {
            border-left: 5px solid #28a745;
        }

        .response-box.error {
            border-left: 5px solid #dc3545;
        }

        .response-box .answer {
            font-size: 1.1em;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .response-box .answer p {
            margin-bottom: 15px;
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

        .stat-item {
            flex: 1;
            min-width: 120px;
        }

        .stat-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
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
            border-top: 4px solid #667eea;
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
            padding: 20px;
            background: #f0f0f0;
            border-radius: 10px;
        }

        .examples h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .example-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .example-btn {
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }

        .example-btn:hover {
            background: #667eea;
            color: white;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: white;
            font-size: 0.9em;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Gemini 2.0 Flash</h1>
            <p>Prueba el nuevo modelo de Google directamente desde tu navegador</p>
        </div>

        <?php if (GeminiConfig::API_KEY === 'TU_API_KEY_AQUI'): ?>
            <div class="api-key-warning">
                <strong>⚠️ Configuración requerida</strong>
                <p>Para usar esta aplicación, necesitas configurar tu API key de Google AI Studio:</p>
                <ol>
                    <li>Obtén tu API key en: <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI
                            Studio</a></li>
                    <li>Edita el archivo PHP y reemplaza 'TU_API_KEY_AQUI' en la línea 9</li>
                </ol>
                <p><small>Sin la API key, las consultas no funcionarán correctamente.</small></p>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form id="geminiForm" method="POST" onsubmit="showLoading()">
                <div class="form-group">
                    <label for="question">Tu pregunta:</label>
                    <textarea id="question" name="question" placeholder="Escribe tu pregunta aquí..."
                        required><?php echo htmlspecialchars($question); ?></textarea>
                </div>

                <div class="config-grid">
                    <div class="config-item">
                        <label>🌡️ Temperatura (creatividad) <span id="tempValue"
                                class="value-display"><?php echo $temperature; ?></span></label>
                        <input type="range" id="temperature" name="temperature" min="0" max="1" step="0.1"
                            value="<?php echo $temperature; ?>" oninput="updateTempValue(this.value)">
                        <small>0 = preciso, 1 = creativo</small>
                    </div>

                    <div class="config-item">
                        <label>📝 Máx. tokens <span id="tokensValue"
                                class="value-display"><?php echo $maxTokens; ?></span></label>
                        <input type="range" id="max_tokens" name="max_tokens" min="100" max="2048" step="50"
                            value="<?php echo $maxTokens; ?>" oninput="updateTokensValue(this.value)">
                        <small>Longitud máxima de respuesta</small>
                    </div>
                </div>

                <button type="submit">Enviar pregunta</button>
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
                            <div class="answer">
                                <?php echo nl2br(htmlspecialchars($response['answer'])); ?>
                            </div>

                            <?php if (!empty($response['usage'])): ?>
                                <div class="stats">
                                    <div class="stat-item">
                                        <div class="stat-label">Tokens de entrada</div>
                                        <div class="stat-value"><?php echo $response['usage']['promptTokenCount'] ?? 0; ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-label">Tokens de salida</div>
                                        <div class="stat-value"><?php echo $response['usage']['candidatesTokenCount'] ?? 0; ?></div>
                                    </div>
                                    <div class="stat-item">
                                        <div class="stat-label">Tokens totales</div>
                                        <div class="stat-value"><?php echo $response['usage']['totalTokenCount'] ?? 0; ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="answer" style="color: #dc3545;">
                                <strong>Error:</strong> <?php echo htmlspecialchars($response['error']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="examples">
                <h3>📝 Preguntas de ejemplo:</h3>
                <div class="example-buttons">
                    <button type="button" class="example-btn"
                        onclick="setExampleQuestion('¿Qué es la inteligencia artificial y cómo funciona?')">
                        🤖 ¿Qué es la IA?
                    </button>
                    <button type="button" class="example-btn"
                        onclick="setExampleQuestion('Explica la teoría de la relatividad de forma sencilla')">
                        ⚛️ Teoría de la relatividad
                    </button>
                    <button type="button" class="example-btn"
                        onclick="setExampleQuestion('¿Cuáles son los beneficios de aprender a programar?')">
                        💻 Beneficios de programar
                    </button>
                    <button type="button" class="example-btn"
                        onclick="setExampleQuestion('Dame 5 ideas para emprender un negocio online')">
                        💡 Ideas de negocio
                    </button>
                    <button type="button" class="example-btn"
                        onclick="setExampleQuestion('¿Cómo funciona el machine learning? Explicación para niños')">
                        🧠 Machine Learning
                    </button>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>Prueba de Gemini 2.0 Flash - Modelo de Google</p>
    </footer>

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

        function setExampleQuestion(question) {
            document.getElementById('question').value = question;
            document.getElementById('question').focus();
        }

        // Mostrar loading si hay una respuesta (para evitar doble envío)
        window.onload = function () {
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                document.getElementById('loading').classList.remove('active');
            <?php endif; ?>
        }

        // Prevenir envío doble del formulario
        document.getElementById('geminiForm').addEventListener('submit', function (e) {
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            setTimeout(() => {
                submitButton.disabled = false;
            }, 30000); // Rehabilitar después de 30 segundos
        });
    </script>
</body>

</html>