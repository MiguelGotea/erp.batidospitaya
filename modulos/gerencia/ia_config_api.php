<?php
require_once '../../core/database/conexion.php';
require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';

// Validar permisos
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;
if (!tienePermiso('ia_graficos_ventas', 'especial', $cargoOperario)) {
    die('Acceso denegado: Se requiere permiso especial para configurar APIs');
}

$mensaje = '';
$tipoMensaje = 'success';

// Procesar Guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = $_POST['id'] ?? null;
        $proveedor = $_POST['proveedor'];
        $apiKey = $_POST['api_key'];
        $password = $_POST['password'] ?? null;
        $activa = isset($_POST['activa']) ? 1 : 0;

        try {
            if ($id) {
                $stmt = $conn->prepare("UPDATE ia_proveedores_api SET proveedor = ?, api_key = ?, password = ?, activa = ? WHERE id = ?");
                $stmt->execute([$proveedor, $apiKey, $password, $activa, $id]);
                $mensaje = "Proveedor actualizado correctamente";
            } else {
                $stmt = $conn->prepare("INSERT INTO ia_proveedores_api (proveedor, api_key, password, activa) VALUES (?, ?, ?, ?)");
                $stmt->execute([$proveedor, $apiKey, $password, $activa]);
                $mensaje = "Nuevo proveedor registrado correctamente";
            }
        } catch (Exception $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipoMensaje = 'error';
        }
    } elseif ($accion === 'eliminar') {
        $id = $_POST['id'];
        try {
            $stmt = $conn->prepare("DELETE FROM ia_proveedores_api WHERE id = ?");
            $stmt->execute([$id]);
            $mensaje = "Proveedor eliminado";
        } catch (Exception $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipoMensaje = 'error';
        }
    }
}

