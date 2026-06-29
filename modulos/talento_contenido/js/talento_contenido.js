// talento_contenido.js - Lógica de control para gestión de colaboradores y habilidades en el ERP

document.addEventListener('DOMContentLoaded', function () {
    // Detectar en qué página estamos
    if (document.getElementById('tablaColaboradores')) {
        cargarColaboradores();
        inicializarFiltroImagen();
    } else if (document.getElementById('tablaHabilidades')) {
        cargarHabilidades();
    } else if (document.getElementById('tablaNoticias')) {
        cargarNoticias();
        inicializarFiltroPortadaNoticia();
    }

    if (document.getElementById('tablaAreas')) {
        cargarAreas();
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

    const formNoticia = document.getElementById('formNoticia');
    if (formNoticia) {
        formNoticia.addEventListener('submit', guardarNoticia);
    }

    const formArea = document.getElementById('formArea');
    if (formArea) {
        formArea.addEventListener('submit', guardarArea);
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
                <td colspan="7" class="text-center text-danger py-4">
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
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No hay colaboradores registrados</td></tr>';
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

// ==============================================================================
// SECCIÓN: NOTICIAS Y NOVEDADES
// ==============================================================================

async function cargarNoticias() {
    try {
        const response = await fetch('ajax/get_noticias.php', { method: 'POST' });
        const data = await response.json();

        if (data.success) {
            renderizarNoticias(data.datos);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('tbodyNoticias').innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-danger py-4">
                    <i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-2"></i>
                    Error al cargar las noticias: ${error.message}
                </td>
            </tr>
        `;
    }
}

function renderizarNoticias(datos) {
    const tbody = document.getElementById('tbodyNoticias');
    tbody.innerHTML = '';

    if (datos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No hay noticias registradas</td></tr>';
        return;
    }

    datos.forEach(not => {
        let portadaHTML = '<div class="noticia-thumb-placeholder"><i class="bi bi-image"></i></div>';
        if (not.imagen_principal) {
            const fotoUrl = `https://talento.batidospitaya.com/uploads/noticias/${not.imagen_principal}`;
            portadaHTML = `<img src="${fotoUrl}" class="noticia-thumb" alt="${escapeHtml(not.titulo)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                           <div class="noticia-thumb-placeholder" style="display:none;"><i class="bi bi-image"></i></div>`;
        }

        let catBadgeClase = 'bg-secondary';
        const cat = not.categoria || '';
        if (cat === 'Expansión') catBadgeClase = 'bg-info text-dark';
        else if (cat === 'Bienestar') catBadgeClase = 'bg-success';
        else if (cat === 'Lanzamiento') catBadgeClase = 'bg-warning text-dark';
        else if (cat === 'Cultura') catBadgeClase = 'bg-primary';

        let estadoBadge = '';
        if (not.estado === 'publicado') {
            estadoBadge = '<span class="badge bg-success">Publicado</span>';
        } else if (not.estado === 'borrador') {
            estadoBadge = '<span class="badge bg-warning text-dark">Borrador</span>';
        } else {
            estadoBadge = '<span class="badge bg-secondary">Archivado</span>';
        }

        const btnEditar = canEdit 
            ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarNoticia(${not.id})" title="Editar"><i class="bi bi-pencil"></i></button>` 
            : '';
        const btnGaleria = canEdit 
            ? `<button class="btn btn-sm btn-outline-info me-1" onclick="gestionarGaleria(${not.id}, '${escapeHtml(not.titulo)}')" title="Gestionar Galería"><i class="bi bi-images"></i></button>` 
            : '';
        const btnEliminar = canDelete 
            ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarNoticia(${not.id}, '${escapeHtml(not.titulo)}')" title="Eliminar"><i class="bi bi-trash"></i></button>` 
            : '';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="d-flex justify-content-center">
                    ${portadaHTML}
                </div>
            </td>
            <td><strong>${escapeHtml(not.titulo)}</strong><br><small class="text-muted">${escapeHtml(not.resumen || '')}</small></td>
            <td><span class="badge ${catBadgeClase}">${escapeHtml(cat)}</span></td>
            <td>${not.fecha_publicacion ? not.fecha_publicacion.split('-').reverse().join('/') : ''}</td>
            <td>${escapeHtml(not.autor)}</td>
            <td class="text-center">${estadoBadge}</td>
            <td class="text-center">
                <div class="d-flex justify-content-center">
                    ${btnEditar}
                    ${btnGaleria}
                    ${btnEliminar}
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function abrirModalNuevaNoticia() {
    document.getElementById('formNoticia').reset();
    document.getElementById('noticiaId').value = '';
    document.getElementById('modalNoticiaLabel').textContent = 'Crear Noticia';
    
    // Resetear preview
    document.getElementById('portadaPreviewBox').innerHTML = '<i class="bi bi-image text-muted fs-1"></i>';
    
    const modal = new bootstrap.Modal(document.getElementById('modalNoticia'));
    modal.show();
}

async function editarNoticia(id) {
    try {
        const response = await fetch(`ajax/get_noticias.php?id=${id}`, { method: 'POST' });
        const data = await response.json();

        if (data.success) {
            const not = data.datos;
            document.getElementById('noticiaId').value = not.id;
            document.getElementById('notTitulo').value = not.titulo;
            document.getElementById('notCategoria').value = not.categoria || 'General';
            document.getElementById('notAutor').value = not.autor || '';
            document.getElementById('notFechaPublicacion').value = not.fecha_publicacion || '';
            document.getElementById('notEstado').value = not.estado || 'borrador';
            document.getElementById('notResumen').value = not.resumen || '';
            document.getElementById('notContenido').value = not.contenido || '';

            // Renderizar preview de imagen de portada
            const previewBox = document.getElementById('portadaPreviewBox');
            if (not.imagen_principal) {
                const fotoUrl = `https://talento.batidospitaya.com/uploads/noticias/${not.imagen_principal}`;
                previewBox.innerHTML = `<img src="${fotoUrl}" style="width:100%; height:100%; object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='block';"><i class="bi bi-image text-muted fs-1" style="display:none;"></i>`;
            } else {
                previewBox.innerHTML = '<i class="bi bi-image text-muted fs-1"></i>';
            }

            document.getElementById('modalNoticiaLabel').textContent = 'Editar Noticia';
            const modal = new bootstrap.Modal(document.getElementById('modalNoticia'));
            modal.show();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo obtener el detalle de la noticia', 'error');
    }
}

async function guardarNoticia(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btnGuardarNoticia');
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

    try {
        const formData = new FormData(this);
        const response = await fetch('ajax/guardar_noticia.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire('¡Éxito!', data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalNoticia')).hide();
            cargarNoticias();
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', error.message || 'No se pudo guardar la noticia', 'error');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Guardar';
    }
}

function eliminarNoticia(id, titulo) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `Eliminarás la noticia "${titulo}" de forma permanente, incluyendo su portada y todas las fotos en su galería.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('ajax/eliminar_noticia.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const data = await response.json();

                if (data.success) {
                    Swal.fire('¡Eliminado!', data.message, 'success');
                    cargarNoticias();
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', error.message || 'No se pudo eliminar la noticia', 'error');
            }
        }
    });
}

function inicializarFiltroPortadaNoticia() {
    const fileInput = document.getElementById('notPortada');
    const previewBox = document.getElementById('portadaPreviewBox');
    
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

// --- SUB-SECCIÓN: GALERÍA DE FOTOS ---

function gestionarGaleria(noticiaId, titulo) {
    document.getElementById('galeriaNoticiaId').value = noticiaId;
    document.getElementById('modalGaleriaLabel').textContent = `Galería de Fotos: ${titulo}`;
    
    // Cargar fotos
    cargarFotosGaleria(noticiaId);
    
    const modal = new bootstrap.Modal(document.getElementById('modalGaleria'));
    modal.show();
}

async function cargarFotosGaleria(noticiaId) {
    const container = document.getElementById('galeriaFotosContainer');
    container.innerHTML = `
        <div class="col-12 text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando fotos...</span>
            </div>
        </div>
    `;

    try {
        const response = await fetch(`ajax/get_galeria.php?noticia_id=${noticiaId}`);
        const data = await response.json();

        if (data.success) {
            renderizarFotosGaleria(data.datos);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        container.innerHTML = `<div class="col-12 text-center text-danger py-4">Error al cargar la galería: ${error.message}</div>`;
    }
}

function renderizarFotosGaleria(fotos) {
    const container = document.getElementById('galeriaFotosContainer');
    container.innerHTML = '';

    if (fotos.length === 0) {
        container.innerHTML = '<div class="col-12 text-center text-muted py-4">Esta noticia aún no tiene fotos en su galería</div>';
        return;
    }

    fotos.forEach(foto => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-3';
        const fotoUrl = `https://talento.batidospitaya.com/uploads/noticias/galeria/${foto.ruta_foto}`;
        
        col.innerHTML = `
            <div class="galeria-item-card">
                <img src="${fotoUrl}" class="galeria-item-img" alt="Foto galería">
                <button class="galeria-item-delete" onclick="eliminarFotoGaleria(${foto.id})" title="Eliminar foto">
                    <i class="bi bi-trash-fill"></i>
                </button>
            </div>
        `;
        container.appendChild(col);
    });
}

async function subirFotoGaleriaDirecto() {
    const fileInput = document.getElementById('inputFotoGaleria');
    const noticiaId = document.getElementById('galeriaNoticiaId').value;
    const file = fileInput.files[0];

    if (!file) return;

    const formData = new FormData();
    formData.append('noticia_id', noticiaId);
    formData.append('foto_galeria', file);

    Swal.fire({
        title: 'Subiendo imagen...',
        text: 'Por favor espera',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch('ajax/guardar_galeria.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            Swal.close();
            // Recargar fotos
            cargarFotosGaleria(noticiaId);
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', error.message || 'No se pudo subir la foto', 'error');
    } finally {
        fileInput.value = ''; // Limpiar input para permitir seleccionar la misma foto
    }
}

function eliminarFotoGaleria(fotoId) {
    const noticiaId = document.getElementById('galeriaNoticiaId').value;
    
    Swal.fire({
        title: '¿Estás seguro?',
        text: 'Esta foto se eliminará permanentemente de la galería de la noticia.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('ajax/eliminar_galeria.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: fotoId })
                });
                const data = await response.json();

                if (data.success) {
                    Swal.fire('¡Eliminado!', data.message, 'success');
                    cargarFotosGaleria(noticiaId);
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', error.message || 'No se pudo eliminar la foto', 'error');
            }
        }
    });
}

