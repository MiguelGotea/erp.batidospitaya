// historial_informes.js

let paginaActual = 1;
let registrosPorPagina = 25;
let filtrosActivos = {};
let ordenActivo = { columna: 'fecha', direccion: 'desc' };
let panelFiltroAbierto = null;
let totalRegistros = 0;
let activeStream = null;
let currentCameraTarget = null;

$(document).ready(function () {
    cargarDatos();

    // Eventos de cierre de filtros
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-panel, .filter-icon, .daterange-calendar-day, .daterange-month-selector').length) {
            cerrarTodosFiltros();
        }
    });


    $(window).on('scroll resize', function () {
        if (panelFiltroAbierto) cerrarTodosFiltros();
    });
});

function cargarDatos() {
    Swal.fire({
        title: 'Cargando informes...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    $.ajax({
        url: 'ajax/historial_informes_get_data.php',
        method: 'POST',
        data: {
            pagina: paginaActual,
            registros_por_pagina: registrosPorPagina,
            filtros: JSON.stringify(filtrosActivos),
            orden: JSON.stringify(ordenActivo)
        },
        dataType: 'json',
        success: function (response) {
            Swal.close();
            if (response.success) {
                totalRegistros = response.total_registros;
                renderizarTabla(response.datos);
                renderizarPaginacion(response.total_registros);
                actualizarIndicadoresFiltros();
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function () {
            Swal.close();
            Swal.fire('Error', 'No se pudieron cargar los datos', 'error');
        }
    });
}

function renderizarTabla(datos) {
    const tbody = $('#tablaInformesBody');
    tbody.empty();

    if (datos.length === 0) {
        tbody.append('<tr><td colspan="6" class="text-center py-5 text-muted">No se encontraron reportes registrados</td></tr>');
        return;
    }

    datos.forEach(i => {
        const kmRecorrido = i.km_final ? (i.km_final - i.km_inicial).toFixed(2) : 'En proceso...';
        const badgeColor = i.estado === 'finalizado' ? 'success' : 'primary';
        const labelEstado = i.estado === 'finalizado' ? 'Finalizado' : 'Abierto';

        let accionesHtml = `
            <div class="d-flex justify-content-center gap-2">
                <a href="imprimir_informe.php?id=${i.id}" target="_blank" class="btn-action bg-dark bg-opacity-10 text-dark" title="Ver/Imprimir">
                    <i class="fas fa-print"></i>
                </a>
        `;

        if (i.estado === 'creado' && (i.cod_operario == actualUserId || puedeVerTodos)) {
            accionesHtml += `
                <a href="agenda_colaborador.php?fecha=${i.fecha}&colaborador=${i.cod_operario}" class="btn-action bg-primary bg-opacity-10 text-primary" title="Continuar Reporte">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            `;
        } else if (i.estado === 'finalizado' && puedeGenerarReembolso) {
            accionesHtml += `
                <a href="agenda_colaborador.php?fecha=${i.fecha}&colaborador=${i.cod_operario}" class="btn-action bg-success bg-opacity-10 text-success" title="Visualizar para Reembolso">
                    <i class="fas fa-search-dollar"></i>
                </a>
            `;
        }
        accionesHtml += `</div>`;

        const row = `
            <tr class="align-middle">
                <td class="ps-4">
                    <span class="fw-bold">${formatearFecha(i.fecha)}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-sm bg-primary bg-opacity-10 text-primary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            ${i.Nombre.charAt(0)}
                        </div>
                        <span>${i.Nombre} ${i.Apellido}</span>
                    </div>
                </td>
                <td>
                    <span class="badge bg-light text-dark border">
                        ${kmRecorrido} ${i.km_final ? 'KM' : ''}
                    </span>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-success">C$${parseFloat(i.monto_caja_chica).toFixed(2)}</span>
                        ${i.foto_caja_chica ? `<i class="fas fa-file-invoice text-muted cursor-zoom" onclick="zoomFoto('uploads/caja/${i.foto_caja_chica}')"></i>` : ''}
                        ${esAdminCaja && i.estado === 'creado' ? `
                            <button class="btn btn-link btn-sm p-0" onclick="modalValidarCaja(${i.id}, ${i.monto_caja_chica})">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
                <td>
                    <span class="status-badge bg-${badgeColor} bg-opacity-10 text-${badgeColor}">
                        ${labelEstado.toUpperCase()}
                    </span>
                </td>
                <td class="text-center pe-4">
                    ${accionesHtml}
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

// Filtros Estándar e Iconografía
function toggleFilter(icon) {
    const th = $(icon).closest('th');
    const columna = th.data('column');
    const tipo = th.data('type');

    if (panelFiltroAbierto === columna) {
        cerrarTodosFiltros();
        return;
    }

    cerrarTodosFiltros();
    crearPanelFiltro(th, columna, tipo, icon);
    panelFiltroAbierto = columna;
    $(icon).addClass('active');
    actualizarIndicadoresFiltros();
}

function cerrarTodosFiltros() {
    $('.filter-panel').remove();
    $('.filter-icon').removeClass('active');
    panelFiltroAbierto = null;
}

function crearPanelFiltro(th, columna, tipo, icon) {
    const panel = $('<div class="filter-panel show"></div>');

    // Ordenamiento
    panel.append(`
        <div class="filter-section">
            <span class="filter-section-title">Ordenar:</span>
            <div class="filter-sort-buttons">
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'asc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'asc')">ASC ↑</button>
                <button class="filter-sort-btn ${ordenActivo.columna === columna && ordenActivo.direccion === 'desc' ? 'active' : ''}" 
                        onclick="aplicarOrden('${columna}', 'desc')">DESC ↓</button>
            </div>
        </div>
    `);

    // Acción Limpiar
    panel.append(`
        <button class="filter-action-btn clear" onclick="limpiarFiltro('${columna}')">
            <i class="bi bi-x-circle"></i> Limpiar Filtro
        </button>
    `);

    // Tipo de filtro
    if (tipo === 'daterange') {
        const fechaActual = filtrosActivos[columna] || { desde: '', hasta: '' };
        panel.append(`
            <div class="filter-section">
                <span class="filter-section-title">Rango de Fechas:</span>
                <input type="date" class="form-control form-control-sm mb-2" value="${fechaActual.desde}" onchange="actualizarFiltroFecha('${columna}', 'desde', this.value)">
                <input type="date" class="form-control form-control-sm" value="${fechaActual.hasta}" onchange="actualizarFiltroFecha('${columna}', 'hasta', this.value)">
            </div>
        `);
    } else if (tipo === 'list') {
        cargarOpcionesFiltro(panel, columna);
    }

    $('body').append(panel);
    posicionarPanelFiltro(panel, icon);
}

function cargarOpcionesFiltro(panel, columna) {
    $.ajax({
        url: 'ajax/historial_informes_get_filtros.php',
        method: 'GET',
        data: { column: columna },
        success: function (response) {
            if (response.success) {
                let html = '<div class="filter-section">';
                html += '<span class="filter-section-title">Opciones:</span>';
                html += '<div class="filter-list-options" style="max-height: 200px; overflow-y: auto;">';
                response.options.forEach(opt => {
                    const checked = filtrosActivos[columna] && filtrosActivos[columna].includes(opt.value) ? 'checked' : '';
                    html += `
                        <div class="form-check small mx-2">
                            <input class="form-check-input" type="checkbox" value="${opt.value}" ${checked} onchange="toggleOpcionFiltro('${columna}', '${opt.value}', this.checked)">
                            <label class="form-check-label">${opt.label}</label>
                        </div>
                    `;
                });
                html += '</div></div>';
                panel.append(html);
            }
        }
    });
}

function posicionarPanelFiltro(panel, icon) {
    const rect = icon.getBoundingClientRect();
    panel.css({
        top: (rect.bottom + window.scrollY + 5) + 'px',
        left: (rect.right - panel.outerWidth() + window.scrollX) + 'px'
    });
}

function aplicarOrden(columna, direccion) {
    ordenActivo = { columna, direccion };
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
}

function limpiarFiltro(columna) {
    delete filtrosActivos[columna];
    cerrarTodosFiltros();
    paginaActual = 1;
    cargarDatos();
    actualizarIndicadoresFiltros();
}

function toggleOpcionFiltro(columna, valor, checked) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = [];
    if (checked) {
        if (!filtrosActivos[columna].includes(valor)) filtrosActivos[columna].push(valor);
    } else {
        filtrosActivos[columna] = filtrosActivos[columna].filter(v => v !== valor);
        if (filtrosActivos[columna].length === 0) delete filtrosActivos[columna];
    }
    paginaActual = 1;
    cargarDatos();
    actualizarIndicadoresFiltros();
}

function actualizarFiltroFecha(columna, tipo, valor) {
    if (!filtrosActivos[columna]) filtrosActivos[columna] = { desde: '', hasta: '' };
    filtrosActivos[columna][tipo] = valor;
    if (filtrosActivos[columna].desde === '' && filtrosActivos[columna].hasta === '') delete filtrosActivos[columna];
    paginaActual = 1;
    cargarDatos();
    actualizarIndicadoresFiltros();
}

function actualizarIndicadoresFiltros() {
    $('.filter-icon').removeClass('has-filter');
    Object.keys(filtrosActivos).forEach(col => {
        $(`th[data-column="${col}"] .filter-icon`).addClass('has-filter');
    });
}

// Paginación y Formato
function renderizarPaginacion(total) {
    const totalPaginas = Math.ceil(total / registrosPorPagina);
    const pag = $('#paginacion');
    pag.empty();

    if (totalPaginas <= 1) return;

    pag.append(`<button class="pagination-btn" ${paginaActual === 1 ? 'disabled' : ''} onclick="cambiarPagina(${paginaActual - 1})"><i class="bi bi-chevron-left"></i></button>`);
    for (let i = 1; i <= totalPaginas; i++) {
        const active = i === paginaActual ? 'active' : '';
        if (i === 1 || i === totalPaginas || (i >= paginaActual - 2 && i <= paginaActual + 2)) {
            pag.append(`<button class="pagination-btn ${active}" onclick="cambiarPagina(${i})">${i}</button>`);
        } else if (i === paginaActual - 3 || i === paginaActual + 3) {
            pag.append(`<span class="pagination-btn" disabled>...</span>`);
        }
    }
    pag.append(`<button class="pagination-btn" ${paginaActual === totalPaginas ? 'disabled' : ''} onclick="cambiarPagina(${paginaActual + 1})"><i class="bi bi-chevron-right"></i></button>`);
}

function cambiarPagina(p) {
    paginaActual = p;
    cargarDatos();
}

function cambiarRegistrosPorPagina() {
    registrosPorPagina = $('#registrosPorPagina').val();
    paginaActual = 1;
    cargarDatos();
}

function formatearFecha(f) {
    const d = new Date(f + 'T00:00:00');
    return d.toLocaleDateString('es-NI', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatearHora(f) {
    const d = new Date(f);
    return d.toLocaleTimeString('es-NI', { hour: '2-digit', minute: '2-digit', hour12: true });
}

/**
 * CAJA CHICA VALIDACION
 */
function modalValidarCaja(id, monto) {
    $('#caja_informe_id').val(id);
    $('#caja_monto').val(monto);
    $('#preview_caja').addClass('d-none');
    $('#cam_caja_container').addClass('d-none');
    stopCamera();
    new bootstrap.Modal(document.getElementById('validarCajaModal')).show();
}

async function guardarValidacionCaja() {
    const form = document.getElementById('formCaja');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);

    // Validar foto (archivo o cámara)
    if (!formData.get('foto_caja').name && !formData.get('foto_caja_cam')) {
        Swal.fire('Error', 'Debe adjuntar o tomar una foto del voucher', 'error');
        return;
    }

    Swal.fire({ title: 'Procesando...', didOpen: () => Swal.showLoading() });

    try {
        const response = await fetch('ajax/validar_caja_chica.php', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            Swal.fire('Éxito', 'Entrega de caja chica validada', 'success').then(() => cargarDatos());
            bootstrap.Modal.getInstance(document.getElementById('validarCajaModal')).hide();
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', e.message, 'error');
    }
}

/**
 * GESTIÓN DE CÁMARA UNIVERSAL
 */
async function startCamera(target) {
    currentCameraTarget = target;
    const container = $(`#${target}_container`);
    const video = document.getElementById(`${target}_video`);

    try {
        activeStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        video.srcObject = activeStream;
        container.removeClass('d-none');
    } catch (e) {
        Swal.fire('Cámara', 'No se pudo acceder a la cámara: ' + e.message, 'warning');
    }
}

function captureSnapshot(target) {
    const video = document.getElementById(`${target}_video`);
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    const dataURL = canvas.toDataURL('image/jpeg', 0.8);

    $(`#${target}_data`).val(dataURL);
    const preview = target.replace('cam_', 'preview_');
    $(`#${preview}`).removeClass('d-none').find('img').attr('src', dataURL);

    stopCamera();
}

function stopCamera() {
    if (activeStream) {
        activeStream.getTracks().forEach(track => track.stop());
        activeStream = null;
    }
    if (currentCameraTarget) {
        $(`#${currentCameraTarget}_container`).addClass('d-none');
    }
}

function previewFile(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            $(`#${previewId}`).removeClass('d-none').find('img').attr('src', e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function zoomFoto(src) {
    $('#zoomImg').attr('src', src);
    new bootstrap.Modal(document.getElementById('zoomModal')).show();
}
