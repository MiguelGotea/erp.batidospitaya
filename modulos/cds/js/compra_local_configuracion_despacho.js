/* ===================================================
   Configuración de Plan de Despacho - JavaScript
   =================================================== */

let sucursales = [];
let configuraciones = {};
let currentSucursal = null;

// Días de la semana
const diasSemana = [
    { num: 1, nombre: 'L', nombreCompleto: 'Lunes' },
    { num: 2, nombre: 'M', nombreCompleto: 'Martes' },
    { num: 3, nombre: 'Mi', nombreCompleto: 'Miércoles' },
    { num: 4, nombre: 'J', nombreCompleto: 'Jueves' },
    { num: 5, nombre: 'V', nombreCompleto: 'Viernes' },
    { num: 6, nombre: 'S', nombreCompleto: 'Sábado' },
    { num: 7, nombre: 'D', nombreCompleto: 'Domingo' }
];

// Inicializar
$(document).ready(function () {
    cargarSucursales();
});

// Cargar sucursales activas
function cargarSucursales() {
    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_get_sucursales.php',
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                sucursales = response.sucursales;
                renderizarTabs();
                if (sucursales.length > 0) {
                    currentSucursal = sucursales[0].codigo;
                    cargarConfiguracion(currentSucursal);
                }
            } else {
                mostrarError('Error al cargar sucursales: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión al cargar sucursales');
        }
    });
}

// Renderizar tabs de sucursales
function renderizarTabs() {
    const tabsHtml = sucursales.map((sucursal, index) => `
        <li class="nav-item" role="presentation">
            <button class="nav-link ${index === 0 ? 'active' : ''}" 
                    id="tab-${sucursal.codigo}" 
                    data-bs-toggle="tab" 
                    data-bs-target="#content-${sucursal.codigo}" 
                    type="button" 
                    role="tab"
                    onclick="cambiarSucursal('${sucursal.codigo}')">
                ${sucursal.nombre}
            </button>
        </li>
    `).join('');

    const contentHtml = sucursales.map((sucursal, index) => `
        <div class="tab-pane fade ${index === 0 ? 'show active' : ''}" 
             id="content-${sucursal.codigo}" 
             role="tabpanel">
            <div id="table-container-${sucursal.codigo}">
                <div class="loader-container">
                    <div class="loader"></div>
                </div>
            </div>
        </div>
    `).join('');

    $('#sucursalesTabs').html(tabsHtml);
    $('#sucursalesTabContent').html(contentHtml);
}

// Cambiar sucursal activa
function cambiarSucursal(codigoSucursal) {
    currentSucursal = codigoSucursal;
    if (!configuraciones[codigoSucursal]) {
        cargarConfiguracion(codigoSucursal);
    }
}

// Cargar configuración de una sucursal
function cargarConfiguracion(codigoSucursal) {
    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_get_configuracion.php',
        method: 'POST',
        data: { codigo_sucursal: codigoSucursal },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                configuraciones[codigoSucursal] = response.configuracion;
                renderizarTabla(codigoSucursal);
            } else {
                mostrarError('Error al cargar configuración: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión al cargar configuración');
        }
    });
}

