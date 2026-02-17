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

let perfiles = [];

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
                perfiles = response.perfiles || [];
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
    const puedeEditar = true; // Simplificado para el ejemplo, usar el valor real del PHP si es necesario

    let tableHtml = `
        <div class="table-responsive">
            <table class="table config-table align-middle">
                <thead>
                    <tr class="table-dark">
                        <th style="width: 200px;">Producto</th>
                        ${diasSemana.map(d => `<th class="text-center" style="width: 40px;" title="${d.nombreCompleto}">${d.nombre}</th>`).join('')}
                        <th style="width: 80px;" title="Consumo base diario">Consumo</th>
                        <th style="width: 60px;" title="Días excedentes de seguridad">Cont.</th>
                        <th style="width: 60px;" title="Vida útil en días">Vida Útil</th>
                        <th style="width: 60px;">Estado</th>
                    </tr>
                </thead>
                <tbody>
    `;

    // Agrupar configuración por producto
    const agrupado = agruparPorProducto(config);

    Object.keys(agrupado).forEach(idProducto => {
        const item = agrupado[idProducto];
        const isInactive = item.status === 'inactivo';

        tableHtml += `
            <tr class="${isInactive ? 'inactive-row' : ''}" data-producto-id="${idProducto}">
                <td>
                    <div class="fw-bold text-primary">${item.nombre}</div>
                    <div class="x-small text-muted">${item.sku || ''}</div>
                </td>
                ${diasSemana.map(d => {
            const diaConfig = item.dias[d.num] || { is_delivery: 0, id: null };
            const isDelivery = diaConfig.is_delivery == 1;
            return `
                        <td class="text-center">
                            <span class="day-dot ${isDelivery ? 'active' : ''} ${!puedeEditar || isInactive ? 'readonly' : ''}" 
                                  onclick="${puedeEditar && !isInactive ? `toggleDelivery(${idProducto}, '${codigoSucursal}', ${d.num}, ${isDelivery ? 0 : 1}, ${diaConfig.id})` : ''}"
                                  title="${d.nombreCompleto}: ${isDelivery ? 'Despacho activo' : 'Sin despacho'}">
                            </span>
                        </td>
                    `;
        }).join('')}
                <td>
                    <input type="number" step="0.01" class="form-control form-control-sm" 
                           value="${item.base_consumption || 0}" 
                           ${!puedeEditar || isInactive ? 'disabled' : ''}
                           onchange="updateField(${idProducto}, '${codigoSucursal}', 'base_consumption', this.value)">
                </td>
                <td>
                    <input type="number" step="1" class="form-control form-control-sm" 
                           value="${item.lead_time_days || 0}" 
                           ${!puedeEditar || isInactive ? 'disabled' : ''}
                           onchange="updateField(${idProducto}, '${codigoSucursal}', 'lead_time_days', this.value)">
                </td>
                <td>
                    <input type="number" step="1" class="form-control form-control-sm" 
                           value="${item.shelf_life_days || 7}" 
                           ${!puedeEditar || isInactive ? 'disabled' : ''}
                           onchange="updateField(${idProducto}, '${codigoSucursal}', 'shelf_life_days', this.value)">
                </td>
                <td class="text-center">
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               ${isInactive ? '' : 'checked'} 
                               ${!puedeEditar ? 'disabled' : ''}
                               onchange="toggleStatus(${idProducto}, '${codigoSucursal}', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </td>
            </tr>
        `;
    });

    // Fila para agregar nuevo producto
    if (puedeEditar) {
        tableHtml += `
            <tr class="new-product-row">
                <td colspan="${diasSemana.length + 4}">
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
                dias: {}, // Ahora es objeto dia -> config
                status: item.status,
                lead_time_days: item.lead_time_days,
                shelf_life_days: item.shelf_life_days
            };
        }
        agrupado[item.id_producto_presentacion].dias[item.dia_entrega] = {
            id: item.id,
            is_delivery: item.is_delivery,
            base_consumption: item.base_consumption,
            event_factor: item.event_factor
        };
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
function toggleDelivery(idProducto, codigoSucursal, dia, nuevoEstado, id) {
    updateField(idProducto, codigoSucursal, 'is_delivery', nuevoEstado, id);
}

// Actualizar campo de configuración
function updateField(idProducto, codigoSucursal, campo, valor, id = null) {
    if (!puedeEditar) return;

    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_update_field.php',
        method: 'POST',
        data: {
            id: id,
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
                            if (id === null || item.id == id) {
                                item[campo] = valor;
                            }
                        }
                    });
                }
                mostrarExito('Configuración actualizada');

                // Si cambiamos is_delivery, refrescamos para actualizar iconos
                if (campo === 'is_delivery') {
                    renderizarTabla(codigoSucursal);
                }
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
