<?php
/**
 * ANALIZADOR DE CV CON GEMINI API
 * Replicando rotación de API Keys usando AIService
 */
require_once '../../core/auth/auth.php';
require_once '../../core/database/conexion.php';
require_once '../../core/ai/AIService.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;


// Verificar acceso
if (!$usuario) {
    header('Location: /login.php');
    exit();
}

// Configuración de límites
define('MAX_PDF_SIZE', 50 * 1024 * 1024); // 50MB

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
    } elseif ($archivo['size'] > MAX_PDF_SIZE) {
        $error = "El archivo es demasiado grande (máx 50MB)";
    } elseif ($archivo['type'] !== 'application/pdf') {
        $error = "Solo se permiten archivos PDF";
    } else {
        try {
            // Leer el PDF y convertirlo a base64
            $pdf_content = file_get_contents($archivo['tmp_name']);
            $base64_pdf = base64_encode($pdf_content);
            $nombre_archivo = $archivo['name'];

            // Construir el prompt para Gemini
            $systemPrompt = "Eres un reclutador experto con 15 años de experiencia en selección de personal.";
            $prompt = construirPromptAnalisis($perfil_puesto);

            // Preparar partes extra para Gemini
            $extraParts = [
                [
                    'inline_data' => [
                        'mime_type' => 'application/pdf',
                        'data' => $base64_pdf
                    ]
                ]
            ];

            // Usar AIService con rotación de llaves (proveedor google)
            $aiService = new AIService($conn, 'google');
            $texto_respuesta = $aiService->procesarPrompt($systemPrompt, $prompt, 0.2, $extraParts);

            if (empty($texto_respuesta)) {
                throw new Exception("No se recibió respuesta de la IA.");
            }

            // Parsear la respuesta
            $resultado = [
                'success' => true,
                'raw_response' => $texto_respuesta,
                'parsed' => parsearRespuesta($texto_respuesta)
            ];

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

/**
 * Construye el prompt especializado para análisis de CV
 */
function construirPromptAnalisis($perfil)
{
    return "Tu tarea: Analizar el CV adjunto y compararlo con el siguiente perfil de puesto:
    
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

    if (preg_match('/PUNTAJE:\s*(\d+)/i', $texto, $matches)) {
        $resultado['puntaje'] = intval($matches[1]);
    }

    if (preg_match('/FORTALEZAS:(.*?)(?=BRECHAS:|RECOMENDACIÓN:|$)/is', $texto, $matches)) {
        $lineas = explode("\n", trim($matches[1]));
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (!empty($linea) && (strpos($linea, '•') === 0 || strpos($linea, '-') === 0)) {
                $resultado['fortalezas'][] = trim(ltrim($linea, '•- '));
            }
        }
    }

    if (preg_match('/BRECHAS:(.*?)(?=RECOMENDACIÓN:|EXPLICACIÓN:|$)/is', $texto, $matches)) {
        $lineas = explode("\n", trim($matches[1]));
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (!empty($linea) && (strpos($linea, '•') === 0 || strpos($linea, '-') === 0)) {
                $resultado['brechas'][] = trim(ltrim($linea, '•- '));
            }
        }
    }

    if (preg_match('/RECOMENDACIÓN:\s*([^\n]+)/i', $texto, $matches)) {
        $resultado['recomendacion'] = trim($matches[1]);
    }

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
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background: #f4f7f6;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
            color: #667eea;
        }

        .file-name {
            margin-top: 10px;
            padding: 8px;
            background: #e9ecef;
            border-radius: 8px;
            font-size: 0.95em;
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
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Analizador de CV con Gemini'); ?>
            <div class="container-fluid p-3">
                <div class="container">
                    <div class="header">
                        <h1>📄 Analizador de CV con Gemini</h1>
                        <p>Sube un CV en PDF, escribe el perfil del puesto y obtén el % de compatibilidad</p>
                    </div>
                    <div class="card">
                        <?php
                        if (!isset($aiService)) {
                            $aiService = new AIService($conn, 'google');
                        }
                        if (!$aiService->hasAvailableKeys() && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                            <div class="api-warning">
                                <strong>⚠️ Error de configuración:</strong> No se encontraron API Keys activas para Google.
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" onsubmit="showLoading()">
                            <div class="form-group">
                                <label>📋 Perfil del puesto:</label>
                                <textarea name="perfil"
                                    required><?php echo htmlspecialchars($perfil_puesto); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>📎 CV del candidato (PDF):</label>
                                <input type="file" name="cv_pdf" id="cv_pdf" accept=".pdf" required style="display:none"
                                    onchange="updateFileName(this)">
                                <label for="cv_pdf" class="file-label"><span>📁</span> Seleccionar archivo PDF</label>
                                <div class="file-name" id="file-name">Ningún archivo seleccionado</div>
                            </div>
                            <button type="submit" id="submitBtn">🔍 Analizar compatibilidad</button>
                        </form>

                        <div class="loading" id="loading">
                            <div class="spinner"></div>
                            <p>Gemini está analizando el CV...</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="error-box" style="margin-top:20px"><strong>❌ Error:</strong>
                                <?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if ($resultado && $resultado['success']): ?>
                            <div style="margin-top: 30px;">
                                <div class="score-circle"><?php echo $resultado['parsed']['puntaje']; ?>%</div>
                                <?php
                                $rec = strtolower($resultado['parsed']['recomendacion']);
                                $badge = 'entrevistar';
                                if (strpos($rec, 'contratar') !== false)
                                    $badge = 'contratar';
                                if (strpos($rec, 'descartar') !== false)
                                    $badge = 'descartar';
                                ?>
                                <div class="recommendation-badge <?php echo $badge; ?>">
                                    <strong>Recomendación:</strong>
                                    <?php echo htmlspecialchars($resultado['parsed']['recomendacion']); ?>
                                </div>
                                <div class="section">
                                    <h3>✅ Fortalezas:</h3>
                                    <?php foreach ($resultado['parsed']['fortalezas'] as $f): ?>
                                        <div class="list-item">• <?php echo htmlspecialchars($f); ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="section">
                                    <h3>⚠️ Brechas:</h3>
                                    <?php foreach ($resultado['parsed']['brechas'] as $b): ?>
                                        <div class="list-item">• <?php echo htmlspecialchars($b); ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="section">
                                    <h3>💬 Explicación:</h3>
                                    <div class="explicacion">
                                        <?php echo nl2br(htmlspecialchars($resultado['parsed']['explicacion'])); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        function updateFileName(input) { document.getElementById('file-name').textContent = input.files[0] ? '📄 ' + input.files[0].name : 'Ningún archivo seleccionado'; }
        function showLoading() { document.getElementById('loading').classList.add('active'); document.getElementById('submitBtn').disabled = true; }
        <?php if ($resultado || $error): ?>
            window.onload = function () { document.getElementById('loading').classList.remove('active'); document.getElementById('submitBtn').disabled = false; }
        <?php endif; ?>
    </script>
</body>

</html>