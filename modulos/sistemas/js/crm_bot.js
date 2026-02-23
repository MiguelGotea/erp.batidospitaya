'use strict';
/* ============================================================
   crm_bot.js — CRM Bot WhatsApp
   Funciones: chat, conversaciones, badge VPS, reset sesion
   ============================================================ */

// ── Estado global ──────────────────────────────────────────
let convActiva = null;  // { id, numero_cliente, status, instancia }
let mensajesPag = 1;
let pollingTimer = null;
let statusTimer = null;
let qrInterval = null;
let autoScrollOk = true;

// ── ARRANQUE ───────────────────────────────────────────────
$(document).ready(() => {
    cargarConversaciones();
    verificarStatusVPS();
    statusTimer = setInterval(verificarStatusVPS, 20_000);
    // Recargar lista cada 15s para badges de mensajes nuevos
    setInterval(cargarConversaciones, 15_000);
});

// ══════════════════════════════════════════════════════════
// BADGE VPS (igual que campanas_wsp)
// ══════════════════════════════════════════════════════════
async function verificarStatusVPS() {
    try {
        const instancia = $('#filtroInstancia').val() || 'wsp-crmbot';
        const resp = await fetch(`ajax/crm_bot_get_status.php?instancia=${instancia}`);
        const data = await resp.json();
        actualizarBadgeVPS(data.estado, data.numero);

        document.getElementById('btnVerQR').classList.toggle('d-none', data.estado !== 'qr_pendiente');
        if (data.estado === 'qr_pendiente') {
            if (!qrInterval) qrInterval = setInterval(verificarStatusVPS, 5_000);
        } else {
            clearInterval(qrInterval); qrInterval = null;
        }
    } catch (e) {
        actualizarBadgeVPS('desconectado', null);
    }
}

function actualizarBadgeVPS(estado, numero = null) {
    const dot = document.getElementById('vpsDot');
    const texto = document.getElementById('vpsStatusTexto');
    dot.className = 'wsp-dot ' + estado;

    let numStr = '';
    if (estado === 'conectado' && numero) {
        const n = String(numero);
        numStr = n.startsWith('505') && n.length === 11
            ? ` (☏ +505 ${n.slice(3, 7)}-${n.slice(7)})`
            : ` (☏ +${n})`;
    }

    const textos = {
        conectado: `✅ CRM Bot Conectado${numStr}`,
        qr_pendiente: '📷 QR Pendiente — clic Ver QR',
        desconectado: '🔴 Bot Desconectado',
        reset_pendiente: '🔄 Esperando reinicio...'
    };
    texto.textContent = textos[estado] || '⏳ Verificando...';
}

function mostrarModalQR() {
    const instancia = $('#filtroInstancia').val() || 'wsp-crmbot';
    fetch(`ajax/crm_bot_get_status.php?instancia=${instancia}`)
        .then(r => r.json())
        .then(data => {
            if (data.qr) {
                document.getElementById('qrImg').src = data.qr;
                new bootstrap.Modal(document.getElementById('modalQR')).show();
            } else {
                Swal.fire('Sin QR', 'No hay QR disponible actualmente.', 'info');
            }
        });
}