// Consultar Proveedores
$stmt = $conn->query("SELECT * FROM ia_proveedores_api ORDER BY proveedor ASC, activa DESC");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enlaces de ayuda
$links = [
    'google' => 'https://aistudio.google.com/app/apikey',
    'openai' => 'https://platform.openai.com/api-keys',
    'deepseek' => 'https://platform.deepseek.com/api_keys',
    'mistral' => 'https://console.mistral.ai/api-keys/',
    'openrouter' => 'https://openrouter.ai/keys',
    'huggingface' => 'https://huggingface.co/settings/tokens',
    'cerebras' => 'https://cloud.cerebras.ai/',
    'groq' => 'https://console.groq.com/keys'
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de IA API - Batidos Pitaya</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --bg: #0f172a;
            --card-bg: rgba(255, 255, 255, 0.05);
            --text: #f8fafc;
            --text-dim: #94a3b8;
            --success: #22c55e;
            --danger: #ef4444;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            background-image:
                radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(239, 68, 68, 0.1) 0px, transparent 50%);
            color: var(--text);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(to right, #60a5fa, #f472b6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .header p {
            color: var(--text-dim);
            font-size: 1.1rem;
        }

        /* Card Style */
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form Controls */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            color: var(--text-dim);
            font-size: 0.9rem;
            font-weight: 500;
        }

        input,
        select {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 12px 16px;
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: white;
        }

        /* Table Style */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            text-align: left;
            padding: 15px;
            color: var(--text-dim);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--glass-border);
        }

        td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(234, 179, 8, 0.1);
            color: #eab308;
        }

        .provider-icon {
            text-transform: capitalize;
            font-weight: 600;
            color: #60a5fa;
        }

        .api-key-hidden {
            font-family: monospace;
            color: var(--text-dim);
            font-size: 0.9rem;
        }

        .action-btns {
            display: flex;
            gap: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .helper-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .helper-link {
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            text-decoration: none;
            color: var(--text-dim);
            border: 1px solid var(--glass-border);
            transition: all 0.3s;
        }

        .helper-link:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(59, 130, 246, 0.05);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.1);
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: var(--text);
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--success);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Central AI API</h1>
            <p>Gestiona todas las llaves y motores de inteligencia artificial</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipoMensaje; ?>">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- Formulario Registro / Edición -->
        <div class="card" id="formCard">
            <h2 class="card-title" id="formTitle">Registro de Proveedor</h2>
            <form method="POST">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="editId">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Proveedor</label>
                        <select name="proveedor" id="editProveedor" required>
                            <option value="google">Google Gemini</option>
                            <option value="openai">OpenAI</option>
                            <option value="deepseek">DeepSeek</option>
                            <option value="mistral">Mistral AI</option>
                            <option value="openrouter">OpenRouter</option>
                            <option value="huggingface">Hugging Face</option>
                            <option value="cerebras">Cerebras</option>
                            <option value="groq">Groq</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label>API Key</label>
                        <input type="text" name="api_key" id="editKey" placeholder="sk-..." required>
                    </div>
                    <div class="form-group">
                        <label>Contraseña (Opcional)</label>
                        <input type="password" name="password" id="editPassword" placeholder="Clave de cuenta">
                    </div>
                    <div class="form-group" style="align-items: center; justify-content: flex-end;">
                        <label>¿Activa?</label>
                        <label class="toggle-switch">
                            <input type="checkbox" name="activa" id="editActiva" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                    <div class="helper-links-container">
                        <label style="font-size: 0.8rem; color: var(--text-dim);">Obtener llaves aquí:</label>
                        <div class="helper-links">
                            <?php foreach ($links as $name => $url): ?>
                                <a href="<?php echo $url; ?>" target="_blank" class="helper-link">
                                    <?php echo ucfirst($name); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="action-btns">
                        <button type="button" class="btn" style="background: rgba(255,255,255,0.05);"
                            onclick="limpiarForm()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Listado de LLaves -->
        <div class="card">
            <h2 class="card-title">Llaves Registradas</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Proveedor</th>
                            <th>API Key</th>
                            <th>Password</th>
                            <th>Estado</th>
                            <th>Último Uso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proveedores as $p): ?>
                            <tr>
                                <td class="provider-icon">
                                    <?php echo $p['proveedor']; ?>
                                </td>
                                <td class="api-key-hidden">
                                    <?php
                                    $part = substr($p['api_key'], 0, 8);
                                    echo $part . "..." . substr($p['api_key'], -4);
                                    ?>
                                </td>
                                <td class="api-key-hidden">
                                    <?php echo $p['password'] ? '••••••••' : '-'; ?>
                                </td>
                                <td>
                                    <?php if ($p['limite_alcanzado_hoy']): ?>
                                        <span class="badge badge-warning">LÍMITE DIARIO</span>
                                    <?php elseif ($p['activa']): ?>
                                        <span class="badge badge-success">ACTIVA</span>
                                    <?php else: ?>
                                        <span class="badge"
                                            style="background: rgba(255,255,255,0.05); color: var(--text-dim);">INACTIVA</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-dim);">
                                    <?php echo $p['ultimo_uso'] ? date('d/m/Y H:i', strtotime($p['ultimo_uso'])) : 'Nunca usado'; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn" style="padding: 8px; background: rgba(255,255,255,0.05);"
                                            onclick='editar(<?php echo json_encode($p); ?>)'>
                                            ✏️
                                        </button>
                                        <form method="POST" onsubmit="return confirm('¿Eliminar este proveedor?')">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="padding: 8px;">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($proveedores)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-dim);">
                                    No hay llaves registradas. Añade una arriba.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function editar(data) {
            document.getElementById('formTitle').textContent = "Editar Proveedor: " + data.proveedor.toUpperCase();
            document.getElementById('editId').value = data.id;
            document.getElementById('editProveedor').value = data.proveedor;
            document.getElementById('editKey').value = data.api_key;
            document.getElementById('editPassword').value = data.password || '';
            document.getElementById('editActiva').checked = data.activa == 1;

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function limpiarForm() {
            document.getElementById('formTitle').textContent = "Registro de Proveedor";
            document.getElementById('editId').value = '';
            document.getElementById('editKey').value = '';
            document.getElementById('editPassword').value = '';
            document.getElementById('editActiva').checked = true;
        }
    </script>
</body>

</html>