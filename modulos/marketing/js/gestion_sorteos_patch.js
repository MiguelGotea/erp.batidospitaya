// PATCH: Verificación Colaborador + funciones actualizadas

// ── Estado global de filtro colaborador ──────────────────────────────────────
let colabFilterState = 'all'; // 'all', 'verified', 'review'

// ── Override de cargarRegistros para incluir collab_filter ───────────────────
window.cargarRegistros = function () {
    const params = new URLSearchParams({
        page: paginaActual,
        per_page: registrosPorPagina,
        ...(ordenActivo.columna && {
            orden_columna: ordenActivo.columna,
            orden_direccion: ordenActivo.direccion
        }),
        ...(validoFilterState !== 'all' && {
            valido: validoFilterState === 'valid' ? 1 : 0
        }),
        ...(iaFilterState !== 'all' && {
            ia_filter: iaFilterState
        }),
        ...(colabFilterState !== 'all' && {
            collab_filter: colabFilterState
        })
    });

    Object.keys(filtrosActivos).forEach(key => {
        const value = filtrosActivos[key];
        if (typeof value === 'object' && value !== null) {
            params.append(key, JSON.stringify(value));
        } else {
            params.append(key, value);
        }
    });

    $.ajax({
        url: `ajax/get_registros_sorteos.php?${params}`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                renderizarTabla(response.data);
                renderizarPaginacion(response.total_pages, response.page);
                actualizarIndicadoresFiltros();
            } else {
                mostrarError('Error al cargar registros: ' + (response.message || 'Error desconocido'));
            }
        },
        error: function () {
            mostrarError('Error al cargar registros');
        }
    });
};

// ── Badge de verificación colaborador ────────────────────────────────────────
function getColaboradorBadge(registro) {
    if (registro.colaborador_sospechoso == 1) {
        return '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Revisar</span>';
    }
    return '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Verificado</span>';
}

// ── Override renderizarTabla (11 columnas) ───────────────────────────────────
window.renderizarTabla = function (registros) {
    const tbody = $('#tablaSorteosBody');
    tbody.empty();

    if (!registros || registros.length === 0) {
        tbody.append('<tr><td colspan="11" class="text-center py-4">No se encontraron registros</td></tr>');
        return;
    }

    registros.forEach(registro => {
        const validoIcon = registro.valido == 1
            ? '<i class="bi bi-check-circle-fill valido-icon valid" title="Válido"></i>'
            : '<i class="bi bi-x-circle-fill valido-icon invalid" title="Inválido"></i>';

        const fechaUTC = new Date(registro.fecha_registro);
        const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const dia = String(fechaUTC.getDate()).padStart(2, '0');
        const mes = meses[fechaUTC.getMonth()];
        const año = String(fechaUTC.getFullYear()).slice(-2);
        const fecha = `${dia}/${mes}/${año}`;

        tbody.append(`
            <tr>
                <td>${registro.nombre_completo}</td>
                <td>${registro.numero_cedula || '-'}</td>
                <td>${registro.numero_contacto}</td>
                <td>${registro.correo_electronico || '-'}</td>
                <td>${parseFloat(registro.monto_factura).toFixed(2)}</td>
                <td>${registro.numero_factura}</td>
                <td>${registro.puntos_factura}</td>
                <td>${fecha}</td>
                <td class="text-center">${getVerificacionBadge(registro)}</td>
                <td class="text-center">${getColaboradorBadge(registro)}</td>
                <td class="text-center">${validoIcon}</td>
                <td>
                    <button class="btn btn-sm btn-primary btn-ver-foto" data-id="${registro.id}" title="Ver Detalle">
                        <i class="bi bi-eye"></i> Ver
                    </button>
                </td>
            </tr>
        `);
    });
};

// ── Filtro de columna Verificación Colaborador ───────────────────────────────
function setColabFilter(state) {
    colabFilterState = state;

    document.querySelectorAll('th[data-column="verificacion_colaborador"] .filter-circle').forEach(circle => {
        circle.classList.remove('active');
    });
    document.querySelector(`th[data-column="verificacion_colaborador"] .filter-circle[data-state="${state}"]`).classList.add('active');

    paginaActual = 1;
    cargarRegistros();
}

