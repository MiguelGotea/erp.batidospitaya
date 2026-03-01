<?php
require_once '../../core/database/conexion.php';
require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

// Validar permisos
$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'] ?? null;

// Verificar acceso
if (!tienePermiso('configuracion_ia_provedores', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

// Consultar Proveedores
$stmt = $conn->query("SELECT * FROM ia_proveedores_api ORDER BY proveedor ASC, activa DESC");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Manejo de mensajes vía GET
$mensaje = $_GET['msg'] ?? '';
$tipoMensaje = ($_GET['status'] ?? '') === 'success' ? 'success' : 'danger';

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
    <link rel="stylesheet" href="css/ia_config_api.css?v=<?php echo mt_rand(1, 10000); ?>">
</head>

<body>
    <?php echo renderMenuLateral($cargoOperario); ?>

    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Configuración de APIs IA'); ?>

            <div class="content-padding">
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipoMensaje; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($mensaje); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Cabecera de la sección -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold m-0" style="color: var(--color-header-tabla);">Gestión de APIs</h3>
                    <button class="btn btn-primary shadow-sm" onclick="nuevoProveedor()">
                        <i class="fas fa-plus me-2"></i> Nuevo Proveedor
                    </button>
                </div>

                <!-- Listado de LLaves -->
                <div class="card overflow-hidden">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Proveedor</th>
                                    <th>API Key</th>
                                    <th>Password</th>
                                    <th>Estado</th>
                                    <th>Último Uso</th>
                                    <th style="width: 150px; text-align: center;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proveedores as $p): ?>
                                    <tr id="row-<?php echo $p['id']; ?>">
                                        <td class="provider-icon">
                                            <?php echo htmlspecialchars($p['proveedor']); ?>
                                        </td>
                                        <td class="api-key-hidden">
                                            <?php
                                            $part = substr($p['api_key'], 0, 8);
                                            echo htmlspecialchars($part) . "..." . htmlspecialchars(substr($p['api_key'], -4));
                                            ?>
                                        </td>
                                        <td class="api-key-hidden">
                                            <?php echo $p['password'] ? '••••••••' : '-'; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if ($p['limite_alcanzado_hoy']): ?>
                                                    <span class="badge badge-warning">LÍMITE DIARIO</span>
                                                <?php elseif ($p['activa']): ?>
                                                    <span class="badge badge-success">ACTIVA</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background: #eee; color: #666;">INACTIVA</span>
                                                <?php endif; ?>
                                                <div id="status-test-<?php echo $p['id']; ?>"></div>
                                            </div>
                                        </td>
                                        <td style="font-size: 0.85rem; color: #666;">
                                            <?php echo $p['ultimo_uso'] ? date('d/m/Y H:i', strtotime($p['ultimo_uso'])) : 'Nunca usado'; ?>
                                        </td>
                                        <td>
                                            <div class="action-btns" style="justify-content: center;">
                                                <button class="btn-action ping"
                                                    onclick="probarConexion(<?php echo $p['id']; ?>)"
                                                    title="Probar Conexión">
                                                    <i class="fas fa-bolt"></i>
                                                </button>
                                                <button class="btn-action edit"
                                                    onclick='editar(<?php echo json_encode($p); ?>)' title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" action="ajax/ia_config_api_handler.php"
                                                    onsubmit="return confirmarEliminacion()" style="display:inline;">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                                    <button type="submit" class="btn-action delete" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($proveedores)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                            No hay llaves registradas.
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

    <!-- Modal Registro / Edición -->
    <div class="modal fade" id="apiModal" tabindex="-1" aria-labelledby="apiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold" id="apiModalLabel">Configurar Proveedor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="apiForm" method="POST" action="ajax/ia_config_api_handler.php">
                        <input type="hidden" name="accion" value="guardar">
                        <input type="hidden" name="id" id="editId">

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="form-group-modal">
                                    <label class="form-label fw-bold">Proveedor</label>
                                    <select name="proveedor" id="editProveedor" class="form-select custom-select"
                                        required>
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
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-modal">
                                    <label class="form-label fw-bold">¿Activa?</label>
                                    <div class="form-check form-switch pt-2">
                                        <input class="form-check-input custom-switch" type="checkbox" name="activa"
                                            id="editActiva" checked>
                                        <label class="form-check-label" for="editActiva" id="editActivaLabel">Si</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group-modal">
                                    <label class="form-label fw-bold">API Key</label>
                                    <input type="text" name="api_key" id="editKey" class="form-control"
                                        placeholder="sk-..." required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group-modal">
                                    <label class="form-label fw-bold">Contraseña (Opcional)</label>
                                    <input type="password" name="password" id="editPassword" class="form-control"
                                        placeholder="Clave de cuenta si aplica">
                                    <div class="form-text mt-2"><i class="fas fa-info-circle me-1"></i> Usado solo para
                                        proveedores que requieren login secundario.</div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" form="apiForm" class="btn btn-primary px-4">Guardar Configuración</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Ayuda (Existente) -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i>
                        Guía de Configuración
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light p-3">
                                <div class="card-body p-0">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold mb-3">
                                        <i class="fas fa-robot me-2"></i> Propósito
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Esta herramienta permite centralizar todas las API Keys de los distintos
                                        proveedores de IA. El sistema utiliza estas llaves en cascada para garantizar
                                        que las herramientas de IA siempre funcionen.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 bg-light p-3">
                                <div class="card-body p-0">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold mb-3">
                                        <i class="fas fa-key me-2"></i> Seguridad
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Las llaves se guardan de forma segura en la base de datos empresarial. El campo
                                        "Contraseña" es opcional para cuentas con registro directo.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card border-0 bg-light p-3">
                                <div class="card-body p-0">
                                    <h6 class="text-info border-bottom pb-2 fw-bold mb-3">
                                        <i class="fas fa-external-link-alt me-2"></i> Obtener Credenciales
                                    </h6>
                                    <p class="small text-muted mb-3">
                                        Haz clic en cada proveedor para ir a su consola oficial:
                                    </p>
                                    <div class="helper-links">
                                        <?php foreach ($links as $name => $url): ?>
                                            <a href="<?php echo $url; ?>" target="_blank" class="helper-link transition">
                                                <i class="fas fa-external-link-square-alt me-1"></i>
                                                <?php echo ucfirst($name); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="alert alert-info py-2 px-3 small mt-3 mb-0 border-0 shadow-sm">
                                        <strong><i class="fas fa-info-circle me-1"></i> Recomendación:</strong>
                                        <br>
                                        Para Mistral AI, usamos el modelo <strong>medium</strong> por su equilibrio
                                        entre precisión y costo.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/ia_config_api.js?v=<?php echo mt_rand(1, 10000); ?>"></script>
</body>

</html>