// Renderizar tabla de configuración
function renderizarTabla(codigoSucursal) {
    const config = configuraciones[codigoSucursal] || [];

    let tableHtml = `
        <div class="table-responsive">
            <table class="table config-table">
                <thead>
                    <tr>
                        <th style="width: 250px;">Producto</th>
                        <th style="width: 100px;" title="Consumo base diario">Cons. Base</th>
                        <th style="width: 80px;" title="Días de contingencia">Lead Time</th>
                        <th style="width: 80px;" title="Vida útil en días">Vida Útil</th>
                        <th style="width: 80px;" title="Factor de evento">Factor</th>
                        ${diasSemana.map(dia => `<th title="${dia.nombreCompleto}">${dia.nombre}</th>`).join('')}
                        <th style="width: 100px;">Estado</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // Productos configurados
    const productosAgrupados = agruparPorProducto(config);

    for (const [idProducto, datos] of Object.entries(productosAgrupados)) {
        const isInactive = datos.status === 'inactivo';
        tableHtml += `
            <tr class="${isInactive ? 'inactive-row' : ''}" data-producto-id="${idProducto}">
                <td>${datos.nombre}</td>
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm" 
                           value="${datos.base_consumption || 0}" 
                           ${!puedeEditar || isInactive ? 'disabled' : ''}
                           onchange="updateField(${idProducto}, '${codigoSucursal}', 'base_consumption', this.value)">
                </td>
                <td>
                    <input type="number" step="1" class="form-control form-control-sm" 
                           value="${datos.lead_time_days || 0}" 
                           ${!puedeEditar || isInactive ? 'disabled' : ''}
                           onchange="updateField(${idProducto}, '${codigoSucursal}', 'lead_time_days', this.value)">
                </td>
                <td>
                    <input type="number" step="1" class="form-control form-control-sm" 
                           value="${datos.shelf_life_days || 7}" 
                           ${!puedeEditar || isInactive ? 'disabled' : ''}
                           onchange="updateField(${idProducto}, '${codigoSucursal}', 'shelf_life_days', this.value)">
                </td>
                <td>
                    <input type="number" step="0.1" class="form-control form-control-sm" 
                           value="${datos.event_factor || 1}" 
                           ${!puedeEditar || isInactive ? 'disabled' : ''}
                           onchange="updateField(${idProducto}, '${codigoSucursal}', 'event_factor', this.value)">
                </td>
                ${diasSemana.map(dia => {
            const tieneEntrega = datos.dias.includes(dia.num);
            return `
                        <td class="day-cell ${tieneEntrega ? 'active' : ''} ${!puedeEditar || isInactive ? 'disabled' : ''}" 
                            data-dia="${dia.num}"
                            onclick="${puedeEditar && !isInactive ? `toggleDia(${idProducto}, '${codigoSucursal}', ${dia.num})` : ''}">
                            ${tieneEntrega ? '<i class="bi bi-check-circle-fill"></i>' : ''}
                        </td>
                    `;
        }).join('')}
                <td>
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               ${isInactive ? '' : 'checked'} 
                               ${!puedeEditar ? 'disabled' : ''}
                               onchange="${puedeEditar ? `toggleStatus(${idProducto}, '${codigoSucursal}', this.checked)` : ''}">
                        <span class="toggle-slider ${!puedeEditar ? 'disabled' : ''}"></span>
                    </label>
                </td>
            </tr>
        `;
    }

    // Fila para agregar nuevo producto
    if (puedeEditar) {
        tableHtml += `
            <tr class="new-product-row">
                <td colspan="${diasSemana.length + 6}">
                    <select class="form-select product-search" 
                            id="product-search-${codigoSucursal}" 
                            style="width: 100%;">
                        <option value="">Buscar producto...</option>
                    </select>
                </td>
            </tr>
        `;
    }

    tableHtml += `
                </tbody>
            </table>
        </div>
    `;

    if (config.length === 0 && !puedeEditar) {
        tableHtml = `
            <div class="no-products-message">
                <i class="bi bi-inbox"></i>
                <p>No hay productos configurados para esta sucursal</p>
            </div>
        `;
    }

    $(`#table-container-${codigoSucursal}`).html(tableHtml);

    // Inicializar Select2 para búsqueda de productos
    if (puedeEditar) {
        inicializarBusquedaProducto(codigoSucursal);
    }
}

// Agrupar configuración por producto
function agruparPorProducto(config) {
    const agrupado = {};

    config.forEach(item => {
        if (!agrupado[item.id_producto_presentacion]) {
            agrupado[item.id_producto_presentacion] = {
                nombre: item.nombre_producto,
                dias: [],
                status: item.status,
                base_consumption: item.base_consumption,
                lead_time_days: item.lead_time_days,
                shelf_life_days: item.shelf_life_days,
                event_factor: item.event_factor
            };
        }
        agrupado[item.id_producto_presentacion].dias.push(parseInt(item.dia_entrega));
    });

    return agrupado;
}

