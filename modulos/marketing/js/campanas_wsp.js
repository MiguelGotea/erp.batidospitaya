'use strict';

// ── Estado del wizard ──
let pasoActual = 1;
const TOTAL_PASOS = 3;
let clientesSeleccionados = [];   // [{id, nombre, telefono, sucursal}, ...]
let imagenBase64 = null;
let lastSearchId = 0; // Control de concurrencia para búsquedas

// ── Al cargar la página ──
document.addEventListener('DOMContentLoaded', () => {
    cargarCampanas(1);
    verificarStatusVPS();
    // Polling del estado del VPS cada 30 segundos
    setInterval(verificarStatusVPS, 30_000);
    // Polling de campañas activas cada 20s
    setInterval(() => cargarCampanas(paginaActual, false), 20_000);
});

// ════════════════════════════════════════════
//  STATUS DEL VPS
// ════════════════════════════════════════════

// Bloquea el polling automático mientras hay un reset en curso
let resetEnCurso = false;

async function verificarStatusVPS() {
    if (resetEnCurso) return; // no sobrescribir el badge de reset
    try {
        const resp = await fetch('ajax/campanas_wsp_get_status.php');
        const data = await resp.json();
        actualizarBadgeVPS(data.estado, data.numero);
    } catch {
        actualizarBadgeVPS('desconectado');
    }
}

function actualizarBadgeVPS(estado, numero = null) {
    const dot = document.getElementById('vpsDot');
    const texto = document.getElementById('vpsStatusTexto');
    dot.className = 'wsp-dot ' + estado;

    // Formatear número: 50588888888 → ☏ +505 8888-8888
    let numStr = '';
    if (estado === 'conectado' && numero) {
        const n = String(numero);
        if (n.startsWith('505') && n.length === 11) {
            numStr = ` (☏ +505 ${n.slice(3, 7)}-${n.slice(7)})`;
        } else {
            numStr = ` (☏ +${n})`;
        }
    }

    const textos = {
        conectado: `✅ WhatsApp Conectado${numStr}`,
        qr_pendiente: '📷 QR Pendiente — clic para escanear',
        desconectado: '🔴 Servicio Desconectado',
        reset_pendiente: '🔄 Pendiente de cambio de número...'
    };
    texto.textContent = textos[estado] || '⏳ Verificando...';
}

async function verificarQR() {
    try {
        const resp = await fetch('ajax/campanas_wsp_get_status.php');
        const data = await resp.json();
        if (data.estado === 'qr_pendiente' && data.qr) {
            const img = document.getElementById('qrImage');
            img.src = data.qr;
            img.classList.remove('d-none');
            document.getElementById('qrLoading').classList.add('d-none');
            new bootstrap.Modal(document.getElementById('modalQR')).show();
            // Revisar si ya se conectó
            const intervalo = setInterval(async () => {
                const r = await fetch('ajax/campanas_wsp_get_status.php');
                const d = await r.json();
                if (d.estado === 'conectado') {
                    clearInterval(intervalo);
                    bootstrap.Modal.getInstance(document.getElementById('modalQR')).hide();
                    Swal.fire({ icon: 'success', title: '¡Conectado!', text: 'WhatsApp vinculado correctamente.', timer: 2500 });
                    actualizarBadgeVPS('conectado');
                }
            }, 5000);
        } else if (data.estado === 'conectado') {
            Swal.fire({ icon: 'success', title: 'Conectado', text: 'El servicio WhatsApp está activo.', timer: 2000 });
        } else {
            Swal.fire({ icon: 'warning', title: 'Servicio inactivo', text: 'El VPS no está respondiendo. Verifica que el servicio esté corriendo.' });
        }
    } catch { /* ignorar */ }
}

// ════════════════════════════════════════════
//  TABLA DE CAMPAÑAS
// ════════════════════════════════════════════

let paginaActual = 1;