async function resetearSesion() {
    const conf = await Swal.fire({
        title: '¿Cambiar número?',
        text: 'La sesión actual se cerrará y aparecerá un QR para vincular un número nuevo.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });
    if (!conf.isConfirmed) return;

    actualizarBadgeVPS('reset_pendiente');
    try {
        const resp = await fetch('ajax/crm_bot_reset_sesion.php', { method: 'POST' });
        const data = await resp.json();
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Reset solicitado', text: data.mensaje, timer: 4000 });
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'No se pudo solicitar el reset.', 'error');
    }
}

// ══════════════════════════════════════════════════════════
// LISTA DE CONVERSACIONES
// ══════════════════════════════════════════════════════════
let _convTimer = null;
function cargarConversaciones() {
    clearTimeout(_convTimer);
    _convTimer = setTimeout(_fetchConversaciones, 200);
}

async function _fetchConversaciones() {
    const q = $('#filtroNumero').val().trim();
    const status = $('#filtroStatus').val();
    const instancia = $('#filtroInstancia').val();

    const params = new URLSearchParams({ q, status, instancia, per_page: 50 });
    const resp = await fetch(`ajax/crm_bot_get_conversaciones.php?${params}`);
    const data = await resp.json();

    const lista = document.getElementById('listaConversaciones');
    lista.innerHTML = '';

    if (!data.success || !data.conversaciones?.length) {
        lista.innerHTML = '<li class="crm-loading text-muted">Sin conversaciones</li>';
        return;
    }

    data.conversaciones.forEach(conv => {
        const li = document.createElement('li');
        li.className = 'crm-conv-item' + (convActiva?.id == conv.id ? ' activo' : '');
        li.onclick = () => abrirConversacion(conv);

        const esHumano = conv.status === 'humano';
        const sinLeer = parseInt(conv.mensajes_sin_leer) || 0;
        const preview = (conv.ultimo_mensaje || '...').substring(0, 50);
        const hora = conv.last_interaction_at
            ? new Date(conv.last_interaction_at).toLocaleTimeString('es-NI', { hour: '2-digit', minute: '2-digit' })
            : '';

        li.innerHTML = `
            <div class="crm-conv-numero">
                <span>
                    <span class="badge ${esHumano ? 'badge-humano' : 'badge-bot'} me-1" style="font-size:9px">
                        ${esHumano ? '👤' : '🤖'}
                    </span>
                    +${conv.numero_cliente}
                </span>
                <span class="crm-conv-time">
                    ${hora}
                    ${sinLeer > 0 ? `<span class="badge bg-danger ms-1">${sinLeer}</span>` : ''}
                </span>
            </div>
            <div class="crm-conv-preview">${preview}</div>
        `;
        lista.appendChild(li);
    });
}

// ══════════════════════════════════════════════════════════
// CHAT — abrir conversación
// ══════════════════════════════════════════════════════════
function abrirConversacion(conv) {
    convActiva = conv;
    mensajesPag = 1;

    // Marcar item activo
    document.querySelectorAll('.crm-conv-item').forEach(li => li.classList.remove('activo'));
    event?.currentTarget?.classList.add('activo');

    renderChatPanel(conv);
    cargarMensajes(conv.id, true);

    // Polling de mensajes cada 5s mientras esta conv está abierta
    clearInterval(pollingTimer);
    pollingTimer = setInterval(() => cargarMensajes(conv.id, false), 5_000);
}

function renderChatPanel(conv) {
    const esHumano = conv.status === 'humano';
    const puedeResp = CRM_PERMISOS.responder;
    const puedeCamb = CRM_PERMISOS.cambiarEstado;

    const chat = document.getElementById('panelChat');
    chat.innerHTML = `
        <div class="crm-chat-header">
            <div>
                <h6><i class="bi bi-telephone me-1"></i> +${conv.numero_cliente}</h6>
                <small class="opacity-75">
                    ${conv.instancia} •
                    <span id="statusBadge" class="badge ${esHumano ? 'bg-danger' : 'bg-success'}" style="font-size:10px">
                        ${esHumano ? '👤 Humano' : '🤖 Bot'}
                    </span>
                </small>
            </div>
            <div class="d-flex gap-2 align-items-center">
                ${puedeCamb ? `
                <button class="btn btn-sm btn-outline-light" id="btnTomarControl"
                        onclick="cambiarEstado(${conv.id}, '${esHumano ? 'bot' : 'humano'}')">
                    ${esHumano ? '🤖 Devolver a Bot' : '👤 Tomar Control'}
                </button>` : ''}
            </div>
        </div>

        <div class="crm-mensajes" id="listaMensajes">
            <div class="crm-loading"><i class="bi bi-arrow-repeat spin"></i> Cargando...</div>
        </div>

        ${puedeResp ? `
        <div class="crm-input-area" id="inputArea" ${!esHumano ? 'style="opacity:0.5;pointer-events:none" title="Toma el control para responder"' : ''}>
            <textarea id="txtRespuesta" rows="1" placeholder="Escribe tu respuesta..."
                      onkeydown="handleEnter(event)"></textarea>
            <button class="btn-enviar-crm" onclick="enviarManual()" title="Enviar">
                <i class="bi bi-send-fill"></i>
            </button>
        </div>` : ''}
    `;
}

// ══════════════════════════════════════════════════════════
// MENSAJES
// ══════════════════════════════════════════════════════════
let _ultimoMsgId = 0;

async function cargarMensajes(convId, reset = false) {
    const params = new URLSearchParams({ conversation_id: convId, per_page: 100 });
    const resp = await fetch(`ajax/crm_bot_get_mensajes.php?${params}`);
    const data = await resp.json();

    if (!data.success) return;

    // Actualizar datos de conversación activa
    if (data.conversacion) {
        convActiva = { ...convActiva, ...data.conversacion };
    }

    const lista = document.getElementById('listaMensajes');
    if (!lista) return;

    const mensajes = data.mensajes || [];
    if (!mensajes.length && reset) {
        lista.innerHTML = '<div class="text-center text-muted small pt-4">Sin mensajes aún</div>';
        return;
    }

    // Si es polling, solo agregar mensajes nuevos
    if (!reset && mensajes.length > 0) {
        const nuevoMax = mensajes[mensajes.length - 1]?.id;
        if (nuevoMax <= _ultimoMsgId) return; // nada nuevo
    }

    if (reset) lista.innerHTML = '';

    const fragment = document.createDocumentFragment();
    mensajes.forEach(msg => {
        if (msg.id <= _ultimoMsgId && !reset) return;
        fragment.appendChild(crearBurbuja(msg));
        _ultimoMsgId = Math.max(_ultimoMsgId, msg.id);
    });

    lista.appendChild(fragment);

    // Auto-scroll al fondo
    if (autoScrollOk) lista.scrollTop = lista.scrollHeight;
}

function crearBurbuja(msg) {
    const div = document.createElement('div');
    const claseDir = msg.direction === 'in'
        ? 'in'
        : `out-${msg.sender_type}`;
    div.className = `crm-burbuja ${claseDir}`;

    const labels = { bot: '🤖 Bot', agent: '👤 Agente', campaign: '📣 Campaña', user: '' };
    const hora = new Date(msg.created_at).toLocaleTimeString('es-NI', { hour: '2-digit', minute: '2-digit' });

    div.innerHTML = `
        ${msg.sender_type !== 'user' ? `<div class="crm-burbuja-label">${labels[msg.sender_type] || ''}</div>` : ''}
        ${escHtml(msg.message_text || '')}
        <div class="crm-burbuja-meta">${hora}</div>
    `;
    return div;
}

function escHtml(str) {
    return str
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\n/g, '<br>');
}