// ==============================================================================
// SECCIÓN: ÁREAS DE LA EMPRESA
// ==============================================================================

async function cargarAreas() {
    try {
        const response = await fetch('ajax/get_areas.php', { method: 'POST' });
        const data = await response.json();

        // En caso de que devuelva error directo
        if (data.error) {
            throw new Error(data.error);
        }

        renderizarAreas(data);
    } catch (error) {
        console.error('Error:', error);
        document.getElementById('tbodyAreas').innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger py-4">
                    <i class="bi bi-exclamation-triangle-fill fs-4 d-block mb-2"></i>
                    Error al cargar las áreas: ${error.message}
                </td>
            </tr>
        `;
    }
}

function renderizarAreas(datos) {
    const tbody = document.getElementById('tbodyAreas');
    tbody.innerHTML = '';

    if (!datos || datos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No hay áreas de empresa registradas</td></tr>';
        return;
    }

    datos.forEach(area => {
        const estadoBadge = area.activo == 1 
            ? '<span class="badge bg-success">Activa</span>' 
            : '<span class="badge bg-secondary">Inactiva</span>';

        const btnEditar = canEdit 
            ? `<button class="btn btn-sm btn-outline-primary me-1" onclick="editarArea(${area.id})" title="Editar"><i class="bi bi-pencil"></i></button>` 
            : '';
        const btnEliminar = canDelete 
            ? `<button class="btn btn-sm btn-outline-danger" onclick="eliminarArea(${area.id}, '${escapeHtml(area.titulo)}')" title="Eliminar"><i class="bi bi-trash"></i></button>` 
            : '';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="fs-4 text-secondary text-center">
                    <i class="bi ${escapeHtml(area.icono || 'bi-square')}"></i>
                </div>
            </td>
            <td><strong>${escapeHtml(area.titulo)}</strong></td>
            <td><small class="text-muted text-wrap d-block" style="max-height: 80px; overflow-y: auto;">${escapeHtml(area.descripcion || '')}</small></td>
            <td class="text-center">${area.orden}</td>
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

function abrirModalNuevaArea() {
    document.getElementById('formArea').reset();
    document.getElementById('areaId').value = '';
    document.getElementById('modalAreaLabel').textContent = 'Agregar Área';
    
    const modal = new bootstrap.Modal(document.getElementById('modalArea'));
    modal.show();
}

async function editarArea(id) {
    try {
        // Obtenemos todas y filtramos localmente o por ajax. Como get_areas devuelve todas, podemos filtrar localmente
        const response = await fetch('ajax/get_areas.php', { method: 'POST' });
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }

        const area = data.find(a => a.id == id);
        if (area) {
            document.getElementById('areaId').value = area.id;
            document.getElementById('areaIcono').value = area.icono;
            document.getElementById('areaTitulo').value = area.titulo;
            document.getElementById('areaOrden').value = area.orden;
            document.getElementById('areaDescripcion').value = area.descripcion;
            document.getElementById('areaActivo').checked = area.activo == 1;

            document.getElementById('modalAreaLabel').textContent = 'Editar Área';
            const modal = new bootstrap.Modal(document.getElementById('modalArea'));
            modal.show();
        } else {
            throw new Error("Área no encontrada.");
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo obtener el detalle del área', 'error');
    }
}

async function guardarArea(e) {
    e.preventDefault();
    const btnSubmit = document.getElementById('btnGuardarArea');
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

    try {
        const formData = new FormData(this);
        // Si el switch está apagado, forzar activo = 0
        if (!document.getElementById('areaActivo').checked) {
            formData.set('activo', '0');
        } else {
            formData.set('activo', '1');
        }

        const response = await fetch('ajax/guardar_area.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            Swal.fire('¡Éxito!', data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalArea')).hide();
            cargarAreas();
        } else {
            throw new Error(data.error || data.mensaje);
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', error.message || 'No se pudo guardar el área', 'error');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Guardar';
    }
}

function eliminarArea(id, titulo) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: `Eliminarás el área "${titulo}" de forma permanente del portal público.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('ajax/eliminar_area.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id=${id}`
                });
                const data = await response.json();

                if (data.success) {
                    Swal.fire('¡Eliminado!', data.message, 'success');
                    cargarAreas();
                } else {
                    throw new Error(data.error || data.mensaje);
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', error.message || 'No se pudo eliminar el área', 'error');
            }
        }
    });
}
