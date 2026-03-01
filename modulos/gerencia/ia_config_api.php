<?php
require_once '../../core/database/conexion.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

// Validar permisos
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;

// Verificar acceso (SIEMPRE debe existir permiso 'vista')
if (!tienePermiso('configuracion_ia_provedores', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../core/assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/core/assets/css/global_tools.css?v=<?php echo mt_rand(1, 10000); ?>">
    <style>
        :root {
            --color-principal: #51B8AC;
            --color-header-tabla: #0E544C;
            --btn-nuevo: #218838;
            --btn-nuevo-hover: #1d6f42;
            --bg: #f4f7f6;
            --text: #333;
            --text-dim: #666;
            --success: #218838;
            --danger: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Calibri', sans-serif;
            font-size: clamp(12px, 2vw, 18px);
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .main-container {
            margin-left: 260px;
            /* Ajuste para menú lateral */
            transition: all 0.3s;
        }

        .sub-container {
            padding: 0;
            min-height: 100vh;
        }

        .content-padding {
            padding: 30px;
        }

        /* Card Style */
        .card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--color-header-tabla);
            margin-bottom: 20px;
            border-bottom: 2px solid var(--color-principal);
            padding-bottom: 10px;
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
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Configuración de APIs IA'); ?>

            <div class="content-padding">
                <?php if ($mensaje): ?>
                    <div
                        class="alert alert-<?php echo $tipoMensaje === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

                        <div class="action-btns" style="width: 100%; justify-content: flex-end;">
                            <button type="button" class="btn" style="background: rgba(0,0,0,0.05); color: #666;"
                                onclick="limpiarForm()">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Guardar Configuración</button>
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
                                                <button class="btn"
                                                    style="padding: 8px; background: rgba(255,255,255,0.05);"
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
        </div> <!-- Cierre content-padding -->
    </div> <!-- Cierre sub-container -->
    </div> <!-- Cierre main-container -->

    <!-- Modal de Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Configuración de APIs IA
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-robot me-2"></i> Propósito
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Esta herramienta permite centralizar todas las API Keys de los distintos
                                        proveedores de IA (Google, OpenAI, Mistral, etc.). El sistema utiliza estas
                                        llaves en cascada para garantizar que las herramientas de IA siempre funcionen,
                                        incluso si una cuenta agota su saldo.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-key me-2"></i> Seguridad
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Las llaves se guardan de forma segura en la base de datos empresarial. El campo
                                        "Contraseña" es opcional y se utiliza para identificar cuentas registradas con
                                        credenciales personalizadas fuera de SSO.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-info border-bottom pb-2 fw-bold">
                                        <i class="fas fa-external-link-alt me-2"></i> Obtener Credenciales
                                    </h6>
                                    <p class="small text-muted mb-3">
                                        Haz clic en cada proveedor para ir a su consola oficial y generar tus API Keys:
                                    </p>
                                    <div class="helper-links">
                                        <?php foreach ($links as $name => $url): ?>
                                            <a href="<?php echo $url; ?>" target="_blank" class="helper-link">
                                                <i class="fas fa-external-link-square-alt me-1"></i>
                                                <?php echo ucfirst($name); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="alert alert-info py-2 px-3 small mt-3 mb-0">
                                        <strong><i class="fas fa-info-circle me-1"></i> Nota:</strong>
                                        <br>
                                        Para Mistral AI, recomendamos usar el modelo <strong>medium</strong> para la
                                        generación de gráficos de ventas por su equilibrio entre precisión y costo.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Ajuste de z-index para evitar que el backdrop cubra el modal */
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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