// ══════════════════════════════════════════════════════════
// ACCIONES
// ══════════════════════════════════════════════════════════
async function cambiarEstado(convId, nuevoStatus) {
    const resp = await fetch('ajax/crm_bot_cambiar_estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: convId, nuevo_status: nuevoStatus })
    });
    const data = await resp.json();
    if (data.success) {
        convActiva.status = nuevoStatus;
        renderChatPanel(convActiva);
        cargarMensajes(convId, true);
        cargarConversaciones();
    } else {
        Swal.fire('Error', data.error, 'error');
    }
}

async function enviarManual() {
    const txt = document.getElementById('txtRespuesta')?.value.trim();
    if (!txt || !convActiva) return;

    document.getElementById('txtRespuesta').value = '';

    const resp = await fetch('ajax/crm_bot_enviar_manual.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ conversation_id: convActiva.id, texto: txt })
    });
    const data = await resp.json();

    if (data.success) {
        cargarMensajes(convActiva.id, false);
        if (!data.enviado_via_vps) {
            Swal.fire({ icon: 'warning', title: 'Guardado', text: data.nota, timer: 3000 });
        }
    } else {
        Swal.fire('Error', data.error || 'No se pudo enviar', 'error');
    }
}

function handleEnter(e) {
    // Enter sin Shift = enviar; Enter + Shift = nueva línea
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        enviarManual();
    }
}
