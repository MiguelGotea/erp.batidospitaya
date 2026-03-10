<?php
/**
 * ANALIZADOR DE CV CON GEMINI API - FASE 2: PERFILES DESTILADOS
 */
require_once '../../core/auth/auth.php';
require_once '../../core/database/conexion.php';
require_once '../../core/ai/AIService.php';
require_once '../../core/utils/DocumentParser.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;

if (!$usuario) {
    header('Location: /login.php');
    exit();
}

define('MAX_FILE_SIZE', 50 * 1024 * 1024);

$resultado = null;
$error = null;
$successMsg = null;
$activeTab = 'analyzer';
$perfil_puesto = '';
$nombre_archivo = '';

// Obtener todas las plazas/cargos
$sqlPlazas = "SELECT p.id, nc.Nombre as cargo_nombre, p.sucursal, p.perfil_ia_destilado 
              FROM plazas_cargos p 
              JOIN NivelesCargos nc ON p.cargo = nc.CodNivelesCargos 
              ORDER BY nc.Nombre ASC";
$plazas = ejecutarConsulta($sqlPlazas)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'analyze_cv';
    $activeTab = $_POST['active_tab'] ?? 'analyzer';

    if ($action === 'analyze_cv' && isset($_FILES['cv_pdf'])) {
        $archivo = $_FILES['cv_pdf'];
        $idPlaza = $_POST['id_plaza'] ?? '';
        
        if ($idPlaza === 'manual') {
            $perfil_puesto = trim($_POST['perfil']);
        } else {
            foreach($plazas as $p) {
                if ($p['id'] == $idPlaza) {
                    $perfil_puesto = $p['perfil_ia_destilado'];
                    break;
                }
            }
        }

        if (empty($perfil_puesto)) {
            $error = "El perfil está vacío. Por favor selecciona una vacante con perfil o escribe texto manual.";
        } elseif ($archivo['error'] !== UPLOAD_ERR_OK) {
            $error = "Error al subir CV.";
        } else {
            try {
                $nombre_archivo = $archivo['name'];
                $parsedCV = DocumentParser::parseDocument($archivo);
                $extraParts = [];
                $promptExtra = "";
                
                if ($parsedCV['type'] === 'text') {
                    $promptExtra = "\n\nCONTENIDO DEL CV:\n\"\"\"" . $parsedCV['content'] . "\"\"\"";
                } else {
                    $extraParts[] = ['inline_data' => ['mime_type' => $parsedCV['mime_type'], 'data' => $parsedCV['data']]];
                }

                $aiService = new AIService($conn, 'google');
                $systemPrompt = "Eres un reclutador experto. Analiza el CV y compáralo con el perfil.";
                $prompt = construirPromptAnalisis($perfil_puesto) . $promptExtra;
                
                $texto_respuesta = $aiService->procesarPrompt($systemPrompt, $prompt, 0.2, $extraParts);
                if (empty($texto_respuesta)) throw new Exception("Sin respuesta de IA.");

                $resultado = ['success' => true, 'parsed' => parsearRespuesta($texto_respuesta)];
            } catch (Exception $e) { $error = $e->getMessage(); }
        }
    } 
    elseif ($action === 'distill_profile' && isset($_FILES['perfil_doc'])) {
        $idPlaza = $_POST['id_plaza_destilar'];
        try {
            if (empty($idPlaza)) throw new Exception("Selecciona una vacante.");
            $parsedDoc = DocumentParser::parseDocument($_FILES['perfil_doc']);
            $aiService = new AIService($conn, 'google');
            $destilado = DocumentParser::distillProfile($aiService, $parsedDoc['content'] ?? '', ($parsedDoc['type'] === 'inline_data' ? [['inline_data' => ['mime_type' => $parsedDoc['mime_type'], 'data' => $parsedDoc['data']]]] : []));
            
            ejecutarConsulta("UPDATE plazas_cargos SET perfil_ia_destilado = ?, perfil_ia_ultima_act = NOW() WHERE id = ?", [$destilado, $idPlaza]);
            $successMsg = "Perfil guardado satisfactoriamente.";
            $activeTab = 'profiles';
            $plazas = ejecutarConsulta($sqlPlazas)->fetchAll();
        } catch (Exception $e) { $error = "Error: " . $e->getMessage(); $activeTab = 'profiles'; }
    }
}

