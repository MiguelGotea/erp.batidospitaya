/* compra_local_gestion_perfiles.js */

$(document).ready(function () {
    cargarPerfiles();
    cargarSemanas();

    $('#formPerfil').on('submit', function (e) {
        e.preventDefault();
        savePerfil();
    });
});

let perfilesData = [];

function cargarPerfiles() {
    $.get('ajax/compra_local_gestion_perfiles_get.php', function (res) {
        if (res.success) {
            perfilesData = res.perfiles;
            renderizarPerfiles(res.perfiles);
        }
    });
}

function cargarSemanas() {
    $.get('ajax/compra_local_gestion_perfiles_get_semanas.php', function (res) {
        if (res.success) {
            let options = res.semanas.map(s => `<option value="${s.numero_semana}">Semana ${s.numero_semana} (${s.fecha_inicio})</option>`).join('');
            $('#perfil-semana-ref').html(options);
        }
    });
}

function renderizarPerfiles(perfiles) {
    const list = $('#perfiles-list');
    list.empty();

    if (perfiles.length === 0) {
        list.html('<div class="col-12 text-center p-5 text-muted">No hay perfiles creados.</div>');
        return;
    }

    perfiles.forEach(p => {
        const badgeFreq = getBadgeFrequency(p.frecuencia_semanas);
        const daysHtml = renderDaysDots(p);

        list.append(`
            <div class="col-md-4 mb-4">
                <div class="card profile-card shadow-sm border-0">
                    <div class="card-body">
                        <span class="frequency-badge shadow-sm ${badgeFreq.class}">${badgeFreq.text}</span>
                        <h5 class="fw-bold mb-1">${p.nombre}</h5>
                        <p class="text-muted small mb-3">${p.total_productos} productos asociados</p>
                        
                        <div class="mb-2">
                            <span class="small fw-bold text-dark">Días de despacho:</span>
                            ${daysHtml}
                        </div>

                        ${p.semana_referencia ? `
                        <div class="mt-2 small">
                            <i class="fas fa-calendar-alt me-1 text-muted"></i> 
                            Ciclo inicia: <span class="badge bg-secondary">Sem ${p.semana_referencia}</span>
                        </div>` : ''}

                        <div class="card-actions">
                            <button class="btn btn-light btn-icon-sm rounded-circle" onclick="editarPerfil(${p.id})">
                                <i class="fas fa-edit text-primary"></i>
                            </button>
                            ${p.total_productos == 0 ? `
                            <button class="btn btn-light btn-icon-sm rounded-circle" onclick="eliminarPerfil(${p.id})">
                                <i class="fas fa-trash text-danger"></i>
                            </button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
}

function getBadgeFrequency(val) {
    switch (parseInt(val)) {
        case 1: return { text: 'Semanal', class: 'bg-info text-white' };
        case 2: return { text: 'Quincenal', class: 'bg-primary text-white' };
        case 3: return { text: 'Cada 3 Sem', class: 'bg-warning text-dark' };
        case 4: return { text: 'Mensual', class: 'bg-dark text-white' };
        default: return { text: 'Desconocido', class: 'bg-secondary text-white' };
    }
}

function renderDaysDots(p) {
    const days = [
        { key: 'lunes', label: 'L' },
        { key: 'martes', label: 'M' },
        { key: 'miercoles', label: 'M' },
        { key: 'jueves', label: 'J' },
        { key: 'viernes', label: 'V' },
        { key: 'sabado', label: 'S' },
        { key: 'domingo', label: 'D' }
    ];

    return `
        <div class="days-dot-container">
            ${days.map(d => `<div class="day-dot ${p[d.key] == 1 ? 'active' : ''}" title="${d.key}">${d.label}</div>`).join('')}
        </div>
    `;
}

function toggleSemanaReferencia() {
    const freq = $('#perfil-frecuencia').val();
    if (parseInt(freq) > 1) {
        $('#semana-referencia-container').removeClass('d-none');
    } else {
        $('#semana-referencia-container').addClass('d-none');
    }
}

function abrirModalPerfil() {
    $('#formPerfil')[0].reset();
    $('#perfil-id').val('');
    $('#modalTitle').text('Crear Perfil');
    $('#semana-referencia-container').addClass('d-none');
    $('#modalPerfil').modal('show');
}

function editarPerfil(id) {
    const p = perfilesData.find(x => x.id == id);
    if (!p) return;

    $('#perfil-id').val(p.id);
    $('#perfil-nombre').val(p.nombre);
    $('#perfil-frecuencia').val(p.frecuencia_semanas);
    $('#perfil-semana-ref').val(p.semana_referencia);

    const days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    days.forEach(d => {
        $(`#day-${d.substring(0, 3)}`).prop('checked', p[d] == 1);
    });

    $('#modalTitle').text('Editar Perfil');
    toggleSemanaReferencia();
    $('#modalPerfil').modal('show');
}

function savePerfil() {
    const data = $('#formPerfil').serialize();
    $.post('ajax/compra_local_gestion_perfiles_save.php', data, function (res) {
        if (res.success) {
            Swal.fire('Éxito', 'Perfil guardado correctamente', 'success');
            $('#modalPerfil').modal('hide');
            cargarPerfiles();
        } else {
            Swal.fire('Error', res.message, 'error');
        }
    });
}

function eliminarPerfil(id) {
    Swal.fire({
        title: '¿Eliminar perfil?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('ajax/compra_local_gestion_perfiles_delete.php', { id: id }, function (res) {
                if (res.success) {
                    Swal.fire('Eliminado', 'El perfil ha sido eliminado.', 'success');
                    cargarPerfiles();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            });
        }
    });
}