async function cargarCampanas(pagina = 1, mostrarLoader = true) {
    paginaActual = pagina;
    const rpp = document.getElementById('registrosPorPagina').value;
    const tbody = document.getElementById('cuerpoTablaCampanas');

    if (mostrarLoader) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Cargando...</td></tr>';

    try {
        const resp = await fetch(`ajax/campanas_wsp_get_datos.php?pagina=${pagina}&rpp=${rpp}`);
        const data = await resp.json();

        if (!data.campanas || data.campanas.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i> Sin campañas registradas</td></tr>';
            return;
        }

        tbody.innerHTML = data.campanas.map(c => {
            const pct = c.total_destinatarios > 0
                ? Math.round((c.total_enviados / c.total_destinatarios) * 100) : 0;
            const badgeCls = `badge-${c.estado}`;
            const estadoLabel = {
                borrador: 'Borrador', programada: 'Programada', enviando: 'Enviando',
                completada: 'Completada', fallida: 'Fallida', cancelada: 'Cancelada'
            }[c.estado] || c.estado;

            const acciones = PUEDE_ELIMINAR && ['borrador', 'cancelada'].includes(c.estado)
                ? `<button class="btn-accion btn-eliminar" onclick="eliminarCampana(${c.id})" title="Eliminar">
                       <i class="bi bi-trash"></i>
                   </button>` : '';

            return `<tr>
                <td><strong>${escHtml(c.nombre)}</strong></td>
                <td>${formatearFecha(c.fecha_envio)}</td>
                <td class="text-center">${c.total_destinatarios}</td>
                <td>
                    <div class="wsp-progreso">
                        <div class="progress" style="min-width:80px">
                            <div class="progress-bar bg-success" style="width:${pct}%"></div>
                        </div>
                        <small>${c.total_enviados}/${c.total_destinatarios}</small>
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge ${badgeCls}">${estadoLabel}</span>
                </td>
                <td class="text-center">${acciones}</td>
            </tr>`;
        }).join('');

        // Paginación
        renderPaginacion(data.total, rpp, pagina);

    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-3">Error al cargar campañas</td></tr>`;
    }
}

function renderPaginacion(total, rpp, actual) {
    const totalPags = Math.ceil(total / rpp);
    const cont = document.getElementById('paginacion');
    if (totalPags <= 1) { cont.innerHTML = ''; return; }
    let html = '<nav><ul class="pagination pagination-sm mb-0">';
    for (let i = 1; i <= totalPags; i++) {
        html += `<li class="page-item ${i === actual ? 'active' : ''}">
            <button class="page-link" onclick="cargarCampanas(${i})">${i}</button>
        </li>`;
    }
    html += '</ul></nav>';
    cont.innerHTML = html;
}

// ════════════════════════════════════════════
//  WIZARD — CREAR CAMPAÑA
// ════════════════════════════════════════════

function abrirModalNueva() {
    reiniciarWizard();
    new bootstrap.Modal(document.getElementById('modalNuevaCampana')).show();
}

function reiniciarWizard() {
    pasoActual = 1;
    clientesSeleccionados = [];
    imagenBase64 = null;
    document.getElementById('campNombre').value = '';
    document.getElementById('campMensaje').value = '';
    document.getElementById('campImagen').value = '';
    document.getElementById('previewBubble').innerHTML = '<em class="text-muted">El mensaje aparecerá aquí...</em>';
    document.getElementById('previewImagenContainer').classList.add('d-none');
    document.getElementById('contadorCaracteres').textContent = '0';
    actualizarUI_Wizard();
    cargarSucursalesDisponibles();
}

function pasoSiguiente() {
    if (!validarPaso(pasoActual)) return;
    pasoActual = Math.min(pasoActual + 1, TOTAL_PASOS);
    if (pasoActual === 3) actualizarResumen();
    actualizarUI_Wizard();
}
function pasoAnterior() {
    pasoActual = Math.max(pasoActual - 1, 1);
    actualizarUI_Wizard();
}

function validarPaso(paso) {
    if (paso === 1) {
        if (!document.getElementById('campNombre').value.trim()) {
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Ingresa el nombre de la campaña.' });
            return false;
        }
        if (!document.getElementById('campMensaje').value.trim()) {
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Escribe el mensaje de la campaña.' });
            return false;
        }
    }
    if (paso === 2) {
        if (clientesSeleccionados.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Sin destinatarios', text: 'Selecciona al menos un cliente.' });
            return false;
        }
    }
    return true;
}

function actualizarUI_Wizard() {
    // Mostrar/ocultar pasos
    for (let i = 1; i <= TOTAL_PASOS; i++) {
        document.getElementById(`wizardPaso${i}`).classList.toggle('d-none', i !== pasoActual);
        const ind = document.getElementById(`step-ind-${i}`);
        ind.classList.remove('active', 'done');
        if (i === pasoActual) ind.classList.add('active');
        if (i < pasoActual) ind.classList.add('done');
    }
    document.getElementById('wizardStepBadge').textContent = `Paso ${pasoActual} de ${TOTAL_PASOS}`;
    document.getElementById('btnAntes').style.display = pasoActual > 1 ? 'inline-flex' : 'none';
    document.getElementById('btnSiguiente').classList.toggle('d-none', pasoActual === TOTAL_PASOS);
    document.getElementById('btnGuardar').classList.toggle('d-none', pasoActual !== TOTAL_PASOS);
}