function construirPromptAnalisis($perfil) {
    return "Analiza el CV contra este perfil:\n\"\"\"$perfil\"\"\"\nGenera:\n1. PUNTAJE: (0-100)\n2. FORTALEZAS: (lista puntos)\n3. BRECHAS: (lista puntos)\n4. RECOMENDACIÓN: (Contratar/Entrevistar/Descartar)\n5. EXPLICACIÓN: (breve)";
}

function parsearRespuesta($texto) {
    $res = ['puntaje' => 0, 'fortalezas' => [], 'brechas' => [], 'recomendacion' => 'N/A', 'explicacion' => 'N/A'];
    if (preg_match('/PUNTAJE:\s*(\d+)/i', $texto, $m)) $res['puntaje'] = $m[1];
    if (preg_match('/RECOMENDACIÓN:\s*([^\n]+)/i', $texto, $m)) $res['recomendacion'] = trim($m[1]);
    if (preg_match('/EXPLICACIÓN:\s*([^\n]+(?:\n[^\n]+)*)/i', $texto, $m)) $res['explicacion'] = trim($m[1]);
    
    foreach(['FORTALEZAS' => 'fortalezas', 'BRECHAS' => 'brechas'] as $key => $field) {
        if (preg_match('/' . $key . ':(.*?)(?=BRECHAS:|RECOMENDACIÓN:|EXPLICACIÓN:|$)/is', $texto, $m)) {
            foreach(explode("\n", trim($m[1])) as $l) {
                if (trim($l)) $res[$field][] = trim(ltrim(trim($l), '•-* '));
            }
        }
    }
    return $res;
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
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background: #f4f7f6; min-height: 100vh; }
        .container { max-width: 900px; margin: 0 auto; }
        
        /* Tabs */
        .tabs-header { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tab-btn { padding: 12px 25px; cursor: pointer; border: none; background: #eee; border-radius: 10px 10px 0 0; font-weight: 600; color: #666; transition: 0.3s; }
        .tab-btn.active { background: #667eea; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .header { text-align: center; color: #333; margin: 20px 0 30px; }
        .card { background: white; border-radius: 20px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); padding: 30px; margin-bottom: 20px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 15px; }
        .form-group textarea { min-height: 120px; resize: vertical; }

        .file-label { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 15px; background: #f8f9fa; border: 2px dashed #667eea; border-radius: 12px; cursor: pointer; color: #667eea; }
        .file-name { margin-top: 5px; font-size: 0.9em; color: #666; }
        
        .btn-primary { width: 100%; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; font-size: 1.1em; font-weight: 600; cursor: pointer; }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }

        .profile-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .profile-table th, .profile-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8em; font-weight: bold; }
        .status-ok { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }

        .score-circle { width: 100px; height: 100px; margin: 0 auto 20px; border-radius: 50%; background: #667eea; display: flex; align-items: center; justify-content: center; font-size: 2em; font-weight: bold; color: white; }
        .recommendation-badge { display: inline-block; padding: 8px 15px; border-radius: 20px; font-weight: bold; margin-bottom: 15px; }
        .contratar { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .entrevistar { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }
        .descartar { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
        .list-item { padding: 6px 12px; background: #f8f9fa; border-radius: 8px; margin-bottom: 4px; border-left: 3px solid #667eea; }
        .explicacion { background: #f1f3f5; padding: 15px; border-radius: 10px; font-style: italic; margin-top: 10px; }

        .loading { display: none; text-align: center; padding: 20px; }
        .loading.active { display: block; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 10px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .error-box { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .success-box { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
    </style>
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Pruebas de Selección con IA'); ?>
            <div class="container-fluid p-3">
                <div class="container">
                    <div class="header">
                        <h1>🤖 Selección Inteligente (Pitaya AI)</h1>
                        <p>Analiza CVs contra perfiles destilados para mayor precisión y menor costo.</p>
                    </div>

                    <?php if ($error): ?><div class="error-box"><strong>❌ Error:</strong> <?php echo $error; ?></div><?php endif; ?>
                    <?php if ($successMsg): ?><div class="success-box"><strong>✅ Éxito:</strong> <?php echo $successMsg; ?></div><?php endif; ?>

                    <div class="tabs-header">
                        <div class="tab-btn <?php echo $activeTab === 'analyzer' ? 'active' : ''; ?>" onclick="switchTab('analyzer')">🔍 Analizador de CV</div>
                        <div class="tab-btn <?php echo $activeTab === 'profiles' ? 'active' : ''; ?>" onclick="switchTab('profiles')">📂 Gestión de Perfiles</div>
                    </div>

                    <!-- PESTAÑA: ANALIZADOR -->
                    <div id="analyzer" class="tab-content <?php echo $activeTab === 'analyzer' ? 'active' : ''; ?>">
                        <div class="card">
                            <form method="POST" enctype="multipart/form-data" onsubmit="showLoading('analyzeBtn')">
                                <input type="hidden" name="action" value="analyze_cv">
                                <input type="hidden" name="active_tab" value="analyzer">

                                <div class="form-group">
                                    <label>🎯 Seleccionar Perfil de Puesto:</label>
                                    <select name="id_plaza" id="id_plaza" onchange="toggleManualText(this.value)">
                                        <option value="manual">-- Escribir perfil manualmente --</option>
                                        <?php foreach($plazas as $p): ?>
                                            <option value="<?php echo $p['id']; ?>" <?php echo $p['perfil_ia_destilado'] ? '' : 'disabled'; ?>>
                                                <?php echo htmlspecialchars($p['cargo_nombre']); ?> (<?php echo $p['sucursal']; ?>) 
                                                <?php echo $p['perfil_ia_destilado'] ? '✅' : '❌ (Sin destilar)'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group" id="manual-text-group">
                                    <label>📋 Perfil (Texto Manual):</label>
                                    <textarea name="perfil" placeholder="Pega aquí los requisitos del puesto..."><?php echo htmlspecialchars($perfil_puesto); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>📎 CV del candidato (PDF, Word, Imagen):</label>
                                    <input type="file" name="cv_pdf" id="cv_pdf" accept=".pdf,.docx,.jpg,.jpeg,.png,.webp" required style="display:none" onchange="updateFileName(this, 'cv-file-name')">
                                    <label for="cv_pdf" class="file-label"><span>📁</span> Seleccionar CV</label>
                                    <div class="file-name" id="cv-file-name">Ningún archivo seleccionado</div>
                                </div>

                                <button type="submit" class="btn-primary" id="analyzeBtn">🚀 Analizar Compatibilidad</button>
                            </form>

                            <div class="loading" id="loading-analyze">
                                <div class="spinner"></div>
                                <p>IA analizando CV... por favor espere.</p>
                            </div>

                            <?php if ($resultado): ?>
                                <div style="margin-top: 30px; border-top: 2px solid #eee; padding-top: 20px;">
                                    <div class="score-circle"><?php echo $resultado['parsed']['puntaje']; ?>%</div>
                                    <div class="recommendation-badge <?php echo strtolower($resultado['parsed']['recomendacion']); ?>">
                                        <strong>Veredicto:</strong> <?php echo $resultado['parsed']['recomendacion']; ?>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                        <div>
                                            <h4>✅ Fortalezas</h4>
                                            <?php foreach($resultado['parsed']['fortalezas'] as $f): ?><div class="list-item"><?php echo $f; ?></div><?php endforeach; ?>
                                        </div>
                                        <div>
                                            <h4>⚠️ Brechas</h4>
                                            <?php foreach($resultado['parsed']['brechas'] as $b): ?><div class="list-item"><?php echo $b; ?></div><?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="explicacion"><strong>Análisis:</strong> <?php echo nl2br($resultado['parsed']['explicacion']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- PESTAÑA: GESTIÓN DE PERFILES -->
                    <div id="profiles" class="tab-content <?php echo $activeTab === 'profiles' ? 'active' : ''; ?>">
                        <div class="card">
                            <h3>⚡ Destilar Nuevo Perfil</h3>
                            <p style="font-size: 0.9em; color: #666; margin-bottom: 15px;">
                                Sube el PDF/Word original del cargo. La IA extraerá los requisitos clave y los guardará para que no tengas que subir el documento cada vez.
                            </p>
                            
                            <form method="POST" enctype="multipart/form-data" onsubmit="showLoading('distillBtn')">
                                <input type="hidden" name="action" value="distill_profile">
                                <input type="hidden" name="active_tab" value="profiles">

                                <div class="form-group">
                                    <label>📍 Seleccionar Vacante:</label>
                                    <select name="id_plaza_destilar" required>
                                        <option value="">-- Selecciona una plaza --</option>
                                        <?php foreach($plazas as $p): ?>
                                            <option value="<?php echo $p['id']; ?>">
                                                <?php echo htmlspecialchars($p['cargo_nombre']); ?> (<?php echo $p['sucursal']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>📄 Documento del Perfil (PDF o Word):</label>
                                    <input type="file" name="perfil_doc" id="perfil_doc" accept=".pdf,.docx" required style="display:none" onchange="updateFileName(this, 'distill-file-name')">
                                    <label for="perfil_doc" class="file-label"><span>📁</span> Subir Documento para Destilar</label>
                                    <div class="file-name" id="distill-file-name">Ningún archivo seleccionado</div>
                                </div>

                                <button type="submit" class="btn-primary" id="distillBtn">✨ Destilar y Guardar en DB</button>
                            </form>

                            <div class="loading" id="loading-distill">
                                <div class="spinner"></div>
                                <p>IA procesando perfil... esto ahorra tokens a futuro.</p>
                            </div>

                            <h3 style="margin-top: 40px;">📋 Estado de Perfiles en DB</h3>
                            <table class="profile-table">
                                <thead>
                                    <tr>
                                        <th>Cargo</th>
                                        <th>Sucursal</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($plazas as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['cargo_nombre']); ?></td>
                                        <td><?php echo $p['sucursal']; ?></td>
                                        <td>
                                            <?php if ($p['perfil_ia_destilado']): ?>
                                                <span class="status-badge status-ok">✓ Destilado</span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending">? Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            if (event) event.currentTarget.classList.add('active');
            document.querySelectorAll('input[name="active_tab"]').forEach(i => i.value = tabId);
        }

        function updateFileName(input, targetId) {
            document.getElementById(targetId).textContent = input.files[0] ? '📄 ' + input.files[0].name : 'Ningún archivo seleccionado';
        }

        function showLoading(btnId) {
            document.getElementById(btnId).disabled = true;
            if (btnId === 'analyzeBtn') document.getElementById('loading-analyze').classList.add('active');
            else document.getElementById('loading-distill').classList.add('active');
        }

        function toggleManualText(val) {
            const group = document.getElementById('manual-text-group');
            if (group) group.style.display = (val === 'manual' ? 'block' : 'none');
        }

        window.onload = function() {
            const select = document.getElementById('id_plaza');
            if (select) toggleManualText(select.value);
        }
    </script>
</body>
</html>