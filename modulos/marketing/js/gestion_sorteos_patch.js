// PATCH: Verificación Colaborador + Puntos Globales + Descarga

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

// ── Badge de verificación IA ──────────────────────────────────────────────────
// (Redefine getVerificacionBadge para asegurar que esté disponible)
window.getVerificacionBadge = function (registro) {
    const tieneValoresIA = (registro.codigo_sorteo_ia !== null && registro.codigo_sorteo_ia !== '') ||
        (registro.puntos_ia !== null && registro.puntos_ia !== '');
    if (!tieneValoresIA) {
        return '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Revisar</span>';
    }
    const codigoCoincide = registro.numero_factura == registro.codigo_sorteo_ia;
    const puntosCoinciden = registro.puntos_factura == registro.puntos_ia;
    if (codigoCoincide && puntosCoinciden) {
        return '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Verificado</span>';
    }
    return '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Revisar</span>';
};

// ── Badge de verificación colaborador ────────────────────────────────────────
function getColaboradorBadge(registro) {
    if (registro.colaborador_sospechoso == 1) {
        return '<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Revisar</span>';
    }
    return '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Verificado</span>';
}

// ── Override renderizarTabla (12 columnas) ───────────────────────────────────
window.renderizarTabla = function (registros) {
    const tbody = $('#tablaSorteosBody');
    tbody.empty();

    if (!registros || registros.length === 0) {
        tbody.append('<tr><td colspan="12" class="text-center py-4">No se encontraron registros</td></tr>');
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

        // Puntos globales: destacar si hay más de 1 participación
        const ptsGlobales = parseInt(registro.puntos_globales) || parseInt(registro.puntos_factura);
        const esRepetido = ptsGlobales > parseInt(registro.puntos_factura);
        const ptsGlobalesHtml = esRepetido
            ? `<span class="badge bg-info text-dark" title="Participante con múltiples registros">${ptsGlobales} <i class="bi bi-layers-fill"></i></span>`
            : `<span>${ptsGlobales}</span>`;

        tbody.append(`
            <tr>
                <td>${registro.nombre_completo}</td>
                <td>${registro.numero_cedula || '-'}</td>
                <td>${registro.numero_contacto}</td>
                <td class="col-correo">${registro.correo_electronico || '-'}</td>
                <td>${parseFloat(registro.monto_factura).toFixed(2)}</td>
                <td>${registro.numero_factura}</td>
                <td>${registro.puntos_factura}</td>
                <td class="text-center">${ptsGlobalesHtml}</td>
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

// ── Ejecutar Invalidación Masiva ─────────────────────────────────────────────
function ejecutarInvalidacionMasiva() {
    if (!confirm('¿Está seguro de ejecutar la invalidación masiva? \n\nEsto marcará como Inválidos (valido=0) todos los registros que no cumplan la verificación de IA o de Colaboradores usando la lógica completa del sistema.')) {
        return;
    }

    const btn = document.querySelector('button[onclick="ejecutarInvalidacionMasiva()"]');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Procesando...';
    btn.disabled = true;

    $.ajax({
        url: 'ajax/procesar_invalidacion_masiva.php',
        method: 'POST',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                // Llenar datos en el modal
                $('#msgTotalActualizados').text(`${response.counts.total} Registros Actualizados`);
                $('#cntIA').text(response.counts.ia);
                $('#cntColab').text(response.counts.colab);

                // Mostrar modal
                const modal = new bootstrap.Modal(document.getElementById('modalResultadoInvalidacion'));
                modal.show();

                // Recargar tabla
                cargarRegistros();
            } else {
                alert('Error: ' + (response.message || 'Ocurrió un error al procesar.'));
            }
        },
        error: function () {
            alert('Error crítico al conectar con el servidor.');
        },
        complete: function () {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
    });
}

// ── Descarga XLSX de concursantes válidos (SheetJS) ───────────────────────
function descargarConcursantesValidos() {
    const btn = document.querySelector('button[onclick="descargarConcursantesValidos()"]');
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Descargando...';
    btn.disabled = true;

    const params = new URLSearchParams({
        valido: 1,
        page: 1,
        per_page: 99999,
        orden_columna: 'fecha_registro',
        orden_direccion: 'ASC'
    });

    fetch(`ajax/get_registros_sorteos.php?${params}`)
        .then(r => r.json())
        .then(response => {
            if (!response.success || !response.data.length) {
                alert('No hay concursantes válidos para descargar.');
                return;
            }

            const cols = [
                { key: 'nombre_completo', label: 'Nombre Completo' },
                { key: 'numero_cedula', label: 'No. Cédula' },
                { key: 'numero_contacto', label: 'No. Contacto' },
                { key: 'correo_electronico', label: 'Correo' },
                { key: 'monto_factura', label: 'Monto (C$)' },
                { key: 'numero_factura', label: 'No. Factura' },
                { key: 'puntos_factura', label: 'Puntos' },
                { key: 'puntos_globales', label: 'Pts. Globales' },
                { key: 'fecha_registro', label: 'Fecha Registro' },
            ];

            // Construir array de arrays para SheetJS
            const aoa = [cols.map(c => c.label)];
            response.data.forEach(r => {
                aoa.push(cols.map(c => {
                    let val = r[c.key] ?? '';
                    if (c.key === 'monto_factura') return parseFloat(val) || 0;
                    if (c.key === 'puntos_factura' || c.key === 'puntos_globales') return parseInt(val) || 0;
                    if (c.key === 'fecha_registro') {
                        return new Date(val).toLocaleString('es-NI', { hour12: true });
                    }
                    return val === null ? '' : String(val);
                }));
            });

            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(aoa);

            // Ajustar ancho de columnas automáticamente
            ws['!cols'] = cols.map((_, ci) => ({
                wch: Math.max(
                    cols[ci].label.length,
                    ...aoa.slice(1).map(row => String(row[ci] ?? '').length)
                ) + 2
            }));

            XLSX.utils.book_append_sheet(wb, ws, 'Concursantes');
            XLSX.writeFile(wb, `concursantes_validos_${new Date().toISOString().slice(0, 10)}.xlsx`);
        })
        .catch(() => alert('Error al generar el Excel.'))
        .finally(() => {
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        });
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

                // Bloque de alerta de colaborador sospechoso
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
                                <!-- Fecha -->
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
                                <!-- Monto -->
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
                                <!-- Puntos Globales -->
                                ${registro.puntos_globales > registro.puntos_factura ? `
                                <div class="comparison-row">
                                    <div class="comparison-label"><i class="bi bi-layers-fill text-info"></i> Puntos Globales (todas sus participaciones)</div>
                                    <div class="comparison-value"><span class="badge bg-info text-dark fs-6">${registro.puntos_globales} pts</span></div>
                                </div>
                                ` : ''}
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
