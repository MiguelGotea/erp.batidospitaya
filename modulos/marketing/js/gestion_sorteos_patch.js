// PATCH: Simplified verFoto function - Add this AFTER gestion_sorteos.js loads

// Override verFoto with simplified version
window.verFoto = function (id) {
    $.ajax({
        url: `ajax/get_registros_sorteos.php?id=${id}`,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success && response.data.length > 0) {
                const registro = response.data[0];

                // Helper function to create inline comparison
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

                // Build 2-column layout
                const modalBody = $('.modal-body', '#modalVerFoto');
                modalBody.html(`
                    <div class="modal-comparison-simple">
                        <div class="comparison-photo-col">
                            <h6><i class="bi bi-image"></i> Foto de Factura</h6>
                            <img src="https://pitayalove.batidospitaya.com/uploads/${registro.foto_factura}" 
                                 alt="Factura" 
                                 class="comparison-photo"
                                 onclick="window.open(this.src, '_blank')">
                        </div>

                        <div class="comparison-data-col">
                            <h6><i class="bi bi-database"></i> Datos del Registro</h6>
                            <div class="comparison-data">
                                <div class="comparison-row">
                                    <div class="comparison-label">Nombre Completo</div>
                                    <div class="comparison-value">${registro.nombre_completo}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">No. Contacto</div>
                                    <div class="comparison-value">${registro.numero_contacto}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">No. Cédula</div>
                                    <div class="comparison-value">${registro.numero_cedula || 'N/A'}</div>
                                </div>
                                ${compararValores(
                    'Código Sorteo',
                    registro.numero_factura,
                    registro.codigo_sorteo_ia
                )}
                                <div class="comparison-row">
                                    <div class="comparison-label">Correo Electrónico</div>
                                    <div class="comparison-value">${registro.correo_electronico || 'N/A'}</div>
                                </div>
                                <div class="comparison-row">
                                    <div class="comparison-label">Monto Factura</div>
                                    <div class="comparison-value">C$ ${parseFloat(registro.monto_factura).toFixed(2)}</div>
                                </div>
                                ${compararValores(
                    'Puntos',
                    registro.puntos_factura,
                    registro.puntos_ia
                )}
                            </div>
                            
                            ${registro.validado_ia == 0 ? `
                                <div class="alert alert-warning mt-3 small">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Nota:</strong> La IA no pudo validar esta factura automáticamente.
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
                        <div class="alert alert-secondary text-center mt-3">
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

console.log('✅ verFoto function patched with simplified version');
