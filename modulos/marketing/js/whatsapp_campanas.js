/**
 * WhatsApp Marketing - JavaScript
 * Batidos Pitaya ERP
 */

// Variables globales
let debounceTimer = null;
let paginaActualHistorial = 1;
let totalPaginasHistorial = 1;
let statusCheckInterval = null;

/**
 * Inicialización cuando el DOM está listo
 */
$(document).ready(function () {
    // Verificar estado inicial
    verificarEstado();

    // Cargar datos iniciales
    cargarCumpleanos();
    cargarCampanas();
    cargarPlantillas();

    // Configurar fechas por defecto para historial
    const hoy = new Date();
    const hace30dias = new Date(hoy.getTime() - 30 * 24 * 60 * 60 * 1000);
    $('#filtroFechaDesde').val(formatearFechaInput(hace30dias));
    $('#filtroFechaHasta').val(formatearFechaInput(hoy));

    cargarHistorial();

    // Verificar estado cada 30 segundos
    statusCheckInterval = setInterval(verificarEstado, 30000);

    // Evento para actualizar vista previa de plantilla
    $('#plantillaMensaje').on('input', actualizarVistaPrevia);

    // Evento para mostrar/ocultar campo sucursal
    $('#campanaSegmento').on('change', function () {
        if ($(this).val() === 'sucursal') {
            $('#grupSucursal').show();
        } else {
            $('#grupSucursal').hide();
        }
    });
});

/**
 * Formatear fecha para input date
 */
function formatearFechaInput(fecha) {
    return fecha.toISOString().split('T')[0];
}

/**
 * Formatear fecha para mostrar
 */
