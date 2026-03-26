<?php
/**
 * pitayabot_admin.php — Página de administración de PitayaBot
 * 3 tabs: Estado del Bot | Recordatorios | Guía y Funciones
 */

require_once '../../core/auth/auth.php';
require_once '../../core/layout/menu_lateral.php';
require_once '../../core/layout/header_universal.php';
require_once '../../core/permissions/permissions.php';

$usuario       = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

if (!tienePermiso('pitayabot', 'ver_estado', $cargoOperario)) {
    header('Location: /login.php');
    exit();
}

$permisoReset = tienePermiso('pitayabot', 'resetear_sesion', $cargoOperario);
$permisoPing  = tienePermiso('pitayabot', 'prueba_envio', $cargoOperario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración PitayaBot</title>
    <link rel="icon" href="../../assets/img/icon12.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/global_tools.css?v=<?php echo mt_rand(1,9999); ?>">
    <style>
        .status-dot { width:10px;height:10px;border-radius:50%;display:inline-block; }
        .status-dot.conectado    { background:#28a745; box-shadow:0 0 6px #28a74599; animation:pulse 2s infinite; }
        .status-dot.desconectado { background:#dc3545; }
        .status-dot.qr_pendiente { background:#ffc107; animation:pulse 1.5s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;}50%{opacity:.4;} }
        .cron-row { transition: opacity .2s; }
        .cron-row.inactivo { opacity:.55; }
        .badge-horario { font-family:monospace; font-size:.78rem; }
        .guide-card { border-left:4px solid #25d366; }
        .guide-card .badge-cmd { background:#f0f0f0; border-radius:6px; padding:2px 8px;
                                  font-family:monospace; font-size:.82rem; color:#333; }
        .whatsapp-bubble { background:#dcf8c6; border-radius:12px 12px 0 12px;
                           padding:8px 14px; display:inline-block; max-width:90%;
                           font-size:.88rem; white-space:pre-wrap; }
    </style>
</head>
<body>
    <?php echo renderMenuLateral($cargoOperario); ?>
    <div class="main-container">
        <div class="sub-container">
            <?php echo renderHeader($usuario, false, 'Administración PitayaBot'); ?>

            <div class="container-fluid p-3">

                <!-- ── Header ── -->
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-whatsapp text-success fs-3"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold">PitayaBot</h4>
                        <small class="text-muted">Asistente virtual de Batidos Pitaya — WhatsApp :3007</small>
                    </div>
                    <span class="ms-auto badge bg-secondary-subtle text-secondary border">
                        <span class="status-dot desconectado me-1" id="headerDot"></span>
                        <span id="headerStatus">Verificando...</span>
                    </span>
                </div>

                <!-- ── Tabs ── -->
                <ul class="nav nav-tabs mb-3" id="mainTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabEstado">
                            <i class="bi bi-wifi me-1"></i>Estado del Bot
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCrons">
                            <i class="bi bi-alarm me-1"></i>Recordatorios
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGuia">
                            <i class="bi bi-book me-1"></i>Guía y Funciones
                        </button>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- ═══════════ TAB 1: ESTADO ═══════════ -->
                    <div class="tab-pane fade show active" id="tabEstado">
                        <div class="row g-3">

                            <!-- Status card -->
                            <div class="col-md-6">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header fw-semibold d-flex align-items-center gap-2">
                                        <i class="bi bi-router text-success"></i> Conexión WhatsApp
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <span class="status-dot desconectado" id="botDot" style="width:14px;height:14px;"></span>
                                            <span class="fs-5 fw-semibold" id="botStatusText">Verificando...</span>
                                        </div>
                                        <div id="botInfoPanel" class="text-muted small d-none">
                                            <div>📱 Número: <span id="botNumero" class="fw-semibold text-dark">—</span></div>
                                            <div>🕐 Último ping: <span id="botPing">—</span></div>
                                            <div>🌐 IP VPS: <span id="botIP">—</span></div>
                                        </div>
                                        <!-- QR -->
                                        <div id="botQrPanel" class="d-none mt-3 text-center">
                                            <p class="text-warning fw-semibold mb-2">📷 Escanea el QR con WhatsApp</p>
                                            <img id="botQrImg" src="" alt="QR" class="img-fluid rounded border" style="max-width:220px;">
                                        </div>
                                    </div>
                                    <div class="card-footer d-flex gap-2 flex-wrap">
                                        <?php if ($permisoPing): ?>
                                        <button class="btn btn-sm btn-info text-white" onclick="abrirPing()">
                                            <i class="bi bi-lightning-charge"></i> Prueba de Envío
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($permisoReset): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="resetBot()">
                                            <i class="bi bi-arrow-repeat"></i> Cambiar Número
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="cargarStatus()">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Métricas rápidas -->
                            <div class="col-md-6">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-header fw-semibold">
                                        <i class="bi bi-bar-chart text-primary me-1"></i>Módulos Activos
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm mb-0">
                                            <tbody>
                                                <tr><td>🗂️ Tareas y Reuniones</td><td><span class="badge bg-success">Activo</span></td></tr>
                                                <tr><td>📝 Notas Obsidian</td><td><span class="badge bg-success">Activo</span></td></tr>
                                                <tr><td>📧 Correos</td><td><span class="badge bg-success">Activo</span></td></tr>
                                                <tr><td>⏰ Recordatorios Automáticos</td><td><span class="badge bg-success">Activo</span></td></tr>
                                                <tr><td>🌐 Clasificador IA (Gemini)</td><td><span class="badge bg-success">Activo</span></td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Ping -->
                        <div class="modal fade" id="modalPing" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title">Prueba de Envío</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Número destino</label>
                                            <input type="text" class="form-control" id="pingNumero" placeholder="88112233">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Mensaje</label>
                                            <textarea class="form-control" id="pingMensaje" rows="3">Hola, prueba de PitayaBot ✅</textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button class="btn btn-success" onclick="enviarPing()">
                                            <i class="bi bi-send"></i> Enviar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /tabEstado -->

                    <!-- ═══════════ TAB 2: RECORDATORIOS ═══════════ -->
                    <div class="tab-pane fade" id="tabCrons">
                        <div class="card shadow-sm">
                            <div class="card-header d-flex align-items-center gap-2">
                                <i class="bi bi-alarm text-warning"></i>
                                <span class="fw-semibold">Recordatorios Automáticos</span>
                                <small class="text-muted ms-auto">Zona horaria: America/Managua</small>
                            </div>
                            <div class="card-body p-0">
                                <div id="cronsContainer">
                                    <div class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-secondary"></div>
                                        <span class="ms-2 text-muted">Cargando...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /tabCrons -->

                    <!-- ═══════════ TAB 3: GUÍA ═══════════ -->
                    <div class="tab-pane fade" id="tabGuia">
                        <div class="row g-3">

                            <?php
                            $modulos = [
                                ['icon'=>'✅','color'=>'#28a745','titulo'=>'Tareas','desc'=>'Gestión de tareas del equipo.','ejemplos'=>[
                                    'crear tarea revisar inventario para el viernes',
                                    'busca mis tareas retrasadas',
                                    'cancela la tarea del inventario',
                                    'marca como finalizada la tarea de inventario',
                                ]],
                                ['icon'=>'📅','color'=>'#0d6efd','titulo'=>'Reuniones','desc'=>'Programar y consultar reuniones.','ejemplos'=>[
                                    'crea una reunión con Ana y Luis el martes a las 3pm',
                                    'busca mis reuniones de este mes',
                                    'cancela la reunión con proveedores',
                                ]],
                                ['icon'=>'📝','color'=>'#6f42c1','titulo'=>'Notas Obsidian','desc'=>'Gestión del vault de Obsidian vía GitHub.','ejemplos'=>[
                                    'crea una nota: recordar llamar al proveedor',
                                    'busca una nota sobre inventario',
                                    'crear nota decisión: decidí cambiar el proveedor por precio',
                                ]],
                                ['icon'=>'📧','color'=>'#0dcaf0','titulo'=>'Correos','desc'=>'Envío y búsqueda de correos corporativos.','ejemplos'=>[
                                    'envía un correo a Ana con asunto "Presupuesto" y el mensaje X',
                                    'envía correo a admin@batidospitaya.com con el mensaje X',
                                    'busca un correo de Carlos sobre presupuesto',
                                    '¿qué correos tengo pendientes?',
                                ]],
                                ['icon'=>'⏰','color'=>'#fd7e14','titulo'=>'Recordatorios Automáticos','desc'=>'Mensajes automáticos por cron job.','ejemplos'=>[
                                    'Briefing diario: 7 AM Lun-Vie — resumen personalizado',
                                    'Recordatorio reunión: ~1h antes de cada reunión',
                                    'Resumen fin de día: 6 PM Lun-Vie',
                                    'Revisión semanal: Viernes 5 PM (Gemini)',
                                    'Cumpleaños: 8 AM diario',
                                ]],
                            ];
                            foreach ($modulos as $m): ?>
                            <div class="col-md-6">
                                <div class="card guide-card shadow-sm h-100" style="border-left-color:<?php echo $m['color']; ?>">
                                    <div class="card-header fw-semibold" style="background:<?php echo $m['color']; ?>18">
                                        <?php echo $m['icon']; ?> <?php echo $m['titulo']; ?>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted small mb-2"><?php echo $m['desc']; ?></p>
                                        <div class="d-flex flex-column gap-2">
                                            <?php foreach ($m['ejemplos'] as $ej): ?>
                                            <div class="whatsapp-bubble"><?php echo htmlspecialchars($ej); ?></div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Confirmación y palabras clave -->
                            <div class="col-12">
                                <div class="card shadow-sm">
                                    <div class="card-header fw-semibold">
                                        💬 Flujo de Confirmación
                                    </div>
                                    <div class="card-body">
                                        <p class="small text-muted">Todas las acciones requieren confirmación antes de ejecutarse.</p>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="bg-success bg-opacity-10 rounded p-2">
                                                    <strong class="text-success small">Palabras que confirman:</strong><br>
                                                    <code>sí, si, confirmo, dale, ok, correcto, adelante, procede 👍</code>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="bg-danger bg-opacity-10 rounded p-2">
                                                    <strong class="text-danger small">Palabras que cancelan:</strong><br>
                                                    <code>no, cancelar, cancela, olvídalo, atrás, para 👎</code>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div><!-- /tabGuia -->

                </div><!-- /tab-content -->
            </div><!-- /container -->
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ─── Status del bot ───────────────────────────────────────────
function cargarStatus() {
    fetch('../gerencia/ajax/gestion_tareas_reuniones_pitayabot_status.php')
        .then(r => r.json())
        .then(data => {
            const dot      = document.getElementById('botDot');
            const headerDot = document.getElementById('headerDot');
            const text     = document.getElementById('botStatusText');
            const info     = document.getElementById('botInfoPanel');
            const qrPanel  = document.getElementById('botQrPanel');
            const headerStatus = document.getElementById('headerStatus');

            const estado = data.estado || 'desconectado';
            [dot, headerDot].forEach(d => {
                d.className = `status-dot ${estado}`;
            });

            const labels = { conectado:'Conectado ✅', desconectado:'Desconectado ❌', qr_pendiente:'Escaneando QR… 📷' };
            text.textContent = labels[estado] || estado;
            headerStatus.textContent = labels[estado] || estado;

            if (estado === 'conectado') {
                info.classList.remove('d-none');
                document.getElementById('botNumero').textContent = data.numero_telefono || '—';
                document.getElementById('botPing').textContent   = data.ultimo_ping || '—';
                document.getElementById('botIP').textContent     = data.ip_vps || '—';
                qrPanel.classList.add('d-none');
            } else if (estado === 'qr_pendiente' && data.qr) {
                qrPanel.classList.remove('d-none');
                document.getElementById('botQrImg').src = data.qr;
                info.classList.add('d-none');
            } else {
                info.classList.add('d-none');
                qrPanel.classList.add('d-none');
            }
        })
        .catch(() => {
            document.getElementById('botStatusText').textContent = 'Error al consultar';
        });
}

function abrirPing() {
    new bootstrap.Modal(document.getElementById('modalPing')).show();
}

function enviarPing() {
    const numero  = document.getElementById('pingNumero').value.trim();
    const mensaje = document.getElementById('pingMensaje').value.trim();
    if (!numero || !mensaje) return Swal.fire('Faltan datos','Completa número y mensaje','warning');

    fetch('../gerencia/ajax/gestion_tareas_reuniones_pitayabot_reset.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ accion:'ping', numero, mensaje })
    }).then(r => r.json()).then(d => {
        Swal.fire(d.success ? '¡Enviado!' : 'Error', d.message || '', d.success ? 'success' : 'error');
    });
}

function resetBot() {
    Swal.fire({
        title:'¿Cambiar número?',
        text:'Se cerrará la sesión de WhatsApp y se generará un nuevo QR.',
        icon:'warning', showCancelButton:true, confirmButtonText:'Sí, cambiar', cancelButtonText:'Cancelar'
    }).then(r => {
        if (!r.isConfirmed) return;
        fetch('../gerencia/ajax/gestion_tareas_reuniones_pitayabot_reset.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ accion:'reset' })
        }).then(r => r.json()).then(d => {
            Swal.fire('Listo','Se generará un nuevo QR en breve.','success');
            setTimeout(cargarStatus, 3000);
        });
    });
}

// ─── Crons ────────────────────────────────────────────────────
function cargarCrons() {
    fetch('ajax/pitayabot_admin_get_crons.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const container = document.getElementById('cronsContainer');
            if (!data.data.length) {
                container.innerHTML = '<p class="text-muted text-center py-3">Sin crons configurados</p>';
                return;
            }

            const rows = data.data.map(c => `
                <tr class="cron-row ${c.activo == 1 ? '' : 'inactivo'}" id="cronRow_${c.id}">
                    <td class="ps-3 py-3">
                        <div class="fw-semibold">${c.nombre}</div>
                        <small class="text-muted">${c.descripcion || ''}</small>
                    </td>
                    <td class="align-middle">
                        <code class="badge-horario badge bg-light text-dark border">${c.horario}</code>
                    </td>
                    <td class="align-middle text-muted small">${c.ultima_ejecucion ? c.ultima_ejecucion : 'Nunca'}</td>
                    <td class="align-middle pe-3">
                        <div class="form-check form-switch d-flex justify-content-end">
                            <input class="form-check-input" type="checkbox" ${c.activo == 1 ? 'checked' : ''}
                                   onchange="toggleCron(${c.id}, this.checked)" style="cursor:pointer;width:2.5em;height:1.3em;">
                        </div>
                    </td>
                </tr>`).join('');

            container.innerHTML = `<table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Recordatorio</th>
                        <th>Horario (cron)</th>
                        <th>Última ejecución</th>
                        <th class="pe-3 text-end">Activo</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>`;
        })
        .catch(() => document.getElementById('cronsContainer').innerHTML =
            '<p class="text-danger text-center py-3">Error cargando crons</p>');
}

function toggleCron(id, activo) {
    fetch('ajax/pitayabot_admin_toggle_cron.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id, activo: activo ? 1 : 0 })
    }).then(r => r.json()).then(d => {
        const row = document.getElementById(`cronRow_${id}`);
        if (row) row.classList.toggle('inactivo', !activo);
        if (!d.success) Swal.fire('Error', d.message, 'error');
    });
}

// ─── Init ─────────────────────────────────────────────────────
cargarStatus();
setInterval(cargarStatus, 30000);

document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', e => {
        if (e.target.dataset.bsTarget === '#tabCrons') cargarCrons();
    });
});
</script>
</body>
</html>
