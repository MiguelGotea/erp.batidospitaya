<?php
/**
 * crm_bot.php — CRM Bot WhatsApp (herramienta de testeo)
 * Módulo: sistemas
 */

require_once '../../core/auth/auth.php';
require_once '../../core/permissions/permissions.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('crm_bot', 'vista', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$puedeResponder = tienePermiso('crm_bot', 'responder', $cargoOperario);
$puedeCambiarEstado = tienePermiso('crm_bot', 'cambiar_estado', $cargoOperario);
$puedeGestionarBot = tienePermiso('crm_bot', 'gestionar_intents', $cargoOperario);
$puedeReset = tienePermiso('crm_bot', 'resetear_sesion', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Bot WhatsApp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?= mt_rand(1, 9999) ?>">
    <link rel="stylesheet" href="css/crm_bot.css?v=<?= mt_rand(1, 9999) ?>">
</head>

<body>
    <?= renderMenuLateral($cargoOperario) ?>

    <div class="main-container">
        <div class="sub-container">
            <?= renderHeader($usuario, false, 'CRM Bot WhatsApp') ?>

            <div class="container-fluid p-3">

                <!-- Barra de estado VPS + acciones -->
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">

                    <!-- Badge de estado (igual que campanas) -->
                    <div class="d-flex align-items-center gap-3">
                        <span>
                            <span class="wsp-dot desconectado" id="vpsDot"></span>
                            <small id="vpsStatusTexto">⏳ Verificando...</small>
                        </span>

                        <!-- Botón QR -->
                        <span id="btnVerQR" class="d-none">
                            <button class="btn btn-sm btn-warning" onclick="mostrarModalQR()">
                                <i class="bi bi-qr-code me-1"></i> Ver QR
                            </button>
                        </span>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($puedeGestionarBot): ?>
                            <a href="crm_bot_intents.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-robot me-1"></i> Gestionar Intenciones
                            </a>
                        <?php endif; ?>
                        <?php if ($puedeReset): ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="resetearSesion()">
                                <i class="bi bi-arrow-repeat me-1"></i> Cambiar Número
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Layout chat -->
                <div class="crm-layout">

                    <!-- SIDEBAR: Lista de conversaciones -->
                    <div class="crm-sidebar">
                        <div class="crm-sidebar-header">
                            <h6><i class="bi bi-chat-dots me-1"></i> Conversaciones</h6>
                        </div>
                        <div class="crm-sidebar-filters">
                            <input type="text" class="form-control form-control-sm mb-1" id="filtroNumero"
                                placeholder="🔍 Buscar número..." oninput="cargarConversaciones()">
                            <div class="d-flex gap-1">
                                <select id="filtroStatus" class="form-select form-select-sm"
                                    onchange="cargarConversaciones()">
                                    <option value="all">Todos</option>
                                    <option value="bot">🤖 Bot</option>
                                    <option value="humano">👤 Humano</option>
                                </select>
                                <select id="filtroInstancia" class="form-select form-select-sm"
                                    onchange="cargarConversaciones()">
                                    <option value="wsp-crmbot">CRM Bot</option>
                                    <option value="wsp-clientes">Clientes</option>
                                </select>
                            </div>
                        </div>
                        <ul class="crm-conv-list" id="listaConversaciones">
                            <li class="crm-loading"><i class="bi bi-arrow-repeat spin"></i> Cargando...</li>
                        </ul>
                    </div>

                    <!-- CHAT: Panel derecho -->
                    <div class="crm-chat" id="panelChat">
                        <div class="crm-empty" id="estadoVacio">
                            <i class="bi bi-chat-square-dots"></i>
                            <p>Selecciona una conversación</p>
                        </div>
                    </div>

                </div><!-- /crm-layout -->

            </div>
        </div>
    </div>

    <!-- Modal QR -->
    <div class="modal fade" id="modalQR" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title">📷 Escanear QR — wsp-crmbot</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-2">
                    <img id="qrImg" src="" alt="QR" class="img-fluid" style="max-width:250px;">
                    <p class="text-muted small mt-2">Escanea con WhatsApp → Dispositivos vinculados</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ayuda -->
    <div class="modal fade" id="pageHelpModal" tabindex="-1" aria-labelledby="pageHelpModalLabel" aria-hidden="true"
        data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pageHelpModalLabel">
                        <i class="fas fa-info-circle me-2"></i> Guía CRM Bot WhatsApp
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary border-bottom pb-2 fw-bold">
                                        <i class="fas fa-robot me-2"></i> Bot Automático
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        El bot responde mensajes usando 4 niveles: contexto previo, keywords, similitud
                                        TF-IDF y Naive Bayes. Las intenciones se configuran en "Gestionar Intenciones".
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-success border-bottom pb-2 fw-bold">
                                        <i class="fas fa-user me-2"></i> Tomar Control
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Al hacer clic en "Tomar control", el bot deja de responder y el agente puede
                                        escribir manualmente. Si el cliente escribe "asesor", el cambio ocurre
                                        automáticamente.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-warning border-bottom pb-2 fw-bold">
                                        <i class="fas fa-history me-2"></i> Historial Unificado
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Se muestran mensajes de todas las fuentes: usuario, bot, agente y campañas. Las
                                        campañas enviadas a ese número también aparecen aquí.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-danger border-bottom pb-2 fw-bold">
                                        <i class="fas fa-qrcode me-2"></i> Cambiar Número
                                    </h6>
                                    <p class="small text-muted mb-0">
                                        Usa "Cambiar Número" para desvincular el número actual y escanear uno nuevo. El
                                        bot detectará el cambio en el próximo ciclo (~60s).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 px-3 small">
                        <strong><i class="fas fa-info-circle me-1"></i> Herramienta de testeo.</strong>
                        Las conversaciones dependen de <code>instancia + número_cliente</code>, no del número remitente.
                        Si se cambia el número en el VPS, las conversaciones siguen vinculadas correctamente.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        #pageHelpModal {
            z-index: 1060 !important;
        }

        .modal-backdrop {
            z-index: 1050 !important;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>

    <!-- Pasar permisos al JS -->
    <script>
        const CRM_PERMISOS = {
            responder:     <?= $puedeResponder ? 'true' : 'false' ?>,
            cambiarEstado: <?= $puedeCambiarEstado ? 'true' : 'false' ?>,
            reset:         <?= $puedeReset ? 'true' : 'false' ?>
        };
    </script>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/crm_bot.js?v=<?= mt_rand(1, 9999) ?>"></script>
</body>

</html>