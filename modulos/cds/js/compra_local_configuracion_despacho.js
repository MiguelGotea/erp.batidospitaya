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
                        <th style="width: 100px;">Pedido M├¡n.</th>
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
                    <input type="number" 
                           class="form-control form-control-sm pedido-minimo-input" 
                           value="${datos.pedido_minimo || 1}" 
                           min="1" 
                           step="1"
                           ${!puedeEditar || isInactive ? 'disabled' : ''}
                           onchange="updatePedidoMinimo(${idProducto}, '${codigoSucursal}', this.value)">
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
                <td colspan="${diasSemana.length + 3}">
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
                dias: [],
                status: item.status,
                pedido_minimo: item.pedido_minimo
            };
        }
        agrupado[item.id_producto_presentacion].dias.push(parseInt(item.dia_entrega));
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

// Agregar d├¡a de entrega
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
                mostrarExito('D├¡a de entrega agregado');
            } else {
                mostrarError('Error al agregar d├¡a: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexi├│n');
        }
    });
}

// Eliminar d├¡a de entrega
function eliminarDiaEntrega(id, codigoSucursal) {
    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_eliminar.php',
        method: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                cargarConfiguracion(codigoSucursal);
                mostrarExito('D├¡a de entrega eliminado');
            } else {
                mostrarError('Error al eliminar d├¡a: ' + response.message);
            }
        },
        error: function () {
            mostrarError('Error de conexi├│n');
        }
    });
}

// Actualizar pedido m├¡nimo
function updatePedidoMinimo(idProducto, codigoSucursal, valor) {
    if (!puedeEditar) return;

    $.ajax({
        url: 'ajax/compra_local_configuracion_despacho_update_minimo.php',
        method: 'POST',
        data: {
            id_producto_presentacion: idProducto,
            codigo_sucursal: codigoSucursal,
            pedido_minimo: valor
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                // Actualizar en el objeto local para no recargar toda la tabla
                if (configuraciones[codigoSucursal]) {
                    configuraciones[codigoSucursal].forEach(item => {
                        if (item.id_producto_presentacion == idProducto) {
                            item.pedido_minimo = valor;
                        }
                    });
                }
                mostrarExito('Pedido m├¡nimo actualizado');
            } else {
                mostrarError('Error al actualizar pedido m├¡nimo: ' + response.message);
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
