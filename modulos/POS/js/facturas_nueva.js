/* ===================================================
   facturas_nueva.js — Nueva Factura POS
   =================================================== */

// Estado local
let detalleFactura = []; // array de {id_presentacion, nombre, unidad, cantidad, costo_total_iva}
let todosProductos = []; // cache de productos elegibles
let todosProveedores = []; // cache de proveedores para autocomplete
let proveedorSeleccionadoIdx = -1; // índice para navegación por teclado

$(document).ready(function () {
    cargarProveedores();
    cargarProductosElegibles();

    // Búsqueda en panel derecho con debounce
    let timerBuscar;
    $('#buscarProducto').on('input', function () {
        clearTimeout(timerBuscar);
        timerBuscar = setTimeout(() => filtrarProductos($(this).val().trim()), 280);
    });

    // Autocomplete Proveedores
    setupProveedorAutocomplete();

    // Submit formulario
    $('#formFactura').on('submit', function (e) {
        e.preventDefault();
        guardarFactura();
    });
});

/* ---- Cargar proveedores ---- */
function cargarProveedores() {
    $.get('ajax/facturas_get_proveedores.php', function (res) {
        if (res.success) {
            todosProveedores = res.data;
        }
    }, 'json');
}

/* ---- Lógica Autocomplete Proveedores ---- */
function setupProveedorAutocomplete() {
    const $input       = $('#proveedorSearch');
    const $hidden      = $('#proveedorFactura');
    const $suggestions = $('#proveedorSuggestions');

    $input.on('input', function () {
        const q = $(this).val().trim().toLowerCase();
        $hidden.val(''); // Reset id if typing
        
        if (q.length < 1) {
            $suggestions.hide().empty();
            return;
        }

        const filtrados = todosProveedores.filter(p => 
            (p.nombre || '').toLowerCase().includes(q) || 
            (p.ruc_nit || '').toLowerCase().includes(q)
        ).slice(0, 10); // Limit to 10 results

        if (filtrados.length === 0) {
            $suggestions.html('<div class="autocomplete-item text-muted">Sin resultados</div>').show();
            return;
        }

        let html = '';
        filtrados.forEach((p, idx) => {
            html += `
            <div class="autocomplete-item" data-id="${p.id}" data-nombre="${escAttr(p.nombre)}">
                <span class="prov-name">${escHtml(p.nombre)}</span>
                ${p.ruc_nit ? `<span class="prov-ruc">RUC: ${escHtml(p.ruc_nit)}</span>` : ''}
            </div>`;
        });

        $suggestions.html(html).show();
        proveedorSeleccionadoIdx = -1;
    });

    // Delegación de click para selección
    $suggestions.on('click', '.autocomplete-item', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        
        if (!id) return;

        $input.val(nombre);
        $hidden.val(id);
        $suggestions.hide().empty();
    });

    // Navegación con teclado
    $input.on('keydown', function (e) {
        const $items = $suggestions.find('.autocomplete-item:not(.text-muted)');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            proveedorSeleccionadoIdx = Math.min(proveedorSeleccionadoIdx + 1, $items.length - 1);
            $items.removeClass('active').eq(proveedorSeleccionadoIdx).addClass('active');
            // Scroll to item if needed
            $items.eq(proveedorSeleccionadoIdx)[0].scrollIntoView({ block: 'nearest' });
        } 
        else if (e.key === 'ArrowUp') {
            e.preventDefault();
            proveedorSeleccionadoIdx = Math.max(proveedorSeleccionadoIdx - 1, -1);
            $items.removeClass('active');
            if (proveedorSeleccionadoIdx >= 0) {
                $items.eq(proveedorSeleccionadoIdx).addClass('active');
                $items.eq(proveedorSeleccionadoIdx)[0].scrollIntoView({ block: 'nearest' });
            }
        } 
        else if (e.key === 'Enter') {
            if (proveedorSeleccionadoIdx >= 0) {
                e.preventDefault();
                $items.eq(proveedorSeleccionadoIdx).click();
            }
        }
        else if (e.key === 'Escape') {
            $suggestions.hide().empty();
        }
    });

    // Cerrar al hacer click fuera
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.autocomplete-wrapper').length) {
            $suggestions.hide().empty();
        }
    });
}

