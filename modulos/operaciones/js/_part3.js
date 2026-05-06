
// =============================================
// AJAX: ACTUALIZAR ESTADO / OBSERVACIONES
// =============================================

function actualizarEstado(id, nuevoEstado) {
    if (!confirm('¿Está seguro de ' + (nuevoEstado === 'Justificado' ? 'aprobar' : 'rechazar') + ' esta tardanza?')) {
        return;
    }
    var observaciones = document.getElementById('obs-edit-' + id).value;
    var actionsDiv = document.getElementById('actions-' + id);
    var originalHTML = actionsDiv.innerHTML;
    actionsDiv.innerHTML = '<div style="display:flex;align-items:center;gap:8px;"><i class="fas fa-spinner fa-spin"></i> Procesando...</div>';

    fetch('actualizar_estado_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'id': id, 'estado': nuevoEstado, 'observaciones': observaciones })
    })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var badge = document.getElementById('status-badge-' + id);
                badge.textContent = nuevoEstado;
                badge.className = 'status-badge status-' + nuevoEstado.toLowerCase().replace(' ', '-');
                actualizarBotonesAccion(id, nuevoEstado);
                mostrarNotificacion('success', data.message);
            } else {
                actionsDiv.innerHTML = originalHTML;
                mostrarNotificacion('error', data.message);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            actionsDiv.innerHTML = originalHTML;
            mostrarNotificacion('error', 'Error al actualizar el estado');
        });
}

function cambiarEstado(id, estadoActual) {
    var nuevoEstado = estadoActual === 'Justificado' ? 'No Válido' : 'Justificado';
    actualizarEstado(id, nuevoEstado);
}

function toggleEditObservaciones(id) {
    var displayDiv = document.getElementById('obs-display-' + id);
    var editTextarea = document.getElementById('obs-edit-' + id);
    var actionsDiv = document.getElementById('actions-' + id);
    var saveCancelDiv = document.getElementById('save-cancel-' + id);

    if (!editandoObservaciones[id]) {
        observacionesOriginales[id] = editTextarea.value;
    }
    displayDiv.style.display = 'none';
    editTextarea.style.display = 'block';
    actionsDiv.style.display = 'none';
    saveCancelDiv.style.display = 'flex';
    editandoObservaciones[id] = true;
    editTextarea.focus();
}

function guardarObservaciones(id) {
    var editTextarea = document.getElementById('obs-edit-' + id);
    var nuevasObservaciones = editTextarea.value.trim();
    var badge = document.getElementById('status-badge-' + id);
    var estadoActual = badge.textContent.trim();
    var saveCancelDiv = document.getElementById('save-cancel-' + id);
    var originalHTML = saveCancelDiv.innerHTML;
    saveCancelDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('actualizar_estado_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ 'id': id, 'estado': estadoActual, 'observaciones': nuevasObservaciones })
    })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var displayDiv = document.getElementById('obs-display-' + id);
                displayDiv.innerHTML = nuevasObservaciones
                    ? nuevasObservaciones.replace(/\n/g, '<br>')
                    : '<span class="text-muted">Sin observaciones</span>';
                finalizarEdicionObservaciones(id);
                mostrarNotificacion('success', 'Observaciones actualizadas correctamente');
            } else {
                saveCancelDiv.innerHTML = originalHTML;
                mostrarNotificacion('error', data.message);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            saveCancelDiv.innerHTML = originalHTML;
            mostrarNotificacion('error', 'Error al guardar las observaciones');
        });
}

function cancelarEditObservaciones(id) {
    var editTextarea = document.getElementById('obs-edit-' + id);
    if (observacionesOriginales[id] !== undefined) {
        editTextarea.value = observacionesOriginales[id];
    }
    finalizarEdicionObservaciones(id);
}

function finalizarEdicionObservaciones(id) {
    var displayDiv = document.getElementById('obs-display-' + id);
    var editTextarea = document.getElementById('obs-edit-' + id);
    var actionsDiv = document.getElementById('actions-' + id);
    var saveCancelDiv = document.getElementById('save-cancel-' + id);
    displayDiv.style.display = 'block';
    editTextarea.style.display = 'none';
    actionsDiv.style.display = 'flex';
    saveCancelDiv.style.display = 'none';
    delete editandoObservaciones[id];
    delete observacionesOriginales[id];
}