// ════════════════════════════════════════════
//  PREVIEW DEL MENSAJE
// ════════════════════════════════════════════

function actualizarPreview() {
    const texto = document.getElementById('campMensaje').value;
    const preview = texto
        .replace(/\{\{nombre\}\}/gi, '<strong>Juan Pérez</strong>')
        .replace(/\{\{sucursal\}\}/gi, '<em>Ciudad Jardín</em>');
    document.getElementById('previewBubble').innerHTML = preview || '<em class="text-muted">El mensaje aparecerá aquí...</em>';
    document.getElementById('contadorCaracteres').textContent = texto.length;
}

function insertarVariable(variable) {
    const ta = document.getElementById('campMensaje');
    const inicio = ta.selectionStart;
    const fin = ta.selectionEnd;
    ta.value = ta.value.substring(0, inicio) + variable + ta.value.substring(fin);
    ta.selectionStart = ta.selectionEnd = inicio + variable.length;
    ta.focus();
    actualizarPreview();
}

function previsualizarImagen(input) {
    const archivo = input.files[0];
    if (!archivo) return;
    const reader = new FileReader();
    reader.onload = e => {
        imagenBase64 = e.target.result;
        document.getElementById('previewImagen').src = imagenBase64;
        document.getElementById('previewImagenContainer').classList.remove('d-none');
    };
    reader.readAsDataURL(archivo);
}

// ════════════════════════════════════════════
//  SELECCIÓN DE CLIENTES (Paso 2)
// ════════════════════════════════════════════

async function cargarSucursalesDisponibles() {
    try {
        const resp = await fetch('/modulos/marketing/ajax/campanas_wsp_get_clientes.php?accion=sucursales');
        const data = await resp.json();
        const sel = document.getElementById('filtroSucursal');
        sel.innerHTML = '<option value="">— Todas —</option>' +
            (data.sucursales || []).map(s =>
                `<option value="${escHtml(s.nombre_sucursal)}">${escHtml(s.nombre_sucursal)}</option>`
            ).join('');
    } catch { /* ignorar */ }
}

let busquedaTimer = null;
function buscarClientes() {
    clearTimeout(busquedaTimer);
    busquedaTimer = setTimeout(_buscarClientes, 400);
}

async function _buscarClientes(silencioso = false) {
    const sucursal = document.getElementById('filtroSucursal').value;
    const busqueda = document.getElementById('buscarClienteInput').value.trim();
    const ultimaCompra = document.getElementById('filtroUltimaCompra').value;
    const lista = document.getElementById('listaDisponibles');

    // Control de concurrencia
    const currentSearchId = ++lastSearchId;

    if (!silencioso) {
        lista.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';
    }

    try {
        const params = new URLSearchParams({ accion: 'buscar', sucursal, q: busqueda, ultima_compra: ultimaCompra });
        const resp = await fetch(`/modulos/marketing/ajax/campanas_wsp_get_clientes.php?${params}`);
        const data = await resp.json();

        // Si llegó una búsqueda más reciente, ignorar esta
        if (currentSearchId !== lastSearchId) return;

        if (!data.clientes || data.clientes.length === 0) {
            document.getElementById('contDisponibles').textContent = 0;
            lista.innerHTML = '<div class="text-center text-muted py-3 small">Sin resultados</div>';
            return;
        }

        const items = data.clientes.filter(c => !clientesSeleccionados.some(s => Number(s.id) == Number(c.id)));
        document.getElementById('contDisponibles').textContent = items.length;

        lista.innerHTML = items.map(c => {
            const etiqueta = c.membresia
                ? `<span class="wsp-membresia">${escHtml(c.membresia)}</span> ${escHtml(c.nombre)}`
                : escHtml(c.nombre);
            return `<div class="wsp-cliente-item" onclick="agregarCliente(${c.id}, '${escHtml(c.nombre).replace(/'/g, "\\'")}', '${c.telefono}', '${escHtml(c.sucursal || '').replace(/'/g, "\\'")}', '${escHtml(String(c.membresia || '')).replace(/'/g, "\\'")}')">
                <div>
                    <div class="nombre">${etiqueta}</div>
                    <div class="telefono">${c.telefono}</div>
                </div>
                <button class="btn-accion-mini btn-agregar" title="Agregar">
                    <i class="bi bi-plus-circle-fill"></i>
                </button>
            </div>`;
        }).join('');

    } catch (e) {
        if (currentSearchId === lastSearchId) {
            lista.innerHTML = '<div class="text-muted text-center py-3 small">Error al buscar</div>';
            console.error(e);
        }
    }
}

