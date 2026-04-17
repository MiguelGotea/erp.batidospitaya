// ============================================
// VARIABLES GLOBALES
// ============================================

let idProductoActual = 0;
let componentesReceta = [];
let variacionesLocal = [];
let fichaTecnicaLocal = [];

// ============================================
// INICIALIZACIÓN
// ============================================

$(document).ready(async function () { // Made the function async
    // Obtener ID del producto de la URL
    const urlParams = new URLSearchParams(window.location.search);
    idProductoActual = parseInt(urlParams.get('id')) || 0;

    // Modales eliminados (ahora es inline)

    // Cargar catálogos
    await cargarCatalogos(); // Await the catalog loading

    // Si es edición, cargar datos del producto
    if (idProductoActual > 0) {
        await cargarDatosProducto(); // Await product data loading
    }

    // Manejar envío del formulario principal
    $('#formProducto').on('submit', function (e) {
        e.preventDefault();
        guardarProducto();
    });
});

// ============================================
// NAVEGACIÓN DE PESTAÑAS
// ============================================

function cambiarTab(tabId) {
    // Remover clase active de todos los botones y paneles
    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

    // Activar el botón y panel seleccionado
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
    document.getElementById(`tab-${tabId}`).classList.add('active');
}

// Actualizar badges de pestañas
function actualizarBadges() {
    // Badge de componentes
    const numComponentes = componentesReceta.length;
    const badgeComp = document.getElementById('badgeComponentes');
    if (badgeComp) {
        if (numComponentes > 0) {
            badgeComp.textContent = numComponentes;
            badgeComp.style.display = 'block';
        } else {
            badgeComp.style.display = 'none';
        }
    }

    // Badge de variaciones
    const numVariaciones = variacionesLocal.length;
    const badgeVar = document.getElementById('badgeVariaciones');
    if (badgeVar) {
        if (numVariaciones > 0) {
            badgeVar.textContent = numVariaciones;
            badgeVar.style.display = 'block';
        } else {
            badgeVar.style.display = 'none';
        }
    }

    // Badge de multimedia (fotos + archivos)
    const numFotos = document.querySelectorAll('#galeriaFotos .foto-item').length;
    const numArchivos = document.querySelectorAll('#listaArchivos .archivo-item').length;
    const totalMultimedia = numFotos + numArchivos;
    const badgeMultimedia = document.getElementById('badgeMultimedia');
    if (badgeMultimedia) {
        if (totalMultimedia > 0) {
            badgeMultimedia.textContent = totalMultimedia;
            badgeMultimedia.style.display = 'block';
        } else {
            badgeMultimedia.style.display = 'none';
        }
    }

    // Badge de ficha técnica
    const numFicha = fichaTecnicaLocal.length;
    const badgeFicha = document.getElementById('badgeFicha');
    if (badgeFicha) {
        if (numFicha > 0) {
            badgeFicha.textContent = numFicha;
            badgeFicha.style.display = 'block';
        } else {
            badgeFicha.style.display = 'none';
        }
    }
}

// ============================================
// CARGAR CATÁLOGOS
// ============================================