// Inicializar búsqueda de producto con Select2
function inicializarBusquedaProducto(codigoSucursal) {
    $(`#product-search-${codigoSucursal}`).select2({
        theme: 'bootstrap-5',
        placeholder: 'Escriba para buscar producto...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: 'ajax/compra_local_configuracion_despacho_buscar_productos.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    search: params.term,
                    codigo_sucursal: codigoSucursal
                };
            },
            processResults: function (data) {
                if (data.success) {
                    return {
                        results: data.productos.map(p => ({
                            id: p.id,
                            text: `${p.Nombre} (${p.SKU})`
                        }))
                    };
                }
                return { results: [] };
            },
            cache: true
        }
    }).on('select2:select', function (e) {
        const idProducto = e.params.data.id;
        agregarProducto(idProducto, codigoSucursal);
        $(this).val(null).trigger('change');
    });
}

// Toggle día de entrega
function toggleDia(idProducto, codigoSucursal, dia) {
    if (!puedeEditar) return;

    const config = configuraciones[codigoSucursal] || [];
    const existe = config.find(c =>
        c.id_producto_presentacion == idProducto &&
        c.dia_entrega == dia
    );

    if (existe) {
        // Eliminar
        eliminarDiaEntrega(existe.id, codigoSucursal);
    } else {
        // Agregar
        agregarDiaEntrega(idProducto, codigoSucursal, dia);
    }
}

// Agregar día de entrega
function agregarDiaEntrega(idProducto, codigoSucursal, dia) {
    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_guardar.php',
        method: 'POST',
        data: {
            id_producto_presentacion: idProducto,
            codigo_sucursal: codigoSucursal,
            dia_entrega: dia
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarConfiguracion(codigoSucursal);
                mostrarExito('Día de entrega agregado');
            } else {
                mostrarError('Error al agregar día: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión');
        }
    });
}

// Eliminar día de entrega
function eliminarDiaEntrega(id, codigoSucursal) {
    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_eliminar.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarConfiguracion(codigoSucursal);
                mostrarExito('Día de entrega eliminado');
            } else {
                mostrarError('Error al eliminar día: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión');
        }
    });
}

// Actualizar campo de configuración
function updateField(idProducto, codigoSucursal, campo, valor) {
    if (!puedeEditar) return;

    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_update_field.php',
        method: 'POST',
        data: {
            id_producto_presentacion: idProducto,
            codigo_sucursal: codigoSucursal,
            campo: campo,
            valor: valor
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                // Actualizar en el objeto local
                if (configuraciones[codigoSucursal]) {
                    configuraciones[codigoSucursal].forEach(item => {
                        if (item.id_producto_presentacion == idProducto) {
                            item[campo] = valor;
                        }
                    });
                }
                mostrarExito('Configuración actualizada');
            } else {
                mostrarError('Error al actualizar: ' + response.message);
                cargarConfiguracion(codigoSucursal);
            }
        },
        error: function () {
            mostrarError('Error de conexión');
            cargarConfiguracion(codigoSucursal);
        }
    });
}

// Agregar producto nuevo
function toggleStatus(idProducto, codigoSucursal, activo) {
    if (!puedeEditar) return;

    const status = activo ? 'activo' : 'inactivo';

    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_toggle_status.php',
        method: 'POST',
        data: {
            id_producto_presentacion: idProducto,
            codigo_sucursal: codigoSucursal,
            status: status
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarConfiguracion(codigoSucursal);
                mostrarExito(`Producto ${activo ? 'activado' : 'desactivado'}`);
            } else {
                mostrarError('Error al cambiar estado: ' + response.message);
                cargarConfiguracion(codigoSucursal);
            }
        },
        error: function () {
            mostrarError('Error de conexión');
            cargarConfiguracion(codigoSucursal);
        }
    });
}

// Agregar producto nuevo
function agregarProducto(idProducto, codigoSucursal) {
    // Crear registro inicial sin días de entrega
    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_guardar.php',
        method: 'POST',
        data: {
            id_producto_presentacion: idProducto,
            codigo_sucursal: codigoSucursal,
            dia_entrega: 1, // Día inicial por defecto
            crear_nuevo: true
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarConfiguracion(codigoSucursal);
                mostrarExito('Producto agregado. Configure los días de entrega.');
            } else {
                mostrarError('Error al agregar producto: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexión');
        }
    });
}

// Mostrar mensaje de éxito
function mostrarExito(mensaje) {
    Swal.fire({
        icon: 'success',
        title: 'Éxito',
        text: mensaje,
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

// Mostrar mensaje de error
function mostrarError(mensaje) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje,
        confirmButtonColor: '#51B8AC'
    });
}
