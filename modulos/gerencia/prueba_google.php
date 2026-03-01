<?php
/**
 * Script de prueba para Google Gemini API - Modelo 2.0 Flash
 * 
 * Este script permite enviar preguntas a Gemini y obtener respuestas
 */

// Configuración
class GeminiConfig
{
    // Reemplaza con tu API key de Google AI Studio
    const API_KEY = 'AIzaSyDuH_tIYexDItYyDswnoYf6hG2BFVsHg6Q';
    const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';
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
     * 
     * @param string $question La pregunta a realizar
     * @param array $config Configuración adicional (temperatura, maxTokens, etc)
     * @return array Respuesta de la API
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

        // Preparar el payload para la API
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

        // Procesar la respuesta
        if ($error) {
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $error,
                'http_code' => $httpCode
            ];
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => $result['error']['message'] ?? 'Error desconocido',
                'http_code' => $httpCode,
                'details' => $result
            ];
        }

        // Extraer el texto de la respuesta
        $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'success' => true,
            'answer' => $answer,
            'full_response' => $result
        ];
    }

    /**
     * Procesa y formatea la respuesta para mostrar
     */
    public function formatResponse($response)
    {
        if (!$response['success']) {
            return "❌ Error: " . $response['error'];
        }

        $output = "✅ Respuesta generada exitosamente:\n\n";
        $output .= "📝 " . $response['answer'] . "\n\n";
        $output .= "---\n";
        $output .= "📊 Estadísticas:\n";

        if (isset($response['full_response']['usageMetadata'])) {
            $usage = $response['full_response']['usageMetadata'];
            $output .= "• Tokens de entrada: " . ($usage['promptTokenCount'] ?? 'N/A') . "\n";
            $output .= "• Tokens de salida: " . ($usage['candidatesTokenCount'] ?? 'N/A') . "\n";
            $output .= "• Tokens totales: " . ($usage['totalTokenCount'] ?? 'N/A') . "\n";
        }

        return $output;
    }
}

/**
 * Función para mostrar el menú de ayuda
 */
function showHelp()
{
    echo "\n🔧 COMANDOS DISPONIBLES:\n";
    echo "------------------------\n";
    echo "/exit - Salir del programa\n";
    echo "/clear - Limpiar pantalla\n";
    echo "/config - Ver configuración actual\n";
    echo "/help - Mostrar esta ayuda\n";
    echo "/temperature [valor] - Cambiar temperatura (0.0-1.0)\n";
    echo "/tokens [valor] - Cambiar máximo de tokens\n";
    echo "------------------------\n\n";
}

/**
 * Función para limpiar pantalla
 */
function clearScreen()
{
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        system('cls');
    } else {
        system('clear');
    }
}

// Función principal
function main()
{
    clearScreen();

    echo "========================================\n";
    echo "   PRUEBA DE GEMINI 2.0 FLASH API\n";
    echo "========================================\n\n";

    // Verificar si se configuró la API key
    if (GeminiConfig::API_KEY === 'TU_API_KEY_AQUI') {
        echo "⚠️  IMPORTANTE: Debes configurar tu API key\n";
        echo "1. Obtén tu API key en: https://makersuite.google.com/app/apikey\n";
        echo "2. Edita el archivo y reemplaza 'TU_API_KEY_AQUI' en la línea 11\n\n";

        echo "¿Quieres continuar con pruebas limitadas? (s/n): ";
        $handle = fopen("php://stdin", "r");
        $continue = trim(fgets($handle));

        if (strtolower($continue) !== 's') {
            echo "Saliendo...\n";
            exit(0);
        }
    }

    // Inicializar el cliente Gemini
    $gemini = new GeminiTest(GeminiConfig::API_KEY, GeminiConfig::API_URL);

    // Configuración por defecto
    $temperature = 0.7;
    $maxTokens = 800;

    echo "✅ Cliente inicializado\n";
    echo "ℹ️  Temperatura: $temperature | Máx tokens: $maxTokens\n";
    echo "ℹ️  Escribe '/help' para ver comandos disponibles\n\n";

    // Bucle principal
    while (true) {
        echo "🤔 Tu pregunta: ";
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));

        // Procesar comandos
        if (strpos($input, '/') === 0) {
            $parts = explode(' ', $input);
            $command = $parts[0];

            switch ($command) {
                case '/exit':
                    echo "👋 ¡Hasta luego!\n";
                    exit(0);

                case '/clear':
                    clearScreen();
                    echo "🔄 Pantalla limpiada\n\n";
                    continue 2;

                case '/help':
                    showHelp();
                    continue 2;

                case '/config':
                    echo "\n⚙️  CONFIGURACIÓN ACTUAL:\n";
                    echo "• Temperatura: $temperature\n";
                    echo "• Máx tokens: $maxTokens\n";
                    echo "• API Key: " . substr(GeminiConfig::API_KEY, 0, 5) . "..." . substr(GeminiConfig::API_KEY, -5) . "\n\n";
                    continue 2;

                case '/temperature':
                    if (isset($parts[1]) && is_numeric($parts[1])) {
                        $newTemp = floatval($parts[1]);
                        if ($newTemp >= 0 && $newTemp <= 1) {
                            $temperature = $newTemp;
                            echo "✅ Temperatura actualizada a: $temperature\n\n";
                        } else {
                            echo "❌ La temperatura debe estar entre 0 y 1\n\n";
                        }
                    } else {
                        echo "❌ Uso: /temperature [valor 0-1]\n\n";
                    }
                    continue 2;

                case '/tokens':
                    if (isset($parts[1]) && is_numeric($parts[1])) {
                        $newTokens = intval($parts[1]);
                        if ($newTokens > 0 && $newTokens <= 2048) {
                            $maxTokens = $newTokens;
                            echo "✅ Máximo de tokens actualizado a: $maxTokens\n\n";
                        } else {
                            echo "❌ El máximo de tokens debe estar entre 1 y 2048\n\n";
                        }
                    } else {
                        echo "❌ Uso: /tokens [valor 1-2048]\n\n";
                    }
                    continue 2;

                default:
                    echo "❌ Comando no reconocido. Escribe '/help' para ayuda\n\n";
                    continue 2;
            }
        }

        // Validar entrada
        if (empty($input)) {
            echo "⚠️  Por favor, ingresa una pregunta válida\n\n";
            continue;
        }

        echo "\n⏳ Enviando pregunta a Gemini 2.0 Flash...\n";

        // Configurar parámetros para esta pregunta
        $config = [
            'temperature' => $temperature,
            'maxOutputTokens' => $maxTokens
        ];

        // Enviar pregunta
        $response = $gemini->askQuestion($input, $config);

        // Mostrar resultado
        echo $gemini->formatResponse($response);

        echo "\n" . str_repeat("-", 50) . "\n\n";
    }
}

// Ejecutar el programa
if (PHP_SAPI === 'cli') {
    main();
} else {
    echo "Este script debe ejecutarse desde la línea de comandos:\n";
    echo "php " . basename(__FILE__) . "\n";
}
?>