function actualizarBotonesAccion(id, nuevoEstado) {
    var actionsDiv = document.getElementById('actions-' + id);
    if (nuevoEstado === 'Pendiente') {
        actionsDiv.innerHTML =
            '<button type="button" class="btn-action btn-approve" onclick="actualizarEstado(' + id + ', \'Justificado\')" title="Aprobar"><i class="fas fa-check"></i></button>' +
            '<button type="button" class="btn-action btn-reject" onclick="actualizarEstado(' + id + ', \'No Válido\')" title="Rechazar"><i class="fas fa-times"></i></button>' +
            '<button type="button" class="btn-action btn-edit" onclick="toggleEditObservaciones(' + id + ')" title="Editar observaciones"><i class="fas fa-edit"></i></button>';
    } else {
        actionsDiv.innerHTML =
            '<button type="button" class="btn-action btn-change" onclick="cambiarEstado(' + id + ', \'' + nuevoEstado + '\')" title="Cambiar estado"><i class="fas fa-exchange-alt"></i></button>' +
            '<button type="button" class="btn-action btn-edit" onclick="toggleEditObservaciones(' + id + ')" title="Editar observaciones"><i class="fas fa-edit"></i></button>';
    }
}

function mostrarNotificacion(tipo, mensaje) {
    var notification = document.createElement('div');
    notification.className = 'notification notification-' + tipo;
    notification.innerHTML =
        '<i class="fas fa-' + (tipo === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i>' +
        '<span>' + mensaje + '</span>';
    notification.style.cssText =
        'position:fixed;top:20px;right:20px;padding:15px 20px;border-radius:8px;color:white;font-weight:bold;' +
        'display:flex;align-items:center;gap:10px;z-index:10000;animation:slideIn 0.3s ease;' +
        'box-shadow:0 4px 12px rgba(0,0,0,0.15);' +
        'background:' + (tipo === 'success'
            ? 'linear-gradient(135deg,#28a745 0%,#20c997 100%)'
            : 'linear-gradient(135deg,#dc3545 0%,#e83e8c 100%)') + ';';
    document.body.appendChild(notification);
    setTimeout(function() {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(function() { notification.remove(); }, 300);
    }, 3000);
}

// Animaciones de notificación
(function() {
    var style = document.createElement('style');
    style.textContent =
        '@keyframes slideIn{from{transform:translateX(400px);opacity:0}to{transform:translateX(0);opacity:1}}' +
        '@keyframes slideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(400px);opacity:0}}';
    document.head.appendChild(style);
})();

// =============================================
// REGISTRO RÁPIDO DE TARDANZA NO REPORTADA
// =============================================

function registrarTardanzaNoReportada(codOperario, fecha, sucursalNombre, minutos, codSucursal) {
    if (!codSucursal) {
        alert('Error: No se pudo determinar la sucursal. Intente seleccionar una sucursal en los filtros.');
        return;
    }
    if (confirm('¿Desea registrar la tardanza del colaborador en fecha ' + formatearFechaLocal(fecha) + '?\n\nTardanza: ' + minutos + ' minutos\nSucursal: ' + sucursalNombre)) {
        mostrarModalRegistroRapido(codOperario, fecha, codSucursal, minutos, sucursalNombre);
    }
}

function mostrarModalRegistroRapido(codOperario, fecha, codSucursal, minutos, sucursalNombre) {
    var nombreColaborador = '';
    var fila = document.querySelector('#tardanza-nr-' + codOperario + '-' + fecha + ' td:first-child');
    if (fila) nombreColaborador = fila.textContent;

    var modalHTML =
        '<div class="modal" id="modalRegistroRapido">' +
        '<div class="modal-content" style="max-width:500px;">' +
        '<div class="modal-header">' +
        '<h2 class="modal-title">Registrar Tardanza</h2>' +
        '<button class="modal-close" onclick="cerrarModalRegistroRapido()">&times;</button>' +
        '</div>' +
        '<form id="formRegistroRapido" method="post" enctype="multipart/form-data">' +
        '<input type="hidden" name="registrar_tardanza" value="1">' +
        '<input type="hidden" name="cod_operario" value="' + codOperario + '">' +
        '<input type="hidden" name="fecha_tardanza" value="' + fecha + '">' +
        '<input type="hidden" name="cod_sucursal" value="' + codSucursal + '">' +
        '<div class="modal-body">' +
        '<div class="info-group"><span class="info-label">Colaborador:</span><span class="info-value">' + nombreColaborador + '</span></div>' +
        '<div class="info-group"><span class="info-label">Sucursal:</span><span class="info-value">' + sucursalNombre + '</span></div>' +
        '<div class="info-group"><span class="info-label">Fecha:</span><span class="info-value">' + formatearFechaLocal(fecha) + '</span></div>' +
        '<div class="info-group"><span class="info-label">Minutos de tardanza:</span><span class="info-value">' + minutos + ' minutos</span></div>' +
        '<div class="form-group"><label for="rapido_tipo" class="form-label">Tipo de Justificación:</label>' +
        '<select id="rapido_tipo" name="tipo_justificacion" class="form-select" required>' +
        '<option value="llave">Problema con llave</option>' +
        '<option value="error_sistema">Error del sistema</option>' +
        '<option value="accidente">Accidente/tráfico</option>' +
        '<option value="transporte">Problema de transporte</option>' +
        '<option value="personal">Asunto personal</option>' +
        '</select></div>' +
        '<div class="form-group"><label for="rapido_foto" class="form-label">Foto (obligatorio):</label>' +
        '<input type="file" id="rapido_foto" name="foto" class="form-input" accept="image/*" required>' +
        '<img id="rapido_foto_preview" class="photo-preview" src="#" alt="Vista previa"></div>' +
        '<div class="form-group"><label for="rapido_observaciones" class="form-label">Observaciones:</label>' +
        '<textarea id="rapido_observaciones" name="observaciones" class="form-textarea" placeholder="Opcional"></textarea></div>' +
        '</div>' +
        '<div class="modal-footer">' +
        '<button type="button" onclick="cerrarModalRegistroRapido()" class="btn btn-secondary">Cancelar</button>' +
        '<button type="submit" class="btn btn-primary">Registrar</button>' +
        '</div></form></div></div>';

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.getElementById('modalRegistroRapido').style.display = 'flex';

    document.getElementById('rapido_foto').addEventListener('change', function(e) {
        var preview = document.getElementById('rapido_foto_preview');
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) { preview.src = e.target.result; preview.style.display = 'block'; };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    });

    document.getElementById('formRegistroRapido').addEventListener('submit', function(e) {
        if (!validarFormularioRapido()) { e.preventDefault(); return false; }
        return true;
    });
}