function formatearFecha(fechaStr) {
    if (!fechaStr) return '--';
    const fecha = new Date(fechaStr);
    return fecha.toLocaleDateString('es-NI', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// ============================================
// FUNCIONES DE ESTADO Y CONEXIÓN
// ============================================

/**
 * Verificar estado del servidor WhatsApp
 */
async function verificarEstado() {
    try {
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_get_estado.php');
        const data = await response.json();

        actualizarUIEstado(data);

    } catch (error) {
        console.error('Error verificando estado:', error);
        actualizarUIEstado({
            success: false,
            conexion: 'error',
            mensaje: 'Error de conexión con el servidor'
        });
    }
}

/**
 * Actualizar UI con el estado
 */
function actualizarUIEstado(data) {
    const indicator = $('#statusIndicator');
    const dot = indicator.find('.status-dot');
    const text = indicator.find('.status-text');
    const qrContainer = $('#qrContainer');

    // Limpiar clases
    dot.removeClass('connected disconnected waiting');

    if (!data.success) {
        dot.addClass('disconnected');
        text.text(data.mensaje || 'Error de conexión');
        qrContainer.hide();
        return;
    }

    switch (data.conexion) {
        case 'ready':
            dot.addClass('connected');
            text.text('Conectado y listo');
            qrContainer.hide();
            break;

        case 'waiting_qr':
            dot.addClass('waiting');
            text.text('Esperando escanear QR');
            if (data.qr) {
                $('#qrImage').attr('src', data.qr);
                qrContainer.show();
            }
            break;

        case 'authenticated':
            dot.addClass('waiting');
            text.text('Autenticado, iniciando...');
            qrContainer.hide();
            break;

        default:
            dot.addClass('disconnected');
            text.text('Desconectado');
            qrContainer.hide();
    }

    // Actualizar estadísticas
    if (data.stats) {
        $('#statMensajesHoy').text(data.stats.messagesSentToday || 0);
        $('#statEnCola').text(data.stats.queueWaiting || 0);
        $('#statLimiteHora').text(`${data.stats.messagesSentThisHour || 0}/50`);
    }
}

/**
 * Reiniciar conexión WhatsApp
 */
async function reiniciarConexion() {
    const result = await Swal.fire({
        title: '¿Reiniciar conexión?',
        text: 'Esto cerrará la sesión actual de WhatsApp',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, reiniciar',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;

    try {
        Swal.fire({
            title: 'Reiniciando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_reiniciar.php', {
            method: 'POST'
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Reiniciado',
                text: 'Espera unos segundos y escanea el QR',
                timer: 3000
            });
            setTimeout(verificarEstado, 3000);
        } else {
            throw new Error(data.error || 'Error al reiniciar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

/**
 * Abrir modal de configuración
 */
function abrirConfiguracion() {
    $('#modalConfig').modal('show');
}

/**
 * Probar conexión con el servidor
 */
async function probarConexion() {
    const url = $('#configUrl').val();
    const token = $('#configToken').val();

    if (!url || !token) {
        Swal.fire({
            icon: 'warning',
            title: 'Datos incompletos',
            text: 'Ingresa la URL y el token'
        });
        return;
    }

    try {
        Swal.fire({
            title: 'Probando conexión...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_probar_conexion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url, token })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Conexión exitosa!',
                text: 'El servidor responde correctamente'
            });
        } else {
            throw new Error(data.error || 'No se pudo conectar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: error.message
        });
    }
}

/**
 * Guardar configuración
 */
async function guardarConfiguracion() {
    const url = $('#configUrl').val();
    const token = $('#configToken').val();

    if (!url || !token) {
        Swal.fire({
            icon: 'warning',
            title: 'Datos incompletos',
            text: 'Ingresa la URL y el token'
        });
        return;
    }

    try {
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_guardar_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url, token })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Guardado',
                text: 'Configuración actualizada correctamente'
            });
            $('#modalConfig').modal('hide');
            verificarEstado();
        } else {
            throw new Error(data.error || 'Error al guardar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

/**
 * Toggle visibilidad de contraseña
 */
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// ============================================
// FUNCIONES DE CUMPLEAÑOS
// ============================================

/**
 * Cargar lista de cumpleañeros
 */
async function cargarCumpleanos() {
    const periodo = $('#filtroPeriodoCumple').val();
    const sucursal = $('#filtroSucursalCumple').val();
    const estado = $('#filtroEstadoCumple').val();

    $('#tbodyCumpleanos').html(`
        <tr>
            <td colspan="8" class="text-center">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i> Cargando cumpleañeros...
                </div>
            </td>
        </tr>
    `);

    try {
        const params = new URLSearchParams({ periodo, sucursal, estado });
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_get_cumpleanos.php?' + params);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Error al cargar');
        }

        renderizarCumpleanos(data.cumpleanos);
        $('#totalCumpleanos').text(data.total);

    } catch (error) {
        $('#tbodyCumpleanos').html(`
            <tr>
                <td colspan="8" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle"></i> ${error.message}
                </td>
            </tr>
        `);
    }
}

/**
 * Renderizar tabla de cumpleañeros
 */
function renderizarCumpleanos(cumpleanos) {
    if (!cumpleanos || cumpleanos.length === 0) {
        $('#tbodyCumpleanos').html(`
            <tr>
                <td colspan="8" class="text-center text-muted">
                    <i class="fas fa-birthday-cake"></i> No hay cumpleañeros para el período seleccionado
                </td>
            </tr>
        `);
        return;
    }

    let html = '';
    cumpleanos.forEach(c => {
        const estadoClass = c.estado_envio ? 'estado-enviado' : 'estado-pendiente';
        const estadoTexto = c.estado_envio ? 'Enviado' : 'Pendiente';

        html += `
            <tr data-id="${c.id_clienteclub}">
                <td class="col-check">
                    <input type="checkbox" class="check-cumple" value="${c.id_clienteclub}"
                           ${c.estado_envio ? 'disabled' : ''}>
                </td>
                <td><strong>${escapeHtml(c.nombre)} ${escapeHtml(c.apellido || '')}</strong></td>
                <td>${escapeHtml(c.celular)}</td>
                <td>${escapeHtml(c.nombre_sucursal || '--')}</td>
                <td>${c.fecha_nacimiento}</td>
                <td>${c.edad} años</td>
                <td><span class="estado-badge ${estadoClass}">${estadoTexto}</span></td>
                <td>
                    ${!c.estado_envio && CONFIG.puedeEnviar ? `
                        <button type="button" class="btn-accion btn-accion-wa" 
                                onclick="enviarIndividual(${c.id_clienteclub})" title="Enviar WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                    ` : ''}
                    <button type="button" class="btn-accion btn-accion-ver" 
                            onclick="verCliente(${c.id_clienteclub})" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    $('#tbodyCumpleanos').html(html);
}

/**
 * Toggle seleccionar todos los cumpleañeros
 */
function toggleAllCumple(checkbox) {
    $('.check-cumple:not(:disabled)').prop('checked', checkbox.checked);
}

/**
 * Enviar felicitación individual
 */
async function enviarIndividual(clienteId) {
    const plantillaId = $('#plantillaCumple').val();

    if (!plantillaId) {
        Swal.fire({
            icon: 'warning',
            title: 'Selecciona una plantilla',
            text: 'Debes seleccionar una plantilla de mensaje'
        });
        return;
    }

    const confirm = await Swal.fire({
        title: '¿Enviar felicitación?',
        text: 'Se enviará el mensaje de cumpleaños a este cliente',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#25D366',
        confirmButtonText: '<i class="fab fa-whatsapp"></i> Enviar',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {
        Swal.fire({
            title: 'Enviando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_enviar_cumpleanos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                clientes: [clienteId],
                plantilla_id: plantillaId
            })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Enviado!',
                text: 'El mensaje fue agregado a la cola de envío',
                timer: 2000
            });
            cargarCumpleanos();
            verificarEstado();
        } else {
            throw new Error(data.error || 'Error al enviar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

/**
 * Enviar felicitaciones a todos los cumpleañeros del día
 */
async function enviarCumpleanosHoy() {
    const plantillaId = $('#plantillaCumple').val();

    if (!plantillaId) {
        Swal.fire({
            icon: 'warning',
            title: 'Selecciona una plantilla',
            text: 'Debes seleccionar una plantilla de mensaje'
        });
        return;
    }

    // Obtener IDs seleccionados
    let clientesIds = [];
    $('.check-cumple:checked').each(function () {
        clientesIds.push($(this).val());
    });

    if (clientesIds.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin selección',
            text: 'Selecciona al menos un cliente para enviar'
        });
        return;
    }

    const confirm = await Swal.fire({
        title: `¿Enviar a ${clientesIds.length} clientes?`,
        html: `
            Se enviarán felicitaciones de cumpleaños a los clientes seleccionados.<br><br>
            <small class="text-muted">Los mensajes se agregarán a la cola y se enviarán respetando los límites anti-baneo.</small>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#25D366',
        confirmButtonText: '<i class="fab fa-whatsapp"></i> Enviar a todos',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {
        Swal.fire({
            title: 'Procesando...',
            text: 'Agregando mensajes a la cola',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_enviar_cumpleanos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                clientes: clientesIds,
                plantilla_id: plantillaId
            })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Listo!',
                html: `
                    <p>${data.agregados} mensajes agregados a la cola</p>
                    <small class="text-muted">Tiempo estimado: ~${data.tiempoEstimado} minutos</small>
                `
            });
            cargarCumpleanos();
            verificarEstado();
        } else {
            throw new Error(data.error || 'Error al procesar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

// ============================================
// FUNCIONES DE CAMPAÑAS
// ============================================

/**
 * Cargar lista de campañas
 */
async function cargarCampanas() {
    const estado = $('#filtroEstadoCampana').val();
    const tipo = $('#filtroTipoCampana').val();

    try {
        const params = new URLSearchParams({ estado, tipo });
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_get_campanas.php?' + params);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Error al cargar');
        }

        renderizarCampanas(data.campanas);

    } catch (error) {
        $('#tbodyCampanas').html(`
            <tr>
                <td colspan="9" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle"></i> ${error.message}
                </td>
            </tr>
        `);
    }
}

/**
 * Renderizar tabla de campañas
 */
function renderizarCampanas(campanas) {
    if (!campanas || campanas.length === 0) {
        $('#tbodyCampanas').html(`
            <tr>
                <td colspan="9" class="text-center text-muted">
                    <i class="fas fa-bullhorn"></i> No hay campañas registradas
                </td>
            </tr>
        `);
        return;
    }

    let html = '';
    campanas.forEach(c => {
        const estadoClass = `estado-${c.estado}`;

        html += `
            <tr>
                <td>${c.id}</td>
                <td><strong>${escapeHtml(c.nombre)}</strong></td>
                <td><span class="badge badge-secondary">${c.tipo}</span></td>
                <td><span class="estado-badge ${estadoClass}">${c.estado}</span></td>
                <td>${c.total_destinatarios}</td>
                <td class="text-success">${c.total_enviados}</td>
                <td class="text-danger">${c.total_fallidos}</td>
                <td>${formatearFecha(c.fecha_creacion)}</td>
                <td>
                    <button type="button" class="btn-accion btn-accion-ver" 
                            onclick="verCampana(${c.id})" title="Ver detalles">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${c.estado === 'borrador' && CONFIG.puedeEditar ? `
                        <button type="button" class="btn-accion btn-accion-editar" 
                                onclick="editarCampana(${c.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                    ` : ''}
                    ${c.estado === 'borrador' && CONFIG.puedeEnviar ? `
                        <button type="button" class="btn-accion btn-accion-wa" 
                                onclick="ejecutarCampana(${c.id})" title="Iniciar envío">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                    ` : ''}
                    ${(c.estado === 'en_proceso' || c.estado === 'programada') && CONFIG.puedeEnviar ? `
                        <button type="button" class="btn-accion btn-accion-eliminar" 
                                onclick="pausarCampana(${c.id})" title="Pausar">
                            <i class="fas fa-pause"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
    });

    $('#tbodyCampanas').html(html);
}

/**
 * Abrir modal para nueva campaña
 */
function abrirModalCampana(id = null) {
    $('#formCampana')[0].reset();
    $('#campanaId').val('');
    $('#grupSucursal').hide();
    $('#conteoDestinatarios span').text('0');

    if (id) {
        $('#modalCampanaTitle').text('Editar Campaña');
        cargarDatosCampana(id);
    } else {
        $('#modalCampanaTitle').text('Nueva Campaña');
    }

    $('#modalCampana').modal('show');
}

/**
 * Actualizar conteo de destinatarios
 */
async function actualizarConteoDestinatarios() {
    const segmento = $('#campanaSegmento').val();
    const sucursal = $('#campanaSucursal').val();

    try {
        const params = new URLSearchParams({ segmento, sucursal });
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_contar_destinatarios.php?' + params);
        const data = await response.json();

        $('#conteoDestinatarios span').text(data.total || 0);

    } catch (error) {
        console.error('Error contando destinatarios:', error);
    }
}

/**
 * Guardar campaña
 */
async function guardarCampana(enviarDespues = false) {
    const form = $('#formCampana');

    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return;
    }

    try {
        const formData = new FormData(form[0]);
        formData.append('segmento', $('#campanaSegmento').val());
        formData.append('sucursal', $('#campanaSucursal').val());
        formData.append('enviar_ahora', enviarDespues ? '1' : '0');

        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_guardar_campana.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Guardado',
                text: enviarDespues ? 'Campaña guardada e iniciada' : 'Campaña guardada correctamente',
                timer: 2000
            });
            $('#modalCampana').modal('hide');
            cargarCampanas();
        } else {
            throw new Error(data.error || 'Error al guardar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

/**
 * Guardar y enviar campaña
 */
function guardarYEnviarCampana() {
    guardarCampana(true);
}

// ============================================
// FUNCIONES DE PLANTILLAS
// ============================================

/**
 * Cargar plantillas
 */
async function cargarPlantillas() {
    try {
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_get_plantillas.php');
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Error al cargar');
        }

        renderizarPlantillas(data.plantillas);

    } catch (error) {
        $('#gridPlantillas').html(`
            <div class="text-center text-danger">
                <i class="fas fa-exclamation-triangle"></i> ${error.message}
            </div>
        `);
    }
}

/**
 * Renderizar grid de plantillas
 */
function renderizarPlantillas(plantillas) {
    if (!plantillas || plantillas.length === 0) {
        $('#gridPlantillas').html(`
            <div class="text-center text-muted" style="grid-column: 1/-1; padding: 40px;">
                <i class="fas fa-file-alt fa-3x mb-3"></i>
                <p>No hay plantillas creadas</p>
                ${CONFIG.puedeCrear ? '<button class="btn btn-success" onclick="abrirModalPlantilla()"><i class="fas fa-plus"></i> Crear primera plantilla</button>' : ''}
            </div>
        `);
        return;
    }

    let html = '';
    plantillas.forEach(p => {
        const mensajeCorto = p.mensaje.length > 150 ? p.mensaje.substring(0, 150) + '...' : p.mensaje;

        html += `
            <div class="plantilla-card ${!p.activa ? 'opacity-50' : ''}">
                <div class="plantilla-card-header">
                    <h5>${escapeHtml(p.nombre)}</h5>
                    <span class="plantilla-tipo">${p.tipo}</span>
                </div>
                <div class="plantilla-card-body">
                    <div class="plantilla-mensaje">${escapeHtml(mensajeCorto)}</div>
                </div>
                <div class="plantilla-card-footer">
                    <span class="plantilla-usos"><i class="fas fa-paper-plane"></i> ${p.uso_count} usos</span>
                    <div>
                        ${CONFIG.puedeEditar ? `
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="editarPlantilla(${p.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${CONFIG.puedeEliminar ? `
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="eliminarPlantilla(${p.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });

    $('#gridPlantillas').html(html);
}

/**
 * Abrir modal para nueva plantilla
 */
function abrirModalPlantilla(id = null) {
    $('#formPlantilla')[0].reset();
    $('#plantillaId').val('');
    $('#previewMensaje').text('El mensaje aparecerá aquí...');

    if (id) {
        $('#modalPlantillaTitle').text('Editar Plantilla');
        cargarDatosPlantilla(id);
    } else {
        $('#modalPlantillaTitle').text('Nueva Plantilla');
    }

    $('#modalPlantilla').modal('show');
}

/**
 * Cargar datos de plantilla para editar
 */
async function cargarDatosPlantilla(id) {
    try {
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_get_plantilla.php?id=' + id);
        const data = await response.json();

        if (data.success) {
            $('#plantillaId').val(data.plantilla.id);
            $('#plantillaNombre').val(data.plantilla.nombre);
            $('#plantillaTipo').val(data.plantilla.tipo);
            $('#plantillaMensaje').val(data.plantilla.mensaje);
            $('#plantillaImagen').val(data.plantilla.imagen_url || '');
            $('#plantillaActiva').val(data.plantilla.activa);
            actualizarVistaPrevia();
        }

    } catch (error) {
        console.error('Error cargando plantilla:', error);
    }
}

/**
 * Actualizar vista previa del mensaje
 */
function actualizarVistaPrevia() {
    let mensaje = $('#plantillaMensaje').val();

    // Reemplazar variables de ejemplo
    mensaje = mensaje
        .replace(/{nombre}/gi, 'Juan')
        .replace(/{apellido}/gi, 'Pérez')
        .replace(/{sucursal}/gi, 'Sucursal Centro')
        .replace(/{puntos}/gi, '150');

    $('#previewMensaje').text(mensaje || 'El mensaje aparecerá aquí...');
}

/**
 * Guardar plantilla
 */
async function guardarPlantilla() {
    const form = $('#formPlantilla');

    if (!form[0].checkValidity()) {
        form[0].reportValidity();
        return;
    }

    try {
        const formData = new FormData(form[0]);

        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_guardar_plantilla.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Guardado',
                text: 'Plantilla guardada correctamente',
                timer: 2000
            });
            $('#modalPlantilla').modal('hide');
            cargarPlantillas();
        } else {
            throw new Error(data.error || 'Error al guardar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

/**
 * Editar plantilla
 */
function editarPlantilla(id) {
    abrirModalPlantilla(id);
}

/**
 * Eliminar plantilla
 */
async function eliminarPlantilla(id) {
    const confirm = await Swal.fire({
        title: '¿Eliminar plantilla?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_eliminar_plantilla.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Eliminado',
                timer: 1500
            });
            cargarPlantillas();
        } else {
            throw new Error(data.error || 'Error al eliminar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

/**
 * Previsualizar plantilla desde select
 */
async function previsualizarPlantilla(selectId) {
    const plantillaId = $('#' + selectId).val();

    if (!plantillaId) {
        Swal.fire({
            icon: 'info',
            title: 'Sin plantilla',
            text: 'Selecciona una plantilla para ver la vista previa'
        });
        return;
    }

    try {
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_get_plantilla.php?id=' + plantillaId);
        const data = await response.json();

        if (data.success) {
            let mensaje = data.plantilla.mensaje
                .replace(/{nombre}/gi, '<strong>Juan</strong>')
                .replace(/{apellido}/gi, '<strong>Pérez</strong>')
                .replace(/{sucursal}/gi, '<strong>Sucursal Centro</strong>')
                .replace(/{puntos}/gi, '<strong>150</strong>')
                .replace(/\n/g, '<br>');

            Swal.fire({
                title: data.plantilla.nombre,
                html: `
                    <div style="background:#E5DDD5; padding:20px; border-radius:10px; text-align:left;">
                        <div style="background:white; padding:15px; border-radius:8px; line-height:1.6;">
                            ${mensaje}
                        </div>
                    </div>
                `,
                width: 400
            });
        }

    } catch (error) {
        console.error('Error:', error);
    }
}

// ============================================
// FUNCIONES DE HISTORIAL
// ============================================

/**
 * Cargar historial con debounce
 */
function debounceCargarHistorial() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(cargarHistorial, 500);
}

/**
 * Cargar historial de mensajes
 */
async function cargarHistorial(pagina = 1) {
    const desde = $('#filtroFechaDesde').val();
    const hasta = $('#filtroFechaHasta').val();
    const estado = $('#filtroEstadoHistorial').val();
    const texto = $('#filtroTextoHistorial').val();

    paginaActualHistorial = pagina;

    try {
        const params = new URLSearchParams({ desde, hasta, estado, texto, pagina, limite: 20 });
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_get_historial.php?' + params);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Error al cargar');
        }

        renderizarHistorial(data.mensajes);
        renderizarPaginacion(data.total, data.pagina, data.totalPaginas);

    } catch (error) {
        $('#tbodyHistorial').html(`
            <tr>
                <td colspan="6" class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle"></i> ${error.message}
                </td>
            </tr>
        `);
    }
}

/**
 * Renderizar tabla de historial
 */
function renderizarHistorial(mensajes) {
    if (!mensajes || mensajes.length === 0) {
        $('#tbodyHistorial').html(`
            <tr>
                <td colspan="6" class="text-center text-muted">
                    <i class="fas fa-inbox"></i> No hay mensajes en el historial
                </td>
            </tr>
        `);
        return;
    }

    let html = '';
    mensajes.forEach(m => {
        const estadoClass = `estado-${m.estado}`;

        html += `
            <tr>
                <td>${formatearFecha(m.fecha_envio || m.fecha_creacion)}</td>
                <td>${escapeHtml(m.nombre_cliente || '--')}</td>
                <td>${escapeHtml(m.telefono)}</td>
                <td>${m.campana_nombre || '<span class="text-muted">Individual</span>'}</td>
                <td><span class="estado-badge ${estadoClass}">${m.estado}</span></td>
                <td>
                    <button type="button" class="btn-accion btn-accion-ver" 
                            onclick="verMensaje(${m.id})" title="Ver mensaje">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    $('#tbodyHistorial').html(html);
}

/**
 * Renderizar paginación
 */
function renderizarPaginacion(total, pagina, totalPaginas) {
    totalPaginasHistorial = totalPaginas;

    let html = '';

    // Botón anterior
    html += `<button onclick="cargarHistorial(${pagina - 1})" ${pagina <= 1 ? 'disabled' : ''}>
        <i class="fas fa-chevron-left"></i>
    </button>`;

    // Páginas
    const inicio = Math.max(1, pagina - 2);
    const fin = Math.min(totalPaginas, pagina + 2);

    for (let i = inicio; i <= fin; i++) {
        html += `<button onclick="cargarHistorial(${i})" class="${i === pagina ? 'active' : ''}">${i}</button>`;
    }

    // Botón siguiente
    html += `<button onclick="cargarHistorial(${pagina + 1})" ${pagina >= totalPaginas ? 'disabled' : ''}>
        <i class="fas fa-chevron-right"></i>
    </button>`;

    html += `<span style="margin-left:15px; color:#666;">${total} registros</span>`;

    $('#paginacionHistorial').html(html);
}

/**
 * Ver detalle de mensaje
 */
async function verMensaje(id) {
    try {
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_get_mensaje.php?id=' + id);
        const data = await response.json();

        if (data.success) {
            const m = data.mensaje;

            $('#contenidoMensaje').html(`
                <div class="mb-3">
                    <strong>Destinatario:</strong> ${escapeHtml(m.nombre_cliente || '--')}<br>
                    <strong>Teléfono:</strong> ${escapeHtml(m.telefono)}<br>
                    <strong>Estado:</strong> <span class="estado-badge estado-${m.estado}">${m.estado}</span><br>
                    <strong>Fecha:</strong> ${formatearFecha(m.fecha_envio || m.fecha_creacion)}
                </div>
                <hr>
                <div class="preview-phone">
                    <div class="preview-message">${escapeHtml(m.mensaje).replace(/\n/g, '<br>')}</div>
                </div>
                ${m.error_mensaje ? `<div class="alert alert-danger mt-3"><strong>Error:</strong> ${escapeHtml(m.error_mensaje)}</div>` : ''}
            `);

            $('#modalVerMensaje').modal('show');
        }

    } catch (error) {
        console.error('Error:', error);
    }
}

/**
 * Exportar historial a Excel
 */
function exportarHistorial() {
    const desde = $('#filtroFechaDesde').val();
    const hasta = $('#filtroFechaHasta').val();
    const estado = $('#filtroEstadoHistorial').val();

    const params = new URLSearchParams({ desde, hasta, estado, exportar: 1 });
    window.open(CONFIG.ajaxBase + 'whatsapp_exportar_historial.php?' + params, '_blank');
}

// ============================================
// UTILIDADES
// ============================================

/**
 * Escapar HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Ver detalle de cliente
 */
function verCliente(id) {
    // Implementar según necesidad
    console.log('Ver cliente:', id);
}

/**
 * Ver detalle de campaña
 */
function verCampana(id) {
    // Implementar según necesidad
    console.log('Ver campaña:', id);
}

/**
 * Editar campaña
 */
function editarCampana(id) {
    abrirModalCampana(id);
}

/**
 * Ejecutar campaña
 */
async function ejecutarCampana(id) {
    const confirm = await Swal.fire({
        title: '¿Iniciar campaña?',
        text: 'Los mensajes se agregarán a la cola de envío',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#25D366',
        confirmButtonText: '<i class="fab fa-whatsapp"></i> Iniciar',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {
        Swal.fire({
            title: 'Iniciando campaña...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_ejecutar_campana.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Campaña iniciada!',
                html: `
                    <p>${data.mensajes_agregados} mensajes en cola</p>
                    <small class="text-muted">Tiempo estimado: ~${data.tiempo_estimado} minutos</small>
                `
            });
            cargarCampanas();
            verificarEstado();
        } else {
            throw new Error(data.error || 'Error al iniciar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}

/**
 * Pausar campaña
 */
async function pausarCampana(id) {
    const confirm = await Swal.fire({
        title: '¿Pausar campaña?',
        text: 'Los mensajes pendientes se detendrán',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, pausar',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {
        const response = await fetch(CONFIG.ajaxBase + 'whatsapp_pausar_campana.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Campaña pausada',
                timer: 1500
            });
            cargarCampanas();
        } else {
            throw new Error(data.error || 'Error al pausar');
        }

    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message
        });
    }
}