/* ===================================================
   Configuraci├│n de Plan de Despacho - JavaScript
   =================================================== */

let sucursales = [];
let configuraciones = {};
let currentSucursal = null;

// D├¡as de la semana
const diasSemana = [
    { num: 1, nombre: 'L', nombreCompleto: 'Lunes' },
    { num: 2, nombre: 'M', nombreCompleto: 'Martes' },
    { num: 3, nombre: 'Mi', nombreCompleto: 'Mi├⌐rcoles' },
    { num: 4, nombre: 'J', nombreCompleto: 'Jueves' },
    { num: 5, nombre: 'V', nombreCompleto: 'Viernes' },
    { num: 6, nombre: 'S', nombreCompleto: 'S├íbado' },
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
            mostrarError('Error de conexi├│n al cargar sucursales');
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

// Cargar configuraci├│n de una sucursal
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
                mostrarError('Error al cargar configuraci├│n: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexi├│n al cargar configuraci├│n');
        }
    });
}

// Renderizar tabla de configuraci├│n
function renderizarTabla(codigoSucursal) {
    const config = configuraciones[codigoSucursal] || [];

    let tableHtml = `
        <div class="table-responsive">
            <table class="table config-table">
                <thead>
                    <tr>
                        <th style="width: 200px;">Producto</th>
                        <th style="width: 80px;" title="Pedido Mínimo">P. Mín.</th>
                        <th style="width: 60px;" title="Días excedentes de seguridad">Cont.</th>
                        <th style="width: 60px;" title="Vida útil en días">Vida Útil</th>
                        ${diasSemana.map(dia => `<th title="${dia.nombreCompleto}" class="text-center">${dia.nombre}</th>`).join('')}
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
                <td>
                    <div class="fw-bold text-dark">${datos.nombre}</div>
                </td>
                <td>
                    <input type="number" step="1" class="form-control form-control-sm" 
                           value="${datos.pedido_minimo || 1}" 
                           ${!puedeEditar || isInactive ? 'disabled' : ''}
                           onchange="updateField(${idProducto}, '${codigoSucursal}', 'pedido_minimo', this.value)">
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
                ${diasSemana.map(dia => {
            const configDia = datos.dias[dia.num] || { is_delivery: 0, base_consumption: 0, event_factor: 1 };
            const isDelivery = configDia.is_delivery == 1;

            return `
                    <td class="day-config-cell ${isDelivery ? 'delivery-active' : ''}">
                        <div class="d-flex flex-column align-items-center gap-1">
                            <button class="btn btn-sm ${isDelivery ? 'btn-success' : 'btn-outline-secondary'} delivery-toggle" 
                                    title="${isDelivery ? 'Día de entrega' : 'No es día de entrega'}"
                                    ${!puedeEditar || isInactive ? 'disabled' : ''}
                                    onclick="toggleDelivery(${idProducto}, '${codigoSucursal}', ${dia.num}, ${isDelivery ? 0 : 1})">
                                <i class="fas fa-truck text-white"></i>
                            </button>
                            
                            <div class="input-group input-group-xs" title="Consumo Base">
                                <span class="input-group-text">C:</span>
                                <input type="number" step="0.01" class="form-control daily-input input-consumption" 
                                       value="${configDia.base_consumption || 0}" 
                                       ${!puedeEditar || isInactive ? 'disabled' : ''}
                                       onchange="updateField(${idProducto}, '${codigoSucursal}', 'base_consumption', this.value, ${dia.num})">
                            </div>

                            <div class="input-group input-group-xs" title="Factor Evento">
                                <span class="input-group-text">F:</span>
                                <input type="number" step="0.1" class="form-control daily-input input-factor" 
                                       value="${configDia.event_factor || 1}" 
                                       ${!puedeEditar || isInactive ? 'disabled' : ''}
                                       onchange="updateField(${idProducto}, '${codigoSucursal}', 'event_factor', this.value, ${dia.num})">
                            </div>
                        </div>
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
                <td colspan="${diasSemana.length + 5}">
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

    // Inicializar Select2 para b├║squeda de productos
    if (puedeEditar) {
        inicializarBusquedaProducto(codigoSucursal);
    }
}

// Agrupar configuraci├│n por producto
function agruparPorProducto(config) {
    const agrupado = {};

    config.forEach(item => {
        if (!agrupado[item.id_producto_presentacion]) {
            agrupado[item.id_producto_presentacion] = {
                nombre: item.nombre_producto,
                sku: item.SKU,
                dias: {}, // Ahora es objeto dia -> config
                status: item.status,
                lead_time_days: item.lead_time_days,
                shelf_life_days: item.shelf_life_days,
                pedido_minimo: item.pedido_minimo
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

// Inicializar b├║squeda de producto con Select2
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

// Toggle d├¡a de entrega
function toggleDelivery(idProducto, codigoSucursal, dia, nuevoEstado) {
    updateField(idProducto, codigoSucursal, 'is_delivery', nuevoEstado, dia);
}

// Se eliminan funciones viejas de manejo de d├¡as individuales


// Actualizar campo de configuraci├│n
function updateField(idProducto, codigoSucursal, campo, valor, diaEntrega = null) {
    if (!puedeEditar) return;

    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_update_field.php',
        method: 'POST',
        data: {
            id_producto_presentacion: idProducto,
            codigo_sucursal: codigoSucursal,
            campo: campo,
            valor: valor,
            dia_entrega: diaEntrega
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                // Actualizar en el objeto local
                if (configuraciones[codigoSucursal]) {
                    configuraciones[codigoSucursal].forEach(item => {
                        if (item.id_producto_presentacion == idProducto) {
                            if (diaEntrega === null || item.dia_entrega == diaEntrega) {
                                item[campo] = valor;
                            }
                        }
                    });
                }
                mostrarExito('Configuraci├│n actualizada');

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
            mostrarError('Error de conexi├│n');
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
            mostrarError('Error de conexi├│n');
            cargarConfiguracion(codigoSucursal);
        }
    });
}

// Agregar producto nuevo
function agregarProducto(idProducto, codigoSucursal) {
    // Crear registro inicial sin d├¡as de entrega
    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_guardar.php',
        method: 'POST',
        data: {
            id_producto_presentacion: idProducto,
            codigo_sucursal: codigoSucursal,
            dia_entrega: 1, // D├¡a inicial por defecto
            crear_nuevo: true
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarConfiguracion(codigoSucursal);
                mostrarExito('Producto agregado. Configure los d├¡as de entrega.');
            } else {
                mostrarError('Error al agregar producto: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexi├│n');
        }
    });
}

// Mostrar mensaje de ├⌐xito
function mostrarExito(mensaje) {
    Swal.fire({
        icon: 'success',
        title: '├ëxito',
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
