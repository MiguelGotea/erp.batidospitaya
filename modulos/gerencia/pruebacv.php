<?php
/**
 * ANALIZADOR DE CV CON GEMINI API - Versión súper sencilla
 * Sube un PDF, escribe el perfil del puesto y obtén % de compatibilidad
 */

// Configuración - ¡REEMPLAZA CON TU API KEY!
class GeminiConfig
{
    const API_KEY = 'TU_API_KEY_GOOGLE_AQUI';
    const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent';

    // Límite de PDF: 50MB para inline data [citation:1]
    const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB en bytes
}

// Procesar el formulario cuando se envía
$resultado = null;
$error = null;
$perfil_puesto = '';
$nombre_archivo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cv_pdf']) && isset($_POST['perfil'])) {
    $perfil_puesto = trim($_POST['perfil']);
    $archivo = $_FILES['cv_pdf'];

    // Validaciones básicas
    if (empty($perfil_puesto)) {
        $error = "Por favor, escribe el perfil del puesto";
    } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
        $error = "Error al subir el archivo. Código: " . $archivo['error'];
    } elseif ($archivo['size'] > GeminiConfig::MAX_FILE_SIZE) {
        $error = "El archivo es demasiado grande (máx 50MB)";
    } elseif ($archivo['type'] !== 'application/pdf') {
        $error = "Solo se permiten archivos PDF";
    } else {
        // Leer el PDF y convertirlo a base64 [citation:1]
        $pdf_content = file_get_contents($archivo['tmp_name']);
        $base64_pdf = base64_encode($pdf_content);
        $nombre_archivo = $archivo['name'];

        // Construir el prompt para Gemini
        $prompt = construirPromptAnalisis($perfil_puesto);

        // Llamar a la API de Gemini
        $resultado = llamarGeminiAPI($base64_pdf, $prompt);
    }
}

/**
 * Construye el prompt especializado para análisis de CV [citation:3][citation:7]
 */
function construirPromptAnalisis($perfil)
{
    return "Eres un reclutador experto con 15 años de experiencia en selección de personal.
    
    Tu tarea: Analizar el CV adjunto y compararlo con el siguiente perfil de puesto:
    
    PERFIL DEL PUESTO:
    \"\"\"$perfil\"\"\"
    
    IMPORTANTE: Evalúa el CV punto por punto y genera:
    
    1. PUNTAJE TOTAL (0-100): Un número entero que representa el porcentaje de compatibilidad
    2. FORTALEZAS: Lista de 3-5 habilidades o experiencias que coinciden perfectamente
    3. BRECHAS: Lista de 2-4 habilidades o requisitos que faltan o son débiles
    4. RECOMENDACIÓN: Contratar, Entrevistar, o Descartar
    5. EXPLICACIÓN BREVE: 2-3 frases justificando el puntaje
    
    FORMATO DE RESPUESTA (DEBE SER EXACTAMENTE ASÍ):
    
    PUNTAJE: [número]
    
    FORTALEZAS:
    • [fortaleza 1]
    • [fortaleza 2]
    • [fortaleza 3]
    
    BRECHAS:
    • [brecha 1]
    • [brecha 2]
    
    RECOMENDACIÓN: [Contratar/Entrevistar/Descartar]
    
    EXPLICACIÓN: [texto breve]
    
    Sé objetivo y basado en evidencia del CV.";
}

/**
 * Llama a Gemini API con el PDF y el prompt [citation:1]
 */
function llamarGeminiAPI($base64_pdf, $prompt)
{
    $url = GeminiConfig::API_URL . '?key=' . GeminiConfig::API_KEY;

    // Preparar el payload según documentación oficial [citation:1]
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => 'application/pdf',
                            'data' => $base64_pdf
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2, // Bajo para respuestas más precisas [citation:3]
            'maxOutputTokens' => 1024,
            'topP' => 0.8
        ]
    ];

    // Configurar cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Timeout de 60 segundos

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => 'Error de conexión: ' . $error];
    }

    $result = json_decode($response, true);

    if ($httpCode !== 200) {
        $errorMsg = $result['error']['message'] ?? 'Error desconocido';
        return ['success' => false, 'error' => $errorMsg];
    }

    // Extraer el texto de la respuesta
    $texto_respuesta = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // Parsear la respuesta para mostrar bonito
    return [
        'success' => true,
        'raw_response' => $texto_respuesta,
        'parsed' => parsearRespuesta($texto_respuesta)
    ];
}