function cerrarModalRegistroRapido() {
    var modal = document.getElementById('modalRegistroRapido');
    if (modal) modal.remove();
}

function validarFormularioRapido() {
    var fotoInput = document.getElementById('rapido_foto');
    if (!fotoInput.files || fotoInput.files.length === 0) {
        alert('Debe seleccionar una foto como evidencia');
        return false;
    }
    if (!fotoInput.files[0].type.match('image.*')) {
        alert('El archivo debe ser una imagen');
        return false;
    }
    return true;
}

// =============================================
// DETALLES TARDANZA NO REPORTADA
// =============================================

function verDetallesTardanzaNoReportada(codOperario, fecha, sucursalNombre, minutos) {
    var nombreColaborador = '';
    var fila = document.querySelector('#tardanza-nr-' + codOperario + '-' + fecha + ' td:first-child');
    if (fila) nombreColaborador = fila.textContent;

    var detallesHTML =
        '<div class="modal" id="modalDetallesNR">' +
        '<div class="modal-content">' +
        '<div class="modal-header">' +
        '<h2 class="modal-title">Detalles de Tardanza Detectada</h2>' +
        '<button class="modal-close" onclick="cerrarModalDetallesNR()">&times;</button>' +
        '</div>' +
        '<div class="modal-body">' +
        '<div class="info-group"><span class="info-label">Colaborador:</span><span class="info-value">' + nombreColaborador + '</span></div>' +
        '<div class="info-group"><span class="info-label">Sucursal:</span><span class="info-value">' + sucursalNombre + '</span></div>' +
        '<div class="info-group"><span class="info-label">Fecha:</span><span class="info-value">' + formatearFechaLocal(fecha) + '</span></div>' +
        '<div class="info-group"><span class="info-label">Minutos de tardanza:</span><span class="info-value">' + minutos + ' minutos</span></div>' +
        '<div class="info-group"><span class="info-label">Estado:</span><span class="info-value"><span class="status-badge status-no-reportada">No Reportada</span></span></div>' +
        '<div class="info-group"><span class="info-label">Descripción:</span><span class="info-value">Esta tardanza fue detectada automáticamente por el sistema al comparar el horario programado con las marcaciones reales. Aún no ha sido reportada manualmente por un líder.</span></div>' +
        '</div>' +
        '<div class="modal-footer"><button type="button" onclick="cerrarModalDetallesNR()" class="btn btn-primary">Cerrar</button></div>' +
        '</div></div>';

    document.body.insertAdjacentHTML('beforeend', detallesHTML);
    document.getElementById('modalDetallesNR').style.display = 'flex';
}

function cerrarModalDetallesNR() {
    var modal = document.getElementById('modalDetallesNR');
    if (modal) modal.remove();
}

function mostrarMinutosTardanza(minutos) {
    if (minutos <= 0) return 'A tiempo';
    if (minutos === 1) return '1 minuto (gracia)';
    return minutos + ' minutos';
}

// =============================================
// DATATABLES
// =============================================

$(document).ready(function () {
    $('#listaTardanzasMan').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json' },
        dom: '<"top"l>rt<"bottom"ip>',
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        pageLength: 25,
        order: [],
        ordering: true,
        orderMulti: true,
        columnDefs: [{ orderable: true, targets: '_all' }]
    });
});
