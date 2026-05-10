<?php
/**
 * pitayabot_admin.php — Administración de PitayaBot
 * 3 tabs: Estado | Recordatorios | Guía y Funciones
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
$permisoPing  = tienePermiso('pitayabot', 'prueba_envio',    $cargoOperario);
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
        .status-dot { width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0; }
        .status-dot.conectado    { background:#28a745;box-shadow:0 0 6px #28a74599;animation:pulse 2s infinite; }
        .status-dot.desconectado { background:#dc3545; }
        .status-dot.qr_pendiente { background:#ffc107;animation:pulse 1.5s infinite; }
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}

        .cron-row { transition:opacity .2s; }
        .cron-row.inactivo { opacity:.5; }
        .badge-horario { font-family:monospace;font-size:.78rem; }

        .guide-card { border-left:4px solid #25d366; }
        .wa-bubble {
            background:#dcf8c6;border-radius:12px 12px 0 12px;
            padding:8px 14px;display:inline-block;max-width:100%;
            font-size:.84rem;white-space:pre-wrap;line-height:1.4;
        }
        .wa-bubble.bot {
            background:#fff;border-radius:0 12px 12px 12px;border:1px solid #ddd;
        }
        .intent-badge { font-size:.7rem;font-family:monospace;vertical-align:middle; }

        .trigger-result { font-size:.82rem; }
        .cron-actions { display:flex;align-items:center;gap:8px;justify-content:flex-end; }
    </style>
</head>
<body>
<?php echo renderMenuLateral($cargoOperario); ?>
<div class="main-container">
  <div class="sub-container">
    <?php echo renderHeader($usuario, 'Administración PitayaBot'); ?>

    <div class="container-fluid p-3">

      <!-- Header -->
      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="bg-success bg-opacity-10 rounded-circle p-3">
          <i class="bi bi-whatsapp text-success fs-3"></i>
        </div>
        <div>
          <h4 class="mb-0 fw-bold">PitayaBot</h4>
          <small class="text-muted">Asistente virtual Batidos Pitaya — WhatsApp :3007</small>
        </div>
        <span class="ms-auto badge bg-secondary-subtle text-secondary border d-flex align-items-center gap-1 fs-6 px-3 py-2">
          <span class="status-dot desconectado" id="headerDot"></span>
          <span id="headerStatus">Verificando...</span>
        </span>
      </div>

      <!-- Tabs -->
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

            <div class="col-md-6">
              <div class="card h-100 shadow-sm">
                <div class="card-header fw-semibold">
                  <i class="bi bi-bar-chart text-primary me-1"></i>Módulos y Estado
                </div>
                <div class="card-body p-0">
                  <table class="table table-sm mb-0">
                    <tbody>
                      <tr><td class="ps-3">🗂️ Tareas</td>                    <td><span class="badge bg-success">Activo</span></td></tr>
                      <tr><td class="ps-3">📅 Reuniones + ICS</td>            <td><span class="badge bg-success">Activo</span></td></tr>
                      <tr><td class="ps-3">📝 Notas Obsidian (GitHub)</td>   <td><span class="badge bg-success">Activo</span></td></tr>
                      <tr><td class="ps-3">📧 Correos (SMTP + IMAP)</td>     <td><span class="badge bg-success">Activo</span></td></tr>
                      <tr><td class="ps-3">⏰ Recordatorios Automáticos</td> <td><span class="badge bg-success">Activo</span></td></tr>
                      <tr><td class="ps-3">🌐 Clasificador IA (cascada)</td> <td><span class="badge bg-success">Activo</span></td></tr>
                      <tr><td class="ps-3">🔐 Flujo de confirmación</td>     <td><span class="badge bg-success">Activo</span></td></tr>
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
                <div class="modal-header">
                  <h5 class="modal-title">Prueba de Envío</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="mb-3">
                    <label class="form-label">Número destino</label>
                    <input type="text" class="form-control" id="pingNumero" placeholder="88112233">
                    <small class="text-muted">Solo número local (sin 505)</small>
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
              <small class="text-muted ms-auto">Zona horaria: America/Managua (UTC-6)</small>
            </div>
            <div class="card-body p-0">
              <div id="cronsContainer">
                <div class="text-center py-4">
                  <div class="spinner-border spinner-border-sm text-secondary"></div>
                  <span class="ms-2 text-muted">Cargando...</span>
                </div>
              </div>
            </div>
            <div class="card-footer text-muted small">
              <i class="bi bi-info-circle me-1"></i>
              Los crons se ejecutan automáticamente desde el VPS. El botón <strong>▶ Probar</strong> llama el endpoint directamente para verificar qué mensajes enviaría en este momento.
            </div>
          </div>
        </div><!-- /tabCrons -->


        <!-- ═══════════ TAB 3: GUÍA ═══════════ -->
        <div class="tab-pane fade" id="tabGuia">
          <div class="row g-3">

            <?php
            $modulos = [
              [
                'icon'   => '✅',
                'color'  => '#28a745',
                'titulo' => 'Tareas',
                'desc'   => 'Gestiona tus tareas pendientes, retrasadas y semanales.',
                'casos'  => [
                  ['u' => 'crea una tarea revisar inventario de sucursal norte para el viernes',       'b' => "✅ Tarea creada\n📋 *Revisar inventario de sucursal Norte*\n📅 Fecha límite: viernes 28/03\n🆔 ID: #142"],
                  ['u' => 'busca mis tareas retrasadas',                                              'b' => "⚠️ *2 tareas retrasadas*\n1️⃣ Actualizar contrato Ana López → venció hace 3 días\n2️⃣ Enviar reporte mensual → venció hace 5 días"],
                  ['u' => 'tareas de esta semana',                                                    'b' => "📋 *3 tareas esta semana*\n1️⃣ Revisar inventario → vence mañana\n2️⃣ Aprobar presupuesto → vence el miércoles\n3️⃣ Reunión con proveedores → vence el viernes"],
                  ['u' => 'finaliza la tarea revisar inventario',                                     'b' => "Encontré 2 tareas:\n1️⃣ Revisar inventario norte (vence 28/03)\n2️⃣ Revisar inventario central (vence 30/03)\n*Responde con el número.*"],
                  ['u' => 'cancela la tarea del reporte mensual',                                     'b' => "¿Cancelar *Enviar reporte mensual*?\nResponde *sí* o *no*."],
                  ['u' => 'agrega pendiente llamar al proveedor mañana',                              'b' => "¿Crear la tarea *Llamar al proveedor* para mañana 27/03?\nResponde *sí* o *no*."],
                ]
              ],
              [
                'icon'   => '📅',
                'color'  => '#0d6efd',
                'titulo' => 'Reuniones + ICS',
                'desc'   => 'Crea, modifica y cancela reuniones. Se envían invitaciones al calendario.',
                'casos'  => [
                  ['u' => 'crea una reunión con Ana y Carlos el martes a las 10am',                  'b' => "Para *Ana* encontré:\n1️⃣ Ana Flores — Gerente de Sucursal\n2️⃣ Ana López — Coordinadora RRHH\n¿Cuál es?"],
                  ['u' => 'reuniones de esta semana',                                                'b' => "📅 *2 reuniones esta semana*\n📌 Lunes 25/03 — 09:00 AM\nRevisión de inventario (1h)\n\n📌 Miércoles 27/03 — 03:00 PM\nEvaluación proveedores (2h)"],
                  ['u' => 'qué horario tengo libre el jueves',                                       'b' => "🕐 *Horarios libres el jueves 27/03*\nTus reuniones: 09:00–10:00, 14:00–15:30\n✅ 07:00–09:00 (2h libres)\n✅ 10:00–14:00 (4h libres)\n✅ 15:30 en adelante"],
                  ['u' => 'cambia la reunión con proveedores para el viernes a las 3pm',             'b' => "¿Reprogramar *Evaluación proveedores* al viernes 29/03 a las 3:00 PM?\nSe reenviará el ICS a los participantes."],
                  ['u' => 'cancela la reunión con RRHH de hoy',                                     'b' => "¿Cancelar *Reunión con RRHH* (25/03 09:00 AM)?\nSe notificará a los participantes con ICS de cancelación."],
                ]
              ],
              [
                'icon'   => '📝',
                'color'  => '#6f42c1',
                'titulo' => 'Notas Obsidian',
                'desc'   => 'Crea y busca notas en tu vault de Obsidian vía GitHub.',
                'casos'  => [
                  ['u' => 'crea una nota: el proveedor Norte subió un 15% los costos de empaque', 'b' => "✅ Nota creada en tu vault\n📝 *El proveedor Norte subió costos*\n📂 Guardada en: /Notas/2026-03-26.md"],
                  ['u' => 'decisión: decidí no renovar el contrato con Distribuidora Central', 'b' => "¿Registrar decisión: *No renovar contrato con Distribuidora Central*?\nSe guardará en /Decisiones/ con formato estándar."],
                  ['u' => 'busca una nota sobre presupuesto',                                 'b' => "🔍 Encontré 2 notas relevantes:\n1️⃣ Presupuesto Q1 aprobado (15/03)\n2️⃣ Ajuste presupuestario sucursal norte (10/03)"],
                  ['u' => 'anota esto: reunión con proveedores fue positiva, acordamos descuento del 5% en pedidos mayores a 500 unidades', 'b' => "✅ Nota guardada con estructura:\n# Reunión con proveedores\n**Acuerdo:** descuento 5% en pedidos >500 uds."],
                  ['u' => 'crea una nota decisión dividir el turno de tarde en dos grupos', 'b' => "¿Registrar decisión: *Dividir turno de tarde en dos grupos*?\nSe guardará en /Decisiones/2026-03-26_dividir-turno.md"],
                ]
              ],
              [
                'icon'   => '📧',
                'color'  => '#0dcaf0',
                'titulo' => 'Correos Corporativos',
                'desc'   => 'Envía y busca correos con tu cuenta @batidospitaya.com.',
                'casos'  => [
                  ['u' => 'envía un correo a Ana con asunto Presupuesto Q2 y el mensaje adjunto la propuesta revisada', 'b' => "📧 *Correo enviado*\nPara: Ana Flores (ana.flores@batidospitaya.com)\nAsunto: Presupuesto Q2\n✅ Enviado correctamente"],
                  ['u' => 'envía correo a mantenimiento@batidospitaya.com con el asunto Falla equipo y el detalle de la falla', 'b' => "¿Enviar correo a mantenimiento@batidospitaya.com con asunto *Falla equipo*?\nResponde *sí* para confirmar."],
                  ['u' => 'busca un correo de Carlos sobre presupuesto',                  'b' => "📩 *2 correos encontrados de Carlos:*\n[1] *Aprobación presupuesto Q1* — 20/03\n\"Buenos días, adjunto el presupuesto...\"\n\n[2] *Revisión de costos* — 18/03"],
                  ['u' => 'qué correos tengo pendientes',                                 'b' => "📬 *3 correos sin responder (últimos 7 días):*\n1. Ana Flores — Solicitud de vacaciones (hace 2 días)\n2. RRHH — Actualización contrato (hace 3 días)\n3. Proveedores — Cotización (hace 5 días)"],
                ]
              ],
              [
                'icon'   => '⏰',
                'color'  => '#fd7e14',
                'titulo' => 'Recordatorios Automáticos',
                'desc'   => 'Mensajes enviados automáticamente por el bot según horario.',
                'casos'  => [
                  ['u' => '🌅 Briefing matutino (7 AM, Lun–Vie)',                         'b' => "🌅 ¡Buenos días, Carlos!\n\nHoy tienes:\n📋 2 tareas con vencimiento hoy\n📅 1 reunión a las 10:00 AM\n⚠️ 3 tareas retrasadas pendientes\n\n¡Buen día! 🚀"],
                  ['u' => '⏰ Recordatorio de reunión (1h antes)',                         'b' => "⏰ *Recordatorio — en 1 hora*\n\n📌 Evaluación de proveedores\n🕙 10:00 AM (en 58 min)\n👥 Con: Ana Flores, Luis Martínez\n\n¡Prepárate con tiempo!"],
                  ['u' => '📊 Resumen fin de día (6 PM, Lun–Vie)',                        'b' => "📊 *Resumen del día — 26 de marzo*\n\n✅ Completaste 3 tareas hoy\n🔄 2 tareas pasan a mañana\n📅 Tuviste 2 reuniones\n\n¡Buen trabajo! 💪"],
                  ['u' => '📅 Revisión semanal (Viernes 5 PM)',                           'b' => "📊 *Semana del 17–21 de marzo*\n\n✅ 8 tareas completadas\n🔄 2 pasan a la próxima semana\n🤝 4 reuniones\n📝 3 notas creadas\n📬 2 correos sin responder\n\n💡 Las tareas de inventario vencen el martes — empiézalas el lunes."],
                  ['u' => '🎂 Cumpleaños (8 AM diario)',                                  'b' => "🎂 ¡Hoy cumple años un compañero!\n\n🎉 María González — Coord. RRHH\n🎈 Cumple 32 años hoy\n🏢 Sucursal Central\n\n¿Le envío un mensaje de felicitación? Responde *sí* o *no*."],
                ]
              ],
              [
                'icon'   => '🤖',
                'color'  => '#20c997',
                'titulo' => 'Flujo de Confirmación',
                'desc'   => 'Toda acción requiere confirmación antes de ejecutarse. El bot también maneja ambigüedad.',
                'casos'  => [
                  ['u' => 'sí / si / confirmo / dale / ok / correcto / adelante / 👍',    'b' => "✅ Acción ejecutada correctamente."],
                  ['u' => 'no / cancelar / cancela / olvídalo / atrás / 👎',             'b' => "❌ Entendido, acción cancelada."],
                  ['u' => '[mensaje ambiguo o confianza baja]',                           'b' => "🤔 No estoy seguro de lo que quieres hacer. ¿Puedes reformularlo?\n\nEjemplo: *\"crea una tarea revisar inventario para el viernes\"*"],
                  ['u' => '[sesión expirada después de 5 min sin confirmar]',             'b' => "⏱️ Tu sesión expiró. Por favor repite la acción."],
                ]
              ],
            ];

            foreach ($modulos as $m):
            ?>
            <div class="col-md-6">
              <div class="card guide-card shadow-sm h-100" style="border-left-color:<?= $m['color'] ?>">
                <div class="card-header fw-semibold" style="background:<?= $m['color'] ?>18">
                  <?= $m['icon'] ?> <?= $m['titulo'] ?>
                </div>
                <div class="card-body">
                  <p class="text-muted small mb-3"><?= $m['desc'] ?></p>
                  <div class="d-flex flex-column gap-3">
                    <?php foreach ($m['casos'] as $caso): ?>
                    <div>
                      <div class="mb-1"><span class="wa-bubble"><?= htmlspecialchars($caso['u']) ?></span></div>
                      <?php if ($caso['b']): ?>
                      <div class="d-flex justify-content-end">
                        <span class="wa-bubble bot"><?= htmlspecialchars($caso['b']) ?></span>
                      </div>
                      <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>

            <!-- Intents disponibles -->
            <div class="col-12">
              <div class="card shadow-sm">
                <div class="card-header fw-semibold">🧠 Intenciones que reconoce el clasificador IA</div>
                <div class="card-body">
                  <div class="row g-2">
                    <?php
                    $intents = [
                      ['crear_tarea','success'],['buscar_tarea','success'],['modificar_tarea_fecha','success'],
                      ['finalizar_tarea','success'],['cancelar_tarea','success'],['buscar_tareas_retrasadas','success'],
                      ['resumen_tareas_semana','success'],
                      ['crear_reunion','primary'],['buscar_reunion','primary'],['modificar_reunion_fecha','primary'],
                      ['cancelar_reunion','primary'],['resumen_reuniones_semana','primary'],['horarios_libres','primary'],
                      ['crear_nota','purple'],['buscar_nota','purple'],['crear_nota_decision','purple'],
                      ['enviar_correo','info'],['buscar_correo','info'],['correos_pendientes','info'],
                      ['consulta_libre','secondary'],['desconocido','danger'],
                    ];
                    foreach ($intents as [$intent, $color]):
                    ?>
                    <div class="col-auto">
                      <code class="badge bg-<?= $color === 'purple' ? 'light text-dark border' : $color . '-subtle text-' . $color ?> intent-badge">
                        <?= $intent ?>
                      </code>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div><!-- /tabGuia -->

      </div><!-- tab-content -->
    </div><!-- container -->
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ─── STATUS ──────────────────────────────────────────────────────
function cargarStatus() {
    fetch('../gerencia/ajax/gestion_tareas_reuniones_pitayabot_status.php')
        .then(r => r.json())
        .then(data => {
            const estado = data.estado || 'desconectado';
            const labels = { conectado:'Conectado ✅', desconectado:'Desconectado ❌', qr_pendiente:'Escaneando QR… 📷', inicializando:'Iniciando… ⏳' };

            ['botDot','headerDot'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.className = `status-dot ${estado}`;
            });
            document.getElementById('botStatusText').textContent  = labels[estado] || estado;
            document.getElementById('headerStatus').textContent   = labels[estado] || estado;

            const info    = document.getElementById('botInfoPanel');
            const qrPanel = document.getElementById('botQrPanel');

            if (estado === 'conectado') {
                info.classList.remove('d-none');
                document.getElementById('botNumero').textContent = data.numero_telefono || '—';
                document.getElementById('botPing').textContent   = data.ultimo_ping    || '—';
                document.getElementById('botIP').textContent     = data.ip_vps         || '—';
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
        .catch(() => document.getElementById('botStatusText').textContent = 'Error al consultar');
}

function abrirPing() { new bootstrap.Modal(document.getElementById('modalPing')).show(); }

function enviarPing() {
    const numero  = document.getElementById('pingNumero').value.trim();
    const mensaje = document.getElementById('pingMensaje').value.trim();
    if (!numero || !mensaje) return Swal.fire('Faltan datos','Completa número y mensaje','warning');
    fetch('../gerencia/ajax/gestion_tareas_reuniones_pitayabot_reset.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ accion:'ping', numero, mensaje })
    }).then(r => r.json()).then(d => Swal.fire(d.success ? '✅ Enviado' : 'Error', d.message || '', d.success ? 'success' : 'error'));
}

function resetBot() {
    Swal.fire({ title:'¿Cambiar número?', text:'Se cerrará la sesión actual y se generará un QR nuevo.', icon:'warning', showCancelButton:true, confirmButtonText:'Sí, cambiar', cancelButtonText:'Cancelar' })
        .then(r => {
            if (!r.isConfirmed) return;
            fetch('../gerencia/ajax/gestion_tareas_reuniones_pitayabot_reset.php', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ accion:'reset' })
            }).then(r => r.json()).then(() => { Swal.fire('Listo','QR nuevo en breve.','success'); setTimeout(cargarStatus, 3000); });
        });
}

// ─── CRONS ───────────────────────────────────────────────────────
function cargarCrons() {
    fetch('ajax/pitayabot_admin_get_crons.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const c = document.getElementById('cronsContainer');
            if (!data.data.length) { c.innerHTML = '<p class="text-muted text-center py-3">Sin crons configurados</p>'; return; }

            const rows = data.data.map(cr => `
                <tr class="cron-row ${cr.activo == 1 ? '' : 'inactivo'}" id="cronRow_${cr.id}">
                    <td class="ps-3 py-2">
                        <div class="fw-semibold">${cr.nombre}</div>
                        <small class="text-muted">${cr.descripcion || ''}</small>
                    </td>
                    <td class="align-middle">
                        <code class="badge-horario badge bg-light text-dark border">${cr.horario}</code>
                    </td>
                    <td class="align-middle text-muted small">${cr.ultima_ejecucion || 'Nunca'}</td>
                    <td class="align-middle pe-3">
                        <div class="cron-actions">
                            <button class="btn btn-xs btn-outline-primary py-0 px-2"
                                    title="Probar ahora" onclick="dispararCron('${cr.clave}', '${cr.nombre}', this)">
                                <i class="bi bi-play-fill"></i> Probar
                            </button>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" ${cr.activo == 1 ? 'checked' : ''}
                                       onchange="toggleCron(${cr.id}, this.checked)" style="cursor:pointer;width:2.5em;height:1.3em;">
                            </div>
                        </div>
                    </td>
                </tr>`).join('');

            c.innerHTML = `<table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Recordatorio</th>
                        <th>Horario (cron)</th>
                        <th>Última ejecución</th>
                        <th class="pe-3 text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>`;
        })
        .catch(() => document.getElementById('cronsContainer').innerHTML = '<p class="text-danger text-center py-3">Error cargando crons</p>');
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

function dispararCron(clave, nombre, btn, ejecutar = false) {
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('ajax/pitayabot_admin_trigger_cron.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ clave, ejecutar })
    }).then(r => r.json()).then(d => {
        btn.disabled = false;
        btn.innerHTML = orig;

        if (!d.success) {
            const raw = d.raw ? `<br><pre style="font-size:.7rem;text-align:left;max-height:200px;overflow:auto;background:#f8f9fa;padding:8px;border-radius:4px;margin-top:8px">${d.raw}</pre>` : '';
            return Swal.fire({ title: '⚠️ Error', html: `<strong>${d.message || 'Error desconocido'}</strong>${raw}`, icon: 'warning', width: d.raw ? 700 : 500 });
        }

        if (ejecutar) {
            // Resultado de ejecución real
            Swal.fire({
                title: '✅ Envío Completado',
                text: `Se procesaron y enviaron ${d.mensajes} mensajes correctamente.`,
                icon: 'success'
            });
            cargarCrons(); // Refrescar para ver nueva fecha de ejecución
        } else {
            // Resultado de Test
            const msg = `El cron <strong>${nombre}</strong> se validó correctamente.<br>Destinatarios detectados: <strong>${d.mensajes}</strong>`;
            
            Swal.fire({
                title: '🔍 Resultado del Test',
                html: msg,
                icon: 'info',
                showCancelButton: d.mensajes > 0,
                confirmButtonText: d.mensajes > 0 ? '🚀 Confirmar Envío Real' : 'Cerrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                width: 500
            }).then(result => {
                if (result.isConfirmed && d.mensajes > 0) {
                    dispararCron(clave, nombre, btn, true);
                }
            });
        }

    }).catch(() => {
        btn.disabled = false;
        btn.innerHTML = orig;
        Swal.fire('Error', 'No se pudo conectar al servidor', 'error');
    });
}

// ─── INIT ────────────────────────────────────────────────────────
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