/* ---- Cargar productos elegibles (panel derecho) ---- */
function cargarProductosElegibles() {
    $('#listaProductosElegibles').html(`
        <p class="no-productos-msg">
            <span class="spinner-border spinner-border-sm me-1"></span> Cargando…
        </p>`);

    $.get('ajax/facturas_get_productos_elegibles.php', function (res) {
        if (!res.success || res.data.length === 0) {
            $('#listaProductosElegibles').html(`
                <p class="no-productos-msg">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    No hay productos marcados como "¿Es comprable para facturas?".
                </p>`);
            return;
        }
        todosProductos = res.data;
        renderizarListaProductos(todosProductos);
    }, 'json').fail(function () {
        $('#listaProductosElegibles').html(`<p class="no-productos-msg text-danger">Error al cargar productos.</p>`);
    });
}

/* ---- Renderizar lista de productos elegibles ---- */
function renderizarListaProductos(lista) {
    if (!lista || lista.length === 0) {
        $('#listaProductosElegibles').html(`<p class="no-productos-msg">Sin resultados.</p>`);
        return;
    }

    let html = '';
    lista.forEach(p => {
        html += `
        <div class="producto-card-elegible" data-id="${p.id}">
            <div class="prod-info">
                <span class="prod-nombre">${escHtml(p.Nombre)}</span>
                <span class="prod-presentacion">${escHtml(p.nombre_maestro)} · ${escHtml(p.nombre_unidad || '—')}</span>
            </div>
            <button class="btn-agregar-prod" onclick="agregarProducto(${p.id}, '${escAttr(p.Nombre)}', '${escAttr(p.nombre_unidad || '')}')">
                <i class="bi bi-plus-lg"></i> Agregar
            </button>
        </div>`;
    });
    $('#listaProductosElegibles').html(html);
}

/* ---- Filtrar localmente ---- */
function filtrarProductos(q) {
    if (q === '') {
        renderizarListaProductos(todosProductos);
        return;
    }
    const ql = q.toLowerCase();
    const filtrados = todosProductos.filter(p =>
        (p.Nombre        || '').toLowerCase().includes(ql) ||
        (p.nombre_maestro || '').toLowerCase().includes(ql) ||
        (p.SKU            || '').toLowerCase().includes(ql)
    );
    renderizarListaProductos(filtrados);
}

/* ---- Agregar producto al detalle ---- */
function agregarProducto(id, nombre, unidad) {
    // Verificar si ya está en el detalle
    const existe = detalleFactura.find(d => d.id_presentacion === id);
    if (existe) {
        // Animar la fila existente para indicar que ya está
        const fila = $(`#fila-${id}`);
        fila.addClass('table-warning');
        setTimeout(() => fila.removeClass('table-warning'), 800);
        fila.find('.input-cantidad').focus();
        return;
    }

    detalleFactura.push({
        id_presentacion: id,
        nombre:          nombre,
        unidad:          unidad,
        cantidad:        1,
        costo_total_iva: 0
    });

    actualizarTablaDetalle();
}

/* ---- Renderizar tabla de detalle (izquierda) ---- */
function actualizarTablaDetalle() {
    $('#contadorProductos').text(`${detalleFactura.length} ítem(s)`);

    if (detalleFactura.length === 0) {
        $('#tablaDetalleBody').html(`
            <tr id="filaVacia">
                <td colspan="5" class="empty-detalle">
                    <i class="bi bi-box-seam me-2"></i>
                    Selecciona productos desde el panel derecho
                </td>
            </tr>`);
        actualizarTotal();
        return;
    }

    let html = '';
    detalleFactura.forEach((item, idx) => {
        const costoUnit = (item.cantidad > 0)
            ? (item.costo_total_iva / item.cantidad).toFixed(4)
            : '0.0000';

        html += `
        <tr id="fila-${item.id_presentacion}" data-idx="${idx}">
            <td>
                <strong style="font-size:.875rem;">${escHtml(item.nombre)}</strong>
                <small class="d-block text-muted">${escHtml(item.unidad)}</small>
            </td>
            <td class="text-end" style="width:140px;">
                <div class="qty-control">
                    <button type="button" class="qty-btn" onclick="modificarCantidad(${idx}, -1)" title="Disminuir">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                    <input type="number" class="qty-input input-cantidad"
                           value="${item.cantidad}" min="0.01" step="0.01"
                           data-idx="${idx}"
                           oninput="actualizarItem(${idx}, 'cantidad', this.value)">
                    <button type="button" class="qty-btn" onclick="modificarCantidad(${idx}, 1)" title="Aumentar">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            </td>
            <td class="text-end" style="min-width:130px;">
                <input type="number" class="input-tabla input-costo"
                       value="${item.costo_total_iva}" min="0" step="0.01"
                       data-idx="${idx}"
                       onchange="actualizarItem(${idx}, 'costo_total_iva', this.value)">
            </td>
            <td class="costo-unitario-cell" id="unitario-${item.id_presentacion}">
                C$ ${parseFloat(costoUnit).toLocaleString('es-NI', {minimumFractionDigits: 4})}
            </td>
            <td class="text-center">
                <button class="btn-eliminar-fila" onclick="eliminarItem(${idx})" title="Quitar">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </td>
        </tr>`;
    });

    $('#tablaDetalleBody').html(html);
    actualizarTotal();
}

