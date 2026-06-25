// talento_contenido.js - Lógica de control para gestión de colaboradores y habilidades en el ERP

document.addEventListener('DOMContentLoaded', function () {
    // Detectar en qué página estamos
    if (document.getElementById('tablaColaboradores')) {
        cargarColaboradores();
        inicializarFiltroImagen();
    } else if (document.getElementById('tablaHabilidades')) {
        cargarHabilidades();
    }

    // Inicializar envíos de formularios
    const formColaborador = document.getElementById('formColaborador');
    if (formColaborador) {
        formColaborador.addEventListener('submit', guardarColaborador);
    }

    const formHabilidad = document.getElementById('formHabilidad');
    if (formHabilidad) {
        formHabilidad.addEventListener('submit', guardarHabilidad);
    }
});

// ==============================================================================
// SECCIÓN: COLABORADORES
// ==============================================================================

async function cargarColaboradores() {
    try {
        const response = await fetch('ajax/get_colaboradores.php', { method: 'POST' });
        const data = await response.json();

        if (data.success) {
            renderizarColaboradores(data.datos);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('tbodyColaboradores').innerHTML = `
            <tr>
                <td colspan="8" class="text-center text-danger py-4">
                    <i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-2"></i>
                    Error al cargar los colaboradores: ${error.message}
                </td>
            </tr>
        `;
    }
}

function renderizarColaboradores(datos) {
    const tbody = document.getElementById('tbodyColaboradores');
    tbody.innerHTML = '';

    if (datos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No hay colaboradores registrados</td></tr>';
        return;
    }

    // Colores para avatares placeholder
    const avatarColores = ['#51B8AC', '#0E544C', '#FF6B00', '#218838', '#3d9a8f', '#854d0e'];

    datos.forEach((col, idx) => {
        let fotoHTML = '';
        if (col.foto) {
            // Se asume la subida a talento.batidospitaya.com/uploads/equipo/
            const fotoUrl = `https://talento.batidospitaya.com/uploads/equipo/${col.foto}`;
            fotoHTML = `<img src="${fotoUrl}" class="colaborador-thumb" alt="${escapeHtml(col.nombre)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">`;
        }
        
        // Placeholder
        const iniciales = obtenerIniciales(col.nombre);
        const bgColor = avatarColores[idx % avatarColores.length];
        fotoHTML += `
            <div class="avatar-initials" style="background: ${bgColor};">
                ${iniciales}
            </div>
        `;

        const estadoBadge = col.activo == 1 
            ? '<span class="badge bg-success">Activo</span>' 
            : '<span class="badge bg-secondary">Inactivo</span>';

        const btnEditar = canEdit 
            ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarColaborador(${col.id})" title="Editar"><i class="bi bi-pencil"></i></button>` 
            : '';
        const btnEliminar = canDelete 
            ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarColaborador(${col.id}, '${escapeHtml(col.nombre)}')" title="Eliminar"><i class="bi bi-trash"></i></button>` 
            : '';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="d-flex justify-content-center">
                    ${fotoHTML}
                </div>
            </td>
            <td><strong>${escapeHtml(col.nombre)}</strong></td>
            <td>${escapeHtml(col.cargo)}</td>
            <td><span class="badge bg-light text-dark border">${escapeHtml(col.departamento || 'No asignado')}</span></td>
            <td><small class="text-muted text-wrap d-block" style="max-height: 60px; overflow-y: auto;">"${escapeHtml(col.testimonio || '')}"</small></td>
            <td class="text-center">${col.orden}</td>
            <td class="text-center">${estadoBadge}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center">
                    ${btnEditar}
                    ${btnEliminar}
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function abrirModalNuevoColaborador() {
    document.getElementById('formColaborador').reset();
    document.getElementById('colaboradorId').value = '';
    document.getElementById('modalColaboradorLabel').textContent = 'Agregar Colaborador';
    
    // Resetear preview
    document.getElementById('fotoPreviewBox').innerHTML = '<i class="bi bi-person-fill text-muted fs-1"></i>';
    
    const modal = new bootstrap.Modal(document.getElementById('modalColaborador'));
    modal.show();
}

async function editarColaborador(id) {
    try {
        const response = await fetch(`ajax/get_colaboradores.php?id=${id}`, { method: 'POST' });
        const data = await response.json();

        if (data.success) {
            const col = data.datos;
            document.getElementById('colaboradorId').value = col.id;
            document.getElementById('colNombre').value = col.nombre;
            document.getElementById('colCargo').value = col.cargo;
            document.getElementById('colDepartamento').value = col.departamento || '';
            document.getElementById('colOrden').value = col.orden;
            document.getElementById('colTestimonio').value = col.testimonio || '';
            document.getElementById('colActivo').checked = col.activo == 1;

            // Renderizar preview de imagen
            const previewBox = document.getElementById('fotoPreviewBox');
            if (col.foto) {
                const fotoUrl = `https://talento.batidospitaya.com/uploads/equipo/${col.foto}`;
                previewBox.innerHTML = `<img src="${fotoUrl}" style="width:100%; height:100%; object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='block';"><i class="bi bi-person-fill text-muted fs-1" style="display:none;"></i>`;
            } else {
                previewBox.innerHTML = '<i class="bi bi-person-fill text-muted fs-1"></i>';
            }

            document.getElementById('modalColaboradorLabel').textContent = 'Editar Colaborador';
            const modal = new bootstrap.Modal(document.getElementById('modalColaborador'));
            modal.show();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo obtener el detalle del colaborador', 'error');
    }
}

async function guardarColaborador(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btnGuardarColaborador');
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

    try {
        const formData = new FormData(this);
        // Si el switch está apagado, forzar activo = 0
        if (!document.getElementById('colActivo').checked) {
            formData.set('activo', '0');
        } else {
            formData.set('activo', '1');
        }

        const response = await fetch('ajax/guardar_colaborador.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire('¡Éxito!', data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalColaborador')).hide();
            cargarColaboradores();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', error.message || 'No se pudo guardar el colaborador', 'error');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Guardar';
    }
}

function eliminarColaborador(id, nombre) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `Eliminarás a "${nombre}" del carrusel público de colaboradores de forma permanente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('ajax/eliminar_colaborador.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const data = await response.json();

                if (data.success) {
                    Swal.fire('¡Eliminado!', data.message, 'success');
                    cargarColaboradores();
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', error.message || 'No se pudo eliminar el colaborador', 'error');
            }
        }
    });
}

function inicializarFiltroImagen() {
    const fileInput = document.getElementById('colFoto');
    const previewBox = document.getElementById('fotoPreviewBox');
    
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewBox.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }
}


// ==============================================================================
// SECCIÓN: HABILIDADES
// ==============================================================================

async function cargarHabilidades() {
    try {
        const response = await fetch('ajax/get_habilidades.php', { method: 'POST' });
        const data = await response.json();

        if (data.success) {
            renderizarHabilidades(data.datos);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('tbodyHabilidades').innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-danger py-4">
                    <i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-2"></i>
                    Error al cargar las habilidades: ${error.message}
                </td>
            </tr>
        `;
    }
}

function renderizarHabilidades(datos) {
    const tbody = document.getElementById('tbodyHabilidades');
    tbody.innerHTML = '';

    if (datos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay habilidades registradas en el catálogo</td></tr>';
        return;
    }

    datos.forEach(hab => {
        let badgeClase = 'badge-cat-otros';
        const cat = hab.categoria || '';
        if (cat.includes('Blandas')) badgeClase = 'badge-cat-blanda';
        else if (cat.includes('Técnicas') || cat.includes('Tecnicas')) badgeClase = 'badge-cat-tecnica';
        else if (cat.includes('Idiomas')) badgeClase = 'badge-cat-idiomas';

        const estadoBadge = hab.activo == 1 
            ? '<span class="badge bg-success">Activa</span>' 
            : '<span class="badge bg-secondary">Inactiva</span>';

        const btnEditar = canEdit 
            ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarHabilidad(${hab.id})" title="Editar"><i class="bi bi-pencil"></i></button>` 
            : '';
        const btnEliminar = canDelete 
            ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarHabilidad(${hab.id}, '${escapeHtml(hab.nombre)}')" title="Eliminar"><i class="bi bi-trash"></i></button>` 
            : '';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td><code>#${hab.id}</code></td>
            <td><strong>${escapeHtml(hab.nombre)}</strong></td>
            <td><span class="badge ${badgeClase}">${escapeHtml(cat)}</span></td>
            <td class="text-center">${estadoBadge}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center">
                    ${btnEditar}
                    ${btnEliminar}
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function abrirModalNuevaHabilidad() {
    document.getElementById('formHabilidad').reset();
    document.getElementById('habilidadId').value = '';
    document.getElementById('modalHabilidadLabel').textContent = 'Agregar Habilidad';
    
    const modal = new bootstrap.Modal(document.getElementById('modalHabilidad'));
    modal.show();
}

async function editarHabilidad(id) {
    try {
        const response = await fetch(`ajax/get_habilidades.php?id=${id}`, { method: 'POST' });
        const data = await response.json();

        if (data.success) {
            const hab = data.datos;
            document.getElementById('habilidadId').value = hab.id;
            document.getElementById('habNombre').value = hab.nombre;
            document.getElementById('habCategoria').value = hab.categoria || '';
            document.getElementById('habActivo').checked = hab.activo == 1;

            document.getElementById('modalHabilidadLabel').textContent = 'Editar Habilidad';
            const modal = new bootstrap.Modal(document.getElementById('modalHabilidad'));
            modal.show();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo obtener el detalle de la habilidad', 'error');
    }
}

async function guardarHabilidad(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btnGuardarHabilidad');
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

    try {
        const formData = new FormData(this);
        // Si el switch está apagado, forzar activo = 0
        if (!document.getElementById('habActivo').checked) {
            formData.set('activo', '0');
        } else {
            formData.set('activo', '1');
        }

        const response = await fetch('ajax/guardar_habilidad.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire('¡Éxito!', data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalHabilidad')).hide();
            cargarHabilidades();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', error.message || 'No se pudo guardar la habilidad', 'error');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Guardar';
    }
}

function eliminarHabilidad(id, nombre) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `Eliminarás "${nombre}" del catálogo global de habilidades. Esto afectará a las vacantes que la tengan asignada.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('ajax/eliminar_habilidad.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const data = await response.json();

                if (data.success) {
                    Swal.fire('¡Eliminado!', data.message, 'success');
                    cargarHabilidades();
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', error.message || 'No se pudo eliminar la habilidad', 'error');
            }
        }
    });
}


// ==============================================================================
// UTILERÍAS / HELPERS
// ==============================================================================

function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function obtenerIniciales(nombre) {
    if (!nombre) return '?';
    const partes = nombre.trim().split(/\s+/);
    let iniciales = '';
    for (let i = 0; i < Math.min(partes.length, 2); i++) {
        if (partes[i].length > 0) {
            iniciales += partes[i][0].toUpperCase();
        }
    }
    return iniciales || '?';
}