async function cargarCatalogos() {
    try {
        const response = await fetch('ajax/registro_producto_get_catalogos.php');
        const data = await response.json();

        if (data.success) {
            // Productos Maestros
            let htmlMaestros = '<option value="">Seleccione...</option>';
            data.productos_maestros.forEach(pm => {
                htmlMaestros += `<option value="${pm.id}">${pm.Nombre}</option>`;
            });
            $('#productoMaestro').html(htmlMaestros);

            // Unidades
            let htmlUnidades = '<option value="">Seleccione...</option>';
            data.unidades.forEach(u => {
                htmlUnidades += `<option value="${u.id}">${u.nombre}</option>`;
            });
            $('#unidad').html(htmlUnidades);

            // Grupos
            let htmlGrupos = '<option value="">Seleccione...</option>';
            data.grupos.forEach(g => {
                htmlGrupos += `<option value="${g.id}">${g.nombre}</option>`;
            });
            $('#grupo').html(htmlGrupos);

            // Tipos de Receta
            let htmlTipos = '<option value="">Seleccione...</option>';
            data.tipos_receta.forEach(tr => {
                htmlTipos += `<option value="${tr.id}">${tr.nombre}</option>`;
            });
            $('#tipoReceta').html(htmlTipos);

            // Productos para componentes (excluir el actual)
            let htmlProductos = '<option value="">Seleccione...</option>';
            data.productos_presentacion.forEach(pp => {
                if (pp.id != idProductoActual) {
                    htmlProductos += `<option value="${pp.id}">${pp.Nombre} (${pp.SKU})</option>`;
                }
            });
            $('#inlineComponenteProducto').html(htmlProductos);

        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        console.error('Error cargando catálogos:', error);
        Swal.fire('Error', 'Error al cargar los catálogos', 'error');
    }
}

// ============================================
// CARGAR SUBGRUPOS
// ============================================

async function cargarSubgrupos() {
    const idGrupo = $('#grupo').val();

    if (!idGrupo) {
        $('#subgrupo').html('<option value="">Seleccione grupo primero...</option>');
        return;
    }

    try {
        const response = await fetch('ajax/registro_producto_get_catalogos.php?accion=subgrupos&id_grupo=' + idGrupo);
        const data = await response.json();

        if (data.success) {
            let html = '<option value="">Seleccione...</option>';
            data.subgrupos.forEach(sg => {
                html += `<option value="${sg.id}">${sg.nombre}</option>`;
            });
            $('#subgrupo').html(html);
        }
    } catch (error) {
        console.error('Error cargando subgrupos:', error);
    }
}

// ============================================
// CARGAR DATOS DEL PRODUCTO (EDICIÓN)
// ============================================

async function cargarDatosProducto(cacheBuster = '') {
    try {
        const response = await fetch(`ajax/registro_producto_get_datos.php?id=${idProductoActual}${cacheBuster}`);
        const data = await response.json();

        if (data.success) {
            const p = data.producto;

            // Datos básicos
            $('#sku').val(p.SKU);
            $('#nombre').val(p.Nombre);
            $('#productoMaestro').val(p.id_producto_maestro);
            $('#unidad').val(p.id_unidad_producto);
            $('#cantidad').val(p.cantidad || '0.00');
            $('#esVendible').prop('checked', p.es_vendible === 'SI');
            $('#esComprable').prop('checked', p.es_comprable === 'SI');
            $('#esFabricable').prop('checked', p.es_fabricable === 'SI');
            $('#compraTienda').prop('checked', parseInt(p.compra_tienda) === 1);
            $('#presentacionBasica').prop('checked', parseInt(p.presentacion_basica_inventario) === 1);
            $('#presentacionDespacho').prop('checked', parseInt(p.presentacion_despacho) === 1);
            $('#categoriaInsumo').val(p.categoria_insumo || '');

            if (p.id_subgrupo_presentacion_producto) {
                $('#grupo').val(p.id_grupo);
                await cargarSubgrupos(); // Await the subgrupos loading
                $('#subgrupo').val(p.id_subgrupo_presentacion_producto);
            }

            // Receta
            if (data.receta) {
                $('#tieneReceta').prop('checked', true);
                toggleReceta();
                $('#nombreReceta').val(data.receta.nombre);
                $('#tipoReceta').val(data.receta.id_tipo_receta);
                $('#descripcionReceta').val(data.receta.descripcion);

                // Cargar componentes
                componentesReceta = data.componentes || [];
                renderizarComponentes();
            } else {
                $('#tieneReceta').prop('checked', false);
                toggleReceta();
            }

            // Variaciones
            if (data.variaciones) {
                variacionesLocal = data.variaciones.map(v => ({
                    ...v,
                    es_principal: parseInt(v.es_principal) || 0
                }));
                renderizarVariaciones();
            }

            // Fotos
            if (data.fotos) {
                renderizarFotos(data.fotos);
            }

            // Archivos
            if (data.archivos) {
                renderizarArchivos(data.archivos);
            }

            // Ficha técnica
            if (data.ficha_tecnica) {
                fichaTecnicaLocal = data.ficha_tecnica;
                renderizarFichaTecnica();
            }

            // Actualizar badges
            actualizarBadges();

        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        console.error('Error cargando datos:', error);
        Swal.fire('Error', 'Error al cargar los datos del producto', 'error');
    }
}

// ============================================
// TOGGLE RECETA
// ============================================

function toggleReceta() {
    const tieneReceta = $('#tieneReceta').is(':checked');
    if (tieneReceta) {
        $('#datosReceta').slideDown();
        $('#tipoReceta').attr('required', true);

        // Ocultar campos maestros y quitar obligatoriedad
        $('#contenedorCamposMaestros').hide();
        $('#productoMaestro, #unidad, #cantidad').removeAttr('required');

        // Asignar ID 9 (Unidades) automáticamente
        $('#unidad').val('9');
    } else {
        $('#datosReceta').slideUp();
        $('#tipoReceta').removeAttr('required');

        // Mostrar campos maestros y reponer obligatoriedad
        $('#contenedorCamposMaestros').show();
        $('#productoMaestro, #unidad, #cantidad').attr('required', true);
    }
}

// ============================================
// GUARDAR PRODUCTO
// ============================================

async function guardarProducto() {
    // Validar datos básicos
    if (!$('#formProducto')[0].checkValidity()) {
        $('#formProducto')[0].reportValidity();
        return;
    }

    // Validar receta si está marcada
    if ($('#tieneReceta').is(':checked')) {
        if (!$('#tipoReceta').val()) {
            Swal.fire('Advertencia', 'Complete los datos de la receta', 'warning');
            cambiarTab('receta');
            return;
        }
    }

    // Validar que si hay variaciones, haya una principal
    if (variacionesLocal.length > 0) {
        const tienePrincipal = variacionesLocal.some(v => v.es_principal == 1);
        if (!tienePrincipal) {
            Swal.fire('Advertencia', 'Debe marcar una variación como principal', 'warning');
            cambiarTab('variaciones');
            return;
        }
    }

    // Preparar datos
    const formData = new FormData($('#formProducto')[0]);

    // Agregar flags de checkboxes
    formData.set('es_vendible', $('#esVendible').is(':checked') ? 'SI' : 'NO');
    formData.set('es_comprable', $('#esComprable').is(':checked') ? 'SI' : 'NO');
    formData.set('es_fabricable', $('#esFabricable').is(':checked') ? 'SI' : 'NO');
    formData.set('compra_tienda', $('#compraTienda').is(':checked') ? 1 : 0);
    formData.set('presentacion_basica_inventario', $('#presentacionBasica').is(':checked') ? 1 : 0);
    formData.set('presentacion_despacho', $('#presentacionDespacho').is(':checked') ? 1 : 0);
    formData.set('tiene_receta', $('#tieneReceta').is(':checked') ? 1 : 0);

    // Agregar datos de los arreglos locales (JSON)
    formData.append('componentes', JSON.stringify(componentesReceta));
    formData.append('variaciones', JSON.stringify(variacionesLocal));
    formData.append('fichas', JSON.stringify(fichaTecnicaLocal));

    try {
        const response = await fetch('ajax/registro_producto_guardar.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            await Swal.fire('Éxito', data.message, 'success');

            // Si es nuevo, redirigir a edición
            if (idProductoActual === 0 && data.id_producto) {
                window.location.href = `registro_producto_global.php?id=${data.id_producto}`;
            } else {
                // Recargar datos con cache-busting
                const cacheBuster = `&_=${new Date().getTime()}`;
                cargarDatosProducto(cacheBuster);
            }
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        console.error('Error guardando producto:', error);
        Swal.fire('Error', 'Ocurrió un error al intentar guardar el producto', 'error');
    }
}

// ============================================
// ACTUALIZACIÓN INLINE DE CAMPOS
// ============================================

async function guardarCampoInline(campo, valor) {
    if (idProductoActual === 0) {
        // Si el producto es nuevo, no hacemos nada extra, se guardará con el botón principal
        return;
    }

    try {
        const formData = new FormData();
        formData.append('id', idProductoActual);
        formData.append('campo', campo);
        formData.append('valor', valor);

        const response = await fetch('ajax/registro_producto_update_campo_inline.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            // Notificación sutil (Toast)
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'success',
                title: 'Campo actualizado'
            });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        console.error('Error en actualización inline:', error);
    }
}

// ============================================
// COMPONENTES DE RECETA
// ============================================

function agregarComponenteInline() {
    const idProductoComp = $('#inlineComponenteProducto').val();
    const nombreProductoComp = $('#inlineComponenteProducto option:selected').text();
    const cantidad = parseFloat($('#inlineComponenteCantidad').val());
    const notas = $('#inlineComponenteNotas').val();

    if (!idProductoComp || !cantidad || cantidad <= 0) {
        Swal.fire('Advertencia', 'Complete el producto y una cantidad válida', 'warning');
        return;
    }

    // Validar que no esté ya agregado
    if (componentesReceta.some(c => c.id_presentacion_producto == idProductoComp)) {
        Swal.fire('Advertencia', 'Este producto ya está en la receta', 'warning');
        return;
    }

    // Extraer unidad si está en el texto del option (opcional)
    // O mejor, el renderizado usará lo que venga del servidor después de guardar

    // Agregar al arreglo local
    componentesReceta.push({
        id: -Date.now(),
        id_presentacion_producto: idProductoComp,
        nombre_producto: nombreProductoComp.split(' (')[0], // Limpiar SKU
        cantidad: cantidad,
        unidad: '', // Se llenará al recargar del servidor
        notas: notas
    });

    // Limpiar campos UI
    $('#inlineComponenteProducto').val('');
    $('#inlineComponenteCantidad').val('');
    $('#inlineComponenteNotas').val('');

    renderizarComponentes();
    actualizarBadges();
}

async function eliminarComponente(id) {
    const result = await Swal.fire({
        title: '¿Eliminar componente?',
        text: 'Esta acción borrará el componente de forma definitiva.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        // Si el ID es positivo, ya existe en la base de datos
        if (id > 0) {
            try {
                const response = await fetch('ajax/registro_producto_eliminar_componente.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Exception(data.message);
                }

                Swal.fire('Eliminado', 'El componente ha sido borrado físicamente.', 'success');
            } catch (error) {
                console.error('Error al eliminar componente:', error);
                Swal.fire('Error', 'No se pudo eliminar de la base de datos: ' + error.message, 'error');
                return; // Abortar borrado local si falla el servidor
            }
        }

        // Borrar del arreglo local y actualizar UI
        componentesReceta = componentesReceta.filter(c => c.id != id);
        renderizarComponentes();
        actualizarBadges();
    }
}

function renderizarComponentes() {
    const tbody = $('#tablaComponentes');

    if (componentesReceta.length === 0) {
        tbody.html('<tr><td colspan="5" class="text-center text-muted">No hay componentes agregados</td></tr>');
        $('#badgeComponentes').text('0');
        return;
    }

    let html = '';
    componentesReceta.forEach((comp, index) => {
        html += `
            <tr>
                <td>${index + 1}</td>
                <td>${comp.nombre_producto}</td>
                <td>${comp.cantidad} ${comp.unidad}</td>
                <td>${comp.notas || '-'}</td>
                <td>
                    <button type="button" class="btn-accion btn-eliminar" onclick="eliminarComponente(${comp.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.html(html);
    // Badge se actualiza en actualizarBadges()
}


// ============================================
// VARIACIONES
// ============================================

function agregarVariacionInline() {
    const nombre = $('#inlineVariacionNombre').val().trim();
    const descripcion = $('#inlineVariacionDescripcion').val().trim();

    if (!nombre) {
        Swal.fire('Advertencia', 'El nombre de la variación es obligatorio', 'warning');
        return;
    }

    // Si es la primera, marcarla como principal
    const esPrincipal = (variacionesLocal.length === 0) ? 1 : 0;

    variacionesLocal.push({
        id: -Date.now(),
        nombre: nombre,
        descripcion: descripcion,
        es_principal: esPrincipal
    });

    $('#inlineVariacionNombre').val('');
    $('#inlineVariacionDescripcion').val('');

    renderizarVariaciones();
    actualizarBadges();
}

function marcarPrincipal(id) {
    variacionesLocal = variacionesLocal.map(v => ({
        ...v,
        es_principal: (v.id == id ? 1 : 0)
    }));
    renderizarVariaciones();
}

async function eliminarVariacion(id) {
    const varAEliminar = variacionesLocal.find(v => v.id == id);
    const result = await Swal.fire({
        title: '¿Eliminar variación?',
        text: 'Este cambio se aplicará al guardar el producto',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        const fuePrincipal = varAEliminar && varAEliminar.es_principal == 1;
        variacionesLocal = variacionesLocal.filter(v => v.id != id);

        // Si eliminamos la principal y quedan otras, promover la primera
        if (fuePrincipal && variacionesLocal.length > 0) {
            variacionesLocal[0].es_principal = 1;
        }

        renderizarVariaciones();
        actualizarBadges();
    }
}

function renderizarVariaciones() {
    const lista = $('#listaVariaciones');

    if (variacionesLocal.length === 0) {
        lista.html('<p class="text-center text-muted">No hay variaciones registradas</p>');
        $('#badgeVariaciones').hide();
        return;
    }

    let html = '';
    variacionesLocal.forEach(v => {
        const isPrincipal = (v.es_principal == 1);
        html += `
            <div class="variacion-item ${isPrincipal ? 'principal' : ''}">
                <div class="variacion-selector">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="radioPrincipal" 
                            id="radioPrincipal_${v.id}" ${isPrincipal ? 'checked' : ''} 
                            onclick="marcarPrincipal(${v.id})">
                        <label class="form-check-label small text-muted" for="radioPrincipal_${v.id}">
                            ${isPrincipal ? 'Principal' : 'Marcar Principal'}
                        </label>
                    </div>
                </div>
                <div class="variacion-info">
                    <div class="variacion-nombre">${v.nombre}</div>
                    ${v.descripcion ? `<p class="variacion-descripcion">${v.descripcion}</p>` : ''}
                </div>
                <div class="variacion-acciones">
                    <button type="button" class="btn-accion btn-eliminar" onclick="eliminarVariacion(${v.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });

    lista.html(html);
    $('#badgeVariaciones').text(variacionesLocal.length).show();
}

// ============================================
// FOTOS
// ============================================

async function subirFoto() {
    if (idProductoActual === 0) {
        Swal.fire('Advertencia', 'Primero debe guardar el producto', 'warning');
        return;
    }

    const input = document.getElementById('inputFoto');
    const file = input.files[0];

    if (!file) return;

    // Validar tamaño (10MB)
    if (file.size > 10 * 1024 * 1024) {
        Swal.fire('Error', 'El archivo no debe superar 10MB', 'error');
        input.value = '';
        return;
    }

    // Validar tipo
    if (!file.type.match('image.*')) {
        Swal.fire('Error', 'Solo se permiten imágenes', 'error');
        input.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('foto', file);
    formData.append('id_producto', idProductoActual);

    Swal.fire({
        title: 'Subiendo foto...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch('ajax/registro_producto_subir_foto.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            input.value = '';
            cargarDatosProducto();
            Swal.fire('Éxito', data.message, 'success');
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        console.error('Error subiendo foto:', error);
        Swal.fire('Error', 'Error al subir la foto', 'error');
    }
}

async function eliminarFoto(id) {
    const result = await Swal.fire({
        title: '¿Eliminar foto?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch('ajax/registro_producto_eliminar_foto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            const data = await response.json();

            if (data.success) {
                cargarDatosProducto();
                Swal.fire('Eliminado', data.message, 'success');
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            console.error('Error eliminando foto:', error);
            Swal.fire('Error', 'Error al eliminar la foto', 'error');
        }
    }
}

function renderizarFotos(fotos) {
    const galeria = $('#galeriaFotos');

    if (fotos.length === 0) {
        galeria.html('<p class="text-center text-muted">No hay fotos cargadas</p>');
        $('#badgeFotos').text('0');
        return;
    }

    let html = '';
    fotos.forEach(f => {
        const rutaNormalizada = normalizarRuta(f.ruta);
        html += `
            <div class="foto-item" onclick="abrirPreview('${rutaNormalizada}', '${f.nombre}')">
                <img src="${rutaNormalizada}" alt="${f.nombre}">
                <div class="foto-overlay">
                    <div class="foto-nombre">${f.nombre}</div>
                    <button class="btn-accion btn-eliminar" onclick="event.stopPropagation(); eliminarFoto(${f.id})">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        `;
    });

    galeria.html(html);
    $('#badgeFotos').text(fotos.length);
}

// ============================================
// ARCHIVOS
// ============================================

async function subirArchivo() {
    if (idProductoActual === 0) {
        Swal.fire('Advertencia', 'Primero debe guardar el producto', 'warning');
        return;
    }

    const input = document.getElementById('inputArchivo');
    const file = input.files[0];

    if (!file) return;

    // Validar tamaño (10MB)
    if (file.size > 10 * 1024 * 1024) {
        Swal.fire('Error', 'El archivo no debe superar 10MB', 'error');
        input.value = '';
        return;
    }

    const formData = new FormData();
    formData.append('archivo', file);
    formData.append('id_producto', idProductoActual);

    Swal.fire({
        title: 'Subiendo archivo...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch('ajax/registro_producto_subir_archivo.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            input.value = '';
            cargarDatosProducto();
            Swal.fire('Éxito', data.message, 'success');
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (error) {
        console.error('Error subiendo archivo:', error);
        Swal.fire('Error', 'Error al subir el archivo', 'error');
    }
}

async function eliminarArchivo(id) {
    const result = await Swal.fire({
        title: '¿Eliminar archivo?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            const response = await fetch('ajax/registro_producto_eliminar_archivo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            const data = await response.json();

            if (data.success) {
                cargarDatosProducto();
                Swal.fire('Eliminado', data.message, 'success');
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            console.error('Error eliminando archivo:', error);
            Swal.fire('Error', 'Error al eliminar el archivo', 'error');
        }
    }
}

function renderizarArchivos(archivos) {
    const lista = $('#listaArchivos');

    if (archivos.length === 0) {
        lista.html('<p class="text-center text-muted">No hay archivos adjuntos</p>');
        $('#badgeArchivos').text('0');
        return;
    }

    let html = '';
    archivos.forEach(a => {
        const icono = obtenerIconoArchivo(a.nombre);
        const rutaNormalizada = normalizarRuta(a.ruta);
        html += `
            <div class="archivo-item">
                <div class="archivo-info">
                    <i class="${icono} archivo-icon"></i>
                    <div class="archivo-detalles">
                        <div class="archivo-nombre">${a.nombre}</div>
                        ${a.descripcion ? `<p class="archivo-descripcion">${a.descripcion}</p>` : ''}
                    </div>
                </div>
                <div class="archivo-acciones">
                    <a href="${rutaNormalizada}" target="_blank" class="btn-accion btn-ver">
                        <i class="bi bi-eye"></i>
                    </a>
                    <button type="button" class="btn-accion btn-eliminar" onclick="eliminarArchivo(${a.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });

    lista.html(html);
    $('#badgeArchivos').text(archivos.length);
}

function normalizarRuta(ruta) {
    if (!ruta) return '';
    // Si la ruta contiene modulos/POS/ al inicio, lo quitamos para que sea relativa a la ubicación actual
    return ruta.replace(/^modulos\/POS\//, '');
}

// ============================================
// VISTA PREVIA DE IMÁGENES
// ============================================

function abrirPreview(ruta, nombre) {
    const modal = document.getElementById("imagePreviewModal");
    const modalImg = document.getElementById("imgPreview");
    const captionText = document.getElementById("previewCaption");

    modal.style.display = "block";
    modalImg.src = ruta;
    captionText.innerHTML = nombre;

    // Cerrar con Escape
    $(document).on('keydown.preview', function (e) {
        if (e.key === "Escape") cerrarPreview();
    });

    // Cerrar al hacer clic fuera de la imagen
    $(modal).on('click.preview', function (e) {
        if (e.target === modal || e.target.classList.contains('preview-close')) {
            cerrarPreview();
        }
    });
}

function cerrarPreview() {
    const modal = document.getElementById("imagePreviewModal");
    modal.style.display = "none";
    $(document).off('keydown.preview');
    $(modal).off('click.preview');
}

function obtenerIconoArchivo(nombre) {
    const ext = nombre.split('.').pop().toLowerCase();
    const iconos = {
        'pdf': 'bi bi-file-earmark-pdf-fill',
        'doc': 'bi bi-file-earmark-word-fill',
        'docx': 'bi bi-file-earmark-word-fill',
        'xls': 'bi bi-file-earmark-excel-fill',
        'xlsx': 'bi bi-file-earmark-excel-fill',
        'default': 'bi bi-file-earmark-fill'
    };
    return iconos[ext] || iconos['default'];
}

// ============================================
// FICHA TÉCNICA
// ============================================

function agregarFichaInline() {
    const campo = $('#inlineFichaCampo').val().trim();
    const valor = $('#inlineFichaValor').val().trim();

    if (!campo || !valor) {
        Swal.fire('Advertencia', 'El campo y el valor son obligatorios', 'warning');
        return;
    }

    fichaTecnicaLocal.push({
        id: -Date.now(),
        campo: campo,
        descripcion: valor
    });

    $('#inlineFichaCampo').val('');
    $('#inlineFichaValor').val('');

    renderizarFichaTecnica();
    actualizarBadges();
}

async function eliminarFicha(id) {
    const result = await Swal.fire({
        title: '¿Eliminar campo?',
        text: 'Este cambio se aplicará al guardar el producto',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        fichaTecnicaLocal = fichaTecnicaLocal.filter(f => f.id != id);
        renderizarFichaTecnica();
        actualizarBadges();
    }
}

function renderizarFichaTecnica() {
    const lista = $('#listaFichaTecnica');

    if (fichaTecnicaLocal.length === 0) {
        lista.html('<p class="text-center text-muted">No hay campos en la ficha técnica</p>');
        $('#badgeFicha').hide();
        return;
    }

    let html = '';
    fichaTecnicaLocal.forEach(f => {
        html += `
            <div class="ficha-item">
                <div class="ficha-info">
                    <div class="ficha-campo">${f.campo}</div>
                    <p class="ficha-valor">${f.descripcion}</p>
                </div>
                <div class="ficha-acciones">
                    <button type="button" class="btn-accion btn-eliminar" onclick="eliminarFicha(${f.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });

    lista.html(html);
    $('#badgeFicha').text(fichaTecnicaLocal.length).show();
}