/* ---- Actualizar valor de un campo en el detalle ---- */
function actualizarItem(idx, campo, valor) {
    detalleFactura[idx][campo] = parseFloat(valor) || 0;

    // Recalcular costo unitario en vivo
    const item = detalleFactura[idx];
    const costoUnit = (item.cantidad > 0)
        ? (item.costo_total_iva / item.cantidad)
        : 0;

    $(`#unitario-${item.id_presentacion}`).text(
        'C$ ' + costoUnit.toLocaleString('es-NI', { minimumFractionDigits: 4, maximumFractionDigits: 4 })
    );

    actualizarTotal();
}

/* ---- Eliminar ítem del detalle ---- */
function eliminarItem(idx) {
    detalleFactura.splice(idx, 1);
    actualizarTablaDetalle();
}

/* ---- Modificar cantidad con botones +/- ---- */
function modificarCantidad(idx, delta) {
    const item = detalleFactura[idx];
    let nuevaCant = (parseFloat(item.cantidad) || 0) + delta;
    
    if (nuevaCant < 0) nuevaCant = 0;
    
    // Si queremos que el mínimo sea 1 si se intenta bajar de 1, se puede ajustar. 
    // Pero permitiremos 0 si el usuario lo desea bajar todo.
    
    actualizarItem(idx, 'cantidad', nuevaCant);
    
    // Forzamos actualización de la tabla para refrescar el input visualmente
    actualizarTablaDetalle();
}

/* ---- Actualizar total mostrado ---- */
function actualizarTotal() {
    const total = detalleFactura.reduce((s, d) => s + (parseFloat(d.costo_total_iva) || 0), 0);
    $('#totalFacturaMonto').text(
        'C$ ' + total.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    );
}

/* ---- Guardar factura ---- */
function guardarFactura() {
    const numero    = $('#numeroFactura').val().trim();
    const fecha     = $('#fechaFactura').val();
    const proveedor = $('#proveedorFactura').val();
    const notas     = $('#notasFactura').val().trim();

    if (!numero)           { alertError('El número de factura es obligatorio.'); return; }
    if (!fecha)            { alertError('La fecha es obligatoria.'); return; }
    if (!proveedor)        { alertError('Debe seleccionar un proveedor.'); return; }
    if (detalleFactura.length === 0) { alertError('Debe agregar al menos un producto.'); return; }

    // Validar montos
    for (let i = 0; i < detalleFactura.length; i++) {
        const d = detalleFactura[i];
        if (d.cantidad <= 0) {
            alertError(`La cantidad debe ser mayor a 0 en "${d.nombre}".`); return;
        }
    }

    const $btn = $('#btnGuardar');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Guardando…');

    $.post('ajax/facturas_guardar.php', {
        numero_factura: numero,
        fecha:          fecha,
        id_proveedor:   proveedor,
        notas:          notas,
        detalle:        JSON.stringify(detalleFactura)
    }, function (res) {
        if (res.success) {
            Swal.fire({
                title:             '¡Factura guardada!',
                text:              res.message,
                icon:              'success',
                confirmButtonText: 'Ver historial',
                confirmButtonColor:'#51B8AC'
            }).then(() => {
                window.location.href = 'facturas_historial.php';
            });
        } else {
            alertError(res.message);
            $btn.prop('disabled', false).html('<i class="bi bi-save2-fill"></i> Guardar Factura');
        }
    }, 'json').fail(function () {
        alertError('Error de conexión. Intenta de nuevo.');
        $btn.prop('disabled', false).html('<i class="bi bi-save2-fill"></i> Guardar Factura');
    });
}

/* ---- Helpers ---- */
function alertError(msg) {
    Swal.fire({ title: 'Error', text: msg, icon: 'error', confirmButtonColor: '#0E544C' });
}

function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escAttr(str) {
    return String(str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}