function agregarCliente(id, nombre, telefono, sucursal, membresia = '', silencioso = true, noRefrescar = false) {
    if (clientesSeleccionados.some(c => Number(c.id) == Number(id))) return;
    clientesSeleccionados.push({ id, nombre, telefono, sucursal, membresia });
    renderListaSeleccionados();
    if (!noRefrescar) _buscarClientes(silencioso); // refrescar disponibles
}

function agregarTodosVisibles() {
    const items = document.querySelectorAll('#listaDisponibles .wsp-cliente-item');
    if (items.length === 0) return;

    items.forEach(item => {
        // Disparamos el click para cada uno. agregarCliente ya está optimizado para 
        // no refrescar individualmente si le pasamos el flag, o podemos simplemente
        // dejar que el debouncer/concurrencia lo maneje. 
        // Pero para cumplir con el pedido de "limpiar inmediatamente":
        item.click();
    });

    // Limpieza visual inmediata
    document.getElementById('listaDisponibles').innerHTML = '<div class="text-center text-muted py-3 small">Sin resultados</div>';
    document.getElementById('contDisponibles').textContent = 0;
}

function quitarCliente(id) {
    clientesSeleccionados = clientesSeleccionados.filter(c => Number(c.id) != Number(id));
    renderListaSeleccionados();
    _buscarClientes(true);
}

function limpiarSeleccion() {
    clientesSeleccionados = [];
    renderListaSeleccionados();
    _buscarClientes(true);
}

function renderListaSeleccionados() {
    const lista = document.getElementById('listaSeleccionados');
    document.getElementById('contSeleccionados').textContent = clientesSeleccionados.length;

    if (clientesSeleccionados.length === 0) {
        lista.innerHTML = '<div class="text-center text-muted py-3 small">Sin destinatarios aún</div>';
        return;
    }
    lista.innerHTML = clientesSeleccionados.map(c => {
        const etiqueta = c.membresia
            ? `<span class="wsp-membresia">${escHtml(c.membresia)}</span> ${escHtml(c.nombre)}`
            : escHtml(c.nombre);
        return `<div class="wsp-cliente-item">
            <div>
                <div class="nombre">${etiqueta}</div>
                <div class="telefono">${c.telefono}</div>
            </div>
            <button class="btn-accion-mini btn-quitar" title="Quitar" onclick="quitarCliente(${c.id})">
                <i class="bi bi-x-circle-fill"></i>
            </button>
        </div>`;
    }).join('');
}

// ════════════════════════════════════════════
//  RESUMEN Y ENVÍO
// ════════════════════════════════════════════

const GRUPO_SIZE = 100;  // destinatarios por sub-campaña
const GRUPO_HORAS = 2;    // horas entre cada grupo
const GRUPOS_POR_DIA = 3;    // grupos por día para evitar baneo (300 msgs/día)

function actualizarResumen() {
    document.getElementById('resNombre').textContent = document.getElementById('campNombre').value;
    document.getElementById('resDestinatarios').textContent = clientesSeleccionados.length;
    document.getElementById('resImagen').textContent = imagenBase64 ? 'Sí' : 'No';

    // Info de grupos
    const numGrupos = Math.ceil(clientesSeleccionados.length / GRUPO_SIZE);
    const necesitaGrupos = clientesSeleccionados.length > GRUPO_SIZE;
    const numDias = Math.ceil(numGrupos / GRUPOS_POR_DIA);

    document.getElementById('resGruposRow').classList.toggle('d-none', !necesitaGrupos);
    document.getElementById('alertaGrupos').classList.toggle('d-none', !necesitaGrupos);

    if (necesitaGrupos) {
        let textoGrupos = `${numGrupos} grupos de máx. ${GRUPO_SIZE} c/u`;
        if (numDias > 1) {
            textoGrupos += ` (distribuidos en ${numDias} días)`;
        }
        document.getElementById('resGrupos').textContent = textoGrupos;
        document.getElementById('alertaNumGrupos').textContent = numGrupos;

        const alertaText = document.getElementById('alertaGrupos');
        alertaText.innerHTML = `
            <i class="bi bi-layers me-1"></i>
            <strong>Campaña dividida en grupos:</strong>
            Se crearán <strong>${numGrupos}</strong> sub-campañas programadas con <strong>${GRUPO_HORAS} horas</strong> de diferencia.
            <br><i class="bi bi-calendar-event me-1"></i> <strong>Duración estimada:</strong> 
            La distribución tomará <strong>${numDias} días</strong> (máx. ${GRUPOS_POR_DIA * GRUPO_SIZE} mensajes/día).
        `;
    }

    // Fecha mínima = ahora + 5 min
    const ahora = new Date();
    ahora.setMinutes(ahora.getMinutes() + 5);
    document.getElementById('campFechaEnvio').min = ahora.toISOString().slice(0, 16);
    if (!document.getElementById('campFechaEnvio').value) {
        document.getElementById('campFechaEnvio').value = ahora.toISOString().slice(0, 16);
    }
}