/**
 * Parsea la respuesta de Gemini para extraer los campos estructurados
 */
function parsearRespuesta($texto)
{
    $resultado = [
        'puntaje' => 'N/A',
        'fortalezas' => [],
        'brechas' => [],
        'recomendacion' => 'N/A',
        'explicacion' => 'N/A'
    ];

    // Extraer puntaje (buscamos un número entre 0-100)
    if (preg_match('/PUNTAJE:\s*(\d+)/i', $texto, $matches)) {
        $resultado['puntaje'] = intval($matches[1]);
    }

    // Extraer fortalezas
    if (preg_match('/FORTALEZAS:(.*?)(?=BRECHAS:|RECOMENDACIÓN:|$)/is', $texto, $matches)) {
        $lineas = explode("\n", trim($matches[1]));
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (!empty($linea) && (strpos($linea, '•') === 0 || strpos($linea, '-') === 0)) {
                $resultado['fortalezas'][] = trim(ltrim($linea, '•- '));
            }
        }
    }

    // Extraer brechas
    if (preg_match('/BRECHAS:(.*?)(?=RECOMENDACIÓN:|EXPLICACIÓN:|$)/is', $texto, $matches)) {
        $lineas = explode("\n", trim($matches[1]));
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (!empty($linea) && (strpos($linea, '•') === 0 || strpos($linea, '-') === 0)) {
                $resultado['brechas'][] = trim(ltrim($linea, '•- '));
            }
        }
    }

    // Extraer recomendación
    if (preg_match('/RECOMENDACIÓN:\s*([^\n]+)/i', $texto, $matches)) {
        $resultado['recomendacion'] = trim($matches[1]);
    }

    // Extraer explicación
    if (preg_match('/EXPLICACIÓN:\s*([^\n]+(?:\n[^\n]+)*)/i', $texto, $matches)) {
        $resultado['explicacion'] = trim($matches[1]);
    }

    return $resultado;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analizador de CV con Gemini</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            padding: 30px;
            margin-bottom: 20px;
        }

        .api-warning {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.95em;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }

        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            min-height: 120px;
            resize: vertical;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .file-input {
            position: relative;
            width: 100%;
        }

        .file-input input[type="file"] {
            position: absolute;
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            z-index: -1;
        }

        .file-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px;
            background: #f8f9fa;
            border: 2px dashed #667eea;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            color: #667eea;
            transition: all 0.3s;
        }

        .file-label:hover {
            background: #e9ecef;
            border-color: #764ba2;
        }

        .file-label span {
            font-weight: 600;
        }

        .file-name {
            margin-top: 10px;
            padding: 8px;
            background: #e9ecef;
            border-radius: 8px;
            font-size: 0.95em;
            color: #333;
        }

        button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.2em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
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
            border-top: 4px solid #667eea;
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

        .result-box {
            background: white;
            border-radius: 16px;
            padding: 25px;
        }

        .score-circle {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5em;
            font-weight: bold;
            color: white;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .recommendation-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .recommendation-badge.contratar {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .recommendation-badge.entrevistar {
            background: #fff3cd;
            color: #856404;
            border-left: 5px solid #ffc107;
        }

        .recommendation-badge.descartar {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        .section {
            margin-bottom: 20px;
        }

        .section h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.2em;
        }

        .list-item {
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        .explicacion {
            background: #e9ecef;
            padding: 15px;
            border-radius: 10px;
            font-style: italic;
            line-height: 1.5;
        }

        .error-box {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
            padding: 15px;
            border-radius: 10px;
            color: #721c24;
        }

        .footer {
            text-align: center;
            color: white;
            margin-top: 20px;
            font-size: 0.9em;
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📄 Analizador de CV con Gemini</h1>
            <p>Sube un CV en PDF, escribe el perfil del puesto y obtén el % de compatibilidad</p>
        </div>

        <div class="card">
            <?php if (GeminiConfig::API_KEY === 'TU_API_KEY_GOOGLE_AQUI'): ?>
                <div class="api-warning">
                    <strong>⚠️ Configuración pendiente:</strong> Reemplaza 'TU_API_KEY_GOOGLE_AQUI' con tu API key de Google
                    AI Studio.
                    <br><small>Obtén tu key en: <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI
                            Studio</a></small>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
                <div class="form-group">
                    <label>📋 Perfil del puesto:</label>
                    <textarea name="perfil"
                        placeholder="Ej: Buscamos un desarrollador PHP con 3 años de experiencia, conocimiento de Laravel, MySQL y APIs REST. Inglés intermedio y trabajo en equipo."
                        required><?php echo htmlspecialchars($perfil_puesto); ?></textarea>
                </div>

                <div class="form-group">
                    <label>📎 CV del candidato (PDF):</label>
                    <div class="file-input">
                        <input type="file" name="cv_pdf" id="cv_pdf" accept=".pdf" required
                            onchange="updateFileName(this)">
                        <label for="cv_pdf" class="file-label">
                            <span>📁</span> Seleccionar archivo PDF
                        </label>
                    </div>
                    <div class="file-name" id="file-name">Ningún archivo seleccionado</div>
                </div>

                <button type="submit" id="submitBtn" <?php echo GeminiConfig::API_KEY === 'TU_API_KEY_GOOGLE_AQUI' ? 'disabled' : ''; ?>>
                    🔍 Analizar compatibilidad
                </button>
            </form>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Gemini está analizando el CV... (esto toma unos segundos)</p>
            </div>

            <?php if ($error): ?>
                <div style="margin-top: 25px;">
                    <div class="error-box">
                        <strong>❌ Error:</strong>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($resultado && $resultado['success']): ?>
                <div style="margin-top: 30px;">
                    <h2 style="margin-bottom: 20px; color: #333;">📊 Resultado del análisis</h2>

                    <div class="result-box">
                        <div class="score-circle">
                            <?php echo $resultado['parsed']['puntaje']; ?>%
                        </div>

                        <?php
                        $recomendacion = strtolower($resultado['parsed']['recomendacion']);
                        $badge_class = 'entrevistar';
                        if (strpos($recomendacion, 'contratar') !== false)
                            $badge_class = 'contratar';
                        if (strpos($recomendacion, 'descartar') !== false)
                            $badge_class = 'descartar';
                        ?>

                        <div class="recommendation-badge <?php echo $badge_class; ?>">
                            <strong>Recomendación:</strong>
                            <?php echo htmlspecialchars($resultado['parsed']['recomendacion']); ?>
                        </div>

                        <div class="section">
                            <h3>✅ Fortalezas detectadas:</h3>
                            <?php if (!empty($resultado['parsed']['fortalezas'])): ?>
                                <?php foreach ($resultado['parsed']['fortalezas'] as $fortaleza): ?>
                                    <div class="list-item">•
                                        <?php echo htmlspecialchars($fortaleza); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="list-item">No se detectaron fortalezas específicas</div>
                            <?php endif; ?>
                        </div>

                        <div class="section">
                            <h3>⚠️ Áreas de mejora / Brechas:</h3>
                            <?php if (!empty($resultado['parsed']['brechas'])): ?>
                                <?php foreach ($resultado['parsed']['brechas'] as $brecha): ?>
                                    <div class="list-item">•
                                        <?php echo htmlspecialchars($brecha); ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="list-item">No se detectaron brechas significativas</div>
                            <?php endif; ?>
                        </div>

                        <div class="section">
                            <h3>💬 Explicación:</h3>
                            <div class="explicacion">
                                <?php echo nl2br(htmlspecialchars($resultado['parsed']['explicacion'])); ?>
                            </div>
                        </div>

                        <div style="margin-top: 20px; font-size: 0.9em; color: #666; text-align: center;">
                            <small>Análisis generado por Gemini 3 Flash •
                                <?php echo htmlspecialchars($nombre_archivo); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Basado en Google Gemini API - Procesamiento seguro, los archivos no se almacenan</p>
        </div>
    </div>

    <script>
        function updateFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : 'Ningún archivo seleccionado';
            document.getElementById('file-name').textContent = '📄 ' + fileName;
        }

        function showLoading() {
            const perfil = document.querySelector('textarea[name="perfil"]').value.trim();
            const archivo = document.getElementById('cv_pdf').files.length;

            if (perfil && archivo > 0) {
                document.getElementById('loading').classList.add('active');
                document.getElementById('submitBtn').disabled = true;
            }
        }
        
        <?php if ($resultado || $error): ?>
                window.onload = function() {
                    document.getElementById('loading').classList.remove('active');
                    document.getElementById('submitBtn').disabled = false;
                }
        <?php endif; ?>
    </script>
</body>

</html>