// ── Override verFoto: muestra colaborador sospechoso debajo del nombre ───────
window.verFoto = function (id) {

    $.ajax({
        url: `ajax/get_registros_sorteos.php?id=${id}`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {

            if (response.success && response.data.length > 0) {
                const registro = response.data[0];

                // Helper para comparación inline
                const compararValores = (label, guardado, ia) => {
                    const diferente = guardado != ia && ia != null && ia !== '';
                    return `
                        <div class="comparison-row ${diferente ? 'highlight-diff' : ''}">
                            <div class="comparison-label">${label}</div>
                            <div class="comparison-inline">
                                <div class="stored-value">
                                    <strong>Guardado:</strong> ${guardado || 'N/A'}
                                </div>
                                ${ia ? `
                                    <div class="ai-value">
                                        <strong>IA detectó:</strong> ${ia}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                };

                // Bloque de alerta de colaborador sospechoso (debajo del nombre)
                const bloqueColaborador = registro.colaborador_sospechoso == 1 ? `
                    <div class="comparison-row highlight-diff-colab">
                        <div class="comparison-label">
                            <i class="bi bi-person-exclamation text-warning"></i> Colaborador Sospechoso
                        </div>
                        <div class="comparison-value">
                            <span class="badge bg-warning text-dark me-1">
                                <i class="bi bi-exclamation-triangle"></i>
                            </span>
                            ${registro.nombre_colaborador}
                        </div>
                    </div>
                ` : '';

                const modalBody = $('.modal-body', '#modalVerFoto');
                modalBody.html(`
                    <div class="modal-comparison-simple">
                        <div class="comparison-photo-col">
                            <img src="https://pitayalove.batidospitaya.com/uploads/${registro.foto_factura}" 
                                 alt="Factura" 
                                 class="comparison-photo"
                                 onclick="window.open(this.src, '_blank')">
                        </div>

                        <div class="comparison-data-col">
                            <div class="comparison-data">
                                <!-- Nombre | Cédula -->
                                <div class="comparison-row-grid">
                                    <div class="comparison-row-half">
                                        <div class="comparison-label">Nombre</div>
                                        <div class="comparison-value">${registro.nombre_completo}</div>
                                    </div>
                                    <div class="comparison-row-half">
                                        <div class="comparison-label">Cédula</div>
                                        <div class="comparison-value">${registro.numero_cedula || 'N/A'}</div>
                                    </div>
                                </div>
                                ${bloqueColaborador}
                                <!-- Fecha (full width) -->
                                <div class="comparison-row">
                                    <div class="comparison-label">Fecha Registro</div>
                                    <div class="comparison-value">${new Date(registro.fecha_registro).toLocaleString('es-NI', { hour12: true })}</div>
                                </div>
                                <!-- Contacto | Correo -->
                                <div class="comparison-row-grid">
                                    <div class="comparison-row-half">
                                        <div class="comparison-label">Contacto</div>
                                        <div class="comparison-value">${registro.numero_contacto}</div>
                                    </div>
                                    <div class="comparison-row-half">
                                        <div class="comparison-label">Correo</div>
                                        <div class="comparison-value">${registro.correo_electronico || 'N/A'}</div>
                                    </div>
                                </div>
                                <!-- Monto (full width) -->
                                <div class="comparison-row">
                                    <div class="comparison-label">Monto</div>
                                    <div class="comparison-value">C$ ${parseFloat(registro.monto_factura).toFixed(2)}</div>
                                </div>
                                <!-- Código Sorteo | Puntos side by side -->
                                <div class="comparison-row-grid">
                                    <div class="comparison-row-half ${registro.numero_factura != registro.codigo_sorteo_ia && registro.codigo_sorteo_ia != null && registro.codigo_sorteo_ia !== '' ? 'highlight-diff' : ''}">
                                        <div class="comparison-label">Código Sorteo</div>
                                        <div class="comparison-inline-compact">
                                            <div class="stored-value"><strong>Registrado:</strong> ${registro.numero_factura}</div>
                                            ${registro.codigo_sorteo_ia ? `<div class="ai-value"><strong>Foto:</strong> ${registro.codigo_sorteo_ia}</div>` : ''}
                                        </div>
                                    </div>
                                    <div class="comparison-row-half ${registro.puntos_factura != registro.puntos_ia && registro.puntos_ia != null && registro.puntos_ia !== '' ? 'highlight-diff' : ''}">
                                        <div class="comparison-label">Puntos</div>
                                        <div class="comparison-inline-compact">
                                            <div class="stored-value"><strong>Registrado:</strong> ${registro.puntos_factura}</div>
                                            ${registro.puntos_ia ? `<div class="ai-value"><strong>Foto:</strong> ${registro.puntos_ia}</div>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${registro.validado_ia == 0 ? `
                                <div class="alert alert-warning mt-2 small p-2">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Nota:</strong> IA no pudo validar factura.
                                </div>
                            ` : ''}
                        </div>
                    </div>

                    ${tienePermisoEdicion ? `
                        <div class="valido-toggle-container">
                            <span class="valido-toggle-label">Estado del Registro:</span>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       id="toggleValido" 
                                       ${registro.valido == 1 ? 'checked' : ''}
                                       onchange="toggleValidoRegistro(${registro.id}, this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-status-text ${registro.valido == 1 ? 'valid' : 'invalid'}" id="toggleStatusText">
                                ${registro.valido == 1 ? '✓ Válido' : '✗ Inválido'}
                            </span>
                        </div>
                    ` : `
                        <div class="alert alert-secondary text-center mt-2">
                            <strong>Estado:</strong> 
                            ${registro.valido == 1
                        ? '<span class="text-success">✓ Válido</span>'
                        : '<span class="text-danger">✗ Inválido</span>'}
                        </div>
                    `}
                `);

                new bootstrap.Modal(document.getElementById('modalVerFoto')).show();
            }
        },
        error: function () {
            mostrarError('Error al cargar los detalles del registro');
        }
    });
};