async function guardarCampana() {
    const nombre = document.getElementById('campNombre').value.trim();
    const mensaje = document.getElementById('campMensaje').value.trim();
    const fecha = document.getElementById('campFechaEnvio').value;

    if (!fecha) {
        Swal.fire({ icon: 'warning', title: 'Fecha requerida', text: 'Selecciona la fecha y hora de envío.' });
        return;
    }

    const btnGuardar = document.getElementById('btnGuardar');
    btnGuardar.disabled = true;

    // Dividir destinatarios en grupos de GRUPO_SIZE
    const grupos = [];
    for (let i = 0; i < clientesSeleccionados.length; i += GRUPO_SIZE) {
        grupos.push(clientesSeleccionados.slice(i, i + GRUPO_SIZE));
    }

    const totalGrupos = grupos.length;
    const fechaBase = new Date(fecha);
    let errores = 0;

    try {
        for (let g = 0; g < totalGrupos; g++) {
            const esSolo = totalGrupos === 1;
            const nomGrupo = esSolo ? nombre : `${nombre} - Grupo ${g + 1}`;

            // Distribución multi-día: 3 grupos por día, cada uno con 2h de diferencia
            const diaOffset = Math.floor(g / GRUPOS_POR_DIA);
            const grupoEnDia = g % GRUPOS_POR_DIA;

            const fechaGrupo = new Date(fechaBase);
            fechaGrupo.setDate(fechaGrupo.getDate() + diaOffset);
            fechaGrupo.setHours(fechaGrupo.getHours() + (grupoEnDia * GRUPO_HORAS));

            const fechaStr = fechaGrupo.toISOString().slice(0, 16).replace('T', ' ');

            btnGuardar.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Guardando grupo ${g + 1}/${totalGrupos}...`;

            const resp = await fetch('/modulos/marketing/ajax/campanas_wsp_guardar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nombre: nomGrupo,
                    mensaje,
                    fecha_envio: fechaStr,
                    imagen_base64: imagenBase64 || null,
                    destinatarios: grupos[g]
                })
            });
            const data = await resp.json();
            if (!data.success) errores++;
        }

        bootstrap.Modal.getInstance(document.getElementById('modalNuevaCampana')).hide();

        if (errores === 0) {
            const msg = totalGrupos > 1
                ? `${clientesSeleccionados.length} destinatarios divididos en ${totalGrupos} grupos. El primero se enviará el ${formatearFecha(fecha)}.`
                : `${clientesSeleccionados.length} destinatarios. Se enviará el ${formatearFecha(fecha)}.`;
            Swal.fire({ icon: 'success', title: '¡Campaña programada!', text: msg, timer: 4000 });
        } else {
            Swal.fire({ icon: 'warning', title: 'Guardado parcial', text: `${errores} de ${totalGrupos} grupos no se guardaron correctamente.` });
        }
        cargarCampanas(1);

    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
    } finally {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="bi bi-check-circle me-1"></i> Guardar y Programar';
    }
}

async function eliminarCampana(id) {
    const conf = await Swal.fire({
        icon: 'warning', title: '¿Eliminar campaña?',
        text: 'Esta acción no se puede deshacer.',
        showCancelButton: true, confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
    });
    if (!conf.isConfirmed) return;

    try {
        const resp = await fetch('ajax/campanas_wsp_eliminar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await resp.json();
        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1500 });
            cargarCampanas(paginaActual);
        } else throw new Error(data.error);
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
    }
}

// ════════════════════════════════════════════
//  UTILIDADES
// ════════════════════════════════════════════

function escHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function formatearFecha(iso) {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleDateString('es-NI', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('es-NI', { hour: '2-digit', minute: '2-digit' });
}
